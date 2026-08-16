<?php
/**
 * Nimmt Videodateien stückweise entgegen.
 *
 * Warum stückweise? Ein Video hat schnell mehrere hundert Megabyte. Ein
 * normaler Formular-Upload müsste in einem Rutsch durch – daran scheitern bei
 * Standard-Hosting reihenweise Grenzen: upload_max_filesize, post_max_size,
 * max_execution_time. Der Browser zerlegt die Datei deshalb in Stücke von
 * wenigen Megabyte und schickt sie nacheinander. Jedes Stück ist eine kurze,
 * kleine Anfrage. Nebenbei lässt sich so ein Fortschritt anzeigen.
 *
 * Die Stücke landen in <video_ordner>/.uploads/<id>.part. Erst wenn das
 * Formular in admin.php abgeschickt wird, bekommt die Datei ihren endgültigen
 * Namen – abgeleitet aus dem geprüften Kürzel, nie aus einer Eingabe.
 *
 * Antwortet immer mit JSON.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/verwaltung.php';

header('Content-Type: application/json; charset=utf-8');

function antwort(array $daten, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($daten, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Nur angemeldete Trainer – und ohne Weiterleitung, weil hier JSON erwartet wird. */
if (!angemeldet() || aktuelles_mitglied()['rolle'] !== 'trainer') {
    antwort(['fehler' => 'Nicht angemeldet oder keine Berechtigung.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    antwort(['fehler' => 'Nur POST.'], 405);
}
csrf_pruefen();

$aktion = (string) ($_POST['aktion'] ?? 'stueck');

/* ---------------------------------------------------------------
   Aufräumen: liegengebliebene Stücke älter als einen Tag entfernen.
   Passiert nebenbei bei jedem Upload-Start, damit kein Cronjob nötig ist.
   --------------------------------------------------------------- */
if ($aktion === 'start') {
    foreach (glob(upload_ordner() . '/*.part') ?: [] as $alt) {
        if (filemtime($alt) < time() - 86400) {
            @unlink($alt);
        }
    }
    antwort([
        'id'         => bin2hex(random_bytes(16)),
        'stueck'     => 2 * 1024 * 1024,      // 2 MB je Anfrage
        'max_bytes'  => max_video_bytes(),
    ]);
}

$id = (string) ($_POST['id'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
    antwort(['fehler' => 'Ungültige Upload-Kennung.'], 400);
}

/* ---------------------------------------------------------------
   Vorschaubild: ein Standbild, das der Browser aus dem Video
   geschnitten hat. Kommt in einem Rutsch – ein JPEG ist klein.
   --------------------------------------------------------------- */
if ($aktion === 'poster') {
    if (!isset($_FILES['bild']) || $_FILES['bild']['error'] !== UPLOAD_ERR_OK) {
        antwort(['fehler' => 'Das Vorschaubild kam nicht an.'], 400);
    }
    if ($_FILES['bild']['size'] > 3 * 1024 * 1024) {
        antwort(['fehler' => 'Das Vorschaubild ist zu groß.'], 413);
    }
    // Nur echte Bilder annehmen – der Dateiname des Browsers zählt nicht.
    $masse = @getimagesize($_FILES['bild']['tmp_name']);
    if (!$masse || $masse[2] !== IMAGETYPE_JPEG) {
        antwort(['fehler' => 'Das Vorschaubild ist kein JPEG.'], 400);
    }
    if (!move_uploaded_file($_FILES['bild']['tmp_name'], upload_ordner() . '/' . $id . '.jpg')) {
        antwort(['fehler' => 'Das Vorschaubild konnte nicht gespeichert werden.'], 500);
    }
    antwort(['breite' => $masse[0], 'hoehe' => $masse[1]]);
}

/* ---------------------------------------------------------------
   Ein Stück der Videodatei entgegennehmen
   --------------------------------------------------------------- */

if (!isset($_FILES['stueck']) || $_FILES['stueck']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['stueck']['error'] ?? -1;
    antwort(['fehler' => 'Das Teilstück kam nicht an (Fehlercode ' . $code . ').'], 400);
}

$ziel = upload_ordner() . '/' . $id . '.part';
$bisher = is_file($ziel) ? filesize($ziel) : 0;

/* Der Browser sagt, an welcher Stelle das Stück beginnt. Stimmt das nicht mit
   der bisherigen Dateigröße überein, ist etwas durcheinandergeraten – dann
   lieber abbrechen als eine kaputte Datei zusammensetzen. */
$versatz = (int) ($_POST['versatz'] ?? -1);
if ($versatz !== $bisher) {
    antwort(['fehler' => 'Die Teilstücke kamen in falscher Reihenfolge an.',
             'erwartet' => $bisher], 409);
}

$groesse = (int) $_FILES['stueck']['size'];
if ($bisher + $groesse > max_video_bytes()) {
    @unlink($ziel);
    antwort(['fehler' => 'Die Datei ist größer als '
             . (int) konfiguration()['max_video_mb'] . ' MB.'], 413);
}

$inhalt = file_get_contents($_FILES['stueck']['tmp_name']);
if ($inhalt === false || file_put_contents($ziel, $inhalt, FILE_APPEND | LOCK_EX) === false) {
    antwort(['fehler' => 'Das Teilstück konnte nicht gespeichert werden.'], 500);
}

antwort(['geschrieben' => $bisher + $groesse]);
