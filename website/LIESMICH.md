# Der Ordner website/

Hier liegt genau das, was auf den Server gehört – keine Tests, keine
Hilfsskripte, keine Rohaufnahmen. Erzeugt von `werkzeuge/paket.sh`.

**Nichts hier von Hand ändern.** Änderungen gehören in den Projektordner
eine Ebene höher; danach `werkzeuge/paket.sh` laufen lassen, und dieser
Ordner entsteht neu. Ob er noch zu den Quelldateien passt, sagt
`php test/check.php` – die Prüfsumme dafür steht in `STAND.txt`.

## Wohin was kommt

| Ordner im Paket | Wohin auf dem Server |
| --- | --- |
| `www/` | in den Webordner des Hostings – bei IONOS heißt er meist `/` oder `htdocs`. **Der Inhalt**, nicht der Ordner selbst. |
| `videos-privat/` | eine Ebene **über** dem Webordner. Nicht in `www/`. |
| `datenbank/schema.sql` | nicht hochladen: in phpMyAdmin unter *Importieren* einspielen. |

So sieht es danach auf dem Server aus:

```
/
├── videos-privat/           ← hierher der Inhalt von videos-privat/
└── www/                     ← hierher der Inhalt von www/
    ├── index.html
    ├── assets/
    ├── downloads/
    └── backend/
```

Dass `videos-privat/` außerhalb liegt, ist der Kern des
Mitgliederbereichs: Von dort kann niemand eine Videodatei direkt
herunterladen. `backend/stream.php` liest sie und gibt sie erst nach
geprüfter Anmeldung heraus.

## Die vier Schritte nach dem Hochladen

1. **`backend/config.php` anlegen.** Im Paket liegt
   `www/backend/config.example.php` als Vorlage. Kopieren, in
   `config.php` umbenennen und die Zugangsdaten der Datenbank
   eintragen. Diese Datei gibt es nur auf dem Server.
2. **Datenbank einspielen.** `datenbank/schema.sql` in phpMyAdmin
   importieren. Danach stehen die Tabellen, aber noch kein Zugang.
3. **Erstes Trainerkonto anlegen.** Einmal
   `https://deine-domain.de/backend/einrichten.php` aufrufen. Die Seite
   legt das erste Konto an, mit einem Passwort, das du selbst wählst,
   und sperrt sich danach selbst. Es gibt kein vorgegebenes Passwort und
   keinen Testzugang.
4. **Schreibrechte prüfen.** Der Webserver muss schreiben dürfen in
   `videos-privat/`, `www/assets/video/`,
   `www/assets/js/trainingstermine.js` und `www/training.html`. Die
   letzten beiden sind Absicht: Die Terminverwaltung schreibt die
   Termine in die Website zurück, damit die Seite ohne Datenbank
   auskommt.

Ausführlich, mit allem rundherum: `LIVEGANG.md` im Projektordner.

## Weitere Zugänge

Nach dem Anmelden unter *Verwaltung → Zugänge*. Für viele auf einmal
gibt es dort „Mehrere Zugänge auf einmal anlegen": eine Liste von Namen
hinein, und heraus kommt eine **Zugangsliste** mit Benutzername und
Startpasswort – zum Ausdrucken, als CSV-Datei oder als Zettel je Person
zum Ausschneiden. Diese Liste gibt es genau einmal: Gespeichert wird nur
der verschlüsselte Abdruck, ein Startpasswort lässt sich später nicht
mehr auslesen.
