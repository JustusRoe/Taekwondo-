# Testumgebung

Ein Befehl startet alles: Website, Mitgliederbereich (Entwurf **und** Serverfassung),
Testdatenbank und Videoablage. Danach prüft das Skript selbst, ob alles läuft.

```bash
./test/testmain.sh
```

Unter Windows ohne WSL oder Git Bash stattdessen:

```
test\testmain.bat
```

## Auf dem Mac

macOS bringt seit Version 12 (Monterey) kein PHP mehr mit. Einmalig einrichten:

```bash
php -v                       # kommt "command not found", dann weiter mit:
brew install php             # setzt Homebrew voraus – https://brew.sh
```

Danach im Ordner des Projekts:

```bash
./test/testmain.sh
```

Das Skript prüft PHP-Version und Erweiterungen selbst und sagt, wenn etwas fehlt.
Beendet wird mit `Strg+C`. Bleibt einmal ein Server hängen: `./test/testmain.sh --stop`.

## Testzugänge

| Benutzername | Passwort | Rolle | Sieht |
| --- | --- | --- | --- |
| `testuser` | `test1234` | Mitglied | Videothek und Player |
| `testtrainer` | `test1234` | Trainer | zusätzlich Videos hochladen und Zugänge verwalten |

Beide Konten gelten in der Serverfassung **und** im Entwurf. In der Datenbank liegt nur
der Hash des Passworts – erzeugt beim Einrichten, nicht fest eingetragen.

**Wichtig:** Die Spalte „Sieht" gilt für die **Serverfassung** unter `backend/`. Der
Entwurf (`mitglieder*.html`) kennt keine Rollen und hat keinen Verwaltungsbereich – dort
sehen beide Konten dasselbe. Die Verwaltung gibt es nur unter
`http://localhost:8080/backend/login.php`.

## Was danach im Browser erreichbar ist

| Adresse | Inhalt |
| --- | --- |
| `http://localhost:8080/index.html` | Die öffentliche Website |
| `http://localhost:8080/mitglieder.html` | Mitgliederbereich als Entwurf (Anmeldung nur im Browser) |
| `http://localhost:8080/backend/login.php` | Mitgliederbereich echt (Datenbank, geschützte Videos) |

## Optionen

| Aufruf | Wirkung |
| --- | --- |
| `./test/testmain.sh` | Einrichten, starten, prüfen |
| `./test/testmain.sh --neu` | Testdatenbank vorher löschen und neu aufbauen |
| `./test/testmain.sh --port 9000` | Anderer Port, falls 8080 belegt ist |
| `./test/testmain.sh --nur-pruefen` | Nur die Prüfungen gegen einen laufenden Server |
| `./test/testmain.sh --stop` | Einen hängengebliebenen Testserver beenden |
| `php test/check.php http://localhost:8080` | Prüfungen einzeln aufrufen |

## Die Dateien

| Datei | Aufgabe |
| --- | --- |
| `testmain.sh` / `testmain.bat` | Startskript: Voraussetzungen, Einrichtung, Server, Prüfung |
| `setup.php` | Legt SQLite-Datenbank, Testkonten, Videoablage und `backend/config.php` an |
| `router.php` | Statischer Auslieferer **mit** Range-Unterstützung für den PHP-Server |
| `check.php` | 29 Prüfungen gegen den laufenden Server |
| `daten/server.pid` | Kennung des laufenden Servers, für `--stop` |
| `daten/` | Testdatenbank und Serverprotokoll (nicht im Repository) |
| `videos-privat/` | Kopie der Videos außerhalb des Web-Ordners (nicht im Repository) |

## Warum SQLite statt MySQL

Damit kein Datenbankserver installiert werden muss. Die Tabellen entsprechen
`backend/schema.sql`, der Anwendungscode ist identisch – er spricht über PDO mit beiden.
Für den Echtbetrieb beim Hoster gilt `backend/schema.sql` (MySQL) und eine eigene
`config.php` nach dem Muster von `backend/config.example.php`.

## Warum ein eigener Router

Der eingebaute PHP-Server beantwortet für statische Dateien keine Range-Anfragen. Ohne
`router.php` ließe sich in den Videos des Entwurfs deshalb nicht spulen – ein Problem der
Testumgebung, nicht der Seite. `router.php` verhält sich wie Apache oder Nginx später.

## Was geprüft wird

`check.php` durchläuft unter anderem:

- Startseite, Download-PDF und Anmeldeseiten erreichbar
- Spulen im Video über Range-Anfragen (`206 Partial Content`)
- Videothek und `stream.php` ohne Anmeldung gesperrt (Umleitung)
- Falsches Passwort abgewiesen, richtiges akzeptiert
- Videothek zeigt sechs Videos, Videoseite zeigt den Player
- Geschütztes Video wird ausgeliefert und lässt sich spulen
- Ausweichformat WebM, unbekanntes Format abgewiesen
- Pfadmanipulation (`?v=../../etc/passwd`) abgewiesen
- Verwaltung, Zugänge und Hochladen für Mitglieder gesperrt, für Trainer erreichbar
- Upload lässt sich anmelden; Teilstücke in falscher Reihenfolge werden abgewiesen
- Hochladen ohne CSRF-Token abgewiesen
- Nach dem Abmelden wieder gesperrt

## Aufräumen

```bash
rm -rf test/daten test/videos-privat backend/config.php
```

Alle drei werden beim nächsten Start neu angelegt.
