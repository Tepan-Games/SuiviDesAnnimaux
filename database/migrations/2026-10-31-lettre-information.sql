-- Lettre d'information (31/10/2026) : à exécuter une fois dans phpMyAdmin sur une base créée avant cette date.
ALTER TABLE users ADD COLUMN newsletter_at DATETIME NULL AFTER last_login_at;
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
