<?php
declare(strict_types=1);

// ===== Interface en ligne : saisie (fiches, prises de traitement, accueil, contacts, stock, frais et entretiens, pontes) =====
// Chaque enregistrement est écrit comme un journal de l'appareil « site » (app/lib/online_write.php) : les appareils le
// reçoivent à leur prochaine synchronisation. Droits comme SpaceAccess du logiciel (online_can_write).

/** Listes des fiches liées que la personne peut choisir : animaux visibles, et contacts et stock sauf famille d'accueil seule. */
function online_refs(array $organisation, array $state, array $animals): array
{
    $names = function (array $rows): array {
        $list = array_map(fn($row) => (string)($row['Name'] ?? ''), $rows);
        uasort($list, fn($a, $b) => strcoll(mb_strtolower($a), mb_strtolower($b)));
        return $list;
    };
    $refs = ['Animals' => $names($animals)];
    if (!online_is_foster_only((string)$organisation['roles'])) {
        $refs['Contacts'] = $names($state['rows']['Contacts'] ?? []);
        $refs['StockItems'] = $names($state['rows']['StockItems'] ?? []);
    }
    return $refs;
}

/** Champs montrés : sans les listes que la personne ne voit pas ni les montants sans droit aux finances (gardés tels quels). */
function online_visible_fields(array $organisation, string $table, array $refs): array
{
    $finance = online_can_see_finance((string)$organisation['roles']);
    return array_filter(online_forms()[$table]['fields'], fn($field) => ($field[1] !== 'ref' || isset($refs[$field['ref']]))
        && (empty($field['finance']) || $finance));
}

/** Accord des messages (« Photo ajoutée », « Contact ajouté »). */
function online_feminine(string $table): string
{
    return in_array($table, ['AnimalPhotos', 'Adoptions', 'Placements', 'AnimalExpenses', 'EggCounts'], true) ? 'e' : '';
}

/** Page où revenir après un enregistrement. */
function online_return_url(int $o, string $table, ?string $animalId): string
{
    $form = online_forms()[$table];
    if ($table === 'Animals' || (!empty($form['animal']) && $animalId !== null)) {
        return "/application/animal?o=$o&a=" . rawurlencode((string)$animalId) . (empty($form['animal']) ? '' : '#' . strtolower($table));
    }
    return match ($table) {
        'Contacts' => "/application/contacts?o=$o",
        'StockItems' => "/application/stock?o=$o",
        'RecurringTasks' => "/application/taches?o=$o",
        'EggCounts' => "/application/pontes?o=$o",
        default => "/application/accueil?o=$o",
    };
}

/**
 * Fiche à saisir ou à modifier (paramètres o, t = table, id = fiche existante, a = animal). Renvoie le contexte, ou une
 * page introuvable si la personne n'a pas accès à cette fiche.
 */
function online_edit_context(array $user): array
{
    [$organisation, $state, $animals] = online_context($user);
    $table = input('t');
    $forms = online_forms();
    if (!isset($forms[$table])) {
        not_found();
    }
    $form = $forms[$table];
    $id = input('id') !== '' ? input('id') : null;
    $row = $id !== null ? ($state['rows'][$table][$id] ?? null) : null;
    if ($id !== null && $row === null) {
        not_found();
    }
    $animalId = $table === 'Animals' ? $id : ($row !== null ? (($row['AnimalId'] ?? null) !== null ? (string)$row['AnimalId'] : null)
        : (input('a') !== '' ? input('a') : null));
    $animal = $animalId !== null ? ($animals[$animalId] ?? null) : null;
    if (($animalId !== null && $animal === null && $table !== 'Animals') || ($table === 'Animals' && $id !== null && $animal === null)
        || (!empty($form['animal']) && $animal === null)) {
        not_found(); // animal inconnu ou non visible (famille d'accueil)
    }
    if (!online_can_write($organisation, $table) || ($row !== null && $table === 'RecurringTasks' && ($row['Kind'] ?? '') === 'Frais'
        && !online_can_see_finance((string)$organisation['roles']))) {
        http_response_code(403);
        view('error', ['message' => licence_is_read_only(organisation_licence((int)$organisation['id']))
            ? 'La licence de cette structure n’accepte plus de modifications.'
            : 'Vos rôles dans cette structure ne permettent pas cette modification.'], 'Modification impossible');
        exit;
    }
    return [$organisation, $state, $animals, $table, $form, $id, $row, $animalId, $animal];
}

function page_online_form(): void
{
    $user = require_login();
    [$organisation, $state, $animals, $table, $form, $id, $row, $animalId, $animal] = online_edit_context($user);
    $o = (int)$organisation['id'];
    $refs = online_refs($organisation, $state, $animals);
    if ($table === 'Animals' && $id !== null) {
        unset($refs['Animals'][$id]); // un animal n'est pas son propre parent
    }
    $fields = online_visible_fields($organisation, $table, $refs);
    if ($table === 'RecurringTasks' && !online_can_see_finance((string)$organisation['roles'])) {
        $fields['Kind']['options'] = ['Entretien'];
        $fields['Kind']['default'] = 'Entretien';
    }
    $values = [];
    foreach ($fields as $column => $field) {
        $values[$column] = $row !== null ? ($row[$column] ?? null) : online_default_value($field);
    }
    if ($row === null && $animalId !== null && isset($fields['AnimalId'])) {
        $values['AnimalId'] = $animalId; // frais, entretien ou ponte ouverts depuis la fiche d'un animal
    }
    if ($row === null && $table === 'AnimalEvents' && in_array(input('type'), online_event_types($animal), true)) {
        $values['Type'] = input('type'); // « Noter une pesée »
    }
    if ($row === null && $animal !== null && $table === 'FoodLogs') {
        $values['Brand'] = (string)($animal['DietBrand'] ?? '');
        $values['Product'] = (string)($animal['DietName'] ?? '');
        $values['FoodType'] = ($animal['DietType'] ?? '') !== 'Non renseigné' ? (string)($animal['DietType'] ?? '') : '';
    }
    $errors = [];
    if (is_post()) {
        csrf_check();
        [$posted, $errors] = online_form_values($o, $fields, $row, $animal, $refs);
        if ($table === 'RecurringTasks' && ($posted['Kind'] ?? '') === 'Frais' && ($posted['AnimalId'] ?? null) === null) {
            $errors[] = 'Animal : à choisir pour des frais (la dépense lui est rattachée).';
        }
        if (isset($posted['StartDate'], $posted['EndDate']) && $posted['EndDate'] !== null && $posted['EndDate'] < $posted['StartDate']) {
            $errors[] = 'La date de fin est avant la date de début.';
        }
        if (!$errors) {
            if (!empty($form['animal'])) {
                $posted['AnimalId'] = $animalId;
            }
            $savedId = online_save($o, $state, $table, $id, $posted);
            audit('application.' . ($id === null ? 'ajout' : 'modification'), "Structure n°$o — $table $savedId");
            flash($form['title'] . ($id === null ? ' ajouté' : ' enregistré') . online_feminine($table)
                . '. Les appareils le recevront à leur prochaine synchronisation.');
            redirect(online_return_url($o, $table, $table === 'Animals' ? $savedId : ($animalId ?? null)));
        }
        $values = array_merge($values, array_filter($posted, fn($v, $c) => $fields[$c][1] !== 'file', ARRAY_FILTER_USE_BOTH));
    }
    $canDelete = $id !== null && ($table !== 'Animals' || online_can_manage((string)$organisation['roles']));
    view('online/form', [
        'organisation' => $organisation, 'state' => $state, 'table' => $table, 'form' => $form, 'fields' => $fields, 'values' => $values,
        'refs' => $refs, 'id' => $id, 'row' => $row, 'animal' => $animal, 'animalId' => $animalId, 'errors' => $errors, 'canDelete' => $canDelete,
        'back' => online_return_url($o, $table, $animalId),
    ], ($id === null ? 'Ajouter : ' : 'Modifier : ') . $form['title']);
}

/** Suppression d'une fiche (et de ce qui en dépend, comme le logiciel). Un animal : responsable seulement. */
function page_online_delete(): void
{
    $user = require_login();
    if (!is_post()) {
        not_found();
    }
    csrf_check();
    [$organisation, $state, $animals, $table, $form, $id, $row, $animalId] = online_edit_context($user);
    if ($id === null || ($table === 'Animals' && !online_can_manage((string)$organisation['roles']))) {
        not_found();
    }
    $o = (int)$organisation['id'];
    online_delete($o, $state, $table, $id);
    audit('application.suppression', "Structure n°$o — $table $id");
    flash($form['title'] . ' supprimé' . online_feminine($table) . '.');
    redirect($table === 'Animals' ? "/application/animaux?o=$o" : online_return_url($o, $table, $animalId));
}

/** Prise d'un traitement : « Donné » ou « Oublié » (décompte du stock, prochaine prise). */
function page_online_dose(): void
{
    $user = require_login();
    if (!is_post()) {
        not_found();
    }
    csrf_check();
    $_GET['t'] = 'Treatments';
    [$organisation, $state, , , , $id, $row, $animalId] = online_edit_context($user);
    $status = input('statut');
    if ($id === null || !in_array($status, ['Donné', 'Oublié'], true)) {
        not_found();
    }
    $o = (int)$organisation['id'];
    flash(online_record_administration($o, $state, $id, $status));
    redirect(input('retour') === 'accueil' ? "/application/accueil?o=$o" : "/application/animal?o=$o&a=" . rawurlencode((string)$animalId) . '#treatments');
}

/** Accueil d'une structure : échéances, rendez-vous, suivis demandés, stock à racheter. */
function page_online_home(): void
{
    $user = require_login();
    [$organisation, $state, $animals] = online_context($user);
    $finance = online_can_see_finance((string)$organisation['roles']);
    $days = in_array((int)input('jours', '30'), [7, 30, 90], true) ? (int)input('jours', '30') : 30;
    $items = online_dashboard_items($state, $animals, $finance, $days);
    $foster = online_is_foster_only((string)$organisation['roles']);
    view('online/home', [
        'organisation' => $organisation, 'state' => $state, 'animals' => $animals, 'items' => $items, 'days' => $days,
        'stockAlerts' => $foster ? [] : online_stock_alerts($state),
        'present' => count(array_filter($animals, fn($a) => ($a['Status'] ?? 'Présent') === 'Présent')),
        'canWrite' => online_can_write($organisation, 'Treatments'),
    ], $organisation['name']);
}

/** Refuse les pages de gestion de la structure à une famille d'accueil seule. */
function online_require_staff(array $organisation): void
{
    if (online_is_foster_only((string)$organisation['roles'])) {
        not_found();
    }
}

function page_online_contacts(): void
{
    $user = require_login();
    [$organisation, $state] = online_context($user);
    online_require_staff($organisation);
    $search = mb_strtolower(input('q'));
    $role = input('role');
    $contacts = array_filter($state['rows']['Contacts'] ?? [], fn($c) => ($role === '' || ($c['Role'] ?? '') === $role)
        && ($search === '' || str_contains(mb_strtolower(implode(' ', [$c['Name'] ?? '', $c['Phone'] ?? '', $c['Email'] ?? '', $c['Address'] ?? ''])), $search)));
    uasort($contacts, fn($a, $b) => strcoll(mb_strtolower((string)($a['Name'] ?? '')), mb_strtolower((string)($b['Name'] ?? ''))));
    view('online/contacts', [
        'organisation' => $organisation, 'state' => $state, 'contacts' => $contacts, 'search' => input('q'), 'role' => $role,
        'roles' => online_forms()['Contacts']['fields']['Role']['options'], 'canWrite' => online_can_write($organisation, 'Contacts'),
    ], $organisation['name']);
}

function page_online_stock(): void
{
    $user = require_login();
    [$organisation, $state] = online_context($user);
    online_require_staff($organisation);
    $o = (int)$organisation['id'];
    $canWrite = online_can_write($organisation, 'StockItems');
    if (is_post()) {
        csrf_check();
        $id = input('id');
        $item = $state['rows']['StockItems'][$id] ?? null;
        $delta = str_replace([',', ' '], ['.', ''], input('quantite'));
        if (!$canWrite || $item === null) {
            not_found();
        }
        if (!is_numeric($delta) || (float)$delta === 0.0) {
            flash('Quantité à ajouter ou à retirer : un nombre (négatif pour retirer).', 'error');
        } else {
            $quantity = max(0, (float)($item['Quantity'] ?? 0) + (float)$delta);
            online_save($o, $state, 'StockItems', $id, ['Quantity' => $quantity]);
            flash(($item['Name'] ?? '') . ' : ' . online_number($quantity) . ' ' . ($item['Unit'] ?? '') . '.');
        }
        redirect("/application/stock?o=$o" . (input('categorie') !== '' ? '&categorie=' . rawurlencode(input('categorie')) : ''));
    }
    $category = input('categorie');
    $search = mb_strtolower(input('q'));
    $items = array_filter($state['rows']['StockItems'] ?? [], fn($i) => ($category === '' || ($i['Category'] ?? '') === $category)
        && ($search === '' || str_contains(mb_strtolower(($i['Name'] ?? '') . ' ' . ($i['Notes'] ?? '') . ' ' . ($i['Gtin'] ?? '')), $search)));
    uasort($items, fn($a, $b) => strcoll(mb_strtolower((string)($a['Name'] ?? '')), mb_strtolower((string)($b['Name'] ?? ''))));
    view('online/stock', [
        'organisation' => $organisation, 'state' => $state, 'items' => $items, 'alerts' => online_stock_alerts($state), 'category' => $category,
        'search' => input('q'), 'categories' => online_forms()['StockItems']['fields']['Category']['options'], 'canWrite' => $canWrite,
    ], $organisation['name']);
}

/** Frais et entretiens réguliers : liste, « Fait » (ou « Payé »), « Passer ». */
function page_online_tasks(): void
{
    $user = require_login();
    [$organisation, $state, $animals] = online_context($user);
    online_require_staff($organisation);
    $o = (int)$organisation['id'];
    $finance = online_can_see_finance((string)$organisation['roles']);
    $canWrite = online_can_write($organisation, 'RecurringTasks');
    if (is_post()) {
        csrf_check();
        $task = $state['rows']['RecurringTasks'][input('id')] ?? null;
        if (!$canWrite || $task === null || (($task['Kind'] ?? '') === 'Frais' && !$finance) || !in_array(input('action'), ['fait', 'passer'], true)) {
            not_found();
        }
        $amount = str_replace([',', ' '], ['.', ''], input('montant'));
        online_complete_task($o, $state, input('id'), input('action') === 'passer', is_numeric($amount) ? (float)$amount : null);
        flash(input('action') === 'passer' ? 'Échéance passée.' : (($task['Kind'] ?? '') === 'Frais' && ($task['AnimalId'] ?? null) !== null
            ? 'Frais payés : la dépense est ajoutée à la fiche de l’animal.' : 'Entretien noté comme fait.'));
        redirect(input('retour') === 'accueil' ? "/application/accueil?o=$o" : "/application/taches?o=$o");
    }
    $tasks = array_filter($state['rows']['RecurringTasks'] ?? [], fn($t) => $finance || ($t['Kind'] ?? '') !== 'Frais');
    uasort($tasks, fn($a, $b) => [empty($a['Active']), (string)($a['NextDueDate'] ?? '')] <=> [empty($b['Active']), (string)($b['NextDueDate'] ?? '')]);
    view('online/tasks', [
        'organisation' => $organisation, 'state' => $state, 'tasks' => $tasks, 'animals' => $animals, 'finance' => $finance, 'canWrite' => $canWrite,
        'contacts' => $state['rows']['Contacts'] ?? [],
    ], $organisation['name']);
}

/** Pontes : œufs ramassés par jour (poulailler ou poule), sur une période. */
function page_online_eggs(): void
{
    $user = require_login();
    [$organisation, $state, $animals] = online_context($user);
    online_require_staff($organisation);
    $days = in_array((int)input('jours', '30'), [7, 30, 90, 365], true) ? (int)input('jours', '30') : 30;
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $counts = array_filter($state['rows']['EggCounts'] ?? [], fn($c) => substr((string)($c['CountDate'] ?? ''), 0, 10) >= $from);
    uasort($counts, fn($a, $b) => strcmp((string)($b['CountDate'] ?? ''), (string)($a['CountDate'] ?? '')));
    $byCoop = [];
    foreach ($counts as $count) {
        $coop = ($count['CoopName'] ?? '') !== '' ? (string)$count['CoopName'] : (string)($animals[(string)($count['AnimalId'] ?? '')]['CoopName'] ?? '');
        $byCoop[$coop === '' ? 'Sans poulailler' : $coop] = ($byCoop[$coop === '' ? 'Sans poulailler' : $coop] ?? 0) + (int)($count['Eggs'] ?? 0);
    }
    view('online/eggs', [
        'organisation' => $organisation, 'state' => $state, 'animals' => $animals, 'counts' => $counts, 'days' => $days, 'byCoop' => $byCoop,
        'total' => array_sum(array_map(fn($c) => (int)($c['Eggs'] ?? 0), $counts)), 'canWrite' => online_can_write($organisation, 'EggCounts'),
    ], $organisation['name']);
}

/** Onglets de l'interface en ligne d'une structure (selon les rôles). */
function online_tabs(array $organisation, array $state): array
{
    $o = (int)$organisation['id'];
    $tabs = ['accueil' => ['Accueil', "/application/accueil?o=$o"], 'animaux' => ['Animaux', "/application/animaux?o=$o"]];
    if (!online_is_foster_only((string)$organisation['roles'])) {
        $tabs['contacts'] = ['Contacts', "/application/contacts?o=$o"];
        $tabs['stock'] = ['Stock', "/application/stock?o=$o"];
        $tabs['taches'] = ['Frais et entretiens', "/application/taches?o=$o"];
        $poultry = ($state['rows']['EggCounts'] ?? []) !== [] || array_filter($state['rows']['Animals'] ?? [],
            fn($a) => online_species_group((string)($a['Species'] ?? '')) === 'Volailles');
        if ($poultry) {
            $tabs['pontes'] = ['Pontes', "/application/pontes?o=$o"];
        }
    }
    return $tabs;
}
