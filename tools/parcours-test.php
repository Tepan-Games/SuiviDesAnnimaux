<?php
declare(strict_types=1);

// Parcours complet du site de test (lancé par tools/test-local.ps1) : vitrine, inscription, confirmation, structure,
// invitation, assistance, administration, sécurité. S'arrête à la première erreur (code de sortie 1).
require __DIR__ . '/../app/bootstrap.php';

const BASE = 'http://localhost:8080';
$checks = 0;

function check(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) {
        fwrite(STDERR, "ÉCHEC : $message\n");
        exit(1);
    }
}

/** Requête HTTP avec un « navigateur » (fichier de cookies) ; renvoie [code, corps, adresse de redirection]. */
function http(string $browser, string $path, array $post = null): array
{
    $curl = curl_init(str_starts_with($path, 'http') ? $path : BASE . $path);
    $jar = STORAGE_DIR . "/cookies-$browser.txt";
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
    ]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string)curl_exec($curl);
    $result = [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $body, (string)curl_getinfo($curl, CURLINFO_REDIRECT_URL)];
    curl_close($curl);
    return $result;
}

/** Page puis jeton CSRF de son formulaire. */
function csrf(string $browser, string $path): string
{
    [, $body] = http($browser, $path);
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $body, $match);
    check(isset($match[1]), "Jeton CSRF absent de $path");
    return $match[1];
}

/** Appel de l'API : [code, corps décodé (JSON) ou brut, en-têtes]. $body : tableau → JSON, chaîne → corps brut. */
function api(string $method, string $path, array|string|null $body = null, string $token = ''): array
{
    $curl = curl_init(BASE . $path);
    $headers = $token !== '' ? ["Authorization: Bearer $token"] : [];
    if (is_array($body)) {
        $headers[] = 'Content-Type: application/json';
        $body = json_encode($body);
    } elseif ($body !== null) {
        $headers[] = 'Content-Type: application/octet-stream';
    }
    $responseHeaders = '';
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$responseHeaders) { $responseHeaders .= $line; return strlen($line); },
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    }
    $raw = (string)curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $json = str_contains($responseHeaders, 'application/json') ? json_decode($raw, true) : null;
    return [$code, $json ?? $raw, $responseHeaders];
}

/** Dernier lien d'un type dans les e-mails de test. */
function last_mail_link(string $pattern): string
{
    $log = (string)@file_get_contents(STORAGE_DIR . '/mails.log');
    preg_match_all('#' . preg_quote(BASE, '#') . $pattern . '[^\s]+#', $log, $matches);
    check(!empty($matches[0]), "Aucun e-mail contenant $pattern");
    return end($matches[0]);
}

foreach (glob(STORAGE_DIR . '/cookies-*.txt') ?: [] as $file) {
    unlink($file);
}

// ===== Vitrine =====
foreach (['/' => 'Un accès adapté à chacun', '/fonctionnalites' => 'Fiche de chaque animal', '/tarifs' => '19,00 €', '/telecharger' => 'Windows 10 et 11',
             '/contact' => 'Contact', '/mentions-legales' => 'OVH', '/confidentialite' => 'sous-traitant'] as $path => $text) {
    [$code, $body] = http('visiteur', $path);
    check($code === 200 && str_contains($body, $text), "Page $path ($code)");
}
[$code] = http('visiteur', '/page-qui-n-existe-pas');
check($code === 404, 'Page inconnue : 404 attendu');
[$code, $body] = http('visiteur', '/assets/site.css');
check($code === 200 && str_contains($body, '--vert'), 'Feuille de style');

// ===== Inscription d'une association, confirmation =====
$token = csrf('a', '/inscription');
[$code] = http('a', '/inscription', ['csrf' => $token, 'kind' => 'Association', 'organisation' => 'Asso Test', 'name' => 'Alice Responsable',
    'email' => 'Alice@Test.fr', 'password' => 'motdepasse-solide', 'cgu' => '1', 'newsletter' => '1']);
check($code === 302, 'Inscription : redirection attendue');
[$code, $body] = http('a', '/compte');
check($code === 200 && str_contains($body, 'Asso Test') && str_contains($body, 'Confirmez votre adresse'), 'Tableau de bord après inscription');
$organisationId = (int)q("SELECT id FROM organisations WHERE name = 'Asso Test'")->fetchColumn();
check($organisationId > 0 && q('SELECT plan_code FROM licences WHERE organisation_id = ?', [$organisationId])->fetchColumn() === 'decouverte',
    'Structure créée avec la licence Découverte');
http('a', last_mail_link('/confirmer\?t='));
check(q("SELECT email_verified_at FROM users WHERE email = 'alice@test.fr'")->fetchColumn() !== null, 'Adresse confirmée par le lien');
[$code] = http('a', last_mail_link('/confirmer\?t='));
check($code === 302 && q("SELECT COUNT(*) FROM user_tokens WHERE type = 'verify' AND used_at IS NULL")->fetchColumn() == 0, 'Lien de confirmation à usage unique');

// Doublon refusé
$token = csrf('visiteur', '/inscription');
[, $body] = http('visiteur', '/inscription', ['csrf' => $token, 'kind' => 'Particulier', 'name' => 'Autre', 'email' => 'alice@test.fr', 'password' => 'motdepasse-solide', 'cgu' => '1']);
check(str_contains($body, 'Un compte existe déjà'), 'Adresse déjà utilisée refusée');

// ===== Invitation d'un bénévole, inscription par le lien, acceptation =====
$token = csrf('a', "/compte/structure?id=$organisationId");
[$code, $body] = http('a', "/compte/structure?id=$organisationId", ['csrf' => $token, 'action' => 'inviter', 'email' => 'bob@test.fr', 'roles' => ['soins', 'tresorier']]);
check($code === 200 && str_contains($body, '/invitation?c='), 'Lien d’invitation affiché');
$invitation = last_mail_link('/invitation\?c=');
[$code, $body] = http('b', $invitation);
check($code === 200 && str_contains($body, 'Asso Test') && str_contains($body, 'Créer mon compte'), 'Invitation vue sans compte');
$token = csrf('b', '/inscription?email=bob@test.fr');
[$code, , $location] = http('b', '/inscription', ['csrf' => $token, 'kind' => 'Particulier', 'name' => 'Bob Bénévole', 'email' => 'bob@test.fr',
    'password' => 'autre-motdepasse', 'cgu' => '1']);
check($code === 302 && str_contains($location, '/invitation?c='), 'Retour à l’invitation après l’inscription');
$token = csrf('b', $location);
http('b', '/invitation', ['csrf' => $token, 'c' => substr($location, strpos($location, 'c=') + 2)]);
$bobId = (int)q("SELECT id FROM users WHERE email = 'bob@test.fr'")->fetchColumn();
check(q('SELECT roles FROM memberships WHERE organisation_id = ? AND user_id = ?', [$organisationId, $bobId])->fetchColumn() === 'soins,tresorier',
    'Bénévole membre avec ses rôles');
[$code] = http('b', $invitation);
check($code === 200 && str_contains(http('b', $invitation)[1], 'plus valable'), 'Invitation à usage unique');

// Bob (non responsable) ne peut pas gérer les membres
$token = csrf('b', "/compte/structure?id=$organisationId");
[$code] = http('b', "/compte/structure?id=$organisationId", ['csrf' => $token, 'action' => 'inviter', 'email' => 'x@test.fr', 'roles' => ['soins']]);
check($code === 403, 'Gestion des membres réservée aux responsables');

// ===== Assistance =====
$token = csrf('b', '/compte/assistance');
[$code, , $location] = http('b', '/compte/assistance', ['csrf' => $token, 'category' => 'Synchronisation', 'organisation' => (string)$organisationId,
    'subject' => 'La synchronisation ne se fait pas', 'body' => 'Depuis hier, mon téléphone ne reçoit plus les pesées.']);
$ticketId = (int)q('SELECT id FROM tickets WHERE user_id = ?', [$bobId])->fetchColumn();
check($code === 302 && $ticketId > 0, 'Demande d’assistance créée');
[$code] = http('a', "/compte/demande?id=$ticketId");
check($code === 404, 'Demande d’un autre compte invisible');

// ===== Administration =====
[$code] = http('b', '/admin');
check($code === 403, 'Administration refusée aux comptes ordinaires');
q("UPDATE users SET is_admin = 1 WHERE email = 'alice@test.fr'");
[$code, $body] = http('a', '/admin');
check($code === 200 && str_contains($body, 'Demandes à traiter') && str_contains($body, 'La synchronisation ne se fait pas'), 'Tableau de bord de l’administration');
[, $body] = http('a', '/admin/clients?q=Asso');
check(str_contains($body, 'Asso Test'), 'Liste des clients');
$token = csrf('a', "/admin/client?id=$organisationId");
http('a', "/admin/client?id=$organisationId", ['csrf' => $token, 'plan' => 'association', 'status' => 'active', 'billing' => 'an',
    'starts_at' => '2026-11-01', 'ends_at' => '2027-10-31', 'notes' => 'Virement reçu']);
$licence = q('SELECT * FROM licences WHERE organisation_id = ?', [$organisationId])->fetch();
check($licence['plan_code'] === 'association' && $licence['billing'] === 'an' && $licence['ends_at'] === '2027-10-31', 'Licence modifiée par l’administration');
[, $body] = http('b', "/compte/structure?id=$organisationId");
check(str_contains($body, 'Association') && str_contains($body, '31/10/2027'), 'Licence visible par le membre');
$token = csrf('a', "/admin/ticket?id=$ticketId");
http('a', "/admin/ticket?id=$ticketId", ['csrf' => $token, 'body' => 'Pouvez-vous nous envoyer la version de l’application ?', 'status' => '', 'priority' => 'haute']);
$ticket = q('SELECT * FROM tickets WHERE id = ?', [$ticketId])->fetch();
check($ticket['status'] === 'attente' && $ticket['priority'] === 'haute', 'Réponse de l’équipe : demande en attente du client');
check(str_contains((string)file_get_contents(STORAGE_DIR . '/mails.log'), "Réponse à votre demande n°$ticketId"), 'E-mail de réponse au client');
$token = csrf('b', "/compte/demande?id=$ticketId");
http('b', "/compte/demande?id=$ticketId", ['csrf' => $token, 'body' => 'Version 0.20.0 sur Android.']);
check(q('SELECT status FROM tickets WHERE id = ?', [$ticketId])->fetchColumn() === 'ouvert'
      && (int)q('SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = ?', [$ticketId])->fetchColumn() === 3, 'Réponse du client : demande rouverte');
[, $body] = http('a', '/admin/journal');
check(str_contains($body, 'licence.modification') && str_contains($body, 'invitation.acceptation'), 'Journal des actions');

// ===== Lettre d'information =====
check(q("SELECT newsletter_at FROM users WHERE email = 'alice@test.fr'")->fetchColumn() !== null
      && q('SELECT newsletter_at FROM users WHERE id = ?', [$bobId])->fetchColumn() === null, 'Abonnement seulement si la case est cochée à l’inscription');
$log = (string)file_get_contents(STORAGE_DIR . '/mails.log');
check(str_contains($log, 'De : Suivi des animaux <noreply@suivi-des-animaux.fr>') && str_contains($log, 'Répondre à : support@suivi-des-animaux.fr'),
    'E-mails automatiques : expéditeur noreply@, réponses vers support@');
$token = csrf('b', '/compte/profil');
http('b', '/compte/profil', ['csrf' => $token, 'name' => 'Bob Bénévole', 'newsletter' => '1']);
check(q('SELECT newsletter_at FROM users WHERE id = ?', [$bobId])->fetchColumn() !== null, 'Abonnement depuis le profil');
check(newsletter_subscriber_count() === 1, 'Adresse non confirmée : pas encore destinataire');
q('UPDATE users SET email_verified_at = ? WHERE id = ?', [now(), $bobId]);
q("INSERT INTO users (email, name, password_hash, email_verified_at, newsletter_at, created_at) VALUES ('carol@test.fr', 'Carol', 'x', NOW(), NOW(), NOW()),
   ('dan@test.fr', 'Dan', 'x', NOW(), NULL, NOW())");
check(newsletter_subscriber_count() === 3, 'Trois abonnés confirmés');
[$code] = http('b', '/admin/lettres');
check($code === 403, 'Lettres réservées à l’administration');
$token = csrf('a', '/admin/lettres');
[$code, , $location] = http('a', '/admin/lettres', ['csrf' => $token]);
$letterId = (int)q('SELECT MAX(id) FROM newsletters')->fetchColumn();
check($code === 302 && str_contains($location, "/admin/lettre?id=$letterId"), 'Nouvelle lettre créée');
[, $body] = http('a', "/admin/lettre?id=$letterId");
check(str_contains($body, 'Voici les nouveautés') && str_contains($body, 'Bonjour Alice Responsable'), 'Modèle de lettre et aperçu personnalisé');
$token = csrf('a', "/admin/lettre?id=$letterId");
[, $body] = http('a', "/admin/lettre?id=$letterId", ['csrf' => $token, 'action' => 'enregistrer', 'subject' => 'x', 'body' => 'court']);
check(str_contains($body, 'L’objet doit faire'), 'Lettre incomplète refusée');
$letterBody = "Bonjour {nom},\n\nLa version 3 est sortie : pesées plus rapides.\nDétails : {site}/fonctionnalites";
http('a', "/admin/lettre?id=$letterId", ['csrf' => $token, 'action' => 'essai', 'subject' => 'Novembre : la version 3', 'body' => $letterBody]);
$log = (string)file_get_contents(STORAGE_DIR . '/mails.log');
check(str_contains($log, 'À : alice@test.fr') && str_contains($log, '/desinscription?t=essai') && !str_contains($log, 'À : carol@test.fr'),
    'Essai envoyé à l’administrateur seulement');
http('a', "/admin/lettre?id=$letterId", ['csrf' => $token, 'action' => 'envoyer', 'subject' => 'Novembre : la version 3', 'body' => $letterBody]);
check(q('SELECT queued_at FROM newsletters WHERE id = ?', [$letterId])->fetchColumn() === null, 'Envoi refusé sans la case de confirmation');
http('a', "/admin/lettre?id=$letterId", ['csrf' => $token, 'action' => 'envoyer', 'subject' => 'Novembre : la version 3', 'body' => $letterBody, 'confirmation' => '1']);
$stats = newsletter_stats($letterId);
check($stats['total'] === 3 && $stats['sent'] === 2 && $stats['pending'] === 1, 'Envoi par lots (2 par lot dans le test)');
$log = (string)file_get_contents(STORAGE_DIR . '/mails.log');
check(str_contains($log, 'De : Suivi des animaux <newsletter@suivi-des-animaux.fr>') && str_contains($log, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click')
      && str_contains($log, 'Bonjour Bob Bénévole,') && str_contains($log, 'Détails : http://localhost:8080/fonctionnalites') && !str_contains($log, 'À : dan@test.fr'),
    'Lettre personnalisée, expéditeur newsletter@, en-têtes de désinscription');
[, $body] = http('a', "/admin/lettre?id=$letterId", ['csrf' => $token, 'action' => 'enregistrer', 'subject' => 'Modifiée', 'body' => $letterBody . ' modifiée']);
check(q('SELECT subject FROM newsletters WHERE id = ?', [$letterId])->fetchColumn() === 'Novembre : la version 3', 'Lettre envoyée non modifiable');
http('a', "/admin/lettre?id=$letterId", ['csrf' => $token, 'action' => 'lot']);
$stats = newsletter_stats($letterId);
check($stats['sent'] === 3 && $stats['pending'] === 0 && q('SELECT finished_at FROM newsletters WHERE id = ?', [$letterId])->fetchColumn() !== null,
    'Lot suivant : envoi terminé');
check(q('SELECT COUNT(*) FROM newsletter_deliveries WHERE token_hash IS NULL OR LENGTH(token_hash) <> 64')->fetchColumn() == 0, 'Jetons de désinscription gardés en empreinte');
// Désinscription : page de confirmation, puis bouton (sans session) ; désabonnement en un clic des messageries
preg_match_all('#À : (\S+)\n(?:(?!====).)*?/desinscription\?t=([0-9a-f]{48})#s', $log . (string)file_get_contents(STORAGE_DIR . '/mails.log'), $links, PREG_SET_ORDER);
$byEmail = [];
foreach ($links as [, $email, $link]) {
    $byEmail[$email] = $link;
}
check(isset($byEmail['bob@test.fr'], $byEmail['carol@test.fr']), 'Liens de désinscription dans les lettres');
[$code, $body] = http('visiteur', '/desinscription?t=' . $byEmail['bob@test.fr']);
check($code === 200 && str_contains($body, 'Me désinscrire') && q('SELECT newsletter_at FROM users WHERE id = ?', [$bobId])->fetchColumn() !== null,
    'Ouvrir le lien ne désinscrit pas encore');
[$code, $body] = http('visiteur', '/desinscription?t=' . $byEmail['bob@test.fr'], []);
check($code === 200 && str_contains($body, 'C’est fait') && q('SELECT newsletter_at FROM users WHERE id = ?', [$bobId])->fetchColumn() === null, 'Désinscription par le lien');
[$code, $body] = http('visiteur', '/desinscription?t=' . $byEmail['carol@test.fr'], ['List-Unsubscribe' => 'One-Click']);
check($code === 200 && q("SELECT newsletter_at FROM users WHERE email = 'carol@test.fr'")->fetchColumn() === null, 'Désinscription en un clic (messagerie)');
[$code] = http('visiteur', '/desinscription?t=' . str_repeat('0', 48), []);
[$code2] = http('visiteur', '/desinscription?t=essai');
check($code === 404 && $code2 === 404, 'Lien de désinscription inconnu');
check(newsletter_stats($letterId)['unsubscribed'] === 2 && newsletter_subscriber_count() === 1, 'Désinscriptions comptées');
[, $body] = http('a', '/admin/lettres');
check(str_contains($body, 'Novembre : la version 3') && str_contains($body, '3 / 3'), 'Liste des lettres');

// ===== Membres : rôles, retrait, dernier responsable =====
$token = csrf('a', "/compte/structure?id=$organisationId");
http('a', "/compte/structure?id=$organisationId", ['csrf' => $token, 'action' => 'roles', 'member' => (string)$bobId, 'roles' => ['consultation']]);
check(q('SELECT roles FROM memberships WHERE user_id = ?', [$bobId])->fetchColumn() === 'consultation', 'Rôles changés');
$aliceId = (int)q("SELECT id FROM users WHERE email = 'alice@test.fr'")->fetchColumn();
http('a', "/compte/structure?id=$organisationId", ['csrf' => $token, 'action' => 'retirer', 'member' => (string)$aliceId]);
check((int)q('SELECT COUNT(*) FROM memberships WHERE user_id = ?', [$aliceId])->fetchColumn() === 1, 'Le dernier responsable ne peut pas être retiré');
http('a', "/compte/structure?id=$organisationId", ['csrf' => $token, 'action' => 'retirer', 'member' => (string)$bobId]);
check((int)q('SELECT COUNT(*) FROM memberships WHERE user_id = ?', [$bobId])->fetchColumn() === 0, 'Membre retiré');

// ===== Sécurité : formulaire sans jeton, mots de passe =====
[$code] = http('a', '/compte/profil', ['name' => 'Pirate']);
check($code === 400, 'Envoi sans jeton CSRF refusé');
$token = csrf('c', '/connexion');
for ($i = 0; $i < 5; $i++) {
    http('c', '/connexion', ['csrf' => $token, 'email' => 'bob@test.fr', 'password' => 'faux']);
}
[, $body] = http('c', '/connexion', ['csrf' => $token, 'email' => 'bob@test.fr', 'password' => 'autre-motdepasse']);
check(str_contains($body, 'Trop d’essais'), 'Essais de connexion limités');
q('DELETE FROM login_attempts');
$token = csrf('d', '/mot-de-passe-oublie');
[, $body] = http('d', '/mot-de-passe-oublie', ['csrf' => $token, 'email' => 'bob@test.fr']);
check(str_contains($body, 'Si un compte existe'), 'Mot de passe oublié : même réponse pour toutes les adresses');
$reset = last_mail_link('/nouveau-mot-de-passe\?t=');
$token = csrf('d', $reset);
http('d', '/nouveau-mot-de-passe', ['csrf' => $token, 't' => substr($reset, strpos($reset, 't=') + 2), 'password' => 'nouveau-motdepasse']);
$token = csrf('d', '/connexion');
[$code, , $location] = http('d', '/connexion', ['csrf' => $token, 'email' => 'bob@test.fr', 'password' => 'nouveau-motdepasse']);
check($code === 302 && str_ends_with($location, '/compte'), 'Connexion avec le nouveau mot de passe');
$hash = q("SELECT password_hash FROM users WHERE email = 'bob@test.fr'")->fetchColumn();
check(str_starts_with((string)$hash, '$2y$') || str_starts_with((string)$hash, '$argon'), 'Mot de passe haché');

// ===== Contact =====
$token = csrf('visiteur', '/contact');
[, $body] = http('visiteur', '/contact', ['csrf' => $token, 'name' => 'Claire', 'email' => 'claire@test.fr', 'message' => 'Bonjour, est-ce gratuit pour un refuge ?']);
check(str_contains($body, 'votre message est envoyé'), 'Formulaire de contact');

// ===== Appareils : code de connexion, API, synchronisation des structures =====
[$code, $body] = http('a', '/compte/appareils');
check($code === 200 && str_contains($body, 'Générer un code') && str_contains($body, 'Aucun appareil connecté'), 'Page des appareils');
$token = csrf('a', '/compte/appareils');
[$code, $body] = http('a', '/compte/appareils', ['csrf' => $token, 'action' => 'code']);
check($code === 200 && preg_match('/id="code-appareil">([A-Z2-9]{4}-[A-Z2-9]{4})</', $body, $match) === 1, 'Code de connexion affiché');
$deviceCode = $match[1];
check(str_contains($body, 'data-qr="suivianimaux://site?code=' . $deviceCode . '"') && str_contains($body, '/assets/js/qrcode.js'), 'QR code du code de connexion');
check(q("SELECT COUNT(*) FROM user_tokens WHERE type = 'appareil' AND token_hash = ?", [hash('sha256', $deviceCode)])->fetchColumn() == 1
      && q("SELECT COUNT(*) FROM user_tokens WHERE token_hash = ?", [$deviceCode])->fetchColumn() == 0, 'Code gardé haché (sha256)');
[$code] = http('a', '/compte/appareils', ['action' => 'code']);
check($code === 400, 'Code de connexion : jeton CSRF exigé');

[$code, $json, $headers] = api('POST', '/api/code', ['code' => strtolower(str_replace('-', '', $deviceCode)), 'device' => "PC de l'accueil"]);
check($code === 200 && preg_match('/^[0-9a-f]{48}$/', $json['token'] ?? '') === 1 && $json['email'] === 'alice@test.fr', 'API : connexion par code');
check(!str_contains(strtolower($headers), 'set-cookie'), 'API : ni session ni cookie');
$aliceToken = $json['token'];
$asso = array_values(array_filter($json['organisations'], fn($o) => $o['id'] === $organisationId))[0] ?? null;
check($asso !== null && $asso['name'] === 'Asso Test' && $asso['kind'] === 'Association' && $asso['roles'] === ['responsable']
      && $asso['licence'] === 'active', 'API : structures et rôles reçus');
check(q('SELECT name FROM devices WHERE token_hash = ?', [hash('sha256', $aliceToken)])->fetchColumn() === "PC de l'accueil", 'Appareil enregistré, jeton haché');
[$code, $json] = api('POST', '/api/code', ['code' => $deviceCode, 'device' => 'Autre']);
check($code === 401 && $json['error'] === 'code', 'API : code à usage unique');
$expired = (function () {
    $code = create_device_code((int)q("SELECT id FROM users WHERE email = 'alice@test.fr'")->fetchColumn());
    q("UPDATE user_tokens SET expires_at = ? WHERE token_hash = ?", [date('Y-m-d H:i:s', time() - 60), hash('sha256', $code)]);
    return $code;
})();
[$code] = api('POST', '/api/code', ['code' => $expired, 'device' => 'Autre']);
check($code === 401, 'API : code expiré refusé');

[$code, $json] = api('POST', '/api/connexion', ['email' => 'alice@test.fr', 'password' => 'faux-mot-de-passe', 'device' => 'Téléphone']);
check($code === 401 && $json['error'] === 'identifiants' && isset($json['message']), 'API : mauvais mot de passe');
[$code, $json] = api('POST', '/api/connexion', ['email' => 'Alice@Test.fr', 'password' => 'motdepasse-solide', 'device' => 'Téléphone d’Alice']);
check($code === 200 && preg_match('/^[0-9a-f]{48}$/', $json['token'] ?? '') === 1 && $json['token'] !== $aliceToken, 'API : connexion par adresse et mot de passe');
$phoneToken = $json['token'];
[$code, $json] = api('GET', '/api/connexion');
check($code === 405 && $json['error'] === 'methode', 'API : méthode refusée');
[$code, $json] = api('GET', '/api/inconnue');
check($code === 404 && $json['error'] === 'adresse', 'API : adresse inconnue en JSON');

[$code, $json] = api('GET', '/api/structures');
check($code === 401 && $json['error'] === 'jeton', 'API : jeton exigé');
[$code, $json] = api('GET', '/api/structures', null, str_repeat('ab', 24));
check($code === 401, 'API : jeton inconnu refusé');
[$code, $json] = api('GET', '/api/structures', null, $aliceToken);
check($code === 200 && in_array($organisationId, array_column($json['organisations'], 'id'), true), 'API : structures du compte');

// Fichiers : écriture, lecture, liste
$content = "\x1f\x8b\x00binaire\r\n" . random_bytes(2000);
[$code, $json] = api('POST', "/api/sync/fichier?o=$organisationId&chemin=journaux/0001_pc.json.gz", $content, $aliceToken);
check($code === 200 && $json['ok'] === true && $json['size'] === strlen($content), 'API : fichier envoyé');
check(is_file(STORAGE_DIR . "/sync/$organisationId/journaux/0001_pc.json.gz"), 'Fichier rangé dans storage/sync/<structure>');
[$code, $raw] = api('GET', "/api/sync/fichier?o=$organisationId&chemin=journaux/0001_pc.json.gz", null, $phoneToken);
check($code === 200 && $raw === $content, 'API : fichier relu à l’identique par un autre appareil');
api('POST', "/api/sync/fichier?o=$organisationId&chemin=journaux/0002_tel.json.gz", 'deux', $phoneToken);
api('POST', "/api/sync/fichier?o=$organisationId&chemin=journaux/0002_tel.json.gz", 'deux remplacé', $phoneToken);
[, $raw] = api('GET', "/api/sync/fichier?o=$organisationId&chemin=journaux/0002_tel.json.gz", null, $aliceToken);
check($raw === 'deux remplacé', 'API : fichier remplacé');
api('POST', "/api/sync/fichier?o=$organisationId&chemin=deplace.txt", 'repère', $aliceToken);
[$code, $json] = api('GET', "/api/sync/liste?o=$organisationId&dossier=journaux", null, $aliceToken);
$files = $json['files'] ?? [];
sort($files);
check($code === 200 && $files === ['0001_pc.json.gz', '0002_tel.json.gz'], 'API : liste d’un dossier');
[, $json] = api('GET', "/api/sync/liste?o=$organisationId&dossier=", null, $aliceToken);
check($json['files'] === ['deplace.txt'], 'API : liste de la racine (fichiers seulement)');
[, $json] = api('GET', "/api/sync/liste?o=$organisationId&dossier=instantanes", null, $aliceToken);
check($json['files'] === [], 'API : dossier absent = liste vide');
[$code, $json] = api('GET', "/api/sync/fichier?o=$organisationId&chemin=instantanes/absent.json.gz", null, $aliceToken);
check($code === 404 && $json['error'] === 'absent', 'API : fichier absent → 404');

// Chemins dangereux, taille, structure d'un autre compte, licence
foreach (['../x', 'journaux/../../x', '..', '.htaccess', 'journaux/.cache', 'a//b', '/abs', 'a\\b', 'é.txt', 'journaux/', ''] as $bad) {
    [$code, $json] = api('POST', "/api/sync/fichier?o=$organisationId&chemin=" . rawurlencode($bad), 'x', $aliceToken);
    check($code === 400 && $json['error'] === 'chemin', "API : chemin « $bad » refusé");
}
[$code] = api('GET', "/api/sync/liste?o=$organisationId&dossier=" . rawurlencode('../..'), null, $aliceToken);
check($code === 400, 'API : dossier « ../.. » refusé');
[$code] = api('POST', "/api/sync/fichier?o=$organisationId&chemin=journaux", 'x', $aliceToken);
check($code === 400, 'API : écriture sur un dossier refusée');
[$code, $json] = api('POST', "/api/sync/fichier?o=$organisationId&chemin=fichiers/grand.jpg", str_repeat('x', 1024 * 1024 + 1), $aliceToken);
check($code === 413 && $json['error'] === 'taille' && !is_file(STORAGE_DIR . "/sync/$organisationId/fichiers/grand.jpg"), 'API : fichier trop volumineux refusé');
[$code, $json] = api('POST', '/api/connexion', ['email' => 'bob@test.fr', 'password' => 'nouveau-motdepasse', 'device' => 'PC de Bob']);
$bobToken = $json['token'];
check($code === 200 && $json['organisations'] === [], 'API : compte sans structure');
[$code, $json] = api('GET', "/api/sync/liste?o=$organisationId&dossier=journaux", null, $bobToken);
check($code === 404 && $json['error'] === 'structure', 'API : liste refusée à un non-membre');
[$code] = api('GET', "/api/sync/fichier?o=$organisationId&chemin=journaux/0001_pc.json.gz", null, $bobToken);
check($code === 404, 'API : lecture refusée à un non-membre');
[$code] = api('POST', "/api/sync/fichier?o=$organisationId&chemin=journaux/pirate.json", 'x', $bobToken);
check($code === 404 && !is_file(STORAGE_DIR . "/sync/$organisationId/journaux/pirate.json"), 'API : écriture refusée à un non-membre');
q("UPDATE licences SET status = 'suspendue' WHERE organisation_id = ?", [$organisationId]);
[$code, $json] = api('POST', "/api/sync/fichier?o=$organisationId&chemin=journaux/0003_pc.json.gz", 'x', $aliceToken);
check($code === 403 && $json['error'] === 'licence' && str_contains($json['message'], 'suspendue'), 'API : écriture refusée, licence suspendue');
[$code] = api('GET', "/api/sync/fichier?o=$organisationId&chemin=journaux/0001_pc.json.gz", null, $aliceToken);
check($code === 200, 'API : lecture permise, licence suspendue');
q("UPDATE licences SET status = 'active' WHERE organisation_id = ?", [$organisationId]);

// Limite des codes faux
for ($i = 0; $i < 10; $i++) {
    api('POST', '/api/code', ['code' => 'AAAA-AAAA', 'device' => 'x']);
}
[$code, $json] = api('POST', '/api/code', ['code' => 'AAAA-AAAA', 'device' => 'x']);
check($code === 429 && $json['error'] === 'trop_essais', 'API : essais de codes limités');
q('DELETE FROM login_attempts');

// Espace client : liste, déconnexion d'un appareil ; déconnexion par l'API
[, $body] = http('a', '/compte/appareils');
check(str_contains($body, 'PC de l&#039;accueil') && str_contains($body, 'Téléphone d’Alice') && !str_contains($body, 'PC de Bob'), 'Appareils du compte listés');
$phoneId = (int)q('SELECT id FROM devices WHERE token_hash = ?', [hash('sha256', $phoneToken)])->fetchColumn();
$bobDeviceId = (int)q('SELECT id FROM devices WHERE token_hash = ?', [hash('sha256', $bobToken)])->fetchColumn();
$token = csrf('a', '/compte/appareils');
http('a', '/compte/appareils', ['csrf' => $token, 'action' => 'deconnecter', 'device' => (string)$bobDeviceId]);
[$code] = api('GET', '/api/structures', null, $bobToken);
check($code === 200, 'Un compte ne peut pas déconnecter l’appareil d’un autre');
http('a', '/compte/appareils', ['csrf' => $token, 'action' => 'deconnecter', 'device' => (string)$phoneId]);
[$code, $json] = api('GET', '/api/structures', null, $phoneToken);
check($code === 401 && $json['error'] === 'jeton', 'Appareil déconnecté depuis l’espace client');
[$code] = api('POST', '/api/deconnexion', null, $aliceToken);
[$code2] = api('GET', '/api/structures', null, $aliceToken);
check($code === 200 && $code2 === 401, 'API : l’appareil se déconnecte');
[, $body] = http('a', '/compte/appareils');
check(str_contains($body, 'Aucun appareil connecté'), 'Plus aucun appareil après les déconnexions');

// Nouveau mot de passe par « mot de passe oublié » : les appareils sont déconnectés
$token = csrf('d', '/mot-de-passe-oublie');
http('d', '/mot-de-passe-oublie', ['csrf' => $token, 'email' => 'bob@test.fr']);
$reset = last_mail_link('/nouveau-mot-de-passe\?t=');
$token = csrf('d', $reset);
http('d', '/nouveau-mot-de-passe', ['csrf' => $token, 't' => substr($reset, strpos($reset, 't=') + 2), 'password' => 'encore-un-motdepasse']);
[$code] = api('GET', '/api/structures', null, $bobToken);
check($code === 401, 'Mot de passe réinitialisé : appareils déconnectés');

// ===== Interface en ligne : fiches des animaux reconstituées à partir des fichiers de synchronisation =====
[, $json] = api('POST', '/api/connexion', ['email' => 'alice@test.fr', 'password' => 'motdepasse-solide', 'device' => 'PC de l’association']);
$syncToken = $json['token'];
$photo = "\xFF\xD8\xFF\xE0" . random_bytes(500);
$photoName = hash('sha256', $photo) . '.jpg';
$hidden = random_bytes(100);
$hiddenName = hash('sha256', $hidden) . '.png';
$change = fn(string $table, string $id, string $modified, ?array $data) =>
    ['Table' => $table, 'Id' => $id, 'Deleted' => $data === null, 'Modified' => $modified, 'Data' => $data];
$journal = fn(array $changes, ?array $cursors = null, int $format = 1) => json_encode(['Format' => $format, 'Device' => 'x',
    'DeviceName' => 'Test', 'CreatedUtc' => '2026-10-31T10:00:00Z', 'Changes' => $changes, 'Cursors' => $cursors]);
$minou = ['Name' => 'Minou', 'Species' => 'Chat', 'Sex' => 'Femelle', 'Breed' => 'Européen', 'BirthDate' => '2023-04-01', 'Status' => 'Présent',
    'ChipNumber' => '250269812345678', 'Allergies' => 'Poulet', 'VeterinarianContactId' => 'c-vet', 'PhotoPath' => "fichier:$photoName",
    'Sterilized' => 1, 'MotherId' => null, 'Notes' => "Timide <b>au début</b>"];
$snapshot = $journal([
    $change('Contacts', 'c-fanny', '2026-10-01T08:00:00.000Z', ['Name' => 'Fanny Accueil', 'Email' => ' Fanny@Test.fr ', 'Role' => 'Famille d’accueil']),
    $change('Contacts', 'c-vet', '2026-10-01T08:00:00.000Z', ['Name' => 'Clinique des Lilas', 'Email' => '', 'Role' => 'Vétérinaire']),
    $change('Animals', 'a-minou', '2026-10-10T08:00:00.000Z', $minou),
    $change('Animals', 'a-rex', '2026-10-10T08:00:00.000Z', ['Name' => 'Rex', 'Species' => 'Chien', 'Sex' => 'Mâle', 'Status' => 'Présent', 'PhotoPath' => '']),
    $change('Animals', 'a-ancien', '2026-10-10T08:00:00.000Z', ['Name' => 'Ancien', 'Species' => 'Chat', 'Sex' => 'Mâle', 'Status' => 'Adopté']),
    $change('Animals', 'a-parti', '2026-10-10T08:00:00.000Z', ['Name' => 'Supprimé', 'Species' => 'Chat', 'Sex' => 'Mâle', 'Status' => 'Présent']),
    $change('Placements', 'p-1', '2026-10-10T08:00:00.000Z', ['AnimalId' => 'a-minou', 'Kind' => 'Famille d’accueil', 'ContactId' => 'c-fanny',
        'StartDate' => '2026-10-01', 'EndDate' => null, 'Location' => '', 'Notes' => '']),
    $change('Placements', 'p-2', '2026-10-10T08:00:00.000Z', ['AnimalId' => 'a-rex', 'Kind' => 'Famille d’accueil', 'ContactId' => 'c-fanny',
        'StartDate' => '2026-01-01', 'EndDate' => '2026-02-01', 'Location' => '', 'Notes' => '']),
    $change('AnimalEvents', 'e-1', '2026-10-12T08:00:00.000Z', ['AnimalId' => 'a-minou', 'EventDate' => '2026-10-12', 'Type' => 'Pesée',
        'Title' => '', 'Description' => '', 'WeightKg' => 4.25, 'NextDueDate' => null, 'Veterinarian' => '', 'AttachmentPath' => '']),
    $change('AnimalEvents', 'e-2', '2026-10-13T08:00:00.000Z', ['AnimalId' => 'a-minou', 'EventDate' => '2026-10-13', 'Type' => 'Vaccin',
        'Title' => '<script>alert(1)</script>', 'Description' => 'Typhus et coryza', 'WeightKg' => null, 'NextDueDate' => '2027-10-13',
        'Veterinarian' => 'Dr Martin', 'AttachmentPath' => '']),
    $change('Treatments', 't-1', '2026-10-14T08:00:00.000Z', ['AnimalId' => 'a-minou', 'MedicationName' => 'Métacam', 'Dosage' => '0,5 ml',
        'Route' => 'Orale', 'StartDate' => '2026-10-14', 'EndDate' => null, 'FrequencyValue' => 1, 'FrequencyUnit' => 'jour(s)', 'Status' => 'En cours']),
    $change('AnimalExpenses', 'x-1', '2026-10-15T08:00:00.000Z', ['AnimalId' => 'a-minou', 'ExpenseDate' => '2026-10-15', 'Category' => 'Vétérinaire',
        'Description' => 'Stérilisation', 'Amount' => 120.5, 'ReimbursedAmount' => 0, 'PaidBy' => 'Association']),
], ['pcasso' => '0000000000000000090_pcasso.json', 'tel' => '0000000000000000095_tel.json']);
foreach ([
    "instantanes/0000000000000000100_pcasso.json.gz" => gzencode($snapshot),
    // Déjà compris dans l'état complet (curseur) : ignoré
    "journaux/0000000000000000090_pcasso.json" => $journal([$change('Animals', 'a-rex', '2026-10-20T08:00:00.000Z', ['Name' => 'Ignoré', 'Species' => 'Chien', 'Sex' => 'Mâle', 'Status' => 'Présent'])]),
    // Après l'état complet : modification plus récente, modification plus ancienne (ignorée), suppression
    "journaux/0000000000000000200_tel.json" => $journal([
        $change('Animals', 'a-rex', '2026-10-21T08:00:00.000Z', ['Name' => 'Rex le brave', 'Species' => 'Chien', 'Sex' => 'Mâle', 'Status' => 'Présent']),
        $change('Animals', 'a-minou', '2026-10-01T08:00:00.000Z', ['Name' => 'Vieux nom'] + $minou),
        $change('Animals', 'a-parti', '2026-10-21T08:00:00.000Z', null),
    ]),
    "journaux/illisible_tel.json" => 'pas du JSON',
    "fichiers/$photoName" => $photo,
    "fichiers/$hiddenName" => $hidden,
] as $path => $content) {
    [$code] = api('POST', "/api/sync/fichier?o=$organisationId&chemin=$path", $content, $syncToken);
    check($code === 200, "Interface en ligne : envoi de $path");
}

[$code, , $location] = http('visiteur', "/application/animaux?o=$organisationId");
check($code === 302 && str_contains($location, '/connexion'), 'Interface en ligne : connexion exigée');
[$code, $body] = http('a', '/application');
check($code === 200 && str_contains($body, 'Asso Test') && str_contains($body, "/application/animaux?o=$organisationId")
      && str_contains($body, '2 animaux présents'), 'Interface en ligne : structures du compte');
[$code, $body] = http('a', "/application/animaux?o=$organisationId");
check($code === 200 && str_contains($body, 'Minou') && str_contains($body, 'Rex le brave'), 'Interface en ligne : liste des animaux présents');
check(!str_contains($body, 'Ignoré') && !str_contains($body, 'Vieux nom'), 'Interface en ligne : journal déjà compris et modification plus ancienne ignorés');
check(!str_contains($body, 'Supprimé') && !str_contains($body, 'Ancien'), 'Interface en ligne : animal supprimé absent, adopté filtré');
check(str_contains($body, 'Famille d’accueil : Fanny Accueil') && str_contains($body, e(online_file_url($organisationId, $photoName))), 'Interface en ligne : placement et photo dans la liste');
[, $body] = http('a', "/application/animaux?o=$organisationId&statut=Tous&q=ANC");
check(str_contains($body, 'Ancien') && !str_contains($body, 'Minou'), 'Interface en ligne : recherche et filtre du statut');
check(is_file(STORAGE_DIR . "/cache/etat-$organisationId.json"), 'Interface en ligne : état gardé en cache');

[$code, $body] = http('a', "/application/animal?o=$organisationId&a=a-minou");
check($code === 200 && str_contains($body, 'Poulet') && str_contains($body, 'Clinique des Lilas') && str_contains($body, '250269812345678'), 'Fiche en ligne : identité et santé');
check(str_contains($body, 'Métacam') && str_contains($body, '4,25 kg') && str_contains($body, 'Typhus et coryza') && str_contains($body, '13/10/2027'), 'Fiche en ligne : traitements, pesée, journal');
check(str_contains($body, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($body, '<script>alert(1)') && str_contains($body, 'Timide &lt;b&gt;'), 'Fiche en ligne : textes échappés');
check(str_contains($body, 'Stérilisation') && str_contains($body, '120,50 €'), 'Fiche en ligne : dépenses visibles du responsable');
[$code] = http('a', "/application/animal?o=$organisationId&a=a-parti");
check($code === 404, 'Fiche en ligne : animal supprimé introuvable');

$curl = curl_init(BASE . online_file_url($organisationId, $photoName));
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => STORAGE_DIR . '/cookies-a.txt']);
$raw = curl_exec($curl);
check(curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200 && $raw === $photo && curl_getinfo($curl, CURLINFO_CONTENT_TYPE) === 'image/jpeg', 'Interface en ligne : photo servie');
curl_close($curl);
foreach ([$hiddenName, '../../app/config.php', 'deplace.txt', str_repeat('0', 64) . '.jpg'] as $name) {
    [$code] = http('a', online_file_url($organisationId, $name));
    check($code === 404, "Interface en ligne : fichier « $name » refusé");
}

// Autres rôles : famille d'accueil (ses animaux seulement), bénévole soins (sans les dépenses), non-membre
create_user('Fanny Accueil', 'fanny@test.fr', 'motdepasse-fanny');
create_user('Gaël Soins', 'gael@test.fr', 'motdepasse-gael');
create_user('Henri Dehors', 'henri@test.fr', 'motdepasse-henri');
foreach (['fanny@test.fr' => 'accueil', 'gael@test.fr' => 'soins'] as $email => $roles) {
    q('INSERT INTO memberships (organisation_id, user_id, roles, created_at) SELECT ?, id, ?, ? FROM users WHERE email = ?', [$organisationId, $roles, now(), $email]);
}
foreach (['f' => 'fanny', 'g' => 'gael', 'h' => 'henri'] as $browser => $name) {
    $token = csrf($browser, '/connexion');
    http($browser, '/connexion', ['csrf' => $token, 'email' => "$name@test.fr", 'password' => "motdepasse-$name"]);
}
[$code, $body] = http('f', "/application/animaux?o=$organisationId&statut=Tous");
check($code === 200 && str_contains($body, 'Minou') && !str_contains($body, 'Rex') && !str_contains($body, 'Ancien')
      && str_contains($body, 'seuls les animaux qui vous sont confiés'), 'Famille d’accueil : seulement l’animal confié (placement en cours)');
[$code] = http('f', "/application/animal?o=$organisationId&a=a-rex");
check($code === 404, 'Famille d’accueil : fiche d’un autre animal refusée');
[$code, $body] = http('f', "/application/animal?o=$organisationId&a=a-minou");
check($code === 200 && str_contains($body, 'Métacam') && !str_contains($body, 'Stérilisation'), 'Famille d’accueil : fiche sans les dépenses');
[$code, $body] = http('g', "/application/animal?o=$organisationId&a=a-minou");
check($code === 200 && str_contains($body, 'Métacam') && !str_contains($body, 'Stérilisation'), 'Bénévole soins : fiche sans les dépenses');
[$code] = http('h', "/application/animaux?o=$organisationId");
[$code2] = http('h', online_file_url($organisationId, $photoName));
check($code === 404 && $code2 === 404, 'Interface en ligne : refusée à un non-membre');

// Nouveau journal : l'état est recalculé ; fichier d'une version plus récente signalé
api('POST', "/api/sync/fichier?o=$organisationId&chemin=journaux/0000000000000000300_pcasso.json",
    $journal([$change('Animals', 'a-rex', '2026-10-22T08:00:00.000Z', ['Name' => 'Rex III', 'Species' => 'Chien', 'Sex' => 'Mâle', 'Status' => 'Présent'])]), $syncToken);
[, $body] = http('a', "/application/animaux?o=$organisationId");
check(str_contains($body, 'Rex III') && !str_contains($body, 'Rex le brave'), 'Interface en ligne : nouveau journal pris en compte');
api('POST', "/api/sync/fichier?o=$organisationId&chemin=journaux/0000000000000000400_futur.json", $journal([], null, 99), $syncToken);
[, $body] = http('a', "/application/animaux?o=$organisationId");
check(str_contains($body, 'version plus récente') && str_contains($body, 'Rex III'), 'Interface en ligne : données d’une version plus récente signalées');
api('POST', '/api/deconnexion', null, $syncToken);

// ===== Administration des clients et des licences =====
[, $body] = http('visiteur', '/confidentialite');
check(str_contains($body, 'interface en ligne') && str_contains($body, 'synchronisent leurs données par ce site'), 'Confidentialité : données des structures sur le site');
[$code] = http('g', '/admin/formules');
check($code === 403, 'Formules réservées à l’administration');
$token = csrf('a', '/admin/formules');
http('a', '/admin/formules', ['csrf' => $token, 'action' => 'nouvelle', 'code' => 'mini', 'name' => 'Mini test', 'monthly' => '4,50', 'yearly' => '45',
    'max_animals' => '1', 'sort_order' => '9', 'active' => '1']);
$mini = q("SELECT * FROM plans WHERE code = 'mini'")->fetch();
check($mini && (int)$mini['monthly_price_cents'] === 450 && (int)$mini['max_animals'] === 1, 'Formule créée (prix en centimes, limite)');
[, $body] = http('visiteur', '/tarifs');
check(str_contains($body, 'Mini test') && str_contains($body, '4,50 €'), 'Nouvelle formule sur la page Tarifs');
[, $body] = http('a', '/admin/formules', ['csrf' => $token, 'action' => 'nouvelle', 'code' => 'mini', 'name' => 'Doublon', 'monthly' => '1', 'yearly' => '1']);
check(q("SELECT name FROM plans WHERE code = 'mini'")->fetchColumn() === 'Mini test', 'Code de formule déjà pris refusé');

$client = "/admin/client?id=$organisationId";
$token = csrf('a', $client);
$post = fn(array $fields) => http('a', $client, ['csrf' => $token] + $fields);
$post(['action' => 'licence', 'plan' => 'mini', 'status' => 'active', 'billing' => 'mois', 'starts_at' => '2026-01-01', 'ends_at' => '2026-02-01', 'notes' => 'Test']);
[, $body] = http('a', $client);
check(str_contains($body, 'Plus d’animaux présents que la formule n’en permet : 2 animaux pour 1') && str_contains($body, 'La date de fin de la licence'),
    'Fiche client : alertes dépassement et fin dépassée');
check(str_contains($body, '2 / 1') && str_contains($body, 'Appareils connectés') && str_contains($body, 'Données synchronisées'), 'Fiche client : utilisation');
[, $body] = http('a', '/admin/clients?alerte=depassement');
check(str_contains($body, 'Asso Test') && str_contains($body, 'Au-delà de la formule'), 'Clients : filtre « au-delà de la formule »');
[, $body] = http('a', '/admin/clients?alerte=fin_depassee&formule=mini&etat=active&type=Association');
check(str_contains($body, 'Asso Test'), 'Clients : filtres combinés');
[, $body] = http('a', '/admin/clients?etat=suspendue');
check(!str_contains($body, 'Asso Test'), 'Clients : filtre de l’état');
[, $body] = http('a', '/admin');
check(str_contains($body, 'À régulariser') && str_contains($body, 'Fin dépassée') && str_contains($body, 'Mini test'), 'Tableau de bord : licences à régulariser, par formule');
[, $body] = http('a', "/compte/structure?id=$organisationId");
check(str_contains($body, '2 animaux présents') && str_contains($body, 'plus d’animaux que sa formule'), 'Espace client : utilisation de la formule');

$post(['action' => 'prolonger', 'duree' => 'an']);
check(q('SELECT ends_at FROM licences WHERE organisation_id = ?', [$organisationId])->fetchColumn() === date('Y-m-d', strtotime('+1 year')),
    'Prolonger d’un an à partir d’aujourd’hui (fin passée)');
$post(['action' => 'prolonger', 'duree' => 'mois']);
check(q('SELECT ends_at FROM licences WHERE organisation_id = ?', [$organisationId])->fetchColumn() === date('Y-m-d', strtotime(date('Y-m-d', strtotime('+1 year')) . ' +1 month')),
    'Prolonger d’un mois à partir de la fin');
$post(['action' => 'etat', 'status' => 'suspendue']);
[, $body] = http('a', "/compte/structure?id=$organisationId");
check(q('SELECT status FROM licences WHERE organisation_id = ?', [$organisationId])->fetchColumn() === 'suspendue'
      && str_contains($body, 'ne peuvent plus en envoyer'), 'Licence suspendue, signalée au client');
$post(['action' => 'prolonger', 'duree' => 'mois']);
check(q('SELECT status FROM licences WHERE organisation_id = ?', [$organisationId])->fetchColumn() === 'active', 'Prolonger réactive la licence');
$before = q('SELECT * FROM licences WHERE organisation_id = ?', [$organisationId])->fetch();
$post(['action' => 'licence', 'plan' => 'association', 'status' => 'active', 'billing' => 'an', 'starts_at' => '2026-05-01', 'ends_at' => '2026-04-01']);
check(q('SELECT * FROM licences WHERE organisation_id = ?', [$organisationId])->fetch() == $before, 'Licence : fin avant le début refusée');
$post(['action' => 'licence', 'plan' => 'inconnue', 'status' => 'active', 'billing' => 'an', 'starts_at' => '2026-05-01', 'ends_at' => '']);
check(q('SELECT plan_code FROM licences WHERE organisation_id = ?', [$organisationId])->fetchColumn() === 'mini', 'Licence : formule inconnue refusée');
$post(['action' => 'licence', 'plan' => 'association', 'status' => 'active', 'billing' => 'an', 'starts_at' => '2026-01-01', 'ends_at' => '']);

$post(['action' => 'infos', 'name' => 'Asso Test', 'kind' => 'Pension', 'contact_email' => 'contact@asso.test', 'phone' => '0102030405', 'address' => '1 rue des Chats', 'siret' => 'W123456789']);
check(q('SELECT kind FROM organisations WHERE id = ?', [$organisationId])->fetchColumn() === 'Pension', 'Coordonnées modifiées par l’administration');
$post(['action' => 'infos', 'name' => 'Asso Test', 'kind' => 'Inconnu', 'contact_email' => 'pas-une-adresse']);
check(q('SELECT kind FROM organisations WHERE id = ?', [$organisationId])->fetchColumn() === 'Pension', 'Coordonnées invalides refusées');
$post(['action' => 'infos', 'name' => 'Asso Test', 'kind' => 'Association', 'contact_email' => '', 'phone' => '', 'address' => '', 'siret' => '']);

$henriId = (int)q("SELECT id FROM users WHERE email = 'henri@test.fr'")->fetchColumn();
$aliceId = (int)q("SELECT id FROM users WHERE email = 'alice@test.fr'")->fetchColumn();
$post(['action' => 'ajouter', 'email' => 'inconnu@test.fr', 'roles' => ['soins']]);
$post(['action' => 'ajouter', 'email' => 'Henri@Test.fr', 'roles' => ['soins']]);
check(q('SELECT roles FROM memberships WHERE organisation_id = ? AND user_id = ?', [$organisationId, $henriId])->fetchColumn() === 'soins'
      && !q("SELECT 1 FROM users WHERE email = 'inconnu@test.fr'")->fetch(), 'Membre ajouté par l’administration (compte existant seulement)');
$post(['action' => 'titulaire', 'member' => (string)$henriId]);
check((int)q('SELECT owner_user_id FROM organisations WHERE id = ?', [$organisationId])->fetchColumn() === $henriId
      && q('SELECT roles FROM memberships WHERE organisation_id = ? AND user_id = ?', [$organisationId, $henriId])->fetchColumn() === 'responsable,soins',
    'Nouveau titulaire, devenu responsable');
$post(['action' => 'retirer', 'member' => (string)$henriId]);
check(q('SELECT 1 FROM memberships WHERE organisation_id = ? AND user_id = ?', [$organisationId, $henriId])->fetch() !== false, 'Le titulaire ne peut pas être retiré');
$post(['action' => 'titulaire', 'member' => (string)$aliceId]);
$post(['action' => 'retirer', 'member' => (string)$henriId]);
check(q('SELECT 1 FROM memberships WHERE organisation_id = ? AND user_id = ?', [$organisationId, $henriId])->fetch() === false, 'Membre retiré par l’administration');
$post(['action' => 'ecrire', 'subject' => 'Votre licence arrive à échéance', 'body' => "Bonjour,\n\nPensez à renouveler votre formule."]);
check(str_contains((string)file_get_contents(STORAGE_DIR . '/mails.log'), "À : alice@test.fr\nRépondre à : support@suivi-des-animaux.fr\nObjet : Votre licence arrive à échéance"),
    'Message envoyé aux responsables');
[, $body] = http('a', $client);
check(str_contains($body, 'admin.structure.email') && str_contains($body, 'structure.titulaire') && str_contains($body, 'licence.modification'), 'Fiche client : historique');
[$code] = http('g', $client, ['csrf' => $token, 'action' => 'etat', 'status' => 'resiliee']);
check($code === 403 && q('SELECT status FROM licences WHERE organisation_id = ?', [$organisationId])->fetchColumn() === 'active', 'Actions sur les licences réservées à l’administration');

[$code, $csv] = http('a', '/admin/clients?q=Asso&format=csv');
check($code === 200 && str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, 'Animaux présents') && str_contains($csv, '"Asso Test";Association'), 'Export CSV des clients');
$token = csrf('a', '/admin/formules');
http('a', '/admin/formules', ['csrf' => $token, 'action' => 'modifier', 'code' => 'mini', 'name' => 'Mini test', 'monthly' => '4,5', 'yearly' => '45', 'max_animals' => '', 'sort_order' => '9']);
[, $body] = http('visiteur', '/tarifs');
check(!str_contains($body, 'Mini test') && q("SELECT max_animals FROM plans WHERE code = 'mini'")->fetchColumn() === null, 'Formule retirée des tarifs, limite illimitée');

echo "OK site : $checks vérifications\n";
