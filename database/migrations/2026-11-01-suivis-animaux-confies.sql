-- Suivis des animaux confiés (01/11/2026) : à exécuter une fois dans phpMyAdmin sur une base créée avant cette date.
-- Suivis des animaux confiés (01/11/2026) : lien à identifiant unique (et QR code) remis à l'adoptant, à la famille
-- d'accueil ou au particulier qui reçoit un animal d'une structure. Sans compte : le lien suffit (seule l'empreinte de son
-- jeton est gardée). Les nouvelles, pesées, photos et questions arrivent sur le compte de la structure.
CREATE TABLE IF NOT EXISTS animal_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Identifiant affiché (XXXX-XXXX), à citer au téléphone ; le lien contient en plus un jeton secret.
    reference CHAR(9) NOT NULL,
    organisation_id INT UNSIGNED NOT NULL,
    -- adoption, accueil (famille d'accueil), particulier (cession, garde…)
    kind VARCHAR(20) NOT NULL,
    -- Identifiant de l'animal dans le logiciel de la structure (SyncId), pour relier les nouvelles à sa fiche.
    animal_ref VARCHAR(64) NOT NULL DEFAULT '',
    animal_name VARCHAR(120) NOT NULL,
    species VARCHAR(60) NOT NULL DEFAULT '',
    recipient_name VARCHAR(150) NOT NULL DEFAULT '',
    recipient_email VARCHAR(190) NOT NULL DEFAULT '',
    -- Fiche de l'animal envoyée par le logiciel (JSON : identification, vaccins, traitements, alimentation…).
    card MEDIUMTEXT NULL,
    -- Nouvelles demandées (photo, pesee, nouvelles) et rythme en jours (0 = libre).
    requests VARCHAR(120) NOT NULL DEFAULT '',
    interval_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    token_hash CHAR(64) NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    -- Fin du suivi (NULL = jusqu'à la fermeture, pour une famille d'accueil).
    ends_at DATETIME NULL,
    closed_at DATETIME NULL,
    opened_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    UNIQUE KEY ux_links_reference (reference),
    UNIQUE KEY ux_links_hash (token_hash),
    KEY ix_links_org (organisation_id, created_at),
    CONSTRAINT fk_links_org FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    CONSTRAINT fk_links_by FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Échanges d'un suivi : nouvelles, pesées, photos, questions de la personne ; réponses et conseils de la structure.
CREATE TABLE IF NOT EXISTS animal_link_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    link_id INT UNSIGNED NOT NULL,
    -- famille (la personne qui a reçu l'animal) ou structure
    author VARCHAR(20) NOT NULL,
    author_name VARCHAR(150) NOT NULL DEFAULT '',
    -- nouvelle, question, pesee, photo, reponse
    kind VARCHAR(20) NOT NULL,
    body TEXT NOT NULL,
    weight_kg DECIMAL(7,3) NULL,
    -- Nom du fichier de la photo dans storage/suivis/<id du suivi>/
    photo VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    read_at DATETIME NULL,
    KEY ix_link_messages (link_id, id),
    CONSTRAINT fk_link_messages FOREIGN KEY (link_id) REFERENCES animal_links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
