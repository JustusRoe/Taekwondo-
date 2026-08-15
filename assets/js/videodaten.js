/* =========================================================
   Beispieldaten der Videothek
   -----------------------------------------------------------
   Im Entwurf stehen die Videos hier fest im Code. In der
   späteren Fassung liefert die Datenbank dieselben Felder
   (siehe backend/schema.sql).
   ========================================================= */
window.VIDEOTHEK = [
  {
    slug: 'poomsae-taegeuk-il-jang',
    titel: 'Taegeuk Il Jang – Schritt für Schritt',
    bereich: 'Poomsae',
    grad: 'ab 8. Kup',
    trainer: 'Daniel Lee',
    datum: '2026-07-14',
    dauer: 62,
    beschreibung: 'Die erste Form der Taegeuk-Reihe in ruhigem Tempo. Zuerst der komplette Ablauf, danach die beiden Sequenzen einzeln mit den häufigsten Korrekturen: Stand zu kurz, Hüfte nicht mitgedreht, Blick zu früh.',
    kapitel: [
      { t: 0, name: 'Vorbereitung und Stand' },
      { t: 14, name: 'Erste Sequenz' },
      { t: 32, name: 'Zweite Sequenz' },
      { t: 48, name: 'Abschluss und Korrektur' }
    ]
  },
  {
    slug: 'grundschule-fusstechniken',
    titel: 'Grundschule: die drei Basis-Fußtechniken',
    bereich: 'Grundschule',
    grad: 'Alle Grade',
    trainer: 'Michael Hoffmann',
    datum: '2026-06-28',
    dauer: 50,
    beschreibung: 'Ap Chagi, Dollyo Chagi und Yop Chagi im direkten Vergleich. Achten Sie auf das Anheben des Knies vor jeder Technik – daran scheitern die meisten Prüfungen, nicht an der Höhe des Tritts.',
    kapitel: [
      { t: 0, name: 'Ap Chagi – Fußstoß vorwärts' },
      { t: 16, name: 'Dollyo Chagi – Halbkreisfußstoß' },
      { t: 34, name: 'Yop Chagi – Seitwärtsstoß' }
    ]
  },
  {
    slug: 'partnertraining-hanbon',
    titel: 'Hanbon Kyorugi – Einschrittkampf',
    bereich: 'Partnertraining',
    grad: 'ab 5. Kup',
    trainer: 'Daniel Lee',
    datum: '2026-06-12',
    dauer: 44,
    beschreibung: 'Ablauf des Einschrittkampfs mit fester Rollenverteilung. Wichtig ist die Distanz vor dem Angriff: einen halben Schritt zu nah, und der Block kommt zu spät.',
    kapitel: [
      { t: 0, name: 'Ablauf und Distanz' },
      { t: 15, name: 'Angriff und Block' },
      { t: 30, name: 'Konter' }
    ]
  },
  {
    slug: 'selbstverteidigung-befreiung',
    titel: 'Befreiungstechniken aus Griffen',
    bereich: 'Selbstverteidigung',
    grad: 'Alle Grade',
    trainer: 'Michael Hoffmann',
    datum: '2026-05-30',
    dauer: 36,
    beschreibung: 'Zwei Grundbefreiungen, die ohne Kraft funktionieren: Handgelenk gegen den Daumen lösen, aus der Umklammerung über den Schwerpunkt. Beide werden im Kurs am Donnerstag vertieft.',
    kapitel: [
      { t: 0, name: 'Handgelenkbefreiung' },
      { t: 18, name: 'Befreiung aus der Umklammerung' }
    ]
  },
  {
    slug: 'kyorugi-beinarbeit',
    titel: 'Beinarbeit im Freikampf',
    bereich: 'Wettkampf',
    grad: 'ab 5. Kup',
    trainer: 'Daniel Lee',
    datum: '2026-05-16',
    dauer: 42,
    beschreibung: 'Grundstellung, Schrittfolgen und seitliches Ausweichen. Für das Wettkampfteam als Vorbereitung auf die Stadtmeisterschaft; wer Freitag nicht kann, findet hier die Übungen der letzten Einheit.',
    kapitel: [
      { t: 0, name: 'Grundstellung' },
      { t: 12, name: 'Schrittfolgen vor und zurück' },
      { t: 28, name: 'Ausweichen zur Seite' }
    ]
  },
  {
    slug: 'dehnung-beweglichkeit',
    titel: 'Dehnprogramm für zu Hause',
    bereich: 'Athletik',
    grad: 'Alle Grade',
    trainer: 'Sarah Berger',
    datum: '2026-04-25',
    dauer: 54,
    beschreibung: 'Zwölf Minuten Beweglichkeit, die sich zwischen den Trainingstagen zu Hause machen lassen. Zweimal pro Woche bringt mehr als einmal lang – gerade bei den hohen Fußtechniken.',
    kapitel: [
      { t: 0, name: 'Aufwärmen' },
      { t: 14, name: 'Beinrückseite' },
      { t: 28, name: 'Hüftöffner' },
      { t: 42, name: 'Ausklang' }
    ]
  }
];
