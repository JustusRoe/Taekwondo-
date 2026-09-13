<?php
/**
 * Nimmt das Kontaktformular entgegen und schickt es an die Abteilung.
 *
 * Die Seite kommt ohne Datenbank aus: Fällt sie aus, sollen
 * Probetrainingsanfragen trotzdem ankommen. Aus demselben Grund braucht
 * sie keine Sitzung – und damit auch kein CSRF-Token, das ohne Sitzung
 * ohnehin nichts prüfen könnte.
 *
 * Gegen Spam wirken drei Dinge ohne Captcha:
 *   1. ein Feld, das für Menschen unsichtbar ist (Honigtopf),
 *   2. eine Mindestzeit zwischen Aufruf und Absenden,
 *   3. eine Obergrenze je IP-Adresse und Stunde.
 *
 * Antwortet als JSON, wenn das Formular per JavaScript abgeschickt wurde,
 * sonst als schlichte Seite – so funktioniert es auch ohne JavaScript.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';

const MINDESTZEIT   = 3;      // Sekunden zwischen Aufruf und Absenden
const HOECHSTENS    = 5;      // Nachrichten je IP und Stunde
const SPERRFENSTER  = 3600;

$c = konfiguration();
$empfaenger = (string) ($c['kontakt_empfaenger'] ?? 'taekwondo@tv-steinau.de');
$absender   = (string) ($c['kontakt_absender']   ?? $empfaenger);

/** Kam die Anfrage über fetch()? Dann JSON zurückgeben. */
function will_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function antwort(bool $ok, string $text): never
{
    if (will_json()) {
        http_response_code($ok ? 200 : 400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'text' => $text], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code($ok ? 200 : 400);
    header('Content-Type: text/html; charset=utf-8');
    $titel = $ok ? 'Nachricht verschickt' : 'Nachricht nicht verschickt';
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex">'
       . '<title>' . h($titel) . ' – Taekwondo im TV 1897 Steinau e.V.</title>'
       . '<link rel="stylesheet" href="../assets/css/style.css"></head><body>'
       . '<main class="legal"><div class="container legal-inner">'
       . '<h1>' . h($titel) . '</h1><p>' . h($text) . '</p>'
       . '<a class="back-link" href="../kontakt.html">&larr; Zurück zum Kontaktformular</a>'
       . '</div></main></body></html>';
    exit;
}

/**
 * Zählt die Nachrichten je IP in einer Datei mit.
 *
 * Bewusst ohne Datenbank: Das Kontaktformular soll auch dann noch
 * funktionieren, wenn die Datenbank streikt.
 */
function zu_viele(): bool
{
    $datei = sys_get_temp_dir() . '/tkd-kontakt-'
           . substr(hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''), 0, 32) . '.txt';

    $zeiten = is_file($datei)
        ? array_map('intval', explode(',', (string) file_get_contents($datei)))
        : [];
    $zeiten = array_filter($zeiten, static fn ($z) => $z > time() - SPERRFENSTER);

    if (count($zeiten) >= HOECHSTENS) {
        return true;
    }
    $zeiten[] = time();
    @file_put_contents($datei, implode(',', $zeiten), LOCK_EX);
    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    antwort(false, 'Diese Adresse nimmt nur abgeschickte Formulare entgegen.');
}

/* ---------- Spamschutz ---------- */
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    // Der Honigtopf ist im Browser unsichtbar. Wer ihn ausfüllt, ist keiner.
    // Für den Absender sieht es nach Erfolg aus, damit Bots nichts lernen.
    antwort(true, 'Vielen Dank für deine Nachricht.');
}

$geladen = (int) ($_POST['geladen'] ?? 0);
if ($geladen > 0 && time() - $geladen < MINDESTZEIT) {
    antwort(false, 'Das ging sehr schnell. Bitte schick das Formular noch einmal ab.');
}

if (zu_viele()) {
    antwort(false, 'Von diesem Anschluss kamen in der letzten Stunde schon mehrere '
                 . 'Nachrichten. Bitte melde dich direkt unter taekwondo@tv-steinau.de.');
}

/* ---------- Eingaben prüfen ---------- */
/**
 * Macht aus einer Formulareingabe eine Zeile ohne Steuerzeichen.
 *
 * Zeilenumbrüche in einer Kopfzeile wären eine Einladung, fremde
 * Empfänger unterzuschieben. Deshalb werden sie schon beim Einlesen
 * entfernt und nicht erst beim Zusammenbauen der Mail.
 */
function eine_zeile(string $wert): string
{
    $wert = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $wert) ?? $wert;
    return trim(preg_replace('/\s{2,}/u', ' ', $wert) ?? $wert);
}

$name    = eine_zeile((string) ($_POST['name'] ?? ''));
$email   = eine_zeile((string) ($_POST['email'] ?? ''));
$telefon = eine_zeile((string) ($_POST['phone'] ?? ''));
$thema   = eine_zeile((string) ($_POST['topic'] ?? 'Sonstiges'));
$text    = trim((string) ($_POST['message'] ?? ''));

if (mb_strlen($name) < 2) {
    antwort(false, 'Bitte gib deinen Namen an.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    antwort(false, 'Diese E-Mail-Adresse sieht nicht vollständig aus.');
}
if (mb_strlen($text) < 10) {
    antwort(false, 'Bitte beschreibe dein Anliegen in mindestens 10 Zeichen.');
}
if (empty($_POST['privacy'])) {
    antwort(false, 'Bitte bestätige die Datenschutzhinweise.');
}

$betreff = 'Website: ' . $thema . ' – ' . $name;

/* Der Anzeigename kommt in Anführungszeichen; ein Name mit Komma oder
   Doppelpunkt bringt die Kopfzeile sonst durcheinander. */
$anzeigename = '"' . str_replace(['\\', '"'], '', $name) . '"';

$inhalt = "Über das Kontaktformular der Website\n"
        . str_repeat('-', 52) . "\n\n"
        . "Name:     $name\n"
        . "E-Mail:   $email\n"
        . ($telefon !== '' ? "Telefon:  $telefon\n" : '')
        . "Anliegen: $thema\n"
        . 'Datum:    ' . date('d.m.Y H:i') . "\n\n"
        . str_repeat('-', 52) . "\n\n"
        . $text . "\n";

$kopf = [
    'From: Taekwondo Website <' . eine_zeile($absender) . '>',
    'Reply-To: ' . $anzeigename . ' <' . $email . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . PHP_VERSION,
];

/* In der Testumgebung gibt es keinen Mailversand. Ist ein Ablageordner
   eingetragen, landet die Nachricht dort als Datei statt im Postausgang –
   so lässt sich der ganze Weg prüfen, ohne echte Mails zu verschicken.
   Im Betrieb fehlt der Eintrag und es wird wirklich verschickt. */
$ablage = (string) ($c['kontakt_ablage'] ?? '');

if ($ablage !== '') {
    if (!is_dir($ablage)) {
        @mkdir($ablage, 0770, true);
    }
    $verschickt = (bool) @file_put_contents(
        rtrim($ablage, '/') . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt',
        'An: ' . $empfaenger . "\n"
        . 'Betreff: ' . $betreff . "\n"
        . implode("\n", $kopf) . "\n\n" . $inhalt
    );
} else {
    $verschickt = @mail(
        $empfaenger,
        '=?UTF-8?B?' . base64_encode($betreff) . '?=',
        $inhalt,
        implode("\r\n", $kopf)
    );
}

if (!$verschickt) {
    error_log('Kontaktformular: mail() ist fehlgeschlagen.');
    antwort(false, 'Die Nachricht konnte gerade nicht verschickt werden. '
                 . 'Bitte schreib uns direkt an ' . $empfaenger . '.');
}

antwort(true, 'Vielen Dank, ' . explode(' ', $name)[0]
            . '. Deine Nachricht ist angekommen – wir melden uns zurück.');

