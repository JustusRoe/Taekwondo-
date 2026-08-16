<?php
/**
 * Verwaltung, Teil 1: Videos.
 *
 * Die Videodatei wird hier hochgeladen – kein FTP mehr nötig. Der Browser
 * schickt sie stückweise an upload.php und legt sie unter einer Kennung ab.
 * Erst beim Absenden dieses Formulars bekommt sie ihren endgültigen Namen,
 * abgeleitet aus dem geprüften Kürzel.
 *
 * Trainer/in und Dauer werden nicht eingetippt: Die Trainerin oder der Trainer
 * ist das angemeldete Konto, die Dauer liest der Browser aus der Videodatei.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/verwaltung.php';

$mitglied = trainer_verlangen();
$meldung = '';
$fehler  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $aktion = (string) ($_POST['aktion'] ?? '');

    /* ---------- Eintrag samt Datei löschen ---------- */
    if ($aktion === 'loeschen') {
        $stmt = db()->prepare('SELECT dateiname FROM videos WHERE id = ?');
        $stmt->execute([(int) $_POST['id']]);
        $datei = (string) ($stmt->fetchColumn() ?: '');

        db()->prepare('DELETE FROM videos WHERE id = ?')->execute([(int) $_POST['id']]);

        // basename() schließt aus, dass ein manipulierter Eintrag aus dem
        // Videoordner herausführt.
        $pfad = rtrim(konfiguration()['video_ordner'], '/') . '/' . basename($datei);
        if ($datei !== '' && is_file($pfad)) {
            unlink($pfad);
            $meldung = 'Eintrag und Videodatei gelöscht.';
        } else {
            $meldung = 'Eintrag gelöscht.';
        }
    }

    /* ---------- Neues Video ---------- */
    if ($aktion === 'anlegen') {
        $slug     = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $uploadId = (string) ($_POST['upload_id'] ?? '');
        $endung   = strtolower((string) ($_POST['endung'] ?? ''));
        $teil     = upload_ordner() . '/' . $uploadId . '.part';

        if (!preg_match('/^[a-z0-9-]{3,80}$/', $slug)) {
            $fehler = 'Das Kürzel darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten.';
        } elseif (!preg_match('/^[a-f0-9]{32}$/', $uploadId) || !is_file($teil)) {
            $fehler = 'Es wurde keine Videodatei hochgeladen. Bitte zuerst eine Datei auswählen.';
        } elseif (!in_array($endung, ['mp4', 'webm', 'mov'], true)) {
            $fehler = 'Nur MP4, WebM und MOV sind zulässig.';
        } else {
            $dateiname = $slug . '.' . $endung;
            $ziel = rtrim(konfiguration()['video_ordner'], '/') . '/' . $dateiname;

            // Standbild, das der Browser aus dem Video geschnitten hat
            $posterTeil = upload_ordner() . '/' . $uploadId . '.jpg';
            $posterDatei = null;
            if (is_file($posterTeil)) {
                $posterDatei = $slug . '.jpg';
            }

            try {
                db()->prepare(
                    'INSERT INTO videos (slug, titel, bereich, grad, trainer, beschreibung,
                                         dateiname, posterdatei, dauer, veroeffentlicht_am)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $slug,
                    trim((string) $_POST['titel']),
                    trim((string) $_POST['bereich']),
                    trim((string) $_POST['grad']),
                    $mitglied['name'],                       // nicht eingetippt
                    trim((string) $_POST['beschreibung']),
                    $dateiname,
                    $posterDatei,
                    max(0, (int) $_POST['dauer']),           // aus der Datei gelesen
                    (string) ($_POST['datum'] ?: date('Y-m-d')),
                ]);

                // Erst nach dem erfolgreichen Eintrag umbenennen: Schlägt das
                // Einfügen fehl, bleibt der Videoordner unverändert.
                rename($teil, $ziel);
                if ($posterDatei !== null) {
                    rename($posterTeil, rtrim(konfiguration()['poster_ordner'], '/') . '/' . $posterDatei);
                }
                $meldung = 'Video „' . trim((string) $_POST['titel']) . '" wurde angelegt.';
            } catch (PDOException $e) {
                $fehler = str_contains($e->getMessage(), 'uq_slug') || str_contains($e->getMessage(), 'slug')
                    ? 'Dieses Kürzel ist bereits vergeben.'
                    : 'Speichern nicht möglich. Bitte die Angaben prüfen.';
            }
        }
    }
}

$videos = db()->query('SELECT * FROM videos ORDER BY veroeffentlicht_am DESC, id DESC')->fetchAll();

kopf('Verwaltung', $mitglied);
?>

<main id="main" class="member-main">
  <div class="container">

    <div class="member-head">
      <div>
        <h1>Videos verwalten</h1>
        <p>Datei auswählen, Angaben ergänzen, fertig. Die Datei landet im geschützten
           Ordner und ist nur nach Anmeldung abrufbar.</p>
      </div>
    </div>

    <?php verwaltung_menue('admin.php'); ?>
    <?php hinweis($meldung, $fehler); ?>

    <div class="verwaltung-layout">
      <div>
        <form method="post" action="" class="contact-form" id="videoForm">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="aktion" value="anlegen">
          <input type="hidden" name="upload_id" id="uploadId">
          <input type="hidden" name="endung" id="endung">
          <input type="hidden" name="dauer" id="dauer" value="0">

          <p class="field">
            <label for="videodatei">Videodatei</label>
            <input type="file" id="videodatei" accept="video/mp4,video/webm,video/quicktime">
            <span class="feld-hinweis">MP4 (H.264) ist das sicherste Format – es läuft in
              allen Browsern. Höchstens <?= (int) (konfiguration()['max_video_mb'] ?? 800) ?> MB.</span>
          </p>

          <div class="upload-stand" id="uploadStand" hidden>
            <div class="upload-balken"><span id="uploadBalken"></span></div>
            <p class="upload-text" id="uploadText"></p>
          </div>

          <noscript>
            <p class="form-status ist-fehler">
              Das Hochladen benötigt JavaScript. Ohne JavaScript muss die Datei
              weiterhin per FTP in den Videoordner gelegt werden.
            </p>
          </noscript>

          <div class="field-row">
            <p class="field">
              <label for="titel">Titel</label>
              <input type="text" id="titel" name="titel" required>
            </p>
            <p class="field">
              <label for="slug">Kürzel für die Adresse</label>
              <input type="text" id="slug" name="slug" placeholder="taegeuk-il-jang" required>
              <span class="feld-hinweis">Wird aus dem Titel vorgeschlagen, lässt sich ändern.</span>
            </p>
          </div>

          <div class="field-row">
            <p class="field">
              <label for="bereich">Bereich</label>
              <input type="text" id="bereich" name="bereich" list="bereiche" required>
              <datalist id="bereiche">
                <option value="Poomsae"><option value="Hanbon Kyorugi">
              </datalist>
            </p>
            <p class="field">
              <label for="grad">Gürtelgrad</label>
              <input type="text" id="grad" name="grad" value="Alle Grade">
            </p>
          </div>

          <div class="field-row">
            <p class="field">
              <label for="datum">Veröffentlicht am</label>
              <input type="date" id="datum" name="datum" value="<?= date('Y-m-d') ?>">
            </p>
            <p class="field" id="dauerFeld" hidden>
              <label for="dauerManuell">Länge in Sekunden</label>
              <input type="text" id="dauerManuell" inputmode="numeric" value="0">
              <span class="feld-hinweis">Dein Browser konnte die Länge nicht aus der Datei
                lesen – bitte von Hand eintragen.</span>
            </p>
          </div>

          <p class="field">
            <label for="beschreibung">Beschreibung</label>
            <textarea id="beschreibung" name="beschreibung" rows="4"></textarea>
          </p>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary" id="speichern" disabled>Video anlegen</button>
            <span class="form-note" id="speichernNote">Zuerst eine Videodatei auswählen.</span>
          </div>
        </form>
      </div>

      <aside class="verwaltung-liste">
        <h2><?= count($videos) ?> Videos</h2>
        <ul>
          <?php foreach ($videos as $v): ?>
            <li>
              <strong><?= h($v['titel']) ?></strong>
              <span class="listen-zeile">
                <?= h($v['bereich']) ?> · <?= h(mmss((int) $v['dauer'])) ?> Min.
                <?= $v['trainer'] !== '' ? ' · ' . h($v['trainer']) : '' ?>
              </span>
              <form method="post" action=""
                    onsubmit="return confirm('Eintrag und Videodatei wirklich löschen?')">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="aktion" value="loeschen">
                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                <button type="submit" class="tool-btn tool-btn-klein">Löschen</button>
              </form>
            </li>
          <?php endforeach; ?>
          <?php if (!$videos): ?>
            <li><span class="listen-zeile">Noch keine Videos angelegt.</span></li>
          <?php endif; ?>
        </ul>
      </aside>
    </div>
  </div>
</main>

<script>
/* Videodatei stückweise hochladen und dabei Dauer und Kürzel ableiten. */
(function () {
  'use strict';
  var feld      = document.getElementById('videodatei');
  var stand     = document.getElementById('uploadStand');
  var balken    = document.getElementById('uploadBalken');
  var text      = document.getElementById('uploadText');
  var speichern = document.getElementById('speichern');
  var note      = document.getElementById('speichernNote');
  var csrf      = document.querySelector('#videoForm [name=csrf]').value;

  /* Kürzel aus dem Titel vorschlagen, solange es niemand selbst angefasst hat */
  var titel = document.getElementById('titel');
  var slug  = document.getElementById('slug');
  var slugBerührt = false;
  slug.addEventListener('input', function () { slugBerührt = true; });
  titel.addEventListener('input', function () {
    if (slugBerührt) return;
    slug.value = titel.value.toLowerCase()
      .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
  });

  /* Länge und Standbild aus der Datei holen. Beides kann der Browser selbst,
     ohne dass etwas hochgeladen sein muss – damit entfallen zwei Eingaben.
     Klappt es nicht (etwa weil der Browser das Format nicht dekodiert), wird
     die Länge von Hand abgefragt und das Video bekommt kein Vorschaubild. */
  function ausVideoLesen(datei) {
    return new Promise(function (fertig) {
      var v = document.createElement('video');
      var url = URL.createObjectURL(datei);
      var erledigt = false;
      function aufgeben() {
        if (erledigt) return;
        erledigt = true;
        URL.revokeObjectURL(url);
        fertig({ dauer: 0, bild: null });
      }
      v.preload = 'metadata';
      v.muted = true;
      v.onerror = aufgeben;
      setTimeout(aufgeben, 15000);          // hängengebliebene Dekodierung

      v.onloadedmetadata = function () {
        var dauer = isFinite(v.duration) ? Math.round(v.duration) : 0;
        // Standbild aus dem ersten Drittel – am Anfang ist oft noch nichts zu sehen
        v.currentTime = Math.min(Math.max(dauer / 3, 1), 10);
        v.onseeked = function () {
          if (erledigt) return;
          var c = document.createElement('canvas');
          var breite = 640;
          c.width = breite;
          c.height = Math.round(breite * (v.videoHeight || 360) / (v.videoWidth || 640));
          try {
            c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
            c.toBlob(function (blob) {
              erledigt = true;
              URL.revokeObjectURL(url);
              fertig({ dauer: dauer, bild: blob });
            }, 'image/jpeg', 0.82);
          } catch (e) {
            erledigt = true;
            URL.revokeObjectURL(url);
            fertig({ dauer: dauer, bild: null });
          }
        };
      };
      v.src = url;
    });
  }

  function mb(bytes) { return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB'; }

  function melden(nachricht, fehler) {
    text.textContent = nachricht;
    text.classList.toggle('ist-fehler', !!fehler);
  }

  feld.addEventListener('change', async function () {
    var datei = feld.files[0];
    if (!datei) return;

    speichern.disabled = true;
    stand.hidden = false;
    balken.style.width = '0%';
    melden('Datei wird vorbereitet …', false);

    var endung = (datei.name.split('.').pop() || '').toLowerCase();
    if (['mp4', 'webm', 'mov'].indexOf(endung) === -1) {
      melden('Nur MP4, WebM und MOV sind zulässig.', true);
      return;
    }

    var gelesen = await ausVideoLesen(datei);
    var dauer = gelesen.dauer;
    document.getElementById('dauer').value = dauer;
    document.getElementById('endung').value = endung;

    /* Konnte der Browser die Länge nicht ermitteln, muss sie eingetippt werden. */
    var dauerFeld = document.getElementById('dauerFeld');
    var dauerManuell = document.getElementById('dauerManuell');
    dauerFeld.hidden = dauer > 0;
    if (dauer === 0) {
      dauerManuell.addEventListener('input', function () {
        document.getElementById('dauer').value = parseInt(dauerManuell.value, 10) || 0;
      });
    }

    /* Upload anmelden: Kennung und Stückgröße kommen vom Server */
    var start = new FormData();
    start.append('csrf', csrf);
    start.append('aktion', 'start');
    var antwort = await fetch('upload.php', { method: 'POST', body: start }).then(r => r.json());
    if (antwort.fehler) { melden(antwort.fehler, true); return; }

    if (datei.size > antwort.max_bytes) {
      melden('Die Datei ist ' + mb(datei.size) + ' groß – erlaubt sind '
             + mb(antwort.max_bytes) + '.', true);
      return;
    }

    var id = antwort.id, stueck = antwort.stueck, versatz = 0;
    while (versatz < datei.size) {
      var bis = Math.min(versatz + stueck, datei.size);
      var daten = new FormData();
      daten.append('csrf', csrf);
      daten.append('id', id);
      daten.append('versatz', versatz);
      daten.append('stueck', datei.slice(versatz, bis));

      var r = await fetch('upload.php', { method: 'POST', body: daten }).then(r => r.json());
      if (r.fehler) { melden(r.fehler, true); return; }

      versatz = r.geschrieben;
      balken.style.width = Math.round(versatz / datei.size * 100) + '%';
      melden('Hochgeladen: ' + mb(versatz) + ' von ' + mb(datei.size), false);
    }

    /* Standbild hinterherschicken – schlägt es fehl, ist das kein Beinbruch:
       Die Kachel zeigt dann eben kein Vorschaubild. */
    var bildOk = false;
    if (gelesen.bild) {
      var bd = new FormData();
      bd.append('csrf', csrf);
      bd.append('aktion', 'poster');
      bd.append('id', id);
      bd.append('bild', gelesen.bild, 'poster.jpg');
      var pr = await fetch('upload.php', { method: 'POST', body: bd }).then(r => r.json());
      bildOk = !pr.fehler;
    }

    document.getElementById('uploadId').value = id;
    balken.style.width = '100%';
    melden('Fertig hochgeladen (' + mb(datei.size)
           + (dauer > 0 ? ', Länge ' + Math.floor(dauer / 60) + ':'
              + String(dauer % 60).padStart(2, '0') : '')
           + ')' + (bildOk ? ' – Vorschaubild aus dem Video übernommen.' : '.'), false);
    speichern.disabled = false;
    note.textContent = 'Die Datei liegt bereit und bekommt beim Speichern den Namen des Kürzels.';
  });
})();
</script>

<?php fuss(); ?>
