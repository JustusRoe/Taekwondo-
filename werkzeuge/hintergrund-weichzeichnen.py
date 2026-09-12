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


def maske_nachziehen(bgr, m):
    """
    Zieht die Maske bis an die Kante des Doboks.

    u2net trifft den Umriss gut, endet aber stellenweise ein Stück
    *innerhalb* des Gewebes – bei Anna-Karoline am rechten Ärmel bis zu
    13 Bildpunkte. Dieser Streifen gilt dann als Hintergrund, wird
    weichgezeichnet, und der Arm verliert seine Kante: Er verläuft nach
    außen in einen hellen Schleier statt sauber aufzuhören.

    Zu erkennen ist das, weil Gewebe und Wand hier weit auseinander
    liegen. Der Dobok ist nahezu weiß (Helligkeit um 235), die Mattenwand
    grau und nie heller als etwa 180. Was heller ist als die hellste
    Stelle der Wand, kann also nur Gewebe sein – und was davon an die
    Maske grenzt, gehört zur Person.

    Die Grenze wird aus dem Bild gelesen statt festgelegt, damit das auch
    bei einer heller ausgeleuchteten Wand noch stimmt. Gewachsen wird nur
    in der Nähe der Maske: Ein weißes Stück am Bildrand, das nichts mit
    der Person zu tun hat, soll nicht dazukommen.
    """
    person = m > 128
    if not person.any() or person.all():
        return m

    sicher = cv2.erode((~person).astype(np.uint8) * 255,
                       np.ones((25, 25), np.uint8), iterations=2) > 0
    if sicher.sum() < 2000:
        return m

    hell = cv2.cvtColor(bgr, cv2.COLOR_BGR2LAB)[:, :, 0]
    schwelle = max(190, int(np.percentile(hell[sicher], 99.9)) + 10)
    gewebe = hell > schwelle

    nahe = cv2.dilate(person.astype(np.uint8), np.ones((3, 3), np.uint8),
                      iterations=16) > 0

    # Punktweise nach Helligkeit zu entscheiden ergibt einen ausgefransten
    # Rand: Die dunkleren Falten im Gewebe blieben Hintergrund, die hellen
    # Stellen wurden Person, und dazwischen entstanden Fetzen. Deshalb
    # wird die Fläche geschlossen und werden Löcher gefüllt – der Ärmel
    # bekommt eine durchgehende Kante.
    kandidat = (person | (gewebe & nahe)).astype(np.uint8) * 255
    kandidat = cv2.morphologyEx(kandidat, cv2.MORPH_CLOSE,
                                np.ones((9, 9), np.uint8))

    # Löcher füllen: von den Bildrändern aus in den Hintergrund fluten,
    # was dabei nicht erreicht wird, liegt innen.
    aussenherum = np.zeros((kandidat.shape[0] + 2, kandidat.shape[1] + 2), np.uint8)
    geflutet = kandidat.copy()
    cv2.floodFill(geflutet, aussenherum, (0, 0), 255)
    kandidat = kandidat | cv2.bitwise_not(geflutet)

    # Volle Deckung für die ganze Fläche, nicht nur für das, was neu
    # dazukommt. Der Grund liegt darin, wie die Maske aussieht: Sie ist
    # nicht schwarzweiß, sondern ein weicher Verlauf, und die
    # Überblendung folgt ihm. Bei Anna-Karoline beginnt er mit Wert 154
    # schon tief im Ärmel und fällt über 25 Bildpunkte ab – dort wird das
    # Gewebe also zu fast der Hälfte mit dem weichgezeichneten
    # Hintergrund vermischt, und der Arm verliert seine Kante. Innerhalb
    # des Gewebes soll die Deckung voll sein; weich wird es erst an der
    # tatsächlichen Kante, und dafür genügen die paar Bildpunkte, die der
    # Weichzeichner am Ende ohnehin verstreicht.
    fest = kandidat > 0
    # Gezählt wird nur, was wirklich ins Gewicht fällt. Fast die ganze
    # Person hat Maskenwert 254 statt 255; das mitzuzählen ergäbe eine
    # große Zahl ohne Bedeutung.
    geaendert = int((fest & (m < 223)).sum())
    if not fest.any():
        return m

    neu = m.copy()
    neu[fest] = 255
    print(f"   Maske bis zur Gewebekante nachgezogen: {geaendert} "
          f"Bildpunkte mit deutlich zu geringer Deckung "
          f"(Grenze bei Helligkeit {schwelle})")
    return neu


def luecken_glaetten(ergebnis, bgr, m, kaesten):
    """
    Glättet Hintergrund, der zwischen Arm und Rumpf durchscheint.

    Wer den Arm etwas vom Körper weg hält, hat dort eine schmale Lücke, in
    der die Mattenwand zu sehen ist. Alle drei getesteten Modelle (u2net,
    u2net_human_seg, isnet-general-use) zählen so eine Lücke zur
    Silhouette – sie liefern einen Umriss, und ein schmaler Spalt zwischen
    Gliedmaßen fällt darin weg. Die Lücke bleibt deshalb scharf und
    körnig, während ringsum alles ruhig ist. Sie liest sich dann als
    Schmierer auf dem Arm statt als Wand dahinter.

    Was hier passiert, ist bewusst wenig: Die Lücke behält ihre Form und
    ihre Helligkeit, sie wird nur in sich weichgezeichnet. Zwei Wege, die
    ich verworfen habe:

    * Die Lücke aus der Maske herausnehmen und wie Hintergrund behandeln.
      Dann malt sie der Füllschritt aus dem umliegenden weißen Gewebe zu
      und die Unschärfe verteilt das Grau über den halben Ärmel – das
      Ergebnis war deutlich schlechter als das Problem.
    * Die Lücke automatisch finden, über Farbe, Zusammenhang und Form.
      Erwischte zuverlässig auch den KWON-Aufdruck und Schattenfalten im
      Gewebe, also Dinge, die zur Person gehören.

    Deshalb wird die Stelle von Hand angegeben, ein Rechteck je Lücke.
    Darin gilt als Wand, was weder helles Gewebe noch farbig ist. Was das
    Rechteck nicht abdeckt, bleibt unangetastet.

    Geglättet wird nur mit den Bildpunkten der Lücke selbst (gewichtete
    Mittelung): Nähme man die Umgebung mit, zöge das weiße Gewebe den
    Spalt hell und es entstünde genau der Saum, den das Skript sonst
    vermeidet.
    """
    person = m > 128
    if not kaesten or not person.any():
        return ergebnis

    lab = cv2.cvtColor(bgr, cv2.COLOR_BGR2LAB)
    hell, gruen, blau = lab[:, :, 0], lab[:, :, 1], lab[:, :, 2]

    # Wand: grau, also weder heller Stoff noch Farbe noch schwarz.
    #
    # Die Untergrenze ist wichtig: Bei Michael kreuzt ein schwarzer Gürtel
    # die Lücke, und ein Rechteck, das den Keil umfasst, erwischt ihn mit.
    # Die Wand liegt im Spalt bei Helligkeit 70 bis 170, ein schwarzer
    # Gürtel unter 30. Was die Grenze kostet, ist der tiefste Grund der
    # Falte – der bleibt scharf, und das ist richtig: Dort ist es Schatten
    # und nicht Wand.
    wand = ((hell > 45) & (hell < 175)
            & (np.abs(gruen.astype(int) - 128) <= 8)
            & (np.abs(blau.astype(int) - 128) <= 10))

    bereich = np.zeros(person.shape, dtype=bool)
    hoehe, breite = person.shape
    for x, y, w, h in kaesten:
        x0, y0 = max(0, x), max(0, y)
        x1, y1 = min(breite, x + w), min(hoehe, y + h)
        if x1 <= x0 or y1 <= y0:
            print(f"   Hinweis: Rechteck {x},{y},{w},{h} liegt außerhalb des Bildes.")
            continue
        bereich[y0:y1, x0:x1] = True

    luecke = bereich & person & wand
    # Löcher schließen, Einzelpixel wegnehmen: eine geschlossene Fläche,
    # keine gesprenkelte.
    roh = cv2.morphologyEx(luecke.astype(np.uint8) * 255, cv2.MORPH_CLOSE,
                           np.ones((5, 5), np.uint8))
    roh = cv2.morphologyEx(roh, cv2.MORPH_OPEN, np.ones((3, 3), np.uint8))
    luecke = roh > 0

    if luecke.sum() < 100:
        print("   In den angegebenen Rechtecken war keine Lücke zu glätten.")
        return ergebnis

    # Gewichtete Mittelung: Summe der Farben der Lücke, geteilt durch die
    # Zahl der beteiligten Punkte. So bleibt das Grau, wie es ist, und nur
    # das Korn verschwindet.
    radius = 21
    gewicht = luecke.astype(np.float32)
    quelle = ergebnis.astype(np.float32) * gewicht[:, :, None]
    summe = cv2.GaussianBlur(quelle, (radius, radius), 0)
    teiler = cv2.GaussianBlur(gewicht, (radius, radius), 0)
    weich = summe / np.maximum(teiler, 1e-6)[:, :, None]

    # Weicher Übergang am Rand der Lücke, damit keine Kante entsteht.
    rand = cv2.GaussianBlur(luecke.astype(np.float32), (7, 7), 0)[:, :, None]
    neu = (weich * rand + ergebnis.astype(np.float32) * (1 - rand))

    print(f"   Lücke im Umriss geglättet: {int(luecke.sum())} Bildpunkte "
          f"({100 * luecke.sum() / person.sum():.2f} % der Silhouette)")
    return np.clip(neu, 0, 255).astype(np.uint8)


def kasten_lesen(text):
    """140,665,60,185 wird zu (140, 665, 60, 185)."""
    teile = text.split(",")
    if len(teile) != 4:
        sys.exit("FEHLER: --luecke braucht vier Zahlen (x,y,breite,hoehe), "
                 f"bekommen habe ich: {text}")
    try:
        return tuple(int(t) for t in teile)
    except ValueError:
        sys.exit(f"FEHLER: --luecke braucht ganze Zahlen, bekommen habe ich: {text}")


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


def bearbeiten(pfad, sitzung, kaesten=()):
    bgr = cv2.imread(pfad, cv2.IMREAD_COLOR)
    if bgr is None:
        sys.exit(f"FEHLER: {pfad} konnte nicht gelesen werden.")

    m = maske(pfad, sitzung)
    m = maske_nachziehen(bgr, m)
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

    ergebnis = (bgr * kante + hg * (1 - kante)).astype(np.uint8)

    # Zum Schluss die von Hand angegebenen Lücken im Umriss beruhigen.
    if kaesten:
        ergebnis = luecken_glaetten(ergebnis, bgr, m, kaesten)
    return ergebnis


def main():
    p = argparse.ArgumentParser(description=__doc__,
                                formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("bilder", nargs="+", help="ein oder mehrere Porträts")
    p.add_argument("--ziel", help="Ordner für die Ergebnisse statt Ersetzen")
    p.add_argument("--luecke", action="append", metavar="X,Y,BREITE,HOEHE",
                   default=[],
                   help="Rechteck, in dem Graues als Hintergrund gilt – für "
                        "eine Lücke zwischen Arm und Rumpf, welche die "
                        "Freistellung zur Person gezählt hat. Mehrfach "
                        "angebbar. Gilt für alle genannten Bilder, ist also "
                        "nur mit einem Bild je Aufruf sinnvoll.")
    args = p.parse_args()

    kaesten = [kasten_lesen(k) for k in args.luecke]
    if kaesten and len(args.bilder) > 1:
        sys.exit("FEHLER: --luecke gilt für alle genannten Bilder. Bitte ein "
                 "Bild je Aufruf.")

    sitzung = new_session("u2net")
    for pfad in args.bilder:
        print("→", pfad)
        ergebnis = bearbeiten(pfad, sitzung, kaesten)
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
