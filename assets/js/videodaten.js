/* =========================================================
   Beispieldaten der Videothek
   -----------------------------------------------------------
   Der Inhalt der eckigen Klammern ist bewusst gültiges JSON.
   So lesen sowohl der Browser als auch das Testskript
   (test/setup.php) dieselbe Quelle – die Daten stehen nur an
   einer Stelle. In der späteren Fassung liefert die Datenbank
   dieselben Felder (siehe backend/schema.sql).

   Die Poomsae-Videos sind noch Platzhalter. Der Einschrittkampf
   steht zwischen den Markierungen REIHE:ANFANG und REIHE:ENDE und
   wird von werkzeuge/videodaten-erzeugen.php geschrieben.
   ========================================================= */
window.VIDEOTHEK = [
  {
    "slug": "kibon-poomsae",
    "titel": "Kibon Poomsae – die Grundform",
    "bereich": "Poomsae",
    "grad": "ab 9. Kup",
    "trainer": "Michael Buchhold",
    "datum": "2026-08-01",
    "dauer": 42,
    "beschreibung": "Die Grundform als Einstieg in den Formenlauf. Zuerst die Stellung, dann der komplette Ablauf, zum Schluss die Fehler, die im Training am häufigsten auffallen."
  },
  {
    "slug": "taegeuk-il-jang",
    "titel": "Taegeuk Il Jang",
    "bereich": "Poomsae",
    "grad": "ab 8. Kup",
    "trainer": "Michael Buchhold",
    "datum": "2026-07-25",
    "dauer": 62,
    "beschreibung": "Die erste Form der Taegeuk-Reihe in ruhigem Tempo. Zuerst der komplette Ablauf, danach die beiden Sequenzen einzeln mit den häufigsten Korrekturen: Stand zu kurz, Hüfte nicht mitgedreht, Blick zu früh."
  },
  {
    "slug": "taegeuk-i-jang",
    "titel": "Taegeuk I Jang",
    "bereich": "Poomsae",
    "grad": "ab 7. Kup",
    "trainer": "Michael Buchhold",
    "datum": "2026-07-18",
    "dauer": 46,
    "beschreibung": "Die zweite Form der Taegeuk-Reihe. Neu gegenüber Il Jang sind die Fußtechniken im Ablauf – achte auf das Anheben des Knies vor jedem Tritt."
  },
  {
    "slug": "taegeuk-sam-jang",
    "titel": "Taegeuk Sam Jang",
    "bereich": "Poomsae",
    "grad": "ab 6. Kup",
    "trainer": "Michael Buchhold",
    "datum": "2026-07-11",
    "dauer": 58,
    "beschreibung": "Die dritte Form mit doppelten Handtechniken und schnellerem Wechsel der Richtung. Der Ablauf wird zunächst langsam gezeigt, dann im Prüfungstempo."
  },
  /* REIHE:ANFANG – Einschrittkampf, geschrieben von
     werkzeuge/videodaten-erzeugen.php, nicht von Hand ändern.
     Titel und Beschreibungen bleiben bei einem Lauf erhalten. */
  {
    "slug": "hanbon-kyorugi-01",
    "titel": "Einschrittkampf 1",
    "bereich": "Hanbon Kyorugi",
    "grad": "Alle Grade",
    "trainer": "",
    "datum": "2026-09-12",
    "dauer": 10,
    "reihenfolge": 1,
    "herkunft": "IMG_1255.mov",
    "beschreibung": ""
  },
  {
    "slug": "hanbon-kyorugi-02",
    "titel": "Einschrittkampf 2",
    "bereich": "Hanbon Kyorugi",
    "grad": "Alle Grade",
    "trainer": "",
    "datum": "2026-09-12",
    "dauer": 9,
    "reihenfolge": 2,
    "herkunft": "IMG_1256.mov",
    "beschreibung": ""
  },
  {
    "slug": "hanbon-kyorugi-03",
    "titel": "Einschrittkampf 3",
    "bereich": "Hanbon Kyorugi",
    "grad": "Alle Grade",
    "trainer": "",
    "datum": "2026-09-12",
    "dauer": 11,
    "reihenfolge": 3,
    "herkunft": "IMG_1257.mov",
    "beschreibung": ""
  },
  {
    "slug": "hanbon-kyorugi-04",
    "titel": "Einschrittkampf 4",
    "bereich": "Hanbon Kyorugi",
    "grad": "Alle Grade",
    "trainer": "",
    "datum": "2026-09-12",
    "dauer": 11,
    "reihenfolge": 4,
    "herkunft": "IMG_1258.mov",
    "beschreibung": ""
  },
  {
    "slug": "hanbon-kyorugi-05",
    "titel": "Einschrittkampf 5",
    "bereich": "Hanbon Kyorugi",
    "grad": "Alle Grade",
    "trainer": "",
    "datum": "2026-09-12",
    "dauer": 10,
    "reihenfolge": 5,
    "herkunft": "IMG_1259.mov",
    "beschreibung": ""
  },
  {
    "slug": "hanbon-kyorugi-06",
    "titel": "Einschrittkampf 6",
    "bereich": "Hanbon Kyorugi",
    "grad": "Alle Grade",
    "trainer": "",
    "datum": "2026-09-12",
    "dauer": 10,
    "reihenfolge": 6,
    "herkunft": "IMG_1260.mov",
    "beschreibung": ""
  },
/* REIHE:ENDE */
];
