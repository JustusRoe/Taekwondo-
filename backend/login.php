<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/seite.php';

if (angemeldet()) {
    header('Location: videothek.php');
    exit;
}

$fehler = '';
$benutzer = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $benutzer = trim((string) ($_POST['benutzer'] ?? ''));
    $passwort = (string) ($_POST['passwort'] ?? '');

    if ($benutzer === '' || $passwort === '') {
        $fehler = 'Bitte Benutzername und Passwort eingeben.';
    } elseif (gesperrt($benutzer)) {
        $c = konfiguration();
        $fehler = 'Zu viele Fehlversuche. Bitte in ' . (int) $c['sperrminuten'] . ' Minuten erneut versuchen.';
    } elseif (anmelden($benutzer, $passwort)) {
        $weiter = (string) ($_GET['weiter'] ?? '');
        // Nur seiteneigene Ziele zulassen, keine fremden Adressen
        $ziel = (str_starts_with($weiter, '/') && !str_starts_with($weiter, '//'))
            ? $weiter
            : 'videothek.php';
        header('Location: ' . $ziel);
        exit;
    } else {
        $fehler = 'Benutzername oder Passwort stimmen nicht.';
    }
}

kopf('Anmeldung');
?>

<main id="main">
  <div class="container login-wrap">
    <div class="login-card">
      <h1>Anmeldung</h1>
      <p class="login-intro">
        Der Mitgliederbereich enthält Trainingsvideos zum Formenlauf (Poomsae) und zum
        Einschrittkampf (Hanbon Kyorugi). Zugang erhalten aktive Mitglieder der Abteilung.
      </p>

      <form method="post" action="">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <p class="field">
          <label for="benutzer">Benutzername</label>
          <input type="text" id="benutzer" name="benutzer" autocomplete="username"
                 value="<?= h($benutzer) ?>" required autofocus>
        </p>
        <p class="field">
          <label for="passwort">Passwort</label>
          <input type="password" id="passwort" name="passwort" autocomplete="current-password" required>
        </p>
        <?php if ($fehler !== ''): ?>
          <p class="error" role="alert"><?= h($fehler) ?></p>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Anmelden</button>
      </form>

      <p class="login-help">
        Zugangsdaten vergessen? Bitte an die Abteilungsleitung wenden – Konten werden im Training vergeben.
      </p>
    </div>
  </div>
</main>

<?php fuss(); ?>
