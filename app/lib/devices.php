<?php
declare(strict_types=1);

// ===== Appareils connectés (logiciel, application) et codes de connexion =====

/** Durée de validité d'un code de connexion, en minutes. */
const DEVICE_CODE_MINUTES = 15;

/** Lettres et chiffres sans confusion possible (pas de 0/O, 1/I/L). */
const DEVICE_CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

/** Code au format XXXX-XXXX, ou '' s'il n'a pas la bonne forme (minuscules, espaces et tirets acceptés). */
function normalize_device_code(string $code): string
{
    $code = strtoupper((string)preg_replace('/[\s-]+/', '', $code));
    if (strlen($code) !== 8 || strspn($code, DEVICE_CODE_ALPHABET) !== 8) {
        return '';
    }
    return substr($code, 0, 4) . '-' . substr($code, 4);
}

/** Nouveau code de connexion d'un appareil (15 minutes, usage unique, seule son empreinte est gardée). */
function create_device_code(int $userId): string
{
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= DEVICE_CODE_ALPHABET[random_int(0, strlen(DEVICE_CODE_ALPHABET) - 1)];
    }
    $code = substr($code, 0, 4) . '-' . substr($code, 4);
    q('INSERT INTO user_tokens (user_id, type, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
        [$userId, 'appareil', token_hash($code), date('Y-m-d H:i:s', time() + DEVICE_CODE_MINUTES * 60), now()]);
    audit('appareil.code', '', $userId);
    return $code;
}

/** Adresse lue par le logiciel et l'application (QR code). */
function device_code_link(string $code): string
{
    return 'suivianimaux://site?code=' . rawurlencode($code);
}

/** Enregistre un appareil pour le compte ; renvoie son jeton (donné une seule fois). */
function create_device(int $userId, string $name): string
{
    $name = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? ''), 0, 120) ?: 'Appareil';
    $token = random_token();
    q('INSERT INTO devices (user_id, name, token_hash, created_at, last_seen_at) VALUES (?, ?, ?, ?, ?)',
        [$userId, $name, token_hash($token), now(), now()]);
    audit('appareil.connexion', $name, $userId);
    return $token;
}

/** Appareil actif et son compte, d'après le jeton ; null si inconnu, déconnecté ou compte désactivé. */
function find_device(string $token): ?array
{
    if ($token === '') {
        return null;
    }
    return q('SELECT d.*, u.email, u.name AS user_name FROM devices d JOIN users u ON u.id = d.user_id
              WHERE d.token_hash = ? AND d.revoked_at IS NULL AND u.disabled_at IS NULL', [token_hash($token)])->fetch() ?: null;
}

function user_devices(int $userId): array
{
    return q('SELECT * FROM devices WHERE user_id = ? AND revoked_at IS NULL ORDER BY COALESCE(last_seen_at, created_at) DESC', [$userId])->fetchAll();
}

function revoke_device(int $userId, int $deviceId): bool
{
    $done = q('UPDATE devices SET revoked_at = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL', [now(), $deviceId, $userId])->rowCount() > 0;
    if ($done) {
        audit('appareil.deconnexion', "Appareil n°$deviceId", $userId);
    }
    return $done;
}

/** Déconnecte tous les appareils du compte (nouveau mot de passe choisi par le lien « mot de passe oublié »). */
function revoke_all_devices(int $userId): void
{
    q('UPDATE devices SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL', [now(), $userId]);
}
