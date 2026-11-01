-- Suivi des animaux — site et service en ligne (MySQL 8 / MariaDB 10.5+, utf8mb4).
-- À importer une fois dans la base créée chez OVH (phpMyAdmin), puis les mises à jour de database/migrations/.

SET NAMES utf8mb4;

-- Comptes : une adresse e-mail par personne (particulier, membre d'une structure, administrateur du site).
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    name VARCHAR(120) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    email_verified_at DATETIME NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    disabled_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME NULL,
    -- Lettre d'information : date de l'accord (case cochée), NULL = pas abonné.
    newsletter_at DATETIME NULL,
    UNIQUE KEY ux_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jetons à usage unique (confirmation de l'adresse, nouveau mot de passe, code « appareil » de connexion d'un appareil) : seule leur empreinte est gardée.
CREATE TABLE IF NOT EXISTS user_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(20) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY ux_tokens_hash (token_hash),
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appareils connectés au compte (logiciel Windows, application Android) pour la synchronisation des structures :
-- jeton reçu une fois à la connexion, seule son empreinte est gardée ; révoqué par « Déconnecter » dans l'espace client.
CREATE TABLE IF NOT EXISTS devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL DEFAULT '',
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    last_seen_at DATETIME NULL,
    revoked_at DATETIME NULL,
    UNIQUE KEY ux_devices_hash (token_hash),
    KEY ix_devices_user (user_id),
    CONSTRAINT fk_devices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentatives de connexion (limite contre les essais de mots de passe).
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL,
    KEY ix_attempts (email, created_at),
    KEY ix_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structures (= espaces du logiciel synchronisés par le service) : association, pension, élevage, famille d'accueil.
CREATE TABLE IF NOT EXISTS organisations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    kind VARCHAR(40) NOT NULL,
    owner_user_id INT UNSIGNED NOT NULL,
    contact_email VARCHAR(190) NOT NULL DEFAULT '',
    phone VARCHAR(40) NOT NULL DEFAULT '',
    address VARCHAR(255) NOT NULL DEFAULT '',
    siret VARCHAR(20) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_org_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Membres et rôles (les mêmes que dans le logiciel : responsable, soins, accueil, tresorier, consultation).
CREATE TABLE IF NOT EXISTS memberships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    roles VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY ux_member (organisation_id, user_id),
    CONSTRAINT fk_member_org FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invitations : lien ou QR code contenant un code secret (seule son empreinte est gardée).
CREATE TABLE IF NOT EXISTS invitations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    roles VARCHAR(120) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    invited_by INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY ux_invitation_hash (token_hash),
    CONSTRAINT fk_invitation_org FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    CONSTRAINT fk_invitation_by FOREIGN KEY (invited_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Formules (prix en centimes TTC par mois ; max_animals NULL = illimité).
CREATE TABLE IF NOT EXISTS plans (
    code VARCHAR(30) PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    monthly_price_cents INT UNSIGNED NOT NULL,
    yearly_price_cents INT UNSIGNED NOT NULL,
    max_animals INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO plans (code, name, monthly_price_cents, yearly_price_cents, max_animals, sort_order) VALUES
    ('decouverte', 'Découverte', 0, 0, 5, 1),
    ('petite', 'Petite structure', 900, 9000, 30, 2),
    ('association', 'Association', 1900, 19000, 150, 3),
    ('grande', 'Grande structure / pension', 3900, 39000, NULL, 4);

-- Licence d'une structure : formule, état et dates (gérée par l'administrateur, puis par le paiement en ligne).
CREATE TABLE IF NOT EXISTS licences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organisation_id INT UNSIGNED NOT NULL,
    plan_code VARCHAR(30) NOT NULL,
    status VARCHAR(20) NOT NULL,
    billing VARCHAR(10) NOT NULL DEFAULT 'mois',
    starts_at DATE NOT NULL,
    ends_at DATE NULL,
    notes TEXT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY ux_licence_org (organisation_id),
    CONSTRAINT fk_licence_org FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE CASCADE,
    CONSTRAINT fk_licence_plan FOREIGN KEY (plan_code) REFERENCES plans(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suivi des problèmes (demandes d'assistance) et leurs échanges.
CREATE TABLE IF NOT EXISTS tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    organisation_id INT UNSIGNED NULL,
    subject VARCHAR(200) NOT NULL,
    category VARCHAR(40) NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normale',
    status VARCHAR(20) NOT NULL DEFAULT 'ouvert',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY ix_tickets_status (status, updated_at),
    CONSTRAINT fk_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_org FOREIGN KEY (organisation_id) REFERENCES organisations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    is_staff TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_message_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_message_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des actions importantes (administration, invitations, licences).
CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    details VARCHAR(500) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    KEY ix_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lettres d'information (texte), rédigées dans l'administration puis envoyées par lots aux comptes abonnés.
CREATE TABLE IF NOT EXISTS newsletters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    queued_at DATETIME NULL,
    finished_at DATETIME NULL,
    CONSTRAINT fk_newsletter_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Destinataires d'une lettre : file d'envoi, puis lien de désinscription (seule l'empreinte du jeton est gardée).
CREATE TABLE IF NOT EXISTS newsletter_deliveries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    newsletter_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NULL,
    sent_at DATETIME NULL,
    failed_at DATETIME NULL,
    unsubscribed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY ux_delivery (newsletter_id, user_id),
    UNIQUE KEY ux_delivery_hash (token_hash),
    KEY ix_delivery_pending (newsletter_id, sent_at),
    CONSTRAINT fk_delivery_newsletter FOREIGN KEY (newsletter_id) REFERENCES newsletters(id) ON DELETE CASCADE,
    CONSTRAINT fk_delivery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
