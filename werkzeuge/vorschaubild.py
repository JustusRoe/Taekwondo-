#!/usr/bin/env python3
"""
Vorschaubild einer Trainingsaufnahme zuschneiden.

    python3 werkzeuge/vorschaubild.py assets/video/IMG_1255.mov \
        --ziel assets/video/hanbon-kyorugi-01.jpg

Genommen wird das **erste Bild** der Aufnahme: Da stehen sich beide
gegenüber, bevor die Technik beginnt. Über eine ganze Reihe hinweg sieht
das gleich aus, und genau das ist der Zweck – die Kacheln sollen eine
Reihe bilden und nicht dreizehn verschiedene Momente zeigen.

Das Problem ist der Ausschnitt. Ein Einzelbild zeigt die Halle in ganzer
Breite, und die beiden stehen je nach Aufnahme mittig, links oder rechts
darin – klein, mit viel leerem Boden. Also muss der Ausschnitt den beiden
folgen.

Woran erkennt man sie? Nicht an der Farbe: Der weiße Dobok ist so hell
wie der Hallenboden, und Bodenlinien sind so farbig wie ein roter Gürtel.
Verlässlich ist etwas anderes – die Kamera steht still. Was sich über die
Aufnahme hinweg verändert, sind die Personen, und was gleich bleibt, ist
die Halle.

Daraus wird ein Bild der leeren Halle: fünfzehn Einzelbilder über das
Video verteilt, je Bildpunkt der Medianwert. Weil die beiden sich
bewegen, gewinnt an jeder Stelle die Halle. Der Unterschied dazu zeigt
die Personen.

Bestimmt wird der Ausschnitt aber nicht aus dem ersten Bild, sondern aus
allen fünfzehn zusammen – siehe bewegungsraum(). Im ersten Bild stehen
beide still, und wer stillsteht, steckt im Hallenbild mit drin und wird
nicht erkannt.

Zugeschnitten wird aus der Originalaufnahme in voller Auflösung, nicht
aus dem fertigen 720p-Video – so kostet der engere Ausschnitt keine
Schärfe.

Voraussetzung: ffmpeg (oder pip install imageio-ffmpeg), numpy, opencv.

Jeder ffmpeg-Aufruf hier bekommt -nostdin und eine leere
Standardeingabe. Ohne das liest ffmpeg von der Standardeingabe mit und
frisst Zeichen weg, wenn dieses Skript in einer Schleife laeuft, die
ihre Liste ueber die Standardeingabe bekommt – die Zieldateien hiessen
dann "-kyorugi-02.jpg" statt "hanbon-kyorugi-02.jpg", weil der Anfang
jeder Zeile verschwunden war.
"""
import argparse
import os
import shutil
import subprocess
import sys
import tempfile

import cv2
import numpy as np

# Wie viel Höhe die Personen im fertigen Bild einnehmen sollen. 0.72
# lässt oben und unten Luft, ohne dass Füße oder Kopf anstoßen.
ANTEIL_HOEHE = 0.72

# So viele Einzelbilder für das Bild der leeren Halle. Weniger als etwa
# sieben, und eine Person, die lange still steht, bleibt darin stehen.
HALLENBILDER = 15

SEITENVERHAELTNIS = 16 / 9
BREITE, HOEHE = 1280, 720

# Breite, in der nach den Personen gesucht wird. Für die Frage „wo stehen
# die beiden" genügt das; zugeschnitten wird danach im Original.
ANALYSE_BREITE = 480


def ffmpeg_pfad():
    for kandidat in (os.environ.get("FFMPEG"), shutil.which("ffmpeg")):
        if kandidat and os.path.exists(kandidat):
            return kandidat
    try:
        import imageio_ffmpeg
        return imageio_ffmpeg.get_ffmpeg_exe()
    except Exception:
        sys.exit("FEHLER: ffmpeg nicht gefunden. 'pip install imageio-ffmpeg' hilft.")


def laufzeit(ff, quelle):
    aus = subprocess.run([ff, "-nostdin", "-hide_banner", "-i", quelle],
                         capture_output=True, text=True,
                         stdin=subprocess.DEVNULL).stderr
    for zeile in aus.splitlines():
        if "Duration:" in zeile:
            teil = zeile.split("Duration:")[1].split(",")[0].strip()
            s, m, h = teil.split(":")[::-1]
            return int(h) * 3600 + int(m) * 60 + float(s)
    return 0.0


def bild_bei(ff, quelle, sekunde, ordner, name):
    """Ein Einzelbild in voller Auflösung, Farben nach bt709 umgerechnet."""
    ziel = os.path.join(ordner, name)
    # Dieselbe Tonwertkette wie in video-aufbereiten.sh: Die Aufnahmen
    # sind HDR, ohne Umrechnung wirkt das Bild blass.
    tonwert = ("zscale=t=linear:npl=100,format=gbrpf32le,zscale=p=bt709,"
               "tonemap=tonemap=hable:desat=0,zscale=t=bt709:m=bt709:r=tv,"
               "format=yuv420p")
    for filter_kette in (tonwert, "format=yuv420p"):
        ergebnis = subprocess.run(
            [ff, "-nostdin", "-hide_banner", "-loglevel", "error", "-y",
             "-ss", f"{sekunde:.3f}", "-i", quelle, "-frames:v", "1",
             "-vf", filter_kette, ziel],
            capture_output=True, text=True, stdin=subprocess.DEVNULL)
        if ergebnis.returncode == 0 and os.path.exists(ziel):
            return cv2.imread(ziel)
    return None


def analysebilder(ff, quelle, dauer, ordner):
    """
    Einzelbilder über die Aufnahme verteilt, klein und ohne Farbumrechnung.

    Beides braucht es hier nicht – gesucht ist nur, wo die Personen
    stehen, und dafür genügt eine schmale Fassung. Mit voller Auflösung
    und Tonwertkette dauerte ein Video zwei Minuten. Ein einziger
    ffmpeg-Aufruf holt jetzt alle Bilder, statt fünfzehnmal neu in die
    Datei zu springen.
    """
    muster = os.path.join(ordner, "h%03d.png")
    rate = HALLENBILDER / max(dauer, 0.1)
    ergebnis = subprocess.run(
        [ff, "-nostdin", "-hide_banner", "-loglevel", "error", "-y", "-i", quelle,
         "-vf", f"fps={rate:.4f},scale={ANALYSE_BREITE}:-2",
         "-frames:v", str(HALLENBILDER), muster],
        capture_output=True, text=True, stdin=subprocess.DEVNULL)
    if ergebnis.returncode != 0:
        return []

    bilder = []
    for i in range(1, HALLENBILDER + 1):
        pfad = os.path.join(ordner, f"h{i:03d}.png")
        if os.path.exists(pfad):
            b = cv2.imread(pfad)
            if b is not None:
                bilder.append(b)
    return bilder


def hallenbild(bilder):
    """Die Halle ohne Personen: Medianwert der Einzelbilder."""
    if len(bilder) < 3:
        return None
    return np.median(np.stack(bilder), axis=0).astype(np.uint8)


def hochrechnen(kasten, von_breite, nach_breite):
    """Rechteck aus der Analysefassung auf die Größe des Originals."""
    f = nach_breite / von_breite
    return tuple(int(round(w * f)) for w in kasten)


def bewegungsraum(bilder, halle):
    """
    Der Bereich, in dem sich die beiden während der Aufnahme aufhalten.

    Der Ausschnitt wird nicht aus dem gewählten Einzelbild bestimmt,
    sondern aus allen. Der Grund liegt am Vorschaubild selbst: Gewünscht
    ist das erste Bild, und da stehen beide in der Ausgangsstellung
    still. Wer stillsteht, steckt aber im Bild der leeren Halle mit drin
    und wird darum nicht erkannt – besonders die Beine, die sich am
    Anfang gar nicht rühren. Der Ausschnitt schnitt dann Füße ab.

    Über die ganze Aufnahme hinweg bewegt sich dagegen jeder Körperteil
    irgendwann. Die Summe aller erkannten Rechtecke deckt deshalb
    zuverlässig ab, wo die beiden stehen und wohin sie treten – Füße
    und ausgestreckte Beine eingeschlossen.
    """
    kaesten = [k for k in (personen_kasten(b, halle) for b in bilder) if k]
    if not kaesten:
        return None
    return (min(k[0] for k in kaesten), min(k[1] for k in kaesten),
            max(k[2] for k in kaesten), max(k[3] for k in kaesten))


def personen_kasten(bild, halle):
    """
    Umschließendes Rechteck der Personen, oder None.

    Gearbeitet wird in der Größe des Hallenbilds, also in der schmalen
    Analysefassung; das Ergebnis rechnet hochrechnen() anschließend auf
    das Original hoch.
    """
    bild = cv2.resize(bild, (halle.shape[1], halle.shape[0]),
                      interpolation=cv2.INTER_AREA)
    unterschied = cv2.absdiff(bild, halle)
    grau = cv2.cvtColor(unterschied, cv2.COLOR_BGR2GRAY)
    grau = cv2.GaussianBlur(grau, (9, 9), 0)

    # Schwelle aus dem Bild selbst: Otsu trennt "Halle" von "anders als
    # Halle", ohne dass eine feste Zahl für jede Aufnahme passen muss.
    _, maske = cv2.threshold(grau, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    maske = cv2.morphologyEx(maske, cv2.MORPH_OPEN, np.ones((3, 3), np.uint8))
    maske = cv2.morphologyEx(maske, cv2.MORPH_CLOSE, np.ones((13, 13), np.uint8))

    anzahl, _, stats, _ = cv2.connectedComponentsWithStats(maske, connectivity=8)
    if anzahl < 2:
        return None

    # Welche Flächen sind Personen? Nicht über eine feste Mindestgröße:
    # Unter den beiden Trainierenden fanden sich Schatten und eine
    # Bodenmarkierung als eigene Flächen von 227 und 504 Bildpunkten,
    # gegen 12 171 für das Paar. Eine Promillegrenze ließ sie durch, und
    # weil eine davon bis an den unteren Bildrand reichte, zog sie den
    # Ausschnitt auf die ganze Bildhöhe – der Zuschnitt fiel damit aus.
    #
    # Der Maßstab ist deshalb die größte Fläche selbst: Zwei Personen im
    # Bild sind ähnlich groß, ein Schatten ist um eine Größenordnung
    # kleiner. Fünfzehn Prozent trennen das zuverlässig.
    groesste = max(int(s[4]) for s in stats[1:])
    mindest = max(bild.shape[0] * bild.shape[1] / 1000, groesste * 0.15)
    teile = [s for s in stats[1:] if s[4] >= mindest]
    if not teile:
        return None

    # Beide Personen zusammen, nicht nur die größere Fläche.
    x0 = min(s[0] for s in teile)
    y0 = min(s[1] for s in teile)
    x1 = max(s[0] + s[2] for s in teile)
    y1 = max(s[1] + s[3] for s in teile)
    return x0, y0, x1, y1


def zuschneiden(bild, kasten):
    """16:9-Ausschnitt um die Personen, im Bild gehalten."""
    hoehe, breite = bild.shape[:2]
    x0, y0, x1, y1 = kasten

    # Ein kleiner Zuschlag nach unten. Früher waren es zwölf Prozent, um
    # stillstehende Füße auszugleichen, die im Bild der leeren Halle
    # steckten; das erledigt jetzt bewegungsraum(). Geblieben ist etwas
    # Luft für den Schatten unter den Füßen.
    y1 = min(hoehe, y1 + int((y1 - y0) * 0.04))

    ziel_hoehe = min(hoehe, max(1, int((y1 - y0) / ANTEIL_HOEHE)))
    ziel_breite = int(ziel_hoehe * SEITENVERHAELTNIS)
    if ziel_breite > breite:
        ziel_breite = breite
        ziel_hoehe = int(ziel_breite / SEITENVERHAELTNIS)

    # Sind die beiden weit auseinander, muss der Ausschnitt breiter sein,
    # sonst fällt eine Person heraus.
    if x1 - x0 > ziel_breite * 0.92:
        ziel_breite = min(breite, int((x1 - x0) / 0.92))
        ziel_hoehe = min(hoehe, int(ziel_breite / SEITENVERHAELTNIS))
        ziel_breite = int(ziel_hoehe * SEITENVERHAELTNIS)

    mitte_x = (x0 + x1) // 2
    # Senkrecht auf die Mitte des (unten erweiterten) Rechtecks. Das
    # verschiebt den Ausschnitt leicht nach unten und lässt über den
    # Köpfen Luft, statt Füße anzuschneiden.
    mitte_y = (y0 + y1) // 2

    links = int(np.clip(mitte_x - ziel_breite // 2, 0, breite - ziel_breite))
    oben = int(np.clip(mitte_y - ziel_hoehe // 2, 0, hoehe - ziel_hoehe))

    ausschnitt = bild[oben:oben + ziel_hoehe, links:links + ziel_breite]
    return cv2.resize(ausschnitt, (BREITE, HOEHE), interpolation=cv2.INTER_AREA)


def main():
    p = argparse.ArgumentParser(description=__doc__,
                                formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("quelle", help="Originalaufnahme (.mov)")
    p.add_argument("--ziel", required=True, help="Pfad des Vorschaubilds (.jpg)")
    # Das erste Bild: Da stehen sich beide gegenüber, bevor die Technik
    # beginnt. Über dreizehn Videos hinweg sieht das gleich aus, und
    # genau das ist der Zweck – die Kacheln sollen eine Reihe bilden und
    # nicht dreizehn verschiedene Momente zeigen.
    p.add_argument("--bei", type=float, default=0.0,
                   help="Zeitpunkt als Anteil der Laufzeit (Standard 0, "
                        "also das erste Bild)")
    args = p.parse_args()

    ff = ffmpeg_pfad()
    dauer = laufzeit(ff, args.quelle)
    if dauer <= 0:
        sys.exit(f"FEHLER: Laufzeit von {args.quelle} nicht lesbar.")

    with tempfile.TemporaryDirectory() as ordner:
        bild = bild_bei(ff, args.quelle, dauer * args.bei, ordner, "bild.png")
        if bild is None:
            sys.exit(f"FEHLER: Kein Einzelbild aus {args.quelle} zu holen.")

        bilder = analysebilder(ff, args.quelle, dauer, ordner)
        halle = hallenbild(bilder)
        kasten = bewegungsraum(bilder, halle) if halle is not None else None
        if kasten is not None:
            kasten = hochrechnen(kasten, halle.shape[1], bild.shape[1])

    if kasten is None:
        print(f"   {os.path.basename(args.quelle)}: keine Personen gefunden, "
              f"ganzes Bild")
        ergebnis = cv2.resize(bild, (BREITE, HOEHE), interpolation=cv2.INTER_AREA)
    else:
        x0, y0, x1, y1 = kasten
        print(f"   {os.path.basename(args.quelle)}: Bewegungsraum "
              f"x {x0}–{x1}, y {y0}–{y1} von {bild.shape[1]}×{bild.shape[0]}")
        ergebnis = zuschneiden(bild, kasten)

    cv2.imwrite(args.ziel, ergebnis,
                [cv2.IMWRITE_JPEG_QUALITY, 88, cv2.IMWRITE_JPEG_PROGRESSIVE, 1])
    print(f"   → {args.ziel}")


if __name__ == "__main__":
    main()
