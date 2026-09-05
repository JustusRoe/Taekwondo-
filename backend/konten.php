<?php
/**
 * Verwaltung, Teil 2: Zugänge.
 *
 * Konten legt ausschließlich das Trainerteam an – es gibt bewusst keine
 * Selbstregistrierung. Name und Startpasswort bekommen die Mitglieder im
 * Training ausgehändigt; beim ersten Anmelden setzen sie ein eigenes
 * (siehe passwort.php). Danach kennt das Passwort nur noch das Mitglied
 * selbst, in der Datenbank steht ohnehin nur der Hash.
 *
 * Trainerzugänge sind zusätzlich geschützt: Wer einen davon abstuft,
 * stilllegt, dessen Passwort neu setzt oder ihn löscht, muss das eigene
 * Passwort noch einmal eingeben. Gelöscht werden können sie erst, wenn sie
 * stillgelegt sind – ein Fehlklick kostet damit nie einen Zugang.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/verwaltung.php';

$mitglied = trainer_verlangen();
$meldung = '';
$fehler  = '';
$neuesPasswort = '';        // wird genau einmal angezeigt
$neuerBenutzer = '';

/** Liest ein Konto oder bricht ab. */
function konto(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM mitglieder WHERE id = ?');
    $stmt->execute([$id]);
    $k = $stmt->fetch();
    return is_array($k) ? $k : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $aktion = (string) ($_POST['aktion'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);
    $ziel   = $id > 0 ? konto($id) : null;

    /* Bestätigung mit dem eigenen Passwort – verlangt, sobald ein
       Trainerzugang angefasst wird. */
    $zielIstTrainer = $ziel && $ziel['rolle'] === 'trainer';
    $bestaetigt = eigenes_passwort_stimmt($mitglied['id'], (string) ($_POST['bestaetigung'] ?? ''));

    /* ---------- Konto anlegen ---------- */
    if ($aktion === 'anlegen') {
        $benutzer = strtolower(trim((string) $_POST['benutzername']));
        $name     = trim((string) $_POST['name']);
        $email    = trim((string) $_POST['email']);
        $rolle    = $_POST['rolle'] === 'trainer' ? 'trainer' : 'mitglied';
        $passwort = (string) $_POST['passwort'];

        $fehler = benutzername_pruefen($benutzer)
               ?: passwort_pruefen($passwort, $rolle, $benutzer);

        if ($fehler === '' && $name === '') {
            $fehler = 'Bitte den Namen angeben.';
        }
        if ($fehler === '' && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fehler = 'Die E-Mail-Adresse sieht nicht gültig aus.';
        }
        // Ein neues Trainerkonto vergibt Rechte an der Verwaltung – dafür
        // noch einmal das eigene Passwort.
        if ($fehler === '' && $rolle === 'trainer' && !$bestaetigt) {
            $fehler = 'Für ein Trainerkonto bitte unten das eigene Passwort bestätigen.';
        }

        if ($fehler === '') {
            try {
                db()->prepare(
                    'INSERT INTO mitglieder (benutzername, name, email, passwort_hash, rolle, passwort_wechseln)
                     VALUES (?, ?, ?, ?, ?, 1)'
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

        // Abstufen oder Stilllegen sind die Schritte, die jemandem den
        // Zugang zur Verwaltung nehmen – nur die brauchen die Bestätigung.
        $heikel = $zielIstTrainer && ($rolle !== 'trainer' || !$aktiv);

        if (!$ziel) {
            $fehler = 'Diesen Zugang gibt es nicht mehr.';
        } elseif ($id === $mitglied['id'] && ($rolle !== 'trainer' || !$aktiv)) {
            $fehler = 'Das eigene Konto kann nicht abgestuft oder stillgelegt werden.';
        } elseif (($rolle !== 'trainer' || !$aktiv) && letzter_trainer($id)) {
            $fehler = 'Das ist das letzte aktive Trainerkonto – sonst kommt niemand mehr in die Verwaltung.';
        } elseif ($name === '') {
            $fehler = 'Bitte den Namen angeben.';
        } elseif ($heikel && !$bestaetigt) {
            $fehler = 'Zum Abstufen oder Stilllegen eines Trainerzugangs bitte das eigene Passwort bestätigen.';
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
        // Rolle und Benutzername des Ziels bestimmen die Vorgaben.
        $fehler = passwort_pruefen(
            $passwort,
            $ziel['rolle'] ?? 'mitglied',
            (string) ($ziel['benutzername'] ?? '')
        );

        if ($fehler === '' && !$ziel) {
            $fehler = 'Diesen Zugang gibt es nicht mehr.';
        } elseif ($fehler === '' && $zielIstTrainer && $id !== $mitglied['id'] && !$bestaetigt) {
            $fehler = 'Zum Zurücksetzen eines Trainerpassworts bitte das eigene Passwort bestätigen.';
        }

        if ($fehler === '') {
            // passwort_wechseln = 1: Das hier gesetzte Passwort kennt auch,
            // wer es setzt. Beim nächsten Anmelden muss die Person ein
            // eigenes festlegen.
            db()->prepare(
                'UPDATE mitglieder SET passwort_hash = ?, passwort_wechseln = 1 WHERE id = ?'
            )->execute([password_hash($passwort, PASSWORD_DEFAULT), $id]);

            // Ab jetzt gilt nur noch das neue Passwort – alte Fehlversuche
            // sollen den Zugang nicht weiter blockieren.
            $neuerBenutzer = (string) $ziel['benutzername'];
            db()->prepare('DELETE FROM login_versuche WHERE benutzername = ?')
                ->execute([$neuerBenutzer]);
            $meldung = 'Neues Startpasswort gesetzt.';
            $neuesPasswort = $passwort;
        }
    }

    /* ---------- Konto löschen ---------- */
    if ($aktion === 'loeschen') {
        if (!$ziel) {
            $fehler = 'Diesen Zugang gibt es nicht mehr.';
        } elseif ($id === $mitglied['id']) {
            $fehler = 'Das eigene Konto lässt sich nicht löschen.';
        } elseif (letzter_trainer($id)) {
            $fehler = 'Das ist das letzte aktive Trainerkonto und darf nicht gelöscht werden.';
        } elseif ($zielIstTrainer && (int) $ziel['aktiv'] === 1) {
            $fehler = 'Trainerzugänge lassen sich erst löschen, wenn sie stillgelegt sind. '
                    . 'Bitte zuerst den Haken bei „Zugang aktiv" entfernen.';
        } elseif (!$bestaetigt) {
            $fehler = 'Zum Löschen bitte das eigene Passwort bestätigen.';
        } else {
            db()->prepare('DELETE FROM mitglieder WHERE id = ?')->execute([$id]);
            $meldung = 'Zugang von ' . $ziel['name'] . ' gelöscht.';
        }
    }
}

/* ---------- Suchen und filtern (ohne JavaScript, als GET) ---------- */
$suche  = trim((string) ($_GET['q'] ?? ''));
$fRolle = (string) ($_GET['rolle'] ?? '');
$fStatus = (string) ($_GET['status'] ?? '');

$wo = [];
$werte = [];
if ($suche !== '') {
    $wo[] = '(name LIKE ? OR benutzername LIKE ? OR email LIKE ?)';
    $wie = '%' . $suche . '%';
    array_push($werte, $wie, $wie, $wie);
}
if ($fRolle === 'trainer' || $fRolle === 'mitglied') {
    $wo[] = 'rolle = ?';
    $werte[] = $fRolle;
}
if ($fStatus === 'aktiv' || $fStatus === 'stillgelegt') {
    $wo[] = 'aktiv = ?';
    $werte[] = $fStatus === 'aktiv' ? 1 : 0;
}
if ($fStatus === 'startpasswort') {
    $wo[] = 'passwort_wechseln = 1';
}

$sql = 'SELECT * FROM mitglieder';
if ($wo) {
    $sql .= ' WHERE ' . implode(' AND ', $wo);
}
$sql .= ' ORDER BY rolle DESC, name';
$stmt = db()->prepare($sql);
$stmt->execute($werte);
$konten = $stmt->fetchAll();

/* Kennzahlen immer über alle Konten, nicht über die gefilterte Auswahl. */
$zahlen = db()->query(
    "SELECT COUNT(*) AS gesamt,
            SUM(CASE WHEN rolle = 'trainer'       THEN 1 ELSE 0 END) AS trainer,
            SUM(CASE WHEN aktiv = 0               THEN 1 ELSE 0 END) AS stillgelegt,
            SUM(CASE WHEN passwort_wechseln = 1   THEN 1 ELSE 0 END) AS startpasswort
       FROM mitglieder"
)->fetch();

/* Ein einzelnes Konto zum Bearbeiten */
$bearbeiten = isset($_GET['bearbeiten']) ? konto((int) $_GET['bearbeiten']) : null;

/** Baut eine Adresse mit den aktuellen Filtern und einer Änderung darin. */
function filter_url(array $aenderung = []): string
{
    $p = array_filter(array_merge([
        'q'      => $_GET['q'] ?? '',
        'rolle'  => $_GET['rolle'] ?? '',
        'status' => $_GET['status'] ?? '',
    ], $aenderung), static fn ($w) => (string) $w !== '');
    return 'konten.php' . ($p ? '?' . http_build_query($p) : '');
}

kopf('Zugänge', $mitglied);
?>

<main id="main" class="member-main">
  <div class="container">

    <div class="member-head">
      <div>
        <h1>Zugänge verwalten</h1>
        <p>Mitglieder bekommen Benutzername und Startpasswort von euch. Beim ersten
           Anmelden legen sie ein eigenes Passwort fest – danach kennt es nur noch
           die Person selbst.</p>
      </div>
      <p class="member-count"><?= (int) $zahlen['gesamt'] ?> Konten</p>
    </div>

    <?php verwaltung_menue('konten.php'); ?>
    <?php hinweis($meldung, $fehler); ?>

    <ul class="kennzahlen">
      <li><strong><?= (int) $zahlen['gesamt'] ?></strong><span>Konten insgesamt</span></li>
      <li><strong><?= (int) $zahlen['trainer'] ?></strong><span>davon Trainer</span></li>
      <li><strong><?= (int) $zahlen['stillgelegt'] ?></strong><span>stillgelegt</span></li>
      <li><strong><?= (int) $zahlen['startpasswort'] ?></strong><span>noch mit Startpasswort</span></li>
    </ul>

    <?php if ($neuesPasswort !== ''): ?>
      <div class="passwort-kasten">
        <h2>Zugangsdaten zum Weitergeben</h2>
        <dl>
          <div><dt>Benutzername</dt><dd><?= h($neuerBenutzer) ?></dd></div>
          <div><dt>Startpasswort</dt><dd><?= h($neuesPasswort) ?></dd></div>
        </dl>
        <p>
          Bitte jetzt notieren. Nach dem Verlassen der Seite lässt sich das Passwort
          nicht mehr anzeigen – gespeichert ist nur eine Prüfsumme. Beim ersten
          Anmelden wird ein eigenes Passwort verlangt; ab dann kennt ihr es nicht mehr.
        </p>
      </div>
    <?php endif; ?>

    <?php if ($bearbeiten): ?>
      <?php
        $istTrainer = $bearbeiten['rolle'] === 'trainer';
        $istSelbst  = (int) $bearbeiten['id'] === $mitglied['id'];
      ?>
      <section class="konto-panel" id="bearbeiten">
        <div class="konto-panel-kopf">
          <h2><?= h($bearbeiten['name']) ?> bearbeiten</h2>
          <a class="tool-btn" href="<?= h(filter_url(['bearbeiten' => ''])) ?>">Schließen</a>
        </div>

        <?php if ($istTrainer): ?>
          <p class="konto-warnung">
            Das ist ein <strong>Trainerzugang</strong> mit Zugriff auf diese Verwaltung.
            Abstufen, Stilllegen, Passwort zurücksetzen und Löschen verlangen dein
            eigenes Passwort. Gelöscht werden kann er erst, wenn er stillgelegt ist.
          </p>
        <?php endif; ?>

        <div class="konto-panel-raster">
          <form method="post" action="" class="konto-form">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="aktion" value="aendern">
            <input type="hidden" name="id" value="<?= (int) $bearbeiten['id'] ?>">
            <h3>Angaben</h3>
            <label>Name
              <input type="text" name="name" value="<?= h($bearbeiten['name']) ?>" required>
            </label>
            <label>E-Mail
              <input type="email" name="email" value="<?= h((string) $bearbeiten['email']) ?>">
            </label>
            <label>Rolle
              <select name="rolle" <?= $istSelbst ? 'disabled' : '' ?>>
                <option value="mitglied" <?= $bearbeiten['rolle'] === 'mitglied' ? 'selected' : '' ?>>Mitglied</option>
                <option value="trainer"  <?= $istTrainer ? 'selected' : '' ?>>Trainer</option>
              </select>
              <?php if ($istSelbst): ?>
                <input type="hidden" name="rolle" value="trainer">
              <?php endif; ?>
            </label>
            <label class="kasten">
              <input type="checkbox" name="aktiv" value="1" <?= $bearbeiten['aktiv'] ? 'checked' : '' ?>
                     <?= $istSelbst ? 'disabled' : '' ?>>
              Zugang aktiv
              <?php if ($istSelbst): ?><input type="hidden" name="aktiv" value="1"><?php endif; ?>
            </label>
            <?php if ($istTrainer && !$istSelbst): ?>
              <label>Dein Passwort zur Bestätigung
                <input type="password" name="bestaetigung" autocomplete="current-password">
              </label>
            <?php endif; ?>
            <button type="submit" class="tool-btn">Speichern</button>
          </form>

          <form method="post" action="" class="konto-form">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="aktion" value="passwort">
            <input type="hidden" name="id" value="<?= (int) $bearbeiten['id'] ?>">
            <h3>Passwort zurücksetzen</h3>
            <p class="konto-hinweis">
              Setzt ein neues Startpasswort. Die Person muss beim nächsten Anmelden
              wieder ein eigenes festlegen.
            </p>
            <label>Neues Startpasswort
              <input type="text" name="passwort" required minlength="8"
                     value="<?= h(passwort_vorschlag()) ?>">
            </label>
            <?php if ($istTrainer && !$istSelbst): ?>
              <label>Dein Passwort zur Bestätigung
                <input type="password" name="bestaetigung" autocomplete="current-password">
              </label>
            <?php endif; ?>
            <button type="submit" class="tool-btn">Passwort setzen</button>
          </form>

          <div class="konto-form">
            <h3>Zugang löschen</h3>
            <?php if ($istSelbst): ?>
              <p class="konto-hinweis">Das eigene Konto lässt sich hier nicht löschen.</p>
            <?php elseif ($istTrainer && $bearbeiten['aktiv']): ?>
              <p class="konto-hinweis">
                Trainerzugänge lassen sich erst löschen, wenn sie stillgelegt sind.
                Nimm links den Haken bei „Zugang aktiv" heraus und speichere – danach
                erscheint hier die Schaltfläche.
              </p>
            <?php else: ?>
              <form method="post" action=""
                    onsubmit="return confirm('Zugang von <?= h($bearbeiten['name']) ?> endgültig löschen?')">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="aktion" value="loeschen">
                <input type="hidden" name="id" value="<?= (int) $bearbeiten['id'] ?>">
                <p class="konto-hinweis">Das lässt sich nicht rückgängig machen.</p>
                <label>Dein Passwort zur Bestätigung
                  <input type="password" name="bestaetigung" autocomplete="current-password" required>
                </label>
                <button type="submit" class="tool-btn">Zugang löschen</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <h2 class="abschnitt-titel">Vorhandene Zugänge</h2>

    <form method="get" action="konten.php" class="konten-filter">
      <p class="field">
        <label for="q">Suchen</label>
        <input type="search" id="q" name="q" value="<?= h($suche) ?>"
               placeholder="Name, Benutzername oder E-Mail">
      </p>
      <p class="field">
        <label for="rolle">Rolle</label>
        <select id="rolle" name="rolle">
          <option value="">alle</option>
          <option value="trainer"  <?= $fRolle === 'trainer'  ? 'selected' : '' ?>>Trainer</option>
          <option value="mitglied" <?= $fRolle === 'mitglied' ? 'selected' : '' ?>>Mitglied</option>
        </select>
      </p>
      <p class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="">alle</option>
          <option value="aktiv"         <?= $fStatus === 'aktiv'         ? 'selected' : '' ?>>aktiv</option>
          <option value="stillgelegt"   <?= $fStatus === 'stillgelegt'   ? 'selected' : '' ?>>stillgelegt</option>
          <option value="startpasswort" <?= $fStatus === 'startpasswort' ? 'selected' : '' ?>>noch mit Startpasswort</option>
        </select>
      </p>
      <button type="submit" class="tool-btn">Anzeigen</button>
      <?php if ($suche !== '' || $fRolle !== '' || $fStatus !== ''): ?>
        <a class="tool-btn" href="konten.php">Filter zurücksetzen</a>
      <?php endif; ?>
    </form>

    <?php if (!$konten): ?>
      <p class="listen-leer">Zu dieser Suche gibt es keinen Zugang.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="konten-tabelle">
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Benutzername</th>
              <th scope="col">Rolle</th>
              <th scope="col">Status</th>
              <th scope="col">Letzte Anmeldung</th>
              <th scope="col"><span class="visually-hidden">Aktion</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($konten as $k): ?>
              <tr<?= (int) $k['id'] === (int) ($bearbeiten['id'] ?? 0) ? ' class="ist-offen"' : '' ?>>
                <th scope="row"><?= h($k['name']) ?></th>
                <td><code><?= h($k['benutzername']) ?></code></td>
                <td>
                  <?php if ($k['rolle'] === 'trainer'): ?>
                    <span class="marke marke-trainer">Trainer</span>
                  <?php else: ?>
                    Mitglied
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!$k['aktiv']): ?>
                    <span class="marke marke-aus">stillgelegt</span>
                  <?php elseif ($k['passwort_wechseln']): ?>
                    <span class="marke marke-warten">Startpasswort</span>
                  <?php else: ?>
                    aktiv
                  <?php endif; ?>
                </td>
                <td><?= $k['letzter_login']
                        ? h(date('d.m.Y', strtotime((string) $k['letzter_login'])))
                        : '<span class="leer">noch nie</span>' ?></td>
                <td class="spalte-aktion">
                  <a class="tool-btn tool-btn-klein"
                     href="<?= h(filter_url(['bearbeiten' => (string) $k['id']])) ?>#bearbeiten">Bearbeiten</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <details class="anlegen-block">
      <summary class="tool-btn">Neuen Zugang anlegen</summary>
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
            <label for="neueRolle">Rolle</label>
            <select id="neueRolle" name="rolle">
              <option value="mitglied">Mitglied – sieht die Videothek</option>
              <option value="trainer">Trainer – sieht zusätzlich die Verwaltung</option>
            </select>
          </p>
        </div>

        <p class="field">
          <label for="passwort">Startpasswort</label>
          <span class="passwort-zeile">
            <input type="text" id="passwort" name="passwort" required minlength="8"
                   value="<?= h(passwort_vorschlag()) ?>">
            <button type="button" class="tool-btn" id="neuVorschlagen">Anderes</button>
          </span>
          <span class="feld-hinweis">Vorgeschlagen ist ein Passwort, das sich diktieren
            lässt. Es ist im Klartext zu sehen, damit ihr es weitergeben könnt – beim
            ersten Anmelden wird es durch ein eigenes ersetzt.</span>
        </p>

        <p class="field">
          <label for="bestaetigung">Dein Passwort <span class="optional">(nur für Trainerkonten)</span></label>
          <input type="password" id="bestaetigung" name="bestaetigung" autocomplete="current-password">
          <span class="feld-hinweis">Ein Trainerkonto darf in diese Verwaltung – deshalb
            dafür noch einmal das eigene Passwort.</span>
        </p>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Zugang anlegen</button>
        </div>
      </form>
    </details>
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
