<?php
declare(strict_types=1);

const PASSWORD_MIN_LENGTH = 10;

/** Personne connectée (null sinon), relue une fois par page. */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $user = q('SELECT * FROM users WHERE id = ? AND disabled_at IS NULL', [$_SESSION['user_id']])->fetch() ?: null;
        }
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/compte';
        flash('Connectez-vous pour continuer.', 'info');
        redirect('/connexion');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (!(int)$user['is_admin']) {
        http_response_code(403);
        exit('Accès réservé à l’administration du site.');
    }
    return $user;
}

function find_user_by_email(string $email): ?array
{
    return q('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))])->fetch() ?: null;
}

/** Vérifie les champs d'inscription ; renvoie la liste des erreurs. */
function validate_registration(string $name, string $email, string $password): array
{
    $errors = [];
    if (mb_strlen($name) < 2) {
        $errors[] = 'Indiquez votre nom.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L’adresse e-mail n’est pas valide.';
    } elseif (find_user_by_email($email)) {
        $errors[] = 'Un compte existe déjà avec cette adresse : connectez-vous ou demandez un nouveau mot de passe.';
    }
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Le mot de passe doit faire au moins ' . PASSWORD_MIN_LENGTH . ' caractères.';
    }
    return $errors;
}

function create_user(string $name, string $email, string $password): int
{
    q('INSERT INTO users (email, name, password_hash, created_at) VALUES (?, ?, ?, ?)',
        [mb_strtolower(trim($email)), trim($name), password_hash($password, PASSWORD_DEFAULT), now()]);
    return (int)db()->lastInsertId();
}

/** Ouvre la session (nouvel identifiant de session : protège contre la fixation de session). */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/** Trop d'essais récents pour cette adresse ou cette connexion (15 minutes). */
function too_many_attempts(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    $byEmail = (int)q('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?', [mb_strtolower($email), $since])->fetchColumn();
    $byIp = (int)q('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > ?', [client_ip(), $since])->fetchColumn();
    return $byEmail >= 5 || $byIp >= 20;
}

/** Connexion : renvoie le compte, ou un message d'erreur. */
function attempt_login(string $email, string $password): array|string
{
    $email = mb_strtolower(trim($email));
    if (too_many_attempts($email)) {
        return 'Trop d’essais : réessayez dans quelques minutes.';
    }
    $user = find_user_by_email($email);
    if (!$user || !password_verify($password, $user['password_hash']) || $user['disabled_at']) {
        q('INSERT INTO login_attempts (email, ip, created_at) VALUES (?, ?, ?)', [$email, client_ip(), now()]);
        return 'Adresse ou mot de passe incorrect.';
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    q('DELETE FROM login_attempts WHERE email = ?', [$email]);
    return $user;
}

/** Jeton à usage unique (confirmation, nouveau mot de passe) : renvoie la valeur à mettre dans le lien. */
function create_user_token(int $userId, string $type, int $hours): string
{
    $token = random_token();
    q('INSERT INTO user_tokens (user_id, type, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
        [$userId, $type, token_hash($token), date('Y-m-d H:i:s', time() + $hours * 3600), now()]);
    return $token;
}

/** Utilise un jeton valide une seule fois ; renvoie le compte concerné. */
function consume_user_token(string $type, string $token): ?int
{
    $row = q('SELECT * FROM user_tokens WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > ?',
        [token_hash($token), $type, now()])->fetch();
    if (!$row) {
        return null;
    }
    q('UPDATE user_tokens SET used_at = ? WHERE id = ?', [now(), $row['id']]);
    return (int)$row['user_id'];
}

function send_verification_mail(array $user): void
{
    $token = create_user_token((int)$user['id'], 'verify', 72);
    send_mail($user['email'], 'Confirmez votre adresse',
        "Bonjour {$user['name']},\n\nPour confirmer votre adresse et activer votre compte Suivi des animaux, ouvrez ce lien :\n"
        . url('/confirmer?t=' . $token) . "\n\nCe lien est valable 3 jours." . mail_footer());
}

function send_password_reset_mail(array $user): void
{
    $token = create_user_token((int)$user['id'], 'reset', 2);
    send_mail($user['email'], 'Nouveau mot de passe',
        "Bonjour {$user['name']},\n\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable 2 heures) :\n"
        . url('/nouveau-mot-de-passe?t=' . $token)
        . "\n\nSi vous n’avez rien demandé, ignorez ce message : votre mot de passe actuel reste valable." . mail_footer());
}
