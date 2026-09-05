<?php
/**
 * Verwaltung, Teil 3: Trainingstermine.
 *
 * Hier pflegt das Trainerteam den Terminplan – einzeln oder als CSV-Datei
 * für eine ganze Saison. Beim Speichern schreibt die Seite die Termine in
 * die öffentliche Website zurück (siehe lib/termine.php), sodass niemand
 * dafür an den Quelltext muss.
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

    <div class="verwaltung-layout">
      <div>
        <h2 class="abschnitt-titel">Terminplan hochladen</h2>
        <form method="post" action="" enctype="multipart/form-data" class="contact-form">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="aktion" value="import">

          <p class="field">
            <label for="csv">CSV-Datei</label>
            <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
            <span class="feld-hinweis">
              Eine Zeile je Termin, in der ersten Zeile die Spaltennamen:
              <code>datum;zeit;gruppe;ort;hinweis</code>. Datum als
              <code>2026-09-05</code> oder <code>05.09.2026</code>, Ort
              <code>steines</code>, <code>schloss</code> oder <code>frei</code>
              für „kein Training". Der Wochentag wird selbst berechnet.
              <a href="?export=csv">Aktuelle Termine als CSV herunterladen</a> –
              das ist zugleich die Vorlage.
            </span>
          </p>

          <p class="field">
            <label for="modus">Was soll mit dem bisherigen Plan passieren?</label>
            <select id="modus" name="modus">
              <option value="ersetzen">Ersetzen – die Datei ist der neue Plan</option>
              <option value="ergaenzen">Ergänzen – vorhandene Termine bleiben stehen</option>
            </select>
          </p>

          <p class="field field-check">
            <input type="checkbox" id="trotzdem" name="trotzdem" value="1">
            <label for="trotzdem">Fehlerhafte Zeilen überspringen und den Rest übernehmen</label>
          </p>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Datei einlesen</button>
          </div>
        </form>

        <h2 class="abschnitt-titel">Einzelnen Termin hinzufügen</h2>
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
              <input type="text" id="gruppe" name="gruppe" placeholder="Training &amp; Bambini">
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

        <?php if ($vergangene > 0): ?>
          <form method="post" action="" class="konto-form"
                onsubmit="return confirm('<?= (int) $vergangene ?> vergangene Termine löschen?')">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="aktion" value="aufraeumen">
            <button type="submit" class="tool-btn">
              <?= (int) $vergangene ?> vergangene Termine aufräumen
            </button>
          </form>
        <?php endif; ?>
      </div>

      <aside class="verwaltung-liste">
        <h2>Kommende Termine</h2>
        <?php if (!$kommende): ?>
          <p class="listen-leer">
            Noch keine kommenden Termine. Lade links eine CSV-Datei hoch oder lege
            einen einzelnen Termin an.
          </p>
        <?php endif; ?>
        <ul>
          <?php foreach ($kommende as $t): ?>
            <li>
              <strong>
                <?= h(date('d.m.Y', (int) strtotime($t['datum']))) ?> ·
                <?= h(termin_wochentag($t['datum'])) ?>
              </strong>
              <span class="listen-zeile">
                <?= h($t['zeit']) ?> · <?= h($t['gruppe']) ?>
              </span>
              <span class="listen-zeile">
                <?= h(termin_orte()[$t['ort']]['name']) ?>
                <?= $t['hinweis'] !== '' ? ' · ' . h($t['hinweis']) : '' ?>
              </span>

              <details>
                <summary class="tool-btn tool-btn-klein">Bearbeiten</summary>

                <form method="post" action="" class="konto-form">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="aktion" value="speichern">
                  <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                  <label>Datum
                    <input type="date" name="datum" value="<?= h($t['datum']) ?>" required>
                  </label>
                  <label>Uhrzeit
                    <input type="text" name="zeit" value="<?= h($t['zeit']) ?>">
                  </label>
                  <label>Gruppe
                    <input type="text" name="gruppe" value="<?= h($t['gruppe']) ?>">
                  </label>
                  <label>Ort
                    <select name="ort">
                      <?php foreach (termin_orte() as $schluessel => $o): ?>
                        <option value="<?= h($schluessel) ?>" <?= $t['ort'] === $schluessel ? 'selected' : '' ?>>
                          <?= h($o['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                  <label>Hinweis
                    <input type="text" name="hinweis" value="<?= h($t['hinweis']) ?>">
                  </label>
                  <button type="submit" class="tool-btn">Speichern</button>
                </form>

                <form method="post" action="" class="konto-form"
                      onsubmit="return confirm('Termin am <?= h(date('d.m.Y', (int) strtotime($t['datum']))) ?> löschen?')">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="aktion" value="loeschen">
                  <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                  <button type="submit" class="tool-btn">Termin löschen</button>
                </form>
              </details>
            </li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </div>
  </div>
</main>

<?php fuss(); ?>
