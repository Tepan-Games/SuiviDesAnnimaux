-- Échéances des licences (31/10/2026) : à exécuter une fois dans phpMyAdmin sur une base créée avant cette date.
ALTER TABLE licences
    ADD COLUMN grace_notified_at DATETIME NULL AFTER updated_at,
    ADD COLUMN expired_notified_at DATETIME NULL AFTER grace_notified_at,
    ADD COLUMN data_deleted_at DATETIME NULL AFTER expired_notified_at;

-- Réglages modifiables dans l'administration (échéances des licences…).
CREATE TABLE IF NOT EXISTS site_settings (
    name VARCHAR(60) PRIMARY KEY,
    value VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
