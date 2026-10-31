<?php
declare(strict_types=1);

// ===== API du logiciel et de l'application (voir docs/synchronisation-par-le-site.md) =====

/** POST /api/connexion : adresse e-mail et mot de passe du site → jeton de l'appareil. */
function page_api_login(): void
{
    api_require_method('POST');
    $result = attempt_login(api_input('email'), api_input('password'));
    if (is_string($result)) {
        $tooMany = str_starts_with($result, 'Trop');
        api_error($tooMany ? 429 : 401, $tooMany ? 'trop_essais' : 'identifiants', $result);
    }
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $result['id']]);
    api_login_response((int)$result['id'], api_input('device'));
}

/** POST /api/code : code de connexion généré par le site (XXXX-XXXX, 15 minutes, usage unique) → jeton de l'appareil. */
function page_api_code(): void
{
    api_require_method('POST');
    if (too_many_code_attempts()) {
        api_error(429, 'trop_essais', 'Trop d’essais : réessayez dans quelques minutes.');
    }
    $code = normalize_device_code(api_input('code'));
    $userId = $code === '' ? null : consume_user_token('appareil', $code);
    if ($userId === null || !q('SELECT id FROM users WHERE id = ? AND disabled_at IS NULL', [$userId])->fetch()) {
        record_code_attempt();
        api_error(401, 'code', 'Ce code n’est pas valable (déjà utilisé ou expiré) : générez-en un nouveau sur le site.');
    }
    api_login_response($userId, api_input('device'));
}

/** GET /api/structures : structures du compte de l'appareil, avec ses rôles. */
function page_api_organisations(): void
{
    api_require_method('GET');
    $device = api_device();
    api_json(['email' => $device['email'], 'name' => $device['user_name'], 'organisations' => api_organisations((int)$device['user_id'])]);
}

/** POST /api/deconnexion : l'appareil oublie son jeton (bouton « Se déconnecter » du logiciel). */
function page_api_logout(): void
{
    api_require_method('POST');
    $device = api_device();
    revoke_device((int)$device['user_id'], (int)$device['id']);
    api_json(['ok' => true]);
}

/** GET /api/sync/liste?o=ID&dossier=… : noms des fichiers d'un dossier de la structure. */
function page_api_sync_list(): void
{
    api_require_method('GET');
    $organisation = api_sync_organisation(api_device());
    $folder = api_input('dossier');
    if ($folder !== '' && !sync_path_is_valid($folder)) {
        api_error(400, 'chemin', 'Nom de dossier non valable.');
    }
    api_json(['files' => sync_list((int)$organisation['id'], $folder)]);
}

/** GET /api/sync/fichier?o=ID&chemin=… : contenu brut (404 si absent) ; POST : corps brut = nouveau contenu. */
function page_api_sync_file(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET' && $method !== 'POST') {
        api_require_method('GET');
    }
    $device = api_device();
    $organisation = api_sync_organisation($device);
    $id = (int)$organisation['id'];
    $path = api_input('chemin');
    if (!sync_path_is_valid($path)) {
        api_error(400, 'chemin', 'Nom de fichier non valable.');
    }
    $file = sync_root($id) . "/$path";

    if ($method === 'GET') {
        if (!is_file($file)) {
            api_error(404, 'absent', 'Fichier absent.');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-store');
        readfile($file);
        exit;
    }

    $licence = organisation_licence($id);
    if ($licence && in_array($licence['status'], SYNC_READ_ONLY_STATUSES, true)) {
        api_error(403, 'licence', 'La licence de la structure est ' . mb_strtolower(LICENCE_STATUSES[$licence['status']])
            . ' : les données restent lisibles, mais plus rien n’est envoyé. Voir l’espace client du site.');
    }
    // Au-delà de post_max_size, PHP vide le corps : refuser plutôt qu'écrire un fichier vide.
    $max = min((int)config('sync.max_bytes', SYNC_MAX_BYTES_DEFAULT), ini_bytes((string)ini_get('post_max_size')) ?: PHP_INT_MAX);
    $announced = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : null;
    if ($announced !== null && $announced > $max) {
        api_error(413, 'taille', 'Fichier trop volumineux.');
    }
    if (is_dir($file)) {
        api_error(400, 'chemin', 'Ce chemin est un dossier.');
    }
    $size = sync_write_from_input($id, $path, $max, $announced);
    if ($size === null) {
        api_error(413, 'taille', 'Fichier trop volumineux.');
    }
    if ($size < 0) {
        api_error(400, 'incomplet', 'Envoi incomplet : réessayez.');
    }
    api_json(['ok' => true, 'size' => $size]);
}
