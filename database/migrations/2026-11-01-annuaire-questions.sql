-- Annuaire des structures et questions (01/11/2026) : à exécuter une fois dans phpMyAdmin sur une base créée avant cette date.
-- Chaque structure choisit, séparément, d'apparaître dans l'annuaire et d'accepter les questions du public.
ALTER TABLE organisations
    ADD COLUMN directory_listed TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN accepts_questions TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN description VARCHAR(600) NOT NULL DEFAULT '',
    ADD COLUMN species VARCHAR(200) NOT NULL DEFAULT '',
    ADD COLUMN website VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN city VARCHAR(120) NOT NULL DEFAULT '',
    ADD COLUMN latitude DECIMAL(9,6) NULL,
    ADD COLUMN longitude DECIMAL(9,6) NULL,
    ADD KEY ix_org_directory (directory_listed, latitude, longitude);

-- Questions posées à une structure (sans compte) ; la réponse est envoyée par e-mail et visible avec le lien de la question.
CREATE TABLE IF NOT EXISTS organisation_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    answer TEXT NULL,
    answered_by INT UNSIGNED NULL,
    answered_at DATETIME NULL,
    closed_at DATETIME NULL,
    UNIQUE KEY ux_questions_hash (token_hash),
    KEY ix_questions_org (organisation_id, created_at),
    CONSTRAINT fk_questions_org FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
