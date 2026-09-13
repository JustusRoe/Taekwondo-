<?php
/**
 * Prüft, ob alle Dienste laufen und der Mitgliederbereich funktioniert.
 *
 * Aufruf:  php test/check.php [http://localhost:8080]
 * Rückgabe: 0 = alles in Ordnung, 1 = mindestens eine Prüfung fehlgeschlagen
 */
declare(strict_types=1);

$basis = rtrim($argv[1] ?? 'http://localhost:8080', '/');
$keks  = tempnam(sys_get_temp_dir(), 'tkd');

$bestanden = 0;
$fehler    = 0;

function anfrage(string $url, array $optionen = []): array
{
    global $keks;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => $keks,
        CURLOPT_COOKIEFILE     => $keks,
        CURLOPT_TIMEOUT        => 15,
    ] + $optionen);

    $antwort = curl_exec($ch);
    if ($antwort === false) {
        $meldung = curl_error($ch);
        curl_close($ch);
        return ['code' => 0, 'kopf' => '', 'inhalt' => '', 'fehler' => $meldung];
    }
    $kopfLaenge = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code'   => $code,
        'kopf'   => substr($antwort, 0, $kopfLaenge),
        'inhalt' => substr($antwort, $kopfLaenge),
        'fehler' => '',
    ];
}

function pruefe(string $was, bool $ok, string $hinweis = ''): void
{
    global $bestanden, $fehler;
    if ($ok) {
        $bestanden++;
        printf("  [ok]     %s\n", $was);
    } else {
        $fehler++;
        printf("  [FEHLER] %s%s\n", $was, $hinweis !== '' ? '  → ' . $hinweis : '');
    }
}

echo "Prüfung der Dienste auf $basis\n";
echo str_repeat('-', 62), "\n";

/* ---------- Öffentliche Website ---------- */
$a = anfrage($basis . '/index.html');
if ($a['code'] === 0) {
    echo "  [FEHLER] Kein Server erreichbar: {$a['fehler']}\n";
    echo "\n  Läuft der Server? → ./test/testmain.sh\n";
    exit(1);
}
pruefe('Startseite erreichbar', $a['code'] === 200, 'HTTP ' . $a['code']);

/* Die Startseite traegt die Inhalte nicht mehr selbst. Jedes Thema hat eine eigene
   Datei, erreichbar ueber die Navigationsleiste, die auf jeder Seite gleich ist. */
$unterseiten = ['training.html', 'angebot.html', 'abteilung.html', 'trainerteam.html',
                'galerie.html', 'downloads.html', 'kontakt.html'];
$fehlend = array_values(array_filter($unterseiten,
    static fn (string $seite): bool => !str_contains($a['inhalt'], $seite)));
pruefe('Startseite verlinkt alle Themenseiten', $fehlend === [],
    'fehlt: ' . implode(', ', $fehlend));

foreach ($unterseiten as $seite) {
    $u = anfrage($basis . '/' . $seite);
    pruefe("Themenseite $seite erreichbar", $u['code'] === 200, 'HTTP ' . $u['code']);
}

/* Trainingsplan und Terminliste stehen zusammen auf einer Seite. */
$a = anfrage($basis . '/training.html');
pruefe('Trainingsseite enthält den Trainingsplan',
    str_contains($a['inhalt'], 'Trainingsplan'));
pruefe('Trainingsseite listet alle Termine',
    substr_count($a['inhalt'], 'kal-eintrag') >= 30,
    substr_count($a['inhalt'], 'kal-eintrag') . ' Termine gefunden');

/* Die Verwaltung schreibt die Termine zwischen diese Markierungen zurück.
   Fehlen sie, bleibt die Website beim Speichern unverändert stehen. */
$a = anfrage($basis . '/training.html');
pruefe('Trainingsseite hat die Markierungen für die Terminverwaltung',
    str_contains($a['inhalt'], 'TERMINE:ANFANG') && str_contains($a['inhalt'], 'TERMINE:ENDE'));

$a = anfrage($basis . '/assets/js/trainingstermine.js');
pruefe('Termindatei hat die Markierungen für die Terminverwaltung',
    str_contains($a['inhalt'], 'TERMINE:ANFANG') && str_contains($a['inhalt'], 'TERMINE:ENDE'));

/* Die Startseite nennt den festen Wochenrhythmus und baut das nächste
   Training per JavaScript aus assets/js/trainingstermine.js auf. */
$a = anfrage($basis . '/index.html');
pruefe('Startseite nennt die beiden Trainingstage',
    str_contains($a['inhalt'], 'donnerstags und samstags'));
pruefe('Startseite bereitet das nächste Training vor',
    str_contains($a['inhalt'], 'data-modus="naechstes"'));

$a = anfrage($basis . '/downloads/mitgliedsformular.pdf');
pruefe('Mitgliedsformular wird ausgeliefert',
    $a['code'] === 200 && str_starts_with($a['inhalt'], '%PDF'));

/* ---------- Entwurf des Mitgliederbereichs ---------- */
$a = anfrage($basis . '/mitglieder.html');
pruefe('Entwurf: Anmeldeseite erreichbar', $a['code'] === 200);

$a = anfrage($basis . '/assets/video/taegeuk-il-jang.mp4',
    [CURLOPT_HTTPHEADER => ['Range: bytes=1000-1999'], CURLOPT_NOBODY => false]);
pruefe('Entwurf: Video lässt sich spulen (Range-Anfrage)',
    $a['code'] === 206 && str_contains($a['kopf'], 'Content-Range:'),
    'HTTP ' . $a['code']);

/* ---------- Serverfassung ---------- */
$a = anfrage($basis . '/backend/videothek.php');
pruefe('Server: Videothek ohne Anmeldung gesperrt',
    $a['code'] === 302 && str_contains($a['kopf'], 'login.php'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=taegeuk-il-jang');
pruefe('Server: Video ohne Anmeldung gesperrt', $a['code'] === 302, 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/termine.php');
pruefe('Server: Terminverwaltung ohne Anmeldung gesperrt',
    $a['code'] === 302 && str_contains($a['kopf'], 'login.php'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/konten.php');
pruefe('Server: Zugangsverwaltung ohne Anmeldung gesperrt',
    $a['code'] === 302 && str_contains($a['kopf'], 'login.php'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/passwort.php');
pruefe('Server: Passwortseite ohne Anmeldung gesperrt',
    $a['code'] === 302 && str_contains($a['kopf'], 'login.php'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/login.php');
pruefe('Server: Anmeldeseite erreichbar', $a['code'] === 200);

preg_match('/name="csrf" value="([^"]+)"/', $a['inhalt'], $t);
$token = $t[1] ?? '';
pruefe('Server: CSRF-Token im Formular', $token !== '');

/* Falsches Passwort */
$a = anfrage($basis . '/backend/login.php', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query(
        ['csrf' => $token, 'benutzer' => 'testuser', 'passwort' => 'falsch']),
]);
pruefe('Server: falsches Passwort wird abgewiesen',
    $a['code'] === 200 && str_contains($a['inhalt'], 'stimmen nicht'));

/* Richtiges Passwort */
$a = anfrage($basis . '/backend/login.php');
preg_match('/name="csrf" value="([^"]+)"/', $a['inhalt'], $t);
$a = anfrage($basis . '/backend/login.php', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query(
        ['csrf' => $t[1] ?? '', 'benutzer' => 'testuser', 'passwort' => 'test1234']),
]);
pruefe('Server: Anmeldung mit testuser erfolgreich',
    $a['code'] === 302 && str_contains($a['kopf'], 'videothek.php'),
    'HTTP ' . $a['code']);

/* Die erwartete Zahl kommt aus assets/js/videodaten.js, nicht als feste
   Zahl im Prüfskript: Kommen Videos dazu, soll die Prüfung nicht
   deswegen fehlschlagen. */
$js = (string) file_get_contents(__DIR__ . '/../assets/js/videodaten.js');
$erwartet = substr_count($js, '"slug":');

$a = anfrage($basis . '/backend/videothek.php');
$anzahl = substr_count($a['inhalt'], 'class="video-card"');
pruefe("Server: Videothek zeigt alle $erwartet Videos",
    $a['code'] === 200 && $anzahl === $erwartet, 'gefunden: ' . $anzahl);

/* Eine Reihe wird in ihrer Nummer gelernt – nach Datum stünde sie
   verkehrt herum. Geprüft wird, dass Teil 1 vor Teil 2 steht. */
$eins = strpos($a['inhalt'], 'hanbon-kyorugi-01');
$zwei = strpos($a['inhalt'], 'hanbon-kyorugi-02');
pruefe('Server: Videoreihe steht in ihrer Reihenfolge',
    $eins === false || $zwei === false || $eins < $zwei,
    'Teil 1 steht hinter Teil 2');

$a = anfrage($basis . '/backend/video.php?v=taegeuk-il-jang');
pruefe('Server: Videoseite zeigt den Player',
    $a['code'] === 200 && str_contains($a['inhalt'], 'id="player"'));

/* Geschützte Auslieferung */
$a = anfrage($basis . '/backend/stream.php?v=taegeuk-il-jang');
pruefe('Server: Video wird nach Anmeldung ausgeliefert',
    $a['code'] === 200 && str_contains($a['kopf'], 'Accept-Ranges: bytes'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=taegeuk-il-jang',
    [CURLOPT_HTTPHEADER => ['Range: bytes=100000-149999']]);
pruefe('Server: Spulen im geschützten Video (206 Partial Content)',
    $a['code'] === 206 && str_contains($a['kopf'], 'Content-Range: bytes 100000-149999'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=taegeuk-il-jang&f=webm');
pruefe('Server: Ausweichformat WebM wird ausgeliefert',
    $a['code'] === 200 && str_contains($a['kopf'], 'video/webm'), 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=taegeuk-il-jang&f=exe');
pruefe('Server: unbekanntes Format abgewiesen', $a['code'] === 400, 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=../../etc/passwd');
pruefe('Server: Pfadmanipulation abgewiesen', $a['code'] === 400, 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/admin.php');
pruefe('Server: Verwaltung für Mitglieder gesperrt', $a['code'] === 403, 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/konten.php');
pruefe('Server: Zugangsverwaltung für Mitglieder gesperrt', $a['code'] === 403, 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/upload.php', [CURLOPT_POST => true, CURLOPT_POSTFIELDS => []]);
pruefe('Server: Hochladen für Mitglieder gesperrt', $a['code'] === 403, 'HTTP ' . $a['code']);

/* Trainerkonto */
anfrage($basis . '/backend/logout.php');
$a = anfrage($basis . '/backend/login.php');
preg_match('/name="csrf" value="([^"]+)"/', $a['inhalt'], $t);
anfrage($basis . '/backend/login.php', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query(
        ['csrf' => $t[1] ?? '', 'benutzer' => 'testtrainer', 'passwort' => 'test1234']),
]);
$a = anfrage($basis . '/backend/admin.php');
pruefe('Server: Verwaltung für testtrainer erreichbar', $a['code'] === 200, 'HTTP ' . $a['code']);
pruefe('Server: Verwaltung bietet ein Feld zum Hochladen',
    str_contains($a['inhalt'], 'type="file"'));
preg_match('/name="csrf" value="([^"]+)"/', $a['inhalt'], $t);
$trainerToken = $t[1] ?? '';

$a = anfrage($basis . '/backend/konten.php');
pruefe('Server: Zugangsverwaltung für testtrainer erreichbar',
    $a['code'] === 200 && str_contains($a['inhalt'], 'testuser'), 'HTTP ' . $a['code']);

/* Hochladen: Anmeldung eines Uploads liefert eine Kennung */
$a = anfrage($basis . '/backend/upload.php', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query(['csrf' => $trainerToken, 'aktion' => 'start']),
]);
$start = json_decode($a['inhalt'], true);
pruefe('Server: Upload lässt sich anmelden',
    $a['code'] === 200 && isset($start['id']) && preg_match('/^[a-f0-9]{32}$/', $start['id']) === 1,
    'Antwort: ' . substr($a['inhalt'], 0, 80));

/* Ein Teilstück in falscher Reihenfolge muss abgelehnt werden – sonst
   entstünde aus vertauschten Stücken eine unbrauchbare Datei. */
$grenze = '----tkd' . bin2hex(random_bytes(8));
$koerper = '';
foreach (['csrf' => $trainerToken, 'id' => $start['id'] ?? '', 'versatz' => '999999'] as $feld => $wert) {
    $koerper .= "--$grenze\r\nContent-Disposition: form-data; name=\"$feld\"\r\n\r\n$wert\r\n";
}
$koerper .= "--$grenze\r\nContent-Disposition: form-data; name=\"stueck\"; filename=\"t.bin\"\r\n"
          . "Content-Type: application/octet-stream\r\n\r\nABCD\r\n--$grenze--\r\n";
$a = anfrage($basis . '/backend/upload.php', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => $koerper,
    CURLOPT_HTTPHEADER => ['Content-Type: multipart/form-data; boundary=' . $grenze],
]);
pruefe('Server: Teilstück in falscher Reihenfolge wird abgewiesen',
    $a['code'] === 409, 'HTTP ' . $a['code']);

/* Ohne CSRF-Token darf gar nichts durchgehen */
$a = anfrage($basis . '/backend/upload.php', [
    CURLOPT_POST       => true,
    CURLOPT_POSTFIELDS => http_build_query(['aktion' => 'start']),
]);
pruefe('Server: Hochladen ohne CSRF-Token abgewiesen', $a['code'] === 400, 'HTTP ' . $a['code']);

/* Abmelden */
anfrage($basis . '/backend/logout.php');
$a = anfrage($basis . '/backend/videothek.php');
pruefe('Server: nach dem Abmelden wieder gesperrt', $a['code'] === 302, 'HTTP ' . $a['code']);

@unlink($keks);

/* =========================================================
   Der Ordner website/

   Er liegt im Repository, damit man ihn ohne Werkzeuge herunterladen
   kann – und genau daraus entsteht eine Gefahr: Wird eine Seite
   geändert und der Ordner nicht neu gebaut, lädt jemand eine alte
   Fassung auf den Server und merkt es nicht. Die Prüfsumme in
   website/STAND.txt geht über die Quelldateien; stimmt sie nicht mehr,
   ist der Ordner veraltet.
   ========================================================= */
echo "\nAuslieferungsordner\n";

$wurzel = dirname(__DIR__);
$stand  = $wurzel . '/website/STAND.txt';

if (!is_file($stand)) {
    pruefe('Der Ordner website/ ist gebaut', false,
        'website/STAND.txt fehlt – einmal werkzeuge/paket.sh laufen lassen');
} else {
    $zeilen = parse_ini_file($stand) ?: [];
    $notiert = (string) ($zeilen['pruefsumme'] ?? '');
    $jetzt = trim((string) shell_exec(
        'bash ' . escapeshellarg($wurzel . '/werkzeuge/quellen-pruefsumme.sh') . ' 2>/dev/null'
    ));

    pruefe('Der Ordner website/ passt zu den Quelldateien',
        $jetzt !== '' && $notiert !== '' && $jetzt === $notiert,
        $jetzt === '' || $notiert === ''
            ? 'Prüfsumme nicht ermittelbar'
            : 'notiert ' . $notiert . ', jetzt ' . $jetzt
              . ' – einmal werkzeuge/paket.sh laufen lassen');

    // Die Stichproben fangen ab, was eine Prüfsumme nicht sieht: einen
    // Ordner, der zwar aktuell ist, aber unvollständig kopiert wurde.
    $fehlend = [];
    foreach (['www/index.html', 'www/training.html', 'www/.htaccess',
              'www/assets/css/style.css', 'www/assets/js/main.js',
              'www/backend/login.php', 'www/backend/einrichten.php',
              'www/backend/lib/db.php', 'datenbank/schema.sql',
              'LIESMICH.md'] as $datei) {
        if (!is_file($wurzel . '/website/' . $datei)) {
            $fehlend[] = $datei;
        }
    }
    pruefe('Der Ordner website/ ist vollständig', $fehlend === [],
        'fehlt: ' . implode(', ', $fehlend));

    $videos = glob($wurzel . '/website/videos-privat/hanbon-kyorugi-[0-9][0-9].mp4') ?: [];
    $bilder = glob($wurzel . '/website/www/assets/video/hanbon-kyorugi-[0-9][0-9].jpg') ?: [];
    pruefe('Die Videoreihe liegt vollständig im Ordner website/',
        count($videos) === 13 && count($bilder) === 13,
        count($videos) . ' Videos, ' . count($bilder) . ' Vorschaubilder');

    // Was dort nicht liegen darf. paket.sh prüft das beim Bauen auch,
    // aber der Ordner ist eingecheckt und könnte von Hand angefasst
    // worden sein.
    $verboten = [];
    foreach (['www/backend/config.php', 'www/mitglieder.html',
              'www/assets/js/mitglieder.js', 'www/assets/js/videodaten.js'] as $datei) {
        if (file_exists($wurzel . '/website/' . $datei)) {
            $verboten[] = $datei;
        }
    }
    pruefe('Im Ordner website/ liegt nichts, was dort nicht hingehört',
        $verboten === [], 'gefunden: ' . implode(', ', $verboten));
}

echo str_repeat('-', 62), "\n";
printf("%d Prüfungen bestanden, %d fehlgeschlagen\n", $bestanden, $fehler);
exit($fehler === 0 ? 0 : 1);
