# Rohaufnahmen – hier liegen die Handyvideos

Dieser Ordner ist der Ablageplatz für die **unbearbeiteten** Aufnahmen
(`IMG_1255.mov` und so weiter), bevor sie für den Mitgliederbereich
aufbereitet werden. Die Aufnahmen selbst gehören **nicht** ins
Repository – siehe „Warum nicht ins GitHub“ weiter unten. `.gitignore`
sorgt dafür, dass sie versehentlich auch nicht mitgehen.

## Ablauf

1. Aufnahmen in diesen Ordner legen.
2. `reihenfolge.txt` ausfüllen: **eine Datei je Zeile, in der Reihenfolge,
   in der die Videos im Mitgliederbereich stehen sollen.** Zeile 1 wird
   Einschrittkampf 1, Zeile 2 wird Einschrittkampf 2 und so weiter.
   Leere Zeilen und alles hinter `#` wird übersprungen.
3. Aufbereiten:

   ```
   werkzeuge/video-aufbereiten.sh --liste videos-roh/reihenfolge.txt \
       videos-privat hanbon-kyorugi
   php werkzeuge/videodaten-erzeugen.php
   ```

4. Nachsehen: `bash test/testmain.sh`, dann im Mitgliederbereich prüfen,
   ob die Reihenfolge stimmt.

Die Reihenfolge steht damit in einer Textdatei und nicht in den
Dateinamen. Das ist der Punkt: Dateinamen der Kamera (`IMG_1263`) sagen
nur, wann aufgenommen wurde – nicht, welche Technik das ist.

## Ein einzelnes Video war falsch

Dann muss nicht die ganze Reihe neu laufen:

```
werkzeuge/video-aufbereiten.sh --platz 4 videos-privat hanbon-kyorugi \
    videos-roh/IMG_1299.mov
php werkzeuge/videodaten-erzeugen.php
```

Ersetzt nur Nr. 4, alle anderen bleiben, wie sie sind. `reihenfolge.txt`
danach bitte auch auf den neuen Namen ändern, damit die Liste weiter
zum Ergebnis passt.

## Warum nicht ins GitHub

Das Repository `JustusRoe/Taekwondo-` ist **öffentlich**, und GitHub
Pages ist eingeschaltet. Alles, was hier eingecheckt wird, kann jeder
herunterladen – auch Jahre später noch aus der Versionsgeschichte, selbst
wenn die Datei später gelöscht wird. Auf den Videos sind Mitglieder zu
erkennen, teils Kinder. Genau davor schützt der Mitgliederbereich mit
`backend/stream.php`; ein Upload ins öffentliche Repository würde diesen
Schutz umgehen.

Zwei Wege, die das nicht tun:

* **Über den Server (der normale Weg).** Die aufbereiteten Dateien aus
  `videos-privat/` per SFTP in den Ordner `videos-privat/` auf dem
  IONOS-Webspace legen – dort liegt er außerhalb von `/`, also nicht im
  Web erreichbar. Nur `backend/stream.php` reicht sie an angemeldete
  Mitglieder weiter. Schritt für Schritt steht das in `LIVEGANG.md`.
* **Zum Weitergeben an die Entwicklung.** Ein zweites, **privates**
  Repository (zum Beispiel `Taekwondo-videos`) oder ein geteilter Ordner
  in einer Cloud. Privat heißt bei GitHub: nur eingeladene Konten
  kommen ran.

Wer die Rohaufnahmen doch im öffentlichen Repository haben will, muss
das bewusst tun (`git add -f`) – und vorher die Einwilligungen der
gezeigten Personen für eine öffentliche Veröffentlichung vorliegen
haben. Die Einwilligung für den Mitgliederbereich deckt das nicht ab.
