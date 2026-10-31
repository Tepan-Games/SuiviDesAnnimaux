<?php
declare(strict_types=1);

// ===== Structures, membres, invitations et licences =====

/** Structures dont la personne est membre, avec ses rôles et la licence. */
function user_organisations(int $userId): array
{
    return q('SELECT o.*, m.roles, l.plan_code, l.status AS licence_status, l.ends_at, l.billing, l.data_deleted_at, p.name AS plan_name,
                     (SELECT COUNT(*) FROM memberships m2 WHERE m2.organisation_id = o.id) AS members
              FROM memberships m JOIN organisations o ON o.id = m.organisation_id
              LEFT JOIN licences l ON l.organisation_id = o.id LEFT JOIN plans p ON p.code = l.plan_code
              WHERE m.user_id = ? ORDER BY o.name', [$userId])->fetchAll();
}

/** Structure vue par un de ses membres (null s'il n'en fait pas partie). */
function organisation_for_member(int $organisationId, int $userId): ?array
{
    return q('SELECT o.*, m.roles FROM organisations o JOIN memberships m ON m.organisation_id = o.id
              WHERE o.id = ? AND m.user_id = ?', [$organisationId, $userId])->fetch() ?: null;
}

function can_manage(string $roles): bool
{
    return in_array('responsable', explode(',', $roles), true);
}

/** Nouvelle structure : la personne en est responsable, avec la formule Découverte. */
function create_organisation(int $userId, string $name, string $kind): int
{
    $pdo = db();
    $pdo->beginTransaction();
    q('INSERT INTO organisations (name, kind, owner_user_id, created_at) VALUES (?, ?, ?, ?)',
        [trim($name), in_array($kind, ORGANISATION_KINDS, true) ? $kind : 'Autre', $userId, now()]);
    $id = (int)$pdo->lastInsertId();
    q('INSERT INTO memberships (organisation_id, user_id, roles, created_at) VALUES (?, ?, ?, ?)', [$id, $userId, 'responsable', now()]);
    q('INSERT INTO licences (organisation_id, plan_code, status, starts_at, updated_at) VALUES (?, ?, ?, ?, ?)',
        [$id, 'decouverte', 'active', date('Y-m-d'), now()]);
    $pdo->commit();
    audit('structure.creation', "Structure n°$id « $name » ($kind)", $userId);
    return $id;
}

function organisation_members(int $organisationId): array
{
    return q('SELECT m.*, u.name, u.email, u.last_login_at FROM memberships m JOIN users u ON u.id = m.user_id
              WHERE m.organisation_id = ? ORDER BY u.name', [$organisationId])->fetchAll();
}

function pending_invitations(int $organisationId): array
{
    return q('SELECT * FROM invitations WHERE organisation_id = ? AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > ?
              ORDER BY created_at DESC', [$organisationId, now()])->fetchAll();
}

/** Invitation par e-mail : renvoie le lien (affiché une fois et envoyé par e-mail ; seul son code haché est gardé). */
function create_invitation(array $organisation, string $email, string $roles, array $by): string
{
    $token = random_token();
    q('INSERT INTO invitations (organisation_id, email, roles, token_hash, invited_by, expires_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$organisation['id'], mb_strtolower(trim($email)), $roles, token_hash($token), $by['id'], date('Y-m-d H:i:s', time() + 14 * 86400), now()]);
    $link = url('/invitation?c=' . $token);
    send_mail($email, "Invitation : {$organisation['name']}",
        "Bonjour,\n\n{$by['name']} vous invite à rejoindre « {$organisation['name']} » sur Suivi des animaux"
        . ' (' . role_labels($roles) . ").\n\nPour accepter, ouvrez ce lien (valable 14 jours) :\n$link\n\n"
        . "Vous pourrez créer votre compte gratuitement si vous n’en avez pas encore." . mail_footer());
    audit('invitation.creation', "Structure n°{$organisation['id']} : $email (" . role_labels($roles) . ')', (int)$by['id']);
    return $link;
}

function find_invitation(string $token): ?array
{
    return q('SELECT i.*, o.name AS organisation_name FROM invitations i JOIN organisations o ON o.id = i.organisation_id
              WHERE i.token_hash = ? AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND i.expires_at > ?',
        [token_hash($token), now()])->fetch() ?: null;
}

/** Accepte une invitation : la personne devient membre (ses rôles sont mis à jour si elle l'était déjà). */
function accept_invitation(array $invitation, array $user): void
{
    q('INSERT INTO memberships (organisation_id, user_id, roles, created_at) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE roles = VALUES(roles)', [$invitation['organisation_id'], $user['id'], $invitation['roles'], now()]);
    q('UPDATE invitations SET accepted_at = ? WHERE id = ?', [now(), $invitation['id']]);
    audit('invitation.acceptation', "Structure n°{$invitation['organisation_id']} : {$user['email']}", (int)$user['id']);
}

function revoke_invitation(int $organisationId, int $invitationId): void
{
    q('UPDATE invitations SET revoked_at = ? WHERE id = ? AND organisation_id = ?', [now(), $invitationId, $organisationId]);
}

/** Retire un membre (jamais le dernier responsable). Renvoie un message d'erreur, ou null. */
function remove_member(int $organisationId, int $userId): ?string
{
    $member = q('SELECT roles FROM memberships WHERE organisation_id = ? AND user_id = ?', [$organisationId, $userId])->fetch();
    if (!$member) {
        return 'Ce membre ne fait pas partie de la structure.';
    }
    if (can_manage($member['roles']) && count_managers($organisationId) <= 1) {
        return 'La structure doit garder au moins un responsable.';
    }
    q('DELETE FROM memberships WHERE organisation_id = ? AND user_id = ?', [$organisationId, $userId]);
    audit('membre.retrait', "Structure n°$organisationId : compte n°$userId");
    return null;
}

/** Change les rôles d'un membre (le dernier responsable le reste). Renvoie un message d'erreur, ou null. */
function set_member_roles(int $organisationId, int $userId, string $roles): ?string
{
    if ($roles === '') {
        return 'Cochez au moins un rôle.';
    }
    $member = q('SELECT roles FROM memberships WHERE organisation_id = ? AND user_id = ?', [$organisationId, $userId])->fetch();
    if (!$member) {
        return 'Ce membre ne fait pas partie de la structure.';
    }
    if (can_manage($member['roles']) && !can_manage($roles) && count_managers($organisationId) <= 1) {
        return 'La structure doit garder au moins un responsable.';
    }
    q('UPDATE memberships SET roles = ? WHERE organisation_id = ? AND user_id = ?', [$roles, $organisationId, $userId]);
    audit('membre.roles', "Structure n°$organisationId : compte n°$userId → " . role_labels($roles));
    return null;
}

function count_managers(int $organisationId): int
{
    return (int)q("SELECT COUNT(*) FROM memberships WHERE organisation_id = ? AND FIND_IN_SET('responsable', roles)", [$organisationId])->fetchColumn();
}

function organisation_licence(int $organisationId): ?array
{
    return q('SELECT l.*, p.name AS plan_name, p.monthly_price_cents, p.yearly_price_cents, p.max_animals
              FROM licences l JOIN plans p ON p.code = l.plan_code WHERE l.organisation_id = ?', [$organisationId])->fetch() ?: null;
}

function plans(): array
{
    return q('SELECT * FROM plans WHERE active = 1 ORDER BY sort_order')->fetchAll();
}

const LICENCE_STATUSES = ['essai' => 'Essai', 'active' => 'Active', 'suspendue' => 'Suspendue', 'expiree' => 'Expirée', 'resiliee' => 'Résiliée'];

/** Licence modifiée par l'administration (formule, état, période, notes). */
function save_licence(int $organisationId, string $plan, string $status, string $billing, string $startsAt, string $endsAt, string $notes): void
{
    q('INSERT INTO licences (organisation_id, plan_code, status, billing, starts_at, ends_at, notes, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE plan_code = VALUES(plan_code), status = VALUES(status), billing = VALUES(billing),
           starts_at = VALUES(starts_at), ends_at = VALUES(ends_at), notes = VALUES(notes), updated_at = VALUES(updated_at),
           grace_notified_at = NULL, expired_notified_at = NULL, data_deleted_at = NULL',
        [$organisationId, $plan, $status, $billing, $startsAt, $endsAt !== '' ? $endsAt : null, $notes, now()]);
    audit('licence.modification', "Structure n°$organisationId : $plan, $status, du $startsAt au " . ($endsAt ?: '—'));
}

// ===== Utilisation et alertes des licences (administration et espace client) =====

/** Taille d'un dossier (octets), sous-dossiers compris. */
function directory_size(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $size += $file->isFile() ? $file->getSize() : 0;
    }
    return $size;
}

function format_bytes(int $bytes): string
{
    foreach (['Go' => 1024 ** 3, 'Mo' => 1024 ** 2, 'Ko' => 1024] as $unit => $factor) {
        if ($bytes >= $factor) {
            return number_format($bytes / $factor, 1, ',', ' ') . " $unit";
        }
    }
    return "$bytes octets";
}

/**
 * Utilisation d'une structure d'après ses données synchronisées : animaux présents (ceux que compte la formule),
 * animaux en tout, date des dernières données reçues. $withStorage : taille des fichiers (plus long à calculer).
 */
function organisation_usage(int $organisationId, bool $withStorage = false): array
{
    $state = online_state($organisationId);
    $animals = $state['rows']['Animals'] ?? [];
    $usage = [
        'present' => count(array_filter($animals, fn($a) => ($a['Status'] ?? 'Présent') === 'Présent')),
        'animals' => count($animals),
        'synced_at' => $state['synced_at'],
    ];
    if ($withStorage) {
        $usage['storage'] = directory_size(sync_root($organisationId));
    }
    return $usage;
}

/**
 * Alertes d'une licence : [clé => libellé]. Clés : fin_proche (fin dans le délai d'alerte), grace (fin passée, période de
 * grâce), expiree (grâce terminée : lecture seule, données encore gardées), donnees_supprimees, depassement (plus
 * d'animaux présents que la formule n'en permet), sans_licence. $licence : ligne avec status, billing, ends_at,
 * data_deleted_at et max_animals (null = illimité).
 */
function licence_alerts(?array $licence, int $presentAnimals): array
{
    if (!$licence || empty($licence['status'])) {
        return ['sans_licence' => 'Sans licence'];
    }
    $alerts = [];
    $timeline = licence_timeline($licence);
    $phase = licence_phase($licence);
    if ($phase === 'supprimee') {
        $alerts['donnees_supprimees'] = 'Données supprimées le ' . format_date($licence['data_deleted_at']);
    } elseif ($phase === 'grace') {
        $alerts['grace'] = 'Grâce jusqu’au ' . format_date($timeline['grace_end']);
    } elseif ($timeline && ($phase === 'expiree' || (in_array($licence['status'], ['expiree', 'resiliee'], true) && $timeline['end'] < date('Y-m-d')))) {
        $alerts['expiree'] = site_setting('licence.suppression_auto') ? 'Suppression le ' . format_date($timeline['deletion']) : 'Expirée';
    } elseif ($phase === 'en_cours' && $timeline && $timeline['end'] <= date('Y-m-d', strtotime('+' . site_setting('licence.alerte_jours') . ' days'))) {
        $alerts['fin_proche'] = 'Fin le ' . format_date($timeline['end']);
    }
    if (($licence['max_animals'] ?? null) !== null && $presentAnimals > (int)$licence['max_animals']) {
        $alerts['depassement'] = "$presentAnimals animaux pour " . (int)$licence['max_animals'];
    }
    return $alerts;
}

/** Prolonge une licence d'une durée (« 1 month », « 1 year ») à partir de sa fin, ou d'aujourd'hui si elle est passée ou absente. */
function extend_licence(int $organisationId, string $period): ?string
{
    $licence = organisation_licence($organisationId);
    if (!$licence) {
        return null;
    }
    $from = $licence['ends_at'] && $licence['ends_at'] > date('Y-m-d') ? $licence['ends_at'] : date('Y-m-d');
    $end = date('Y-m-d', strtotime("$from +$period"));
    $status = in_array($licence['status'], ['suspendue', 'expiree', 'resiliee'], true) ? 'active' : $licence['status'];
    save_licence($organisationId, $licence['plan_code'], $status, $licence['billing'], $licence['starts_at'], $end, (string)$licence['notes']);
    return $end;
}

/** Ajoute un compte existant à une structure (ou change ses rôles s'il en est déjà membre). */
function add_member(int $organisationId, int $userId, string $roles): void
{
    q('INSERT INTO memberships (organisation_id, user_id, roles, created_at) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE roles = VALUES(roles)', [$organisationId, $userId, $roles, now()]);
    audit('membre.ajout', "Structure n°$organisationId : compte n°$userId → " . role_labels($roles));
}

/** Nouveau titulaire d'une structure : il doit en être membre, et devient responsable. Renvoie un message d'erreur, ou null. */
function transfer_organisation(int $organisationId, int $userId): ?string
{
    $member = q('SELECT roles FROM memberships WHERE organisation_id = ? AND user_id = ?', [$organisationId, $userId])->fetch();
    if (!$member) {
        return 'Le nouveau titulaire doit être membre de la structure.';
    }
    if (!can_manage($member['roles'])) {
        q('UPDATE memberships SET roles = ? WHERE organisation_id = ? AND user_id = ?',
            [implode(',', array_unique(array_merge(['responsable'], online_role_list($member['roles'])))), $organisationId, $userId]);
    }
    q('UPDATE organisations SET owner_user_id = ? WHERE id = ?', [$userId, $organisationId]);
    audit('structure.titulaire', "Structure n°$organisationId : compte n°$userId");
    return null;
}
