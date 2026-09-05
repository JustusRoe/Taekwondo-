<?php
/**
 * Richtet die Testumgebung ein.
 *
 * Legt an:
 *   test/daten/test.db        – SQLite-Datenbank als Ersatz für MySQL
 *   test/videos-privat/       – Videoablage außerhalb des Web-Ordners
 *   backend/config.php        – Konfiguration, die auf beides zeigt
 *
 * Aufruf:  php test/setup.php  [--neu]
 *   --neu   löscht eine vorhandene Testdatenbank vorher
 *
 * Für den Echtbetrieb gilt weiterhin backend/schema.sql (MySQL).
 */
declare(strict_types=1);

const WURZEL = __DIR__ . '/..';

/* ---------- Testkonten ---------- */
const KONTEN = [
    ['testuser',    'Test Nutzer',   'testuser@example.de',    'test1234', 'mitglied'],
    ['testtrainer', 'Test Trainer',  'testtrainer@example.de', 'test1234', 'trainer'],
];

$neu = in_array('--neu', $argv ?? [], true);

echo "Testumgebung wird eingerichtet\n";
echo str_repeat('-', 52), "\n";

/* ---------- 1. Ordner ---------- */
foreach ([__DIR__ . '/daten', __DIR__ . '/videos-privat'] as $ordner) {
    if (!is_dir($ordner) && !mkdir($ordner, 0775, true)) {
        exit("FEHLER: Ordner $ordner konnte nicht angelegt werden.\n");
    }
}

/* ---------- 2. Videodateien in die private Ablage ---------- */
$quelle = WURZEL . '/assets/video';
$ziel   = __DIR__ . '/videos-privat';
$kopiert = 0;
foreach (glob($quelle . '/*.{mp4,webm}', GLOB_BRACE) ?: [] as $datei) {
    $nach = $ziel . '/' . basename($datei);
    if (!is_file($nach) || filesize($nach) !== filesize($datei)) {
        copy($datei, $nach);
        $kopiert++;
    }
}
printf("  Videoablage      %d Dateien (%d neu kopiert)\n", count(glob($ziel . '/*') ?: []), $kopiert);

/* ---------- 3. Datenbank ---------- */
$dbDatei = __DIR__ . '/daten/test.db';
if ($neu && is_file($dbDatei)) {
    unlink($dbDatei);
    echo "  Datenbank        alte Datei gelöscht\n";
}

$pdo = new PDO('sqlite:' . $dbDatei, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');

/* Dieselben Tabellen wie backend/schema.sql, in SQLite-Schreibweise. */
$pdo->exec('CREATE TABLE IF NOT EXISTS mitglieder (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    benutzername TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    email TEXT,
    passwort_hash TEXT NOT NULL,
    rolle TEXT NOT NULL DEFAULT "mitglied",
    aktiv INTEGER NOT NULL DEFAULT 1,
    angelegt_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    letzter_login TEXT,
    passwort_wechseln INTEGER NOT NULL DEFAULT 1,
    passwort_geaendert_am TEXT
)');
/* Bestehende Testdatenbanken nachziehen – SQLite meldet einen Fehler,
   wenn es die Spalte schon gibt; der ist hier folgenlos. */
foreach (['passwort_wechseln INTEGER NOT NULL DEFAULT 1',
          'passwort_geaendert_am TEXT'] as $spalte) {
    try {
        $pdo->exec('ALTER TABLE mitglieder ADD COLUMN ' . $spalte);
    } catch (PDOException $e) {
        // Spalte ist bereits vorhanden
    }
}
$pdo->exec('CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    titel TEXT NOT NULL,
    bereich TEXT NOT NULL,
    grad TEXT NOT NULL DEFAULT "Alle Grade",
    trainer TEXT NOT NULL DEFAULT "",
    beschreibung TEXT,
    dateiname TEXT NOT NULL,
    posterdatei TEXT,
    dauer INTEGER NOT NULL DEFAULT 0,
    veroeffentlicht_am TEXT NOT NULL,
    sichtbar INTEGER NOT NULL DEFAULT 1
)');
$pdo->exec('CREATE TABLE IF NOT EXISTS trainingstermine (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    datum TEXT NOT NULL,
    zeit TEXT NOT NULL DEFAULT "",
    gruppe TEXT NOT NULL DEFAULT "",
    ort TEXT NOT NULL DEFAULT "steines",
    hinweis TEXT NOT NULL DEFAULT "",
    geaendert_am TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (datum, zeit)
)');
$pdo->exec('CREATE TABLE IF NOT EXISTS login_versuche (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    benutzername TEXT NOT NULL,
    ip BLOB NOT NULL,
    zeitpunkt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)');

/* ---------- 4. Testkonten ---------- */
/* passwort_wechseln = 0: Die Testkonten gelten als eingerichtet, sonst
   landet jeder Testlauf zuerst auf passwort.php. Frisch angelegte Konten
   im Echtbetrieb starten dagegen mit 1. */
$anlegen = $pdo->prepare(
    'INSERT INTO mitglieder (benutzername, name, email, passwort_hash, rolle, passwort_wechseln)
     VALUES (?, ?, ?, ?, ?, 0)'
);
$aendern = $pdo->prepare(
    'UPDATE mitglieder SET name = ?, email = ?, passwort_hash = ?, rolle = ?, aktiv = 1,
            passwort_wechseln = 0
      WHERE benutzername = ?'
);
$vorhanden = $pdo->prepare('SELECT COUNT(*) FROM mitglieder WHERE benutzername = ?');

foreach (KONTEN as [$benutzer, $name, $mail, $passwort, $rolle]) {
    // Der Hash wird hier erzeugt, nicht fest eingetragen –
    // so steht das Passwort nirgends im Klartext in der Datenbank.
    $hash = password_hash($passwort, PASSWORD_DEFAULT);
    $vorhanden->execute([$benutzer]);
    if ((int) $vorhanden->fetchColumn() > 0) {
        $aendern->execute([$name, $mail, $hash, $rolle, $benutzer]);
        printf("  Konto            %-12s aktualisiert (%s)\n", $benutzer, $rolle);
    } else {
        $anlegen->execute([$benutzer, $name, $mail, $hash, $rolle]);
        printf("  Konto            %-12s angelegt (%s)\n", $benutzer, $rolle);
    }
}

/* ---------- 5. Videos aus assets/js/videodaten.js ---------- */
$js = file_get_contents(WURZEL . '/assets/js/videodaten.js');
$von = strpos($js, '[');
$bis = strrpos($js, ']');
$daten = ($von !== false && $bis !== false)
    ? json_decode(substr($js, $von, $bis - $von + 1), true)
    : null;

if (!is_array($daten)) {
    exit("FEHLER: assets/js/videodaten.js konnte nicht gelesen werden.\n");
}

$pdo->exec('DELETE FROM videos');

$video = $pdo->prepare(
    'INSERT INTO videos (slug, titel, bereich, grad, trainer, beschreibung,
                         dateiname, posterdatei, dauer, veroeffentlicht_am)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
foreach ($daten as $d) {
    // Der Testbrowser spielt notfalls WebM ab; sonst MP4 wie im Echtbetrieb.
    $datei = is_file($ziel . '/' . $d['slug'] . '.mp4')
        ? $d['slug'] . '.mp4'
        : $d['slug'] . '.webm';

    $video->execute([
        $d['slug'], $d['titel'], $d['bereich'], $d['grad'], $d['trainer'],
        $d['beschreibung'], $datei, $d['slug'] . '.jpg', $d['dauer'], $d['datum'],
    ]);
}
printf("  Videothek        %d Videos\n", count($daten));

/* ---------- 6. Trainingstermine aus assets/js/trainingstermine.js ---------- */
$js = (string) file_get_contents(WURZEL . '/assets/js/trainingstermine.js');
$von = strpos($js, 'window.TRAININGSTERMINE = [');
$termine = null;
if ($von !== false) {
    $von = strpos($js, '[', $von);
    $bis = strpos($js, '];', (int) $von);
    if ($von !== false && $bis !== false) {
        // Die Kommentarzeilen der Markierungen stören json_decode.
        $roh = substr($js, (int) $von, $bis - (int) $von + 1);
        $roh = preg_replace('#/\*.*?\*/#s', '', $roh) ?? $roh;
        $termine = json_decode($roh, true);
    }
}

if (!is_array($termine)) {
    exit("FEHLER: assets/js/trainingstermine.js konnte nicht gelesen werden.\n");
}

$pdo->exec('DELETE FROM trainingstermine');
$termin = $pdo->prepare(
    'INSERT INTO trainingstermine (datum, zeit, gruppe, ort, hinweis)
     VALUES (?, ?, ?, ?, ?)'
);
foreach ($termine as $t) {
    $termin->execute([
        $t['datum'], $t['zeit'], $t['gruppe'], $t['ort'], $t['hinweis'] ?? '',
    ]);
}
printf("  Termine          %d Trainingstermine\n", count($termine));

/* ---------- 7. Konfiguration für das Backend ---------- */
$config = <<<'PHP'
<?php
/**
 * AUTOMATISCH ERZEUGT von test/setup.php – nur für den lokalen Test.
 *
 * Diese Datei nutzt SQLite statt MySQL, damit kein Datenbankserver
 * nötig ist. Für den Echtbetrieb config.example.php als Vorlage nehmen
 * und die MySQL-Zugangsdaten des Hosters eintragen.
 */
return [
    'db' => [
        'dsn'      => 'sqlite:' . __DIR__ . '/../test/daten/test.db',
        'benutzer' => null,
        'passwort' => null,
    ],
    'video_ordner'    => __DIR__ . '/../test/videos-privat',
    'poster_ordner'   => __DIR__ . '/../assets/video',
    'poster_url'      => '/assets/video/',
    'max_video_mb'    => 800,
    'max_versuche'    => 5,
    'sperrminuten'    => 15,
    'sitzung_minuten' => 180,
];
PHP;

file_put_contents(WURZEL . '/backend/config.php', $config . "\n");
echo "  Konfiguration    backend/config.php geschrieben (SQLite)\n";

echo str_repeat('-', 52), "\n";
echo "Fertig.\n\n";
echo "Testzugänge (Passwort jeweils: test1234)\n";
foreach (KONTEN as [$benutzer, $name, , , $rolle]) {
    printf("  %-12s %-14s %s\n", $benutzer, $rolle, $name);
}
