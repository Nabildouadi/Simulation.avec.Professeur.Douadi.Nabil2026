-- ==========================================================================
--  PLATEFORME PÉDAGOGIQUE — INSFP EL HIDHAB 2026
--  Professeur Douadi Nabil
-- --------------------------------------------------------------------------
--  Base de données MySQL / MariaDB
--
--  Ce fichier est FACULTATIF : api.php crée et installe la base tout seul
--  lors de la première ouverture de http://127.0.0.1:8080/api.php
--
--  Utilisation manuelle (phpMyAdmin, MySQL Workbench, ligne de commande) :
--     mysql -u root -p < database.sql
--
--  Compte professeur créé par ce script : identifiant et mot de passe
--  fournis séparément. Ils sont stockés sous forme d'empreintes SHA-256
--  salées, jamais en clair dans la base ni dans ce fichier.
-- ==========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `insfp_projets`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `insfp_projets`;

-- --------------------------------------------------------------------------
-- 1. Comptes professeurs
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(128) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `prof_name` VARCHAR(100) DEFAULT 'Douadi Nabil'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 2. Spécialités
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `specialites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 3. Stagiaires
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stagiaires` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(15) NOT NULL UNIQUE,
    `password` VARCHAR(4) NOT NULL,
    `specialite_id` INT NULL,
    `semestre` ENUM('S1','S2','S3','S4','S5') DEFAULT 'S1',
    `photo` LONGTEXT NULL,
    CONSTRAINT `fk_stagiaires_specialite`
        FOREIGN KEY (`specialite_id`) REFERENCES `specialites`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 4. Projets
--    data_json : copie JSON complète de la fiche projet (secours / export)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(255) NOT NULL,
    `methode` TEXT NULL,
    `data_json` LONGTEXT NULL,
    `date_creation` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `date_modification` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 5. Membres du projet
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projet_membres` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `stagiaire_id` INT NULL,
    `nom_libre` VARCHAR(100) NULL,
    `prenom_libre` VARCHAR(100) NULL,
    CONSTRAINT `fk_membres_projet`
        FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_membres_stagiaire`
        FOREIGN KEY (`stagiaire_id`) REFERENCES `stagiaires`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 6. Objectifs
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projet_objectifs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `texte` TEXT NOT NULL,
    CONSTRAINT `fk_objectifs_projet`
        FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 7. Étapes de réalisation
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projet_etapes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `texte` TEXT NOT NULL,
    CONSTRAINT `fk_etapes_projet`
        FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 8. Matériel utilisé
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projet_materiel` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `texte` TEXT NOT NULL,
    CONSTRAINT `fk_materiel_projet`
        FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 9. Photos du projet (images encodées en base64)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projet_photos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `position` INT NOT NULL,
    `image_data` LONGTEXT NOT NULL,
    CONSTRAINT `fk_photos_projet`
        FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 10. Notes / grille d'évaluation
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projet_notes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `projet_id` INT NOT NULL,
    `critere_id` VARCHAR(50) NOT NULL,
    `critere_texte` VARCHAR(255) NULL,
    `points_100` FLOAT DEFAULT 0,
    `points_20` FLOAT DEFAULT 0,
    `note_obtenue` FLOAT DEFAULT 0,
    CONSTRAINT `fk_notes_projet`
        FOREIGN KEY (`projet_id`) REFERENCES `projets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- 11. Paramètres (thème, logos, galerie…)
-- --------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================================
--  DONNÉES DE DÉPART
-- ==========================================================================

-- Compte professeur : empreintes SHA-256 salées (aucun mot de passe en clair)
-- Sel : 517670aa3dc9f48c724d53e5a1aea398307735567c60d9de
INSERT IGNORE INTO `users` (`username`, `password`, `prof_name`) VALUES
(
    '7a6c15bcdcf959c71d1dd7e4bf64bddb982fb01a32365d831dd7dacac12c20ed',
    '0300ef4631a500641e7ec2af25987f053712342796746e2ca4b7899514546f53',
    'Douadi Nabil'
);

-- Spécialités proposées par défaut
INSERT IGNORE INTO `specialites` (`nom`) VALUES
('Chimie Industrielle'),
('Analyse et Contrôle Qualité'),
('Pharmacie'),
('Biologie'),
('Industrie Agroalimentaire'),
('Protection de l''Environnement'),
('Autre');

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================================================
--  Fin du script — la plateforme est prête.
-- ==========================================================================
