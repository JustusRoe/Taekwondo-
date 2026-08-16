<?php
/**
 * Verwaltung, Teil 2: Zugänge.
 *
 * Konten legt ausschließlich das Trainerteam an – es gibt bewusst keine
 * Selbstregistrierung. Name und Passwort bekommen die Mitglieder im Training
 * ausgehändigt. Deshalb schlägt die Seite ein Passwort vor, das sich vorlesen
 * und aufschreiben lässt, und zeigt es genau einmal an: In der Datenbank liegt
 * nur der Hash, aus dem sich das Passwort nicht zurückrechnen lässt.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/verwaltung.php';

$mitglied = trainer_verlangen();
$meldung = '';
$fehler  = '';
$neuesPasswort = '';        // wird genau einmal angezeigt
$neuerBenutzer = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $aktion = (string) ($_POST['aktion'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);

    /* ---------- Konto anlegen ---------- */
    if ($aktion === 'anlegen') {
        $benutzer = strtolower(trim((string) $_POST['benutzername']));
        $name     = trim((string) $_POST['name']);
        $email    = trim((string) $_POST['email']);
        $rolle    = $_POST['rolle'] === 'trainer' ? 'trainer' : 'mitglied';
        $passwort = (string) $_POST['passwort'];

        $fehler = benutzername_pruefen($benutzer) ?: passwort_pruefen($passwort);

        if ($fehler === '' && $name === '') {
            $fehler = 'Bitte den Namen angeben.';
        }
        if ($fehler === '' && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fehler = 'Die E-Mail-Adresse sieht nicht gültig aus.';
        }

        if ($fehler === '') {
            try {
                db()->prepare(
                    'INSERT INTO mitglieder (benutzername, name, email, passwort_hash, rolle)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $benutzer, $name, $email ?: null,
                    password_hash($passwort, PASSWORD_DEFAULT), $rolle,
                ]);
                $meldung = 'Zugang für ' . $name . ' wurde angelegt.';
                $neuesPasswort = $passwort;
                $neuerBenutzer = $benutzer;
            } catch (PDOException $e) {
                $fehler = 'Diesen Benutzernamen gibt es bereits.';
            }
        }
    }

    /* ---------- Konto ändern ---------- */
    if ($aktion === 'aendern') {
        $name  = trim((string) $_POST['name']);
        $email = trim((string) $_POST['email']);
        $rolle = $_POST['rolle'] === 'trainer' ? 'trainer' : 'mitglied';
        $aktiv = isset($_POST['aktiv']) ? 1 : 0;

        // Wer sich selbst die Trainerrolle nimmt oder sich stilllegt, käme
        // nicht mehr in die Verwaltung zurück.
        if ($id === $mitglied['id'] && ($rolle !== 'trainer' || !$aktiv)) {
            $fehler = 'Das eigene Konto kann nicht abgestuft oder stillgelegt werden.';
        } elseif (($rolle !== 'trainer' || !$aktiv) && letzter_trainer($id)) {
            $fehler = 'Das ist das letzte aktive Trainerkonto – sonst kommt niemand mehr in die Verwaltung.';
        } elseif ($name === '') {
            $fehler = 'Bitte den Namen angeben.';
        } else {
            db()->prepare(
                'UPDATE mitglieder SET name = ?, email = ?, rolle = ?, aktiv = ? WHERE id = ?'
            )->execute([$name, $email ?: null, $rolle, $aktiv, $id]);
            $meldung = 'Zugang aktualisiert.';
        }
    }

    /* ---------- Passwort neu setzen ---------- */
    if ($aktion === 'passwort') {
        $passwort = (string) $_POST['passwort'];
        $fehler = passwort_pruefen($passwort);
        if ($fehler === '') {
            db()->prepare('UPDATE mitglieder SET passwort_hash = ? WHERE id = ?')
                ->execute([password_hash($passwort, PASSWORD_DEFAULT), $id]);
            // Ab jetzt gilt nur noch das neue Passwort – alte Fehlversuche
            // sollen den Zugang nicht weiter blockieren.
            $stmt = db()->prepare('SELECT benutzername FROM mitglieder WHERE id = ?');
            $stmt->execute([$id]);
            $neuerBenutzer = (string) ($stmt->fetchColumn() ?: '');
            db()->prepare('DELETE FROM login_versuche WHERE benutzername = ?')
                ->execute([$neuerBenutzer]);
            $meldung = 'Neues Passwort gesetzt.';
            $neuesPasswort = $passwort;
        }
    }

    /* ---------- Konto löschen ---------- */
    if ($aktion === 'loeschen') {
        if ($id === $mitglied['id']) {
            $fehler = 'Das eigene Konto lässt sich nicht löschen.';
        } elseif (letzter_trainer($id)) {
            $fehler = 'Das ist das letzte aktive Trainerkonto und darf nicht gelöscht werden.';
        } else {
            db()->prepare('DELETE FROM mitglieder WHERE id = ?')->execute([$id]);
            $meldung = 'Zugang gelöscht.';
        }
    }
}

$konten = db()->query(
    'SELECT * FROM mitglieder ORDER BY rolle DESC, name'
)->fetchAll();

kopf('Zugänge', $mitglied);
?>

<main id="main" class="member-main">
  <div class="container">

    <div class="member-head">
      <div>
        <h1>Zugänge verwalten</h1>
        <p>Mitglieder bekommen Benutzernamen und Passwort von euch – eine
           Selbstanmeldung gibt es nicht.</p>
      </div>
      <p class="member-count"><?= count($konten) ?> Konten</p>
    </div>

    <?php verwaltung_menue('konten.php'); ?>
    <?php hinweis($meldung, $fehler); ?>

    <?php if ($neuesPasswort !== ''): ?>
      <div class="passwort-kasten">
        <h2>Zugangsdaten zum Weitergeben</h2>
        <dl>
          <div><dt>Benutzername</dt><dd><?= h($neuerBenutzer) ?></dd></div>
          <div><dt>Passwort</dt><dd><?= h($neuesPasswort) ?></dd></div>
        </dl>
        <p>
          Bitte jetzt notieren. Nach dem Verlassen der Seite lässt sich das Passwort
          nicht mehr anzeigen – gespeichert ist nur eine Prüfsumme. Vergessene
          Passwörter werden hier neu gesetzt, nicht ausgelesen.
        </p>
      </div>
    <?php endif; ?>

    <div class="verwaltung-layout">
      <div>
        <h2 class="abschnitt-titel">Neuen Zugang anlegen</h2>
        <form method="post" action="" class="contact-form">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="aktion" value="anlegen">

          <div class="field-row">
            <p class="field">
              <label for="name">Name</label>
              <input type="text" id="name" name="name" required>
            </p>
            <p class="field">
              <label for="benutzername">Benutzername</label>
              <input type="text" id="benutzername" name="benutzername" required
                     pattern="[a-z0-9._\-]{3,60}">
              <span class="feld-hinweis">Kleinbuchstaben, Ziffern, Punkt, Bindestrich.</span>
            </p>
          </div>

          <div class="field-row">
            <p class="field">
              <label for="email">E-Mail <span class="optional">(optional)</span></label>
              <input type="email" id="email" name="email">
            </p>
            <p class="field">
              <label for="rolle">Rolle</label>
              <select id="rolle" name="rolle">
                <option value="mitglied">Mitglied – sieht die Videothek</option>
                <option value="trainer">Trainer – sieht zusätzlich die Verwaltung</option>
              </select>
            </p>
          </div>

          <p class="field">
            <label for="passwort">Passwort</label>
            <span class="passwort-zeile">
              <input type="text" id="passwort" name="passwort" required minlength="8"
                     value="<?= h(passwort_vorschlag()) ?>">
              <button type="button" class="tool-btn" id="neuVorschlagen">Anderes</button>
            </span>
            <span class="feld-hinweis">Vorgeschlagen ist ein Passwort, das sich diktieren
              lässt. Es ist im Klartext zu sehen, damit ihr es weitergeben könnt.</span>
          </p>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Zugang anlegen</button>
          </div>
        </form>
      </div>

      <aside class="verwaltung-liste">
        <h2>Vorhandene Zugänge</h2>
        <ul>
          <?php foreach ($konten as $k): ?>
            <li>
              <strong><?= h($k['name']) ?></strong>
              <span class="listen-zeile">
                <?= h($k['benutzername']) ?> ·
                <?= $k['rolle'] === 'trainer' ? 'Trainer' : 'Mitglied' ?>
                <?= $k['aktiv'] ? '' : ' · stillgelegt' ?>
              </span>
              <span class="listen-zeile">
                <?= $k['letzter_login']
                      ? 'zuletzt angemeldet ' . h(date('d.m.Y', strtotime((string) $k['letzter_login'])))
                      : 'noch nie angemeldet' ?>
              </span>
              <details>
                <summary class="tool-btn tool-btn-klein">Bearbeiten</summary>

                <form method="post" action="" class="konto-form">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="aktion" value="aendern">
                  <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                  <label>Name
                    <input type="text" name="name" value="<?= h($k['name']) ?>" required>
                  </label>
                  <label>E-Mail
                    <input type="email" name="email" value="<?= h((string) $k['email']) ?>">
                  </label>
                  <label>Rolle
                    <select name="rolle">
                      <option value="mitglied" <?= $k['rolle'] === 'mitglied' ? 'selected' : '' ?>>Mitglied</option>
                      <option value="trainer"  <?= $k['rolle'] === 'trainer'  ? 'selected' : '' ?>>Trainer</option>
                    </select>
                  </label>
                  <label class="kasten">
                    <input type="checkbox" name="aktiv" value="1" <?= $k['aktiv'] ? 'checked' : '' ?>>
                    Zugang aktiv
                  </label>
                  <button type="submit" class="tool-btn">Speichern</button>
                </form>

                <form method="post" action="" class="konto-form">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="aktion" value="passwort">
                  <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                  <label>Neues Passwort
                    <input type="text" name="passwort" required minlength="8"
                           value="<?= h(passwort_vorschlag()) ?>">
                  </label>
                  <button type="submit" class="tool-btn">Passwort setzen</button>
                </form>

                <?php if ($k['id'] !== $mitglied['id']): ?>
                  <form method="post" action="" class="konto-form"
                        onsubmit="return confirm('Zugang von <?= h($k['name']) ?> wirklich löschen?')">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="aktion" value="loeschen">
                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                    <button type="submit" class="tool-btn">Zugang löschen</button>
                  </form>
                <?php endif; ?>
              </details>
            </li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </div>
  </div>
</main>

<script>
/* „Anderes" holt einen neuen Vorschlag, ohne die Seite neu zu laden. */
document.getElementById('neuVorschlagen').addEventListener('click', function () {
  var woerter = ['Anker','Ahorn','Blume','Brücke','Delfin','Distel','Eiche','Falke','Feder',
    'Fluss','Garten','Gipfel','Grille','Hafen','Halle','Hammer','Hügel','Insel','Kanu',
    'Kiesel','Kranich','Kupfer','Lampe','Leiter','Linde','Möwe','Nadel','Nebel','Otter',
    'Pfeil','Quelle','Rabe','Reiher','Ruder','Salbei','Schilf','Segel','Silber','Spatz',
    'Stufe','Tanne','Taube','Teich','Trommel','Ufer','Vogel','Waage','Weide','Welle',
    'Wiese','Wolke','Zeder','Zirkel'];
  function wort() { return woerter[Math.floor(Math.random() * woerter.length)]; }
  document.getElementById('passwort').value =
    wort() + '-' + wort() + '-' + (100 + Math.floor(Math.random() * 900));
});
</script>

<?php fuss(); ?>
