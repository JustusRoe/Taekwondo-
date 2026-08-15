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
              ├── login.php  videothek.php  video.php  stream.php  admin.php
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
4. **Videos per FTP** in `videos-privat/` laden, Vorschaubilder nach `assets/video/`.
5. **Passwörter setzen.** Die Testkonten aus `schema.sql` (`testuser` und `testtrainer`)
   haben beide das Passwort `test1234` – vor dem Echtbetrieb löschen oder ersetzen:
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
| `schema.sql` | Tabellen `mitglieder`, `videos`, `kapitel`, `login_versuche` samt Beispieldaten |
| `config.example.php` | Vorlage für Zugangsdaten und Pfade |
| `lib/db.php` | Datenbankverbindung, Hilfsfunktionen |
| `lib/auth.php` | Anmeldung, Sitzung, Sperre nach Fehlversuchen, CSRF-Token |
| `lib/seite.php` | Gemeinsamer Seitenrahmen (nutzt dieselben Stylesheets wie die Website) |
| `login.php` / `logout.php` | An- und Abmeldung |
| `videothek.php` | Übersicht mit Filter und Suche |
| `video.php` | Player mit Abschnitten, Spulen und Tempo |
| `stream.php` | **Geschützte Auslieferung der Videodatei mit Range-Unterstützung** |
| `admin.php` | Kleine Verwaltung für das Trainerteam (nur Rolle `trainer`) |

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
- HTTP-Range-Requests (Teilanfragen) – dadurch funktionieren Spulen und Kapitelsprünge,
  ohne dass das Video vorher komplett geladen wird.
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
- **Upload-Grenze:** Videos gehören per FTP auf den Server, nicht über ein Formular –
  PHP-Uploads sind bei den meisten Paketen auf 64–128 MB begrenzt.

## Ausprobieren ohne Hoster

`./test/testmain.sh` richtet eine vollständige lokale Umgebung ein – SQLite statt MySQL,
Testkonten, Videoablage und Konfiguration – startet den Server und prüft alles durch.
Einzelheiten in `test/README.md`.

## Vor dem Freischalten

- Testkonten (`testuser`, `testtrainer`) löschen oder mit neuen Passwörtern versehen.
- Einwilligungen der gefilmten Personen einholen; bei Minderjährigen von den
  Erziehungsberechtigten.
- Datenschutzhinweise um die Mitgliederverwaltung ergänzen und einen
  Auftragsverarbeitungsvertrag mit dem Hoster abschließen.
