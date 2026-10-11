-- =====================================================================
--  Gestion des Stages PFE - Schéma de base de données (MySQL / MariaDB)
--  Import : phpMyAdmin / HeidiSQL (Laragon) ou
--           mysql -u root < database/gestion_pfe.sql
-- =====================================================================

DROP DATABASE IF EXISTS gestion_pfe;
CREATE DATABASE gestion_pfe CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestion_pfe;

-- ---------------------------------------------------------------------
-- Utilisateurs (étudiants, encadrants, administrateurs)
-- ---------------------------------------------------------------------
CREATE TABLE utilisateurs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(100) NOT NULL,
    prenom        VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    mot_de_passe  VARCHAR(255) NOT NULL,
    role          ENUM('etudiant','encadrant','admin') NOT NULL DEFAULT 'etudiant',
    telephone     VARCHAR(30)  NULL,
    filiere       VARCHAR(100) NULL,      -- étudiant : filière / classe
    departement   VARCHAR(100) NULL,      -- encadrant : département
    actif         TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Entreprises d'accueil
-- ---------------------------------------------------------------------
CREATE TABLE entreprises (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    adresse     VARCHAR(255) NULL,
    ville       VARCHAR(100) NULL,
    telephone   VARCHAR(30)  NULL,
    email       VARCHAR(150) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Stages PFE
--   Workflow des statuts :
--   brouillon -> soumis -> (a_corriger -> soumis ...) -> valide_encadrant
--             -> valide (validation finale admin) -> termine
--   refuse possible à l'étape encadrant ou admin
-- ---------------------------------------------------------------------
CREATE TABLE stages (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    etudiant_id              INT UNSIGNED NOT NULL,
    encadrant_id             INT UNSIGNED NULL,
    entreprise_id            INT UNSIGNED NULL,
    sujet                    VARCHAR(255) NOT NULL,
    description              TEXT         NULL,
    technologies             VARCHAR(255) NULL,
    encadrant_entreprise     VARCHAR(150) NULL,
    encadrant_entreprise_email VARCHAR(150) NULL,
    date_debut               DATE         NOT NULL,
    date_fin                 DATE         NOT NULL,
    statut                   ENUM('brouillon','soumis','a_corriger','valide_encadrant','valide','refuse','termine')
                             NOT NULL DEFAULT 'brouillon',
    avancement               TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- en %
    fichier_convention       VARCHAR(255) NULL,
    fichier_rapport          VARCHAR(255) NULL,
    note_finale              DECIMAL(4,2) NULL,
    annee_universitaire      VARCHAR(9)   NOT NULL,                 -- ex : 2026-2027
    created_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stage_etudiant   FOREIGN KEY (etudiant_id)   REFERENCES utilisateurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_stage_encadrant  FOREIGN KEY (encadrant_id)  REFERENCES utilisateurs(id) ON DELETE SET NULL,
    CONSTRAINT fk_stage_entreprise FOREIGN KEY (entreprise_id) REFERENCES entreprises(id)  ON DELETE SET NULL,
    INDEX idx_stage_statut (statut)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Journal de suivi (rapports d'avancement + remarques de l'encadrant)
-- ---------------------------------------------------------------------
CREATE TABLE suivis (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stage_id    INT UNSIGNED NOT NULL,
    auteur_id   INT UNSIGNED NOT NULL,
    contenu     TEXT         NOT NULL,
    avancement  TINYINT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_suivi_stage  FOREIGN KEY (stage_id)  REFERENCES stages(id)       ON DELETE CASCADE,
    CONSTRAINT fk_suivi_auteur FOREIGN KEY (auteur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Historique des changements de statut (traçabilité de la validation)
-- ---------------------------------------------------------------------
CREATE TABLE historique_statuts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stage_id        INT UNSIGNED NOT NULL,
    utilisateur_id  INT UNSIGNED NOT NULL,
    ancien_statut   VARCHAR(30)  NULL,
    nouveau_statut  VARCHAR(30)  NOT NULL,
    commentaire     TEXT         NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_stage FOREIGN KEY (stage_id)       REFERENCES stages(id)       ON DELETE CASCADE,
    CONSTRAINT fk_hist_user  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Notifications internes
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT UNSIGNED NOT NULL,
    message         VARCHAR(255) NOT NULL,
    lien            VARCHAR(255) NULL,
    lu              TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
--  Données de démonstration
--  Mot de passe de tous les comptes : password123
-- =====================================================================
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, telephone, filiere, departement) VALUES
('Admin',    'Système', 'admin@pfe.tn',     '$2y$10$yN/5IXRLx5166VYTYgOtyeaBZzND2kOYlZKzNrVo7.AXU5ikdWNQu', 'admin',     NULL,         NULL,              'Direction des stages'),
('Ben Ali',  'Sami',    'encadrant@pfe.tn', '$2y$10$yN/5IXRLx5166VYTYgOtyeaBZzND2kOYlZKzNrVo7.AXU5ikdWNQu', 'encadrant', '20 000 001', NULL,              'Informatique'),
('Trabelsi', 'Amel',    'amel@pfe.tn',      '$2y$10$yN/5IXRLx5166VYTYgOtyeaBZzND2kOYlZKzNrVo7.AXU5ikdWNQu', 'encadrant', '20 000 002', NULL,              'Génie logiciel'),
('Gharbi',   'Yassine', 'etudiant@pfe.tn',  '$2y$10$yN/5IXRLx5166VYTYgOtyeaBZzND2kOYlZKzNrVo7.AXU5ikdWNQu', 'etudiant',  '50 000 001', 'Licence GLSI 3',  NULL),
('Mansour',  'Ines',    'ines@pfe.tn',      '$2y$10$yN/5IXRLx5166VYTYgOtyeaBZzND2kOYlZKzNrVo7.AXU5ikdWNQu', 'etudiant',  '50 000 002', 'Master SID 2',    NULL);

INSERT INTO entreprises (nom, adresse, ville, telephone, email) VALUES
('TechSoft',      'Rue du Lac Léman',     'Tunis',  '71 000 000', 'contact@techsoft.tn'),
('DataVision',    'Technopole El Ghazela','Ariana', '70 000 000', 'rh@datavision.tn');

INSERT INTO stages (etudiant_id, encadrant_id, entreprise_id, sujet, description, technologies,
                    encadrant_entreprise, encadrant_entreprise_email, date_debut, date_fin,
                    statut, avancement, annee_universitaire) VALUES
(4, 2, 1, 'Application web de gestion des ressources humaines',
 'Conception et développement d''une application de gestion des congés et des absences.',
 'PHP, MySQL, Bootstrap', 'Karim Jlassi', 'k.jlassi@techsoft.tn',
 '2027-02-01', '2027-05-31', 'soumis', 0, '2026-2027'),
(5, 3, 2, 'Tableau de bord décisionnel pour le suivi des ventes',
 'Mise en place d''un entrepôt de données et de tableaux de bord interactifs.',
 'Python, Power BI, SQL Server', 'Leila Haddad', 'l.haddad@datavision.tn',
 '2027-02-15', '2027-06-15', 'valide', 35, '2026-2027');

INSERT INTO historique_statuts (stage_id, utilisateur_id, ancien_statut, nouveau_statut, commentaire) VALUES
(1, 4, 'brouillon',        'soumis',           'Soumission du sujet'),
(2, 5, 'brouillon',        'soumis',           'Soumission du sujet'),
(2, 3, 'soumis',           'valide_encadrant', 'Sujet pertinent, validé.'),
(2, 1, 'valide_encadrant', 'valide',           'Validation finale.');

INSERT INTO suivis (stage_id, auteur_id, contenu, avancement) VALUES
(2, 5, 'Semaine 1-3 : étude de l''existant et modélisation de l''entrepôt de données.', 35),
(2, 3, 'Bon début. Pensez à documenter le choix du schéma en étoile.', NULL);
