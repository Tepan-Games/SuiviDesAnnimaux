<?php
declare(strict_types=1);

// ===== Suivi des problèmes (demandes d'assistance) =====

const TICKET_CATEGORIES = ['Problème', 'Question', 'Synchronisation', 'Compte et licence', 'Suggestion'];
const TICKET_STATUSES = ['ouvert' => 'Ouvert', 'en_cours' => 'En cours', 'attente' => 'En attente de votre réponse', 'resolu' => 'Résolu', 'ferme' => 'Fermé'];
const TICKET_PRIORITIES = ['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'];

function create_ticket(array $user, ?int $organisationId, string $subject, string $category, string $body): int
{
    $pdo = db();
    $pdo->beginTransaction();
    q('INSERT INTO tickets (user_id, organisation_id, subject, category, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$user['id'], $organisationId, $subject, in_array($category, TICKET_CATEGORIES, true) ? $category : 'Question', now(), now()]);
    $id = (int)$pdo->lastInsertId();
    q('INSERT INTO ticket_messages (ticket_id, user_id, body, is_staff, created_at) VALUES (?, ?, ?, 0, ?)', [$id, $user['id'], $body, now()]);
    $pdo->commit();
    send_mail((string)config('support_email'), "Demande n°$id : $subject",
        "{$user['name']} ({$user['email']}) — $category\n\n$body\n\n" . url("/admin/ticket?id=$id"));
    return $id;
}

/** Demande vue par son auteur (ou par l'administration). */
function find_ticket(int $id, ?int $userId = null): ?array
{
    $sql = 'SELECT t.*, u.name AS user_name, u.email AS user_email, o.name AS organisation_name FROM tickets t
            JOIN users u ON u.id = t.user_id LEFT JOIN organisations o ON o.id = t.organisation_id WHERE t.id = ?';
    $params = [$id];
    if ($userId !== null) {
        $sql .= ' AND t.user_id = ?';
        $params[] = $userId;
    }
    return q($sql, $params)->fetch() ?: null;
}

function ticket_messages(int $ticketId): array
{
    return q('SELECT m.*, u.name FROM ticket_messages m JOIN users u ON u.id = m.user_id WHERE m.ticket_id = ? ORDER BY m.created_at, m.id',
        [$ticketId])->fetchAll();
}

/** Réponse : de l'administration (la demande passe « en attente de votre réponse ») ou de l'auteur (elle repasse « ouvert »). */
function reply_ticket(array $ticket, array $user, string $body, bool $staff, ?string $status = null): void
{
    q('INSERT INTO ticket_messages (ticket_id, user_id, body, is_staff, created_at) VALUES (?, ?, ?, ?, ?)',
        [$ticket['id'], $user['id'], $body, $staff ? 1 : 0, now()]);
    $status ??= $staff ? 'attente' : 'ouvert';
    q('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?', [array_key_exists($status, TICKET_STATUSES) ? $status : 'ouvert', now(), $ticket['id']]);
    if ($staff) {
        send_mail($ticket['user_email'], "Réponse à votre demande n°{$ticket['id']}",
            "Bonjour {$ticket['user_name']},\n\nNous avons répondu à votre demande « {$ticket['subject']} » :\n\n$body\n\n"
            . 'Pour répondre : ' . url("/compte/demande?id={$ticket['id']}") . mail_footer());
    } else {
        send_mail((string)config('support_email'), "Nouvelle réponse, demande n°{$ticket['id']}",
            "{$user['name']} : $body\n\n" . url("/admin/ticket?id={$ticket['id']}"));
    }
}
