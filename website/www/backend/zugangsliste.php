<?php
/**
 * Zugangsliste – die Startpasswörter dieser Sitzung, einmal zum Ausdrucken.
 *
 * Das Problem, das diese Seite löst: In der Datenbank steht nur der Hash
 * eines Passworts. Ein Startpasswort lässt sich später nicht mehr
 * auslesen, auch nicht vom Trainerteam. Wer zwanzig Zugänge anlegt, müsste
 * sich also zwanzig Passwörter im Moment des Anlegens abschreiben.
 *
 * Stattdessen sammelt die Verwaltung, was in dieser Sitzung vergeben
 * wurde – einzeln angelegt, in einer Ladung angelegt oder zurückgesetzt –
 * und zeigt es hier als eine Liste: zum Ausdrucken, als CSV-Datei oder als
 * Zettel je Person zum Ausschneiden.
 *
 * Die Liste liegt ausschließlich in der Sitzung. Sie endet mit dem
 * Abmelden, spätestens nach ABLAUF Sekunden, und mit einem Klick auf
 * „Liste schließen". Danach ist sie endgültig weg; wer ein Passwort dann
 * noch braucht, setzt es neu.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib/verwaltung.php';

$mitglied = trainer_verlangen();

/** Nach dieser Zeit gilt die Liste als vergessen (2 Stunden). */
const ABLAUF = 7200;

$liste = $_SESSION['zugangsliste'] ?? [];
$zeit  = (int) ($_SESSION['zugangsliste_zeit'] ?? 0);

if ($liste && $zeit > 0 && time() - $zeit > ABLAUF) {
    unset($_SESSION['zugangsliste'], $_SESSION['zugangsliste_zeit']);
    $liste = [];
    $abgelaufen = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_pruefen();
    if (($_POST['aktion'] ?? '') === 'schliessen') {
        unset($_SESSION['zugangsliste'], $_SESSION['zugangsliste_zeit']);
        header('Location: konten.php');
        exit;
    }
}

/* ---------- Als CSV herunterladen ---------- */
if (($_GET['csv'] ?? '') === '1' && $liste) {
    // Kein Zwischenspeicher: Die Datei enthält Startpasswörter.
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="zugaenge-'
         . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    $aus = fopen('php://output', 'w');
    // Byte-Order-Mark, damit Excel die Umlaute richtig liest.
    fwrite($aus, "\xEF\xBB\xBF");
    fputcsv($aus, ['Name', 'Benutzername', 'Startpasswort', 'Rolle'], ';');
    foreach ($liste as $e) {
        fputcsv($aus, [
            $e['name'], $e['benutzername'], $e['passwort'],
            $e['rolle'] === 'trainer' ? 'Trainer' : 'Mitglied',
        ], ';');
    }
    fclose($aus);
    exit;
}

/* Diese Seite darf nirgends zwischengespeichert werden. */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

kopf('Zugangsliste', $mitglied, true);
?>

<style>
  /* Nur diese Seite braucht das – der Ausdruck soll ohne Menü und
     Farbflächen auskommen, damit er auf einem Schwarzweißdrucker
     lesbar bleibt. */
  .zl-warnung {
    border: 2px solid var(--rot, #c8102e);
    border-radius: 8px;
    padding: 1rem 1.25rem;
    margin: 0 0 1.5rem;
    background: #fff6f7;
  }
  .zl-warnung p { margin: 0.35rem 0; }
  .zl-werkzeuge { display: flex; flex-wrap: wrap; gap: 0.75rem; margin: 0 0 1.5rem; }
  table.zl-tabelle { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
  table.zl-tabelle th, table.zl-tabelle td {
    text-align: left; padding: 0.55rem 0.7rem; border-bottom: 1px solid #ddd;
    vertical-align: top;
  }
  table.zl-tabelle th { font-size: 0.8rem; letter-spacing: 0.04em; text-transform: uppercase; }
  .zl-passwort {
    font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
    font-size: 1.05rem;
    white-space: nowrap;
  }
  /* Zettel zum Ausschneiden: jede Person einzeln, damit nicht die ganze
     Liste über den Tisch geht. */
  .zl-zettel { display: grid; grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr)); gap: 0.75rem; }
  .zl-zettel .zettel {
    border: 1px dashed #999; border-radius: 6px; padding: 0.8rem 0.9rem;
    font-size: 0.92rem; break-inside: avoid;
  }
  .zl-zettel .zettel strong { display: block; margin-bottom: 0.3rem; }
  .zl-zettel .zettel .zl-passwort { display: block; margin: 0.25rem 0 0.4rem; }
  .zl-zettel .zettel small { color: #555; }
  @media print {
    .site-header, .site-footer, .verwaltung-menue,
    .zl-werkzeuge, .zl-kein-druck { display: none !important; }
    .zl-warnung { border-color: #000; background: none; }
    body { background: #fff; }
    table.zl-tabelle th, table.zl-tabelle td { border-color: #000; }
  }
</style>

<main id="main">
  <div class="container">
    <?php verwaltung_menue('zugangsliste.php'); ?>

    <h1>Zugangsliste</h1>

    <?php if (!$liste): ?>
      <p class="form-status">
        <?= !empty($abgelaufen)
              ? 'Die Liste ist abgelaufen und wurde verworfen.'
              : 'Gerade ist keine Liste offen.' ?>
      </p>
      <p>
        Eine Liste entsteht, sobald Zugänge angelegt oder Passwörter
        zurückgesetzt werden. Sie sammelt alles aus dieser Sitzung an einer
        Stelle, damit nichts einzeln abgeschrieben werden muss.
      </p>
      <p><a class="btn btn-primary" href="konten.php">Zu den Zugängen</a></p>

    <?php else: ?>
      <div class="zl-warnung">
        <p><strong>Diese Liste gibt es genau einmal.</strong></p>
        <p>
          In der Datenbank steht nur der verschlüsselte Abdruck eines
          Passworts. Ein Startpasswort lässt sich später nicht mehr
          auslesen – auch nicht vom Trainerteam. Jetzt ausdrucken oder als
          Datei speichern; danach geht es nur noch über ein neues Passwort.
        </p>
        <p class="zl-kein-druck">
          Beim Ausdruck fallen Menü und Farben weg. Die Liste gehört nicht
          in eine E-Mail und nicht in eine Chatgruppe – Papier im Training
          ist hier der sicherere Weg.
        </p>
      </div>

      <div class="zl-werkzeuge">
        <button type="button" class="btn btn-primary" onclick="window.print()">Drucken</button>
        <a class="btn" href="?csv=1">Als CSV speichern</a>
        <form method="post" action="" style="margin:0"
              onsubmit="return confirm('Liste endgültig schließen? Die Startpasswörter sind danach nicht mehr abrufbar.');">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="aktion" value="schliessen">
          <button type="submit" class="btn">Liste schließen</button>
        </form>
      </div>

      <p>
        <strong><?= count($liste) ?></strong>
        <?= count($liste) === 1 ? 'Zugang' : 'Zugänge' ?>,
        angelegt am <?= h(date('d.m.Y \u\m H:i', $zeit ?: time())) ?> Uhr.
        Alle müssen beim ersten Anmelden ein eigenes Passwort festlegen.
      </p>

      <table class="zl-tabelle">
        <thead>
          <tr>
            <th scope="col">Name</th>
            <th scope="col">Benutzername</th>
            <th scope="col">Startpasswort</th>
            <th scope="col">Rolle</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($liste as $e): ?>
            <tr>
              <td><?= h($e['name']) ?></td>
              <td><?= h($e['benutzername']) ?></td>
              <td class="zl-passwort"><?= h($e['passwort']) ?></td>
              <td><?= $e['rolle'] === 'trainer' ? 'Trainer' : 'Mitglied' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2>Zum Ausschneiden</h2>
      <p>
        Ein Zettel je Person – so bekommt niemand die Zugänge der anderen
        zu sehen.
      </p>
      <div class="zl-zettel">
        <?php foreach ($liste as $e): ?>
          <div class="zettel">
            <strong><?= h($e['name']) ?></strong>
            Benutzername: <?= h($e['benutzername']) ?>
            <span class="zl-passwort"><?= h($e['passwort']) ?></span>
            <small>
              Anmelden auf der Website unter „Mitglieder". Beim ersten Mal
              wirst du nach einem eigenen Passwort gefragt.
            </small>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php fuss(); ?>
