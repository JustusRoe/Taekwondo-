#!/usr/bin/env bash
#
# testmain.sh – startet die komplette Testumgebung mit einem Befehl.
#
#   ./test/testmain.sh              Einrichten, starten, prüfen, offen lassen
#   ./test/testmain.sh --neu        Testdatenbank vorher zurücksetzen
#   ./test/testmain.sh --port 9000  anderer Port
#   ./test/testmain.sh --nur-pruefen  nur die Prüfungen gegen einen laufenden Server
#   ./test/testmain.sh --stop       einen hängengebliebenen Testserver beenden
#
# Beenden mit Strg+C – der Server wird dabei sauber gestoppt.

set -u

PORT=8080
NEU=""
NUR_PRUEFEN=0
STOPPEN=0

while [ $# -gt 0 ]; do
  case "$1" in
    --neu)          NEU="--neu" ;;
    --port)         PORT="${2:-8080}"; shift ;;
    --nur-pruefen)  NUR_PRUEFEN=1 ;;
    --stop)         STOPPEN=1 ;;
    -h|--hilfe|--help)
      sed -n '3,13p' "$0" | sed 's/^# \{0,1\}//'
      exit 0 ;;
    *) echo "Unbekannte Option: $1"; exit 1 ;;
  esac
  shift
done

cd "$(dirname "$0")/.." || exit 1
WURZEL="$(pwd)"
BASIS="http://localhost:${PORT}"
PID_DATEI="$WURZEL/test/daten/server.pid"

rot()  { printf '\033[31m%s\033[0m\n' "$1"; }
gruen(){ printf '\033[32m%s\033[0m\n' "$1"; }
grau() { printf '\033[90m%s\033[0m\n' "$1"; }

echo
echo "Taekwondo Club Musterstadt – Testumgebung"
echo "========================================================"

# Einen früher gestarteten Testserver beenden
if [ "$STOPPEN" -eq 1 ]; then
  if [ -f "$PID_DATEI" ] && kill -0 "$(cat "$PID_DATEI")" 2>/dev/null; then
    ALT="$(cat "$PID_DATEI")"
    kill "$ALT" 2>/dev/null
    rm -f "$PID_DATEI"
    gruen "Testserver (PID $ALT) beendet."
  else
    grau "Es läuft kein Testserver, der über dieses Skript gestartet wurde."
    rm -f "$PID_DATEI"
  fi
  exit 0
fi

# ---------------------------------------------------------------
# 1. Voraussetzungen
# ---------------------------------------------------------------
if ! command -v php >/dev/null 2>&1; then
  rot "PHP wurde nicht gefunden."
  echo "  macOS:  brew install php"
  echo "  Ubuntu: sudo apt install php-cli php-sqlite3 php-curl"
  echo "  Windows: https://windows.php.net/download/ (danach PHP zum PATH hinzufügen)"
  exit 1
fi

PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
if ! php -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);'; then
  rot "PHP $PHP_VERSION ist zu alt – benötigt wird mindestens PHP 8.0."
  exit 1
fi

FEHLENDE=""
for MODUL in pdo_sqlite session curl; do
  php -m | grep -qi "^${MODUL}$" || FEHLENDE="$FEHLENDE $MODUL"
done
if [ -n "$FEHLENDE" ]; then
  rot "Es fehlen PHP-Erweiterungen:$FEHLENDE"
  echo "  Ubuntu: sudo apt install php-sqlite3 php-curl"
  exit 1
fi
grau "PHP $PHP_VERSION mit allen benötigten Erweiterungen gefunden."

if [ ! -d "$WURZEL/assets/video" ] || [ -z "$(ls -A "$WURZEL/assets/video" 2>/dev/null)" ]; then
  rot "Der Ordner assets/video ist leer – die Platzhaltervideos fehlen."
  exit 1
fi

# ---------------------------------------------------------------
# 2. Nur prüfen?
# ---------------------------------------------------------------
if [ "$NUR_PRUEFEN" -eq 1 ]; then
  php "$WURZEL/test/check.php" "$BASIS"
  exit $?
fi

# ---------------------------------------------------------------
# 3. Belegten Port erkennen
# ---------------------------------------------------------------
if php -r '
    $p = (int) $argv[1];
    $s = @fsockopen("127.0.0.1", $p, $e, $m, 0.7);
    if ($s) { fclose($s); exit(0); }
    exit(1);
' "$PORT"; then
  echo
  rot "Auf Port $PORT läuft bereits etwas."
  if [ -f "$PID_DATEI" ] && kill -0 "$(cat "$PID_DATEI")" 2>/dev/null; then
    echo "  Vermutlich ein früherer Testserver (PID $(cat "$PID_DATEI"))."
    echo "  Beenden mit:          ./test/testmain.sh --stop"
  fi
  echo "  Anderen Port wählen:  ./test/testmain.sh --port 8090"
  exit 1
fi

# ---------------------------------------------------------------
# 4. Testdaten einrichten
# ---------------------------------------------------------------
echo
if ! php "$WURZEL/test/setup.php" $NEU; then
  rot "Die Einrichtung ist fehlgeschlagen."
  exit 1
fi

# ---------------------------------------------------------------
# 5. Server starten
# ---------------------------------------------------------------
echo
echo "Server wird gestartet …"
PROTOKOLL="$WURZEL/test/daten/server.log"
php -S "localhost:${PORT}" -t "$WURZEL" "$WURZEL/test/router.php" \
    > "$PROTOKOLL" 2>&1 &
SERVER_PID=$!
echo "$SERVER_PID" > "$PID_DATEI"

aufraeumen() {
  echo
  grau "Server wird beendet (PID $SERVER_PID) …"
  kill "$SERVER_PID" 2>/dev/null
  wait "$SERVER_PID" 2>/dev/null
  rm -f "$PID_DATEI"
  echo "Beendet."
}
trap aufraeumen EXIT INT TERM

# Auf Bereitschaft warten
BEREIT=0
for _ in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
  if php -r '
      $s = @fsockopen("127.0.0.1", (int) $argv[1], $e, $m, 0.5);
      if ($s) { fclose($s); exit(0); } exit(1);
  ' "$PORT"; then
    BEREIT=1
    break
  fi
  sleep 0.4
done

if [ "$BEREIT" -ne 1 ]; then
  rot "Der Server ist nicht gestartet. Protokoll:"
  tail -n 20 "$PROTOKOLL"
  exit 1
fi
gruen "Server läuft auf ${BASIS} (PID $SERVER_PID)"

# ---------------------------------------------------------------
# 6. Prüfen
# ---------------------------------------------------------------
echo
php "$WURZEL/test/check.php" "$BASIS"
ERGEBNIS=$?

echo
echo "========================================================"
if [ "$ERGEBNIS" -eq 0 ]; then
  gruen "Alle Dienste laufen."
else
  rot "Mindestens eine Prüfung ist fehlgeschlagen (siehe oben)."
fi

cat <<INFO

Im Browser öffnen
  Website               ${BASIS}/index.html
  Mitglieder (Entwurf)  ${BASIS}/mitglieder.html
  Mitglieder (Server)   ${BASIS}/backend/login.php

Testzugänge (Passwort jeweils: test1234)
  testuser       Mitglied – sieht die Videothek
  testtrainer    Trainer  – sieht zusätzlich die Verwaltung

Der Entwurf akzeptiert dieselben Zugänge, prüft sie aber nur im Browser.

Protokoll   test/daten/server.log
Beenden     Strg+C

INFO

# ---------------------------------------------------------------
# 7. Offen halten
# ---------------------------------------------------------------
wait "$SERVER_PID"
