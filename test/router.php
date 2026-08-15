<?php
/**
 * Router für den eingebauten PHP-Server.
 *
 * Der eingebaute Server (`php -S`) beantwortet keine Range-Requests für
 * statische Dateien – dadurch ließe sich in den Videos des Entwurfs nicht
 * spulen. Dieser Router liefert statische Dateien deshalb selbst aus und
 * beherrscht dabei Teilanfragen, so wie es Apache und Nginx später tun.
 *
 * PHP-Dateien werden an den Server zurückgegeben und normal ausgeführt.
 *
 * Aufruf:  php -S localhost:8080 -t . test/router.php
 */
declare(strict_types=1);

$pfad = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$pfad = rawurldecode($pfad);

if ($pfad === '/' || str_ends_with($pfad, '/')) {
    $pfad .= 'index.html';
}

$wurzel = realpath(__DIR__ . '/..');
$datei  = realpath($wurzel . $pfad);

// Ausbrüche aus dem Projektordner abweisen
if ($datei === false || !str_starts_with($datei, $wurzel) || !is_file($datei)) {
    return false;                       // Server antwortet mit 404
}

// PHP führt der eingebaute Server selbst aus
if (str_ends_with(strtolower($datei), '.php')) {
    return false;
}

$typen = [
    'html' => 'text/html; charset=utf-8',
    'css'  => 'text/css; charset=utf-8',
    'js'   => 'text/javascript; charset=utf-8',
    'json' => 'application/json',
    'svg'  => 'image/svg+xml',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'mp4'  => 'video/mp4',
    'webm' => 'video/webm',
    'pdf'  => 'application/pdf',
];
$endung = strtolower(pathinfo($datei, PATHINFO_EXTENSION));
$typ    = $typen[$endung] ?? 'application/octet-stream';
$groesse = filesize($datei);

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $typ);
header('Accept-Ranges: bytes');
header('Cache-Control: no-cache');

$start = 0;
$ende  = $groesse - 1;
$range = $_SERVER['HTTP_RANGE'] ?? '';

if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $t)) {
    if ($t[1] === '') {
        $start = max(0, $groesse - (int) $t[2]);
    } else {
        $start = (int) $t[1];
        if ($t[2] !== '') {
            $ende = min((int) $t[2], $groesse - 1);
        }
    }
    if ($start > $ende || $start >= $groesse) {
        header('Content-Range: bytes */' . $groesse);
        http_response_code(416);
        return true;
    }
    http_response_code(206);
    header(sprintf('Content-Range: bytes %d-%d/%d', $start, $ende, $groesse));
}

$laenge = $ende - $start + 1;
header('Content-Length: ' . $laenge);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    return true;
}

$zeiger = fopen($datei, 'rb');
fseek($zeiger, $start);
$rest = $laenge;
while ($rest > 0 && !feof($zeiger) && !connection_aborted()) {
    $block = fread($zeiger, (int) min(128 * 1024, $rest));
    if ($block === false || $block === '') {
        break;
    }
    echo $block;
    $rest -= strlen($block);
    flush();
}
fclose($zeiger);
return true;
