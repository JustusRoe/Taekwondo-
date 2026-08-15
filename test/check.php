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
pruefe('Startseite enthält die Trainingszeiten',
    str_contains($a['inhalt'], 'Trainingszeiten'));

$a = anfrage($basis . '/downloads/aufnahmeantrag.pdf');
pruefe('Download-PDF wird ausgeliefert',
    $a['code'] === 200 && str_starts_with($a['inhalt'], '%PDF'));

/* ---------- Entwurf des Mitgliederbereichs ---------- */
$a = anfrage($basis . '/mitglieder.html');
pruefe('Entwurf: Anmeldeseite erreichbar', $a['code'] === 200);

$a = anfrage($basis . '/assets/video/poomsae-taegeuk-il-jang.mp4',
    [CURLOPT_HTTPHEADER => ['Range: bytes=1000-1999'], CURLOPT_NOBODY => false]);
pruefe('Entwurf: Video lässt sich spulen (Range-Anfrage)',
    $a['code'] === 206 && str_contains($a['kopf'], 'Content-Range:'),
    'HTTP ' . $a['code']);

/* ---------- Serverfassung ---------- */
$a = anfrage($basis . '/backend/videothek.php');
pruefe('Server: Videothek ohne Anmeldung gesperrt',
    $a['code'] === 302 && str_contains($a['kopf'], 'login.php'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=poomsae-taegeuk-il-jang');
pruefe('Server: Video ohne Anmeldung gesperrt', $a['code'] === 302, 'HTTP ' . $a['code']);

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

$a = anfrage($basis . '/backend/videothek.php');
$anzahl = substr_count($a['inhalt'], 'class="video-card"');
pruefe('Server: Videothek zeigt sechs Videos',
    $a['code'] === 200 && $anzahl === 6, 'gefunden: ' . $anzahl);

$a = anfrage($basis . '/backend/video.php?v=poomsae-taegeuk-il-jang');
pruefe('Server: Videoseite mit vier Abschnitten',
    $a['code'] === 200 && substr_count($a['inhalt'], 'class="ch-time"') === 4);

/* Geschützte Auslieferung */
$a = anfrage($basis . '/backend/stream.php?v=poomsae-taegeuk-il-jang');
pruefe('Server: Video wird nach Anmeldung ausgeliefert',
    $a['code'] === 200 && str_contains($a['kopf'], 'Accept-Ranges: bytes'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=poomsae-taegeuk-il-jang',
    [CURLOPT_HTTPHEADER => ['Range: bytes=100000-149999']]);
pruefe('Server: Spulen im geschützten Video (206 Partial Content)',
    $a['code'] === 206 && str_contains($a['kopf'], 'Content-Range: bytes 100000-149999'),
    'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=poomsae-taegeuk-il-jang&f=webm');
pruefe('Server: Ausweichformat WebM wird ausgeliefert',
    $a['code'] === 200 && str_contains($a['kopf'], 'video/webm'), 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=poomsae-taegeuk-il-jang&f=exe');
pruefe('Server: unbekanntes Format abgewiesen', $a['code'] === 400, 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/stream.php?v=../../etc/passwd');
pruefe('Server: Pfadmanipulation abgewiesen', $a['code'] === 400, 'HTTP ' . $a['code']);

$a = anfrage($basis . '/backend/admin.php');
pruefe('Server: Verwaltung für Mitglieder gesperrt', $a['code'] === 403, 'HTTP ' . $a['code']);

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

/* Abmelden */
anfrage($basis . '/backend/logout.php');
$a = anfrage($basis . '/backend/videothek.php');
pruefe('Server: nach dem Abmelden wieder gesperrt', $a['code'] === 302, 'HTTP ' . $a['code']);

@unlink($keks);

echo str_repeat('-', 62), "\n";
printf("%d Prüfungen bestanden, %d fehlgeschlagen\n", $bestanden, $fehler);
exit($fehler === 0 ? 0 : 1);
