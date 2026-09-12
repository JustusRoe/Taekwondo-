<?php
/**
 * Schreibt die Einträge der Videothek für eine Videoreihe.
 *
 *   php werkzeuge/videodaten-erzeugen.php
 *
 * Liest die aufbereiteten Dateien aus videos-privat/, ermittelt Laufzeit
 * und Nummer aus dem Dateinamen und schreibt daraus den Abschnitt
 * "Hanbon Kyorugi" in assets/js/videodaten.js – zwischen den
 * Markierungen REIHE:ANFANG und REIHE:ENDE.
 *
 * Titel und Beschreibung bleiben dabei erhalten, wenn sie dort schon
 * stehen: Die Technikbezeichnungen kennt nur das Trainerteam, die werden
 * von Hand eingetragen und sollen ein zweiter Lauf nicht überschreiben.
 */
declare(strict_types=1);

const WURZEL = __DIR__ . '/..';
const KUERZEL = 'hanbon-kyorugi';
const BEREICH = 'Hanbon Kyorugi';
const DATEI = WURZEL . '/assets/js/videodaten.js';

/** Laufzeit in Sekunden, aus der Datei gelesen. */
function laufzeit(string $pfad): int
{
    $ff = getenv('FFMPEG') ?: trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));
    if ($ff === '') {
        $ff = trim((string) shell_exec(
            'python3 -c "import imageio_ffmpeg; print(imageio_ffmpeg.get_ffmpeg_exe())" 2>/dev/null'
        ));
    }
    if ($ff === '') {
        return 0;
    }
    $aus = (string) shell_exec(escapeshellarg($ff) . ' -hide_banner -i ' . escapeshellarg($pfad) . ' 2>&1');
    if (preg_match('/Duration: (\d+):(\d+):(\d+(?:\.\d+)?)/', $aus, $t)) {
        return (int) round((int) $t[1] * 3600 + (int) $t[2] * 60 + (float) $t[3]);
    }
    return 0;
}

/* Was schon in der Datei steht, damit eingetragene Titel bleiben. */
$inhalt = (string) file_get_contents(DATEI);
$vorhanden = [];
if (preg_match('~/\* REIHE:ANFANG.*?\*/(.*?)/\* REIHE:ENDE \*/~s', $inhalt, $m)) {
    $roh = json_decode('[' . rtrim(trim($m[1]), ',') . ']', true);
    foreach (is_array($roh) ? $roh : [] as $e) {
        $vorhanden[$e['slug'] ?? ''] = $e;
    }
}

$eintraege = [];
$dateien = glob(WURZEL . '/videos-privat/' . KUERZEL . '-[0-9][0-9].mp4') ?: [];
sort($dateien);

foreach ($dateien as $pfad) {
    $slug = basename($pfad, '.mp4');
    $nr = (int) substr($slug, -2);
    $alt = $vorhanden[$slug] ?? [];

    $eintraege[] = [
        'slug'         => $slug,
        'titel'        => $alt['titel'] ?? ('Einschrittkampf ' . $nr),
        'bereich'      => BEREICH,
        'grad'         => $alt['grad'] ?? 'Alle Grade',
        'trainer'      => $alt['trainer'] ?? '',
        'datum'        => $alt['datum'] ?? date('Y-m-d'),
        'dauer'        => laufzeit($pfad),
        'reihenfolge'  => $nr,
        'beschreibung' => $alt['beschreibung'] ?? '',
    ];
}

if (!$eintraege) {
    exit("Keine aufbereiteten Videos in videos-privat/ gefunden.\n"
       . "Erst werkzeuge/video-aufbereiten.sh laufen lassen.\n");
}

$zeilen = [];
foreach ($eintraege as $e) {
    $felder = [];
    foreach ($e as $schluessel => $wert) {
        $felder[] = '    ' . json_encode((string) $schluessel) . ': '
                  . json_encode($wert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $zeilen[] = "  {\n" . implode(",\n", $felder) . "\n  }";
}
$block = "\n" . implode(",\n", $zeilen) . ",\n";

$neu = preg_replace(
    '~(/\* REIHE:ANFANG[^*]*\*/).*?(/\* REIHE:ENDE \*/)~s',
    '$1' . str_replace('$', '\\$', $block) . '$2',
    $inhalt
);

if ($neu === null || $neu === $inhalt) {
    exit("FEHLER: Die Markierungen REIHE:ANFANG und REIHE:ENDE fehlen in\n"
       . "assets/js/videodaten.js – ohne sie weiss das Skript nicht, wo es\n"
       . "schreiben soll.\n");
}

file_put_contents(DATEI, $neu);
printf("%d Videos in assets/js/videodaten.js geschrieben:\n", count($eintraege));
foreach ($eintraege as $e) {
    printf("  %-22s Nr. %2d  %3d s  %s\n", $e['slug'], $e['reihenfolge'], $e['dauer'], $e['titel']);
}
echo "\nTitel und Beschreibungen bleiben bei einem zweiten Lauf erhalten.\n";
