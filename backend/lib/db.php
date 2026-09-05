<?php
/**
 * Datenbankverbindung und Konfiguration.
 */
declare(strict_types=1);

function konfiguration(): array
{
    static $config = null;
    if ($config === null) {
        $datei = __DIR__ . '/../config.php';
        if (!is_file($datei)) {
            http_response_code(500);
            exit('config.php fehlt. Bitte config.example.php kopieren und ausfüllen.');
        }
        $config = require $datei;
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = konfiguration()['db'];
        try {
            $pdo = new PDO($c['dsn'], $c['benutzer'] ?? null, $c['passwort'] ?? null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // SQLite (Testumgebung) lässt nur einen Schreiber zu und bricht
            // sonst sofort mit "database is locked" ab. Zehn Sekunden warten
            // statt aufzugeben – dann stören sich Webserver und Prüfskripte
            // nicht gegenseitig. MySQL im Betrieb kennt das Problem nicht und
            // die Anweisung dort auch nicht, deshalb nur für SQLite.
            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec('PRAGMA busy_timeout = 10000');
            }
        } catch (PDOException $e) {
            // Fehlermeldung nicht an den Besucher durchreichen
            error_log('DB-Verbindung fehlgeschlagen: ' . $e->getMessage());
            http_response_code(503);
            exit('Der Mitgliederbereich ist gerade nicht erreichbar.');
        }
    }
    return $pdo;
}

/** Kurzform für die Ausgabe von Text in HTML. */
function h(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Sekunden als 4:05 ausgeben. */
function mmss(int $sekunden): string
{
    return intdiv($sekunden, 60) . ':' . str_pad((string) ($sekunden % 60), 2, '0', STR_PAD_LEFT);
}
