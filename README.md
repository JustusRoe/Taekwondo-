# Taekwondo Club Musterstadt e.V. – Beispiel-Website

Musterseite für einen Taekwondo-Verein: statisches HTML/CSS/JS, ohne Framework, ohne
Build-Schritt und ohne externe Abhängigkeiten. Alle Inhalte sind frei erfunden.

## Inhalt der Seite

| Bereich | Beschreibung |
| --- | --- |
| Hero + Kennzahlen | Einstieg mit Foto, zwei Handlungsaufrufen und Vereinszahlen |
| Verein | Vorstellung des Clubs und die fünf Prinzipien des Taekwondo |
| Angebot | Vier Kursgruppen als Bildkarten (Kinder, Jugend, Erwachsene, Wettkampf) |
| Trainingszeiten | Wochenplan als Tabelle mit Hallen-, Termin- und Einsteigerinfos |
| Galerie | Sechs Bilder mit Lightbox (Klick, Schließen per ✕, Klick daneben oder `Esc`) |
| Trainerteam | Drei Porträts mit Graduierung und Aufgabenbereich |
| Downloads | Sieben PDF-Dokumente (Formulare und Vereinsunterlagen) |
| Kontakt | Formular mit Validierung, Geschäftsstelle, Bürozeiten, Anfahrt |
| Impressum / Datenschutz | Eigene Unterseiten mit Mustertexten |

## Projektstruktur

```
.
├── index.html              Startseite mit allen Abschnitten
├── impressum.html          Impressum (Mustertext)
├── datenschutz.html        Datenschutzhinweise (Mustertext)
├── assets/
│   ├── css/style.css       Gesamtes Layout, Farbwelt und Responsive-Regeln
│   ├── js/main.js          Navigation, Lightbox, Formularprüfung, Scroll-Effekte
│   └── img/                Stockfotos (JPG) und Favicon (SVG)
└── downloads/              PDF-Dokumente des Download-Bereichs
```

## Lokal ansehen

Die Seite kommt ohne Server aus – `index.html` lässt sich direkt im Browser öffnen.
Für saubere Pfade und Download-Links empfiehlt sich ein kleiner lokaler Server:

```bash
python3 -m http.server 8000
# danach http://localhost:8000 aufrufen
```

## Gestaltung

- **Grundton weiß.** Flächen sind weiß oder sehr helles Grau (`#f7f8fa`), Struktur entsteht
  über Haarlinien statt über Schatten oder Farbflächen.
- **Ein Akzent.** Rot (`#b01c2e`) für Aktionen, Auszeichnungen und Marken­elemente,
  Marineblau (`#0f2b4a`) nur im Logo.
- **Typografie.** Serifenschrift (Georgia-Stack) für Überschriften, Systemschrift für
  Fließtext. Keine externen Webfonts, dadurch keine Verbindungen zu Dritten.
- **Responsive.** Ab 980 px Breite klappt die Navigation ins Burger-Menü, unterhalb von
  620 px wird die Trainerspalte des Trainingsplans ausgeblendet, damit die Tabelle ohne
  Querscrollen lesbar bleibt.
- **Zugänglichkeit.** Sprunglink zum Inhalt, sichtbare Fokusrahmen, beschriftete
  Formularfelder, deutschsprachige Alternativtexte, Rücksicht auf
  `prefers-reduced-motion`.

## Hinweise zur Anpassung

- **Kontaktformular:** rein clientseitig. `assets/js/main.js` prüft die Eingaben und zeigt
  eine Bestätigung an; es werden keine Daten übertragen oder gespeichert. Für den
  Produktivbetrieb ist ein Formular-Endpunkt (z. B. PHP-Skript oder Formulardienst) im
  `submit`-Handler zu ergänzen.
- **Vereinsdaten:** Name, Anschrift, Telefon, E-Mail, Registerangaben und Beiträge sind
  Platzhalter (`Musterstadt`, `12345`, `info@tkd-musterstadt.de`) und müssen vor einer
  Veröffentlichung ersetzt werden – auch in den PDFs im Ordner `downloads/`.
- **Rechtstexte:** Impressum und Datenschutzhinweise sind Muster und ersetzen keine
  Rechtsberatung.

## Bildnachweis

Alle Fotos stammen von [Unsplash](https://unsplash.com) und werden unter der
Unsplash-Lizenz verwendet. Es handelt sich um Stockfotos, nicht um Aufnahmen eines realen
Vereins.
