-- =========================================================
-- Mitgliederbereich – Datenbankschema (MySQL / MariaDB)
-- Import über phpMyAdmin beim Hoster oder:
--   mysql -u BENUTZER -p DATENBANK < schema.sql
-- =========================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------
-- Mitglieder mit Zugang zum geschützten Bereich
--
-- Konten legt ausschließlich das Trainerteam an (backend/admin.php).
-- Eine Selbstregistrierung gibt es bewusst nicht: So ist jederzeit klar,
-- wer Zugang hat, und niemand muss eine Freigabe prüfen.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS mitglieder (
  id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  benutzername  VARCHAR(60)    NOT NULL,
  name          VARCHAR(120)   NOT NULL,
  email         VARCHAR(190)   DEFAULT NULL,
  passwort_hash VARCHAR(255)   NOT NULL,   -- password_hash(), niemals Klartext
  rolle         ENUM('mitglied','trainer') NOT NULL DEFAULT 'mitglied',
  aktiv         TINYINT(1)     NOT NULL DEFAULT 1,
  angelegt_am   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  letzter_login DATETIME       DEFAULT NULL,
  -- Startpasswoerter kennt immer auch die Person, die den Zugang angelegt
  -- hat. Solange dieses Feld auf 1 steht, fuehrt jede geschuetzte Seite
  -- zuerst auf passwort.php: Danach kennt das Passwort nur noch die
  -- Person selbst. Fuer Trainerkonten ist das der wichtigste Teil.
  passwort_wechseln     TINYINT(1) NOT NULL DEFAULT 1,
  passwort_geaendert_am DATETIME   DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_benutzername (benutzername)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Videos. Die Datei selbst liegt NICHT in der Datenbank,
-- sondern außerhalb des Web-Ordners; hier steht nur der Name.
--
-- trainer und dauer werden beim Hochladen automatisch gesetzt:
-- trainer aus dem angemeldeten Konto, dauer aus der Videodatei,
-- die der Browser vor dem Hochladen bereits kennt.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS videos (
  id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(80)    NOT NULL,
  titel         VARCHAR(200)   NOT NULL,
  bereich       VARCHAR(60)    NOT NULL,   -- Poomsae, Hanbon Kyorugi …
  grad          VARCHAR(60)    NOT NULL DEFAULT 'Alle Grade',
  trainer       VARCHAR(120)   NOT NULL DEFAULT '',
  beschreibung  TEXT,
  dateiname     VARCHAR(190)   NOT NULL,   -- z. B. taegeuk-il-jang.mp4
  posterdatei   VARCHAR(190)   DEFAULT NULL,
  dauer         SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- Sekunden
  veroeffentlicht_am DATE      NOT NULL DEFAULT (CURRENT_DATE),
  sichtbar      TINYINT(1)     NOT NULL DEFAULT 1,
  -- Platz innerhalb einer Reihe. Der Einschrittkampf besteht aus
  -- dreizehn Techniken, die in ihrer Nummer gelernt werden – nach
  -- Datum sortiert stuenden sie verkehrt herum. 0 bedeutet "keine
  -- Reihe"; solche Videos erscheinen hinter den nummerierten, nach
  -- Datum wie bisher.
  reihenfolge   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug),
  KEY idx_bereich (bereich, sichtbar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Fehlgeschlagene Anmeldungen – bremst Passwortraten aus
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_versuche (
  id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  benutzername  VARCHAR(60)    NOT NULL,
  ip            VARBINARY(16)  NOT NULL,
  zeitpunkt     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_suche (benutzername, zeitpunkt),
  KEY idx_ip (ip, zeitpunkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------
-- Trainingstermine
--
-- Gepflegt wird hier, in backend/termine.php. Beim Speichern
-- schreibt die Verwaltung daraus zwei Stellen der oeffentlichen
-- Website neu, jeweils zwischen den Markierungen TERMINE:ANFANG
-- und TERMINE:ENDE:
--   assets/js/trainingstermine.js  (Daten fuer die Startseite)
--   training.html                  (Liste, auch ohne JavaScript lesbar)
--
-- ort: "steines" | "schloss" | "frei" ("frei" = kein Training)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS trainingstermine (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  datum       DATE         NOT NULL,
  zeit        VARCHAR(40)  NOT NULL DEFAULT '',
  gruppe      VARCHAR(80)  NOT NULL DEFAULT '',
  ort         VARCHAR(20)  NOT NULL DEFAULT 'steines',
  hinweis     VARCHAR(190) NOT NULL DEFAULT '',
  geaendert_am DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_datum_zeit (datum, zeit),
  KEY idx_datum (datum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- Erstes Trainerkonto
--
-- Hier steht bewusst kein Konto.
--
-- Frueher stand an dieser Stelle ein Trainerkonto mit festem Passwort.
-- Das ist eine schlechte Idee: Wer es nach dem Livegang zu loeschen
-- vergisst, hat ein Konto mit oeffentlich bekanntem Passwort auf dem
-- Server -- und das Passwort steht dazu in dieser Datei.
--
-- Stattdessen: nach dem Einspielen dieser Datei einmal
--
--   https://deine-domain.de/backend/einrichten.php
--
-- aufrufen. Die Seite legt das erste Trainerkonto an, mit einem
-- Passwort, das du selbst waehlst. Sie funktioniert nur, solange diese
-- Tabelle leer ist, und sperrt sich danach selbst. Alle weiteren
-- Zugaenge entstehen in der Verwaltung unter "Zugaenge".


-- =========================================================
-- Nachtraeglich einspielen (bestehende Installationen)
--
-- Die beiden Spalten und die Termintabelle kamen spaeter dazu. Wer die
-- Datenbank schon angelegt hat, spielt nur diese Zeilen nach; MySQL meldet
-- "Duplicate column", wenn sie bereits vorhanden sind – dann ist nichts
-- zu tun.
--
--   ALTER TABLE mitglieder
--     ADD COLUMN passwort_wechseln TINYINT(1) NOT NULL DEFAULT 1,
--     ADD COLUMN passwort_geaendert_am DATETIME DEFAULT NULL;
--
--   ALTER TABLE videos
--     ADD COLUMN reihenfolge SMALLINT UNSIGNED NOT NULL DEFAULT 0;
--
-- Die Tabelle trainingstermine legt das CREATE TABLE oben von selbst an
-- (IF NOT EXISTS); die Termine selbst importiert danach die Verwaltung
-- unter backend/termine.php aus einer CSV-Datei.
-- =========================================================
