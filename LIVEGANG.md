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

Danach `backend/schema.sql` einspielen: In der Datenbankübersicht phpMyAdmin
öffnen, links die Datenbank wählen, Reiter *Importieren*, Datei auswählen,
ausführen. Danach stehen die Tabellen `mitglieder`, `videos`,
`trainingstermine` und `login_versuche` bereit, dazu ein erstes Trainerkonto.

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

**Das kommt in `www/`:** alle `.html`-Dateien, `.htaccess`, `robots.txt`,
`.nojekyll`, sowie die Ordner `assets/`, `downloads/` und `backend/`.

**Das bleibt auf deinem Rechner:** `test/`, `werkzeuge/`, `README.md`,
`LIVEGANG.md`, `CHECKLISTE-INHALTE.md`, `.git/`. Das sind Entwicklungs- und
Pflegewerkzeuge, die auf dem Server nichts verloren haben.

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

Das nimmt die Sperre aus allen Seiten und schreibt `robots.txt` neu. Die Seiten
des Mitgliederbereichs behalten ihr `noindex` – die gehören nicht in die Suche.
Rückgängig machen: `--entwurf`. Nachsehen, wie es gerade steht: `--status`.

## 9. Erstes Trainerkonto einrichten

`schema.sql` legt genau ein Konto an: **`testtrainer`** mit dem Passwort
**`test1234`**. Das steht so im Quelltext und ist damit öffentlich bekannt.

Direkt nach dem Livegang, in dieser Reihenfolge:

1. Unter `deine-domain.de/backend/login.php` mit `testtrainer` / `test1234`
   anmelden. Die Seite verlangt sofort ein eigenes Passwort – setz eins mit
   mindestens zwölf Zeichen.
2. Unter *Verwaltung → Zugänge* die echten Trainerkonten anlegen. Für jedes
   Trainerkonto wird dabei dein eigenes Passwort abgefragt.
3. Mit einem der neuen Konten anmelden und prüfen, dass es in die Verwaltung
   kommt.
4. Erst dann `testtrainer` stilllegen und löschen.

Solange `testtrainer` mit dem bekannten Passwort existiert, hat jeder Zugang
zur Verwaltung, der den Quelltext gelesen hat.

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
