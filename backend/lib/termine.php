<?php
/**
 * Trainingstermine: prüfen, aus CSV einlesen und in die öffentliche
 * Website zurückschreiben.
 *
 * Gepflegt werden die Termine in der Tabelle trainingstermine. Die
 * öffentliche Website ist statisch; sie bekommt die Termine deshalb nicht
 * zur Laufzeit aus der Datenbank, sondern beim Speichern in zwei Dateien
 * geschrieben – jeweils zwischen den Markierungen TERMINE:ANFANG und
 * TERMINE:ENDE:
 *
 *   assets/js/trainingstermine.js   Daten für die Startseite
 *   training.html                   Liste, auch ohne JavaScript lesbar
 *
 * Beides bleibt damit ohne PHP nutzbar: Fällt der Server aus oder wird die
 * Seite als reine Dateien ausgeliefert, stehen die Termine trotzdem da.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/** Die Hallen, wie sie auf der Website heißen. */
function termin_orte(): array
{
    return [
        'steines' => [
            'name'  => 'Halle am Steines',
            'karte' => 'https://www.google.com/maps/place/Mehrzweckhalle/@50.31691,9.4579451,625m/data=!3m2!1e3!4b1!4m6!3m5!1s0x47bcd53fa7be3927:0x83ba362dc68596c0!8m2!3d50.31691!4d9.46052!16s%2Fg%2F11fx7r226c',
        ],
        'schloss' => [
            'name'  => 'Halle am Schloss',
            'karte' => 'https://www.google.com/maps/place/Halle+Am+Schloss+(alte+Turnhalle)/@50.3111517,9.4577706,625m/data=!3m2!1e3!4b1!4m6!3m5!1s0x47bcd514a4cd01a7:0x10d71b24c5f712c5!8m2!3d50.3111517!4d9.4603455!16s%2Fg%2F11c5fyld_c',
        ],
        'frei' => ['name' => 'Kein Training', 'karte' => ''],
    ];
}

/**
 * Der feste Wochenrhythmus.
 *
 * Trainiert wird immer donnerstags und samstags, immer zu denselben
 * Zeiten. Nur zweierlei weicht ab: An einzelnen Terminen fällt das
 * Training aus (Ferien, Feiertage), und die Halle wechselt.
 *
 * Deshalb werden Termine nicht einzeln eingetippt, sondern für einen
 * Zeitraum erzeugt; nachträglich wird nur noch gestrichen und die Halle
 * umgestellt (backend/termine.php).
 *
 * wochentag: 0 = Sonntag … 6 = Samstag, wie bei date('w')
 */
function trainingsrhythmus(): array
{
    return [
        [
            'wochentag' => 4,                      // Donnerstag
            'zeit'      => '18:00 – 20:00',
            'gruppe'    => 'Selbstverteidigung',
            'hinweis'   => 'Spiegelraum',
        ],
        [
            'wochentag' => 6,                      // Samstag
            'zeit'      => '09:30 – 11:30',
            'gruppe'    => 'Training & Bambini',
            'hinweis'   => '',
        ],
    ];
}

/**
 * Erzeugt alle Termine des Rhythmus zwischen zwei Daten.
 *
 * Die Halle steht überall auf "steines" – das ist der Normalfall. Was
 * davon abweicht, wird hinterher in der Liste umgestellt; das sind ein
 * paar Klicks statt einer kompletten Eingabe.
 */
function termine_erzeugen(string $von, string $bis, string $ort = 'steines'): array
{
    $rhythmus = trainingsrhythmus();
    $tag  = (int) strtotime($von);
    $ende = (int) strtotime($bis);
    $termine = [];

    // Obergrenze gegen Vertipper wie "2099" – ein Jahr reicht für jeden Plan.
    $hoechstens = 400;

    while ($tag <= $ende && count($termine) < $hoechstens) {
        foreach ($rhythmus as $r) {
            if ((int) date('w', $tag) === $r['wochentag']) {
                $termine[] = [
                    'datum'   => date('Y-m-d', $tag),
                    'zeit'    => $r['zeit'],
                    'gruppe'  => $r['gruppe'],
                    'ort'     => $ort,
                    'hinweis' => $r['hinweis'],
                ];
            }
        }
        $tag = (int) strtotime('+1 day', $tag);
    }
    return $termine;
}

/** Deutscher Wochentag zu einem Datum – die Website zeigt ihn ausgeschrieben. */
function termin_wochentag(string $datum): string
{
    $tage = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
    return $tage[(int) date('w', (int) strtotime($datum))];
}

/** Monat und Jahr ausgeschrieben, etwa „September 2026". */
function termin_monat(string $datum): string
{
    $monate = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli',
                    'August', 'September', 'Oktober', 'November', 'Dezember'];
    $zeit = (int) strtotime($datum);
    return $monate[(int) date('n', $zeit)] . ' ' . date('Y', $zeit);
}

/**
 * Prüft einen Termin und gibt ihn aufgeräumt zurück.
 * Bei einem Fehler steht die Meldung in $fehler und der Rückgabewert ist null.
 */
function termin_pruefen(array $roh, ?string &$fehler): ?array
{
    $fehler = null;
    $datum  = trim((string) ($roh['datum'] ?? ''));
    $ort    = strtolower(trim((string) ($roh['ort'] ?? 'steines')));

    // Datum: 2026-09-05 oder 05.09.2026
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $datum, $t)) {
        $datum = $t[3] . '-' . $t[2] . '-' . $t[1];
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $datum, $t)
        || !checkdate((int) $t[2], (int) $t[3], (int) $t[1])) {
        $fehler = 'Das Datum „' . $datum . '" ist kein gültiges Datum (erwartet: 2026-09-05 oder 05.09.2026).';
        return null;
    }

    if (!isset(termin_orte()[$ort])) {
        $fehler = 'Unbekannter Ort „' . $ort . '". Erlaubt sind: '
                . implode(', ', array_keys(termin_orte())) . '.';
        return null;
    }

    $zeit   = trim((string) ($roh['zeit'] ?? ''));
    $gruppe = trim((string) ($roh['gruppe'] ?? ''));

    // Ausfalltermine tragen auf der Website einen festen Text.
    if ($ort === 'frei') {
        $zeit   = $zeit !== '' ? $zeit : '—';
        $gruppe = $gruppe !== '' ? $gruppe : 'Kein Training';
    } elseif ($zeit === '' || $gruppe === '') {
        $fehler = 'Für den ' . $datum . ' fehlt die Uhrzeit oder die Gruppe.';
        return null;
    }

    return [
        'datum'   => $datum,
        'zeit'    => mb_substr($zeit, 0, 40),
        'gruppe'  => mb_substr($gruppe, 0, 80),
        'ort'     => $ort,
        'hinweis' => mb_substr(trim((string) ($roh['hinweis'] ?? '')), 0, 190),
    ];
}

/**
 * Alle Termine aus der Datenbank, nach Datum sortiert.
 *
 * Das heutige Datum kommt aus PHP und nicht aus der Datenbank: Im Betrieb
 * läuft MySQL, in der Testumgebung SQLite – die beiden schreiben ihre
 * Datumsfunktionen unterschiedlich.
 */
function termine_lesen(bool $nurKommende = false): array
{
    $sql = 'SELECT * FROM trainingstermine';
    $werte = [];
    if ($nurKommende) {
        $sql .= ' WHERE datum >= ?';
        $werte[] = date('Y-m-d');
    }
    $sql .= ' ORDER BY datum, zeit';
    $stmt = db()->prepare($sql);
    $stmt->execute($werte);
    return $stmt->fetchAll();
}

/**
 * Liest eine hochgeladene CSV-Datei.
 *
 * Erwartet eine Kopfzeile mit den Spalten datum, zeit, gruppe, ort, hinweis
 * (Reihenfolge egal, hinweis optional). Trennzeichen darf Semikolon oder
 * Komma sein – Tabellenprogramme im deutschen Sprachraum nehmen meist das
 * Semikolon. Ein BOM am Dateianfang wird entfernt, sonst heißt die erste
 * Spalte "\xEF\xBB\xBFdatum" und wird nicht erkannt.
 */
function csv_einlesen(string $inhalt, array &$fehler): array
{
    $fehler  = [];
    $termine = [];

    $inhalt = preg_replace('/^\xEF\xBB\xBF/', '', $inhalt) ?? $inhalt;
    if (!mb_check_encoding($inhalt, 'UTF-8')) {
        $inhalt = (string) mb_convert_encoding($inhalt, 'UTF-8', 'ISO-8859-15');
    }

    $zeilen = preg_split('/\r\n|\r|\n/', trim($inhalt)) ?: [];
    if (!$zeilen || $zeilen === ['']) {
        $fehler[] = 'Die Datei ist leer.';
        return [];
    }

    $trenner = substr_count($zeilen[0], ';') >= substr_count($zeilen[0], ',') ? ';' : ',';
    $kopf = array_map(
        static fn ($s) => strtolower(trim($s, " \t\"'")),
        str_getcsv(array_shift($zeilen), $trenner)
    );

    if (!in_array('datum', $kopf, true)) {
        $fehler[] = 'In der ersten Zeile fehlt die Spalte „datum". '
                  . 'Erwartet wird eine Kopfzeile: datum;zeit;gruppe;ort;hinweis';
        return [];
    }

    foreach ($zeilen as $nr => $zeile) {
        if (trim($zeile) === '') {
            continue;
        }
        $werte = str_getcsv($zeile, $trenner);
        $roh = [];
        foreach ($kopf as $i => $spalte) {
            $roh[$spalte] = $werte[$i] ?? '';
        }
        $geprueft = termin_pruefen($roh, $meldung);
        if ($geprueft === null) {
            $fehler[] = 'Zeile ' . ($nr + 2) . ': ' . $meldung;
            continue;
        }
        $termine[] = $geprueft;
    }

    return $termine;
}

/** Die Termine als CSV zum Herunterladen. */
function csv_schreiben(array $termine): string
{
    $zeilen = ['datum;zeit;gruppe;ort;hinweis'];
    foreach ($termine as $t) {
        $zeilen[] = implode(';', array_map(
            static fn ($w) => str_contains((string) $w, ';') ? '"' . $w . '"' : (string) $w,
            [$t['datum'], $t['zeit'], $t['gruppe'], $t['ort'], $t['hinweis']]
        ));
    }
    return implode("\r\n", $zeilen) . "\r\n";
}

/* =========================================================
   Die öffentliche Website neu schreiben
   ========================================================= */

/** Pfad zur öffentlichen Website. */
function web_pfad(string $datei): string
{
    return __DIR__ . '/../../' . $datei;
}

/**
 * Ersetzt den Bereich zwischen zwei Markierungen.
 * Gibt bei einem Problem eine Meldung zurück, sonst ''.
 */
function block_ersetzen(string $datei, string $anfang, string $ende, string $neu): string
{
    $pfad = web_pfad($datei);
    if (!is_file($pfad)) {
        return $datei . ' wurde nicht gefunden.';
    }
    $inhalt = (string) file_get_contents($pfad);

    $a = strpos($inhalt, $anfang);
    $e = strpos($inhalt, $ende);
    if ($a === false || $e === false || $e < $a) {
        return 'In ' . $datei . ' fehlen die Markierungen TERMINE:ANFANG und TERMINE:ENDE.';
    }
    $a += strlen($anfang);

    $inhalt = substr($inhalt, 0, $a) . $neu . substr($inhalt, $e);

    // Erst in eine Nachbardatei schreiben, dann umbenennen: So steht nie
    // eine halb geschriebene Datei im Netz, wenn zwischendrin etwas schiefgeht.
    $temp = $pfad . '.neu';
    if (@file_put_contents($temp, $inhalt) === false || !@rename($temp, $pfad)) {
        @unlink($temp);
        return $datei . ' ist nicht beschreibbar. Bitte die Schreibrechte prüfen.';
    }
    return '';
}

/**
 * Datenblock für assets/js/trainingstermine.js.
 *
 * Zwischen den Monaten bleibt eine Leerzeile: Die Datei wird zwar
 * geschrieben und nicht mehr von Hand gepflegt, ist so aber weiter
 * überfliegbar, wenn doch einmal jemand hineinschaut.
 */
function js_block(array $termine): string
{
    $zeilen = [];
    $letzterMonat = '';
    foreach ($termine as $t) {
        $monat = substr($t['datum'], 0, 7);
        if ($letzterMonat !== '' && $monat !== $letzterMonat) {
            $zeilen[] = '';
        }
        $letzterMonat = $monat;
        $felder = [
            '"datum": ' . json_encode($t['datum'], JSON_UNESCAPED_UNICODE),
            '"tag": ' . json_encode(termin_wochentag($t['datum']), JSON_UNESCAPED_UNICODE),
            '"zeit": ' . json_encode($t['zeit'], JSON_UNESCAPED_UNICODE),
            '"gruppe": ' . json_encode($t['gruppe'], JSON_UNESCAPED_UNICODE),
            '"ort": ' . json_encode($t['ort'], JSON_UNESCAPED_UNICODE),
        ];
        if ($t['hinweis'] !== '') {
            $felder[] = '"hinweis": ' . json_encode($t['hinweis'], JSON_UNESCAPED_UNICODE);
        }
        $zeilen[] = '  { ' . implode(', ', $felder) . ' }';
    }

    // Die Trennzeilen bekommen kein Komma – deshalb wird das Komma an die
    // Einträge gehängt statt mit implode() dazwischengesetzt.
    $aus = '';
    $offen = count(array_filter($zeilen, static fn ($z) => $z !== ''));
    $geschrieben = 0;
    foreach ($zeilen as $z) {
        if ($z === '') {
            $aus .= "\n";
            continue;
        }
        $geschrieben++;
        $aus .= $z . ($geschrieben < $offen ? ',' : '') . "\n";
    }
    return "\n" . $aus;
}

/** Listeneinträge für training.html. */
function html_block(array $termine): string
{
    $orte = termin_orte();
    $aus = '';
    $letzterMonat = '';

    foreach ($termine as $t) {
        $zeit = (int) strtotime($t['datum']);
        $monat = termin_monat($t['datum']);
        if ($monat !== $letzterMonat) {
            $aus .= '          <li class="kal-monat"><span>' . h($monat) . "</span></li>\n";
            $letzterMonat = $monat;
        }

        $frei = $t['ort'] === 'frei';
        $info = $orte[$t['ort']];

        $aus .= '          <li class="kal-eintrag' . ($frei ? ' ist-frei' : '')
              . '" data-datum="' . h($t['datum']) . "\">\n";
        // date('d.m.') liefert den abschliessenden Punkt bereits mit.
        $aus .= '            <span class="kal-datum"><strong>' . date('d.m.', $zeit)
              . '</strong><span>' . h(termin_wochentag($t['datum'])) . "</span></span>\n";
        $aus .= '            <span class="kal-zeit">' . h($t['zeit']) . "</span>\n";
        $aus .= '            <span class="kal-gruppe">' . h($t['gruppe']);
        if ($t['hinweis'] !== '') {
            $aus .= "\n              " . '<span class="kal-hinweis">' . h($t['hinweis']) . '</span>';
        }
        $aus .= "</span>\n";

        if ($frei) {
            $aus .= '            <span class="kal-frei">kein Training</span>' . "\n";
        } else {
            $aus .= '            <a class="halle ort-' . h($t['ort']) . '" href="' . h($info['karte'])
                  . '" target="_blank" rel="noopener">' . h($info['name']) . "</a>\n";
        }
        $aus .= "          </li>\n";
    }
    return $aus;
}

/**
 * Schreibt beide Dateien neu. Gibt die Liste der Probleme zurück –
 * ein leeres Feld bedeutet: alles hat geklappt.
 */
function website_schreiben(array $termine): array
{
    $probleme = [];

    $p = block_ersetzen(
        'assets/js/trainingstermine.js',
        '/* TERMINE:ANFANG – ab hier schreibt backend/termine.php, nicht von Hand ändern */',
        '/* TERMINE:ENDE */',
        js_block($termine)
    );
    if ($p !== '') {
        $probleme[] = $p;
    }

    // Die Einrückung vor der Endmarkierung liegt im ersetzten Bereich und
    // muss deshalb mitgeschrieben werden, sonst rutscht sie an den Rand.
    $p = block_ersetzen(
        'training.html',
        '<!-- TERMINE:ANFANG – ab hier schreibt backend/termine.php, nicht von Hand ändern -->',
        '<!-- TERMINE:ENDE -->',
        "\n" . html_block($termine) . '          '
    );
    if ($p !== '') {
        $probleme[] = $p;
    }

    return $probleme;
}
