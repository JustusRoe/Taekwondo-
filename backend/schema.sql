-- =========================================================
-- Mitgliederbereich – Datenbankschema (MySQL / MariaDB)
-- Import über phpMyAdmin beim Hoster oder:
--   mysql -u BENUTZER -p DATENBANK < schema.sql
-- =========================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------
-- Mitglieder mit Zugang zum geschützten Bereich
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
  PRIMARY KEY (id),
  UNIQUE KEY uq_benutzername (benutzername)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Videos. Die Datei selbst liegt NICHT in der Datenbank,
-- sondern außerhalb des Web-Ordners; hier steht nur der Name.
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS videos (
  id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(80)    NOT NULL,
  titel         VARCHAR(200)   NOT NULL,
  bereich       VARCHAR(60)    NOT NULL,   -- Grundschule, Poomsae, Wettkampf …
  grad          VARCHAR(60)    NOT NULL DEFAULT 'Alle Grade',
  trainer       VARCHAR(120)   NOT NULL DEFAULT '',
  beschreibung  TEXT,
  dateiname     VARCHAR(190)   NOT NULL,   -- z. B. poomsae-taegeuk-il-jang.mp4
  posterdatei   VARCHAR(190)   DEFAULT NULL,
  dauer         SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- Sekunden
  veroeffentlicht_am DATE      NOT NULL DEFAULT (CURRENT_DATE),
  sichtbar      TINYINT(1)     NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_slug (slug),
  KEY idx_bereich (bereich, sichtbar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Abschnitte (Kapitel) innerhalb eines Videos
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS kapitel (
  id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  video_id      INT UNSIGNED   NOT NULL,
  startsekunde  SMALLINT UNSIGNED NOT NULL,
  bezeichnung   VARCHAR(160)   NOT NULL,
  PRIMARY KEY (id),
  KEY idx_video (video_id, startsekunde),
  CONSTRAINT fk_kapitel_video FOREIGN KEY (video_id)
    REFERENCES videos (id) ON DELETE CASCADE
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


-- =========================================================
-- Beispieldaten (entsprechen dem Entwurf im Frontend)
-- =========================================================

-- Testkonten. Passwort beider Konten: test1234
-- Neues Passwort erzeugen mit:
--   php -r "echo password_hash('NEUES_PASSWORT', PASSWORD_DEFAULT);"
-- VOR DEM ECHTBETRIEB ERSETZEN ODER LÖSCHEN!
INSERT INTO mitglieder (benutzername, name, email, passwort_hash, rolle) VALUES
  ('testuser',    'Test Nutzer',  'testuser@example.de',
   '$2y$12$l0r.Y4R6V9ovEaM.86aROez/bqHQDADpVuGCc559XdCmBhYBnKR0.', 'mitglied'),
  ('testtrainer', 'Test Trainer', 'testtrainer@example.de',
   '$2y$12$l0r.Y4R6V9ovEaM.86aROez/bqHQDADpVuGCc559XdCmBhYBnKR0.', 'trainer');

INSERT INTO videos (slug, titel, bereich, grad, trainer, beschreibung, dateiname, posterdatei, dauer, veroeffentlicht_am) VALUES
  ('poomsae-taegeuk-il-jang', 'Taegeuk Il Jang – Schritt für Schritt', 'Poomsae', 'ab 8. Kup', 'Daniel Lee',
   'Die erste Form der Taegeuk-Reihe in ruhigem Tempo, danach die Sequenzen einzeln mit den häufigsten Korrekturen.',
   'poomsae-taegeuk-il-jang.mp4', 'poomsae-taegeuk-il-jang.jpg', 62, '2026-07-14'),
  ('grundschule-fusstechniken', 'Grundschule: die drei Basis-Fußtechniken', 'Grundschule', 'Alle Grade', 'Michael Hoffmann',
   'Ap Chagi, Dollyo Chagi und Yop Chagi im direkten Vergleich.',
   'grundschule-fusstechniken.mp4', 'grundschule-fusstechniken.jpg', 50, '2026-06-28'),
  ('partnertraining-hanbon', 'Hanbon Kyorugi – Einschrittkampf', 'Partnertraining', 'ab 5. Kup', 'Daniel Lee',
   'Ablauf des Einschrittkampfs mit fester Rollenverteilung.',
   'partnertraining-hanbon.mp4', 'partnertraining-hanbon.jpg', 44, '2026-06-12'),
  ('selbstverteidigung-befreiung', 'Befreiungstechniken aus Griffen', 'Selbstverteidigung', 'Alle Grade', 'Michael Hoffmann',
   'Zwei Grundbefreiungen, die ohne Kraft funktionieren.',
   'selbstverteidigung-befreiung.mp4', 'selbstverteidigung-befreiung.jpg', 36, '2026-05-30'),
  ('kyorugi-beinarbeit', 'Beinarbeit im Freikampf', 'Wettkampf', 'ab 5. Kup', 'Daniel Lee',
   'Grundstellung, Schrittfolgen und seitliches Ausweichen.',
   'kyorugi-beinarbeit.mp4', 'kyorugi-beinarbeit.jpg', 42, '2026-05-16'),
  ('dehnung-beweglichkeit', 'Dehnprogramm für zu Hause', 'Athletik', 'Alle Grade', 'Sarah Berger',
   'Beweglichkeitsübungen für die Tage zwischen den Trainingseinheiten.',
   'dehnung-beweglichkeit.mp4', 'dehnung-beweglichkeit.jpg', 54, '2026-04-25');

INSERT INTO kapitel (video_id, startsekunde, bezeichnung)
SELECT v.id, k.s, k.b FROM videos v JOIN (
  SELECT 'poomsae-taegeuk-il-jang' slug,  0 s, 'Vorbereitung und Stand' b UNION ALL
  SELECT 'poomsae-taegeuk-il-jang',      14, 'Erste Sequenz'             UNION ALL
  SELECT 'poomsae-taegeuk-il-jang',      32, 'Zweite Sequenz'            UNION ALL
  SELECT 'poomsae-taegeuk-il-jang',      48, 'Abschluss und Korrektur'   UNION ALL
  SELECT 'grundschule-fusstechniken',     0, 'Ap Chagi – Fußstoß vorwärts'      UNION ALL
  SELECT 'grundschule-fusstechniken',    16, 'Dollyo Chagi – Halbkreisfußstoß'  UNION ALL
  SELECT 'grundschule-fusstechniken',    34, 'Yop Chagi – Seitwärtsstoß'        UNION ALL
  SELECT 'partnertraining-hanbon',        0, 'Ablauf und Distanz'   UNION ALL
  SELECT 'partnertraining-hanbon',       15, 'Angriff und Block'    UNION ALL
  SELECT 'partnertraining-hanbon',       30, 'Konter'               UNION ALL
  SELECT 'selbstverteidigung-befreiung',  0, 'Handgelenkbefreiung'  UNION ALL
  SELECT 'selbstverteidigung-befreiung', 18, 'Befreiung aus der Umklammerung' UNION ALL
  SELECT 'kyorugi-beinarbeit',            0, 'Grundstellung'                  UNION ALL
  SELECT 'kyorugi-beinarbeit',           12, 'Schrittfolgen vor und zurück'   UNION ALL
  SELECT 'kyorugi-beinarbeit',           28, 'Ausweichen zur Seite'           UNION ALL
  SELECT 'dehnung-beweglichkeit',         0, 'Aufwärmen'        UNION ALL
  SELECT 'dehnung-beweglichkeit',        14, 'Beinrückseite'    UNION ALL
  SELECT 'dehnung-beweglichkeit',        28, 'Hüftöffner'       UNION ALL
  SELECT 'dehnung-beweglichkeit',        42, 'Ausklang'
) k ON k.slug = v.slug;
