#!/usr/bin/env python3
"""
Erzeugt Platzhalterbilder in der Farbwelt der Website.

Solange die eigenen Aufnahmen aus dem Steinauer Training fehlen, stehen
diese Flächen an ihrer Stelle. Sie sind bewusst als Platzhalter erkennbar:
Ein Stockfoto, das nicht in der Halle entstanden ist, weckt eine falsche
Erwartung – eine Fläche mit „Foto folgt" nicht.

    python3 werkzeuge/platzhalterbild.py

Die Liste der Bilder steht unten in BILDER. Wird ein echtes Foto
geliefert, ersetzt es einfach die Datei; die Größen im HTML passen dann
schon.
"""
import os

from PIL import Image, ImageDraw, ImageFont

WURZEL = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
SCHRIFT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
SCHRIFT_FETT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'

# Farben aus assets/css/style.css
GRUND = (240, 242, 245)
STRICH = (228, 231, 236)
RAHMEN = (208, 214, 222)
TEXT = (106, 113, 125)
INK = (58, 65, 76)
ROT = (193, 25, 31)

# Datei, Breite, Höhe, Bildunterschrift
BILDER = [
    ('hero.jpg',               1700,  947, 'Training in der Halle am Steines'),
    ('kinder-training.jpg',    1200,  819, 'Bambini-Training'),
    ('formenlauf-poomsae.jpg', 1200,  798, 'Formenlauf (Poomsae)'),
    ('wettkampftraining.jpg',  1200,  900, 'Selbstverteidigung'),
    ('partnertraining.jpg',    1200,  798, 'Partnertraining'),
    ('lehrgang.jpg',           1200,  801, 'Lehrgang'),
    ('dojang-abend.jpg',       1200,  900, 'Abendtraining'),
]


def schrift(groesse, fett=False):
    try:
        return ImageFont.truetype(SCHRIFT_FETT if fett else SCHRIFT, groesse)
    except OSError:
        return ImageFont.load_default()


def platzhalter(breite, hoehe, beschriftung):
    bild = Image.new('RGB', (breite, hoehe), GRUND)
    zeichnen = ImageDraw.Draw(bild)

    # Feines diagonales Streifenmuster – gibt der Fläche Struktur, ohne
    # von der Beschriftung abzulenken.
    abstand = max(14, breite // 60)
    for x in range(-hoehe, breite + hoehe, abstand):
        zeichnen.line([(x, 0), (x + hoehe, hoehe)], fill=STRICH, width=2)

    zeichnen.rectangle([0, 0, breite - 1, hoehe - 1], outline=RAHMEN, width=2)

    # Beschriftung mittig, auf ruhigem Grund damit sie lesbar bleibt
    gross = schrift(max(20, breite // 34), fett=True)
    klein = schrift(max(14, breite // 55))

    kopf = 'FOTO FOLGT'
    k1 = zeichnen.textbbox((0, 0), kopf, font=gross)
    k2 = zeichnen.textbbox((0, 0), beschriftung, font=klein)

    inhalt_b = max(k1[2] - k1[0], k2[2] - k2[0])
    inhalt_h = (k1[3] - k1[1]) + (k2[3] - k2[1]) + hoehe // 14
    luft_x, luft_y = breite // 14, hoehe // 12

    kasten = [
        (breite - inhalt_b) // 2 - luft_x,
        (hoehe - inhalt_h) // 2 - luft_y,
        (breite + inhalt_b) // 2 + luft_x,
        (hoehe + inhalt_h) // 2 + luft_y,
    ]
    zeichnen.rectangle(kasten, fill=GRUND)

    # Roter Akzentstrich, wie die Überschriften der Website
    strich_b = max(36, breite // 22)
    y = kasten[1] + luft_y // 2
    zeichnen.rectangle(
        [(breite - strich_b) // 2, y, (breite + strich_b) // 2, y + 3], fill=ROT
    )

    y_kopf = (hoehe - inhalt_h) // 2 + hoehe // 40
    zeichnen.text(((breite - (k1[2] - k1[0])) // 2, y_kopf), kopf, font=gross, fill=INK)
    zeichnen.text(
        ((breite - (k2[2] - k2[0])) // 2, y_kopf + (k1[3] - k1[1]) + hoehe // 18),
        beschriftung, font=klein, fill=TEXT
    )
    return bild


def main():
    ziel = os.path.join(WURZEL, 'assets', 'img')
    for datei, b, h, text in BILDER:
        pfad = os.path.join(ziel, datei)
        platzhalter(b, h, text).save(pfad, quality=86, progressive=True, optimize=True)
        print(f'  {datei:26s} {b}x{h}  „{text}"')


if __name__ == '__main__':
    main()
