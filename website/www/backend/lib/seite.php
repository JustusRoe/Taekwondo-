<?php
/**
 * Gemeinsamer Rahmen der Seiten – nutzt dieselben Stylesheets wie
 * die öffentliche Website, damit die Optik identisch ist.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Pfad zur öffentlichen Website (anpassen, falls backend/ woanders liegt). */
const WEB = '..';

function initialen(string $name): string
{
    $teile = preg_split('/\s+/', trim($name)) ?: [];
    $kurz  = '';
    foreach (array_slice($teile, 0, 2) as $t) {
        $kurz .= mb_strtoupper(mb_substr($t, 0, 1));
    }
    return $kurz !== '' ? $kurz : 'M';
}

function kopf(string $titel, ?array $mitglied = null, bool $navigation = true): void
{
    $web = WEB;
    ?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titel) ?> – Mitgliederbereich | TV 1897 Steinau e.V.</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= $web ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= $web ?>/assets/css/mitglieder.css">
<link rel="icon" href="<?= $web ?>/assets/img/favicon.svg" type="image/svg+xml">
</head>
<body>

<a class="skip-link" href="#main">Zum Inhalt springen</a>

<header class="site-header member-header" id="top">
  <div class="container header-inner">
    <a class="brand" href="<?= $web ?>/index.html" aria-label="Startseite Taekwondo im TV 1897 Steinau">
      <img class="brand-logo" src="<?= $web ?>/assets/img/tv-steinau-logo.png" alt="Wappen des TV 1897 Steinau" width="440" height="299">
      <span class="brand-text">
        <strong>Taekwondo</strong>
        <span>TV 1897 Steinau e.V.</span>
      </span>
    </a>
    <span class="member-badge">Mitgliederbereich</span>

    <nav class="member-nav" aria-label="Mitgliederbereich">
      <?php if ($mitglied): ?>
        <span class="member-user">
          <span class="member-avatar" aria-hidden="true"><?= h(initialen($mitglied['name'])) ?></span>
          <span><?= h($mitglied['name']) ?></span>
        </span>
        <?php if ($navigation): ?>
          <a href="videothek.php">Videothek</a>
          <?php if ($mitglied['rolle'] === 'trainer'): ?>
            <a href="admin.php">Verwaltung</a>
          <?php endif; ?>
          <a href="passwort.php">Passwort</a>
        <?php endif; ?>
        <a href="logout.php">Abmelden</a>
      <?php else: ?>
        <a href="<?= $web ?>/index.html">Zurück zur Website</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php
}

function fuss(): void
{
    $web = WEB;
    ?>
<footer class="site-footer">
  <div class="container footer-bottom" style="border-top:0;">
    <p>© <?= date('Y') ?> TV 1897 Steinau e.V.</p>
    <ul>
      <li><a href="<?= $web ?>/impressum.html">Impressum</a></li>
      <li><a href="<?= $web ?>/datenschutz.html">Datenschutz</a></li>
      <li><a href="<?= $web ?>/index.html">Startseite</a></li>
    </ul>
  </div>
</footer>
</body>
</html>
<?php
}

/** Eine Videokachel für die Übersicht. */
function video_karte(array $v): void
{
    // Ohne Vorschaubild bleibt die Fläche dunkel – ein <img> ohne Dateinamen
    // würde sonst eine ins Leere laufende Anfrage auslösen.
    $poster = $v['posterdatei']
        ? konfiguration()['poster_url'] . $v['posterdatei']
        : null;
    ?>
  <a class="video-card" href="video.php?v=<?= urlencode($v['slug']) ?>">
    <span class="video-thumb">
      <?php if ($poster): ?>
        <img src="<?= h($poster) ?>" alt="" loading="lazy" width="640" height="360">
      <?php endif; ?>
      <span class="play-badge" aria-hidden="true"></span>
      <span class="video-duration"><?= h(mmss((int) $v['dauer'])) ?></span>
    </span>
    <span class="video-body">
      <span class="video-tag"><?= h($v['bereich']) ?></span>
      <h3><?= h($v['titel']) ?></h3>
      <span class="video-sub"><?= h($v['grad']) ?></span>
      <span class="video-foot">
        <span><?= h($v['trainer']) ?></span>
        <span><?= h(date('d.m.Y', strtotime((string) $v['veroeffentlicht_am']))) ?></span>
      </span>
    </span>
  </a>
    <?php
}
