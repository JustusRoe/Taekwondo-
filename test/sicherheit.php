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

/* ---------- Aufräumen ---------- */
db()->prepare('DELETE FROM mitglieder WHERE benutzername = ?')->execute(['pruefziel']);
sperren_aufheben();

echo "\n", str_repeat('-', 62), "\n";
printf("%d Prüfungen bestanden, %d fehlgeschlagen\n", $bestanden, $fehler);
exit($fehler > 0 ? 1 : 0);
