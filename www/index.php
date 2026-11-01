<?php
declare(strict_types=1);

// Serveur de test de PHP (php -S) : les fichiers existants (feuille de style, images) sont servis tels quels.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) {
    return false;
}

// Seul fichier PHP public : tout le code est hors du dossier www (inaccessible depuis le web).
require __DIR__ . '/../app/bootstrap.php';
foreach (['public', 'account', 'organisation', 'support', 'admin', 'devices', 'api', 'newsletter', 'links'] as $pages) {
    require APP_ROOT . "/pages/$pages.php";
}

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

$routes = [
    // Vitrine
    '/' => 'page_home',
    '/fonctionnalites' => 'page_features',
    '/tarifs' => 'page_pricing',
    '/telecharger' => 'page_download',
    '/contact' => 'page_contact',
    '/mentions-legales' => 'page_legal',
    '/confidentialite' => 'page_privacy',
    '/desinscription' => 'page_unsubscribe',
    // Comptes
    '/inscription' => 'page_register',
    '/connexion' => 'page_login',
    '/deconnexion' => 'page_logout',
    '/confirmer' => 'page_confirm',
    '/mot-de-passe-oublie' => 'page_forgot_password',
    '/nouveau-mot-de-passe' => 'page_reset_password',
    // Espace client
    '/compte' => 'page_account',
    '/compte/profil' => 'page_profile',
    '/compte/structure/nouvelle' => 'page_organisation_new',
    '/compte/structure' => 'page_organisation',
    '/invitation' => 'page_invitation',
    '/compte/assistance' => 'page_support',
    '/compte/demande' => 'page_ticket',
    '/application' => 'page_online_app',
    '/compte/appareils' => 'page_devices',
    '/compte/suivis' => 'page_links',
    '/compte/suivi' => 'page_link',
    '/compte/suivi/photo' => 'page_link_photo',
    '/suivi' => 'page_follow',
    // Administration
    '/admin' => 'page_admin_dashboard',
    '/admin/clients' => 'page_admin_clients',
    '/admin/client' => 'page_admin_client',
    '/admin/utilisateurs' => 'page_admin_users',
    '/admin/assistance' => 'page_admin_support',
    '/admin/ticket' => 'page_admin_ticket',
    '/admin/lettres' => 'page_admin_newsletters',
    '/admin/lettre' => 'page_admin_newsletter',
    '/admin/journal' => 'page_admin_log',
    // API du logiciel et de l'application (JSON, jeton « Authorization: Bearer »)
    '/api/connexion' => 'page_api_login',
    '/api/code' => 'page_api_code',
    '/api/deconnexion' => 'page_api_logout',
    '/api/structures' => 'page_api_organisations',
    '/api/sync/liste' => 'page_api_sync_list',
    '/api/sync/fichier' => 'page_api_sync_file',
    '/api/liens' => 'page_api_links',
    '/api/liens/messages' => 'page_api_link_messages',
    '/api/liens/repondre' => 'page_api_link_reply',
    '/api/liens/fiche' => 'page_api_link_card',
    '/api/liens/fermer' => 'page_api_link_close',
    '/api/liens/photo' => 'page_api_link_photo',
    '/api/suivi' => 'page_api_follow',
    '/api/suivi/message' => 'page_api_follow_message',
    '/api/suivi/photo' => 'page_api_follow_photo',
];

if (!isset($routes[$path])) {
    $GLOBALS['is_api'] ? api_error(404, 'adresse', 'Adresse inconnue.') : not_found();
}
try {
    $routes[$path]();
} catch (PDOException|RuntimeException $exception) {
    error_log((string)$exception);
    if ($GLOBALS['is_api']) {
        api_error(503, 'indisponible', 'Le service est momentanément indisponible. Réessayez dans quelques minutes.');
    }
    http_response_code(500);
    view('error', ['message' => 'Le service est momentanément indisponible. Réessayez dans quelques minutes.'], 'Erreur');
}
