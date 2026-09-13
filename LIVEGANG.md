# Livegang bei IONOS

Schritt für Schritt von den gekauften Paketen bis zur erreichbaren Website.
Rechne mit ein bis zwei Stunden für den ersten Durchlauf.

Die Menüpunkte bei IONOS heißen nicht überall gleich und werden gelegentlich
umbenannt. Deshalb steht hier, **was** einzurichten ist – der Punkt heißt im
Kundenmenü dann sinngemäß so.

---

## Vorher: die zwei Sachen, die noch offen sind

Bevor die Seite öffentlich erreichbar ist, sollten diese beiden Punkte geklärt
sein. Beide brauchen keine Arbeit am Code.

1. **Rechtstexte freigeben lassen.** Impressum und Datenschutzhinweise stehen
   mit echten Vereinsdaten, sind aber vom Vorstand nicht freigegeben.
2. **Auftragsverarbeitungsvertrag mit IONOS.** Steht im Kundenmenü unter
   Datenschutz zum Abschließen bereit. Nötig, weil auf dem Server
   personenbezogene Daten liegen (Mitgliederkonten, Kontaktanfragen).

Einzelheiten dazu in `CHECKLISTE-INHALTE.md`, Abschnitte D und G.

Das Kontaktformular verschickt seit dem letzten Stand echte Nachrichten – die
Empfängeradresse wird in Schritt 6 eingetragen.

---

## Was braucht was

PHP und die Datenbank sind **nichts, was du installieren musst** - beides gehoert
schon zum Webhosting-Paket. PHP muss nur auf die richtige Version gestellt
werden (ein Auswahlfeld), und die Datenbank wird einmal angelegt (ein
Formular). Beides dauert zusammen keine zehn Minuten.

Wichtiger ist zu wissen, welcher Teil der Website was davon ueberhaupt braucht:

| Teil der Website | PHP | Datenbank | `config.php` |
| --- | :---: | :---: | :---: |
| Die oeffentliche Website - alle Seiten, Bilder, PDFs, Terminplan | - | - | - |
| Kontaktformular | ja | - | ja |
| Mitgliederbereich, Videothek, Zugaenge, Terminverwaltung | ja | ja | ja |

**Das heisst:** Sobald die Dateien oben liegen, ist die oeffentliche Website
fertig und erreichbar. Sie ist reines HTML, CSS und JavaScript - auch der
Terminplan steht fest in den Dateien. Wenn beim Rest noch etwas fehlt, merken
Besucher davon nichts.

PHP und Datenbank betreffen nur den Ordner `backend/`. Und selbst dort gilt:
Das Kontaktformular kommt ohne Datenbank aus - absichtlich, damit
Probetrainingsanfragen auch dann ankommen, wenn die Datenbank streikt.

## Reihenfolge

Einrichten im Kundenmenue und Hochladen sind zwei getrennte Vorgaenge, die sich
nicht ins Gehege kommen. Am wenigsten Warterei gibt es so:

1. **Zuerst im Kundenmenue** (Schritte 1 bis 4): Domain zuordnen, PHP-Version,
   Zertifikat anfordern, Datenbank anlegen. Das Zertifikat braucht danach
   ohnehin ein paar Minuten - die nutzt du fuers Hochladen.
2. **Dann hochladen** (Schritt 5).
3. **Zum Schluss** `config.php` anlegen und `schema.sql` einspielen - dafuer
   brauchst du die Datenbankangaben aus Schritt 4.

Du kannst auch zuerst hochladen. Dann steht die oeffentliche Website sofort, und
`/backend/...` meldet "config.php fehlt", bis du Schritt 6 nachholst. Kaputt geht
dabei nichts.

## 1. Domain auf das Hosting zeigen lassen

Domain und Webhosting sind bei IONOS zwei getrennte Produkte, auch wenn sie
zusammen gekauft wurden. Die Domain muss dem Webspace **zugeordnet** werden.

Im Kundenmenü unter *Domains*: die Domain auswählen und als Ziel den Webspace
angeben, genauer den Ordner, in den die Website kommt (das sogenannte
Document Root, meist `/` oder `/www`).

Wurden Domain und Hosting zusammen bestellt, ist das oft schon eingerichtet.
Prüfen lässt es sich, indem man die Domain aufruft: Erscheint die
Platzhalterseite von IONOS, zeigt sie richtig.

**Wenn die Domain woanders liegt** – etwa weil `taekwondo.tv-steinau.de` als
Unteradresse der Vereinsseite genutzt werden soll –, trägt stattdessen die
Person, die `tv-steinau.de` verwaltet, einen Eintrag auf die IONOS-Adresse ein.
Nach so einer Änderung dauert es bis zu 24 Stunden, bis sie überall bekannt ist.

## 2. PHP-Version einstellen

Unter *Hosting → PHP* die Version auf **8.2 oder neuer** stellen. Der
Mitgliederbereich nutzt Sprachmittel, die es vorher nicht gab, und läuft mit
älteren Versionen nicht.

## 3. SSL-Zertifikat aktivieren

Unter *Hosting → SSL*. Im Tarif ist ein Zertifikat enthalten; es muss der
Domain nur zugewiesen werden. Das dauert einige Minuten bis wenige Stunden.

**Erst danach** wird in Schritt 7 die Umleitung auf HTTPS eingeschaltet –
vorher liefe die Seite in eine Endlosschleife.

Ohne Verschlüsselung wandern die Passwörter des Mitgliederbereichs im Klartext
durchs Netz. Der Punkt ist nicht optional.

## 4. Datenbank anlegen

Unter *Hosting → Datenbanken* eine **MySQL-Datenbank** anlegen. Notiere dir vier
Angaben, sie werden gleich gebraucht:

| Angabe | Beispiel |
| --- | --- |
| Host | `db1234.hosting-data.io` |
| Datenbankname | `dbs1234567` |
| Benutzer | `dbu1234567` |
| Passwort | selbst vergeben |

Der Host heißt bei IONOS **nicht** `localhost`. Das ist der häufigste Fehler.

Danach `website/datenbank/schema.sql` einspielen: In der Datenbankübersicht
phpMyAdmin öffnen, links die Datenbank wählen, Reiter *Importieren*, Datei
auswählen, ausführen. Danach stehen die Tabellen `mitglieder`, `videos`,
`trainingstermine` und `login_versuche` bereit – noch ohne jeden Zugang. Das
erste Trainerkonto entsteht in Schritt 9 über `einrichten.php`, mit einem
Passwort, das du selbst wählst.

## 5. Dateien hochladen

Unter *Hosting → SFTP/SSH* einen Zugang anlegen und die Zugangsdaten notieren.
Zum Übertragen eignet sich [FileZilla](https://filezilla-project.org/) – als
Protokoll **SFTP** wählen, nicht das alte FTP.

Wichtig ist die Ordnerstruktur. Der Videoordner muss **eine Ebene über** dem
öffentlichen Ordner liegen, sonst kann jeder die Videos direkt herunterladen
und der ganze Mitgliederbereich ist wertlos:

```
/                              ← Wurzel des Hosting-Kontos
  ├── videos-privat/           ← anlegen, bleibt leer; NICHT öffentlich
  └── www/                     ← öffentlicher Ordner (Document Root)
        ├── index.html
        ├── training.html  angebot.html  …
        ├── .htaccess
        ├── assets/
        ├── downloads/
        └── backend/
```

### Am einfachsten: der Ordner `website/`

Von Hand aussortieren muss niemand. Im Repository liegt der Ordner
**`website/`** – darin steckt genau das, was auf den Server gehört,
schon sortiert nach Zielort:

| Ordner in `website/` | Wohin auf dem Server |
| --- | --- |
| `www/` | der **Inhalt** in den öffentlichen Ordner |
| `videos-privat/` | der **Inhalt** eine Ebene darüber |
| `datenbank/schema.sql` | nicht hochladen – in phpMyAdmin importieren (Schritt 4) |

Herunterladen geht auf drei Wegen, je nachdem was da ist:

* **Mit Git:** `git pull`, dann liegt `website/` im Projektordner.
* **Ohne Git:** Bei GitHub auf *Code → Download ZIP*; `website/` ist darin.
* **Nur den Ordner:** Reiter *Actions* → obersten Lauf öffnen → unten bei
  *Artifacts* `website` herunterladen. Das ist derselbe Inhalt, aber ohne
  den Rest des Projekts.

**Nach jeder Änderung an der Website neu bauen:**

```
werkzeuge/paket.sh
```

Das ist wichtig, weil der Ordner eingecheckt ist: Wird eine Seite
geändert und der Ordner nicht neu gebaut, lädt man sonst eine alte
Fassung hoch und merkt es nicht. Dagegen steht in `website/STAND.txt`
eine Prüfsumme über die Quelldateien, und `php test/check.php` meldet,
wenn sie nicht mehr passt.

Das Skript prüft sich außerdem selbst und bricht ab, wenn die Seiten noch
auf Entwurf stehen, wenn Zugangsdaten oder Testzugänge hineingerutscht
sind oder wenn eine Rohaufnahme mitkommt.

### Oder von Hand

**Das kommt in `www/`:** alle `.html`-Dateien außer `mitglieder*.html`,
dazu `.htaccess`, `robots.txt` sowie die Ordner `assets/`, `downloads/`
und `backend/`.

**Das bleibt auf deinem Rechner:** `test/`, `werkzeuge/`, `videos-roh/`,
`website/`, `README.md`, `LIVEGANG.md`, `CHECKLISTE-INHALTE.md`,
`.git/`, `.github/`, `.nojekyll`. Das sind Entwicklungs- und
Pflegewerkzeuge, die auf dem Server nichts verloren haben.

**Und das hier auch – wichtig:**

```
mitglieder.html  mitglieder-videothek.html  mitglieder-video.html
assets/js/mitglieder.js
assets/js/videodaten.js
assets/video/*.mp4  *.webm  *.mov    (die Vorschaubilder *.jpg dagegen schon)
```

Das ist die **nachgebaute Anmeldung zum Vorführen**. Sie läuft nur im Browser,
schützt nichts und zeigt Zugangsdaten offen auf der Seite an. Die
Videodateien daneben gehören nicht in den öffentlichen Ordner: Im Betrieb
liegen sie außerhalb und werden von `backend/stream.php` erst nach
geprüfter Anmeldung ausgeliefert.

Schritt 8 biegt den Menüpunkt „Mitglieder" automatisch auf `backend/login.php`
um, die echte Anmeldung. Die mitgelieferte `.htaccess` sperrt die
Entwurfsdateien zusätzlich aus – aber am saubersten ist, sie gar nicht erst
hochzuladen.

**Und niemals hochladen:** die lokale `backend/config.php`. Sie zeigt auf die
Testdatenbank. Die Fassung für den Server wird im nächsten Schritt direkt dort
angelegt.

> **Achtung, versteckte Dateien.** `.htaccess` beginnt mit einem Punkt und wird
> von vielen FTP-Programmen standardmäßig nicht angezeigt – sie fehlt dann beim
> Hochladen, ohne dass es auffällt. In FileZilla: *Server → Versteckte Dateien
> anzeigen*. Prüfen lässt es sich, indem man nach dem Hochladen im Serverordner
> nachsieht, ob `.htaccess` dort steht.

## 6. `backend/config.php` auf dem Server anlegen

`backend/config.example.php` als Vorlage nehmen, ausfüllen und als
`backend/config.php` speichern:

```php
'db' => [
    'dsn'      => 'mysql:host=db1234.hosting-data.io;dbname=dbs1234567;charset=utf8mb4',
    'benutzer' => 'dbu1234567',
    'passwort' => 'DEIN_DATENBANKPASSWORT',
],

// Zeigt eine Ebene über den öffentlichen Ordner
'video_ordner'  => __DIR__ . '/../../videos-privat',

// Wohin die Anfragen aus dem Kontaktformular gehen
'kontakt_empfaenger' => 'taekwondo@tv-steinau.de',

// Absenderadresse der verschickten Mail. Sie muss zu der Domain gehören,
// von der aus verschickt wird – sonst stufen viele Postfächer die Nachricht
// als gefälscht ein und sie landet im Spam. Die Adresse des Absenders steht
// in Reply-To, Antworten gehen also an ihn.
'kontakt_absender'   => 'noreply@deine-domain.de',
```

Lege die Absenderadresse im Kundenmenü unter *E-Mail* als Postfach oder
Weiterleitung an – manche Hoster verschicken sonst nichts.

`kontakt_ablage` bleibt weg: Der Eintrag legt Nachrichten als Datei ab, statt
sie zu verschicken, und ist nur für die Testumgebung gedacht.

Der Pfad hängt davon ab, wie tief `backend/` liegt. Liegt die Website direkt in
`www/`, dann steht `backend/` in `www/backend/`, und `__DIR__ . '/../../videos-privat'`
zeigt richtig. Prüfen lässt sich das gleich in Schritt 9.

`config.php` wird von `backend/.htaccess` vor dem Ausliefern geschützt und
gehört nicht ins Repository – sie steht in `.gitignore`.

## 7. HTTPS erzwingen

Die mitgelieferte `.htaccess` im öffentlichen Ordner leitet bereits auf HTTPS
um. Sie enthält außerdem: keine Verzeichnisauflistung, keine Auslieferung von
`.md`- und `.sql`-Dateien, ein paar Sicherheits-Kopfzeilen und einen
Zwischenspeicher für Bilder und Videos.

Falls die Seite nach dem Hochladen in einer Endlosschleife hängt, ist das
Zertifikat aus Schritt 3 noch nicht aktiv. Dann in der `.htaccess` den Block
unter *1. HTTPS erzwingen* vorübergehend auskommentieren.

## 8. Suchmaschinensperre lösen

Für die Vorschau ist die Seite gesperrt: `robots.txt` verbietet alles, und jede
Seite trägt ein `noindex`. Bleibt das stehen, taucht die Seite bei Google nie
auf – und das fällt erst Monate später auf.

Vor dem Hochladen einmal lokal ausführen:

```bash
php werkzeuge/livegang.php --live
```

Das erledigt zwei Dinge auf einmal:

1. Es nimmt die Sperre aus allen Seiten und schreibt `robots.txt` neu. Die
   Seiten des Mitgliederbereichs behalten ihr `noindex` – die gehören nicht in
   die Suche.
2. Es biegt den Menüpunkt **„Mitglieder"** von `mitglieder.html` (der Attrappe)
   auf `backend/login.php` um, die echte Anmeldung. Bliebe der Entwurf
   verlinkt, landete jeder Besucher auf einer Anmeldung, die nichts schützt.

Rückgängig machen: `--entwurf`. Nachsehen, wie es gerade steht: `--status` –
das meldet auch, worauf der Menüpunkt gerade zeigt.

## 9. Erstes Trainerkonto einrichten

Es gibt **kein vorgegebenes Konto und kein vorgegebenes Passwort**. Ein
Konto mit festem Passwort im Quelltext wäre öffentlich bekannt – wer es
nach dem Livegang zu löschen vergisst, hat ein offenes Tor in die
Verwaltung. Stattdessen:

1. Einmal `deine-domain.de/backend/einrichten.php` aufrufen.
2. Namen eintragen und ein eigenes Passwort wählen, mindestens zwölf
   Zeichen. Den Benutzernamen kann man leer lassen – aus „Michael
   Buchhold" wird dann `m.buchhold`.
3. Fertig. Die Seite sperrt sich selbst: Solange dieses Konto steht,
   weist sie jeden weiteren Aufruf ab, auch ein nachgebautes Formular.
4. `backend/einrichten.php` vom Server löschen. Nötig ist es nicht, aber
   was nicht da ist, kann nicht schiefgehen.

Ab hier entstehen alle weiteren Zugänge in der Verwaltung.

### Zugänge für die Abteilung anlegen

Unter *Verwaltung → Zugänge* gibt es zwei Wege:

* **Einen einzelnen Zugang**, wenn im Training jemand dazukommt.
* **Mehrere Zugänge auf einmal** – dafür eine Liste von Namen in das Feld,
  eine Person je Zeile. Benutzername und Startpasswort entstehen
  automatisch; hinter einem Semikolon kann eine E-Mail-Adresse stehen.

Danach erscheint die **Zugangsliste**: alle Namen mit Benutzername und
Startpasswort. Drei Ausgaben, je nachdem was gebraucht wird:

| Ausgabe | Wofür |
| --- | --- |
| *Drucken* | Eine Seite für den Cheftrainer. Menü und Farben fallen weg. |
| *Als CSV speichern* | Für Excel oder LibreOffice, etwa als Vorlage für einen Serienbrief. |
| *Zum Ausschneiden* | Ein Zettel je Person – so bekommt niemand die Zugänge der anderen zu sehen. |

**Diese Liste gibt es genau einmal.** In der Datenbank steht nur der
verschlüsselte Abdruck; ein Startpasswort lässt sich später nicht mehr
auslesen, auch nicht vom Trainerteam. Wer eins verliert, bekommt unter
*Zugänge → Bearbeiten → Passwort neu setzen* ein neues – das landet dann
wieder in der Liste.

Damit muss niemand mehr zwanzig Passwörter einzeln abschreiben. Und die
Liste wird schnell wertlos: Jeder Zugang verlangt beim ersten Anmelden ein
eigenes Passwort. Danach kennt es nur noch das Mitglied selbst.

Die Liste gehört nicht in eine E-Mail und nicht in eine Chatgruppe –
Papier im Training ist hier der sicherere Weg.

## 10. Durchprüfen

Der Reihe nach im Browser aufrufen:

- [ ] `deine-domain.de` – lädt, springt von selbst auf `https://`
- [ ] Trainingszeiten, Angebot, Trainerteam, Galerie, Downloads, Kontakt
- [ ] Ein PDF aus dem Download-Bereich lässt sich öffnen
- [ ] Kontaktformular ausfüllen und abschicken – die Nachricht muss im
      Postfach der Abteilung landen. Kommt nichts an, stimmt meist die
      Absenderadresse nicht (Schritt 6)
- [ ] `deine-domain.de/backend/login.php` – Anmeldung geht
- [ ] Als Trainer: *Termine* – ein Termin ändern und speichern. Danach muss die
      Änderung auf `training.html` und der Startseite stehen. Kommt hier
      „nicht beschreibbar", fehlen die Schreibrechte (siehe unten).
- [ ] Als Trainer: *Videos* – eine Videodatei hochladen
- [ ] Als Mitglied: Video abspielen und darin vorspulen
- [ ] `deine-domain.de/backend/config.php` direkt aufrufen – muss einen Fehler
      geben, nicht den Inhalt zeigen
- [ ] `deine-domain.de/mitglieder.html` aufrufen – muss „nicht gefunden"
      melden. Erscheint dort eine Anmeldemaske mit sichtbaren Testzugängen,
      ist die Entwurfsfassung mit hochgeladen und die `.htaccess` fehlt
- [ ] `deine-domain.de/assets/video/taegeuk-il-jang.mp4` aufrufen – muss
      ebenfalls „nicht gefunden" melden
- [ ] Auf dem Telefon ansehen: Burger-Menü, Trainerkarten, Terminliste

## 11. Schreibrechte

Der Webserver muss in vier Stellen schreiben dürfen:

| Wohin | Wofür |
| --- | --- |
| `videos-privat/` | hochgeladene Videos |
| `assets/video/` | Vorschaubilder |
| `assets/js/trainingstermine.js` | Termine der Startseite |
| `training.html` | Terminliste |

Die letzten beiden sind ungewöhnlich: Die Terminverwaltung schreibt die Termine
direkt in die Website zurück, damit sie ohne Datenbank auskommt und auch bei
einem Serverausfall lesbar bleibt.

Meist genügt Rechtestufe 755 für Ordner und 644 für Dateien; bei manchen
Konfigurationen sind 775 und 664 nötig. Im FTP-Programm über *Dateirechte*
einstellbar.

---

## Wenn etwas nicht geht

| Symptom | Wahrscheinliche Ursache |
| --- | --- |
| Weiße Seite bei `/backend/…` | PHP-Version zu alt (Schritt 2) oder `config.php` fehlt |
| „Der Mitgliederbereich ist gerade nicht erreichbar" | Datenbankzugang in `config.php` falsch – meist der Host |
| Endlosschleife beim Aufruf | Zertifikat noch nicht aktiv (Schritt 3) |
| Termine speichern, aber die Website ändert sich nicht | Schreibrechte (Schritt 11) |
| Video lädt, lässt sich aber nicht spulen | Ausführungszeit im Hosting-Menü hochsetzen |
| Seite erscheint nicht bei Google | Schritt 8 vergessen; danach dauert es Wochen |
| Kontaktformular meldet einen Fehler | Absenderadresse gehört nicht zur Domain (Schritt 6) |
| Nachrichten kommen an, aber im Spam | dasselbe – Absenderadresse muss zur Domain passen |

Ausführlicher zum Mitgliederbereich: `backend/README.md`.

## Später etwas ändern

Für Textänderungen an der Website: Datei bearbeiten, per SFTP hochladen,
fertig. Es gibt keinen Bauschritt, die Seite ist reines HTML, CSS und
JavaScript.

Termine und Zugänge dagegen laufen über den Mitgliederbereich – dafür wird
kein FTP mehr gebraucht.

## Videos in den Mitgliederbereich

Es gibt zwei Wege, und beide führen nach `videos-privat/` auf dem Server.
Dort liegt der Ordner außerhalb des öffentlichen Bereichs, und nur
`backend/stream.php` reicht die Videos an angemeldete Mitglieder weiter –
daran ändert sich nichts dadurch, dass die Rohaufnahmen im Repository
liegen. Was dort öffentlich sichtbar ist und was dazugehört, steht in
`videos-roh/LIESMICH.md`.

**Der normale Weg – über den Mitgliederbereich.** Als Trainer anmelden,
*Videos*, Datei auswählen, Titel und Platz in der Reihe eintragen. PHP legt
die Datei selbst in `videos-privat/` ab. Für einzelne Videos ist das der
bequemste Weg; Grenze ist die Upload-Größe des Hostings (Schritt 2).

**Der Weg für eine ganze Reihe.** Wenn zehn oder mehr Aufnahmen vom Handy
kommen, lohnt das Aufbereiten auf dem eigenen Rechner: Handyvideos sind
HEVC in 10 Bit, das spielen viele Browser nicht ab.

1. Aufnahmen nach `assets/video/` legen.
2. `videos-roh/reihenfolge.txt` ausfüllen – eine Datei je Zeile, in der
   Reihenfolge, in der sie im Mitgliederbereich stehen sollen.
3. ```
   werkzeuge/video-aufbereiten.sh --liste videos-roh/reihenfolge.txt \
       assets/video hanbon-kyorugi
   php werkzeuge/videodaten-erzeugen.php
   ```
4. Die aufbereiteten `hanbon-kyorugi-*.mp4` und `*.webm` per SFTP nach
   `videos-privat/` auf dem Webspace legen, die Vorschaubilder `*.jpg`
   nach `www/assets/video/`. Die Rohaufnahmen (`IMG_*.mov`) bleiben auf
   dem eigenen Rechner – der Server braucht sie nicht. Wer mit dem Paket
   aus Schritt 5 arbeitet, hat das schon getan.
5. Anmelden, *Verwaltung → Videos*. Oben steht dann: *„13 Videodateien
   liegen schon im Ordner und sind noch nicht eingetragen."* Titel,
   Bereich und Platz in der Reihe sind vorausgefüllt, sofern
   `reihe.csv` neben den Videos liegt – die legt `paket.sh` mit an. Ein
   Klick auf *Ausgewählte eintragen*, und die Reihe steht in der
   Videothek.

   Ohne diese Liste müsste jedes Video einzeln durch den Browser
   hochgeladen und beschrieben werden. Bei dreizehn Teilen einer Reihe
   ist der Weg über SFTP schneller.

War ein einzelnes Video falsch, muss nicht alles neu laufen:

```
werkzeuge/video-aufbereiten.sh --platz 4 assets/video hanbon-kyorugi \
    assets/video/IMG_1299.mov
```

Ersetzt nur Nr. 4. Danach wieder `php werkzeuge/videodaten-erzeugen.php`.

## Schutz gegen versehentliches Einchecken

Im Repository liegt ein Git-Haken, der die Serverzugänge und den
Zwischenstand der Videoablage vom Commit abhält. Er muss einmal je
Arbeitsplatz eingeschaltet werden:

```
git config core.hooksPath .githooks
```

Danach bricht `git commit` ab, sobald `backend/config.php` oder etwas aus
`videos-privat/` im Commit steckt – auch nach `git add -f`. Wer es
wirklich will, kommt mit `git commit --no-verify` durch.

`config.php` enthält die Datenbank-Zugangsdaten des Servers. Und
`videos-privat/` ist nur der Zwischenstand für den SFTP-Upload: Die
Videos der Website liegen in `assets/video/`, ein zweites Mal eingecheckt
würde jede Datei doppelt im Repository stehen.
