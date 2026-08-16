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


-- =========================================================
-- Erstes Trainerkonto
--
-- Ohne dieses Konto kommt niemand in die Verwaltung, um weitere
-- Konten anzulegen. Passwort: test1234
-- Neues Passwort erzeugen mit:
--   php -r "echo password_hash('NEUES_PASSWORT', PASSWORD_DEFAULT);"
-- VOR DEM ECHTBETRIEB ERSETZEN.
-- =========================================================
INSERT INTO mitglieder (benutzername, name, email, passwort_hash, rolle) VALUES
  ('testtrainer', 'Test Trainer', 'testtrainer@example.de',
   '$2y$12$l0r.Y4R6V9ovEaM.86aROez/bqHQDADpVuGCc559XdCmBhYBnKR0.', 'trainer');
