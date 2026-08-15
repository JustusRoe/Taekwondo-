/* =========================================================
   Beispieldaten der Videothek
   -----------------------------------------------------------
   Der Inhalt der eckigen Klammern ist bewusst gültiges JSON.
   So lesen sowohl der Browser als auch das Testskript
   (test/setup.php) dieselbe Quelle – die Daten stehen nur an
   einer Stelle. In der späteren Fassung liefert die Datenbank
   dieselben Felder (siehe backend/schema.sql).

   Alle Videos sind Platzhalter, bis eigene Aufnahmen vorliegen.
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
    "beschreibung": "Die Grundform als Einstieg in den Formenlauf. Zuerst die Stellung, dann der komplette Ablauf, zum Schluss die Fehler, die im Training am häufigsten auffallen.",
    "kapitel": [
      { "t": 0, "name": "Grundstellung" },
      { "t": 12, "name": "Der Ablauf" },
      { "t": 28, "name": "Häufige Fehler" }
    ]
  },
  {
    "slug": "taegeuk-il-jang",
    "titel": "Taegeuk Il Jang",
    "bereich": "Poomsae",
    "grad": "ab 8. Kup",
    "trainer": "Michael Buchhold",
    "datum": "2026-07-25",
    "dauer": 62,
    "beschreibung": "Die erste Form der Taegeuk-Reihe in ruhigem Tempo. Zuerst der komplette Ablauf, danach die beiden Sequenzen einzeln mit den häufigsten Korrekturen: Stand zu kurz, Hüfte nicht mitgedreht, Blick zu früh.",
    "kapitel": [
      { "t": 0, "name": "Vorbereitung und Stand" },
      { "t": 14, "name": "Erste Sequenz" },
      { "t": 32, "name": "Zweite Sequenz" },
      { "t": 48, "name": "Abschluss und Korrektur" }
    ]
  },
  {
    "slug": "taegeuk-i-jang",
    "titel": "Taegeuk I Jang",
    "bereich": "Poomsae",
    "grad": "ab 7. Kup",
    "trainer": "Michael Buchhold",
    "datum": "2026-07-18",
    "dauer": 46,
    "beschreibung": "Die zweite Form der Taegeuk-Reihe. Neu gegenüber Il Jang sind die Fußtechniken im Ablauf – achten Sie auf das Anheben des Knies vor jedem Tritt.",
    "kapitel": [
      { "t": 0, "name": "Vorbereitung" },
      { "t": 13, "name": "Erste Sequenz" },
      { "t": 30, "name": "Zweite Sequenz" }
    ]
  },
  {
    "slug": "taegeuk-sam-jang",
    "titel": "Taegeuk Sam Jang",
    "bereich": "Poomsae",
    "grad": "ab 6. Kup",
    "trainer": "Michael Buchhold",
    "datum": "2026-07-11",
    "dauer": 58,
    "beschreibung": "Die dritte Form mit doppelten Handtechniken und schnellerem Wechsel der Richtung. Der Ablauf wird zunächst langsam gezeigt, dann im Prüfungstempo.",
    "kapitel": [
      { "t": 0, "name": "Vorbereitung" },
      { "t": 14, "name": "Erste Sequenz" },
      { "t": 30, "name": "Zweite Sequenz" },
      { "t": 44, "name": "Abschluss" }
    ]
  },
  {
    "slug": "hanbon-kyorugi-1",
    "titel": "Hanbon Kyorugi 1 – Ablauf und Konter",
    "bereich": "Hanbon Kyorugi",
    "grad": "ab 8. Kup",
    "trainer": "Michael Buchhold",
    "datum": "2026-07-04",
    "dauer": 44,
    "beschreibung": "Einschrittkampf mit fester Rollenverteilung. Wichtig ist die Distanz vor dem Angriff: einen halben Schritt zu nah, und der Block kommt zu spät.",
    "kapitel": [
      { "t": 0, "name": "Ablauf und Distanz" },
      { "t": 15, "name": "Angriff und Block" },
      { "t": 30, "name": "Konter" }
    ]
  },
  {
    "slug": "hanbon-kyorugi-2",
    "titel": "Hanbon Kyorugi 2 – Ausweichen",
    "bereich": "Hanbon Kyorugi",
    "grad": "ab 6. Kup",
    "trainer": "Michael Buchhold",
    "datum": "2026-06-27",
    "dauer": 42,
    "beschreibung": "Zweite Form des Einschrittkampfs: statt zu blocken, wird ausgewichen. Der Konter kommt aus der Drehung – langsam üben, bis der Stand sicher steht.",
    "kapitel": [
      { "t": 0, "name": "Ausgangsstellung" },
      { "t": 14, "name": "Ausweichen" },
      { "t": 28, "name": "Konter und Abschluss" }
    ]
  }
];
