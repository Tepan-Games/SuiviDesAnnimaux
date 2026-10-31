<?php
declare(strict_types=1);

// ===== Lettre d'information =====
// Envoyée seulement aux comptes qui l'ont demandée (case décochée par défaut), dont l'adresse est confirmée. Chaque
// envoi porte un lien de désinscription en un clic (jeton propre à la lettre et au destinataire, empreinte sha256
// gardée) et les en-têtes List-Unsubscribe demandés par Gmail, Outlook et Yahoo. L'hébergement limite le nombre
// d'e-mails : l'envoi se fait par lots (newsletter.batch, 50 par défaut).

/** Modèle proposé pour une nouvelle lettre ({nom} et {site} sont remplacés à l'envoi). */
const NEWSLETTER_TEMPLATE = <<<TXT
Bonjour {nom},

Voici les nouveautés de Suivi des animaux.

• Nouveauté 1 : …
• Nouveauté 2 : …
• Nouveauté 3 : …

Mises à jour : le logiciel Windows se met à jour tout seul au démarrage ; l'application Android, par Google Play.

Une question, une idée ? Répondez simplement à cet e-mail, ou écrivez-nous depuis votre espace client :
{site}/compte/assistance

À bientôt,
L'équipe Suivi des animaux
TXT;

/** Condition SQL des comptes qui reçoivent la lettre (table « u »). */
const NEWSLETTER_AUDIENCE = 'u.newsletter_at IS NOT NULL AND u.email_verified_at IS NOT NULL AND u.disabled_at IS NULL';

function newsletter_subscriber_count(): int
{
    return (int)q('SELECT COUNT(*) FROM users u WHERE ' . NEWSLETTER_AUDIENCE)->fetchColumn();
}

function find_newsletter(int $id): ?array
{
    return q('SELECT * FROM newsletters WHERE id = ?', [$id])->fetch() ?: null;
}

/** Chiffres d'une lettre : destinataires, envoyés, en attente, échecs, désinscriptions. */
function newsletter_stats(int $id): array
{
    $row = q('SELECT COUNT(*) AS total, COUNT(sent_at) AS sent, SUM(sent_at IS NULL AND failed_at IS NULL) AS pending,
                     COUNT(failed_at) AS failed, COUNT(unsubscribed_at) AS unsubscribed
              FROM newsletter_deliveries WHERE newsletter_id = ?', [$id])->fetch();
    return array_map('intval', $row ?: []) + ['total' => 0, 'sent' => 0, 'pending' => 0, 'failed' => 0, 'unsubscribed' => 0];
}

function newsletter_personalise(string $text, array $user): string
{
    return strtr($text, ['{nom}' => trim((string)$user['name']) ?: 'à vous', '{site}' => url('')]);
}

/** Lien de désinscription d'un destinataire. */
function newsletter_unsubscribe_url(string $token): string
{
    return url('/desinscription?t=' . $token);
}

/** Envoie une lettre à une personne ; $token vide = essai (lien de désinscription inactif). */
function newsletter_send_to(array $newsletter, array $user, string $token): bool
{
    $link = newsletter_unsubscribe_url($token !== '' ? $token : 'essai');
    $body = newsletter_personalise($newsletter['body'], $user)
        . "\n\n—\nVous recevez cette lettre parce que vous l'avez demandée sur " . url('/') . ".\n"
        . "Ne plus la recevoir : $link\n(ou décochez la case dans votre profil : " . url('/compte/profil') . ')';
    return send_mail($user['email'], newsletter_personalise($newsletter['subject'], $user), $body, [
        'from' => (string)config('newsletter.from', config('mail.from')),
        'from_name' => (string)config('newsletter.from_name', config('mail.from_name')),
        'headers' => [
            "List-Unsubscribe: <$link>",
            'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
            'Precedence: bulk',
        ],
    ]);
}

/** Met la lettre en file d'envoi pour tous les abonnés actuels ; renvoie le nombre de destinataires. */
function newsletter_queue(array $newsletter): int
{
    q('INSERT IGNORE INTO newsletter_deliveries (newsletter_id, user_id, created_at)
       SELECT ?, u.id, ? FROM users u WHERE ' . NEWSLETTER_AUDIENCE, [$newsletter['id'], now()]);
    q('UPDATE newsletters SET queued_at = COALESCE(queued_at, ?) WHERE id = ?', [now(), $newsletter['id']]);
    audit('lettre.envoi', "Lettre n°{$newsletter['id']} : {$newsletter['subject']}");
    return newsletter_stats((int)$newsletter['id'])['total'];
}

/** Envoie le lot suivant ; renvoie le nombre d'e-mails partis. */
function newsletter_send_batch(array $newsletter): int
{
    $id = (int)$newsletter['id'];
    // Désinscrits ou désactivés depuis la mise en file : on ne leur envoie plus rien.
    q('DELETE d FROM newsletter_deliveries d JOIN users u ON u.id = d.user_id
       WHERE d.newsletter_id = ? AND d.sent_at IS NULL AND d.failed_at IS NULL AND NOT (' . NEWSLETTER_AUDIENCE . ')', [$id]);
    $batch = max(1, (int)config('newsletter.batch', 50));
    $rows = q("SELECT d.id AS delivery_id, u.* FROM newsletter_deliveries d JOIN users u ON u.id = d.user_id
               WHERE d.newsletter_id = ? AND d.sent_at IS NULL AND d.failed_at IS NULL ORDER BY d.id LIMIT $batch", [$id])->fetchAll();
    $sent = 0;
    foreach ($rows as $row) {
        $token = random_token();
        q('UPDATE newsletter_deliveries SET token_hash = ? WHERE id = ?', [token_hash($token), $row['delivery_id']]);
        if (newsletter_send_to($newsletter, $row, $token)) {
            q('UPDATE newsletter_deliveries SET sent_at = ? WHERE id = ?', [now(), $row['delivery_id']]);
            $sent++;
        } else {
            q('UPDATE newsletter_deliveries SET failed_at = ? WHERE id = ?', [now(), $row['delivery_id']]);
        }
    }
    if (newsletter_stats($id)['pending'] === 0) {
        q('UPDATE newsletters SET finished_at = COALESCE(finished_at, ?) WHERE id = ?', [now(), $id]);
    }
    return $sent;
}

/** Destinataire d'un lien de désinscription (null si le lien n'est pas valable). */
function newsletter_delivery_by_token(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{48}$/', $token)) {
        return null;
    }
    return q('SELECT d.*, u.email, u.name, u.newsletter_at FROM newsletter_deliveries d JOIN users u ON u.id = d.user_id
              WHERE d.token_hash = ?', [token_hash($token)])->fetch() ?: null;
}

function newsletter_unsubscribe(array $delivery): void
{
    q('UPDATE users SET newsletter_at = NULL WHERE id = ?', [$delivery['user_id']]);
    q('UPDATE newsletter_deliveries SET unsubscribed_at = COALESCE(unsubscribed_at, ?) WHERE id = ?', [now(), $delivery['id']]);
    if ($delivery['newsletter_at'] !== null) {
        audit('lettre.desinscription', "Lettre n°{$delivery['newsletter_id']}", (int)$delivery['user_id']);
    }
}

/** Abonnement depuis l'inscription ou le profil (la date de l'accord est gardée). */
function set_newsletter_subscription(int $userId, bool $subscribed): void
{
    if ($subscribed) {
        q('UPDATE users SET newsletter_at = COALESCE(newsletter_at, ?) WHERE id = ?', [now(), $userId]);
    } else {
        q('UPDATE users SET newsletter_at = NULL WHERE id = ?', [$userId]);
    }
}
