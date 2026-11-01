<?php
declare(strict_types=1);

/** Valeur de configuration (clés séparées par des points : « db.dsn »). */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Connexion MySQL partagée (requêtes préparées seulement). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(config('db.dsn'), config('db.user'), config('db.password'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

/** Exécute une requête préparée. */
function q(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Échappement HTML de tout texte affiché. */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    return rtrim((string)config('base_url'), '/') . $path;
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** Message affiché une fois, à la page suivante. */
function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $name, string $default = ''): string
{
    $value = $_POST[$name] ?? $_GET[$name] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

// ===== Protection des formulaires (CSRF) =====

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Refuse tout envoi de formulaire sans le jeton de la session. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        exit('Formulaire expiré : revenez en arrière et rechargez la page.');
    }
}

// ===== Jetons =====

function random_token(): string
{
    return bin2hex(random_bytes(24));
}

function token_hash(string $token): string
{
    return hash('sha256', $token);
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** Journal des actions importantes. */
function audit(string $action, string $details = '', ?int $userId = null): void
{
    q('INSERT INTO audit_log (user_id, action, details, ip, created_at) VALUES (?, ?, ?, ?, ?)',
        [$userId ?? current_user()['id'] ?? null, $action, mb_substr($details, 0, 500), client_ip(), now()]);
}

// ===== Affichage =====

/** Page dans la mise en page commune. */
function view(string $template, array $data = [], string $title = ''): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_ROOT . "/templates/$template.php";
    $content = ob_get_clean();
    $pageTitle = $title;
    require APP_ROOT . '/templates/layout.php';
}

function not_found(): never
{
    http_response_code(404);
    view('404', [], 'Page introuvable');
    exit;
}

function format_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '—';
    }
    $time = strtotime($date);
    return $withTime ? date('d/m/Y à H:i', $time) : date('d/m/Y', $time);
}

function format_price(int $cents): string
{
    return $cents === 0 ? 'Gratuit' : number_format($cents / 100, 2, ',', ' ') . ' €';
}

/**
 * Types de structure (payantes), comme dans le logiciel (SpaceRegistry.ProfessionalKinds). Le particulier et la famille
 * d'accueil sont gratuits et n'ont pas de structure sur le site (règle du 01/11/2026) : la famille d'accueil est un
 * particulier membre d'une structure avec le rôle « accueil ». Une ancienne structure « Famille d’accueil » garde son type.
 */
const ORGANISATION_KINDS = ['Association', 'Pension', 'Élevage', 'Magasin', 'Autre'];

/** Comptes gratuits, sans structure (choix de l'inscription). */
const FREE_KINDS = ['Particulier', 'Famille d’accueil'];

/** Rôles (clés identiques au logiciel). */
const ROLES = [
    'responsable' => ['Responsable', 'Tout, et inviter ou retirer des membres'],
    'soins' => ['Bénévole soins', 'Animaux, soins, traitements, pesées, photos, sorties de stock'],
    'accueil' => ['Famille d’accueil', 'Seulement les animaux qu’elle accueille'],
    'tresorier' => ['Trésorier', 'Dépenses, frais, adoptions, assurance'],
    'consultation' => ['Consultation', 'Tout voir, ne rien modifier'],
];

function role_labels(string $roles): string
{
    $labels = [];
    foreach (explode(',', $roles) as $key) {
        if (isset(ROLES[$key])) {
            $labels[] = ROLES[$key][0];
        }
    }
    return implode(', ', $labels);
}

/** Rôles cochés dans un formulaire (seulement les clés connues). */
function posted_roles(): string
{
    $roles = array_values(array_intersect(array_keys(ROLES), (array)($_POST['roles'] ?? [])));
    return implode(',', $roles);
}
