#!/usr/bin/env bash
#
# Bereitet Handyaufnahmen für den Mitgliederbereich auf.
#
# Drei Aufrufe:
#
#   werkzeuge/video-aufbereiten.sh ZIEL KUERZEL DATEI [DATEI ...]
#       Hängt die Dateien in dieser Reihenfolge hinten an die Reihe an.
#
#   werkzeuge/video-aufbereiten.sh --liste LISTE ZIEL KUERZEL
#       Baut die ganze Reihe neu auf. LISTE ist eine Textdatei mit einer
#       Quelldatei je Zeile: Zeile 1 wird Nr. 01, Zeile 2 wird Nr. 02 und
#       so weiter. Leere Zeilen und Zeilen mit # werden übersprungen,
#       relative Pfade gelten ab dem Ordner der Liste. Das ist der Weg,
#       wenn die Reihenfolge nicht an den Dateinamen hängt.
#
#   werkzeuge/video-aufbereiten.sh --platz NR ZIEL KUERZEL DATEI
#       Ersetzt genau eine Nummer der Reihe – für den Fall, dass ein
#       einzelnes Video falsch war. Alle anderen bleiben unberührt.
#
# Beispiele:
#
#   werkzeuge/video-aufbereiten.sh videos-privat hanbon-kyorugi \
#       ~/IMG_1255.mov ~/IMG_1256.mov
#
#   werkzeuge/video-aufbereiten.sh --liste videos-roh/reihenfolge.txt \
#       videos-privat hanbon-kyorugi
#
#   werkzeuge/video-aufbereiten.sh --platz 4 videos-privat hanbon-kyorugi \
#       ~/IMG_1299.mov
#
# Daraus wird hanbon-kyorugi-01.mp4/.webm/.jpg, -02, -03 …
#
# Warum überhaupt konvertieren: iPhones nehmen HEVC in 10 Bit auf. Das
# spielen viele Browser nicht, und die HLG-Farbmetadaten lassen das Bild
# blass aussehen. Hier entsteht daraus H.264 in 8 Bit mit richtigen
# Farben, dazu eine WebM-Fassung für Browser ohne H.264.
#
# Nach jedem Lauf: php werkzeuge/videodaten-erzeugen.php
#
# Voraussetzung: ffmpeg. Ohne Installation:
#   pip install imageio-ffmpeg
set -euo pipefail

# Ordner des Projekts, damit das Skript von ueberall aufgerufen werden
# kann und werkzeuge/vorschaubild.py trotzdem findet.
WURZEL="$(cd "$(dirname "$0")/.." && pwd)"

hilfe() {
  sed -n '2,44p' "$0" | sed 's/^# \{0,1\}//'
  exit 1
}

MODUS="anhaengen"
LISTE=""
PLATZ=0

case "${1:-}" in
  --liste)
    [ "$#" -eq 4 ] || hilfe
    MODUS="liste"; LISTE="$2"; shift 2
    ;;
  --platz)
    [ "$#" -eq 5 ] || hilfe
    MODUS="platz"; PLATZ="$2"; shift 2
    case "$PLATZ" in
      ''|*[!0-9]*) echo "FEHLER: --platz braucht eine Nummer, nicht '$PLATZ'." >&2; exit 1 ;;
    esac
    [ "$PLATZ" -ge 1 ] || { echo "FEHLER: --platz zählt ab 1." >&2; exit 1; }
    ;;
  -h|--hilfe|--help|'')
    hilfe
    ;;
esac

[ "$#" -ge 2 ] || hilfe
ZIEL="$1"; KUERZEL="$2"; shift 2

FF="${FFMPEG:-}"
if [ -z "$FF" ]; then
  FF="$(command -v ffmpeg || true)"
fi
if [ -z "$FF" ]; then
  FF="$(python3 -c 'import imageio_ffmpeg; print(imageio_ffmpeg.get_ffmpeg_exe())' 2>/dev/null || true)"
fi
if [ -z "$FF" ] || [ ! -x "$FF" ]; then
  echo "FEHLER: ffmpeg nicht gefunden. 'pip install imageio-ffmpeg' hilft." >&2
  exit 1
fi

mkdir -p "$ZIEL"

# Aus HLG/HDR in 10 Bit wird bt709 in 8 Bit. Ohne diesen Schritt wirkt
# das Bild im Browser blass und flau.
TONWERT="zscale=t=linear:npl=100,format=gbrpf32le,zscale=p=bt709,\
tonemap=tonemap=hable:desat=0,zscale=t=bt709:m=bt709:r=tv,format=yuv420p"

# Auf 720p in der Höhe begrenzen; -2 hält die Breite gerade.
SKALA="scale=-2:720:flags=lanczos"

# Die Tonwert-Kette oben gilt nur für HDR-Material. Auf eine normale
# SDR-Aufnahme angewandt bricht ffmpeg mit "no path between colorspaces"
# ab – die Farbräume fehlen dann in der Datei. Deshalb erst nachsehen,
# was hereinkommt: iPhone-Aufnahmen sind bt2020 mit arib-std-b67 (HLG),
# andere Kameras liefern smpte2084 (PQ), eine SDR-Datei nichts davon.
filterkette() {
  local quelle="$1" info
  info="$({ "$FF" -hide_banner -i "$quelle" 2>&1 || true; } | grep -i 'Stream.*Video' || true)"
  case "$info" in
    *arib-std-b67*|*smpte2084*|*bt2020*)
      printf '%s,%s' "$TONWERT" "$SKALA" ;;
    *)
      printf '%s,format=yuv420p' "$SKALA" ;;
  esac
}

# Ein Video in die drei Zieldateien umwandeln: NR QUELLE
aufbereiten() {
  local nr="$1" quelle="$2"
  local name dauer sek filter
  name="$(printf '%s-%02d' "$KUERZEL" "$nr")"

  if [ ! -f "$quelle" ]; then
    echo "FEHLER: $quelle gibt es nicht." >&2
    exit 1
  fi

  filter="$(filterkette "$quelle")"
  case "$filter" in
    zscale*) echo "→ $(basename "$quelle")  wird  $name  (HDR wird umgerechnet)" ;;
    *)       echo "→ $(basename "$quelle")  wird  $name" ;;
  esac

  "$FF" -hide_banner -loglevel error -y -i "$quelle" \
    -map 0:v:0 -map 0:a:0? \
    -vf "$filter" \
    -c:v libx264 -preset medium -crf 24 -profile:v high -level 4.0 -r 30 \
    -c:a aac -b:a 96k -ac 2 -movflags +faststart \
    "$ZIEL/$name.mp4"

  "$FF" -hide_banner -loglevel error -y -i "$quelle" \
    -map 0:v:0 -map 0:a:0? \
    -vf "$filter" \
    -c:v libvpx-vp9 -crf 34 -b:v 0 -row-mt 1 -deadline good -cpu-used 3 -r 30 \
    -c:a libopus -b:a 96k -ac 2 \
    "$ZIEL/$name.webm"

  # Vorschaubild aus dem ersten Drittel – da steht die Technik meist
  # schon, der Anfang zeigt oft nur das Zugehen.
  #
  # Bevorzugt über werkzeuge/vorschaubild.py: Das schneidet um die
  # Personen herum zu, statt die Halle in ganzer Breite zu zeigen. Ein
  # Einzelbild über die ganze Breite lässt die beiden klein und je
  # nach Aufnahme irgendwo im Bild stehen; als Reihe von dreizehn
  # Kacheln wirkt das unruhig. Fehlen dafür numpy oder opencv, gibt es
  # das ganze Bild – besser als keins.
  if python3 -c 'import cv2, numpy' 2> /dev/null \
     && [ -f "$WURZEL/werkzeuge/vorschaubild.py" ]; then
    FFMPEG="$FF" python3 "$WURZEL/werkzeuge/vorschaubild.py" "$quelle" \
      --ziel "$ZIEL/$name.jpg" 2>&1 | sed 's/^/  /'
  else
    echo "   Hinweis: numpy/opencv fehlen – Vorschaubild ohne Zuschnitt."
    # ffmpeg endet ohne Ausgabedatei mit Rückgabewert 1 – mit "set -o
    # pipefail" würde das Skript hier abbrechen, obwohl die Zeile nur die
    # Laufzeit auslesen soll. Deshalb das || true.
    dauer="$({ "$FF" -nostdin -hide_banner -i "$quelle" 2>&1 || true; } \
             | sed -n 's/.*Duration: \([0-9:.]*\).*/\1/p' | head -1)"
    sek="$(python3 -c "
t='$dauer'.split(':')
print(max(0.5, (int(t[0])*3600 + int(t[1])*60 + float(t[2])) / 3))" )"
    "$FF" -nostdin -hide_banner -loglevel error -y -ss "$sek" -i "$quelle" \
      -frames:v 1 -vf "$filter" -q:v 4 "$ZIEL/$name.jpg"
  fi

  printf '   %-22s %6s MP4  %6s WebM  %5s Bild\n' "$name" \
    "$(du -h "$ZIEL/$name.mp4" | cut -f1)" \
    "$(du -h "$ZIEL/$name.webm" | cut -f1)" \
    "$(du -h "$ZIEL/$name.jpg" | cut -f1)"

  herkunft_vermerken "$name" "$(basename "$quelle")"
}

# Festhalten, aus welcher Rohaufnahme eine Nummer entstanden ist.
# videodaten-erzeugen.php liest das: Steckt hinter einer Nummer plötzlich
# eine andere Aufnahme, darf der von Hand eingetragene Technikname nicht
# stehen bleiben – sonst trägt Nr. 4 den Namen des alten Videos.
herkunft_vermerken() {
  local name="$1" quelle="$2" datei="$ZIEL/$KUERZEL-herkunft.txt"
  touch "$datei"
  grep -v "^$name	" "$datei" > "$datei.neu" 2>/dev/null || true
  printf '%s\t%s\n' "$name" "$quelle" >> "$datei.neu"
  sort "$datei.neu" > "$datei"
  rm -f "$datei.neu"
}

# Höchste schon vorhandene Nummer der Reihe.
letzte_nummer() {
  local nr=0
  while [ -f "$(printf '%s/%s-%02d.mp4' "$ZIEL" "$KUERZEL" $((nr + 1)))" ]; do
    nr=$((nr + 1))
  done
  echo "$nr"
}

case "$MODUS" in

  platz)
    [ "$#" -eq 1 ] || hilfe
    VORHANDEN="$(letzte_nummer)"
    if [ "$PLATZ" -gt "$VORHANDEN" ]; then
      echo "Hinweis: Nr. $PLATZ gab es noch nicht – die Reihe hatte $VORHANDEN Videos."
      if [ "$PLATZ" -gt $((VORHANDEN + 1)) ]; then
        echo "FEHLER: Das würde eine Lücke lassen. Erst Nr. $((VORHANDEN + 1)) belegen." >&2
        exit 1
      fi
    else
      echo "Nr. $PLATZ wird ersetzt."
    fi
    aufbereiten "$PLATZ" "$1"
    ANZAHL="$(letzte_nummer)"
    ;;

  liste)
    [ "$#" -eq 0 ] || hilfe
    if [ ! -f "$LISTE" ]; then
      echo "FEHLER: Die Liste $LISTE gibt es nicht." >&2
      exit 1
    fi
    ORDNER="$(cd "$(dirname "$LISTE")" && pwd)"

    # Erst die ganze Liste einlesen und prüfen, dann konvertieren: ein
    # Tippfehler in Zeile 12 soll nicht erst nach elf Videos auffallen.
    QUELLEN=()
    while IFS= read -r ZEILE || [ -n "$ZEILE" ]; do
      ZEILE="${ZEILE%%#*}"
      ZEILE="$(printf '%s' "$ZEILE" | sed 's/^[[:space:]]*//; s/[[:space:]]*$//')"
      [ -n "$ZEILE" ] || continue
      case "$ZEILE" in
        /*) PFAD="$ZEILE" ;;
        *)  PFAD="$ORDNER/$ZEILE" ;;
      esac
      if [ ! -f "$PFAD" ]; then
        echo "FEHLER: In $LISTE steht '$ZEILE' – diese Datei gibt es nicht." >&2
        exit 1
      fi
      QUELLEN+=("$PFAD")
    done < "$LISTE"

    ANZAHL="${#QUELLEN[@]}"
    if [ "$ANZAHL" -eq 0 ]; then
      echo "FEHLER: In $LISTE steht keine einzige Datei." >&2
      exit 1
    fi

    echo "$ANZAHL Videos aus $LISTE, in der Reihenfolge der Zeilen:"
    echo
    NR=0
    for QUELLE in "${QUELLEN[@]}"; do
      NR=$((NR + 1))
      aufbereiten "$NR" "$QUELLE"
    done

    # Was von einem früheren, längeren Lauf übrig ist, wegräumen –
    # sonst hängen Videos in der Reihe, die keiner mehr wollte.
    NR=$((ANZAHL + 1))
    while [ -f "$(printf '%s/%s-%02d.mp4' "$ZIEL" "$KUERZEL" "$NR")" ]; do
      NAME="$(printf '%s-%02d' "$KUERZEL" "$NR")"
      echo "   $NAME war übrig und wird entfernt."
      rm -f "$ZIEL/$NAME.mp4" "$ZIEL/$NAME.webm" "$ZIEL/$NAME.jpg"
      if [ -f "$ZIEL/$KUERZEL-herkunft.txt" ]; then
        grep -v "^$NAME	" "$ZIEL/$KUERZEL-herkunft.txt" > "$ZIEL/$KUERZEL-herkunft.neu" || true
        mv "$ZIEL/$KUERZEL-herkunft.neu" "$ZIEL/$KUERZEL-herkunft.txt"
      fi
      NR=$((NR + 1))
    done
    ;;

  anhaengen)
    [ "$#" -ge 1 ] || hilfe
    NR="$(letzte_nummer)"
    if [ "$NR" -gt 0 ]; then
      echo "$NR Videos liegen schon vor – die neuen werden angehängt."
    fi
    for QUELLE in "$@"; do
      NR=$((NR + 1))
      aufbereiten "$NR" "$QUELLE"
    done
    ANZAHL="$NR"
    ;;
esac

echo
echo "Fertig. $ANZAHL Videos liegen in $ZIEL/"
echo "Jetzt noch: php werkzeuge/videodaten-erzeugen.php"
