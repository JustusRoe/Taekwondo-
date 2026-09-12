# Taekwondo im TV 1897 Steinau e.V. – Website

Website der Taekwondo-Abteilung des TV 1897 Steinau e.V.: statisches HTML/CSS/JS, ohne
Framework, ohne Build-Schritt und ohne externe Abhängigkeiten. Vereinsdaten, Rechtstexte
und Trainingstermine sind echt; Fotos und Trainerprofile noch Platzhalter – siehe
`CHECKLISTE-INHALTE.md`.

## Aufbau der Seite

Die Website ist keine lange Einzelseite mehr: Jedes Thema hat eine eigene Datei, und
die Navigationsleiste führt alle Themen auf – auf dem Handy als Burger-Menü. `index.html`
ist nur noch der Einstieg.

| Seite | Inhalt |
| --- | --- |
| `index.html` | Einstieg: Hero, der feste Wochenrhythmus und das nächste Training mit Halle |
| `training.html` | Wochenplan nach Hallen, alle kommenden Termine, erstes Training, Ferien |
| `angebot.html` | Drei Gruppen als Bildkarten (Bambini, Breitensport, Selbstverteidigung) |
| `abteilung.html` | Die Abteilung unter dem Dach des Turnvereins |
| `trainerteam.html` | Porträts mit Graduierung und Werdegang |
| `galerie.html` | Sechs Bilder mit Lightbox (Klick, Schließen per ✕, Klick daneben oder `Esc`) |
| `downloads.html` | Formulare des Vereins und der Abteilung als PDF |
| `kontakt.html` | Formular mit Validierung, Geschäftsstelle, Anfahrt zu beiden Hallen |
| `mitglieder*.html` | Anmeldung, Videothek mit Filter und Player mit Abschnitten |
| `backend/` | Serverfassung: Videothek, Terminverwaltung und Zugänge (PHP + MySQL) |
| `impressum.html` / `datenschutz.html` | Rechtstexte (Mustertexte) |

Kopf- und Fußbereich sind auf allen Seiten gleich aufgebaut, die Navigationsleiste ist
in jeder Datei identisch. Wird ein Menüpunkt ergänzt, muss er in jeder HTML-Datei
nachgezogen werden – dafür kommt die Seite ohne Build-Schritt aus.

## Projektstruktur

```
.
├── index.html                   Einstieg: Hero und nächste Trainingstermine
├── training.html                Trainingszeiten, Hallen und alle kommenden Termine
├── angebot.html                 Die drei Trainingsgruppen
├── abteilung.html               Die Abteilung im Turnverein
├── trainerteam.html             Trainerinnen und Trainer
├── galerie.html                 Bildergalerie mit Lightbox
├── downloads.html               Formulare als PDF
├── kontakt.html                 Kontaktformular und Anfahrt
├── impressum.html               Impressum (Mustertext)
├── datenschutz.html             Datenschutzhinweise (Mustertext)
├── mitglieder.html              Mitgliederbereich: Anmeldung (Entwurf)
├── mitglieder-videothek.html    Mitgliederbereich: Videoübersicht (Entwurf)
├── mitglieder-video.html        Mitgliederbereich: Player mit Abschnitten (Entwurf)
├── assets/
│   ├── css/style.css            Layout, Farbwelt und Responsive-Regeln
│   ├── css/mitglieder.css       Ergänzungen für den Mitgliederbereich
│   ├── js/main.js               Navigation, Lightbox, Formularprüfung
│   ├── js/mitglieder.js         Demo-Anmeldung, Filter, Player-Steuerung
│   ├── js/videodaten.js         Daten der Videothek
│   ├── js/trainingstermine.js   Trainingstermine – hier werden sie gepflegt
│   ├── img/                     Eigene Fotos, Platzhalter und Favicon (SVG)
│   └── video/                   Platzhaltervideos (MP4 + WebM) und Vorschaubilder
├── LIVEGANG.md                  Anleitung: von IONOS bis zur erreichbaren Seite
├── .htaccess                    Servereinstellungen (HTTPS, Zwischenspeicher)
├── werkzeuge/                   Hilfsskripte, nicht Teil der Website
├── videos-roh/                  Ablage der Rohaufnahmen (Inhalt nicht im Repository)
├── downloads/                   PDF-Dokumente des Download-Bereichs
└── backend/                     Serverfassung des Mitgliederbereichs (PHP + MySQL)
```

## Mitgliederbereich

Der Bereich besteht aus zwei Teilen, die bewusst getrennt sind:

**1. Entwurf zum Anschauen** – die Dateien `mitglieder*.html`. Sie laufen ohne Server,
zeigen Anmeldung, Videothek und Player und lassen sich dem Vorstand direkt vorführen.
Zugang: `testuser` / `test1234` (Mitglied) oder `testtrainer` / `test1234` (Trainer).

> Diese Anmeldung schützt **nichts**. Sie läuft im Browser, die Videos liegen im
> öffentlichen Ordner. Jede Seite weist oben darauf hin.

### Verwaltung für das Trainerteam

Angemeldete Trainer finden unter `backend/` drei Bereiche:

- **Videos** (`admin.php`) – Trainingsvideos hochladen und beschreiben. Eine ganze
  Reihe kommt schneller per SFTP in den geschützten Ordner: Dateien ohne Eintrag
  erkennt die Seite selbst und trägt sie auf einen Klick ein, mit Titel, Bereich und
  Platz in der Reihe aus `reihe.csv`, falls die daneben liegt.
- **Termine** (`termine.php`) – der Terminplan. Einzelne Termine lassen sich anlegen
  und ändern, ein ganzer Plan als CSV-Datei hochladen (`datum;zeit;gruppe;ort;hinweis`);
  der aktuelle Stand lässt sich als CSV herunterladen und dient zugleich als Vorlage.
  Nach jedem Speichern schreibt die Seite die Termine in die öffentliche Website
  zurück – in `assets/js/trainingstermine.js` und in die Liste auf `training.html`,
  jeweils zwischen den Markierungen `TERMINE:ANFANG` und `TERMINE:ENDE`. Die Website
  bleibt damit statisch und funktioniert auch ohne PHP; niemand muss für einen neuen
  Termin an den Quelltext.
- **Zugänge** (`konten.php`) – Konten anlegen, suchen, filtern und stilllegen.

### Wie die Passwörter laufen

Zugänge vergibt das Trainerteam im Training, eine Selbstregistrierung gibt es nicht.
Das Startpasswort kennt deshalb immer auch die Person, die den Zugang angelegt hat –
darum verlangt der Mitgliederbereich beim ersten Anmelden ein eigenes Passwort
(`passwort.php`). Danach kennt es nur noch das Mitglied selbst; gespeichert ist
ohnehin nur der Hash. Vergessene Passwörter kann das Trainerteam neu setzen, nicht
auslesen.

**Das allererste Konto** entsteht über `backend/einrichten.php`. Die Seite legt ein
Trainerkonto mit selbst gewähltem Passwort an, funktioniert nur bei leerer
Mitgliedertabelle und sperrt sich danach selbst – auch gegen ein nachgebautes
Formular. Ein vorgegebenes Konto mit festem Passwort gibt es bewusst nicht: Es stünde
im Quelltext und wäre damit öffentlich bekannt.

**Zugangsliste.** Weil in der Datenbank nur der Hash steht, lässt sich ein
Startpasswort später nicht mehr auslesen. Wer zwanzig Zugänge anlegt, müsste sich
zwanzig Passwörter im Moment des Anlegens abschreiben. Stattdessen sammelt
`zugangsliste.php` alles, was in dieser Sitzung vergeben wurde – einzeln angelegt, in
einer Ladung angelegt oder zurückgesetzt – und gibt es in drei Formen aus: als
Druckseite, als CSV-Datei und als Zettel je Person zum Ausschneiden. Die Liste liegt
nur in der Sitzung und endet mit dem Abmelden, nach zwei Stunden oder auf Klick.

Für viele Zugänge auf einmal nimmt *Zugänge → Mehrere Zugänge auf einmal anlegen*
eine Liste von Namen; Benutzername und Startpasswort entstehen automatisch. Aus
„Michael Buchhold" wird `m.buchhold`, Umlaute werden ausgeschrieben, und bei einer
Dopplung hängt eine Zahl an.

Trainerzugänge dürfen in diese Verwaltung und sind deshalb zusätzlich geschützt:

- Ein Trainerkonto **anlegen**, **abstufen**, **stilllegen**, dessen **Passwort
  zurücksetzen** oder es **löschen** verlangt das eigene Passwort noch einmal.
- **Gelöscht** werden kann ein Trainerzugang erst, wenn er stillgelegt ist – ein
  Fehlklick kostet damit nie einen Zugang.
- Das **letzte aktive Trainerkonto** lässt sich weder löschen noch abstufen oder
  stilllegen, sonst käme niemand mehr in die Verwaltung.

**2. Serverfassung** – der Ordner `backend/`. Anmeldung gegen eine MySQL-Datenbank mit
gehashten Passwörtern, Sperre nach Fehlversuchen, CSRF-Schutz und – der entscheidende
Teil – `stream.php`: Die Videos liegen außerhalb des öffentlichen Ordners und werden erst
nach geprüfter Sitzung ausgeliefert, mit Unterstützung für HTTP-Range-Requests, damit
Spulen und Kapitelsprünge funktionieren. Einrichtung siehe `backend/README.md`.

### Funktionen des Players

- Abschnittsliste neben dem Video; ein Klick springt an die Stelle
- Der laufende Abschnitt wird hervorgehoben
- Sprungtasten −10 s / +10 s, Pfeiltasten für ±5 s, Leertaste für Pause
- Abspieltempo 0,5× bis 1,5× – hilfreich, um Techniken langsam anzusehen
- Zuletzt gesehene Stelle wird gemerkt und zum Weiterschauen angeboten

### Platzhaltervideos

In `assets/video/` liegen sechs kurze, selbst erzeugte Platzhalterclips mit sichtbaren
Abschnitten – so lässt sich das Springen und Spulen ausprobieren, ohne echte Aufnahmen zu
veröffentlichen. Sie liegen als MP4 (H.264, das Format für den Echtbetrieb) und
zusätzlich als WebM vor, damit sie auch in Browsern ohne H.264 abspielen.

### Echte Aufnahmen aufbereiten

Handyvideos sind HEVC in 10 Bit mit HLG-Farben – das spielen viele Browser nicht
ab, und wo sie es tun, wirkt das Bild blass. `werkzeuge/video-aufbereiten.sh`
macht daraus H.264 in 8 Bit mit richtigen Farben, dazu eine WebM-Fassung und ein
Vorschaubild. SDR-Material erkennt das Skript und lässt die Farbumrechnung dann
weg.

```bash
# Rohaufnahmen liegen in assets/video/, reihenfolge.txt legt die Reihenfolge fest:
werkzeuge/video-aufbereiten.sh --liste videos-roh/reihenfolge.txt \
    assets/video hanbon-kyorugi
php werkzeuge/videodaten-erzeugen.php
```

Die Reihenfolge steht in `videos-roh/reihenfolge.txt`, eine Datei je Zeile – nicht
in den Dateinamen. Kameranamen wie `IMG_1263` sagen nur, wann aufgenommen wurde,
nicht welche Technik zu sehen ist. War ein einzelnes Video falsch, ersetzt
`--platz 4` genau diese Nummer und lässt die übrigen unberührt.

Auf die Zeitstempel in den Dateien ist dabei kein Verlass: Bei den klein
geschriebenen `.mov` dieser Reihe steht als `creation_time` der Zeitpunkt des
Exports – mehrere Dateien tragen dieselbe Sekunde. Nur die groß geschriebenen
`.MOV` haben ihre echte Aufnahmezeit behalten, und die steigt mit der
Dateinummer. Deshalb ist die Nummer der Maßstab.

Dabei hält das Skript in `assets/video/<kürzel>-herkunft.txt` fest, aus welcher
Rohaufnahme jede Nummer entstanden ist. Steckt hinter einer Nummer später eine
andere Aufnahme, setzt `videodaten-erzeugen.php` den von Hand eingetragenen
Techniknamen zurück und meldet das – ein Name am falschen Video wäre schlimmer
als einer, der neu eingetragen werden muss.

**Die Aufnahmen liegen im Repository, und das ist öffentlich.** Sie sind damit für
jeden abrufbar, der die Adresse kennt, und bleiben auch nach einem Löschen über
den Commit-Hash erreichbar. Das ist eine bewusste Entscheidung; was dazugehört –
Einwilligungen der gezeigten Personen und der Weg zurück – steht in
`videos-roh/LIESMICH.md`. Auf der Website selbst bleiben die Videos geschützt:
Dort liegen sie in `videos-privat/` außerhalb des öffentlichen Ordners, und nur
`backend/stream.php` liefert sie nach geprüfter Anmeldung aus.

Ein Git-Haken hält `backend/config.php` und den Zwischenstand in `videos-privat/`
vom Commit ab. Einmal je Arbeitsplatz:

```bash
git config core.hooksPath .githooks
```

## Trainerporträts

Alle Porträts sind 800 × 1000 (4:5), einheitlich angeschnitten mit gleicher
Kopfgröße. Der Hintergrund ist weichgezeichnet: Die Mattenwand der Halle hat
Fugen und Flecken, die nebeneinander unruhig wirken. Die Person bleibt dabei
unverändert und scharf – bearbeitet wird nur der Hintergrund.

Für ein neues Porträt:

```bash
pip install numpy opencv-python-headless "rembg[cpu]" onnxruntime
python3 werkzeuge/hintergrund-weichzeichnen.py assets/img/trainer-name.jpg
```

Beim ersten Lauf lädt das Freistellungsmodell (176 MB) selbst herunter. Die
Website braucht davon nichts – sie bleibt reines HTML, CSS und JavaScript ohne
Bauschritt. Die unbearbeiteten Fassungen stehen in der Git-Historie.

### Die Kante des Doboks

Die Maske von u2net ist nicht schwarzweiß, sondern ein weicher Verlauf, und die
Überblendung folgt ihm. Bei Anna-Karoline begann dieser Verlauf am rechten Ärmel
mit Wert 154 von 255 schon tief im Gewebe und fiel über 25 Bildpunkte ab – dort
wurde der Ärmel also zu fast der Hälfte mit dem weichgezeichneten Hintergrund
vermischt. Das Ergebnis: Der Arm verlor seine Kante und verlief nach außen in
einen blassen Schleier. 33 810 Bildpunkte waren bei ihr betroffen, bei den
anderen Porträts je rund 4 000.

`maske_nachziehen()` behebt das. Dobok und Wand liegen in der Helligkeit weit
auseinander – das Gewebe bei etwa 235, die Mattenwand nie heller als 180 –, und
was heller ist als die hellste Stelle der Wand, kann nur Gewebe sein. Diese
Fläche bekommt volle Deckung; weich wird es erst an der tatsächlichen Kante. Die
Grenze wird je Bild aus dem Hintergrund gelesen, damit sie auch bei anderer
Ausleuchtung stimmt.

Nachgemessen über alle sieben Porträts: Was im Original heller als 200 war,
weicht jetzt um durchschnittlich 0,8 bis 1,2 Helligkeitsstufen ab – das ist
Rauschen. Der Hintergrund wird dabei um durchschnittlich unter 1,2 Stufen heller,
es entsteht also kein Saum.

### Lücken zwischen Arm und Rumpf

Wer den Arm etwas vom Körper weg hält, hat dort einen Spalt, in dem die
Mattenwand zu sehen ist. Alle drei getesteten Modelle (`u2net`,
`u2net_human_seg`, `isnet-general-use`) zählen so einen Spalt zur Silhouette –
sie liefern einen Umriss, und ein schmaler Zwischenraum fällt darin weg. Der
Spalt bleibt deshalb scharf und körnig, während ringsum alles ruhig ist, und
liest sich dann als Schmierer auf dem Arm statt als Wand dahinter. Bei Michael,
Maxim und Anna-Karoline war das so.

Automatisch ist die Stelle nicht sauber zu finden: Ein Versuch über Farbe,
Zusammenhang und Form erwischte zuverlässig auch den KWON-Aufdruck und
Schattenfalten im Gewebe. Deshalb wird sie als Rechteck angegeben:

```bash
python3 werkzeuge/hintergrund-weichzeichnen.py assets/img/trainer-michael.jpg \
    --luecke 190,630,62,320
```

Darin wird geglättet, was grau ist – weder helles Gewebe noch farbig noch
schwarz. Die untere Helligkeitsgrenze hält schwarze Gürtel und dunkle Haare
heraus. Der Spalt behält Form und Helligkeit, es verschwindet nur das Korn;
außerhalb des Rechtecks bleibt alles unangetastet. Mehrere `--luecke` sind
möglich, aber nur ein Bild je Aufruf.

Die benutzten Rechtecke: Anna-Karoline `140,660,62,190` und `138,860,46,140`,
Maxim `140,690,80,310`, Michael `190,630,62,320`.

## Alles auf einmal starten

Für den Mitgliederbereich mit Datenbank genügt ein Befehl. Das Skript richtet die
Testumgebung ein, startet den Server und prüft anschließend selbst, ob alle Dienste
laufen:

```bash
./test/testmain.sh          # Windows ohne WSL: test\testmain.bat
```

Danach erreichbar unter `http://localhost:8080` – Einzelheiten und Optionen in
`test/README.md`.

## Nur die Website ansehen

Die Seite kommt ohne Server aus – `index.html` lässt sich direkt im Browser öffnen.
Für saubere Pfade und Download-Links empfiehlt sich ein kleiner lokaler Server:

```bash
python3 -m http.server 8000
# danach http://localhost:8000 aufrufen
```

Hinweis: `python3 -m http.server` und `php -S` beantworten keine Range-Requests. Das
Springen im Video funktioniert damit nur eingeschränkt – auf echten Servern (Apache,
Nginx, IONOS) und über `backend/stream.php` dagegen vollständig.

## Online stellen

Die vollständige Anleitung steht in **[`LIVEGANG.md`](LIVEGANG.md)**: Domain
zuordnen, PHP-Version, Zertifikat, Datenbank, Dateien hochladen, erstes
Trainerkonto und eine Liste zum Durchprüfen.

Ein Punkt daraus ist leicht zu übersehen: Für die Vorschau ist die Seite für
Suchmaschinen gesperrt – `robots.txt` verbietet alles, und jede Seite trägt ein
`noindex`. Das steckt in einem Dutzend Dateien und wird zusammen umgeschaltet:

```bash
php werkzeuge/livegang.php --status     # wie steht es gerade?
php werkzeuge/livegang.php --live       # Sperre lösen
php werkzeuge/livegang.php --entwurf    # wieder sperren
```

Die Seiten des Mitgliederbereichs behalten ihr `noindex` in jedem Fall.

### Das Auslieferungspaket

Im Projektordner liegen Tests, Hilfsskripte, Rohaufnahmen und die Entwurfsfassung
des Mitgliederbereichs – nichts davon gehört auf den Server. `werkzeuge/paket.sh`
stellt zusammen, was hochgehört, und sortiert es nach Zielort:

```bash
werkzeuge/paket.sh
```

```
auslieferung/
├── LIESMICH.md          was wohin kommt
├── www/                 → in den öffentlichen Ordner des Hostings
├── videos-privat/       → eine Ebene darüber, ausserhalb des Webordners
└── datenbank/           → schema.sql in phpMyAdmin importieren
```

Das Paket ist bewusst nicht eingecheckt: Jede Datei läge sonst zweimal im
Repository, und eine Textänderung müsste an zwei Stellen nachgezogen werden. Wer es
ohne Kommandozeile braucht, holt es als ZIP bei GitHub unter *Actions* → oberster
Lauf → *Artifacts*; die Datei entsteht bei jedem Push
(`.github/workflows/paket.yml`).

Das Skript prüft sich selbst und bricht ab, wenn die Seiten noch auf Entwurf stehen,
wenn Zugangsdaten oder Testzugänge im Paket auftauchen oder wenn eine Rohaufnahme
mitgerutscht ist.

## Vorschau im Netz (GitHub Pages)

Um den Entwurf dem Vorstand zu zeigen, genügt GitHub Pages – kostenlos, kein zusätzliches
Konto, das Repository liegt ja schon hier.

**Einschalten:** Repository → *Settings* → *Pages* → unter *Source* „Deploy from a branch"
wählen, als Branch `claude/taekwondo-club-website-jui1tt` und Ordner `/ (root)`, speichern.
Nach ein bis zwei Minuten liegt die Seite unter:

```
https://justusroe.github.io/Taekwondo-/
```

**Was dort funktioniert:** die komplette Website samt Trainingsplan, Terminkalender,
Galerie, Downloads und Kontaktformular (mit Entwurfshinweis statt Versand).

**Was dort nicht funktioniert:** alles unter `backend/`. GitHub Pages liefert nur Dateien
aus, es führt kein PHP aus. Die echte Anmeldung gegen die Datenbank und der geschützte
Videoabruf über `stream.php` lassen sich nur auf einem PHP-Hoster oder lokal über
`./test/testmain.sh` zeigen.

Seit die Seite auf LIVE steht, zeigt der Menüpunkt „Mitglieder" auf
`backend/login.php`. Auf GitHub Pages **lädt ein Klick darauf die Datei
herunter**, statt eine Anmeldeseite zu zeigen: Pages liefert `.php` mit dem
Inhaltstyp `application/x-httpd-php` aus, und der Browser weiß damit nichts
anzufangen außer zu speichern. Auf einem Hoster mit PHP – also auf der echten
Website – gibt Apache dieselbe Datei an den PHP-Interpreter, und heraus kommt
die Anmeldeseite. Das Verhalten verschwindet also mit dem Livegang von selbst,
es ist kein Fehler in der Seite.

Zu holen ist dabei nichts: In den PHP-Dateien stehen keine Zugangsdaten. Die
einzige Datei mit Geheimnissen ist `backend/config.php`, und die liegt nicht im
Repository, sondern wird direkt auf dem Server angelegt.

Wer den Mitgliederbereich auf Pages trotzdem vorführen will, schaltet mit
`php werkzeuge/livegang.php --entwurf` zurück; dann greift der Menüpunkt wieder
auf die nachgebaute Anmeldung (`testuser` / `test1234`). Für den Livegang
vorher `--live` nicht vergessen, sonst trägt jede Seite ein `noindex` und
`robots.txt` sperrt alle Suchmaschinen aus. `werkzeuge/paket.sh` weigert sich in
diesem Fall, ein Paket zu bauen – das ist die Sicherung dagegen.

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

## Was der Verein noch liefern muss

`CHECKLISTE-INHALTE.md` listet vollständig auf, welche Angaben, Bilder und Dokumente
fehlen, um alle Platzhalter zu ersetzen – nach Blöcken sortiert und danach gekennzeichnet,
was die Veröffentlichung blockiert und was später nachgereicht werden kann.

## Hinweise zur Anpassung

- **Kontaktformular:** schickt echte Nachrichten über `backend/kontakt.php`. Die
  Empfängeradresse steht in `backend/config.php`. Gegen Spam wirken ein für Menschen
  unsichtbares Feld, eine Mindestzeit zwischen Aufruf und Absenden und eine Obergrenze
  je IP-Adresse – kein Captcha. Ohne JavaScript wird dasselbe Formular abgeschickt und
  mit einer schlichten Seite beantwortet.
- **Mitgliederbereich:** Vor dem Echtbetrieb die Serverfassung aus `backend/` einrichten
  und das mitgelieferte Trainerkonto ersetzen. Für Aufnahmen mit erkennbaren Personen –
  besonders Kindern – sind schriftliche Einwilligungen nötig, auch im geschützten Bereich.
- **Vereinsdaten:** Name, Anschrift, Telefon, E-Mail und Registerangaben sind die echten
  Daten des TV 1897 Steinau e.V. Was noch fehlt, steht in `CHECKLISTE-INHALTE.md`.
- **Rechtstexte:** Impressum und Datenschutzhinweise sind erstellt, aber weder vom
  Vorstand freigegeben noch anwaltlich geprüft. Sie ersetzen keine Rechtsberatung.

## Bildnachweis

Die Trainerporträts und das Wettkampffoto sind eigene Aufnahmen der Abteilung. Wo noch
kein eigenes Bild vorliegt, steht eine Platzhalterfläche – erzeugt mit
`werkzeuge/platzhalterbild.py`, in der Farbwelt der Website und als Platzhalter
erkennbar. Ein echtes Foto ersetzt einfach die Datei; die Größen im HTML passen dann
schon. Das Vereinswappen ist Eigentum des TV 1897 Steinau e.V.
