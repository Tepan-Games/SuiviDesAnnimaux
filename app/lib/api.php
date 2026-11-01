<?php
declare(strict_types=1);

// ===== API du logiciel et de l'application : réponses JSON, jeton « Authorization: Bearer », stockage de synchronisation =====
// Pas de session ni de cookie : chaque appel porte le jeton de l'appareil (donc pas de CSRF possible).

/** Taille maximale d'un fichier de synchronisation (photos et documents compris). */
const SYNC_MAX_BYTES_DEFAULT = 30 * 1024 * 1024;

/** Licences dont les structures ne peuvent plus envoyer de données (la lecture reste possible). */
const SYNC_READ_ONLY_STATUSES = ['suspendue', 'expiree', 'resiliee'];

function api_json(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Erreur : code stable pour le logiciel, message en français pour la personne. */
function api_error(int $status, string $code, string $message): never
{
    api_json(['error' => $code, 'message' => $message], $status);
}

function api_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header("Allow: $method");
        api_error(405, 'methode', 'Méthode non autorisée.');
    }
}

/** Champ envoyé en JSON (Content-Type: application/json) ou en formulaire. */
function api_input(string $name): string
{
    static $json = null;
    if ($json === null) {
        $json = [];
        if (str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            $decoded = json_decode((string)file_get_contents('php://input'), true);
            $json = is_array($decoded) ? $decoded : [];
        }
    }
    $value = $json[$name] ?? $_POST[$name] ?? $_GET[$name] ?? '';
    // Les nombres d'un corps JSON (identifiants) sont acceptés comme du texte.
    return is_string($value) ? trim($value) : (is_int($value) || is_float($value) ? (string)$value : '');
}

/** Jeton de l'en-tête Authorization (Apache le transmet par la variable posée dans www/.htaccess). */
function api_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    return preg_match('/^Bearer\s+([0-9a-f]{48})$/i', trim($header), $match) ? strtolower($match[1]) : '';
}

/** Appareil connecté de l'appel (401 sinon) ; note sa dernière activité. */
function api_device(): array
{
    $device = find_device(api_bearer_token());
    if (!$device) {
        api_error(401, 'jeton', 'Cet appareil n’est plus connecté : reconnectez-le au site.');
    }
    if ($device['last_seen_at'] === null || strtotime($device['last_seen_at']) < time() - 60) {
        q('UPDATE devices SET last_seen_at = ? WHERE id = ?', [now(), $device['id']]);
    }
    return $device;
}

/** Structures du compte, telles que le logiciel les reçoit. */
function api_organisations(int $userId): array
{
    return array_map(fn(array $o) => [
        'id' => (int)$o['id'],
        'name' => $o['name'],
        'kind' => $o['kind'],
        'roles' => array_values(array_filter(explode(',', (string)$o['roles']))),
        'licence' => $o['licence_status'] ?? null,
        'plan' => $o['plan_code'] ?? null,
    ], user_organisations($userId));
}

/** Réponse d'une connexion réussie : jeton de l'appareil (une seule fois), compte et structures. */
function api_login_response(int $userId, string $deviceName): never
{
    $user = q('SELECT id, email, name FROM users WHERE id = ?', [$userId])->fetch();
    $token = create_device($userId, $deviceName);
    api_json(['token' => $token, 'email' => $user['email'], 'name' => $user['name'], 'organisations' => api_organisations($userId)]);
}

/** Trop de codes de connexion faux depuis cette adresse IP (15 minutes). */
function too_many_code_attempts(): bool
{
    $since = date('Y-m-d H:i:s', time() - 15 * 60);
    return (int)q("SELECT COUNT(*) FROM login_attempts WHERE email = '#code-appareil' AND ip = ? AND created_at > ?", [client_ip(), $since])->fetchColumn() >= 10;
}

function record_code_attempt(): void
{
    q("INSERT INTO login_attempts (email, ip, created_at) VALUES ('#code-appareil', ?, ?)", [client_ip(), now()]);
}

/** Taille d'un réglage PHP (« 8M », « 1G »…) en octets ; 0 = sans limite. */
function ini_bytes(string $value): int
{
    $value = trim($value);
    $number = (int)$value;
    return match (strtoupper(substr($value, -1))) {
        'G' => $number * 1024 ** 3,
        'M' => $number * 1024 ** 2,
        'K' => $number * 1024,
        default => $number,
    };
}

// ===== Stockage de synchronisation : storage/sync/<id structure>/<chemin> =====

/** Chemin relatif sûr : segments de lettres, chiffres, « . », « _ », « - », sans segment caché ni « .. ». */
function sync_path_is_valid(string $path): bool
{
    return strlen($path) <= 255 && preg_match('#^[A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$#D', $path) === 1;
}

function sync_root(int $organisationId): string
{
    return STORAGE_DIR . '/sync/' . $organisationId;
}

/** Structure de l'appel (paramètre o) dont le compte de l'appareil est membre (404 sinon : ne révèle pas les autres). */
function api_sync_organisation(array $device): array
{
    $organisation = organisation_for_member((int)api_input('o'), (int)$device['user_id']);
    if (!$organisation) {
        api_error(404, 'structure', 'Structure introuvable, ou vous n’en êtes plus membre.');
    }
    return $organisation;
}

/** Noms des fichiers d'un dossier ('' : racine), sans les sous-dossiers ni les écritures en cours. */
function sync_list(int $organisationId, string $folder): array
{
    $dir = sync_root($organisationId) . ($folder === '' ? '' : "/$folder");
    if (!is_dir($dir)) {
        return [];
    }
    $names = [];
    foreach (scandir($dir) ?: [] as $name) {
        if ($name[0] !== '.' && is_file("$dir/$name")) {
            $names[] = $name;
        }
    }
    return $names;
}

/**
 * Écrit le corps brut de la requête dans le fichier (écriture à part puis renommage : un autre appareil ne lit jamais
 * un fichier à moitié écrit). Renvoie la taille écrite, null si le fichier dépasse la taille permise, -1 si le corps reçu
 * n'a pas la taille annoncée (envoi coupé) : l'ancien contenu est alors gardé.
 */
function sync_write_from_input(int $organisationId, string $path, int $maxBytes, ?int $expectedBytes = null): ?int
{
    $target = sync_root($organisationId) . "/$path";
    $dir = dirname($target);
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException("Dossier impossible à créer : $dir");
    }
    $temp = $dir . '/.ecriture-' . bin2hex(random_bytes(8));
    $in = fopen('php://input', 'rb');
    $out = fopen($temp, 'wb');
    $size = stream_copy_to_stream($in, $out, $maxBytes + 1);
    fclose($in);
    fclose($out);
    if ($size === false || $size > $maxBytes) {
        @unlink($temp);
        return null;
    }
    if ($expectedBytes !== null && $size !== $expectedBytes) {
        @unlink($temp);
        return -1;
    }
    if (!rename($temp, $target)) {
        @unlink($temp);
        throw new RuntimeException("Écriture impossible : $target");
    }
    return $size;
}
