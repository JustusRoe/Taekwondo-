# Was noch fehlt

Stand nach der Umstellung auf den TV 1897 Steinau. Vieles ist inzwischen eingearbeitet –
hier steht nur noch, was offen ist.

Auf der Website sind alle offenen Stellen mit einem gestrichelten Hinweis markiert
(<em>„Name folgt"</em>, <em>„Graduierung folgt"</em> …). So sind sie im Entwurf sofort zu
finden und verschwinden, sobald die Angabe da ist.

| Zeichen | Bedeutung |
| --- | --- |
| 🔴 | Blockiert die Veröffentlichung |
| 🟡 | Sollte zum Start stimmen |
| ⚪ | Kann später nachgereicht werden |

---

## Bereits eingearbeitet

Aus der Vereinsseite und deinen Angaben ist alles Folgende schon drin:

| Bereich | Quelle |
| --- | --- |
| Vereinsname, Anschrift, Telefon, Fax, E-Mail | tv-steinau.de |
| Vorstand, Registergericht Schlüchtern, VR 176, Finanzamt Gelnhausen | tv-steinau.de |
| Umlage, Anmelde- und Verbandsgebühr, Kündigung bis 15. November | eure Umlageerklärung |
| Vereinsbeitrag 48 / 120 €, 10 € Verwaltungsgebühr, SEPA-Pflicht, Stand Juli 2024 | Beitrittserklärung |
| Vier Abteilungsformulare als Download | eure PDFs |
| Mitgliedsformular und Änderungsmitteilung als echte PDFs | tv-steinau.de |
| Vereinswappen als Logo, Rot als Leitfarbe | tv-steinau.de |
| Michael Buchhold als Abteilungsleiter, Telefon, taekwondo@tv-steinau.de | tv-steinau.de |
| Schriftführer der Hessischen Taekwondo Union | h-t-u.de |
| Alle 37 Trainingstermine bis Dezember mit Halle | dein Trainingsplan-PDF |
| Halle am Steines (Am Steines 23) und Halle am Schloss (Im Schloß) | Deine Angaben |
| Drei Gruppen, ca. 85 Mitglieder, sieben Namen im Trainerteam | Deine Angaben |

---

## A · Trainerteam 🟡

Alle sieben Porträts sind da und eingebaut: Michael, Andy, Maxim, Aileen, Justus,
Arno und Anna-Karoline. Alle einheitlich angeschnitten mit gleicher Kopfgröße vor derselben
grauen Mattenwand; deren Fugen sind weichgezeichnet, damit die sieben Karten
nebeneinander ruhig wirken (`werkzeuge/hintergrund-weichzeichnen.py`). Die Karte von Julia ist auf Wunsch wieder entfernt worden; das Foto
`assets/img/trainer-julia.jpg` liegt weiterhin im Ordner, falls sie zurückkommen soll.

- [x] ✅ **Porträtfoto von Andy** ist da und eingebaut – gedreht, auf 4:5 zugeschnitten
      und auf dieselbe Kopfgröße gebracht wie die übrigen Karten.
- [ ] 🟡 Für **jede der sieben Personen**:

| Feld | Stand |
| --- | --- |
| Name | ✅ alle sieben |
| Funktion | ✅ bei dreien; bei Aileen, Justus, Arno und Anna-Karoline steht „Training" |
| Graduierung | ✅ alle |
| Kurzvorstellung | ✅ alle sieben (als Aufzählung) |

- [x] ✅ Alle sieben Namen und Graduierungen stehen. 1. Dan: Michael, Andy, Aileen, Arno
      und Justus. 1. Kup: Maxim und Anna-Karoline.
- [ ] 🔴 **Einverständnis jeder Person**, mit Name und Bild genannt zu werden. Das
      passende Formular liegt jetzt vor: `downloads/einwilligung-aufnahmen.pdf`.
- [ ] 🟡 Funktion von Aileen, Justus, Arno und Anna-Karoline – auf den Karten steht
      bisher nur „Training"

---

## B · Trainingsplan 🟡

- [x] ✅ Dienstagstraining geklärt: Es gibt keins. Trainiert wird donnerstags
      (Selbstverteidigung) und samstags (Training und Bambini).
- [ ] 🟡 **Bambini-Zeit klären.** Du sagtest samstags 10:30 – 11:30; im Plan steht bei den
      Terminen in der Halle am Schloss „Bambini 11.30 Uhr". Ist das die Regel dort?
- [x] ✅ Bambini-Alter: 3 bis 6 Jahre – steht auf der Angebotskarte.
- [ ] 🟡 **Bambini-Alter bestätigen.** Du warst dir bei „3 bis 6" nicht ganz sicher;
      es steht so auf der Seite.
- [x] ✅ Kartenlinks zeigen auf Google Maps, mit euren Einträgen für die
      Mehrzweckhalle und die Halle Am Schloss.
- [ ] ⚪ Ferienregelung in einem Satz (fällt Training aus oder wird es verlegt?)
- [ ] ⚪ Termine ab Januar 2027, sobald der neue Plan steht

Die Termine pflegst du später selbst in einer einzigen Datei:
`assets/js/trainingstermine.js`. Der Kalender auf der Startseite baut sich daraus auf.

---

## C · Abteilung 🟡

- [x] ✅ Gründung 2024 mit erfahrenem Trainerstamm – steht im Abteilungstext.
- [x] ✅ Beiträge: Auf der Seite stehen bewusst **keine Zahlen** mehr. Sie verweist für
      den Vereinsbeitrag auf tv-steinau.de und für die Umlage auf die Umlageerklärung im
      Downloadbereich. So gibt es die Zahlen nur an einer Stelle und sie können nicht
      auseinanderlaufen – genau das war vorher passiert.
- [ ] 🔴 **Michael Weber Bescheid geben**, dass auf tv-steinau.de noch 40 / 45 / 110 €
      stehen. Laut Beitrittserklärung (Stand Juli 2024) gelten 48 € und 120 € plus 10 €
      Verwaltungsgebühr. Weil die Seite jetzt dorthin verweist, müssen die Zahlen dort
      stimmen.
- [x] ✅ Mitgliederzahl auf ≈ 85 aktualisiert.
- [ ] ⚪ Welchem Verband gehört die Abteilung an – Hessische Taekwondo Union, und darüber
      hinaus DTU?

---

## D · Rechtstexte 🔴

Impressum steht jetzt mit den echten Vereinsdaten. Die Datenschutzhinweise sind
ausführlicher als die des Hauptvereins, weil Kontaktformular und Mitgliederbereich
dazukommen.

- [ ] 🔴 **Datum des Freistellungsbescheids** (Finanzamt Gelnhausen)
- [ ] 🔴 Beide Texte vom Vorstand freigeben lassen; der Landessportbund Hessen prüft so
      etwas für Mitgliedsvereine
- [ ] 🔴 Auftragsverarbeitungsvertrag mit dem Hoster
- [ ] 🟡 Soll auf die Datenschutzerklärung des Hauptvereins verwiesen oder eine eigene
      geführt werden?

---

## E · Bilder ⚪

Bis auf Weiteres bleiben die Stockfotos – so vereinbart.

- [ ] ⚪ Eigene Aufnahmen aus dem Training (Formate stehen unten)
- [ ] 🔴 Sobald eigene Bilder kommen: **Einwilligungen** aller erkennbaren Personen,
      bei Kindern von den Erziehungsberechtigten
- [ ] ⚪ Vereinswappen als Vektordatei (SVG oder EPS) – derzeit nutze ich die PNG-Datei
      von der Vereinsseite, die für große Darstellungen etwas grob ist

| Platz | Anzahl | Format | Mindestgröße |
| --- | --- | --- | --- |
| Kopfbereich | 1 | quer 4:3,2 | 1600 × 1280 px |
| Abteilung | 1 | hoch 3:4 | 1200 × 1600 px |
| Gruppenkarten | 3 | quer 4:3 | 1200 × 900 px |
| Galerie | 6 | 4 × quadratisch, 2 × quer 2:1 | 1200 × 1200 / 1600 × 800 px |
| Trainerporträts | 1 noch offen (Andy) | hoch 4:5 | 1000 × 1250 px |

---

## F · Mitgliederbereich 🟡

Eingerichtet für **Poomsae** und **Hanbon Kyorugi**, mit sechs Platzhaltervideos.

Zugänge und Videos verwaltet ihr selbst im Browser – FTP wird nicht gebraucht.
Konten legt nur das Trainerteam an, eine Selbstanmeldung gibt es nicht.

- [ ] 🔴 **Einwilligung jeder gefilmten Person** – auch für den passwortgeschützten Bereich
- [ ] 🟡 Wer vergibt die Zugänge und setzt Passwörter zurück?
- [ ] 🟡 Bekommen alle Mitglieder Zugang oder nur bestimmte Gruppen?
- [ ] 🟡 Mitgliederliste für die Kontenanlage: Name, gewünschter Benutzername, E-Mail,
      Rolle (Mitglied oder Trainer)
- [ ] 🟡 Erste echte Videos. Nötig sind nur Titel, Bereich, Gürtelgrad und Beschreibung –
      Länge, Vorschaubild und der Name der Trainerin oder des Trainers ergeben sich beim
      Hochladen von selbst.

**Videoformat:** MP4 (H.264 + AAC), 720p genügt, rund 90 MB je 10 Minuten.
Kostenloses Werkzeug zum Umwandeln: HandBrake.

---

## G · Domain und Hosting 🔴

### Empfehlung zur Adresse

**1. `taekwondo.tv-steinau.de` – die naheliegendste Lösung.**
Eine Unteradresse der Vereinsseite. Kostet nichts, muss nicht registriert werden, und die
Zugehörigkeit zum Verein ist sofort sichtbar. Voraussetzung: Zugriff auf die Verwaltung
von `tv-steinau.de` – dort betreut Michael Weber die Website.

**2. `taekwondo-steinau.de` – dein Vorschlag, gute Wahl.**
Kurz, sprechend, gut zu diktieren. Eine DNS-Abfrage zeigt derzeit keinen Eintrag, die
Domain ist also wahrscheinlich frei – verbindlich prüfen lässt sich das nur beim
Registrar. Kosten: 10–20 € im Jahr.

**Weitere freie Kandidaten** (ebenfalls ohne DNS-Eintrag):
`tkd-steinau.de` · `taekwondo-tv-steinau.de` · `tvsteinau-taekwondo.de`

Mein Rat: **Variante 1 anstreben, Variante 2 zusätzlich registrieren** und auf die
Hauptadresse weiterleiten. Dann ist die kurze Adresse gesichert, ohne dass zwei getrennte
Auftritte entstehen.

### Zu klären

- [ ] 🔴 Entscheidung für eine Adresse
- [ ] 🔴 Wer ist Vertragspartner? Der Verein sollte es sein, keine Privatperson
- [ ] 🔴 **Beim Livegang die Suchmaschinensperre lösen.** Für die Vorschau sperrt
      `robots.txt` alles aus, und jede Seite trägt ein `noindex`. Bleibt das stehen,
      taucht die Seite bei Google nie auf. Erledigt das in einem Rutsch:
      `php werkzeuge/livegang.php --live`
- [ ] 🔴 **Das Kontaktformular verschickt nichts.** Es zeigt nach dem Absenden nur den
      Hinweis, dass die Seite ein Entwurf ist. Vor dem Livegang entweder den Versand
      bauen oder das Formular durch Telefonnummer und E-Mail ersetzen – sonst laufen
      Probetrainingsanfragen ins Leere.

Der ganze Weg von IONOS bis zur erreichbaren Seite steht in `LIVEGANG.md`.
- [ ] 🟡 Wer bekommt die FTP-Zugangsdaten?
- [ ] 🟡 Soll die Seite von tv-steinau.de aus verlinkt werden?

### Empfehlung zum Tarif

**IONOS Webhosting Standard** – <https://www.ionos.de/hosting/webhosting>

Deckt alles ab, was die Seite braucht: 100 GB NVMe-Speicher, 10 MariaDB-Datenbanken
(gebraucht wird eine), PHP 8.2 bis 8.4 frei wählbar, SSH-Zugang, 10 Cronjobs, tägliches
Backup von Dateien und Datenbank, ein Postfach, im ersten Jahr eine Domain inklusive.

Kosten: 3 € monatlich für sechs Monate, danach 6 €, dazu einmalig 10 € Einrichtung –
also 64 € im ersten Jahr, danach 72 € im Jahr.

Der Tarif **Plus** lohnt sich nicht: Er ist im ersten Jahr billiger (1 € monatlich),
kostet danach aber 11 € statt 6 €. Auf drei Jahre gerechnet 286 € gegenüber 208 €.
Die 200 GB und 100 Datenbanken braucht die Abteilung nicht.

**Alternative:** ALL-INKL Privat+ – <https://all-inkl.com/webhosting/> – 7,95 € monatlich,
100 GB, 25 Datenbanken, keine Einrichtungsgebühr, keine Mindestlaufzeit und keine
Preiserhöhung nach dem Aktionszeitraum. Macht 95 € im Jahr statt 72 €. Die 23 € Aufpreis
kaufen Planungssicherheit – für eine Vereinskasse, die einmal im Jahr beschließt, kann
das den Ausschlag geben.

### Kontaktformular und E-Mail

Ein zusätzliches Postfach ist **nicht** nötig. `taekwondo@tv-steinau.de` bleibt, wo es
ist, und wird nur Empfänger des Formulars.

Wichtig für die Einrichtung: IONOS lässt seit dem 29.01.2024 vom Webspace aus keine
Mails mehr mit einer Absenderadresse einer vertragsfremden Domain zu. Das Formular darf
also **nicht** mit Absender `taekwondo@tv-steinau.de` verschicken. Der Aufbau ist:

| Feld | Wert |
| --- | --- |
| Absender | das im Tarif enthaltene Postfach, z. B. `formular@taekwondo-steinau.de` |
| Empfänger | `taekwondo@tv-steinau.de` |
| Antwort an | die Adresse, die der Absender ins Formular eingetragen hat |

Ein Klick auf „Antworten" geht damit direkt an den Anfragenden. Zu klären bleibt nur der
Name des Absenderpostfachs.

---

## Reihenfolge

**Damit die Seite online kann:** D (Rechtstexte) und G (Domain und Hosting).
Alles andere lässt sich danach jederzeit nachziehen – die Seite ist bereits vollständig
benutzbar.

**Der größte sichtbare Sprung** kommt von A (Trainerteam): Fünf Porträts stehen,
drei fehlen noch, und bei allen acht fehlen Graduierung und Kurzvorstellung.

---

## Lieferformate

| Was | Wie am besten |
| --- | --- |
| Texte | formlos in einer Datei oder E-Mail |
| Bilder | JPG, Originalgröße, unbearbeitet |
| Videos | MP4, siehe Block F |
| Termine ab 2027 | so wie das bisherige PDF – ich pflege sie ein |
| Zugangsdaten | **nicht per E-Mail**, sondern telefonisch oder über einen Passwort-Dienst |
