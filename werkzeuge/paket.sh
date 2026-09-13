#!/usr/bin/env bash
#
# Baut den Ordner website/: nur die Dateien, die auf den Server gehören,
# sortiert nach dem Ort, an den sie kommen.
#
#   werkzeuge/paket.sh [ZIELORDNER]
#
# Standardziel ist website/. Der Ordner wird bei jedem Lauf neu angelegt,
# damit nichts von einem früheren Stand übrig bleibt.
#
#   website/
#   ├── LIESMICH.md          was wohin kommt
#   ├── www/                 → in den Webordner des Hostings
#   ├── videos-privat/       → eine Ebene ÜBER dem Webordner
#   └── datenbank/           → in phpMyAdmin importieren
#
# Was bewusst fehlt: test/, werkzeuge/, videos-roh/, die Rohaufnahmen,
# die Entwurfsfassung des Mitgliederbereichs, alle .md-Dateien des
# Projekts und backend/config.php. Die Zugangsdaten des Servers werden
# direkt dort angelegt und gehören in kein Paket.
set -euo pipefail

cd "$(dirname "$0")/.."
WURZEL="$(pwd)"
ZIEL="${1:-$WURZEL/website}"

rot()   { printf '\033[31m%s\033[0m\n' "$1"; }
gruen() { printf '\033[32m%s\033[0m\n' "$1"; }

# Die Prüfsumme über die Quelldateien steht in einem eigenen Skript,
# weil test/check.php sie auch braucht – siehe dort.
quellen_pruefsumme() { bash "$WURZEL/werkzeuge/quellen-pruefsumme.sh"; }

echo
echo "Ordner website/ für die Taekwondo-Website bauen"
echo "========================================================"

# ---------------------------------------------------------
# 1. Vorher prüfen: Steht die Seite auf LIVE?
# ---------------------------------------------------------
# Ein Paket aus der Entwurfsfassung trägt noindex und verlinkt die
# nachgebaute Anmeldung. Beides faellt erst auf, wenn die Seite schon
# online ist und Google sie nicht findet.
if command -v php > /dev/null; then
  if ! php werkzeuge/livegang.php --status | grep -q 'steht auf LIVE'; then
    rot "Die Quelldateien stehen auf ENTWURF."
    echo
    php werkzeuge/livegang.php --status
    echo "Erst umstellen, dann das Paket bauen:"
    echo "  php werkzeuge/livegang.php --live"
    exit 1
  fi
  gruen "Quelldateien stehen auf LIVE."
else
  echo "Hinweis: PHP nicht gefunden – der Live-Zustand wurde nicht geprüft."
fi

# ---------------------------------------------------------
# 2. Webordner zusammenstellen
# ---------------------------------------------------------
rm -rf "$ZIEL"
mkdir -p "$ZIEL/www" "$ZIEL/videos-privat" "$ZIEL/datenbank"

# Die öffentlichen Seiten – ohne die Entwurfsfassung des
# Mitgliederbereichs, die nichts schützt und Testzugänge anzeigt.
for DATEI in "$WURZEL"/*.html; do
  NAME="$(basename "$DATEI")"
  case "$NAME" in
    mitglieder.html|mitglieder-videothek.html|mitglieder-video.html) continue ;;
  esac
  cp "$DATEI" "$ZIEL/www/"
done

cp "$WURZEL/.htaccess" "$ZIEL/www/"
cp "$WURZEL/robots.txt" "$ZIEL/www/"

# Gestaltung, Schriftlogik und Bilder
mkdir -p "$ZIEL/www/assets/css" "$ZIEL/www/assets/js" "$ZIEL/www/assets/img" \
         "$ZIEL/www/assets/video"
cp "$WURZEL"/assets/css/*.css "$ZIEL/www/assets/css/"
cp "$WURZEL"/assets/img/* "$ZIEL/www/assets/img/"

# Nur die beiden Skripte der öffentlichen Seiten. mitglieder.js und
# videodaten.js gehören zur Entwurfsfassung.
cp "$WURZEL/assets/js/main.js" "$ZIEL/www/assets/js/"
cp "$WURZEL/assets/js/trainingstermine.js" "$ZIEL/www/assets/js/"

# Aus assets/video kommen nur die Vorschaubilder der echten Reihe mit.
# Die Videos selbst gehören nach videos-privat/, ausserhalb des
# Webordners; die Platzhalterclips aus der Entwicklung bleiben ganz weg.
cp "$WURZEL"/assets/video/hanbon-kyorugi-[0-9][0-9].jpg "$ZIEL/www/assets/video/"

# Formulare des Vereins
if [ -d "$WURZEL/downloads" ]; then
  mkdir -p "$ZIEL/www/downloads"
  cp "$WURZEL"/downloads/* "$ZIEL/www/downloads/" 2> /dev/null || true
fi

# ---------------------------------------------------------
# 3. Mitgliederbereich (PHP)
# ---------------------------------------------------------
mkdir -p "$ZIEL/www/backend/lib"
for DATEI in "$WURZEL"/backend/*.php; do
  NAME="$(basename "$DATEI")"
  # config.php zeigt auf die lokale Testdatenbank. Die Fassung fuer den
  # Server wird dort angelegt, aus config.example.php.
  [ "$NAME" = "config.php" ] && continue
  cp "$DATEI" "$ZIEL/www/backend/"
done
cp "$WURZEL"/backend/lib/*.php "$ZIEL/www/backend/lib/"

# Die Datenbankstruktur wird importiert, nicht ausgeliefert.
cp "$WURZEL/backend/schema.sql" "$ZIEL/datenbank/"

# ---------------------------------------------------------
# 4. Videos – ausserhalb des Webordners
# ---------------------------------------------------------
# Nur die echte Reihe. Die Platzhalterclips (kibon-poomsae, taegeuk-*,
# hanbon-kyorugi-1 und -2 mit einer Ziffer) sind Entwicklungsmaterial.
ANZAHL_VIDEOS=0
for DATEI in "$WURZEL"/assets/video/hanbon-kyorugi-[0-9][0-9].mp4 \
             "$WURZEL"/assets/video/hanbon-kyorugi-[0-9][0-9].webm; do
  [ -f "$DATEI" ] || continue
  cp "$DATEI" "$ZIEL/videos-privat/"
  ANZAHL_VIDEOS=$((ANZAHL_VIDEOS + 1))
done

# Beipackzettel für die Videos: Titel, Bereich, Laufzeit und Platz in der
# Reihe, gelesen aus assets/js/videodaten.js. Die Verwaltung liest diese
# Datei beim Eintragen und muss die Angaben nicht erraten – ohne sie
# stünde auf dem Server nur der Dateiname.
if command -v php > /dev/null; then
  php -r '
    $t = (string) file_get_contents("assets/js/videodaten.js");
    preg_match("~window.VIDEOTHEK = \[(.*)\];~s", $t, $m);
    $roh = preg_replace(["~/\*.*?\*/~s", "~,(\s*\])~"], ["", "$1"], "[" . ($m[1] ?? "") . "]");
    $roh = preg_replace("~,(\s*)$~", "", trim($roh));
    $liste = json_decode(preg_replace("~,(\s*)\]~", "]", $roh), true) ?: [];
    $aus = fopen($argv[1] . "/reihe.csv", "w");
    fputcsv($aus, ["slug", "titel", "bereich", "grad", "reihenfolge", "dauer"], ";");
    $n = 0;
    foreach ($liste as $e) {
      if (!str_starts_with((string) ($e["slug"] ?? ""), "hanbon-kyorugi-")) { continue; }
      if (!preg_match("~-\d\d$~", (string) $e["slug"])) { continue; }
      fputcsv($aus, [
        $e["slug"], $e["titel"] ?? "", $e["bereich"] ?? "",
        $e["grad"] ?? "", $e["reihenfolge"] ?? 0, $e["dauer"] ?? 0,
      ], ";");
      $n++;
    }
    fclose($aus);
    fwrite(STDERR, "  reihe.csv: $n Einträge\n");
  ' "$ZIEL/videos-privat"
fi

# ---------------------------------------------------------
# 5. Gegenprobe: Was nicht drin sein darf
# ---------------------------------------------------------
echo
echo "Gegenprobe"
FEHLER=0

pruefe_weg() {
  if [ -e "$ZIEL/www/$1" ]; then
    rot "  im Paket, gehört aber nicht hinein: www/$1"
    FEHLER=1
  else
    echo "  nicht im Paket: $1"
  fi
}
pruefe_weg "backend/config.php"
pruefe_weg "mitglieder.html"
pruefe_weg "assets/js/mitglieder.js"
pruefe_weg "assets/js/videodaten.js"

# Testzugaenge: Die Entwurfsfassung zeigt "testuser / test1234" offen an.
# Kommt eine dieser Zeichenketten im Paket vor, ist etwas mitgerutscht.
TREFFER="$(grep -rIl -e 'test1234' -e 'testtrainer' -e 'testuser' "$ZIEL" 2> /dev/null || true)"
if [ -n "$TREFFER" ]; then
  rot "  Testzugänge im Paket gefunden:"
  printf '    %s\n' $TREFFER
  FEHLER=1
else
  echo "  keine Testzugänge im Paket"
fi

# Rohaufnahmen
if find "$ZIEL" -iname '*.mov' | grep -q .; then
  rot "  Rohaufnahmen (*.mov) im Paket – die gehören nicht auf den Server."
  FEHLER=1
else
  echo "  keine Rohaufnahmen im Paket"
fi

if [ "$FEHLER" -ne 0 ]; then
  echo
  rot "Das Paket ist nicht in Ordnung. Bitte die Meldungen oben ansehen."
  exit 1
fi

# ---------------------------------------------------------
# 6. Beipackzettel
# ---------------------------------------------------------
SEITEN="$(find "$ZIEL/www" -maxdepth 1 -name '*.html' | wc -l | tr -d ' ')"

cat > "$ZIEL/LIESMICH.md" << 'BEIPACK'
# Der Ordner website/

Hier liegt genau das, was auf den Server gehört – keine Tests, keine
Hilfsskripte, keine Rohaufnahmen. Erzeugt von `werkzeuge/paket.sh`.

**Nichts hier von Hand ändern.** Änderungen gehören in den Projektordner
eine Ebene höher; danach `werkzeuge/paket.sh` laufen lassen, und dieser
Ordner entsteht neu. Ob er noch zu den Quelldateien passt, sagt
`php test/check.php` – die Prüfsumme dafür steht in `STAND.txt`.

## Wohin was kommt

| Ordner im Paket | Wohin auf dem Server |
| --- | --- |
| `www/` | in den Webordner des Hostings – bei IONOS heißt er meist `/` oder `htdocs`. **Der Inhalt**, nicht der Ordner selbst. |
| `videos-privat/` | eine Ebene **über** dem Webordner. Nicht in `www/`. |
| `datenbank/schema.sql` | nicht hochladen: in phpMyAdmin unter *Importieren* einspielen. |

So sieht es danach auf dem Server aus:

```
/
├── videos-privat/           ← hierher der Inhalt von videos-privat/
└── www/                     ← hierher der Inhalt von www/
    ├── index.html
    ├── assets/
    ├── downloads/
    └── backend/
```

Dass `videos-privat/` außerhalb liegt, ist der Kern des
Mitgliederbereichs: Von dort kann niemand eine Videodatei direkt
herunterladen. `backend/stream.php` liest sie und gibt sie erst nach
geprüfter Anmeldung heraus.

## Die vier Schritte nach dem Hochladen

1. **`backend/config.php` anlegen.** Im Paket liegt
   `www/backend/config.example.php` als Vorlage. Kopieren, in
   `config.php` umbenennen und die Zugangsdaten der Datenbank
   eintragen. Diese Datei gibt es nur auf dem Server.
2. **Datenbank einspielen.** `datenbank/schema.sql` in phpMyAdmin
   importieren. Danach stehen die Tabellen, aber noch kein Zugang.
3. **Erstes Trainerkonto anlegen.** Einmal
   `https://deine-domain.de/backend/einrichten.php` aufrufen. Die Seite
   legt das erste Konto an, mit einem Passwort, das du selbst wählst,
   und sperrt sich danach selbst. Es gibt kein vorgegebenes Passwort und
   keinen Testzugang.
4. **Schreibrechte prüfen.** Der Webserver muss schreiben dürfen in
   `videos-privat/`, `www/assets/video/`,
   `www/assets/js/trainingstermine.js` und `www/training.html`. Die
   letzten beiden sind Absicht: Die Terminverwaltung schreibt die
   Termine in die Website zurück, damit die Seite ohne Datenbank
   auskommt.

Ausführlich, mit allem rundherum: `LIVEGANG.md` im Projektordner.

## Weitere Zugänge

Nach dem Anmelden unter *Verwaltung → Zugänge*. Für viele auf einmal
gibt es dort „Mehrere Zugänge auf einmal anlegen": eine Liste von Namen
hinein, und heraus kommt eine **Zugangsliste** mit Benutzername und
Startpasswort – zum Ausdrucken, als CSV-Datei oder als Zettel je Person
zum Ausschneiden. Diese Liste gibt es genau einmal: Gespeichert wird nur
der verschlüsselte Abdruck, ein Startpasswort lässt sich später nicht
mehr auslesen.
BEIPACK

# ---------------------------------------------------------
# 7. Stand festhalten
# ---------------------------------------------------------
# website/ liegt im Repository, damit man es ohne Werkzeuge herunterladen
# kann. Damit entsteht eine Gefahr: Wird eine Seite geändert und das
# Paket nicht neu gebaut, lädt jemand eine alte Fassung auf den Server
# und merkt es nicht. Deshalb bekommt der Ordner eine Prüfsumme über die
# Quelldateien; test/check.php vergleicht sie und meldet, wenn das Paket
# nicht mehr passt.
{
  echo "# Stand des Ordners website/"
  echo "#"
  echo "# Erzeugt von werkzeuge/paket.sh. Die Prüfsumme geht über alle"
  echo "# Quelldateien, aus denen dieser Ordner entstanden ist."
  echo "# test/check.php vergleicht sie und meldet einen veralteten Stand."
  echo "#"
  echo "erzeugt_am=$(date '+%Y-%m-%d %H:%M')"
  if command -v git > /dev/null && [ -d "$WURZEL/.git" ]; then
    echo "commit=$(git -C "$WURZEL" rev-parse --short HEAD 2> /dev/null || echo unbekannt)"
  fi
  echo "pruefsumme=$(quellen_pruefsumme)"
} > "$ZIEL/STAND.txt"

# ---------------------------------------------------------
# 8. Übersicht
# ---------------------------------------------------------
echo
gruen "Paket fertig: $ZIEL"
echo
printf '  %-22s %s\n' "www/" "$SEITEN Seiten, $(du -sh "$ZIEL/www" | cut -f1)"
printf '  %-22s %s\n' "videos-privat/" "$ANZAHL_VIDEOS Videodateien, $(du -sh "$ZIEL/videos-privat" | cut -f1)"
printf '  %-22s %s\n' "datenbank/" "schema.sql"
printf '  %-22s %s\n' "gesamt" "$(du -sh "$ZIEL" | cut -f1)"
echo
echo "Wohin was kommt, steht in $ZIEL/LIESMICH.md"
echo "Der Stand ist in $ZIEL/STAND.txt festgehalten; test/check.php"
echo "meldet, wenn der Ordner nicht mehr zu den Quelldateien passt."
