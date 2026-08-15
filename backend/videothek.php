<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/seite.php';

$mitglied = anmeldung_verlangen();

$bereich = (string) ($_GET['bereich'] ?? 'alle');
$suche   = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT v.*, (SELECT COUNT(*) FROM kapitel k WHERE k.video_id = v.id) AS anzahl_kapitel
          FROM videos v
         WHERE v.sichtbar = 1';
$werte = [];

if ($bereich !== 'alle') {
    $sql .= ' AND v.bereich = ?';
    $werte[] = $bereich;
}
if ($suche !== '') {
    $sql .= ' AND (v.titel LIKE ? OR v.beschreibung LIKE ? OR v.trainer LIKE ?)';
    $muster = '%' . $suche . '%';
    array_push($werte, $muster, $muster, $muster);
}
$sql .= ' ORDER BY v.veroeffentlicht_am DESC, v.id DESC';

$stmt = db()->prepare($sql);
$stmt->execute($werte);
$videos = $stmt->fetchAll();

$gesamt = (int) db()->query('SELECT COUNT(*) FROM videos WHERE sichtbar = 1')->fetchColumn();
$bereiche = db()->query(
    'SELECT DISTINCT bereich FROM videos WHERE sichtbar = 1 ORDER BY bereich'
)->fetchAll(PDO::FETCH_COLUMN);

kopf('Videothek', $mitglied);
?>

<main id="main" class="member-main">
  <div class="container">

    <div class="member-head">
      <div>
        <h1>Videothek</h1>
        <p>Aufnahmen aus dem Training, sortiert nach Bereich. Jedes Video ist in Abschnitte geteilt.</p>
      </div>
      <p class="member-count">
        <?= count($videos) === $gesamt
              ? $gesamt . ' Videos'
              : count($videos) . ' von ' . $gesamt . ' Videos' ?>
      </p>
    </div>

    <form class="filters" method="get" action="">
      <span class="tool-label">Bereich</span>
      <a class="chip" href="?<?= http_build_query(['bereich' => 'alle', 'q' => $suche]) ?>"
         aria-pressed="<?= $bereich === 'alle' ? 'true' : 'false' ?>">Alle</a>
      <?php foreach ($bereiche as $b): ?>
        <a class="chip" href="?<?= http_build_query(['bereich' => $b, 'q' => $suche]) ?>"
           aria-pressed="<?= $bereich === $b ? 'true' : 'false' ?>"><?= h($b) ?></a>
      <?php endforeach; ?>
      <span class="filter-spacer"></span>
      <span class="filter-search">
        <label class="visually-hidden" for="q">Videos durchsuchen</label>
        <input type="search" id="q" name="q" value="<?= h($suche) ?>" placeholder="Suchen …">
        <input type="hidden" name="bereich" value="<?= h($bereich) ?>">
      </span>
    </form>

    <?php if ($videos): ?>
      <div class="video-grid">
        <?php foreach ($videos as $v) { video_karte($v); } ?>
      </div>
    <?php else: ?>
      <p class="empty-state">Zu dieser Auswahl gibt es noch kein Video.</p>
    <?php endif; ?>

  </div>
</main>

<?php fuss(); ?>
