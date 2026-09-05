<?php
/**
 * Eigenes Passwort setzen.
 *
 * Zugänge werden im Training vergeben; das Startpasswort kennt deshalb
 * immer auch die Person, die den Zugang angelegt hat. Hier setzt jedes
 * Mitglied ein eigenes – bei einem frischen Konto erzwungen, sonst
 * jederzeit freiwillig. Nach dem Wechsel kennt das Passwort nur noch
 * das Mitglied selbst; in der Datenbank steht ohnehin nur der Hash.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/verwaltung.php';

$mitglied = anmeldung_verlangen();
$erstmalig = $mitglied['passwort_wechseln'];

$meldung = '';
$fehler  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $alt         = (string) ($_POST['alt'] ?? '');
    $neu         = (string) ($_POST['neu'] ?? '');
    $wiederholen = (string) ($_POST['wiederholen'] ?? '');

    if (!eigenes_passwort_stimmt($mitglied['id'], $alt)) {
        $fehler = 'Das bisherige Passwort stimmt nicht.';
    } elseif ($neu !== $wiederholen) {
        $fehler = 'Die beiden neuen Passwörter sind nicht gleich.';
    } elseif ($neu === $alt) {
        $fehler = 'Bitte ein anderes als das bisherige Passwort wählen.';
    } else {
        $fehler = passwort_pruefen($neu);
    }

    if ($fehler === '') {
        db()->prepare(
            'UPDATE mitglieder
                SET passwort_hash = ?, passwort_wechseln = 0, passwort_geaendert_am = ?
              WHERE id = ?'
        )->execute([password_hash($neu, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $mitglied['id']]);

        $_SESSION['passwort_wechseln'] = false;
        // Nach einem Passwortwechsel eine frische Sitzungskennung.
        session_regenerate_id(true);

        $meldung = 'Das Passwort wurde geändert. Nur du kennst es jetzt.';
        $erstmalig = false;
    }
}

kopf('Passwort ändern', $mitglied, !$erstmalig);
?>

<main id="main">
  <div class="container login-wrap">
    <div class="login-card">
      <h1><?= $erstmalig ? 'Eigenes Passwort festlegen' : 'Passwort ändern' ?></h1>

      <?php if ($erstmalig): ?>
        <p class="login-intro">
          Dein Zugang hat noch das Passwort, das dir im Training genannt wurde – das
          kennt auch die Person, die den Zugang angelegt hat. Bitte lege jetzt ein
          eigenes fest. Danach geht es weiter.
        </p>
      <?php else: ?>
        <p class="login-intro">
          Das neue Passwort gilt sofort. Vergessene Passwörter kann das Trainerteam
          nicht auslesen, sondern nur neu setzen.
        </p>
      <?php endif; ?>

      <?php if ($meldung !== ''): ?>
        <p class="form-status" role="status"><?= h($meldung) ?></p>
      <?php endif; ?>
      <?php if ($fehler !== ''): ?>
        <p class="error" role="alert"><?= h($fehler) ?></p>
      <?php endif; ?>

      <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <p class="field">
          <label for="alt">Bisheriges Passwort</label>
          <input type="password" id="alt" name="alt" autocomplete="current-password" required autofocus>
        </p>
        <p class="field">
          <label for="neu">Neues Passwort</label>
          <input type="password" id="neu" name="neu" autocomplete="new-password"
                 required minlength="8">
          <span class="feld-hinweis">Mindestens 8 Zeichen.</span>
        </p>
        <p class="field">
          <label for="wiederholen">Neues Passwort wiederholen</label>
          <input type="password" id="wiederholen" name="wiederholen" autocomplete="new-password"
                 required minlength="8">
        </p>
        <button type="submit" class="btn btn-primary">Passwort speichern</button>
      </form>

      <?php if (!$erstmalig): ?>
        <p class="login-help"><a href="videothek.php">Zurück zur Videothek</a></p>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php fuss(); ?>
