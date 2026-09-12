<?php
/**
 * Erstes Trainerkonto anlegen – einmal, bei einer leeren Datenbank.
 *
 * Ohne ein Trainerkonto kommt niemand in die Verwaltung, um Zugänge
 * anzulegen. Früher stand dafür ein Konto mit festem Passwort in
 * schema.sql. Das ist eine schlechte Idee: Wer es nach dem Livegang zu
 * löschen vergisst, hat ein Konto mit öffentlich bekanntem Passwort auf
 * dem Server – und das Passwort steht dazu in einer Datei im Repository.
 *
 * Diese Seite ersetzt das. Sie funktioniert ausschließlich, solange die
 * Tabelle „mitglieder" leer ist; sobald das erste Konto steht, weist sie
 * jeden Aufruf ab. Es gibt hier bewusst kein vorgegebenes Passwort: Das
 * legt die Person fest, die das Konto anlegt, und niemand sonst kennt es.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/verwaltung.php';

/** Gibt es schon irgendein Konto? */
function konten_vorhanden(): bool
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM mitglieder')->fetchColumn() > 0;
    } catch (PDOException $e) {
        // Tabelle fehlt: Dann ist schema.sql noch nicht eingespielt.
        return false;
    }
}

/** Steht die Tabelle überhaupt? */
function tabelle_vorhanden(): bool
{
    try {
        db()->query('SELECT 1 FROM mitglieder LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

$fehler   = '';
$fertig   = false;
$gesperrt = konten_vorhanden();
$bereit   = tabelle_vorhanden();
$name     = '';
$benutzer = '';

if (!$gesperrt && $bereit && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    $name     = trim((string) ($_POST['name'] ?? ''));
    $benutzer = strtolower(trim((string) ($_POST['benutzer'] ?? '')));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $passwort = (string) ($_POST['passwort'] ?? '');
    $wieder   = (string) ($_POST['wiederholen'] ?? '');

    if ($benutzer === '') {
        $benutzer = benutzername_ableiten($name);
    }

    if ($name === '') {
        $fehler = 'Bitte den Namen angeben.';
    } elseif ($passwort !== $wieder) {
        $fehler = 'Die beiden Passwörter sind nicht gleich.';
    } else {
        $fehler = benutzername_pruefen($benutzer)
               ?: passwort_pruefen($passwort, 'trainer', $benutzer);
    }
    if ($fehler === '' && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler = 'Die E-Mail-Adresse sieht nicht gültig aus.';
    }

    if ($fehler === '') {
        try {
            // passwort_wechseln = 0: Das Passwort hat die Person selbst
            // gewählt, es kennt sonst niemand. Ein erzwungener Wechsel
            // beim ersten Anmelden hätte hier keinen Zweck.
            db()->prepare(
                'INSERT INTO mitglieder (benutzername, name, email, passwort_hash, rolle, passwort_wechseln)
                 VALUES (?, ?, ?, ?, ?, 0)'
            )->execute([
                $benutzer, $name, $email ?: null,
                password_hash($passwort, PASSWORD_DEFAULT), 'trainer',
            ]);
            $fertig   = true;
            $gesperrt = true;
        } catch (PDOException $e) {
            $fehler = 'Das Konto konnte nicht angelegt werden. Steht die '
                    . 'Datenbank aus schema.sql bereit?';
        }
    }
}

kopf('Einrichten', null, false);
?>

<main id="main">
  <div class="container login-wrap">
    <div class="login-card">

      <?php if ($fertig): ?>
        <h1>Fertig</h1>
        <p class="form-status" role="status">
          Das Trainerkonto <strong><?= h($benutzer) ?></strong> ist angelegt.
        </p>
        <p class="login-intro">
          Diese Seite ist damit gesperrt – ein zweites Konto lässt sich hier
          nicht anlegen. Weitere Zugänge entstehen nach dem Anmelden unter
          <em>Verwaltung → Zugänge</em>; für viele auf einmal gibt es dort
          „Mehrere Zugänge auf einmal anlegen" mit einer Liste zum
          Ausdrucken.
        </p>
        <p class="login-intro">
          <strong>Jetzt noch:</strong> diese Datei
          (<code>backend/einrichten.php</code>) vom Server löschen. Nötig
          ist es nicht, sie sperrt sich selbst – aber was nicht da ist,
          kann auch nicht schiefgehen.
        </p>
        <p><a class="btn btn-primary" href="login.php">Zur Anmeldung</a></p>

      <?php elseif (!$bereit): ?>
        <h1>Datenbank fehlt noch</h1>
        <p class="error" role="alert">
          Die Tabelle <code>mitglieder</code> gibt es nicht.
        </p>
        <p class="login-intro">
          Bitte zuerst <code>backend/schema.sql</code> in die Datenbank
          einspielen – bei IONOS über phpMyAdmin, Reiter <em>Importieren</em>.
          Danach diese Seite neu laden. Schritt für Schritt steht das in
          <code>LIVEGANG.md</code>.
        </p>

      <?php elseif ($gesperrt): ?>
        <h1>Schon eingerichtet</h1>
        <p class="error" role="alert">
          Es gibt bereits Zugänge. Diese Seite legt nur das allererste
          Trainerkonto an und ist deshalb gesperrt.
        </p>
        <p class="login-intro">
          Weitere Zugänge entstehen nach dem Anmelden unter
          <em>Verwaltung → Zugänge</em>. Wer sein Passwort vergessen hat,
          lässt es dort von einem Trainerkonto neu setzen.
        </p>
        <p><a class="btn btn-primary" href="login.php">Zur Anmeldung</a></p>

      <?php else: ?>
        <h1>Erstes Trainerkonto</h1>
        <p class="login-intro">
          Die Datenbank steht, es gibt aber noch keinen Zugang. Dieses erste
          Konto darf in die Verwaltung und legt von dort alle weiteren an.
          Das Passwort wählst du selbst – es wird nirgends vorgegeben und
          ist nur als verschlüsselter Abdruck gespeichert.
        </p>

        <?php if ($fehler !== ''): ?>
          <p class="error" role="alert"><?= h($fehler) ?></p>
        <?php endif; ?>

        <form method="post" action="">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <p class="field">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required autofocus
                   value="<?= h($name) ?>">
          </p>
          <p class="field">
            <label for="benutzer">Benutzername <span class="optional">(optional)</span></label>
            <input type="text" id="benutzer" name="benutzer"
                   pattern="[a-z0-9._\-]{3,60}" value="<?= h($benutzer) ?>">
            <span class="feld-hinweis">
              Leer lassen genügt: Aus „Michael Buchhold" wird dann
              <code>m.buchhold</code>.
            </span>
          </p>
          <p class="field">
            <label for="email">E-Mail <span class="optional">(optional)</span></label>
            <input type="email" id="email" name="email">
          </p>
          <p class="field">
            <label for="passwort">Passwort</label>
            <input type="password" id="passwort" name="passwort" required
                   minlength="12" autocomplete="new-password">
            <span class="feld-hinweis">
              Mindestens 12 Zeichen. Am besten drei, vier Wörter
              hintereinander – leichter zu merken und schwerer zu raten als
              ein kurzes mit Sonderzeichen.
            </span>
          </p>
          <p class="field">
            <label for="wiederholen">Passwort wiederholen</label>
            <input type="password" id="wiederholen" name="wiederholen" required
                   minlength="12" autocomplete="new-password">
          </p>
          <button type="submit" class="btn btn-primary">Konto anlegen</button>
        </form>
      <?php endif; ?>

    </div>
  </div>
</main>

<?php fuss(); ?>
