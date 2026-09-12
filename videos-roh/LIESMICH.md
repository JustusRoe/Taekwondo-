# Videoreihen aufbereiten

Die Rohaufnahmen der Einschrittkampf-Reihe liegen im Repository, in
`assets/video/` (`IMG_1255.mov` und so weiter). Dieser Ordner hier
enthält nur noch `reihenfolge.txt` – die Liste, die festlegt, welche
Aufnahme welche Nummer im Mitgliederbereich bekommt. Aufnahmen, die man
hier zwischenlagert, hält `.gitignore` aus dem Repository heraus, damit
nichts doppelt darin landet.

## Ablauf

1. Neue Aufnahmen nach `assets/video/` legen.
2. `reihenfolge.txt` ergänzen: **eine Datei je Zeile, in der Reihenfolge,
   in der die Videos im Mitgliederbereich stehen sollen.** Zeile 1 wird
   Einschrittkampf 1, Zeile 2 wird Einschrittkampf 2 und so weiter.
   Leere Zeilen und alles hinter `#` wird übersprungen; relative Pfade
   gelten ab diesem Ordner, deshalb steht dort `../assets/video/…`.
3. Aufbereiten:

   ```
   werkzeuge/video-aufbereiten.sh --liste videos-roh/reihenfolge.txt \
       assets/video hanbon-kyorugi
   php werkzeuge/videodaten-erzeugen.php
   ```

4. Nachsehen: `bash test/testmain.sh`, dann im Mitgliederbereich prüfen,
   ob die Reihenfolge stimmt.

Die Reihenfolge steht damit in einer Textdatei und nicht in den
Dateinamen. Das ist der Punkt: Ein Dateiname der Kamera sagt nur, wann
aufgenommen wurde – nicht, welche Technik zu sehen ist.

Auf die Zeitstempel in den Dateien ist dabei kein Verlass. Bei den
Aufnahmen dieser Reihe steht in den klein geschriebenen `.mov` als
`creation_time` der Zeitpunkt des Exports – gleich mehrere Dateien
tragen dieselbe Sekunde (11:39:20, 11:50:29, 12:02:18). Nur die groß
geschriebenen `.MOV` haben ihre echte Aufnahmezeit behalten, und die
steigt mit der Dateinummer. Deshalb ist die Nummer der Maßstab.

## Ein einzelnes Video war falsch

Dann muss nicht die ganze Reihe neu laufen:

```
werkzeuge/video-aufbereiten.sh --platz 4 assets/video hanbon-kyorugi \
    assets/video/IMG_1299.mov
php werkzeuge/videodaten-erzeugen.php
```

Ersetzt nur Nr. 4, alle anderen bleiben, wie sie sind. `reihenfolge.txt`
danach bitte auch auf den neuen Namen ändern, damit die Liste weiter zum
Ergebnis passt.

Dass der Austausch aufgefallen ist, merkt sich das Skript in
`assets/video/hanbon-kyorugi-herkunft.txt`. Steckt hinter einer Nummer
später eine andere Aufnahme, setzt `videodaten-erzeugen.php` den von Hand
eingetragenen Techniknamen zurück und meldet es – ein Name am falschen
Video wäre schlimmer als einer, der neu eingetragen werden muss.

## Was das Repository öffentlich zeigt

`JustusRoe/Taekwondo-` ist **öffentlich**, und GitHub Pages ist
eingeschaltet. Die Aufnahmen sind damit für jeden abrufbar, der die
Adresse kennt – sowohl über
`justusroe.github.io/Taekwondo-/assets/video/…` als auch über
`raw.githubusercontent.com`. Auch nach einem späteren Löschen bleiben sie
über den Commit-Hash erreichbar.

Das ist eine bewusste Entscheidung (Stand 12.09.2026) und nicht aus
Versehen so. Zwei Dinge gehören trotzdem dazu:

* **Einwilligungen.** Für jede erkennbare Person eine Einwilligung zur
  öffentlichen Veröffentlichung, bei Minderjährigen von den
  Erziehungsberechtigten. Die Einwilligung für einen geschlossenen
  Mitgliederbereich deckt eine öffentliche Veröffentlichung nicht ab.
* **Auf dem Livebetrieb bleibt es geschützt.** Die Website selbst liefert
  die Videos weiter nur nach Anmeldung aus: Sie liegen auf dem Server in
  `videos-privat/` außerhalb des öffentlichen Ordners, und
  `backend/stream.php` reicht sie erst nach geprüfter Sitzung weiter. Die
  `.htaccess` sperrt `assets/video/` auf dem Server zusätzlich. Das
  öffentliche Repository ändert daran nichts – wer die Reichweite
  zurücknehmen will, muss beim Repository ansetzen, nicht an der Website.

Soll das zurückgedreht werden: Commit `a9dcd7c` aus der Historie
streichen, mit `--force` pushen und den GitHub-Support bitten, die
unerreichbaren Objekte einzusammeln. Alternativ das Repository privat
schalten – dann entfällt allerdings die Pages-Vorschau für den Vorstand.
