<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/seite.php';

$mitglied = anmeldung_verlangen();

$slug = (string) ($_GET['v'] ?? '');
$stmt = db()->prepare('SELECT * FROM videos WHERE slug = ? AND sichtbar = 1');
$stmt->execute([$slug]);
$video = $stmt->fetch();

if (!$video) {
    http_response_code(404);
    kopf('Nicht gefunden', $mitglied);
    echo '<main id="main" class="member-main"><div class="container">'
       . '<h1>Video nicht gefunden</h1>'
       . '<p><a class="crumb" href="videothek.php">Zurück zur Videothek</a></p>'
       . '</div></main>';
    fuss();
    exit;
}

$stmt = db()->prepare(
    'SELECT * FROM videos WHERE sichtbar = 1 AND id <> ?
      ORDER BY veroeffentlicht_am DESC LIMIT 3'
);
$stmt->execute([$video['id']]);
$weitere = $stmt->fetchAll();

/* Liegt neben der MP4-Datei eine gleichnamige WebM-Fassung, wird sie als
   Ausweichquelle angeboten – für Browser ohne H.264. */
$basisname = pathinfo((string) $video['dateiname'], PATHINFO_FILENAME);
$hatWebm = is_file(rtrim(konfiguration()['video_ordner'], '/') . '/' . $basisname . '.webm');
$hatMp4  = is_file(rtrim(konfiguration()['video_ordner'], '/') . '/' . $basisname . '.mp4');

kopf($video['titel'], $mitglied);
?>

<main id="main" class="member-main">
  <div class="container">
    <a class="crumb" href="videothek.php">Zurück zur Videothek</a>

    <div class="player-layout">
      <div class="player-main">
        <div class="video-frame">
          <!-- Die Quelle zeigt auf stream.php, nicht auf die Datei selbst -->
          <video id="player" controls preload="metadata" playsinline
                 <?= $video['posterdatei']
                       ? 'poster="' . h(konfiguration()['poster_url'] . $video['posterdatei']) . '"'
                       : '' ?>>
            <?php if ($hatMp4): ?>
              <source src="stream.php?v=<?= urlencode($video['slug']) ?>&amp;f=mp4" type="video/mp4">
            <?php endif; ?>
            <?php if ($hatWebm): ?>
              <source src="stream.php?v=<?= urlencode($video['slug']) ?>&amp;f=webm" type="video/webm">
            <?php endif; ?>
            <?php if (!$hatMp4 && !$hatWebm): ?>
              <source src="stream.php?v=<?= urlencode($video['slug']) ?>">
            <?php endif; ?>
            Dein Browser kann dieses Video nicht abspielen.
          </video>
        </div>

        <div class="player-tools">
          <button class="tool-btn" type="button" id="back10">&minus; 10 s</button>
          <button class="tool-btn" type="button" id="fwd10">+ 10 s</button>
          <span class="tool-sep"></span>
          <span class="tool-label">Tempo</span>
          <button class="tool-btn" type="button" data-speed="0.5">0,5×</button>
          <button class="tool-btn" type="button" data-speed="0.75">0,75×</button>
          <button class="tool-btn" type="button" data-speed="1" aria-pressed="true">1×</button>
          <button class="tool-btn" type="button" data-speed="1.5">1,5×</button>
        </div>

        <h1><?= h($video['titel']) ?></h1>
        <p class="player-meta">
          <span><?= h($video['bereich']) ?></span>
          <span><?= h($video['grad']) ?></span>
          <span><?= h($video['trainer']) ?></span>
          <span><?= h(date('d.m.Y', strtotime((string) $video['veroeffentlicht_am']))) ?></span>
          <span><?= h(mmss((int) $video['dauer'])) ?> Min.</span>
        </p>
        <p class="player-desc"><?= nl2br(h((string) $video['beschreibung'])) ?></p>
      </div>
    </div>

    <?php if ($weitere): ?>
      <section class="next-up">
        <h2>Weitere Videos</h2>
        <div class="video-grid">
          <?php foreach ($weitere as $v) { video_karte($v); } ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
</main>

<script>
/* Spulen und Tempo – dieselbe Bedienung wie im Entwurf. */
(function () {
  var player = document.getElementById('player');

  document.getElementById('back10').addEventListener('click', function () {
    player.currentTime = Math.max(0, player.currentTime - 10);
  });
  document.getElementById('fwd10').addEventListener('click', function () {
    player.currentTime = Math.min(player.duration || 0, player.currentTime + 10);
  });

  var tempo = Array.prototype.slice.call(document.querySelectorAll('[data-speed]'));
  tempo.forEach(function (btn) {
    btn.addEventListener('click', function () {
      player.playbackRate = parseFloat(btn.dataset.speed);
      tempo.forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
    });
  });
})();
</script>

<?php fuss(); ?>
