<?php
declare(strict_types=1);

// ===== Interface en ligne : saisie. Le site écrit comme un appareil de plus (« site ») : chaque enregistrement devient un
// journal journaux/<horodatage .NET>_site.json au format du moteur du logiciel (SyncEngine) ; le logiciel et l'application
// le reçoivent à leur prochaine synchronisation, le site le relit aussitôt (état recalculé). =====

const ONLINE_DEVICE_ID = 'site';
const ONLINE_PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const ONLINE_FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'csv', 'heic'];

/** Peut modifier (tout le monde sauf la consultation seule), comme SpaceAccess.CanEdit. */
function online_can_edit(string $roles): bool
{
    return (bool)array_intersect(online_role_list($roles), ['responsable', 'soins', 'accueil', 'tresorier']);
}

function online_can_manage(string $roles): bool
{
    return in_array('responsable', online_role_list($roles), true);
}

/**
 * Peut enregistrer dans cette table : modification permise, licence qui accepte les envois ; famille d'accueil seule :
 * seulement les fiches liées à ses animaux (pas de contacts, de stock ni de parcours) ; finances : rôles qui les voient.
 */
function online_can_write(array $organisation, string $table): bool
{
    $roles = (string)$organisation['roles'];
    $form = online_forms()[$table] ?? null;
    if (!$form || !online_can_edit($roles) || licence_is_read_only(organisation_licence((int)$organisation['id']))) {
        return false;
    }
    if (!empty($form['finance']) && !online_can_see_finance($roles)) {
        return false;
    }
    if (online_is_foster_only($roles)) {
        return in_array($table, ['AnimalEvents', 'Treatments', 'AnimalDocuments', 'AnimalPhotos', 'FoodLogs'], true);
    }
    return true;
}

/** Horodatage UTC trié comme celui du logiciel (« 2026-10-31T16:11:29.123Z »). */
function online_modified_utc(): string
{
    $now = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
    return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
}

/** Nouvel identifiant global (32 caractères hexadécimaux, comme lower(hex(randomblob(16))) du logiciel). */
function online_new_id(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Écrit un journal de changements ([table, id, données ou null pour une suppression]) dans le stockage de la structure.
 * Le nom (ticks .NET) est toujours plus grand que le précédent du site, même pour deux enregistrements dans la même
 * microseconde (verrou).
 */
function online_write_changes(int $organisationId, array $changes): void
{
    $dir = sync_root($organisationId) . '/journaux';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException("Dossier impossible à créer : $dir");
    }
    $lock = fopen(sync_root($organisationId) . '/.site.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        [$micro, $seconds] = explode(' ', microtime());
        $ticks = (int)$seconds * 10_000_000 + (int)round((float)$micro * 10_000_000) + 621_355_968_000_000_000;
        $last = array_filter(sync_list($organisationId, 'journaux'), fn($n) => str_ends_with($n, '_' . ONLINE_DEVICE_ID . '.json'));
        if ($last) {
            $ticks = max($ticks, (int)explode('_', max($last))[0] + 1);
        }
        $modified = online_modified_utc();
        $journal = [
            'Format' => ONLINE_SYNC_FORMAT, 'Device' => ONLINE_DEVICE_ID, 'DeviceName' => 'Site suivi-des-animaux.fr',
            'CreatedUtc' => $modified,
            'Changes' => array_map(fn(array $c) => ['Table' => $c[0], 'Id' => $c[1], 'Deleted' => $c[2] === null, 'Modified' => $modified, 'Data' => $c[2]], $changes),
            'Cursors' => null,
        ];
        $name = sprintf('%019d_%s.json', $ticks, ONLINE_DEVICE_ID);
        $temp = "$dir/.ecriture-" . bin2hex(random_bytes(8));
        if (file_put_contents($temp, json_encode($journal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)) === false
            || !rename($temp, "$dir/$name")) {
            @unlink($temp);
            throw new RuntimeException("Écriture impossible : $dir/$name");
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** Ligne complète à envoyer : l'existante (sans les clés internes) complétée des nouvelles valeurs. */
function online_row_data(?array $existing, array $values): array
{
    $data = $existing ?? [];
    unset($data['_id'], $data['_m']);
    return array_merge($data, $values);
}

/**
 * Enregistre une ligne (nouvelle si $id est null) : dates de création et de mise à jour comme le logiciel. Renvoie son
 * identifiant. $extra : autres changements à écrire dans le même journal.
 */
function online_save(int $organisationId, array $state, string $table, ?string $id, array $values, array $extra = []): string
{
    $existing = $id !== null ? ($state['rows'][$table][$id] ?? null) : null;
    $id ??= online_new_id();
    if ($existing === null && isset(ONLINE_CREATED_COLUMN[$table])) {
        $values[ONLINE_CREATED_COLUMN[$table]] = now();
    }
    if (isset(ONLINE_UPDATED_COLUMN[$table])) {
        $values[ONLINE_UPDATED_COLUMN[$table]] = now();
    }
    online_write_changes($organisationId, [[$table, $id, online_row_data($existing, $values)], ...$extra]);
    return $id;
}

/**
 * Liens entre tables (clés étrangères du logiciel) : table supprimée => [[table liée, colonne, supprimer (CASCADE) ou
 * vider (SET NULL)]]. La suppression écrit aussi ces changements pour que le site et les appareils restent d'accord.
 */
const ONLINE_LINKS = [
    'Animals' => [['AnimalEvents', 'AnimalId', true], ['Treatments', 'AnimalId', true], ['MedicationAdministrations', 'AnimalId', true],
        ['AnimalDocuments', 'AnimalId', true], ['AnimalExpenses', 'AnimalId', true], ['Placements', 'AnimalId', true], ['Adoptions', 'AnimalId', true],
        ['AnimalPhotos', 'AnimalId', true], ['RecurringTasks', 'AnimalId', true], ['FoodLogs', 'AnimalId', true], ['FollowUps', 'AnimalId', true],
        ['EggCounts', 'AnimalId', false], ['Animals', 'MotherId', false], ['Animals', 'FatherId', false]],
    'Contacts' => [['Placements', 'ContactId', false], ['Adoptions', 'ContactId', false], ['RecurringTasks', 'ContactId', false],
        ['VetPrices', 'ContactId', true], ['Animals', 'VeterinarianContactId', false], ['Animals', 'BoardingContactId', false]],
    'StockItems' => [['Treatments', 'StockItemId', false], ['StockItemPathologies', 'StockItemId', true]],
    'Treatments' => [['MedicationAdministrations', 'TreatmentId', true]],
];

/** Changements d'une suppression, avec ses suites (lignes liées supprimées ou détachées). */
function online_delete_changes(array $state, string $table, string $id, array &$seen = []): array
{
    if (isset($seen["$table/$id"])) {
        return [];
    }
    $seen["$table/$id"] = true;
    $changes = [[$table, $id, null]];
    foreach (ONLINE_LINKS[$table] ?? [] as [$linked, $column, $cascade]) {
        foreach ($state['rows'][$linked] ?? [] as $rowId => $row) {
            if (($row[$column] ?? null) !== $id || isset($seen["$linked/$rowId"])) {
                continue;
            }
            if ($cascade) {
                array_push($changes, ...online_delete_changes($state, $linked, (string)$rowId, $seen));
            } else {
                $changes[] = [$linked, (string)$rowId, online_row_data($row, [$column => null])];
            }
        }
    }
    return $changes;
}

function online_delete(int $organisationId, array $state, string $table, string $id): void
{
    online_write_changes($organisationId, online_delete_changes($state, $table, $id));
}

/**
 * Fichier envoyé par un formulaire → « fichier:<sha256>.<ext> » rangé dans fichiers/ (comme le logiciel). Null si aucun
 * fichier ; exception (message pour la personne) si le fichier est refusé.
 */
function online_store_upload(int $organisationId, array $upload, bool $photo): ?string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
        throw new InvalidArgumentException('Le fichier n’a pas pu être reçu (trop volumineux ?).');
    }
    $max = (int)config('sync.max_bytes', SYNC_MAX_BYTES_DEFAULT);
    if ($upload['size'] > $max) {
        throw new InvalidArgumentException('Fichier trop volumineux (' . format_bytes($max) . ' au plus).');
    }
    $extension = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));
    $allowed = $photo ? ONLINE_PHOTO_EXTENSIONS : ONLINE_FILE_EXTENSIONS;
    if (!in_array($extension, $allowed, true)) {
        throw new InvalidArgumentException('Type de fichier non accepté (' . implode(', ', $allowed) . ').');
    }
    if ($photo && @getimagesize($upload['tmp_name']) === false) {
        throw new InvalidArgumentException('Ce fichier n’est pas une image.');
    }
    $name = hash_file('sha256', $upload['tmp_name']) . ".$extension";
    $dir = sync_root($organisationId) . '/fichiers';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException("Dossier impossible à créer : $dir");
    }
    if (!is_file("$dir/$name") && !move_uploaded_file($upload['tmp_name'], "$dir/$name")) {
        throw new RuntimeException("Écriture impossible : $dir/$name");
    }
    return "fichier:$name";
}

/**
 * Valeurs d'un formulaire envoyé, contrôlées et converties au format des colonnes. Renvoie [valeurs, erreurs].
 * $refs : identifiants permis pour chaque table liée.
 */
function online_form_values(int $organisationId, array $fields, ?array $existing, ?array $animal, array $refs): array
{
    $values = [];
    $errors = [];
    foreach ($fields as $column => $field) {
        [$label, $type] = $field;
        $raw = $_POST[$column] ?? '';
        $raw = is_string($raw) ? trim($raw) : '';
        $value = null;
        switch ($type) {
            case 'bool':
                $value = empty($_POST[$column]) ? 0 : 1;
                break;
            case 'tristate':
                $value = match ($raw) { 'Oui' => 1, 'Non' => 0, default => null };
                break;
            case 'int':
            case 'number':
                $number = str_replace([',', ' ', ' '], ['.', '', ''], $raw);
                if ($number !== '' && !is_numeric($number)) {
                    $errors[] = "$label : nombre attendu.";
                }
                $value = $number === '' ? null : ($type === 'int' ? (int)$number : (float)$number);
                if ($value === null && array_key_exists('default', $field) && is_numeric($field['default'])) {
                    $value = $field['default']; // colonne obligatoire avec une valeur par défaut
                }
                break;
            case 'date':
                if ($raw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                    $errors[] = "$label : date invalide.";
                }
                $value = $raw === '' ? null : $raw;
                break;
            case 'datetime':
                if ($raw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $raw)) {
                    $errors[] = "$label : date et heure invalides.";
                }
                $value = $raw === '' ? null : date('Y-m-d H:i:s', strtotime($raw));
                break;
            case 'select':
                $options = isset($field['choices']) ? ($field['choices'])($animal, $existing[$column] ?? null) : $field['options'];
                // Une valeur déjà enregistrée par le logiciel reste permise, même si elle n'est pas dans la liste du site.
                if ($raw !== '' && !in_array($raw, $options, true) && $raw !== (string)($existing[$column] ?? '')) {
                    $errors[] = "$label : choix invalide.";
                }
                $value = $raw;
                break;
            case 'ref':
                if ($raw !== '' && !isset($refs[$field['ref']][$raw])) {
                    $errors[] = "$label : choix invalide.";
                }
                $value = $raw === '' ? null : $raw;
                break;
            case 'file':
                try {
                    $value = online_store_upload($organisationId, $_FILES[$column] ?? [], ($field['accept'] ?? '') === 'image/*');
                } catch (InvalidArgumentException $exception) {
                    $errors[] = "$label : " . $exception->getMessage();
                }
                if ($value === null) {
                    if (!empty($_POST[$column . '__retirer'])) {
                        $value = '';
                    } elseif ($existing !== null || empty($field['required'])) {
                        continue 2; // fichier inchangé
                    }
                }
                break;
            default:
                $value = str_replace("\r\n", "\n", $raw);
        }
        if (!empty($field['required']) && ($value === null || $value === '')) {
            $errors[] = "$label : à renseigner.";
        }
        $values[$column] = $value ?? ($type === 'text' || $type === 'textarea' ? '' : null);
    }
    return [$values, array_values(array_unique($errors))];
}

/** Valeur par défaut d'un champ pour un nouveau formulaire. */
function online_default_value(array $field): mixed
{
    $default = $field['default'] ?? null;
    return match ($default) {
        'today' => date('Y-m-d'),
        'now' => date('Y-m-d H:i:s'),
        '+1 month' => date('Y-m-d', strtotime('+1 month')),
        default => $default,
    };
}

/**
 * Prochaine date selon une fréquence (TreatmentService.Advance, RecurringTask.NextAfter). Mois et années comme AddMonths
 * de .NET : le 31 janvier plus un mois donne le 28 (ou 29) février, pas le 3 mars.
 */
function online_advance(string $date, int $value, string $unit): string
{
    $value = max(1, $value);
    $format = strlen($date) > 10 ? 'Y-m-d H:i:s' : 'Y-m-d';
    $start = new DateTimeImmutable($date);
    if ($unit === 'mois' || $unit === 'an(s)') {
        $months = $unit === 'mois' ? $value : $value * 12;
        $first = $start->modify('first day of this month')->modify("+$months months");
        $day = min((int)$start->format('j'), (int)$first->format('t'));
        return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), $day)->format($format);
    }
    $modifier = match ($unit) { 'heure(s)' => "+$value hours", 'semaine(s)' => '+' . ($value * 7) . ' days', default => "+$value days" };
    return $start->modify($modifier)->format($format);
}
