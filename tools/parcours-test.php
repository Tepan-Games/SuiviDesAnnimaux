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
    'email' => 'Alice@Test.fr', 'password' => 'motdepasse-solide', 'cgu' => '1']);
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

echo "OK site : $checks vérifications\n";
