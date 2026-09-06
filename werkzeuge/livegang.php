<?php
/**
 * Schaltet die Website zwischen Entwurf und Livebetrieb um.
 *
 * Für die Vorschau ist die Seite für Suchmaschinen gesperrt: robots.txt
 * verbietet alles, und jede Seite trägt ein <meta name="robots"
 * content="noindex, nofollow">. Bleibt das beim Livegang stehen, taucht
 * die Seite bei Google nie auf – der Fehler fällt erst Monate später auf.
 *
 * Die Sperre steckt in einem Dutzend Dateien. Dieses Skript nimmt sie
 * überall zugleich weg und kann sie genauso wieder setzen.
 *
 *     php werkzeuge/livegang.php --status
 *     php werkzeuge/livegang.php --live
 *     php werkzeuge/livegang.php --entwurf
 *
 * Die Seiten des Mitgliederbereichs behalten ihr noindex immer: Sie
 * gehören nicht in die Suche, egal ob Entwurf oder Livebetrieb.
 *
 * Zweitens biegt das Skript den Menüpunkt „Mitglieder" um. Im Entwurf
 * zeigt er auf mitglieder.html – die nachgebaute Anmeldung, die nichts
 * schützt und die Testzugänge offen anzeigt. Im Livebetrieb muss er auf
 * backend/login.php zeigen, die echte Anmeldung gegen die Datenbank.
 * Bliebe der Entwurf verlinkt, landete jeder Besucher auf einer Attrappe.
 */
declare(strict_types=1);

const WURZEL = __DIR__ . '/..';

/** Diese Seiten bleiben in jedem Fall aus der Suche heraus. */
const IMMER_GESPERRT = ['mitglieder.html', 'mitglieder-videothek.html', 'mitglieder-video.html'];

const ENTWURFSZEILE = '<!-- ENTWURF: Sperre gegen Suchmaschinen. Vor dem Livegang diese Zeile und'
                    . "\n     robots.txt anpassen, sonst findet Google die Seite nie. -->\n";

const ROBOTS_ENTWURF = <<<TXT
# Entwurfsfassung – noch nicht für Suchmaschinen bestimmt.
# Vor dem Livegang durch die folgende Fassung ersetzen:
#
#   User-agent: *
#   Allow: /
#   Disallow: /backend/
#
User-agent: *
Disallow: /

TXT;

const ROBOTS_LIVE = <<<TXT
User-agent: *
Allow: /
Disallow: /backend/
Disallow: /werkzeuge/

TXT;

/** Die öffentlichen Seiten, also alle außer dem Mitgliederbereich. */
function seiten(): array
{
    $alle = glob(WURZEL . '/*.html') ?: [];
    return array_values(array_filter(
        $alle,
        static fn ($p) => !in_array(basename($p), IMMER_GESPERRT, true)
    ));
}

function status(): void
{
    $gesperrt = [];
    foreach (seiten() as $pfad) {
        if (str_contains((string) file_get_contents($pfad), 'noindex')) {
            $gesperrt[] = basename($pfad);
        }
    }
    $robots = trim((string) @file_get_contents(WURZEL . '/robots.txt'));
    $robotsSperrt = str_contains($robots, "Disallow: /\n") || str_ends_with($robots, 'Disallow: /');

    $entwurfVerlinkt = 0;
    foreach (seiten() as $pfad) {
        if (str_contains((string) file_get_contents($pfad), 'href="mitglieder.html"')) {
            $entwurfVerlinkt++;
        }
    }

    echo "Öffentliche Seiten:      ", count(seiten()), "\n";
    echo "davon für Suchmaschinen gesperrt: ", count($gesperrt), "\n";
    if ($gesperrt) {
        echo "  ", implode(', ', $gesperrt), "\n";
    }
    echo "robots.txt sperrt alles: ", $robotsSperrt ? 'ja' : 'nein', "\n";
    echo "Menüpunkt „Mitglieder\" zeigt auf: ",
        $entwurfVerlinkt > 0 ? 'mitglieder.html (Entwurf)' : 'backend/login.php (echt)', "\n\n";

    echo (count($gesperrt) === 0 && !$robotsSperrt && $entwurfVerlinkt === 0)
        ? "→ Die Seite steht auf LIVE.\n"
        : "→ Die Seite steht auf ENTWURF.\n";
}

/**
 * Biegt den Menüpunkt „Mitglieder" auf die richtige Anmeldung um.
 * Im Entwurf auf die Attrappe, im Livebetrieb auf die echte.
 */
function verweise_umbiegen(string $inhalt, bool $live): string
{
    $von = $live ? 'href="mitglieder.html"' : 'href="backend/login.php"';
    $nach = $live ? 'href="backend/login.php"' : 'href="mitglieder.html"';
    return str_replace($von, $nach, $inhalt);
}

function umschalten(bool $live): void
{
    $geaendert = 0;

    foreach (seiten() as $pfad) {
        $inhalt = (string) file_get_contents($pfad);
        $vorher = $inhalt;

        $inhalt = verweise_umbiegen($inhalt, $live);

        if ($live) {
            // Kommentar und Meta-Zeile zusammen entfernen
            $inhalt = str_replace(ENTWURFSZEILE, '', $inhalt);
            $inhalt = preg_replace(
                '~[ \t]*<meta name="robots" content="noindex[^"]*">\R~',
                '',
                $inhalt
            ) ?? $inhalt;
        } elseif (!str_contains($inhalt, 'noindex')) {
            // Vor dem <title> wieder einsetzen
            $inhalt = preg_replace(
                '~(?=<title>)~',
                ENTWURFSZEILE . '<meta name="robots" content="noindex, nofollow">' . "\n",
                $inhalt,
                1
            ) ?? $inhalt;
        }

        if ($inhalt !== $vorher) {
            file_put_contents($pfad, $inhalt);
            $geaendert++;
            echo "  ", basename($pfad), "\n";
        }
    }

    file_put_contents(WURZEL . '/robots.txt', $live ? ROBOTS_LIVE : ROBOTS_ENTWURF);

    echo "\n", $geaendert, " Seiten geändert, robots.txt neu geschrieben.\n";
    if ($live) {
        echo "Die Seite ist für Suchmaschinen freigegeben, der Menüpunkt\n";
        echo "„Mitglieder\" zeigt auf die echte Anmeldung.\n\n";
        echo "NICHT auf den Server hochladen:\n";
        echo "  mitglieder.html, mitglieder-videothek.html, mitglieder-video.html\n";
        echo "  assets/js/mitglieder.js, assets/js/videodaten.js\n";
        echo "  assets/video/*.mp4 und *.webm  (die Vorschaubilder *.jpg schon)\n";
        echo "Das ist die nachgebaute Anmeldung samt Testzugängen. Sie schützt\n";
        echo "nichts. Die .htaccess sperrt sie zusätzlich – aber hochladen\n";
        echo "muss man sie gar nicht erst.\n";
    } else {
        echo "Die Seite ist für Suchmaschinen gesperrt, der Menüpunkt\n";
        echo "„Mitglieder\" zeigt wieder auf den Entwurf.\n";
    }
}

$befehl = $argv[1] ?? '--status';
match ($befehl) {
    '--live'    => umschalten(true),
    '--entwurf' => umschalten(false),
    '--status'  => status(),
    default     => print("Aufruf: php werkzeuge/livegang.php [--status|--live|--entwurf]\n"),
};
