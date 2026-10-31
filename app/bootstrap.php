<?php
declare(strict_types=1);

// Point de départ commun : configuration, erreurs, session sécurisée, fonctions du site.
const APP_ROOT = __DIR__;
const STORAGE_DIR = __DIR__ . '/../storage';

$configFile = getenv('SUIVI_SITE_CONFIG') ?: APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration manquante : copier app/config.example.php en app/config.php.');
}
$GLOBALS['config'] = require $configFile;
foreach (['helpers', 'mail', 'auth', 'organisations', 'licences', 'tickets', 'devices', 'api', 'newsletter', 'online'] as $lib) {
    require APP_ROOT . "/lib/$lib.php";
}

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');
ini_set('display_errors', config('debug') ? '1' : '0');
error_reporting(E_ALL);
if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0750, true);
    // Si l'hébergement publie la racine du dépôt (déploiement Git), le dossier reste inaccessible depuis le web.
    file_put_contents(STORAGE_DIR . '/.htaccess', "Require all denied\n");
}
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_DIR . '/php-errors.log');

// L'API (/api/…) n'a ni session ni cookie : chaque appel porte le jeton de l'appareil.
$GLOBALS['is_api'] = str_starts_with((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/api/');
if (PHP_SAPI !== 'cli' && $GLOBALS['is_api']) {
    header('X-Content-Type-Options: nosniff');
} elseif (PHP_SAPI !== 'cli') {
    $secure = str_starts_with((string)config('base_url'), 'https://');
    session_name('suivi_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
}
