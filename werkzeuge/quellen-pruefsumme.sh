#!/usr/bin/env bash
#
# Prüfsumme über alle Dateien, aus denen der Ordner website/ entsteht.
#
#   werkzeuge/quellen-pruefsumme.sh
#
# Zwei Stellen brauchen diese Zahl, und sie müssen dieselbe bekommen:
#
#   werkzeuge/paket.sh   schreibt sie beim Bauen nach website/STAND.txt
#   test/check.php       vergleicht sie und meldet einen veralteten Stand
#
# Deshalb steht die Dateiliste hier und nur hier. Läge sie zweimal
# herum, würde eine Änderung irgendwann nur an einer Stelle nachgezogen
# und die Prüfung schlüge grundlos an – oder, schlimmer, gar nicht mehr.
#
# Warum das nötig ist: website/ liegt im Repository, damit man es ohne
# Werkzeuge herunterladen kann. Wird eine Seite geändert und der Ordner
# nicht neu gebaut, lädt sonst jemand eine alte Fassung auf den Server
# und merkt es nicht.
set -euo pipefail

cd "$(dirname "$0")/.."
WURZEL="$(pwd)"

{
  # Die öffentlichen Seiten – ohne die Entwurfsfassung.
  find "$WURZEL" -maxdepth 1 -name '*.html' ! -name 'mitglieder*.html' -print0
  printf '%s\0' "$WURZEL/.htaccess" "$WURZEL/robots.txt"
  # Gestaltung, Bilder, Formulare, Mitgliederbereich – ohne config.php,
  # die auf dem Server angelegt wird und nie mitkommt.
  find "$WURZEL/assets/css" "$WURZEL/assets/img" "$WURZEL/downloads" \
       "$WURZEL/backend" -type f ! -name 'config.php' -print0 2> /dev/null
  # Nur die Skripte der öffentlichen Seiten.
  printf '%s\0' "$WURZEL/assets/js/main.js" "$WURZEL/assets/js/trainingstermine.js"
  # Die Videoreihe samt Vorschaubildern.
  find "$WURZEL/assets/video" -name 'hanbon-kyorugi-[0-9][0-9].*' -print0
} | tr '\0' '\n' | sed "s|^$WURZEL/||" | LC_ALL=C sort -u | while read -r datei; do
  [ -f "$WURZEL/$datei" ] || continue
  printf '%s %s\n' "$datei" "$(cksum < "$WURZEL/$datei" | cut -d' ' -f1)"
done | cksum | cut -d' ' -f1
