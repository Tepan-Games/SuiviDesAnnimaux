<?php
declare(strict_types=1);

// Serveur de test de PHP (php -S) : les fichiers existants (feuille de style, images) sont servis tels quels.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) {
    return false;
}

// Seul fichier PHP public : tout le code est hors du dossier www (inaccessible depuis le web).
require __DIR__ . '/../app/bootstrap.php';
foreach (['public', 'account', 'organisation', 'support', 'admin'] as $pages) {
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
    // Administration
    '/admin' => 'page_admin_dashboard',
    '/admin/clients' => 'page_admin_clients',
    '/admin/client' => 'page_admin_client',
    '/admin/utilisateurs' => 'page_admin_users',
    '/admin/assistance' => 'page_admin_support',
    '/admin/ticket' => 'page_admin_ticket',
    '/admin/journal' => 'page_admin_log',
];

if (!isset($routes[$path])) {
    not_found();
}
try {
    $routes[$path]();
} catch (PDOException $exception) {
    error_log((string)$exception);
    http_response_code(500);
    view('error', ['message' => 'Le service est momentanément indisponible. Réessayez dans quelques minutes.'], 'Erreur');
}
