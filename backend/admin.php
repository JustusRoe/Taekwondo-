<?php
/**
 * Kleine Verwaltung für das Trainerteam: Videos eintragen und
 * Abschnitte festlegen. Die MP4-Datei selbst wird per FTP in den
 * privaten Videoordner geladen – hier wird nur der Dateiname vermerkt.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/seite.php';

$mitglied = trainer_verlangen();
$meldung = '';
$fehler  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();

    $aktion = (string) ($_POST['aktion'] ?? '');

    if ($aktion === 'loeschen') {
        $stmt = db()->prepare('DELETE FROM videos WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $meldung = 'Eintrag gelöscht. Die Videodatei selbst bleibt auf dem Server liegen.';
    }

    if ($aktion === 'anlegen') {
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $datei = basename(trim((string) ($_POST['dateiname'] ?? '')));

        if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) {
            $fehler = 'Das Kürzel darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten.';
        } elseif ($datei === '' || !preg_match('/\.(mp4|webm|mov)$/i', $datei)) {
            $fehler = 'Bitte den Dateinamen inklusive Endung angeben (z. B. formenlauf.mp4).';
        } elseif (!is_file(rtrim(konfiguration()['video_ordner'], '/') . '/' . $datei)) {
            $fehler = 'Die Datei liegt noch nicht im Videoordner. Bitte zuerst per FTP hochladen.';
        } else {
            try {
                db()->beginTransaction();

                $stmt = db()->prepare(
                    'INSERT INTO videos (slug, titel, bereich, grad, trainer, beschreibung,
                                         dateiname, posterdatei, dauer, veroeffentlicht_am)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $slug,
                    trim((string) $_POST['titel']),
                    trim((string) $_POST['bereich']),
                    trim((string) $_POST['grad']),
                    trim((string) $_POST['trainer']),
                    trim((string) $_POST['beschreibung']),
                    $datei,
                    trim((string) $_POST['posterdatei']) ?: null,
                    (int) $_POST['dauer'],
                    (string) ($_POST['datum'] ?: date('Y-m-d')),
                ]);
                $videoId = (int) db()->lastInsertId();

                // Abschnitte: je Zeile „Sekunden | Bezeichnung"
                $zeilen = preg_split('/\R/', (string) ($_POST['kapitel'] ?? '')) ?: [];
                $einfuegen = db()->prepare(
                    'INSERT INTO kapitel (video_id, startsekunde, bezeichnung) VALUES (?, ?, ?)'
                );
                foreach ($zeilen as $zeile) {
                    if (trim($zeile) === '') {
                        continue;
                    }
                    [$sek, $text] = array_pad(explode('|', $zeile, 2), 2, '');
                    $sek = trim($sek);
                    // „1:20" ebenso zulassen wie „80"
                    if (str_contains($sek, ':')) {
                        [$m, $s] = array_pad(explode(':', $sek, 2), 2, '0');
                        $sekunden = (int) $m * 60 + (int) $s;
                    } else {
                        $sekunden = (int) $sek;
                    }
                    $einfuegen->execute([$videoId, $sekunden, trim($text)]);
                }

                db()->commit();
                $meldung = 'Video „' . $_POST['titel'] . '" wurde angelegt.';
            } catch (PDOException $e) {
                db()->rollBack();
                $fehler = str_contains($e->getMessage(), 'uq_slug')
                    ? 'Dieses Kürzel ist bereits vergeben.'
                    : 'Speichern nicht möglich. Bitte die Angaben prüfen.';
            }
        }
    }
}

$videos = db()->query(
    'SELECT v.*, (SELECT COUNT(*) FROM kapitel k WHERE k.video_id = v.id) AS anzahl_kapitel
       FROM videos v ORDER BY v.veroeffentlicht_am DESC'
)->fetchAll();

kopf('Verwaltung', $mitglied);
?>

<main id="main" class="member-main">
  <div class="container">

    <div class="member-head">
      <div>
        <h1>Videos verwalten</h1>
        <p>Neue Aufnahmen zuerst per FTP in den privaten Videoordner laden, danach hier eintragen.</p>
      </div>
    </div>

    <?php if ($meldung !== ''): ?>
      <p class="form-status"><?= h($meldung) ?></p>
    <?php endif; ?>
    <?php if ($fehler !== ''): ?>
      <p class="form-status" style="border-left-color:#b01c2e"><strong><?= h($fehler) ?></strong></p>
    <?php endif; ?>

    <div class="player-layout">
      <div class="player-main">
        <form method="post" action="" class="contact-form">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="aktion" value="anlegen">

          <div class="field-row">
            <p class="field">
              <label for="titel">Titel</label>
              <input type="text" id="titel" name="titel" required>
            </p>
            <p class="field">
              <label for="slug">Kürzel für die Adresse</label>
              <input type="text" id="slug" name="slug" placeholder="poomsae-taegeuk-il-jang" required>
            </p>
          </div>

          <div class="field-row">
            <p class="field">
              <label for="bereich">Bereich</label>
              <input type="text" id="bereich" name="bereich" list="bereiche" required>
              <datalist id="bereiche">
                <option value="Grundschule"><option value="Poomsae"><option value="Partnertraining">
                <option value="Selbstverteidigung"><option value="Wettkampf"><option value="Athletik">
              </datalist>
            </p>
            <p class="field">
              <label for="grad">Gürtelgrad</label>
              <input type="text" id="grad" name="grad" value="Alle Grade">
            </p>
          </div>

          <div class="field-row">
            <p class="field">
              <label for="dateiname">Dateiname im Videoordner</label>
              <input type="text" id="dateiname" name="dateiname" placeholder="formenlauf.mp4" required>
            </p>
            <p class="field">
              <label for="posterdatei">Vorschaubild <span class="optional">(optional)</span></label>
              <input type="text" id="posterdatei" name="posterdatei" placeholder="formenlauf.jpg">
            </p>
          </div>

          <div class="field-row">
            <p class="field">
              <label for="trainer">Trainer/in</label>
              <input type="text" id="trainer" name="trainer">
            </p>
            <p class="field">
              <label for="dauer">Dauer in Sekunden</label>
              <input type="text" id="dauer" name="dauer" inputmode="numeric" required>
            </p>
          </div>

          <p class="field">
            <label for="datum">Veröffentlicht am</label>
            <input type="text" id="datum" name="datum" value="<?= date('Y-m-d') ?>">
          </p>

          <p class="field">
            <label for="beschreibung">Beschreibung</label>
            <textarea id="beschreibung" name="beschreibung" rows="4"></textarea>
          </p>

          <p class="field">
            <label for="kapitel">Abschnitte – je Zeile: Startzeit | Bezeichnung</label>
            <textarea id="kapitel" name="kapitel" rows="5"
              placeholder="0 | Vorbereitung&#10;1:20 | Erste Sequenz&#10;2:45 | Korrekturen"></textarea>
          </p>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Video anlegen</button>
            <span class="form-note">Die Videodatei muss vorher im Ordner liegen.</span>
          </div>
        </form>
      </div>

      <aside class="chapter-panel">
        <h2><?= count($videos) ?> Einträge</h2>
        <ol class="chapter-list">
          <?php foreach ($videos as $v): ?>
            <li>
              <div style="padding:14px 22px">
                <strong style="display:block;font-size:.93rem"><?= h($v['titel']) ?></strong>
                <span style="font-size:.82rem;color:var(--muted)">
                  <?= h($v['bereich']) ?> · <?= (int) $v['anzahl_kapitel'] ?> Abschnitte
                  · <?= h(mmss((int) $v['dauer'])) ?>
                </span>
                <form method="post" action="" style="margin-top:8px"
                      onsubmit="return confirm('Eintrag wirklich löschen?')">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="aktion" value="loeschen">
                  <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                  <button type="submit" class="tool-btn" style="font-size:.78rem">Löschen</button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </aside>
    </div>
  </div>
</main>

<?php fuss(); ?>
