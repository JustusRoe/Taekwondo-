<?php
/**
 * Hilfsfunktionen der Verwaltung – Konten und Videoablage.
 */
declare(strict_types=1);

require_once __DIR__ . '/seite.php';

/** Ordner, in dem angefangene Uploads zwischenliegen. */
function upload_ordner(): string
{
    $ordner = rtrim(konfiguration()['video_ordner'], '/') . '/.uploads';
    if (!is_dir($ordner)) {
        mkdir($ordner, 0770, true);
    }
    return $ordner;
}

/** Größte erlaubte Videodatei in Bytes. */
function max_video_bytes(): int
{
    return (int) (konfiguration()['max_video_mb'] ?? 800) * 1024 * 1024;
}

/**
 * Erzeugt ein Startpasswort, das sich am Telefon durchgeben und auf einen
 * Zettel schreiben lässt. Zwei Wörter aus 56 und vier Ziffern ergeben
 * 56 × 56 × 9000, also rund 28 Millionen Möglichkeiten. Zusammen mit der
 * Sperre nach fünf Fehlversuchen reicht das für ein Passwort, das ohnehin
 * beim ersten Anmelden gegen ein eigenes getauscht wird. Ähnlich
 * aussehende Wörter sind bewusst nicht dabei.
 */
function passwort_vorschlag(): string
{
    $woerter = [
        'Anker', 'Ahorn', 'Blume', 'Brücke', 'Delfin', 'Distel', 'Eiche', 'Falke',
        'Feder', 'Ferse', 'Fluss', 'Garten', 'Gipfel', 'Grille', 'Hafen', 'Halle',
        'Hammer', 'Hügel', 'Insel', 'Kachel', 'Kanu', 'Kiesel', 'Kranich', 'Kupfer',
        'Lampe', 'Leiter', 'Linde', 'Möwe', 'Nadel', 'Nebel', 'Otter', 'Pfeil',
        'Quelle', 'Rabe', 'Reiher', 'Ruder', 'Salbei', 'Schilf', 'Segel', 'Silber',
        'Spatz', 'Stufe', 'Tanne', 'Taube', 'Teich', 'Trommel', 'Ufer', 'Vogel',
        'Wacholder', 'Waage', 'Weide', 'Welle', 'Wiese', 'Wolke', 'Zeder', 'Zirkel',
    ];
    $a = $woerter[random_int(0, count($woerter) - 1)];
    $b = $woerter[random_int(0, count($woerter) - 1)];
    return $a . '-' . $b . '-' . random_int(1000, 9999);
}

/** Prüft einen Benutzernamen; gibt eine Fehlermeldung zurück oder ''. */
function benutzername_pruefen(string $name): string
{
    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $name)) {
        return 'Der Benutzername darf nur Kleinbuchstaben, Ziffern, Punkt, '
             . 'Bindestrich und Unterstrich enthalten (3 bis 60 Zeichen).';
    }
    return '';
}

/**
 * Prüft ein Passwort; gibt eine Fehlermeldung zurück oder ''.
 *
 * Trainerkonten dürfen in die Verwaltung und bekommen deshalb die
 * strengere Vorgabe. Länge wirkt gegen Raten mehr als Sonderzeichen:
 * Zwölf Zeichen aus einem Satz gemerkter Wörter sind schwerer zu treffen
 * als acht mit Ziffer und Ausrufezeichen – und leichter zu merken.
 */
function passwort_pruefen(string $passwort, string $rolle = 'mitglied',
                          string $benutzername = ''): string
{
    $mindestens = $rolle === 'trainer' ? 12 : 8;
    if (mb_strlen($passwort) < $mindestens) {
        return $rolle === 'trainer'
            ? 'Trainerpasswörter müssen mindestens 12 Zeichen haben.'
            : 'Das Passwort muss mindestens 8 Zeichen haben.';
    }

    $klein = mb_strtolower($passwort);

    // Der Benutzername im Passwort ist das Erste, was jemand ausprobiert.
    if ($benutzername !== '' && mb_strlen($benutzername) >= 4
        && str_contains($klein, mb_strtolower($benutzername))) {
        return 'Das Passwort darf den Benutzernamen nicht enthalten.';
    }

    // Kurze Liste dessen, was auf jeder Rateliste ganz oben steht.
    $verbreitet = [
        'passwort', 'password', 'taekwondo', 'steinau', 'tvsteinau',
        'qwertz', 'qwerty', '12345678', '123456789', 'geheim',
        'willkommen', 'training', 'abcdefgh', 'letmein',
    ];
    foreach ($verbreitet as $wort) {
        if (str_contains($klein, $wort)) {
            return 'Dieses Passwort ist zu leicht zu erraten – „' . $wort
                 . '" steht auf jeder Liste, die durchprobiert wird.';
        }
    }

    return '';
}

/** Anzahl der Konten, die sich noch in die Verwaltung anmelden können. */
function aktive_trainer(): int
{
    return (int) db()->query(
        "SELECT COUNT(*) FROM mitglieder WHERE rolle = 'trainer' AND aktiv = 1"
    )->fetchColumn();
}

/**
 * Verhindert, dass sich das Trainerteam selbst aussperrt: Das letzte aktive
 * Trainerkonto darf weder gelöscht noch abgestuft oder stillgelegt werden.
 */
function letzter_trainer(int $id): bool
{
    $stmt = db()->prepare("SELECT rolle, aktiv FROM mitglieder WHERE id = ?");
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if (!$m || $m['rolle'] !== 'trainer' || !$m['aktiv']) {
        return false;
    }
    return aktive_trainer() <= 1;
}

/** Untermenü der Verwaltung. */
function verwaltung_menue(string $aktiv): void
{
    $punkte = [
        'admin.php'    => 'Videos',
        'termine.php'  => 'Termine',
        'konten.php'   => 'Zugänge',
        'passwort.php' => 'Mein Passwort',
    ];
    ?>
  <nav class="verwaltung-menue" aria-label="Verwaltung">
    <?php foreach ($punkte as $datei => $text): ?>
      <a href="<?= $datei ?>" class="chip"
         aria-pressed="<?= $datei === $aktiv ? 'true' : 'false' ?>"><?= $text ?></a>
    <?php endforeach; ?>
  </nav>
    <?php
}

/** Meldung oder Fehler oberhalb des Inhalts. */
function hinweis(string $meldung, string $fehler): void
{
    if ($meldung !== '') {
        echo '<p class="form-status">' . h($meldung) . '</p>';
    }
    if ($fehler !== '') {
        echo '<p class="form-status ist-fehler"><strong>' . h($fehler) . '</strong></p>';
    }
}
