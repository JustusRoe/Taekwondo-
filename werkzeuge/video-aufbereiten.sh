#!/usr/bin/env bash
#
# Bereitet Handyaufnahmen für den Mitgliederbereich auf.
#
#   werkzeuge/video-aufbereiten.sh ZIELORDNER KUERZEL DATEI [DATEI ...]
#
# Beispiel – die ersten vier Einschrittkampf-Videos:
#
#   werkzeuge/video-aufbereiten.sh videos-privat hanbon-kyorugi \
#       ~/IMG_1255.mov ~/IMG_1256.mov ~/IMG_1257.mov ~/IMG_1258.mov
#
# Daraus wird hanbon-kyorugi-01.mp4/.webm/.jpg, -02, -03, -04 …
# Die Nummer folgt der Reihenfolge der Dateien auf der Kommandozeile;
# bei IMG_1255, IMG_1256 … stimmt das mit der Aufnahmereihenfolge.
#
# Warum überhaupt konvertieren: iPhones nehmen HEVC in 10 Bit auf. Das
# spielen viele Browser nicht, und die HLG-Farbmetadaten lassen das Bild
# blass aussehen. Hier entsteht daraus H.264 in 8 Bit mit richtigen
# Farben, dazu eine WebM-Fassung für Browser ohne H.264.
#
# Voraussetzung: ffmpeg. Ohne Installation:
#   pip install imageio-ffmpeg
set -euo pipefail

if [ "$#" -lt 3 ]; then
  sed -n '2,28p' "$0" | sed 's/^# \{0,1\}//'
  exit 1
fi

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

# Vorhandene Nummern überspringen, damit ein zweiter Lauf anhängt
# statt zu überschreiben.
NR=0
while [ -f "$(printf '%s/%s-%02d.mp4' "$ZIEL" "$KUERZEL" $((NR + 1)))" ]; do
  NR=$((NR + 1))
done
if [ "$NR" -gt 0 ]; then
  echo "$NR Videos liegen schon vor – die neuen werden angehängt."
fi

for QUELLE in "$@"; do
  NR=$((NR + 1))
  NAME="$(printf '%s-%02d' "$KUERZEL" "$NR")"
  echo "→ $(basename "$QUELLE")  wird  $NAME"

  # Auf 720p in der Höhe begrenzen; -2 hält die Breite gerade.
  SKALA="scale=-2:720:flags=lanczos"

  "$FF" -hide_banner -loglevel error -y -i "$QUELLE" \
    -map 0:v:0 -map 0:a:0? \
    -vf "$TONWERT,$SKALA" \
    -c:v libx264 -preset medium -crf 24 -profile:v high -level 4.0 -r 30 \
    -c:a aac -b:a 96k -ac 2 -movflags +faststart \
    "$ZIEL/$NAME.mp4"

  "$FF" -hide_banner -loglevel error -y -i "$QUELLE" \
    -map 0:v:0 -map 0:a:0? \
    -vf "$TONWERT,$SKALA" \
    -c:v libvpx-vp9 -crf 34 -b:v 0 -row-mt 1 -deadline good -cpu-used 3 -r 30 \
    -c:a libopus -b:a 96k -ac 2 \
    "$ZIEL/$NAME.webm"

  # Vorschaubild aus dem ersten Drittel – da steht die Technik meist
  # schon, der Anfang zeigt oft nur das Zugehen.
  # ffmpeg endet ohne Ausgabedatei mit Rueckgabewert 1 – mit "set -o
  # pipefail" wuerde das Skript hier abbrechen, obwohl die Zeile nur die
  # Laufzeit auslesen soll. Deshalb das || true.
  DAUER="$({ "$FF" -hide_banner -i "$QUELLE" 2>&1 || true; } \
           | sed -n 's/.*Duration: \([0-9:.]*\).*/\1/p' | head -1)"
  SEK="$(python3 -c "
t='$DAUER'.split(':')
print(max(0.5, (int(t[0])*3600 + int(t[1])*60 + float(t[2])) / 3))" )"
  "$FF" -hide_banner -loglevel error -y -ss "$SEK" -i "$QUELLE" -frames:v 1 \
    -vf "$TONWERT,$SKALA" -q:v 4 "$ZIEL/$NAME.jpg"

  printf '   %-22s %6s MP4  %6s WebM  %5s Bild\n' "$NAME" \
    "$(du -h "$ZIEL/$NAME.mp4" | cut -f1)" \
    "$(du -h "$ZIEL/$NAME.webm" | cut -f1)" \
    "$(du -h "$ZIEL/$NAME.jpg" | cut -f1)"
done

echo
echo "Fertig. $NR Videos liegen in $ZIEL/"
