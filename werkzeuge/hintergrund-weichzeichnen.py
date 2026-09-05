#!/usr/bin/env python3
"""
Hintergrund eines Trainerporträts beruhigen.

Die Porträts entstehen vor der Mattenwand in der Halle. Deren Fugen und
Flecken machen die Teamseite unruhig, weil sieben Bilder nebeneinander
stehen. Dieses Skript zeichnet den Hintergrund weich; die Person bleibt
unverändert und scharf.

    python3 werkzeuge/hintergrund-weichzeichnen.py assets/img/trainer-name.jpg

Ohne --ziel wird die Datei an Ort und Stelle ersetzt. Die Fassung davor
steht weiterhin in der Git-Historie.

Einmalig nötig (rund 200 MB, davon 176 MB Freistellungsmodell):

    pip install numpy opencv-python-headless "rembg[cpu]" onnxruntime

Das Modell u2net lädt beim ersten Lauf selbst herunter und liegt danach
unter ~/.rembg/. Für die Website selbst wird nichts davon gebraucht –
sie bleibt reines HTML, CSS und JavaScript ohne Bauschritt.
"""
import argparse
import sys

import cv2
import numpy as np
from PIL import Image
from rembg import new_session, remove

# Wie stark der Hintergrund verwischt wird. Größer = ruhiger, aber ab
# etwa 121 franst die Silhouette sichtbar aus.
UNSCHAERFE = 81


def maske(pfad, sitzung):
    """Trennt Person und Hintergrund."""
    m = remove(Image.open(pfad).convert("RGB"), session=sitzung, only_mask=True)
    return np.array(m.convert("L"))


def hintergrund_fuellen(bgr, m):
    """
    Malt den Hintergrund hinter der Person weiter.

    Ohne diesen Schritt zöge der weiße Dobok beim Weichzeichnen einen
    hellen Schein um die Silhouette – der verräterische Rand, an dem man
    solche Bearbeitungen sonst erkennt.
    """
    weit = cv2.dilate((m > 20).astype(np.uint8) * 255,
                      np.ones((15, 15), np.uint8), iterations=2)
    return cv2.inpaint(bgr, weit, 12, cv2.INPAINT_TELEA)


def bearbeiten(pfad, sitzung):
    bgr = cv2.imread(pfad, cv2.IMREAD_COLOR)
    if bgr is None:
        sys.exit(f"FEHLER: {pfad} konnte nicht gelesen werden.")

    m = maske(pfad, sitzung)
    hg = hintergrund_fuellen(bgr, m)
    # Zweimal weichzeichnen ergibt eine ruhigere Fläche als einmal mit
    # doppeltem Radius und lässt keine Kanten stehen.
    hg = cv2.GaussianBlur(hg, (UNSCHAERFE, UNSCHAERFE), 0)
    hg = cv2.GaussianBlur(hg, (UNSCHAERFE, UNSCHAERFE), 0)

    # Die Maske einen Hauch einziehen, damit kein Rest der alten
    # Hintergrundfarbe als Saum stehen bleibt, dann die Kante weichzeichnen.
    kante = cv2.erode(m, np.ones((3, 3), np.uint8), iterations=1)
    kante = cv2.GaussianBlur(kante, (5, 5), 0).astype(np.float32) / 255.0
    kante = kante[:, :, None]

    return (bgr * kante + hg * (1 - kante)).astype(np.uint8)


def main():
    p = argparse.ArgumentParser(description=__doc__,
                                formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("bilder", nargs="+", help="ein oder mehrere Porträts")
    p.add_argument("--ziel", help="Ordner für die Ergebnisse statt Ersetzen")
    args = p.parse_args()

    sitzung = new_session("u2net")
    for pfad in args.bilder:
        ergebnis = bearbeiten(pfad, sitzung)
        ziel = pfad
        if args.ziel:
            import os
            os.makedirs(args.ziel, exist_ok=True)
            ziel = os.path.join(args.ziel, os.path.basename(pfad))
        cv2.imwrite(ziel, ergebnis,
                    [cv2.IMWRITE_JPEG_QUALITY, 88, cv2.IMWRITE_JPEG_PROGRESSIVE, 1])
        print("fertig:", ziel)


if __name__ == "__main__":
    main()
