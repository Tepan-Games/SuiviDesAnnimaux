<?php
declare(strict_types=1);

// ===== Échéances des licences (règles du 31/10/2026) =====
// Après la date de fin (durées réglables dans Administration › Réglages, table site_settings) :
//   - période de grâce (licence annuelle : 30 jours ; mensuelle : 7 jours) : tout fonctionne, avec des alertes ;
//   - puis licence expirée : les appareils lisent encore les données mais n'en envoient plus, l'interface en ligne les
//     montre encore pendant 30 jours ;
//   - ensuite, les données synchronisées de la structure sont supprimées du site (si la suppression automatique est active).
// Les e-mails et les changements sont faits par tools/echeances-licences.php (tâche planifiée quotidienne chez OVH),
// aussi lançable depuis le tableau de bord de l'administration. Une licence prolongée repart de zéro.

/** Réglages des échéances : clé => [libellé, valeur par défaut, minimum, maximum]. */
const LICENCE_SETTINGS = [
    'licence.grace_jours_an' => ['Période de grâce d’une licence annuelle (jours)', 30, 0, 365],
    'licence.grace_jours_mois' => ['Période de grâce d’une licence mensuelle (jours)', 7, 0, 365],
    'licence.donnees_jours' => ['Données gardées après la grâce, en lecture seule (jours)', 30, 1, 3650],
    'licence.alerte_jours' => ['Alerte avant la date de fin (jours)', 30, 0, 365],
    'licence.suppression_auto' => ['Supprimer automatiquement les données à la fin de ce délai', 1, 0, 1],
];

/** Réglage entier de l'administration (valeur par défaut si absent ou si la table n'existe pas encore). */
function site_setting(string $key): int
{
    static $values = null;
    if ($values === null) {
        try {
            $values = q('SELECT name, value FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException) {
            $values = [];
        }
    }
    $definition = LICENCE_SETTINGS[$key] ?? null;
    $value = isset($values[$key]) ? (int)$values[$key] : (int)($definition[1] ?? 0);
    return $definition ? max($definition[2], min($definition[3], $value)) : $value;
}

function save_site_setting(string $key, int $value): void
{
    q('INSERT INTO site_settings (name, value, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
        [$key, (string)$value, now()]);
}

/** Dates clés d'une licence qui a une date de fin : fin, fin de la grâce, suppression des données. Null sans date de fin. */
function licence_timeline(?array $licence): ?array
{
    if (!$licence || empty($licence['ends_at'])) {
        return null;
    }
    $end = substr((string)$licence['ends_at'], 0, 10);
    $graceDays = site_setting(($licence['billing'] ?? 'mois') === 'an' ? 'licence.grace_jours_an' : 'licence.grace_jours_mois');
    $grace = date('Y-m-d', strtotime("$end +$graceDays days"));
    return ['end' => $end, 'grace_end' => $grace, 'deletion' => date('Y-m-d', strtotime($grace . ' +' . site_setting('licence.donnees_jours') . ' days'))];
}

/**
 * Étape d'une licence aujourd'hui : « en_cours », « grace » (fin passée, encore active), « expiree » (lecture seule,
 * données gardées), « supprimee » (données effacées), ou l'état choisi à la main (« suspendue », « resiliee »…).
 */
function licence_phase(?array $licence, ?string $today = null): string
{
    if (!$licence) {
        return 'sans_licence';
    }
    $today ??= date('Y-m-d');
    if (!empty($licence['data_deleted_at'])) {
        return 'supprimee';
    }
    $timeline = licence_timeline($licence);
    if (in_array($licence['status'], ['active', 'essai'], true)) {
        if (!$timeline || $today <= $timeline['end']) {
            return 'en_cours';
        }
        return $today <= $timeline['grace_end'] ? 'grace' : 'expiree';
    }
    return $licence['status'];
}

/** Les appareils ne peuvent plus envoyer de données (suspendue, expirée, résiliée, ou grâce terminée). */
function licence_is_read_only(?array $licence): bool
{
    return $licence !== null && (in_array($licence['status'], SYNC_READ_ONLY_STATUSES, true)
            || in_array(licence_phase($licence), ['expiree', 'supprimee'], true));
}

/** État affiché d'une licence (« Expirée » dès la fin de la grâce, même avant le passage de la tâche planifiée). */
function licence_effective_status(?array $licence): ?string
{
    if (!$licence) {
        return null;
    }
    return in_array(licence_phase($licence), ['expiree', 'supprimee'], true) && in_array($licence['status'], ['active', 'essai'], true)
        ? 'expiree' : $licence['status'];
}

/** Message pour le client sur l'échéance de sa licence (null si rien à dire). */
function licence_notice(?array $licence): ?array
{
    $timeline = licence_timeline($licence);
    $phase = licence_phase($licence);
    if ($phase === 'supprimee') {
        return ['error', 'La licence a expiré et les données synchronisées de la structure ont été supprimées du site le '
            . format_date($licence['data_deleted_at']) . '. Les données restent sur vos appareils ; renouvelez la licence pour synchroniser à nouveau.'];
    }
    if (!$timeline) {
        return null;
    }
    $renew = ' Pour la renouveler, écrivez-nous depuis la page Assistance.';
    return match (true) {
        $phase === 'grace' => ['error', 'La licence est arrivée à échéance le ' . format_date($timeline['end'])
            . '. Elle reste active jusqu’au ' . format_date($timeline['grace_end']) . ' (période de grâce).' . $renew],
        $phase === 'expiree' || ($licence['status'] === 'expiree' || $licence['status'] === 'resiliee') && $timeline['end'] < date('Y-m-d')
            => ['error', 'La licence a expiré : vos appareils lisent encore les données mais n’en envoient plus.'
                . (site_setting('licence.suppression_auto') ? ' Les données synchronisées seront supprimées du site le ' . format_date($timeline['deletion']) . '.' : '')
                . $renew],
        $phase === 'en_cours' && $timeline['end'] <= date('Y-m-d', strtotime('+' . site_setting('licence.alerte_jours') . ' days'))
            => ['info', 'La licence se termine le ' . format_date($timeline['end']) . '.' . $renew],
        default => null,
    };
}

/** Adresses des responsables d'une structure (comptes actifs). */
function organisation_manager_emails(int $organisationId): array
{
    return q("SELECT u.email FROM memberships m JOIN users u ON u.id = m.user_id
              WHERE m.organisation_id = ? AND FIND_IN_SET('responsable', m.roles) AND u.disabled_at IS NULL", [$organisationId])->fetchAll(PDO::FETCH_COLUMN);
}

function mail_managers(int $organisationId, string $subject, string $body): void
{
    foreach (organisation_manager_emails($organisationId) as $email) {
        send_mail($email, $subject, "Bonjour,\n\n$body" . mail_footer());
    }
}

/** Efface les fichiers de synchronisation d'une structure (et l'état gardé en cache). */
function delete_sync_data(int $organisationId): void
{
    $root = sync_root($organisationId);
    if (is_dir($root)) {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }
    @unlink(STORAGE_DIR . "/cache/etat-$organisationId.json");
}

/**
 * Passe en revue les licences qui ont une date de fin : e-mail au début de la grâce, passage à « expirée » à la fin de la
 * grâce (e-mail avec la date de suppression), suppression des données un mois plus tard (e-mail). Renvoie le bilan.
 */
function process_licence_deadlines(?string $today = null): array
{
    $today ??= date('Y-m-d');
    $report = ['grace' => 0, 'expirees' => 0, 'supprimees' => 0];
    $licences = q("SELECT l.*, o.name AS organisation_name FROM licences l JOIN organisations o ON o.id = l.organisation_id
                   WHERE l.ends_at IS NOT NULL AND l.ends_at < ? AND l.data_deleted_at IS NULL", [$today])->fetchAll();
    foreach ($licences as $licence) {
        $id = (int)$licence['organisation_id'];
        $name = $licence['organisation_name'];
        $timeline = licence_timeline($licence);
        $phase = licence_phase($licence, $today);
        if ($phase === 'grace' && $licence['grace_notified_at'] === null) {
            mail_managers($id, "Votre licence est arrivée à échéance : $name",
                "La licence de « $name » sur Suivi des animaux est arrivée à échéance le " . format_date($timeline['end']) . ".\n\n"
                . 'Elle reste active jusqu’au ' . format_date($timeline['grace_end']) . " (période de grâce). Ensuite, vos appareils ne pourront "
                . 'plus envoyer de données' . (site_setting('licence.suppression_auto')
                    ? ', et les données synchronisées seront supprimées du site le ' . format_date($timeline['deletion']) : '') . ".\n\n"
                . 'Pour la renouveler, répondez à cet e-mail ou écrivez-nous depuis votre espace : ' . url('/compte/assistance'));
            q('UPDATE licences SET grace_notified_at = ? WHERE id = ?', [now(), $licence['id']]);
            audit('licence.grace', "Structure n°$id : fin le {$timeline['end']}, grâce jusqu’au {$timeline['grace_end']}", null);
            $report['grace']++;
        } elseif ($phase === 'expiree' && $licence['expired_notified_at'] === null) {
            mail_managers($id, "Votre licence a expiré : $name",
                "La période de grâce de la licence de « $name » s’est terminée le " . format_date($timeline['grace_end']) . ".\n\n"
                . 'Vos appareils lisent encore les données mais n’en envoient plus.' . (site_setting('licence.suppression_auto')
                    ? ' Les données restent consultables dans l’interface en ligne jusqu’au ' . format_date($timeline['deletion'])
                      . ', puis elles seront supprimées du site (pas de vos appareils).' : '') . "\n\n"
                . 'Pour renouveler la licence : ' . url('/compte/assistance'));
            q("UPDATE licences SET status = 'expiree', expired_notified_at = ?, updated_at = ? WHERE id = ?", [now(), now(), $licence['id']]);
            audit('licence.expiration', "Structure n°$id : grâce terminée le {$timeline['grace_end']}, suppression le {$timeline['deletion']}", null);
            $report['expirees']++;
        } elseif (site_setting('licence.suppression_auto') && in_array($licence['status'], ['expiree', 'resiliee'], true) && $today > $timeline['deletion']) {
            delete_sync_data($id);
            q('UPDATE licences SET data_deleted_at = ?, updated_at = ? WHERE id = ?', [now(), now(), $licence['id']]);
            mail_managers($id, "Données supprimées : $name",
                "Comme annoncé, les données synchronisées de « $name » ont été supprimées du site, " . site_setting('licence.donnees_jours')
                . " jours après l’expiration de la licence.\n\nElles restent sur vos appareils. Si vous renouvelez la licence, vos appareils pourront les "
                . 'envoyer à nouveau : ' . url('/compte/assistance'));
            audit('licence.suppression_donnees', "Structure n°$id : données synchronisées supprimées", null);
            $report['supprimees']++;
        }
    }
    return $report;
}
