<?php
/**
 * Verwaltung, Teil 3: Trainingstermine.
 *
 * Der Plan wird nicht eingetippt, sondern erzeugt: Trainiert wird immer
 * donnerstags und samstags zu denselben Zeiten. Für einen Zeitraum legt
 * die Seite alle Termine auf einmal an; danach bleibt nur noch zweierlei
 * zu tun – einzelne Termine streichen (Ferien, Feiertage) und die Halle
 * umstellen. Beides geht in der Liste direkt, mit einem Speichern für
 * alle Änderungen zusammen.
 *
 * Für Sonderfälle bleiben der einzelne Termin und der CSV-Import.
 *
 * Beim Speichern schreibt die Seite die Termine in die öffentliche
 * Website zurück (siehe lib/termine.php), sodass niemand dafür an den
 * Quelltext muss.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/verwaltung.php';
require_once __DIR__ . '/lib/termine.php';

$mitglied = trainer_verlangen();
$meldung  = '';
$fehler   = '';
$importFehler = [];

/* ---------- CSV herunterladen ---------- */
if (($_GET['export'] ?? '') === 'csv') {
    $csv = csv_schreiben(termine_lesen());
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="trainingstermine.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $aktion = (string) ($_POST['aktion'] ?? '');

    /* ---------- Termine für einen Zeitraum erzeugen ---------- */
    if ($aktion === 'erzeugen') {
        $von = (string) ($_POST['von'] ?? '');
        $bis = (string) ($_POST['bis'] ?? '');

        if (!strtotime($von) || !strtotime($bis)) {
            $fehler = 'Bitte Anfang und Ende des Zeitraums angeben.';
        } elseif (strtotime($bis) < strtotime($von)) {
            $fehler = 'Das Ende liegt vor dem Anfang.';
        } else {
            $neue = termine_erzeugen($von, $bis, (string) ($_POST['ort'] ?? 'steines'));
            if (!$neue) {
                $fehler = 'In diesem Zeitraum liegt kein Trainingstag.';
            } else {
                // Vorhandene Termine bleiben unangetastet: Wer den Zeitraum
                // versehentlich zweimal erzeugt, verliert keine Anpassung.
                $vorhanden = [];
                foreach (termine_lesen() as $t) {
                    $vorhanden[$t['datum'] . '|' . $t['zeit']] = true;
                }

                $stmt = db()->prepare(
                    'INSERT INTO trainingstermine (datum, zeit, gruppe, ort, hinweis)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $angelegt = 0;
                foreach ($neue as $t) {
                    if (isset($vorhanden[$t['datum'] . '|' . $t['zeit']])) {
                        continue;
                    }
                    $stmt->execute([$t['datum'], $t['zeit'], $t['gruppe'], $t['ort'], $t['hinweis']]);
                    $angelegt++;
                }
                $uebersprungen = count($neue) - $angelegt;
                $meldung = $angelegt . ' Termine angelegt'
                         . ($uebersprungen > 0
                             ? ', ' . $uebersprungen . ' waren schon da und blieben unverändert.'
                             : '.');
            }
        }
    }

    /* ---------- Alle Änderungen der Liste auf einmal ---------- */
    if ($aktion === 'sammel') {
        $zeilen = $_POST['termin'] ?? [];
        $bestand = [];
        foreach (termine_lesen() as $t) {
            $bestand[(int) $t['id']] = $t;
        }

        $rhythmus = [];
        foreach (trainingsrhythmus() as $r) {
            $rhythmus[$r['wochentag']] = $r;
        }

        $stmt = db()->prepare(
            'UPDATE trainingstermine SET zeit = ?, gruppe = ?, ort = ?, hinweis = ? WHERE id = ?'
        );
        $geaendert = 0;

        foreach ($zeilen as $id => $werte) {
            $id = (int) $id;
            if (!isset($bestand[$id])) {
                continue;
            }
            $alt  = $bestand[$id];
            $frei = !empty($werte['frei']);

            if ($frei) {
                // Ausfall: fester Text, der Grund kommt aus dem Hinweisfeld.
                $zeit = '—';
                $gruppe = 'Kein Training';
                $ort = 'frei';
            } elseif ($alt['ort'] === 'frei') {
                // Zurück in den Normalfall: Zeit und Gruppe aus dem Rhythmus,
                // sonst bliebe "Kein Training" stehen.
                $tag = (int) date('w', (int) strtotime($alt['datum']));
                $zeit   = $rhythmus[$tag]['zeit']   ?? '09:30 – 11:30';
                $gruppe = $rhythmus[$tag]['gruppe'] ?? 'Training';
                $ort = ($werte['ort'] ?? 'steines') === 'schloss' ? 'schloss' : 'steines';
            } else {
                // Normalfall bleibt Normalfall: Zeit und Gruppe unangetastet,
                // damit von Hand gesetzte Sonderfälle nicht verloren gehen.
                $zeit = $alt['zeit'];
                $gruppe = $alt['gruppe'];
                $ort = ($werte['ort'] ?? 'steines') === 'schloss' ? 'schloss' : 'steines';
            }

            $hinweis = mb_substr(trim((string) ($werte['hinweis'] ?? '')), 0, 190);

            if ([$zeit, $gruppe, $ort, $hinweis]
                !== [$alt['zeit'], $alt['gruppe'], $alt['ort'], $alt['hinweis']]) {
                $stmt->execute([$zeit, $gruppe, $ort, $hinweis, $id]);
                $geaendert++;
            }
        }

        $meldung = $geaendert === 0
            ? 'Es gab nichts zu ändern.'
            : $geaendert . ' Termine geändert.';
    }

    /* ---------- Einzelnen Termin anlegen oder ändern ---------- */
    if ($aktion === 'speichern') {
        $id = (int) ($_POST['id'] ?? 0);
        $termin = termin_pruefen([
            'datum'   => $_POST['datum']   ?? '',
            'zeit'    => $_POST['zeit']    ?? '',
            'gruppe'  => $_POST['gruppe']  ?? '',
            'ort'     => $_POST['ort']     ?? '',
            'hinweis' => $_POST['hinweis'] ?? '',
        ], $pruefMeldung);

        if ($termin === null) {
            $fehler = (string) $pruefMeldung;
        } else {
            try {
                if ($id > 0) {
                    db()->prepare(
                        'UPDATE trainingstermine
                            SET datum = ?, zeit = ?, gruppe = ?, ort = ?, hinweis = ?
                          WHERE id = ?'
                    )->execute([$termin['datum'], $termin['zeit'], $termin['gruppe'],
                                $termin['ort'], $termin['hinweis'], $id]);
                    $meldung = 'Termin am ' . date('d.m.Y', (int) strtotime($termin['datum'])) . ' geändert.';
                } else {
                    db()->prepare(
                        'INSERT INTO trainingstermine (datum, zeit, gruppe, ort, hinweis)
                         VALUES (?, ?, ?, ?, ?)'
                    )->execute([$termin['datum'], $termin['zeit'], $termin['gruppe'],
                                $termin['ort'], $termin['hinweis']]);
                    $meldung = 'Termin am ' . date('d.m.Y', (int) strtotime($termin['datum'])) . ' angelegt.';
                }
            } catch (PDOException $e) {
                $fehler = 'Zu diesem Datum gibt es die Uhrzeit schon.';
            }
        }
    }

    /* ---------- Termin löschen ---------- */
    if ($aktion === 'loeschen') {
        db()->prepare('DELETE FROM trainingstermine WHERE id = ?')
            ->execute([(int) ($_POST['id'] ?? 0)]);
        $meldung = 'Termin gelöscht.';
    }

    /* ---------- Vergangene Termine aufräumen ---------- */
    if ($aktion === 'aufraeumen') {
        $anzahl = db()->prepare('DELETE FROM trainingstermine WHERE datum < ?');
        $anzahl->execute([date('Y-m-d')]);
        $meldung = $anzahl->rowCount() . ' vergangene Termine entfernt.';
    }

    /* ---------- CSV einlesen ---------- */
    if ($aktion === 'import') {
        $datei = $_FILES['csv'] ?? null;
        if (!is_array($datei) || ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $fehler = 'Es wurde keine Datei hochgeladen (oder sie war zu groß).';
        } elseif (!is_uploaded_file($datei['tmp_name'])) {
            $fehler = 'Die Datei konnte nicht gelesen werden.';
        } elseif ((int) $datei['size'] > 512 * 1024) {
            $fehler = 'Die Datei ist größer als 512 KB – das ist für einen Terminplan zu viel.';
        } else {
            $termine = csv_einlesen((string) file_get_contents($datei['tmp_name']), $importFehler);

            if (!$termine) {
                $fehler = 'Aus der Datei ließ sich kein einziger Termin lesen.';
            } elseif ($importFehler && ($_POST['trotzdem'] ?? '') !== '1') {
                $fehler = 'Die Datei enthält Zeilen mit Fehlern. Bitte korrigieren – '
                        . 'oder unten bestätigen, dass die übrigen ' . count($termine)
                        . ' Termine trotzdem übernommen werden sollen.';
            } else {
                $ersetzen = ($_POST['modus'] ?? 'ersetzen') === 'ersetzen';
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    if ($ersetzen) {
                        $pdo->exec('DELETE FROM trainingstermine');
                    }
                    // Erst den gleichen Termin entfernen, dann einfügen: Das
                    // ersetzt vorhandene Zeilen und kommt ohne die
                    // MySQL-eigene Schreibweise ON DUPLICATE KEY aus, die
                    // SQLite in der Testumgebung nicht kennt.
                    $ersetzeGleiche = $pdo->prepare(
                        'DELETE FROM trainingstermine WHERE datum = ? AND zeit = ?'
                    );
                    $stmt = $pdo->prepare(
                        'INSERT INTO trainingstermine (datum, zeit, gruppe, ort, hinweis)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    foreach ($termine as $t) {
                        $ersetzeGleiche->execute([$t['datum'], $t['zeit']]);
                        $stmt->execute([$t['datum'], $t['zeit'], $t['gruppe'], $t['ort'], $t['hinweis']]);
                    }
                    $pdo->commit();
                    $meldung = count($termine) . ' Termine übernommen'
                             . ($ersetzen ? ' – der bisherige Plan wurde ersetzt.' : ' und ergänzt.');
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $fehler = 'Der Import wurde abgebrochen, es hat sich nichts geändert.';
                }
            }
        }
    }

    /* ---------- Website neu schreiben ---------- */
    if ($fehler === '') {
        $probleme = website_schreiben(termine_lesen());
        if ($probleme) {
            $fehler = 'Gespeichert – aber die Website konnte nicht aktualisiert werden: '
                    . implode(' ', $probleme);
        } elseif ($meldung !== '') {
            $meldung .= ' Die Website ist aktualisiert.';
        }
    }
}

$alle     = termine_lesen();
$kommende = array_values(array_filter(
    $alle,
    static fn ($t) => $t['datum'] >= date('Y-m-d')
));
$vergangene = count($alle) - count($kommende);

kopf('Termine', $mitglied);
?>

<main id="main" class="member-main">
  <div class="container">

    <div class="member-head">
      <div>
        <h1>Trainingstermine</h1>
        <p>Was hier steht, steht auf der Website. Nach jedem Speichern werden
           der Terminplan auf <code>training.html</code> und die Startseite
           neu geschrieben.</p>
      </div>
      <p class="member-count"><?= count($kommende) ?> kommende</p>
    </div>

    <?php verwaltung_menue('termine.php'); ?>
    <?php hinweis($meldung, $fehler); ?>

    <?php if ($importFehler): ?>
      <div class="import-fehler">
        <h2><?= count($importFehler) ?> Zeilen konnten nicht gelesen werden</h2>
        <ul>
          <?php foreach (array_slice($importFehler, 0, 20) as $z): ?>
            <li><?= h($z) ?></li>
          <?php endforeach; ?>
          <?php if (count($importFehler) > 20): ?>
            <li>… und <?= count($importFehler) - 20 ?> weitere.</li>
          <?php endif; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- ---------- Schritt 1: Termine erzeugen ---------- -->
    <section class="termin-block">
      <h2 class="abschnitt-titel">Termine erzeugen</h2>
      <p class="termin-erklaerung">
        Trainiert wird immer donnerstags von 18:00 bis 20:00 Uhr und samstags von
        09:30 bis 11:30 Uhr. Gib einen Zeitraum an – die Termine dazwischen legt die
        Seite selbst an. Was danach abweicht, stellst du unten in der Liste um.
        Schon vorhandene Termine bleiben dabei unangetastet.
      </p>

      <form method="post" action="" class="termin-erzeugen">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="aktion" value="erzeugen">
        <p class="field">
          <label for="von">Von</label>
          <input type="date" id="von" name="von" required
                 value="<?= h(date('Y-m-d')) ?>">
        </p>
        <p class="field">
          <label for="bis">Bis</label>
          <input type="date" id="bis" name="bis" required
                 value="<?= h(date('Y-m-d', strtotime('+6 months'))) ?>">
        </p>
        <p class="field">
          <label for="standardort">Halle</label>
          <select id="standardort" name="ort">
            <option value="steines">Halle am Steines</option>
            <option value="schloss">Halle am Schloss</option>
          </select>
        </p>
        <button type="submit" class="btn btn-primary">Termine anlegen</button>
      </form>
    </section>

    <!-- ---------- Schritt 2: streichen und Halle umstellen ---------- -->
    <section class="termin-block">
      <h2 class="abschnitt-titel">Kommende Termine anpassen</h2>

      <?php if (!$kommende): ?>
        <p class="listen-leer">
          Noch keine kommenden Termine. Lege oben welche für einen Zeitraum an.
        </p>
      <?php else: ?>
        <p class="termin-erklaerung">
          Halle umstellen, Ausfälle ankreuzen, Grund dazuschreiben – dann einmal
          speichern. Zeiten und Gruppen bleiben dabei wie sie sind.
        </p>

        <form method="post" action="">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="aktion" value="sammel">

          <div class="table-wrap">
            <table class="termin-tabelle">
              <thead>
                <tr>
                  <th scope="col">Datum</th>
                  <th scope="col">Training</th>
                  <th scope="col">Halle</th>
                  <th scope="col">fällt aus</th>
                  <th scope="col">Hinweis / Grund</th>
                </tr>
              </thead>
              <tbody>
                <?php
                  $letzterMonat = '';
                  foreach ($kommende as $t):
                    $zeit  = (int) strtotime($t['datum']);
                    $monat = termin_monat($t['datum']);
                    $frei  = $t['ort'] === 'frei';
                ?>
                  <?php if ($monat !== $letzterMonat): $letzterMonat = $monat; ?>
                    <tr class="termin-monat"><th colspan="5" scope="colgroup"><?= h($monat) ?></th></tr>
                  <?php endif; ?>
                  <tr<?= $frei ? ' class="ist-frei"' : '' ?>>
                    <th scope="row">
                      <strong><?= h(date('d.m.', $zeit)) ?></strong>
                      <span><?= h(termin_wochentag($t['datum'])) ?></span>
                    </th>
                    <td class="spalte-training">
                      <?= h($t['zeit']) ?><br>
                      <span><?= h($t['gruppe']) ?></span>
                    </td>
                    <td>
                      <select name="termin[<?= (int) $t['id'] ?>][ort]"
                              aria-label="Halle am <?= h(date('d.m.Y', $zeit)) ?>">
                        <option value="steines" <?= $t['ort'] === 'schloss' ? '' : 'selected' ?>>am Steines</option>
                        <option value="schloss" <?= $t['ort'] === 'schloss' ? 'selected' : '' ?>>am Schloss</option>
                      </select>
                    </td>
                    <td class="spalte-frei">
                      <input type="checkbox" name="termin[<?= (int) $t['id'] ?>][frei]" value="1"
                             <?= $frei ? 'checked' : '' ?>
                             aria-label="Training am <?= h(date('d.m.Y', $zeit)) ?> fällt aus">
                    </td>
                    <td>
                      <input type="text" name="termin[<?= (int) $t['id'] ?>][hinweis]"
                             value="<?= h($t['hinweis']) ?>"
                             placeholder="<?= $frei ? 'Ferien, Feiertag …' : 'z. B. Spiegelraum' ?>"
                             aria-label="Hinweis zum <?= h(date('d.m.Y', $zeit)) ?>">
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="termin-speichern">
            <button type="submit" class="btn btn-primary">Änderungen speichern</button>
            <span class="form-note">
              Danach steht der Plan sofort auf der Website.
            </span>
          </div>
        </form>
      <?php endif; ?>
    </section>

    <!-- ---------- Sonderfälle ---------- -->
    <details class="termin-block termin-sonderfall">
      <summary class="tool-btn">Sonderfälle: einzelner Termin, CSV, aufräumen</summary>

      <div class="verwaltung-layout">
        <div>
          <h3 class="abschnitt-titel">Einzelnen Termin hinzufügen</h3>
          <p class="termin-erklaerung">
            Für alles, was nicht in den Wochenrhythmus passt – einen Lehrgang etwa
            oder eine Gürtelprüfung.
          </p>
          <form method="post" action="" class="contact-form">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="aktion" value="speichern">

            <div class="field-row">
              <p class="field">
                <label for="datum">Datum</label>
                <input type="date" id="datum" name="datum" required>
              </p>
              <p class="field">
                <label for="ort">Ort</label>
                <select id="ort" name="ort">
                  <option value="steines">Halle am Steines</option>
                  <option value="schloss">Halle am Schloss</option>
                  <option value="frei">Kein Training</option>
                </select>
              </p>
            </div>

            <div class="field-row">
              <p class="field">
                <label for="zeit">Uhrzeit</label>
                <input type="text" id="zeit" name="zeit" placeholder="09:30 – 11:30">
              </p>
              <p class="field">
                <label for="gruppe">Gruppe</label>
                <input type="text" id="gruppe" name="gruppe" placeholder="Lehrgang">
              </p>
            </div>

            <p class="field">
              <label for="hinweis">Hinweis <span class="optional">(optional)</span></label>
              <input type="text" id="hinweis" name="hinweis" placeholder="Spiegelraum">
            </p>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary">Termin anlegen</button>
            </div>
          </form>
        </div>

        <div>
          <h3 class="abschnitt-titel">Ganzen Plan als Datei</h3>
          <form method="post" action="" enctype="multipart/form-data" class="contact-form">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="aktion" value="import">

            <p class="field">
              <label for="csv">CSV-Datei</label>
              <input type="file" id="csv" name="csv" accept=".csv,text/csv">
              <span class="feld-hinweis">
                Eine Zeile je Termin, in der ersten Zeile die Spaltennamen:
                <code>datum;zeit;gruppe;ort;hinweis</code>. Ort <code>steines</code>,
                <code>schloss</code> oder <code>frei</code>.
                <a href="?export=csv">Aktuelle Termine herunterladen</a> – das ist
                zugleich die Vorlage.
              </span>
            </p>

            <p class="field">
              <label for="modus">Bisheriger Plan</label>
              <select id="modus" name="modus">
                <option value="ersetzen">Ersetzen – die Datei ist der neue Plan</option>
                <option value="ergaenzen">Ergänzen – Vorhandenes bleibt stehen</option>
              </select>
            </p>

            <p class="field field-check">
              <input type="checkbox" id="trotzdem" name="trotzdem" value="1">
              <label for="trotzdem">Fehlerhafte Zeilen überspringen</label>
            </p>

            <div class="form-actions">
              <button type="submit" class="btn btn-ghost">Datei einlesen</button>
            </div>
          </form>

          <?php if ($vergangene > 0): ?>
            <h3 class="abschnitt-titel">Aufräumen</h3>
            <form method="post" action=""
                  onsubmit="return confirm('<?= (int) $vergangene ?> vergangene Termine löschen?')">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="aktion" value="aufraeumen">
              <p class="termin-erklaerung">
                Vergangene Termine stehen nicht mehr auf der Website, bleiben aber in
                der Datenbank. <?= (int) $vergangene ?> Stück sind es derzeit.
              </p>
              <button type="submit" class="tool-btn">Vergangene Termine löschen</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </details>
  </div>
</main>

<?php fuss(); ?>
