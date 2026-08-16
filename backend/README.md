# Mitgliederbereich – Serverfassung

Diese Dateien sind die echte Umsetzung des Mitgliederbereichs: Anmeldung mit
Benutzername und Passwort, Videothek aus der Datenbank und geschützte Auslieferung der
Videodateien. Die HTML-Dateien im Hauptordner (`mitglieder*.html`) sind demgegenüber nur
der **Entwurf zum Anschauen** – sie schützen nichts.

## Warum ein Server nötig ist

Ein Login, das nur im Browser läuft, ist keiner: Wer die Adresse einer Videodatei kennt,
lädt sie auch ohne Anmeldung herunter. Deshalb liegen die Videos außerhalb des
öffentlichen Ordners, und `stream.php` gibt sie erst heraus, nachdem die Sitzung geprüft
wurde.

## Ordner auf dem Server

```
/kunden/12345/                    ← Wurzel des Hosting-Kontos
  ├── videos-privat/              ← MP4-Dateien, NICHT öffentlich erreichbar
  │     ├── poomsae-taegeuk-il-jang.mp4
  │     └── …
  └── www/                        ← öffentlicher Ordner
        ├── index.html            ← die Website
        ├── assets/…
        └── backend/              ← dieser Ordner
              ├── config.php      ← selbst anlegen, nicht ins Repository
              ├── login.php  videothek.php  video.php  stream.php
              ├── admin.php  konten.php  upload.php
              └── lib/
```

Entscheidend ist, dass `videos-privat/` **eine Ebene über** dem öffentlichen Ordner
liegt. Bei IONOS heißt der öffentliche Ordner meist `/` oder `/www` – im FTP-Programm
sieht man die Ebene darüber.

## Einrichtung in sechs Schritten

1. **Datenbank anlegen** (IONOS: Hosting → Datenbanken → MySQL-Datenbank erstellen).
   Host, Datenbankname, Benutzer und Passwort notieren.
2. **`schema.sql` einspielen** – über phpMyAdmin („Importieren") oder auf der Konsole:
   `mysql -h HOST -u BENUTZER -p DATENBANK < schema.sql`
3. **`config.example.php` nach `config.php` kopieren** und die Zugangsdaten sowie den
   Pfad zu `videos-privat/` eintragen.
4. **Schreibrechte prüfen.** Der Webserver muss in `videos-privat/` und in
   `assets/video/` schreiben dürfen – dort landen die hochgeladenen Videos und die
   Vorschaubilder. Meist genügt Rechte-Stufe 755; bei manchen Hostern 775.
5. **Erstes Passwort setzen.** `schema.sql` legt ein einziges Trainerkonto an
   (`testtrainer`, Passwort `test1234`). Damit meldet man sich einmal an und legt unter
   *Verwaltung → Zugänge* die echten Konten an – danach dieses Konto löschen. Alternativ
   direkt in der Datenbank:
   ```
   php -r "echo password_hash('NEUES_PASSWORT', PASSWORD_DEFAULT);"
   ```
   Den ausgegebenen Wert in die Spalte `passwort_hash` eintragen.
6. **HTTPS erzwingen.** Ohne Verschlüsselung wandern Passwörter im Klartext durchs Netz.
   Bei IONOS ist ein Zertifikat enthalten; im Kundenmenü aktivieren und in der
   `.htaccess` des öffentlichen Ordners auf HTTPS umleiten.

## Dateien

| Datei | Aufgabe |
| --- | --- |
| `schema.sql` | Tabellen `mitglieder`, `videos`, `login_versuche` und das erste Trainerkonto |
| `config.example.php` | Vorlage für Zugangsdaten und Pfade |
| `lib/db.php` | Datenbankverbindung, Hilfsfunktionen |
| `lib/auth.php` | Anmeldung, Sitzung, Sperre nach Fehlversuchen, CSRF-Token |
| `lib/seite.php` | Gemeinsamer Seitenrahmen (nutzt dieselben Stylesheets wie die Website) |
| `login.php` / `logout.php` | An- und Abmeldung |
| `videothek.php` | Übersicht mit Filter und Suche |
| `video.php` | Player mit Spulen, Tempo und Weiterschauen |
| `stream.php` | **Geschützte Auslieferung der Videodatei mit Range-Unterstützung** |
| `admin.php` | Verwaltung: Videos hochladen und löschen (nur Rolle `trainer`) |
| `konten.php` | Verwaltung: Zugänge anlegen, ändern, Passwort setzen (nur `trainer`) |
| `upload.php` | Nimmt Videodateien stückweise entgegen; antwortet mit JSON |
| `lib/verwaltung.php` | Passwortvorschläge, Prüfungen, Aussperrschutz |

## Was eingebaut ist

- Passwörter als `password_hash()` (bcrypt), niemals im Klartext; automatische
  Aktualisierung des Verfahrens beim nächsten Login.
- Sperre nach fünf Fehlversuchen für 15 Minuten, gezählt je Benutzername.
- Gleich lange Antwortzeit für unbekannte Benutzer, damit sich Konten nicht erraten lassen.
- Sitzungscookie mit `HttpOnly`, `SameSite=Lax` und `Secure` bei HTTPS;
  neue Sitzungskennung nach dem Login.
- CSRF-Token in allen Formularen.
- `stream.php` prüft die Anmeldung, lässt nur bekannte Kürzel zu und verhindert über
  `basename()` Ausbrüche aus dem Videoordner.
- HTTP-Range-Requests (Teilanfragen) – dadurch funktioniert das Spulen, ohne dass das
  Video vorher komplett geladen wird.
- Optionales Ausweichformat über `stream.php?v=…&f=webm`: Liegt neben der MP4-Datei eine
  gleichnamige WebM-Fassung, bietet `video.php` sie als zweite Quelle an – hilfreich für
  Browser ohne H.264. Erlaubt sind ausschließlich `mp4` und `webm`.

## Bekannte Fallstricke

- **Der eingebaute PHP-Server (`php -S`) unterstützt keine Range-Requests für statische
  Dateien.** Beim lokalen Ausprobieren lässt sich in direkt eingebundenen Videos deshalb
  nicht spulen. Über `stream.php` funktioniert es trotzdem, weil das Skript die
  Teilanfragen selbst beantwortet. Auf echten Servern (Apache, Nginx, IONOS) ist beides
  kein Problem.
- **Große Dateien und `max_execution_time`:** Bei sehr langen Videos die Ausführungszeit
  im Hosting-Menü hochsetzen. `stream.php` ruft dafür `set_time_limit(0)` auf, was manche
  Hoster jedoch ignorieren.
- **Upload-Grenzen sind umgangen, nicht aufgehoben.** `upload.php` nimmt die Datei in
  Stücken von zwei Megabyte entgegen, deshalb spielen `upload_max_filesize`,
  `post_max_size` und `max_execution_time` keine Rolle mehr – jede einzelne Anfrage ist
  klein. Die Obergrenze steht stattdessen in `config.php` unter `max_video_mb`.
- **Abgebrochene Uploads** hinterlassen eine `.part`-Datei in `videos-privat/.uploads/`.
  Sie wird beim nächsten Upload entfernt, sobald sie älter als einen Tag ist; ein Cronjob
  ist nicht nötig.

## Ausprobieren ohne Hoster

`./test/testmain.sh` richtet eine vollständige lokale Umgebung ein – SQLite statt MySQL,
Testkonten, Videoablage und Konfiguration – startet den Server und prüft alles durch.
Einzelheiten in `test/README.md`.

## Zugänge und Videos verwalten

Beides läuft im Browser, FTP wird nicht mehr gebraucht.

**Zugänge** (`konten.php`): Es gibt bewusst keine Selbstregistrierung. Das Trainerteam
legt jedes Konto an und gibt Benutzername und Passwort im Training weiter. Die Seite
schlägt ein Passwort vor, das sich diktieren lässt (`Gipfel-Delfin-455`), und zeigt es
**genau einmal** an – gespeichert ist nur der Hash. Vergessene Passwörter werden neu
gesetzt, nicht ausgelesen. Das letzte aktive Trainerkonto lässt sich weder löschen noch
abstufen noch stilllegen, damit sich niemand aussperrt.

**Videos** (`admin.php`): Datei auswählen, Angaben ergänzen, speichern. Drei Felder
entfallen dabei, weil der Browser sie selbst ermitteln kann:

| Feld | Woher es kommt |
| --- | --- |
| Trainer/in | das angemeldete Konto |
| Länge | aus der Videodatei gelesen; klappt das nicht, erscheint ein Eingabefeld |
| Vorschaubild | Standbild aus dem ersten Drittel des Videos |

Die Videodatei bekommt beim Speichern den Namen des Kürzels – nie den Namen, unter dem
sie hochgeladen wurde. Damit kann keine Eingabe aus dem Videoordner herausführen.

## Vor dem Freischalten

- Das mitgelieferte Trainerkonto (`testtrainer`) löschen, sobald echte Konten stehen.
- Einwilligungen der gefilmten Personen einholen; bei Minderjährigen von den
  Erziehungsberechtigten.
- Datenschutzhinweise um die Mitgliederverwaltung ergänzen und einen
  Auftragsverarbeitungsvertrag mit dem Hoster abschließen.
