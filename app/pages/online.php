<?php
declare(strict_types=1);

// ===== Interface en ligne : consultation des données synchronisées des structures (lecture seule pour le moment) =====

/** Structures du compte, avec le nombre d'animaux visibles et la date des dernières données reçues. */
function page_online_app(): void
{
    $user = require_login();
    $organisations = [];
    foreach (user_organisations((int)$user['id']) as $organisation) {
        $state = online_state((int)$organisation['id']);
        $animals = online_visible_animals($state, (string)$organisation['roles'], (string)$user['email']);
        $organisation['animal_count'] = count($animals);
        $organisation['present_count'] = count(array_filter($animals, fn($a) => ($a['Status'] ?? 'Présent') === 'Présent'));
        $organisation['synced_at'] = $state['synced_at'];
        $organisation['notice'] = licence_notice(organisation_licence((int)$organisation['id']));
        $organisations[] = $organisation;
    }
    view('online/index', ['organisations' => $organisations], 'Interface en ligne');
}

/** Structure de la page (paramètre o) dont la personne est membre, sinon 404 ; et son état. */
function online_context(array $user): array
{
    $organisation = organisation_for_member((int)input('o'), (int)$user['id']);
    if (!$organisation) {
        not_found();
    }
    $state = online_state((int)$organisation['id']);
    $animals = online_visible_animals($state, (string)$organisation['roles'], (string)$user['email']);
    $organisation['notice'] = licence_notice(organisation_licence((int)$organisation['id']));
    return [$organisation, $state, $animals];
}

function page_online_animals(): void
{
    $user = require_login();
    [$organisation, $state, $animals] = online_context($user);
    $search = mb_strtolower(input('q'));
    $status = input('statut', 'Présent');
    $species = input('espece');
    $speciesList = array_values(array_unique(array_map(fn($a) => (string)($a['Species'] ?? ''), $animals)));
    sort($speciesList);
    $list = array_filter($animals, function (array $animal) use ($search, $status, $species) {
        if ($status !== 'Tous' && ($animal['Status'] ?? 'Présent') !== $status) {
            return false;
        }
        if ($species !== '' && ($animal['Species'] ?? '') !== $species) {
            return false;
        }
        if ($search === '') {
            return true;
        }
        $text = mb_strtolower(implode(' ', [$animal['Name'] ?? '', $animal['ChipNumber'] ?? '', $animal['TattooNumber'] ?? '', $animal['Breed'] ?? '']));
        return str_contains($text, $search);
    });
    uasort($list, fn($a, $b) => strcoll(mb_strtolower((string)($a['Name'] ?? '')), mb_strtolower((string)($b['Name'] ?? ''))));
    $placements = [];
    foreach ($list as $id => $animal) {
        $placements[$id] = online_current_placement($state, (string)$id);
    }
    view('online/animals', [
        'organisation' => $organisation, 'state' => $state, 'animals' => $list, 'total' => count($animals), 'placements' => $placements,
        'search' => input('q'), 'status' => $status, 'species' => $species, 'speciesList' => $speciesList,
    ], $organisation['name']);
}

function page_online_animal(): void
{
    $user = require_login();
    [$organisation, $state, $animals] = online_context($user);
    $id = input('a');
    $animal = $animals[$id] ?? null;
    if ($animal === null) {
        not_found();
    }
    $byDateDesc = fn(string $column) => fn($a, $b) => strcmp((string)($b[$column] ?? ''), (string)($a[$column] ?? ''));
    $events = online_rows_of($state, 'AnimalEvents', $id);
    usort($events, $byDateDesc('EventDate'));
    $treatments = online_rows_of($state, 'Treatments', $id);
    usort($treatments, $byDateDesc('StartDate'));
    $placements = online_rows_of($state, 'Placements', $id);
    usort($placements, $byDateDesc('StartDate'));
    $documents = online_rows_of($state, 'AnimalDocuments', $id);
    usort($documents, $byDateDesc('DocumentDate'));
    $photos = array_values(array_filter(online_rows_of($state, 'AnimalPhotos', $id), fn($p) => online_file_name($p['FilePath'] ?? null)));
    usort($photos, $byDateDesc('PhotoDate'));
    $followUps = array_values(array_filter(online_rows_of($state, 'FollowUps', $id),
        fn($f) => substr((string)($f['EndDate'] ?? ''), 0, 10) >= date('Y-m-d')));
    $weights = array_values(array_filter($events, fn($e) => ($e['WeightKg'] ?? null) !== null));
    $finance = online_can_see_finance((string)$organisation['roles']);
    $expenses = $adoptions = [];
    if ($finance) {
        $expenses = online_rows_of($state, 'AnimalExpenses', $id);
        usort($expenses, $byDateDesc('ExpenseDate'));
        $adoptions = online_rows_of($state, 'Adoptions', $id);
        usort($adoptions, $byDateDesc('AdoptionDate'));
    }
    view('online/animal', [
        'organisation' => $organisation, 'state' => $state, 'animal' => $animal, 'animals' => $animals,
        'contacts' => $state['rows']['Contacts'] ?? [],
        'events' => $events, 'treatments' => $treatments, 'placements' => $placements, 'documents' => $documents,
        'photos' => $photos, 'followUps' => $followUps, 'weights' => $weights,
        'finance' => $finance, 'expenses' => $expenses, 'adoptions' => $adoptions,
    ], (string)($animal['Name'] ?? 'Animal'));
}

/** Photo ou document d'un animal visible (jamais un autre fichier du stockage). */
function page_online_file(): void
{
    $user = require_login();
    [$organisation, $state, $animals] = online_context($user);
    $name = input('n');
    if (preg_match('/^[0-9a-f]{64}\.([a-z0-9]{1,10})$/D', $name, $match) !== 1 || !isset(online_visible_files($state, $animals)[$name])) {
        not_found();
    }
    $path = sync_root((int)$organisation['id']) . "/fichiers/$name";
    if (!is_file($path)) {
        not_found();
    }
    $extension = $match[1];
    $type = ONLINE_IMAGE_TYPES[$extension] ?? ONLINE_DOCUMENT_TYPES[$extension] ?? 'application/octet-stream';
    header("Content-Type: $type");
    header('Content-Length: ' . filesize($path));
    // Nom tiré du contenu : le fichier ne change jamais, il peut rester dans le cache du navigateur.
    header('Cache-Control: private, max-age=604800, immutable');
    if ($type !== 'application/pdf') {
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    }
    $inline = isset(ONLINE_IMAGE_TYPES[$extension]) || isset(ONLINE_DOCUMENT_TYPES[$extension]);
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename=\"$name\"");
    readfile($path);
    exit;
}
