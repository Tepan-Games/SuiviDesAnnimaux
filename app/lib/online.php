<?php
declare(strict_types=1);

// ===== Interface en ligne : état des données d'une structure, reconstitué à partir des fichiers de synchronisation =====
// Même lecture que le moteur du logiciel (SuiviAnimaux.Core/Sync/SyncEngine.cs) : dernier état complet
// (instantanes/<horodatage>_<appareil>.json.gz), puis journaux (journaux/<horodatage>_<appareil>.json) que cet état
// ne contient pas encore (curseur de chaque appareil) ; sur un même enregistrement, la modification la plus récente
// l'emporte. Le résultat est gardé dans storage/cache/ tant qu'aucun fichier n'a été ajouté.

/** Format des fichiers de synchronisation compris par le site. */
const ONLINE_SYNC_FORMAT = 1;

/** Version du calcul gardé en cache (à incrémenter si la forme de l'état change). */
const ONLINE_CACHE_VERSION = 1;

/** Types de fichiers montrés dans le navigateur ; les autres sont proposés au téléchargement. */
const ONLINE_IMAGE_TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'bmp' => 'image/bmp'];
const ONLINE_DOCUMENT_TYPES = ['pdf' => 'application/pdf'];

/**
 * État d'une structure : ['rows' => [table => [SyncId => ligne]], 'synced_at' => horodatage du dernier fichier reçu ou null,
 * 'too_new' => vrai si un fichier vient d'une version plus récente du logiciel]. Chaque ligne garde ses colonnes, plus
 * « _id » (SyncId) et « _m » (date de modification).
 */
function online_state(int $organisationId): array
{
    $root = sync_root($organisationId);
    $snapshots = array_values(array_filter(sync_list($organisationId, 'instantanes'), fn($n) => str_ends_with($n, '.json.gz')));
    $journals = array_values(array_filter(sync_list($organisationId, 'journaux'), fn($n) => str_ends_with($n, '.json')));
    sort($snapshots, SORT_STRING);
    sort($journals, SORT_STRING);
    $latest = $snapshots ? end($snapshots) : null;
    $signature = hash('sha256', ONLINE_CACHE_VERSION . '|' . $latest . '|' . implode(',', $journals));

    $cacheFile = STORAGE_DIR . "/cache/etat-$organisationId.json";
    $cached = is_file($cacheFile) ? json_decode((string)file_get_contents($cacheFile), true) : null;
    if (is_array($cached) && ($cached['signature'] ?? '') === $signature) {
        return $cached['state'];
    }

    $rows = [];
    $cursors = [];
    $tooNew = false;
    $syncedAt = null;
    if ($latest !== null) {
        $file = online_read_sync_file("$root/instantanes/$latest", true);
        if ($file === false) {
            $tooNew = true;
        } elseif ($file !== null) {
            online_apply($rows, $file['Changes'] ?? []);
            $cursors = is_array($file['Cursors'] ?? null) ? $file['Cursors'] : [];
        }
        $syncedAt = @filemtime("$root/instantanes/$latest") ?: null;
    }
    foreach ($journals as $name) {
        $device = online_device_of($name);
        if ($device === null || strcmp($name, (string)($cursors[$device] ?? '')) <= 0) {
            continue; // déjà compris dans l'état complet
        }
        $file = online_read_sync_file("$root/journaux/$name", false);
        if ($file === false) {
            $tooNew = true;
            continue;
        }
        if ($file !== null) {
            online_apply($rows, $file['Changes'] ?? []);
        }
        $syncedAt = max($syncedAt ?? 0, @filemtime("$root/journaux/$name") ?: 0) ?: null;
    }
    $state = ['rows' => $rows, 'synced_at' => $syncedAt, 'too_new' => $tooNew];

    $dir = dirname($cacheFile);
    if (is_dir($dir) || @mkdir($dir, 0750, true)) {
        $temp = $cacheFile . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($temp, json_encode(['signature' => $signature, 'state' => $state], JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($temp, $cacheFile) || @unlink($temp);
        }
    }
    return $state;
}

/** Contenu décodé d'un journal ou d'un état complet ; null s'il est illisible, false s'il vient d'une version plus récente. */
function online_read_sync_file(string $path, bool $gzip): array|false|null
{
    $content = @file_get_contents($path);
    if ($content === false) {
        return null;
    }
    if ($gzip) {
        $content = @gzdecode($content);
        if ($content === false) {
            return null;
        }
    }
    $file = json_decode($content, true);
    if (!is_array($file) || !is_array($file['Changes'] ?? null)) {
        return null;
    }
    return (int)($file['Format'] ?? 0) > ONLINE_SYNC_FORMAT ? false : $file;
}

/** Appareil d'un nom de fichier « <horodatage>_<appareil>.json ». */
function online_device_of(string $name): ?string
{
    $stem = explode('.', $name)[0];
    $index = strpos($stem, '_');
    return $index > 0 ? substr($stem, $index + 1) : null;
}

/** Applique des changements : d'abord les ajouts et modifications, puis les suppressions (comme le logiciel). */
function online_apply(array &$rows, array $changes): void
{
    foreach ([false, true] as $deletions) {
        foreach ($changes as $change) {
            if (!is_array($change) || (bool)($change['Deleted'] ?? false) !== $deletions) {
                continue;
            }
            $table = (string)($change['Table'] ?? '');
            $id = (string)($change['Id'] ?? '');
            $modified = (string)($change['Modified'] ?? '');
            if ($table === '' || $id === '') {
                continue;
            }
            $current = $rows[$table][$id] ?? null;
            if ($current !== null && strcmp($current['_m'], $modified) > 0) {
                continue; // la version gardée est plus récente
            }
            if ($deletions) {
                unset($rows[$table][$id]);
            } elseif (is_array($change['Data'] ?? null)) {
                $rows[$table][$id] = $change['Data'] + ['_id' => $id, '_m' => $modified];
            }
        }
    }
}

// ===== Droits (comme SpaceAccess du logiciel) =====

function online_role_list(string $roles): array
{
    return array_values(array_filter(array_map('trim', explode(',', $roles))));
}

/** Dépenses, frais et adoptions : responsable, trésorier, consultation. */
function online_can_see_finance(string $roles): bool
{
    return (bool)array_intersect(online_role_list($roles), ['responsable', 'tresorier', 'consultation']);
}

/** Famille d'accueil seulement : elle ne voit que les animaux qui lui sont confiés. */
function online_is_foster_only(string $roles): bool
{
    $list = online_role_list($roles);
    return $list !== [] && array_diff($list, ['accueil']) === [];
}

/** Placement en cours : sans date de fin, ou fin aujourd'hui ou plus tard. */
function online_is_current(array $placement): bool
{
    $end = (string)($placement['EndDate'] ?? '');
    return $end === '' || substr($end, 0, 10) >= date('Y-m-d');
}

/** Lignes d'une table liées à un animal (colonne AnimalId = SyncId de l'animal). */
function online_rows_of(array $state, string $table, string $animalId): array
{
    return array_values(array_filter($state['rows'][$table] ?? [], fn($row) => ($row['AnimalId'] ?? null) === $animalId));
}

/**
 * Animaux que la personne peut voir. Famille d'accueil seulement : placement en cours de type « Famille d’accueil »
 * chez un contact qui a l'adresse e-mail de son compte.
 */
function online_visible_animals(array $state, string $roles, string $email): array
{
    $animals = $state['rows']['Animals'] ?? [];
    if (!online_is_foster_only($roles)) {
        return $animals;
    }
    $email = mb_strtolower(trim($email));
    $mine = [];
    foreach ($state['rows']['Contacts'] ?? [] as $id => $contact) {
        if ($email !== '' && mb_strtolower(trim((string)($contact['Email'] ?? ''))) === $email) {
            $mine[$id] = true;
        }
    }
    $visible = [];
    foreach ($state['rows']['Placements'] ?? [] as $placement) {
        $animalId = (string)($placement['AnimalId'] ?? '');
        if (isset($animals[$animalId], $mine[(string)($placement['ContactId'] ?? '')])
            && ($placement['Kind'] ?? '') === 'Famille d’accueil' && online_is_current($placement)) {
            $visible[$animalId] = $animals[$animalId];
        }
    }
    return $visible;
}

/** Placement en cours le plus récent d'un animal (null s'il n'y en a pas). */
function online_current_placement(array $state, string $animalId): ?array
{
    $current = array_filter(online_rows_of($state, 'Placements', $animalId), 'online_is_current');
    usort($current, fn($a, $b) => strcmp((string)$b['StartDate'], (string)$a['StartDate']));
    return $current[0] ?? null;
}

/** Nom du fichier de synchronisation désigné par une colonne (« fichier:<sha256>.<ext> »), ou null. */
function online_file_name(mixed $value): ?string
{
    return is_string($value) && preg_match('/^fichier:([0-9a-f]{64}\.[a-z0-9]{1,10})$/D', $value, $match) ? $match[1] : null;
}

/** Fichiers (photos, documents, pièces jointes) des animaux visibles : seuls ceux-là peuvent être ouverts. */
function online_visible_files(array $state, array $animals): array
{
    $names = [];
    foreach ($animals as $id => $animal) {
        $names[] = online_file_name($animal['PhotoPath'] ?? null);
        foreach (['AnimalPhotos' => 'FilePath', 'AnimalDocuments' => 'FilePath', 'AnimalEvents' => 'AttachmentPath'] as $table => $column) {
            foreach (online_rows_of($state, $table, (string)$id) as $row) {
                $names[] = online_file_name($row[$column] ?? null);
            }
        }
    }
    return array_fill_keys(array_filter($names), true);
}

// ===== Affichage =====

/** Âge à partir d'une date de naissance « AAAA-MM-JJ » (« 3 ans », « 5 mois », « 12 jours »). */
function online_age(?string $birthDate): string
{
    if (!$birthDate || ($birth = date_create(substr($birthDate, 0, 10))) === false) {
        return '';
    }
    $diff = $birth->diff(date_create('today'));
    if ($diff->invert) {
        return '';
    }
    if ($diff->y >= 2) {
        return "$diff->y ans";
    }
    $months = $diff->y * 12 + $diff->m;
    if ($months >= 1) {
        return "$months mois";
    }
    return $diff->d <= 1 ? "$diff->d jour" : "$diff->d jours";
}

/** Nombre décimal à la française, sans zéros inutiles. */
function online_number(mixed $value, int $decimals = 2): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return rtrim(rtrim(number_format((float)$value, $decimals, ',', ' '), '0'), ',');
}

/** Montant en euros. */
function online_money(mixed $value): string
{
    return number_format((float)$value, 2, ',', ' ') . ' €';
}

/** Adresse d'un fichier de la structure pour l'interface en ligne. */
function online_file_url(int $organisationId, string $name): string
{
    return "/application/fichier?o=$organisationId&n=" . rawurlencode($name);
}
