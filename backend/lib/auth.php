<?php
/**
 * Anmeldung, Sitzung und Zugriffsschutz.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function sitzung_starten(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $sicher = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,          // kein Zugriff über JavaScript
        'secure'   => $sicher,       // nur über HTTPS ausliefern
        'samesite' => 'Lax',
    ]);
    session_name('tkd_mitglieder');
    session_start();

    // Sitzung nach Untätigkeit beenden
    $grenze = (int) konfiguration()['sitzung_minuten'] * 60;
    if (isset($_SESSION['zuletzt']) && time() - $_SESSION['zuletzt'] > $grenze) {
        abmelden();
    }
    $_SESSION['zuletzt'] = time();
}

function angemeldet(): bool
{
    sitzung_starten();
    return !empty($_SESSION['mitglied_id']);
}

function aktuelles_mitglied(): ?array
{
    if (!angemeldet()) {
        return null;
    }
    return [
        'id'    => (int) $_SESSION['mitglied_id'],
        'name'  => (string) ($_SESSION['name'] ?? ''),
        'rolle' => (string) ($_SESSION['rolle'] ?? 'mitglied'),
        'passwort_wechseln' => !empty($_SESSION['passwort_wechseln']),
    ];
}

/** Auf geschützten Seiten ganz oben aufrufen. */
function anmeldung_verlangen(): array
{
    if (!angemeldet()) {
        $ziel = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: login.php?weiter=' . urlencode($ziel));
        exit;
    }
    // Konten mit Startpasswort kommen erst weiter, wenn sie ein eigenes
    // gesetzt haben. Der Aufruf steht hier, damit keine geschützte Seite
    // ihn vergessen kann.
    eigenes_passwort_verlangen();
    return aktuelles_mitglied();
}

function trainer_verlangen(): array
{
    $m = anmeldung_verlangen();
    if ($m['rolle'] !== 'trainer') {
        http_response_code(403);
        exit('Dieser Bereich ist dem Trainerteam vorbehalten.');
    }
    return $m;
}

/**
 * Solange ein Konto noch das Startpasswort hat, fuehrt jede geschuetzte
 * Seite zuerst auf passwort.php. Das Startpasswort kennt immer auch die
 * Person, die den Zugang angelegt hat – danach nur noch das Mitglied selbst.
 */
function eigenes_passwort_verlangen(): void
{
    $m = aktuelles_mitglied();
    if ($m && $m['passwort_wechseln'] && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'passwort.php') {
        header('Location: passwort.php?erstmalig=1');
        exit;
    }
}

/**
 * Prueft das Passwort des angemeldeten Kontos noch einmal.
 *
 * Wer angemeldet an einem fremden Rechner sitzt, soll damit keine
 * Trainerzugaenge loeschen oder umstellen koennen. Fehlversuche zaehlen
 * mit, damit auch hier nicht geraten werden kann.
 */
function eigenes_passwort_stimmt(int $mitgliedId, string $passwort): bool
{
    if ($passwort === '') {
        return false;
    }
    $stmt = db()->prepare('SELECT benutzername, passwort_hash FROM mitglieder WHERE id = ?');
    $stmt->execute([$mitgliedId]);
    $m = $stmt->fetch();
    if (!is_array($m)) {
        return false;
    }
    if (!password_verify($passwort, (string) $m['passwort_hash'])) {
        versuch_vermerken((string) $m['benutzername']);
        return false;
    }
    return true;
}

/** Zählt Fehlversuche, um Passwortraten auszubremsen. */
function gesperrt(string $benutzername): bool
{
    $c = konfiguration();
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM login_versuche
          WHERE benutzername = ? AND zeitpunkt > ?'
    );
    $grenze = date('Y-m-d H:i:s', time() - (int) $c['sperrminuten'] * 60);
    $stmt->execute([$benutzername, $grenze]);
    return (int) $stmt->fetchColumn() >= (int) $c['max_versuche'];
}

function versuch_vermerken(string $benutzername): void
{
    $ip = inet_pton($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') ?: inet_pton('0.0.0.0');
    db()->prepare('INSERT INTO login_versuche (benutzername, ip) VALUES (?, ?)')
        ->execute([$benutzername, $ip]);
}

/**
 * Prüft die Zugangsdaten und startet die Sitzung.
 * Gibt bei Erfolg true zurück.
 */
function anmelden(string $benutzername, string $passwort): bool
{
    sitzung_starten();

    $stmt = db()->prepare(
        'SELECT id, name, rolle, passwort_hash, passwort_wechseln FROM mitglieder
          WHERE benutzername = ? AND aktiv = 1'
    );
    $stmt->execute([$benutzername]);
    $m = $stmt->fetch();

    // password_verify auch ohne Treffer aufrufen, damit die Antwortzeit
    // nicht verrät, ob es den Benutzernamen überhaupt gibt.
    $hash = is_array($m)
        ? (string) $m['passwort_hash']
        : '$2y$12$1cCr7hHFNxIWlxxHzM6uNu2hjq8m/uZR7APtiT1TZBLDPUYJPMYDe';
    $passt = password_verify($passwort, $hash);

    if (!is_array($m) || !$passt) {
        versuch_vermerken($benutzername);
        return false;
    }

    // Hash bei Bedarf auf das aktuelle Verfahren heben
    if (password_needs_rehash($m['passwort_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE mitglieder SET passwort_hash = ? WHERE id = ?')
            ->execute([password_hash($passwort, PASSWORD_DEFAULT), $m['id']]);
    }

    session_regenerate_id(true);          // schützt vor Session-Fixation
    $_SESSION['mitglied_id'] = (int) $m['id'];
    $_SESSION['name']        = $m['name'];
    $_SESSION['rolle']       = $m['rolle'];
    $_SESSION['passwort_wechseln'] = (int) $m['passwort_wechseln'] === 1;
    $_SESSION['zuletzt']     = time();

    db()->prepare('UPDATE mitglieder SET letzter_login = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $m['id']]);
    db()->prepare('DELETE FROM login_versuche WHERE benutzername = ?')
        ->execute([$benutzername]);

    return true;
}

function abmelden(): void
{
    sitzung_starten();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Einmal-Token gegen fremde Formularabsendungen (CSRF). */
function csrf_token(): string
{
    sitzung_starten();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_pruefen(): void
{
    sitzung_starten();
    $gesendet = $_POST['csrf'] ?? '';
    if (!is_string($gesendet) || !hash_equals($_SESSION['csrf'] ?? '', $gesendet)) {
        http_response_code(400);
        exit('Ungültige Anfrage. Bitte das Formular neu laden.');
    }
}
