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
 *
 * Ausnahme: Steckt hinter einer Nummer inzwischen eine andere Aufnahme –
 * weil ein Video ersetzt oder die Reihenfolge geändert wurde –, dann wird
 * der Titel zurückgesetzt und gemeldet. Ein Technikname, der zum falschen
 * Video gehört, ist schlimmer als einer, der neu eingetragen werden muss.
 * Woran das erkannt wird: video-aufbereiten.sh schreibt neben die Videos
 * eine Datei <kuerzel>-herkunft.txt mit der Rohaufnahme je Nummer.
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

/** Rohaufnahme je Nummer, aus <kuerzel>-herkunft.txt. */
function herkunft(): array
{
    $datei = WURZEL . '/videos-privat/' . KUERZEL . '-herkunft.txt';
    if (!is_file($datei)) {
        return [];
    }
    $karte = [];
    foreach (file($datei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $zeile) {
        $teile = explode("\t", $zeile, 2);
        if (count($teile) === 2) {
            $karte[trim($teile[0])] = trim($teile[1]);
        }
    }
    return $karte;
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

$herkunft = herkunft();

$eintraege = [];
$zurueckgesetzt = [];
$dateien = glob(WURZEL . '/videos-privat/' . KUERZEL . '-[0-9][0-9].mp4') ?: [];
sort($dateien);

foreach ($dateien as $pfad) {
    $slug = basename($pfad, '.mp4');
    $nr = (int) substr($slug, -2);
    $alt = $vorhanden[$slug] ?? [];
    $quelle = $herkunft[$slug] ?? '';

    // Hinter der Nummer steckt eine andere Aufnahme als beim letzten Lauf:
    // Der eingetragene Technikname gehoert dann zum alten Video.
    if ($alt && $quelle !== '' && ($alt['herkunft'] ?? '') !== '' && $alt['herkunft'] !== $quelle) {
        $zurueckgesetzt[] = [$slug, (string) ($alt['titel'] ?? ''), $alt['herkunft'], $quelle];
        $alt = [];
    }

    $eintraege[] = [
        'slug'         => $slug,
        'titel'        => $alt['titel'] ?? ('Einschrittkampf ' . $nr),
        'bereich'      => BEREICH,
        'grad'         => $alt['grad'] ?? 'Alle Grade',
        'trainer'      => $alt['trainer'] ?? '',
        'datum'        => $alt['datum'] ?? date('Y-m-d'),
        'dauer'        => laufzeit($pfad),
        'reihenfolge'  => $nr,
        'herkunft'     => $quelle !== '' ? $quelle : ($alt['herkunft'] ?? ''),
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

if ($zurueckgesetzt) {
    echo "\nAchtung: Hinter diesen Nummern steckt jetzt eine andere Aufnahme.\n"
       . "Der Titel wurde zurueckgesetzt und muss neu eingetragen werden:\n\n";
    foreach ($zurueckgesetzt as [$slug, $titel, $vorher, $jetzt]) {
        printf("  %-22s hiess \"%s\"\n", $slug, $titel);
        printf("  %-22s vorher %s, jetzt %s\n\n", '', $vorher, $jetzt);
    }
}

if (!$herkunft) {
    echo "\nHinweis: " . KUERZEL . "-herkunft.txt fehlt neben den Videos.\n"
       . "Ohne diese Datei faellt nicht auf, wenn ein Video ersetzt wird und\n"
       . "der eingetragene Technikname danach zum falschen Video gehoert.\n"
       . "Sie entsteht, sobald video-aufbereiten.sh wieder laeuft.\n";
}

echo "\nTitel und Beschreibungen bleiben bei einem zweiten Lauf erhalten,\n"
   . "solange hinter der Nummer dieselbe Aufnahme steckt.\n";
