<?php
declare(strict_types=1);

// ===== Administration du site : clients, licences, comptes, assistance, journal =====

function page_admin_dashboard(): void
{
    require_admin();
    $count = fn(string $sql, array $params = []) => (int)q($sql, $params)->fetchColumn();
    view('admin/dashboard', [
        'stats' => [
            'Comptes' => $count('SELECT COUNT(*) FROM users'),
            'Structures' => $count('SELECT COUNT(*) FROM organisations'),
            'Licences payantes actives' => $count("SELECT COUNT(*) FROM licences WHERE status = 'active' AND plan_code <> 'decouverte'"),
            'Demandes ouvertes' => $count("SELECT COUNT(*) FROM tickets WHERE status IN ('ouvert', 'en_cours')"),
        ],
        'revenue' => (int)q("SELECT COALESCE(SUM(CASE WHEN l.billing = 'an' THEN p.yearly_price_cents / 12 ELSE p.monthly_price_cents END), 0)
                            FROM licences l JOIN plans p ON p.code = l.plan_code WHERE l.status = 'active'")->fetchColumn(),
        'expiring' => q("SELECT o.id, o.name, l.ends_at, p.name AS plan_name FROM licences l JOIN organisations o ON o.id = l.organisation_id
                         JOIN plans p ON p.code = l.plan_code WHERE l.status = 'active' AND l.ends_at IS NOT NULL AND l.ends_at <= ?
                         ORDER BY l.ends_at", [date('Y-m-d', time() + 30 * 86400)])->fetchAll(),
        'tickets' => q("SELECT t.*, u.name AS user_name FROM tickets t JOIN users u ON u.id = t.user_id
                        WHERE t.status IN ('ouvert', 'en_cours') ORDER BY FIELD(t.priority, 'urgente', 'haute', 'normale', 'basse'), t.updated_at LIMIT 10")->fetchAll(),
    ], 'Administration');
}

function page_admin_clients(): void
{
    require_admin();
    $search = input('q');
    $params = [];
    $where = '';
    if ($search !== '') {
        $where = 'WHERE o.name LIKE ? OR u.email LIKE ? OR o.contact_email LIKE ?';
        $params = array_fill(0, 3, '%' . $search . '%');
    }
    $clients = q("SELECT o.*, u.name AS owner_name, u.email AS owner_email, l.plan_code, l.status AS licence_status, l.ends_at, p.name AS plan_name,
                         (SELECT COUNT(*) FROM memberships m WHERE m.organisation_id = o.id) AS members
                  FROM organisations o JOIN users u ON u.id = o.owner_user_id
                  LEFT JOIN licences l ON l.organisation_id = o.id LEFT JOIN plans p ON p.code = l.plan_code
                  $where ORDER BY o.created_at DESC", $params)->fetchAll();
    view('admin/clients', ['clients' => $clients, 'search' => $search], 'Clients');
}

function page_admin_client(): void
{
    require_admin();
    $id = (int)input('id');
    $organisation = q('SELECT o.*, u.name AS owner_name, u.email AS owner_email FROM organisations o JOIN users u ON u.id = o.owner_user_id WHERE o.id = ?',
        [$id])->fetch();
    if (!$organisation) {
        not_found();
    }
    if (is_post()) {
        csrf_check();
        $plan = input('plan');
        $status = input('status');
        $billing = input('billing') === 'an' ? 'an' : 'mois';
        $startsAt = input('starts_at') ?: date('Y-m-d');
        if (!in_array($plan, array_column(plans(), 'code'), true) || !array_key_exists($status, LICENCE_STATUSES)
            || !strtotime($startsAt) || (input('ends_at') !== '' && !strtotime(input('ends_at')))) {
            flash('Vérifiez la formule, l’état et les dates.', 'error');
        } else {
            save_licence($id, $plan, $status, $billing, $startsAt, input('ends_at'), input('notes'));
            flash('Licence enregistrée.');
        }
        redirect("/admin/client?id=$id");
    }
    view('admin/client', [
        'organisation' => $organisation,
        'licence' => organisation_licence($id),
        'plans' => plans(),
        'members' => organisation_members($id),
        'tickets' => q('SELECT t.*, u.name AS user_name FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.organisation_id = ? ORDER BY t.updated_at DESC',
            [$id])->fetchAll(),
        'log' => q("SELECT * FROM audit_log WHERE details LIKE ? ORDER BY created_at DESC LIMIT 30", ["Structure n°$id%"])->fetchAll(),
    ], $organisation['name']);
}

function page_admin_users(): void
{
    $admin = require_admin();
    if (is_post()) {
        csrf_check();
        $userId = (int)input('user');
        if ($userId === (int)$admin['id']) {
            flash('Vous ne pouvez pas modifier votre propre compte ici.', 'error');
        } else {
            match (input('action')) {
                'desactiver' => q('UPDATE users SET disabled_at = ? WHERE id = ?', [now(), $userId]),
                'reactiver' => q('UPDATE users SET disabled_at = NULL WHERE id = ?', [$userId]),
                'confirmer' => q('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [now(), $userId]),
                default => null,
            };
            audit('admin.compte.' . input('action'), "Compte n°$userId");
            flash('Compte mis à jour.');
        }
        redirect('/admin/utilisateurs' . (input('q') !== '' ? '?q=' . urlencode(input('q')) : ''));
    }
    $search = input('q');
    $users = $search === ''
        ? q('SELECT * FROM users ORDER BY created_at DESC LIMIT 200')->fetchAll()
        : q('SELECT * FROM users WHERE email LIKE ? OR name LIKE ? ORDER BY created_at DESC LIMIT 200', ["%$search%", "%$search%"])->fetchAll();
    view('admin/users', ['users' => $users, 'search' => $search], 'Comptes');
}

function page_admin_support(): void
{
    require_admin();
    $status = input('etat', 'actives');
    $where = match ($status) {
        'actives' => "WHERE t.status IN ('ouvert', 'en_cours', 'attente')",
        'toutes' => '',
        default => array_key_exists($status, TICKET_STATUSES) ? 'WHERE t.status = ' . db()->quote($status) : '',
    };
    $tickets = q("SELECT t.*, u.name AS user_name, u.email AS user_email, o.name AS organisation_name FROM tickets t
                  JOIN users u ON u.id = t.user_id LEFT JOIN organisations o ON o.id = t.organisation_id
                  $where ORDER BY FIELD(t.priority, 'urgente', 'haute', 'normale', 'basse'), t.updated_at DESC")->fetchAll();
    view('admin/support', ['tickets' => $tickets, 'status' => $status], 'Assistance');
}

function page_admin_ticket(): void
{
    $admin = require_admin();
    $ticket = find_ticket((int)input('id'));
    if (!$ticket) {
        not_found();
    }
    if (is_post()) {
        csrf_check();
        $status = input('status');
        if (array_key_exists(input('priority'), TICKET_PRIORITIES)) {
            q('UPDATE tickets SET priority = ? WHERE id = ?', [input('priority'), $ticket['id']]);
        }
        if (mb_strlen(input('body')) >= 2) {
            reply_ticket($ticket, $admin, input('body'), true, array_key_exists($status, TICKET_STATUSES) ? $status : null);
        } elseif (array_key_exists($status, TICKET_STATUSES)) {
            q('UPDATE tickets SET status = ?, updated_at = ? WHERE id = ?', [$status, now(), $ticket['id']]);
        }
        flash('Demande mise à jour.');
        redirect("/admin/ticket?id={$ticket['id']}");
    }
    view('admin/ticket', ['ticket' => $ticket, 'messages' => ticket_messages((int)$ticket['id'])], "Demande n°{$ticket['id']}");
}

function page_admin_log(): void
{
    require_admin();
    view('admin/log', ['entries' => q('SELECT a.*, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 300')->fetchAll()],
        'Journal');
}
