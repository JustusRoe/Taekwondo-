<?php
/**
 * Prüft die Schutzmechanismen des Mitgliederbereichs.
 *
 * Jede Prüfung versucht das, wogegen geschützt werden soll, und erwartet,
 * dass es scheitert. Nach jedem Durchlauf räumt das Skript die Spuren
 * wieder weg (Sperren, angelegte Testkonten), damit es beliebig oft
 * laufen kann.
 *
 * Aufruf:  php test/sicherheit.php [http://localhost:8080]
 * Rückgabe: 0 = alles in Ordnung, 1 = mindestens eine Prüfung fehlgeschlagen
 */
declare(strict_types=1);

$basis = rtrim($argv[1] ?? 'http://localhost:8080', '/');

require_once __DIR__ . '/../backend/lib/db.php';
require_once __DIR__ . '/../backend/lib/verwaltung.php';

$bestanden = 0;
$fehler    = 0;

function pruefe(string $was, bool $ok, string $hinweis = ''): void
{
    global $bestanden, $fehler;
    if ($ok) {
        $bestanden++;
        echo "  [ok]     $was\n";
    } else {
        $fehler++;
        printf("  [FEHLER] %s%s\n", $was, $hinweis !== '' ? '  → ' . $hinweis : '');
    }
}

/** Eine Anfrage mit eigenem Keksglas. */
function anfrage(string $url, array $post = null, string $keks = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($keks !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $keks);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $keks);
    }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $roh  = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $teil = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['code' => $code, 'kopf' => substr($roh, 0, $teil), 'inhalt' => substr($roh, $teil)];
}

function csrf(string $inhalt): string
{
    preg_match('/name="csrf" value="([^"]+)"/', $inhalt, $t);
    return $t[1] ?? '';
}

/**
 * Meldet sich über HTTP an und gibt die Keksdatei zurück, sonst ''.
 * Heißt bewusst nicht anmelden() – so heißt schon die Funktion in
 * backend/lib/auth.php, die weiter unten mit eingebunden wird.
 */
function einloggen(string $basis, string $benutzer, string $passwort): string
{
    $keks = tempnam(sys_get_temp_dir(), 'tkd');
    $a = anfrage($basis . '/backend/login.php', null, $keks);
    $a = anfrage($basis . '/backend/login.php',
        ['csrf' => csrf($a['inhalt']), 'benutzer' => $benutzer, 'passwort' => $passwort], $keks);
    return $a['code'] === 302 ? $keks : '';
}

function sperren_aufheben(): void
{
    db()->exec('DELETE FROM login_versuche');
}

/**
 * Ein einzelner Wert aus der Datenbank.
 *
 * Der Lesevorgang wird sofort geschlossen. Ohne closeCursor() hält SQLite
 * ihn offen und blockiert damit das nächste eigene Schreiben mit
 * "database is locked" – ein Fallstrick, der erst auffällt, wenn Lesen
 * und Schreiben sich abwechseln.
 */
function einWert(string $sql, array $werte = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($werte);
    $wert = $stmt->fetchColumn();
    $stmt->closeCursor();
    return $wert;
}

echo "Sicherheitsprüfung auf $basis\n";
echo str_repeat('-', 62), "\n";

$a = anfrage($basis . '/backend/login.php');
if ($a['code'] === 0) {
    echo "  [FEHLER] Kein Server erreichbar.\n\n  Läuft er? → ./test/testmain.sh\n";
    exit(1);
}

sperren_aufheben();

/* ---------- 1. Sperre nach zu vielen Fehlversuchen für ein Konto ---------- */
echo "\nSperre je Benutzername\n";
$c = konfiguration();
for ($i = 0; $i < (int) $c['max_versuche']; $i++) {
    $keks = tempnam(sys_get_temp_dir(), 'tkd');
    $s = anfrage($basis . '/backend/login.php', null, $keks);
    anfrage($basis . '/backend/login.php',
        ['csrf' => csrf($s['inhalt']), 'benutzer' => 'testuser', 'passwort' => 'falsch' . $i], $keks);
}
$keks = tempnam(sys_get_temp_dir(), 'tkd');
$s = anfrage($basis . '/backend/login.php', null, $keks);
$a = anfrage($basis . '/backend/login.php',
    ['csrf' => csrf($s['inhalt']), 'benutzer' => 'testuser', 'passwort' => 'test1234'], $keks);
pruefe('Nach ' . (int) $c['max_versuche'] . ' Fehlversuchen greift die Sperre – '
     . 'auch das richtige Passwort kommt nicht durch',
    $a['code'] === 200 && str_contains($a['inhalt'], 'Fehlversuche'));

sperren_aufheben();
pruefe('Nach dem Ablauf der Sperre geht die Anmeldung wieder',
    einloggen($basis, 'testuser', 'test1234') !== '');

/* ---------- 2. Sperre je IP-Adresse ---------- */
echo "\nSperre je IP-Adresse (gegen das Durchprobieren vieler Benutzernamen)\n";
sperren_aufheben();
$grenze = (int) ($c['max_versuche_ip'] ?? 20);
for ($i = 0; $i < $grenze; $i++) {
    $keks = tempnam(sys_get_temp_dir(), 'tkd');
    $s = anfrage($basis . '/backend/login.php', null, $keks);
    // Jedes Mal ein anderer Benutzername: Die Sperre je Konto greift nie.
    anfrage($basis . '/backend/login.php',
        ['csrf' => csrf($s['inhalt']), 'benutzer' => 'nutzer' . $i, 'passwort' => 'raten'], $keks);
}
$hoechste = (int) einWert(
    'SELECT MAX(anzahl) FROM (SELECT COUNT(*) AS anzahl FROM login_versuche GROUP BY benutzername) t'
);
pruefe("$grenze Fehlversuche verteilt auf $grenze Benutzernamen – "
     . "je Name nur $hoechste, die Kontosperre griffe also nie",
    $hoechste < (int) $c['max_versuche']);

$keks = tempnam(sys_get_temp_dir(), 'tkd');
$s = anfrage($basis . '/backend/login.php', null, $keks);
$a = anfrage($basis . '/backend/login.php',
    ['csrf' => csrf($s['inhalt']), 'benutzer' => 'testuser', 'passwort' => 'test1234'], $keks);
pruefe('Die IP-Sperre greift trotzdem und lässt auch das richtige Passwort nicht durch',
    $a['code'] === 200 && str_contains($a['inhalt'], 'Fehlversuche'),
    'HTTP ' . $a['code']);

sperren_aufheben();

/* ---------- 3. Passwortregeln ---------- */
echo "\nPasswortregeln\n";
pruefe('Mitglied: sieben Zeichen werden abgelehnt',
    passwort_pruefen('kurz123', 'mitglied') !== '');
pruefe('Mitglied: acht Zeichen sind in Ordnung',
    passwort_pruefen('Halbwegs', 'mitglied') === '');
pruefe('Trainer: acht Zeichen reichen nicht mehr',
    passwort_pruefen('Halbwegs', 'trainer') !== '');
pruefe('Trainer: zwölf Zeichen sind in Ordnung',
    passwort_pruefen('Kiesel-Wolke-4711', 'trainer') === '');
pruefe('Der Benutzername darf nicht im Passwort stehen',
    passwort_pruefen('m.buchhold-2026', 'mitglied', 'm.buchhold') !== '');
pruefe('Offensichtliche Passwörter werden abgelehnt',
    passwort_pruefen('TaekwondoSteinau', 'mitglied') !== '');
pruefe('Das vorgeschlagene Startpasswort erfüllt die Trainervorgabe',
    passwort_pruefen(passwort_vorschlag(), 'trainer') === '');

/* ---------- 4. Trainerzugänge sind besonders geschützt ---------- */
echo "\nSchutz der Trainerzugänge\n";
$keks = einloggen($basis, 'testtrainer', 'test1234');
if ($keks === '') {
    pruefe('Anmeldung als Trainer', false, 'ohne sie sind die weiteren Prüfungen sinnlos');
} else {
    // Ein aktives Trainerkonto zum Üben anlegen
    db()->prepare('DELETE FROM mitglieder WHERE benutzername = ?')->execute(['pruefziel']);
    db()->prepare(
        'INSERT INTO mitglieder (benutzername, name, email, passwort_hash, rolle, aktiv)
         VALUES (?, ?, ?, ?, ?, 1)'
    )->execute(['pruefziel', 'Prüfziel Trainer', null,
                password_hash('Kiesel-Wolke-4711', PASSWORD_DEFAULT), 'trainer']);
    $id = (int) einWert("SELECT id FROM mitglieder WHERE benutzername = 'pruefziel'");

    $s = anfrage($basis . '/backend/konten.php', null, $keks);
    $t = csrf($s['inhalt']);

    $a = anfrage($basis . '/backend/konten.php',
        ['csrf' => $t, 'aktion' => 'loeschen', 'id' => $id, 'bestaetigung' => 'test1234'], $keks);
    pruefe('Ein aktiver Trainerzugang lässt sich nicht löschen',
        str_contains($a['inhalt'], 'erst löschen, wenn sie stillgelegt'));

    db()->prepare('UPDATE mitglieder SET aktiv = 0 WHERE id = ?')->execute([$id]);

    $a = anfrage($basis . '/backend/konten.php',
        ['csrf' => $t, 'aktion' => 'loeschen', 'id' => $id, 'bestaetigung' => 'falsch'], $keks);
    pruefe('Stillgelegt, aber ohne das eigene Passwort geht es immer noch nicht',
        str_contains($a['inhalt'], 'eigene Passwort bestätigen'));

    $a = anfrage($basis . '/backend/konten.php',
        ['csrf' => $t, 'aktion' => 'loeschen', 'id' => $id, 'bestaetigung' => 'test1234'], $keks);
    pruefe('Stillgelegt und mit eigenem Passwort bestätigt: jetzt geht es',
        (int) einWert('SELECT COUNT(*) FROM mitglieder WHERE id = ?', [$id]) === 0);

    // Das eigene Konto bleibt geschützt
    $eigene = (int) einWert("SELECT id FROM mitglieder WHERE benutzername = 'testtrainer'");
    $a = anfrage($basis . '/backend/konten.php',
        ['csrf' => $t, 'aktion' => 'loeschen', 'id' => $eigene, 'bestaetigung' => 'test1234'], $keks);
    pruefe('Das eigene Trainerkonto lässt sich nicht löschen',
        (int) einWert('SELECT COUNT(*) FROM mitglieder WHERE id = ?', [$eigene]) === 1);

    $a = anfrage($basis . '/backend/konten.php',
        ['csrf' => $t, 'aktion' => 'aendern', 'id' => $eigene, 'name' => 'Test Trainer',
         'rolle' => 'mitglied', 'bestaetigung' => 'test1234'], $keks);
    pruefe('Das eigene Konto lässt sich nicht selbst abstufen',
        einWert('SELECT rolle FROM mitglieder WHERE id = ?', [$eigene]) === 'trainer');

    /* ---------- 5. Erzwungener Wechsel des Startpassworts ---------- */
    echo "\nStartpasswort muss gewechselt werden\n";
    sperren_aufheben();
    $k2 = einloggen($basis, 'ai.kaempf', 'Kiesel-Wolke-4711');
    if ($k2 === '') {
        pruefe('Anmeldung mit Startpasswort', false, 'Demokonto ai.kaempf fehlt – test/setup.php laufen lassen');
    } else {
        $a = anfrage($basis . '/backend/videothek.php', null, $k2);
        pruefe('Mit offenem Startpasswort führt die Videothek auf die Passwortseite',
            $a['code'] === 302 && str_contains($a['kopf'], 'passwort.php'));

        $a = anfrage($basis . '/backend/konten.php', null, $k2);
        pruefe('Auch die Verwaltung ist bis dahin gesperrt',
            $a['code'] === 302 && str_contains($a['kopf'], 'passwort.php'));
    }
}

/* ---------- 6. Kontaktformular ---------- */
echo "\nKontaktformular\n";
$ziel = $basis . '/backend/kontakt.php';
$alt  = time() - 60;

// Das Formular lässt fünf Nachrichten je Stunde und Adresse zu. Die
// Prüfungen darunter schicken mehr – deshalb den Zähler vorher leeren.
foreach (glob(sys_get_temp_dir() . '/tkd-kontakt-*.txt') ?: [] as $z) {
    @unlink($z);
}

$a = anfrage($ziel, ['name' => 'Bot', 'email' => 'bot@example.de',
    'message' => 'Nachricht eines Bots, lang genug fuer die Pruefung.',
    'privacy' => '1', 'geladen' => $alt, 'website' => 'http://spam.example']);
pruefe('Wer den Honigtopf ausfüllt, bekommt keine Fehlermeldung zu sehen',
    $a['code'] === 200);

$a = anfrage($ziel, ['name' => 'Erika', 'email' => 'erika@example.de',
    'message' => 'Ich haette gern einen Termin zum Probetraining.',
    'privacy' => '1', 'geladen' => time()]);
pruefe('Zu schnell abgeschickte Formulare werden abgewiesen',
    $a['code'] === 400 && str_contains($a['inhalt'], 'sehr schnell'));

$a = anfrage($ziel, ['name' => 'Erika', 'email' => 'keine-adresse',
    'message' => 'Ich haette gern einen Termin zum Probetraining.',
    'privacy' => '1', 'geladen' => $alt]);
pruefe('Eine unvollständige E-Mail-Adresse wird abgewiesen', $a['code'] === 400);

$a = anfrage($ziel, ['name' => 'Erika', 'email' => 'erika@example.de',
    'message' => 'Ich haette gern einen Termin zum Probetraining.',
    'geladen' => $alt]);
pruefe('Ohne Datenschutz-Zustimmung wird abgewiesen',
    $a['code'] === 400 && str_contains($a['inhalt'], 'Datenschutz'));

$a = anfrage($ziel, ['name' => "Erika\nBcc: opfer@example.com",
    'email' => 'erika@example.de',
    'message' => 'Ich haette gern einen Termin zum Probetraining.',
    'privacy' => '1', 'geladen' => $alt]);
$ablage = konfiguration()['kontakt_ablage'] ?? '';
$zuletzt = '';
if ($ablage && is_dir($ablage)) {
    $dateien = glob(rtrim($ablage, '/') . '/*.txt') ?: [];
    if ($dateien) {
        usort($dateien, static fn ($x, $y) => filemtime($y) <=> filemtime($x));
        $zuletzt = (string) file_get_contents($dateien[0]);
    }
}
pruefe('Eingeschleuste Kopfzeilen landen nicht als eigene Kopfzeile',
    $zuletzt !== '' && !preg_match('/^Bcc:/mi', $zuletzt));

$a = anfrage($ziel);
pruefe('Ein Aufruf ohne abgeschicktes Formular wird abgewiesen',
    $a['code'] === 400 && str_contains($a['inhalt'], 'nur abgeschickte Formulare'));

/* ---------- 7. Eingaben der Verwaltung landen nicht als Markup ---------- */
echo "\nTermintexte auf der öffentlichen Website\n";

/* Gruppe und Hinweis tippt das Trainerteam ein und beide landen auf der
   öffentlichen Website. Werden sie dort als HTML eingesetzt statt als
   Text, läuft ein eingetipptes <script> im Browser jedes Besuchers.

   Die Prüfung ist absichtlich eine Textsuche in den Dateien: Ein Skript
   ohne Browser kann nicht feststellen, was der Kalender am Ende baut.
   Sie schlägt an, wenn jemand wieder auf innerHTML umstellt. */
$js = (string) file_get_contents(__DIR__ . '/../assets/js/main.js');
$von = strpos($js, 'function eintragEl');
$bis = $von !== false ? strpos($js, "\n      }", $von) : false;
$bauteil = ($von !== false && $bis !== false) ? substr($js, $von, $bis - $von) : '';

pruefe('Der Kalender der Startseite baut seine Zeilen per DOM, nicht aus HTML-Text',
    $bauteil !== '' && !str_contains($bauteil, 'innerHTML'),
    $bauteil === '' ? 'eintragEl() nicht gefunden' : 'innerHTML ist zurück');

/* Die feste Liste auf training.html wird serverseitig erzeugt – dort muss
   jeder eingetippte Wert durch h() gehen. */
$php = (string) file_get_contents(__DIR__ . '/../backend/lib/termine.php');
$von = strpos($php, 'function html_block');
$bis = $von !== false ? strpos($php, "\n}", $von) : false;
$block = ($von !== false && $bis !== false) ? substr($php, $von, $bis - $von) : '';

$ungeschuetzt = [];
foreach (['gruppe', 'hinweis', 'zeit'] as $feld) {
    if (preg_match('/\$t\[\x27' . $feld . '\x27\]/', $block)
        && !preg_match('/h\(\$t\[\x27' . $feld . '\x27\]\)/', $block)) {
        $ungeschuetzt[] = $feld;
    }
}
pruefe('Die Terminliste auf training.html maskiert alle eingetippten Felder',
    $block !== '' && $ungeschuetzt === [],
    $ungeschuetzt ? 'ohne h(): ' . implode(', ', $ungeschuetzt) : 'html_block() nicht gefunden');

/* =========================================================
   Einrichtungsseite und Zugangsliste

   Beides ist neu und heikel: einrichten.php legt ein Trainerkonto ohne
   jede Anmeldung an, und die Zugangsliste zeigt Startpasswörter im
   Klartext. Die Sperren dafür gehören geprüft.
   ========================================================= */
echo "\nEinrichtungsseite und Zugangsliste\n";

$konten = (int) einWert('SELECT COUNT(*) FROM mitglieder');

$a = anfrage($basis . '/backend/einrichten.php');
pruefe('einrichten.php ist gesperrt, solange es Konten gibt',
    $konten > 0 && str_contains($a['inhalt'], 'Schon eingerichtet')
    && !str_contains($a['inhalt'], 'name="passwort"'),
    'Antwort: ' . substr(strip_tags($a['inhalt']), 0, 120));

// Der Riegel muss auch für POST gelten – ein Formular lässt sich
// nachbauen, die Sperre darf nicht nur die Anzeige betreffen.
$a = anfrage($basis . '/backend/einrichten.php', [
    'csrf' => csrf($a['inhalt']), 'name' => 'Eindringling',
    'benutzer' => 'eindringling', 'passwort' => 'Ahorn-Kranich-Segel-11',
    'wiederholen' => 'Ahorn-Kranich-Segel-11',
]);
pruefe('einrichten.php legt auch per POST kein zweites Konto an',
    (int) einWert('SELECT COUNT(*) FROM mitglieder WHERE benutzername = ?', ['eindringling']) === 0);

$keksMitglied = einloggen($basis, 'testuser', 'test1234');

/* Ein angemeldetes Mitglied bekommt 403 – es ist angemeldet, darf aber
   nicht in die Verwaltung. Wer gar nicht angemeldet ist, wird auf die
   Anmeldung geschickt (302). Beides ist eine Sperre. */
$gesperrt = static fn (array $a): bool => in_array($a['code'], [302, 403], true);

$a = anfrage($basis . '/backend/zugangsliste.php', null, $keksMitglied);
pruefe('Die Zugangsliste ist für Mitglieder gesperrt',
    $keksMitglied !== '' && $gesperrt($a)
    && !str_contains($a['inhalt'], 'zl-passwort'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/zugangsliste.php?csv=1', null, $keksMitglied);
pruefe('Auch die CSV-Ausgabe der Zugangsliste ist für Mitglieder gesperrt',
    $keksMitglied !== '' && $gesperrt($a)
    && !str_contains($a['kopf'], 'text/csv'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/admin.php', ['aktion' => 'vorhandene'], $keksMitglied);
pruefe('Das Eintragen vorhandener Videodateien ist für Mitglieder gesperrt',
    $keksMitglied !== '' && $gesperrt($a), 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/zugangsliste.php');
pruefe('Ohne Anmeldung führt die Zugangsliste auf die Anmeldung',
    $a['code'] === 302, 'HTTP ' . $a['code']);

// Ohne offene Liste darf die Seite nichts zeigen – sie liegt in der
// Sitzung, ein Trainerkonto allein macht sie nicht sichtbar.
$keksTrainer = einloggen($basis, 'testtrainer', 'test1234');
$a = anfrage($basis . '/backend/zugangsliste.php', null, $keksTrainer);
pruefe('Ohne angelegte Zugänge zeigt die Liste keine Passwörter',
    $keksTrainer !== '' && str_contains($a['inhalt'], 'keine Liste offen'));

// Die Sammelanlage vergibt Benutzernamen selbst. Steht einer davon schon
// in der Datenbank, muss sie ausweichen statt den vorhandenen Zugang zu
// ueberschreiben.
//
// Den Zusammenstoss stellt die Pruefung selbst her. Sich darauf zu
// verlassen, dass ein passender Demozugang in der Testdatenbank liegt,
// war ein Fehler: Der Zugang a.roeder stammte aus einem frueheren Lauf,
// und in einer frischen Datenbank gab es ihn nicht - die Pruefung schlug
// dann fehl, obwohl am Programm nichts falsch war.
db()->prepare('DELETE FROM mitglieder WHERE benutzername IN (?, ?)')
    ->execute(['a.roeder', 'a.roeder2']);
db()->prepare(
    'INSERT INTO mitglieder (benutzername, name, email, passwort_hash, rolle, aktiv)
     VALUES (?, ?, ?, ?, ?, 1)'
)->execute(['a.roeder', 'Aileen Röder', null,
            password_hash('Kiesel-Wolke-4711', PASSWORD_DEFAULT), 'mitglied']);

$a = anfrage($basis . '/backend/konten.php', null, $keksTrainer);
$hashVorher = (string) einWert('SELECT passwort_hash FROM mitglieder WHERE benutzername = ?',
    ['a.roeder']);
$sammel = anfrage($basis . '/backend/konten.php', [
    'csrf' => csrf($a['inhalt']), 'aktion' => 'sammel', 'rolle' => 'mitglied',
    'liste' => "Aileen Röder\nPruef Neuling",
], $keksTrainer);

/* Beim Fehlschlag muss ablesbar sein, woran es lag: keine Anmeldung,
   kein Token, eine Fehlermeldung der Seite oder etwas anderes. */
$warum = 'HTTP ' . $sammel['code'] . ', Konto a.roeder vorher: '
       . ($hashVorher !== '' ? 'ja' : 'NEIN')
       . ', Anmeldung: ' . ($keksTrainer !== '' ? 'ja' : 'NEIN')
       . ', Token: ' . (csrf($a['inhalt']) !== '' ? 'ja' : 'NEIN');
if ($sammel['code'] === 200
    && preg_match('~ist-fehler[^>]*>(?:<strong>)?(.*?)(?:</strong>)?</p>~s', $sammel['inhalt'], $t)) {
    $warum .= ', Meldung: ' . trim(strip_tags($t[1]));
}

pruefe('Sammelanlage weicht bei einem vergebenen Benutzernamen aus',
    (int) einWert('SELECT COUNT(*) FROM mitglieder WHERE benutzername = ?', ['a.roeder2']) === 1,
    $warum);
pruefe('Der vorhandene Zugang bleibt dabei unberührt',
    $hashVorher !== ''
    && $hashVorher === (string) einWert(
        'SELECT passwort_hash FROM mitglieder WHERE benutzername = ?', ['a.roeder']));

// Die Zugangsliste steht danach – und nur mit den zwei neuen Zugängen,
// nicht mit allen Konten der Datenbank.
$a = anfrage($basis . '/backend/zugangsliste.php', null, $keksTrainer);
pruefe('Die Zugangsliste zeigt genau die neu angelegten Zugänge',
    substr_count($a['inhalt'], '<tr>') === 3     // Kopfzeile plus zwei
    && str_contains($a['inhalt'], 'a.roeder2')
    && str_contains($a['inhalt'], 'p.neuling'),
    'Zeilen: ' . substr_count($a['inhalt'], '<tr>'));

// In der Datenbank darf kein Startpasswort im Klartext liegen.
$klartexte = (int) einWert(
    'SELECT COUNT(*) FROM mitglieder WHERE passwort_hash NOT LIKE ?', ['$2y$%']);
pruefe('Alle Passwörter liegen als bcrypt-Hash in der Datenbank',
    $klartexte === 0, $klartexte . ' Konten ohne bcrypt-Hash');

/* ---------- Aufräumen ---------- */
db()->prepare('DELETE FROM mitglieder WHERE benutzername IN (?, ?, ?, ?, ?)')
    ->execute(['pruefziel', 'eindringling', 'a.roeder', 'a.roeder2', 'p.neuling']);
sperren_aufheben();
foreach (glob(sys_get_temp_dir() . '/tkd-kontakt-*.txt') ?: [] as $z) {
    @unlink($z);
}

echo "\n", str_repeat('-', 62), "\n";
printf("%d Prüfungen bestanden, %d fehlgeschlagen\n", $bestanden, $fehler);
exit($fehler > 0 ? 1 : 0);
