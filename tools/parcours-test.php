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

// ===== Suivis des animaux confiés : lien à identifiant unique, QR code, nouvelles, réponses =====
$card = ['species' => 'Chat', 'sex' => 'Femelle', 'birthDate' => '2026-03-01', 'chip' => '250269812345678',
    'vaccines' => [['date' => '2026-06-01', 'name' => 'Typhus coryza', 'next' => '2027-06-01']], 'advice' => ['Transition alimentaire sur 7 jours']];
[$code, $json] = api('POST', "/api/liens?o=$organisationId", ['kind' => 'adoption', 'animal_name' => 'Mimi', 'species' => 'Chat',
    'animal_ref' => 'animal:1234', 'recipient_name' => 'Claire Adoptante', 'recipient_email' => 'claire@test.fr', 'months' => 3,
    'requests' => ['nouvelles', 'photo'], 'card' => $card], $aliceToken);
check($code === 200 && preg_match('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/', $json['reference']) && str_contains($json['url'], '/suivi?c='), 'Suivi : création par l’API');
$linkId = $json['link']['id'];
$linkToken = substr($json['url'], strpos($json['url'], 'c=') + 2);
check(q('SELECT token_hash FROM animal_links WHERE id = ?', [$linkId])->fetchColumn() === hash('sha256', $linkToken), 'Suivi : seule l’empreinte du jeton est gardée');
check(last_mail_link('/suivi\?c=') === $json['url'], 'Suivi : lien envoyé par e-mail à l’adoptant');
[$code] = api('POST', "/api/liens?o=$organisationId", ['animal_name' => 'Pirate'], $bobToken);
check($code === 404, 'Suivi : création refusée à un non-membre');
[$code, $json] = api('GET', "/api/liens/messages?o=$organisationId&id=$linkId", null, $bobToken);
check($code === 404, 'Suivi : échanges refusés à un non-membre');

// La personne, sans compte : API (logiciel, application) et page web
[$code, $json] = api('GET', '/api/suivi', null, $linkToken);
check($code === 200 && $json['animal']['name'] === 'Mimi' && $json['card']['chip'] === '250269812345678' && $json['open'] === true
    && $json['days_left'] >= 89 && $json['days_left'] <= 93 && str_contains($json['status'], 'encore 3 mois') && $json['organisation']['name'] === 'Asso Test', 'Suivi : fiche et jours restants pour la personne');
[$code, $json] = api('GET', '/api/suivi', null, str_repeat('0', 48));
check($code === 401 && $json['error'] === 'lien', 'Suivi : jeton inconnu refusé');
[$code, $json] = api('POST', '/api/suivi/message', ['kind' => 'pesee', 'body' => 'Elle mange bien', 'weight_kg' => '3,4'], $linkToken);
check($code === 200, 'Suivi : pesée envoyée par l’API');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
[$code, $json] = api('POST', '/api/suivi/photo?texte=' . rawurlencode('Sur le canapé'), $png, $linkToken);
check($code === 200, 'Suivi : photo envoyée par l’API');
$photoId = $json['id'];
[$code, $json] = api('POST', '/api/suivi/photo', '<?php echo 1;', $linkToken);
check($code === 400, 'Suivi : fichier qui n’est pas une image refusé');
[$code, $body] = http('visiteur', "/suivi?c=$linkToken");
check($code === 200 && str_contains($body, 'Mimi') && str_contains($body, 'Suivi en cours : encore 3 mois') && str_contains($body, '250269812345678')
    && str_contains($body, 'Transition alimentaire') && str_contains($body, '01/03/2026'), 'Suivi : page de la personne (fiche, temps restant, dates)');
$token = csrf('visiteur', "/suivi?c=$linkToken");
[$code, , $location] = http('visiteur', "/suivi?c=$linkToken", ['csrf' => $token, 'kind' => 'question', 'body' => 'Quelle litière conseillez-vous ?']);
check($code === 302 && str_contains((string)@file_get_contents(STORAGE_DIR . '/mails.log'), 'Question : Mimi'), 'Suivi : question par la page, structure prévenue');
[$code] = http('visiteur', "/suivi?c=$linkToken&photo=$photoId");
check($code === 200, 'Suivi : la personne voit sa photo');
[$code] = http('visiteur', '/suivi?c=' . str_repeat('a', 48));
check($code === 404, 'Suivi : page d’un lien inconnu');

// La structure : liste, échanges (non lus), réponse, photo, fiche, fin
[$code, $json] = api('GET', "/api/liens?o=$organisationId", null, $aliceToken);
check($code === 200 && count($json['links']) === 1 && $json['links'][0]['unread'] === 3 && $json['links'][0]['opened'] === true, 'Suivi : liste avec 3 envois non lus');
[$code, $json] = api('GET', "/api/liens/messages?o=$organisationId&id=$linkId", null, $aliceToken);
check($code === 200 && count($json['messages']) === 3 && $json['messages'][0]['weight_kg'] === 3.4 && $json['messages'][1]['photo'] !== null, 'Suivi : échanges reçus par le logiciel');
[$code, $raw] = api('GET', $json['messages'][1]['photo'], null, $aliceToken);
check($code === 200 && $raw === $png, 'Suivi : photo reçue par le logiciel');
[, $json] = api('GET', "/api/liens?o=$organisationId", null, $aliceToken);
check($json['links'][0]['unread'] === 0, 'Suivi : envois lus');
[$code] = api('POST', "/api/liens/repondre?o=$organisationId&id=$linkId", ['body' => 'Une litière végétale, sans parfum.'], $aliceToken);
check($code === 200 && str_contains((string)@file_get_contents(STORAGE_DIR . '/mails.log'), 'Une litière végétale'), 'Suivi : réponse de la structure, personne prévenue');
[, $json] = api('GET', '/api/suivi?lu=0', null, $linkToken);
[, $json2] = api('GET', '/api/suivi?lu=0', null, $linkToken);
check(end($json['messages'])['author'] === 'structure' && end($json2['messages'])['read'] === false, 'Suivi : relecture en arrière-plan sans marquer la réponse comme lue');
[$code] = api('POST', "/api/liens/fiche?o=$organisationId&id=$linkId", ['card' => $card + ['notes' => 'Rappel vaccin en juin']], $aliceToken);
[, $json] = api('GET', '/api/suivi', null, $linkToken);
check($code === 200 && $json['card']['notes'] === 'Rappel vaccin en juin' && end($json['messages'])['author'] === 'structure', 'Suivi : fiche mise à jour et réponse vues par la personne');
[$code, $body] = http('a', "/compte/suivis?o=$organisationId");
check($code === 200 && str_contains($body, 'Mimi') && str_contains($body, 'Claire Adoptante'), 'Suivi : liste sur le site');
[$code, $body] = http('a', "/compte/suivi?o=$organisationId&id=$linkId");
check($code === 200 && str_contains($body, 'Quelle litière') && str_contains($body, '3,4 kg'), 'Suivi : échanges sur le site');
[$code] = http('a', "/compte/suivi/photo?o=$organisationId&id=$linkId&m=$photoId");
check($code === 200, 'Suivi : photo sur le site');
[$code] = http('b', "/compte/suivi?o=$organisationId&id=$linkId");
check($code === 404, 'Suivi : page refusée à un non-membre');

// Création depuis le site (famille d'accueil, sans fin), puis fin d'un suivi
$token = csrf('a', "/compte/suivis?o=$organisationId");
[$code, $body] = http('a', "/compte/suivis?o=$organisationId", ['csrf' => $token, 'kind' => 'accueil', 'animal_name' => 'Rex', 'species' => 'Chien',
    'recipient_name' => 'Famille Martin', 'recipient_email' => '', 'months' => '0', 'requests' => ['nouvelles']]);
check($code === 200 && str_contains($body, 'data-qr="http://localhost:8080/suivi?c=') && str_contains($body, 'Suivi de Rex créé'), 'Suivi : création sur le site avec QR code');
preg_match('#/suivi\?c=([0-9a-f]{48})#', $body, $m);
[, $json] = api('GET', '/api/suivi', null, $m[1]);
check($json['days_left'] === null && $json['ends_at'] === null && $json['kind'] === 'accueil', 'Suivi : famille d’accueil sans date de fin');
// Types de suivi selon la structure : association = adoption ou famille d'accueil ; élevage ou magasin = cession ou vente ; pension : aucun.
[$code, $json] = api('POST', "/api/liens?o=$organisationId", ['kind' => 'particulier', 'animal_name' => 'Tom'], $aliceToken);
[, $list] = api('GET', "/api/liens?o=$organisationId", null, $aliceToken);
check($code === 403 && $json['error'] === 'type' && $list['kinds'] === ['adoption', 'accueil'], 'Suivi : association, adoption ou famille d’accueil seulement');
q("UPDATE organisations SET kind = 'Élevage' WHERE id = ?", [$organisationId]);
[$code] = api('POST', "/api/liens?o=$organisationId", ['kind' => 'adoption', 'animal_name' => 'Tom'], $aliceToken);
[$code2, $json2] = api('POST', "/api/liens?o=$organisationId", ['kind' => 'particulier', 'animal_name' => 'Tom'], $aliceToken);
check($code === 403 && $code2 === 200 && str_contains($json2['url'], '/suivi?c='), 'Suivi : élevage, cession ou vente seulement');
q("UPDATE organisations SET kind = 'Pension' WHERE id = ?", [$organisationId]);
[$code, $body] = http('a', "/compte/suivis?o=$organisationId");
[$code2] = api('POST', "/api/liens?o=$organisationId", ['kind' => 'adoption', 'animal_name' => 'Tom'], $aliceToken);
check($code === 200 && str_contains($body, 'Une pension garde les animaux') && !str_contains($body, 'Nouveau suivi') && $code2 === 403,
    'Suivi : pension, pas de suivi par lien');
q("UPDATE organisations SET kind = 'Association' WHERE id = ?", [$organisationId]);
[$code] = api('POST', "/api/liens/fermer?o=$organisationId&id=$linkId", null, $aliceToken);
[$code2, $json] = api('POST', '/api/suivi/message', ['body' => 'Encore là'], $linkToken);
[$code3, $json3] = api('GET', '/api/suivi', null, $linkToken);
check($code === 200 && $code2 === 403 && $json['error'] === 'termine' && $code3 === 200 && $json3['open'] === false && $json3['days_left'] === 0
    && count($json3['messages']) === 4, 'Suivi : terminé, historique encore lisible, plus d’envoi');

// ===== Annuaire des structures et questions (deux choix séparés, désactivés par défaut) =====
[$code, $json] = api('GET', '/api/annuaire?lat=43.8367&lon=4.36');
[$code2] = http('visiteur', "/structure?id=$organisationId");
check($code === 200 && $json['organisations'] === [] && $code2 === 404, 'Annuaire : structure absente tant qu’elle ne l’a pas choisi');
[$code, $json] = api('GET', '/api/annuaire?lat=abc&lon=4');
check($code === 400 && $json['error'] === 'position', 'Annuaire : position non valable refusée');
$token = csrf('a', "/compte/structure?id=$organisationId");
http('a', "/compte/structure?id=$organisationId", ['csrf' => $token, 'action' => 'infos', 'name' => 'Asso Test', 'contact_email' => 'contact@asso-test.fr',
    'phone' => '04 66 00 00 00', 'address' => '12 rue de la République, 30000 Nîmes', 'siret' => '']);
http('a', "/compte/structure?id=$organisationId", ['csrf' => $token, 'action' => 'annuaire', 'directory_listed' => '1',
    'description' => 'Refuge pour chats et chiens.', 'species' => 'Chats, chiens', 'website' => 'asso-test.fr']);
$org = q('SELECT * FROM organisations WHERE id = ?', [$organisationId])->fetch();
check((int)$org['directory_listed'] === 1 && (int)$org['accepts_questions'] === 0 && abs((float)$org['latitude'] - 43.836699) < 0.0001
    && $org['city'] === 'Nîmes' && $org['website'] === 'https://asso-test.fr', 'Annuaire : inscription et adresse située');
[$code, $json] = api('GET', '/api/annuaire?lat=43.85&lon=4.35&km=20');
[, $far] = api('GET', '/api/annuaire?lat=48.8566&lon=2.3522');
check($code === 200 && count($json['organisations']) === 1 && $json['organisations'][0]['name'] === 'Asso Test' && $json['organisations'][0]['distance_km'] < 3
    && $json['organisations'][0]['accepts_questions'] === false && $json['organisations'][0]['phone'] === '04 66 00 00 00' && $far['organisations'] === [],
    'Annuaire : structure trouvée autour de Nîmes, pas autour de Paris');
[$code, $body] = http('visiteur', "/structure?id=$organisationId");
check($code === 200 && str_contains($body, 'Refuge pour chats') && !str_contains($body, 'Poser une question'), 'Annuaire : page publique sans questions');
[$code, $json] = api('POST', '/api/question', ['organisation' => (string)$organisationId, 'name' => 'Claire', 'email' => 'claire@test.fr', 'body' => 'Adoptez-vous des chats seniors ?']);
check($code === 400 && str_contains($json['message'], 'ne reçoit pas'), 'Questions : refusées tant que la structure ne les accepte pas');
http('a', "/compte/structure?id=$organisationId", ['csrf' => $token, 'action' => 'annuaire', 'directory_listed' => '1', 'accepts_questions' => '1',
    'description' => 'Refuge pour chats et chiens.', 'species' => 'Chats, chiens', 'website' => 'https://asso-test.fr']);
// Identifiant envoyé comme nombre JSON (logiciel et application).
[$code, $json] = api('POST', '/api/question', ['organisation' => $organisationId, 'name' => 'Claire', 'email' => 'claire@test.fr', 'body' => 'Adoptez-vous des chats seniors ?']);
$log = (string)@file_get_contents(STORAGE_DIR . '/mails.log');
check($code === 200 && str_contains($json['url'], '/question?c=') && str_contains($log, "À : contact@asso-test.fr\nRépondre à : claire@test.fr")
    && str_contains($log, 'Votre question à Asso Test'), 'Questions : par l’API, structure prévenue (Répondre = la personne), accusé de réception');
$questionUrl = $json['url'];
[$code, $json] = api('POST', '/api/question', ['organisation' => (string)$organisationId, 'name' => 'X', 'email' => 'pas-une-adresse', 'body' => 'Bonjour bonjour']);
check($code === 400, 'Questions : adresse e-mail non valable refusée');
$token2 = csrf('visiteur', "/structure?id=$organisationId");
[$code, $body] = http('visiteur', "/structure?id=$organisationId", ['csrf' => $token2, 'name' => 'Paul', 'email' => 'paul@test.fr', 'body' => 'Quels sont vos horaires de visite ?']);
check($code === 200 && str_contains($body, 'Question envoyée'), 'Questions : par la page publique');
[$code, $body] = http('a', "/compte/questions?o=$organisationId");
check($code === 200 && str_contains($body, 'chats seniors') && str_contains($body, 'horaires de visite'), 'Questions : liste de la structure');
$questionId = (int)q("SELECT id FROM organisation_questions WHERE email = 'claire@test.fr'")->fetchColumn();
$token = csrf('a', "/compte/questions?o=$organisationId");
http('a', "/compte/questions?o=$organisationId", ['csrf' => $token, 'question' => (string)$questionId, 'action' => 'repondre', 'answer' => 'Oui, avec plaisir !']);
[$code, $body] = http('visiteur', str_replace(BASE, '', $questionUrl));
check($code === 200 && str_contains($body, 'Oui, avec plaisir') && str_contains((string)@file_get_contents(STORAGE_DIR . '/mails.log'), 'Réponse de Asso Test'),
    'Questions : réponse envoyée par e-mail et visible avec le lien');
[$code] = http('b', "/compte/questions?o=$organisationId");
check($code === 404, 'Questions : liste refusée à un non-membre');
[$code, $body] = http('visiteur', '/annuaire?q=' . rawurlencode('Nîmes'));
check($code === 200 && str_contains($body, 'Asso Test') && str_contains($body, 'répond aux questions'), 'Annuaire : recherche par commune');
q('UPDATE organisation_questions SET created_at = ? WHERE 1', [now()]);
for ($i = 0; $i < 5; $i++) {
    api('POST', '/api/question', ['organisation' => (string)$organisationId, 'name' => 'Robot', 'email' => "r$i@test.fr", 'body' => 'Question répétée numéro ' . $i]);
}
[$code, $json] = api('POST', '/api/question', ['organisation' => (string)$organisationId, 'name' => 'Robot', 'email' => 'r9@test.fr', 'body' => 'Encore une question']);
check($code === 429 && $json['error'] === 'trop_essais', 'Questions : nombre limité par adresse IP');

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

echo "OK site : $checks vérifications\n";
