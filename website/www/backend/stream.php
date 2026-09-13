<?php
/**
 * Liefert eine Videodatei aus – aber nur an angemeldete Mitglieder.
 *
 * Die Datei liegt außerhalb des öffentlichen Ordners; ihre echte Adresse
 * bekommt der Browser nie zu sehen. Aufruf:  stream.php?v=slug
 *
 * Wichtig ist die Unterstützung von HTTP-Range-Requests: Nur damit kann
 * der Browser im Video springen (vor- und zurückspulen), ohne
 * es vorher komplett zu laden.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

anmeldung_verlangen();

$slug = (string) ($_GET['v'] ?? '');
if ($slug === '' || !preg_match('/^[a-z0-9-]{1,80}$/', $slug)) {
    http_response_code(400);
    exit('Ungültige Anfrage.');
}

$stmt = db()->prepare('SELECT dateiname FROM videos WHERE slug = ? AND sichtbar = 1');
$stmt->execute([$slug]);
$dateiname = $stmt->fetchColumn();

if ($dateiname === false) {
    http_response_code(404);
    exit('Video nicht gefunden.');
}

/**
 * Optionales Ausweichformat: stream.php?v=slug&f=webm
 *
 * MP4 (H.264) genügt für alle gängigen Browser. Liegt daneben eine
 * gleichnamige Datei in einem anderen Format, kann der Browser sie über
 * diesen Weg anfordern – nützlich etwa für Firefox-Installationen ohne
 * H.264. Erlaubt sind ausschließlich die hier aufgezählten Endungen.
 */
$format = strtolower((string) ($_GET['f'] ?? ''));
if ($format !== '') {
    if (!in_array($format, ['mp4', 'webm'], true)) {
        http_response_code(400);
        exit('Unbekanntes Format.');
    }
    $alternative = pathinfo((string) $dateiname, PATHINFO_FILENAME) . '.' . $format;
    if (!is_file(rtrim(konfiguration()['video_ordner'], '/') . '/' . basename($alternative))) {
        http_response_code(404);
        exit('Video in diesem Format nicht vorhanden.');
    }
    $dateiname = $alternative;
}

// basename() verhindert, dass ein manipulierter Datenbankeintrag
// auf Dateien außerhalb des Videoordners zeigen kann (../../).
$pfad = rtrim(konfiguration()['video_ordner'], '/') . '/' . basename((string) $dateiname);

if (!is_file($pfad) || !is_readable($pfad)) {
    error_log('Videodatei fehlt: ' . $pfad);
    http_response_code(404);
    exit('Video nicht gefunden.');
}

$groesse = filesize($pfad);
$typ     = match (strtolower(pathinfo($pfad, PATHINFO_EXTENSION))) {
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'mov'  => 'video/quicktime',
    default => 'application/octet-stream',
};

// Ausgabepuffer leeren, sonst landen große Dateien im Arbeitsspeicher
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $typ);
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=0, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . basename((string) $dateiname) . '"');

$start = 0;
$ende  = $groesse - 1;
$range = $_SERVER['HTTP_RANGE'] ?? '';

if ($range !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $treffer)) {
        header('Content-Range: bytes */' . $groesse);
        http_response_code(416);                 // Range Not Satisfiable
        exit;
    }

    if ($treffer[1] === '') {
        // „bytes=-500" → die letzten 500 Bytes
        $laenge = (int) $treffer[2];
        $start  = max(0, $groesse - $laenge);
    } else {
        $start = (int) $treffer[1];
        if ($treffer[2] !== '') {
            $ende = min((int) $treffer[2], $groesse - 1);
        }
    }

    if ($start > $ende || $start >= $groesse) {
        header('Content-Range: bytes */' . $groesse);
        http_response_code(416);
        exit;
    }

    http_response_code(206);                     // Partial Content
    header(sprintf('Content-Range: bytes %d-%d/%d', $start, $ende, $groesse));
}

$laenge = $ende - $start + 1;
header('Content-Length: ' . $laenge);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    exit;
}

$datei = fopen($pfad, 'rb');
if ($datei === false) {
    http_response_code(500);
    exit;
}

fseek($datei, $start);
$rest  = $laenge;
$block = 128 * 1024;                             // 128 KB je Durchgang

set_time_limit(0);
ignore_user_abort(false);

while ($rest > 0 && !feof($datei) && !connection_aborted()) {
    $daten = fread($datei, (int) min($block, $rest));
    if ($daten === false || $daten === '') {
        break;
    }
    echo $daten;
    $rest -= strlen($daten);
    flush();
}

fclose($datei);
