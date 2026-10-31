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
        'alerts' => array_filter(admin_clients([]), fn($c) => isset($c['alerts']['fin_depassee']) || isset($c['alerts']['depassement'])),
        'byPlan' => q("SELECT p.name, COUNT(l.id) AS total, SUM(l.status = 'active') AS active, SUM(l.status = 'essai') AS essai
                       FROM plans p LEFT JOIN licences l ON l.plan_code = p.code GROUP BY p.code, p.name, p.sort_order ORDER BY p.sort_order")->fetchAll(),
    ], 'Administration');
}

/** Filtres de la liste des clients : alertes. */
const CLIENT_ALERT_FILTERS = ['fin_depassee' => 'Fin dépassée', 'fin_proche' => 'Fin dans les 30 jours', 'depassement' => 'Au-delà de la formule',
    'sans_licence' => 'Sans licence'];

/** Structures avec licence, responsable, membres, utilisation et alertes (filtres : q, etat, formule, type, alerte). */
function admin_clients(array $filters): array
{
    $where = [];
    $params = [];
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(o.name LIKE ? OR u.email LIKE ? OR u.name LIKE ? OR o.contact_email LIKE ? OR o.siret LIKE ?)';
        array_push($params, ...array_fill(0, 5, '%' . $filters['q'] . '%'));
    }
    foreach (['etat' => 'l.status', 'formule' => 'l.plan_code', 'type' => 'o.kind'] as $key => $column) {
        if (($filters[$key] ?? '') !== '') {
            $where[] = "$column = ?";
            $params[] = $filters[$key];
        }
    }
    $clients = q('SELECT o.*, u.name AS owner_name, u.email AS owner_email, l.plan_code, l.status, l.status AS licence_status, l.billing,
                         l.starts_at, l.ends_at, p.name AS plan_name, p.max_animals, p.monthly_price_cents, p.yearly_price_cents,
                         (SELECT COUNT(*) FROM memberships m WHERE m.organisation_id = o.id) AS members
                  FROM organisations o JOIN users u ON u.id = o.owner_user_id
                  LEFT JOIN licences l ON l.organisation_id = o.id LEFT JOIN plans p ON p.code = l.plan_code
                  ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY o.created_at DESC', $params)->fetchAll();
    $result = [];
    foreach ($clients as $client) {
        $client['usage'] = organisation_usage((int)$client['id']);
        $client['alerts'] = licence_alerts($client['licence_status'] ? $client : null, $client['usage']['present']);
        if (($filters['alerte'] ?? '') === '' || isset($client['alerts'][$filters['alerte']])) {
            $result[] = $client;
        }
    }
    return $result;
}

function page_admin_clients(): void
{
    require_admin();
    $filters = ['q' => input('q'), 'etat' => input('etat'), 'formule' => input('formule'), 'type' => input('type'), 'alerte' => input('alerte')];
    $clients = admin_clients($filters);
    if (input('format') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="clients-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 pour Excel
        fputcsv($out, ['N°', 'Structure', 'Type', 'Titulaire', 'E-mail du titulaire', 'E-mail de contact', 'Téléphone', 'SIRET / RNA',
            'Membres', 'Formule', 'État', 'Facturation', 'Début', 'Fin', 'Animaux présents', 'Animaux en tout', 'Limite', 'Alertes', 'Créée le'], ';');
        foreach ($clients as $c) {
            fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'$v" : $v, [
                $c['id'], $c['name'], $c['kind'], $c['owner_name'], $c['owner_email'], $c['contact_email'], $c['phone'], $c['siret'],
                $c['members'], $c['plan_name'] ?? '', LICENCE_STATUSES[$c['licence_status']] ?? '', $c['billing'] === 'an' ? 'Annuelle' : 'Mensuelle',
                $c['starts_at'] ?? '', $c['ends_at'] ?? '', $c['usage']['present'], $c['usage']['animals'], $c['max_animals'] ?? 'illimité',
                implode(', ', $c['alerts']), substr((string)$c['created_at'], 0, 10),
            ]), ';');
        }
        fclose($out);
        audit('admin.export', 'Clients (' . count($clients) . ')');
        exit;
    }
    view('admin/clients', ['clients' => $clients, 'filters' => $filters, 'plans' => plans_all()], 'Clients');
}

/** Toutes les formules, y compris celles qui ne sont plus proposées. */
function plans_all(): array
{
    return q('SELECT p.*, (SELECT COUNT(*) FROM licences l WHERE l.plan_code = p.code) AS licences FROM plans p ORDER BY p.sort_order, p.code')->fetchAll();
}

function page_admin_client(): void
{
    $admin = require_admin();
    $id = (int)input('id');
    $organisation = q('SELECT o.*, u.name AS owner_name, u.email AS owner_email FROM organisations o JOIN users u ON u.id = o.owner_user_id WHERE o.id = ?',
        [$id])->fetch();
    if (!$organisation) {
        not_found();
    }
    if (is_post()) {
        csrf_check();
        $error = null;
        switch (input('action', 'licence')) {
            case 'licence':
                $plan = input('plan');
                $status = input('status');
                $billing = input('billing') === 'an' ? 'an' : 'mois';
                $startsAt = input('starts_at') ?: date('Y-m-d');
                $endsAt = input('ends_at');
                if (!in_array($plan, array_column(plans_all(), 'code'), true) || !array_key_exists($status, LICENCE_STATUSES)
                    || !strtotime($startsAt) || ($endsAt !== '' && (!strtotime($endsAt) || $endsAt < $startsAt))) {
                    $error = 'Vérifiez la formule, l’état et les dates (la fin ne peut pas précéder le début).';
                } else {
                    save_licence($id, $plan, $status, $billing, $startsAt, $endsAt, input('notes'));
                    flash('Licence enregistrée.');
                }
                break;
            case 'prolonger':
                $period = ['mois' => '1 month', 'an' => '1 year'][input('duree')] ?? null;
                $end = $period ? extend_licence($id, $period) : null;
                if ($end) {
                    flash('Licence prolongée jusqu’au ' . format_date($end) . '.');
                } else {
                    $error = 'Aucune licence à prolonger.';
                }
                break;
            case 'etat':
                $licence = organisation_licence($id);
                $status = input('status');
                if (!$licence || !in_array($status, ['active', 'suspendue', 'resiliee'], true)) {
                    $error = 'Aucune licence à modifier.';
                } else {
                    save_licence($id, $licence['plan_code'], $status, $licence['billing'], $licence['starts_at'], (string)$licence['ends_at'], (string)$licence['notes']);
                    flash('Licence : ' . LICENCE_STATUSES[$status] . '.');
                }
                break;
            case 'infos':
                if (mb_strlen(input('name')) < 2 || !in_array(input('kind'), ORGANISATION_KINDS, true)
                    || (input('contact_email') !== '' && !filter_var(input('contact_email'), FILTER_VALIDATE_EMAIL))) {
                    $error = 'Indiquez un nom, un type et une adresse e-mail valide.';
                } else {
                    q('UPDATE organisations SET name = ?, kind = ?, contact_email = ?, phone = ?, address = ?, siret = ? WHERE id = ?',
                        [input('name'), input('kind'), input('contact_email'), input('phone'), input('address'), input('siret'), $id]);
                    audit('admin.structure.infos', "Structure n°$id « " . input('name') . ' »');
                    flash('Coordonnées enregistrées.');
                }
                break;
            case 'ajouter':
                $user = find_user_by_email(input('email'));
                $roles = posted_roles();
                if (!$user || $roles === '') {
                    $error = 'Aucun compte n’a cette adresse, ou aucun rôle n’est coché. Pour une personne sans compte, la structure l’invite depuis son espace.';
                } else {
                    add_member($id, (int)$user['id'], $roles);
                    flash("{$user['email']} est membre (" . role_labels($roles) . ').');
                }
                break;
            case 'roles':
                $error = set_member_roles($id, (int)input('member'), posted_roles());
                $error ?? flash('Rôles enregistrés.');
                break;
            case 'retirer':
                $error = (int)input('member') === (int)$organisation['owner_user_id']
                    ? 'Le titulaire ne peut pas être retiré : désignez d’abord un autre titulaire.'
                    : remove_member($id, (int)input('member'));
                $error ?? flash('Membre retiré.');
                break;
            case 'titulaire':
                $error = transfer_organisation($id, (int)input('member'));
                $error ?? flash('Titulaire changé.');
                break;
            case 'ecrire':
                $subject = input('subject');
                $body = trim((string)($_POST['body'] ?? ''));
                $managers = q("SELECT u.email FROM memberships m JOIN users u ON u.id = m.user_id
                               WHERE m.organisation_id = ? AND FIND_IN_SET('responsable', m.roles) AND u.disabled_at IS NULL", [$id])->fetchAll(PDO::FETCH_COLUMN);
                if (mb_strlen($subject) < 3 || mb_strlen($body) < 10 || !$managers) {
                    $error = 'Écrivez un objet et un message (et la structure doit avoir un responsable).';
                } else {
                    foreach ($managers as $email) {
                        send_mail($email, $subject, $body . mail_footer());
                    }
                    audit('admin.structure.email', "Structure n°$id : « $subject » à " . implode(', ', $managers), (int)$admin['id']);
                    flash('Message envoyé à ' . implode(', ', $managers) . '.');
                }
                break;
        }
        if ($error) {
            flash($error, 'error');
        }
        redirect("/admin/client?id=$id");
    }
    $licence = organisation_licence($id);
    $usage = organisation_usage($id, true);
    view('admin/client', [
        'organisation' => $organisation,
        'licence' => $licence,
        'alerts' => licence_alerts($licence, $usage['present']),
        'usage' => $usage,
        'plans' => plans_all(),
        'members' => organisation_members($id),
        'invitations' => pending_invitations($id),
        'devices' => q('SELECT d.*, u.email FROM devices d JOIN memberships m ON m.user_id = d.user_id JOIN users u ON u.id = d.user_id
                        WHERE m.organisation_id = ? AND d.revoked_at IS NULL ORDER BY d.last_seen_at DESC', [$id])->fetchAll(),
        'tickets' => q('SELECT t.*, u.name AS user_name FROM tickets t JOIN users u ON u.id = t.user_id WHERE t.organisation_id = ? ORDER BY t.updated_at DESC',
            [$id])->fetchAll(),
        'log' => q("SELECT a.*, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE a.details LIKE ? ORDER BY a.created_at DESC, a.id DESC LIMIT 50",
            ["Structure n°$id %"])->fetchAll(),
    ], $organisation['name']);
}

/** Formules : noms, prix, limite d'animaux, ordre, proposée ou non (une formule utilisée n'est jamais supprimée). */
function page_admin_plans(): void
{
    require_admin();
    if (is_post()) {
        csrf_check();
        $code = input('code');
        $euros = fn(string $name) => (int)round((float)str_replace([',', ' '], ['.', ''], input($name)) * 100);
        $max = input('max_animals');
        $values = [input('name'), $euros('monthly'), $euros('yearly'), $max === '' ? null : max(1, (int)$max), (int)input('sort_order'),
            empty($_POST['active']) ? 0 : 1];
        if (mb_strlen($values[0]) < 2 || $values[1] < 0 || $values[2] < 0) {
            flash('Indiquez un nom et des prix positifs.', 'error');
        } elseif (input('action') === 'nouvelle') {
            if (!preg_match('/^[a-z][a-z0-9_-]{1,29}$/', $code) || q('SELECT 1 FROM plans WHERE code = ?', [$code])->fetch()) {
                flash('Code invalide ou déjà utilisé (lettres minuscules, chiffres, « - », « _ »).', 'error');
            } else {
                q('INSERT INTO plans (name, monthly_price_cents, yearly_price_cents, max_animals, sort_order, active, code) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [...$values, $code]);
                audit('admin.formule.creation', "Formule $code « {$values[0]} »");
                flash('Formule créée.');
            }
        } else {
            q('UPDATE plans SET name = ?, monthly_price_cents = ?, yearly_price_cents = ?, max_animals = ?, sort_order = ?, active = ? WHERE code = ?',
                [...$values, $code]);
            audit('admin.formule.modification', "Formule $code « {$values[0]} » : " . format_price($values[1]) . '/mois, '
                . ($values[3] ?? 'illimité') . ' animaux' . ($values[5] ? '' : ', retirée'));
            flash('Formule enregistrée.');
        }
        redirect('/admin/formules');
    }
    view('admin/plans', ['plans' => plans_all()], 'Formules');
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
