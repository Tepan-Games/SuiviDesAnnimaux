<?php
declare(strict_types=1);

// ===== Suivis des animaux confiés : pages de la structure, page de la personne (/suivi), API du logiciel =====

/** Jours restants avant la fin du suivi (null : sans date de fin ; 0 : terminé). */
function link_days_left(array $link): ?int
{
    if (!link_is_open($link)) {
        return 0;
    }
    if ($link['ends_at'] === null) {
        return null;
    }
    return max(0, (int)ceil((strtotime($link['ends_at']) - time()) / 86400));
}

/** « Suivi avec … : encore 45 jours » (fiche de l'animal chez la personne). */
function link_remaining_text(array $link): string
{
    $days = link_days_left($link);
    return match (true) {
        $days === 0 => link_status($link),
        $days === null => 'Suivi en cours, sans date de fin',
        $days === 1 => 'Suivi en cours : dernier jour',
        $days > 60 => 'Suivi en cours : encore ' . (int)round($days / 30.44) . ' mois (jusqu’au ' . format_date($link['ends_at']) . ')',
        default => "Suivi en cours : encore $days jours (jusqu’au " . format_date($link['ends_at']) . ')',
    };
}

// ----- Espace client de la structure -----

/** Structure de la page (paramètre o) vue par un membre qui a accès aux suivis. */
function links_organisation(array $user): array
{
    $organisation = organisation_for_member((int)input('o'), (int)$user['id']);
    if (!$organisation || !can_see_links($organisation['roles'])) {
        not_found();
    }
    return $organisation;
}

/** /compte/suivis?o=ID : liste des suivis et création d'un suivi (lien + QR code). */
function page_links(): void
{
    $user = require_login();
    $organisation = links_organisation($user);
    $id = (int)$organisation['id'];
    $kinds = organisation_link_kinds($organisation);
    $canWrite = can_follow_animals($organisation['roles']) && $kinds !== [];
    $created = null;
    $errors = [];
    $values = ['kind' => $kinds[0] ?? '', 'animal_name' => '', 'species' => '', 'recipient_name' => '', 'recipient_email' => '', 'months' => '3', 'requests' => ['nouvelles', 'photo']];
    if (is_post()) {
        csrf_check();
        if (!$canWrite) {
            http_response_code(403);
            exit($kinds === [] ? link_kind_refusal($organisation) : 'Vos rôles ne permettent pas de créer un suivi.');
        }
        $values = [
            'kind' => input('kind'), 'animal_name' => input('animal_name'), 'species' => input('species'),
            'recipient_name' => input('recipient_name'), 'recipient_email' => input('recipient_email'), 'months' => input('months'),
            'requests' => array_values(array_filter((array)($_POST['requests'] ?? []), 'is_string')),
        ];
        if (!in_array($values['kind'], $kinds, true)) {
            $errors[] = link_kind_refusal($organisation);
        }
        if (mb_strlen($values['animal_name']) < 1) {
            $errors[] = 'Indiquez le nom de l’animal.';
        }
        if ($values['recipient_email'] !== '' && !filter_var($values['recipient_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'L’adresse e-mail n’est pas valable (vous pouvez la laisser vide).';
        }
        if (!$errors) {
            [$linkId, $reference, $token] = create_animal_link($organisation, $user, $values + ['interval_days' => (int)input('interval_days')]);
            $created = ['id' => $linkId, 'reference' => $reference, 'url' => link_url($token), 'email' => $values['recipient_email'], 'animal' => $values['animal_name']];
            $values['animal_name'] = $values['recipient_name'] = $values['recipient_email'] = '';
        }
    }
    view('account/links', [
        'organisation' => $organisation, 'links' => organisation_links($id), 'canWrite' => $canWrite, 'kinds' => $kinds,
        'created' => $created, 'errors' => $errors, 'values' => $values,
    ], 'Suivis des animaux confiés');
}

/** /compte/suivi?o=ID&id=… : échanges d'un suivi, réponse, fin du suivi. */
function page_link(): void
{
    $user = require_login();
    $organisation = links_organisation($user);
    $link = find_organisation_link((int)$organisation['id'], (int)input('id'));
    if (!$link) {
        not_found();
    }
    $back = "/compte/suivi?o={$organisation['id']}&id={$link['id']}";
    if (is_post()) {
        csrf_check();
        if (!can_follow_animals($organisation['roles'])) {
            http_response_code(403);
            exit('Vos rôles ne permettent pas de répondre.');
        }
        if (input('action') === 'fermer') {
            close_animal_link($link, (int)$user['id']);
            flash('Suivi terminé : la personne garde l’historique, mais ne peut plus rien envoyer.');
        } else {
            $error = add_organisation_message($link, $organisation, $user, input('body'));
            $error ? flash($error, 'error') : flash('Message envoyé.');
        }
        redirect($back);
    }
    $messages = link_messages((int)$link['id']);
    mark_link_read((int)$link['id']);
    view('account/link', [
        'organisation' => $organisation, 'link' => $link, 'messages' => $messages,
        'canWrite' => can_follow_animals($organisation['roles']),
    ], $link['animal_name']);
}

/** /compte/suivi/photo?o=ID&id=…&m=… : photo envoyée par la personne. */
function page_link_photo(): void
{
    $user = require_login();
    $organisation = links_organisation($user);
    $link = find_organisation_link((int)$organisation['id'], (int)input('id'));
    if (!$link || !send_link_photo($link, (int)input('m'))) {
        not_found();
    }
    exit;
}

// ----- Page de la personne qui a reçu l'animal (sans compte) -----

/** /suivi?c=… : fiche de l'animal, échanges, envoi de nouvelles, d'une pesée, d'une photo, d'une question. */
function page_follow(): void
{
    $token = input('c');
    $link = find_link_by_token($token);
    if (!$link) {
        http_response_code(404);
        view('error', ['message' => 'Ce lien de suivi n’existe pas. Vérifiez qu’il est complet, ou demandez-le à la structure.'], 'Suivi');
        return;
    }
    // Le jeton est dans l'adresse : ne jamais l'envoyer à un autre site.
    header('Referrer-Policy: no-referrer');
    if (isset($_GET['photo'])) {
        send_link_photo($link, (int)$_GET['photo']) || not_found();
        exit;
    }
    $error = null;
    if (is_post()) {
        csrf_check();
        $photo = null;
        if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $photo = (string)file_get_contents($_FILES['photo']['tmp_name']);
        } elseif (!empty($_FILES['photo']['error']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error = 'La photo n’a pas pu être envoyée (8 Mo au plus).';
        }
        if ($error === null) {
            $result = add_family_message($link, input('kind'), input('body'), parse_weight(input('weight')), $photo);
            if (is_int($result)) {
                flash(input('kind') === 'question' ? 'Question envoyée : la structure vous répondra ici.' : 'Merci, c’est envoyé à la structure.');
                redirect('/suivi?c=' . $token);
            }
            $error = $result;
        }
    }
    mark_link_read_by_family((int)$link['id']);
    view('links/follow', [
        'link' => $link, 'token' => $token, 'messages' => link_messages((int)$link['id']),
        'card' => $link['card'] ? (json_decode($link['card'], true) ?: []) : [], 'error' => $error,
    ], 'Suivi de ' . $link['animal_name']);
}

// ----- API du logiciel : côté structure (jeton de l'appareil) -----

/** Structure de l'appel (paramètre o), avec accès aux suivis. */
function api_links_organisation(array $device, bool $write): array
{
    $organisation = api_sync_organisation($device);
    if (!can_see_links($organisation['roles']) || ($write && !can_follow_animals($organisation['roles']))) {
        api_error(403, 'roles', 'Vos rôles dans la structure ne permettent pas cette action.');
    }
    return $organisation;
}

function api_find_link(array $organisation): array
{
    $link = find_organisation_link((int)$organisation['id'], (int)api_input('id'));
    if (!$link) {
        api_error(404, 'suivi', 'Suivi introuvable.');
    }
    return $link;
}

/** GET /api/liens?o=ID : suivis de la structure ; POST : nouveau suivi → {link, reference, url}. */
function page_api_links(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $device = api_device();
    if ($method === 'GET') {
        $organisation = api_links_organisation($device, false);
        api_json(['links' => array_map('api_link_summary', organisation_links((int)$organisation['id'])),
            'kinds' => organisation_link_kinds($organisation)]);
    }
    api_require_method('POST');
    $organisation = api_links_organisation($device, true);
    if (!in_array(api_input('kind'), organisation_link_kinds($organisation), true)) {
        api_error(403, 'type', link_kind_refusal($organisation));
    }
    if (api_input('animal_name') === '') {
        api_error(400, 'incomplet', 'Indiquez le nom de l’animal.');
    }
    $card = api_json_field('card');
    $requests = api_json_field('requests');
    [$id, $reference, $token] = create_animal_link($organisation, ['id' => $device['user_id'], 'name' => $device['user_name']], [
        'kind' => api_input('kind'), 'animal_name' => api_input('animal_name'), 'species' => api_input('species'),
        'animal_ref' => api_input('animal_ref'), 'recipient_name' => api_input('recipient_name'),
        'recipient_email' => api_input('recipient_email'), 'months' => api_input('months') === '' ? 3 : (int)api_input('months'),
        'requests' => is_array($requests) ? $requests : explode(',', api_input('requests')),
        'interval_days' => (int)api_input('interval_days'), 'card' => $card,
    ]);
    $link = find_organisation_link((int)$organisation['id'], $id);
    api_json(['link' => api_link_summary($link), 'reference' => $reference, 'url' => link_url($token)]);
}

/** GET /api/liens/messages?o=ID&id=… : échanges du suivi (les envois de la personne deviennent lus). */
function page_api_link_messages(): void
{
    api_require_method('GET');
    $organisation = api_links_organisation(api_device(), false);
    $link = api_find_link($organisation);
    $messages = api_link_messages($link, "/api/liens/photo?o={$organisation['id']}&id={$link['id']}&m=");
    mark_link_read((int)$link['id']);
    api_json(['link' => api_link_summary($link), 'messages' => $messages]);
}

/** POST /api/liens/repondre?o=ID&id=… (body) : réponse ou conseil de la structure. */
function page_api_link_reply(): void
{
    api_require_method('POST');
    $device = api_device();
    $organisation = api_links_organisation($device, true);
    $link = api_find_link($organisation);
    $error = add_organisation_message($link, $organisation, ['id' => $device['user_id'], 'name' => $device['user_name']], api_input('body'));
    if ($error) {
        api_error(400, 'incomplet', $error);
    }
    api_json(['ok' => true]);
}

/** POST /api/liens/fiche?o=ID&id=… (card) : fiche de l'animal mise à jour (nouveau vaccin, traitement…). */
function page_api_link_card(): void
{
    api_require_method('POST');
    $organisation = api_links_organisation(api_device(), true);
    $link = api_find_link($organisation);
    $card = normalize_link_card(api_json_field('card'));
    if ($card === null) {
        api_error(400, 'incomplet', 'Fiche non valable.');
    }
    q('UPDATE animal_links SET card = ? WHERE id = ?', [$card, $link['id']]);
    api_json(['ok' => true]);
}

/** POST /api/liens/fermer?o=ID&id=… : fin du suivi. */
function page_api_link_close(): void
{
    api_require_method('POST');
    $device = api_device();
    $link = api_find_link(api_links_organisation($device, true));
    close_animal_link($link, (int)$device['user_id']);
    api_json(['ok' => true]);
}

/** GET /api/liens/photo?o=ID&id=…&m=… : photo envoyée par la personne. */
function page_api_link_photo(): void
{
    api_require_method('GET');
    $link = api_find_link(api_links_organisation(api_device(), false));
    send_link_photo($link, (int)api_input('m')) || api_error(404, 'absent', 'Photo absente.');
    exit;
}

/** Champ JSON non texte (objet ou liste) du corps de la requête. */
function api_json_field(string $name): mixed
{
    static $json = null;
    if ($json === null) {
        $json = [];
        if (str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
            $decoded = json_decode((string)file_get_contents('php://input'), true);
            $json = is_array($decoded) ? $decoded : [];
        }
    }
    return $json[$name] ?? null;
}

// ----- API du logiciel : côté personne (jeton du lien, sans compte) -----

/** Suivi de l'appel : « Authorization: Bearer <jeton du lien> ». */
function api_follow_link(): array
{
    $link = find_link_by_token(api_bearer_token());
    if (!$link) {
        api_error(401, 'lien', 'Ce lien de suivi n’existe pas ou n’est plus valable.');
    }
    return $link;
}

/** GET /api/suivi[?lu=0] : structure, fiche de l'animal, état du suivi (jours restants) et échanges. */
function page_api_follow(): void
{
    api_require_method('GET');
    $link = api_follow_link();
    $messages = api_link_messages($link, '/api/suivi/photo?m=');
    // ?lu=0 : relecture en arrière-plan par le logiciel (les réponses restent non lues jusqu'à l'ouverture du suivi).
    if (api_input('lu') !== '0') {
        mark_link_read_by_family((int)$link['id']);
    }
    api_json([
        'reference' => $link['reference'],
        'kind' => $link['kind'],
        'organisation' => ['name' => $link['organisation_name'], 'kind' => $link['organisation_kind'],
            'email' => $link['contact_email'], 'phone' => $link['phone'], 'address' => $link['address']],
        'animal' => ['ref' => $link['animal_ref'], 'name' => $link['animal_name'], 'species' => $link['species']],
        'card' => $link['card'] ? json_decode($link['card'], true) : null,
        'requests' => array_values(array_filter(explode(',', $link['requests']))),
        'interval_days' => (int)$link['interval_days'],
        'created_at' => date(DATE_ATOM, strtotime($link['created_at'])),
        'ends_at' => $link['ends_at'] ? date(DATE_ATOM, strtotime($link['ends_at'])) : null,
        'closed_at' => $link['closed_at'] ? date(DATE_ATOM, strtotime($link['closed_at'])) : null,
        'open' => link_is_open($link),
        'days_left' => link_days_left($link),
        'status' => link_remaining_text($link),
        'messages' => $messages,
    ]);
}

/** POST /api/suivi/message (kind, body, weight_kg) : nouvelles, question ou pesée. */
function page_api_follow_message(): void
{
    api_require_method('POST');
    $link = api_follow_link();
    $result = add_family_message($link, api_input('kind'), api_input('body'), parse_weight(api_input('weight_kg')), null);
    is_int($result) ? api_json(['ok' => true, 'id' => $result]) : api_error(link_is_open($link) ? 400 : 403, link_is_open($link) ? 'incomplet' : 'termine', $result);
}

/** POST /api/suivi/photo?texte=… : corps brut = photo (JPEG, PNG ou WebP) ; GET ?m=… : photo d'un message. */
function page_api_follow_photo(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $link = api_follow_link();
    if ($method === 'GET') {
        send_link_photo($link, (int)api_input('m')) || api_error(404, 'absent', 'Photo absente.');
        exit;
    }
    api_require_method('POST');
    $announced = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($announced > LINK_PHOTO_MAX_BYTES) {
        api_error(413, 'taille', 'Photo trop volumineuse (8 Mo au plus).');
    }
    $photo = (string)file_get_contents('php://input', false, null, 0, LINK_PHOTO_MAX_BYTES + 1);
    $result = add_family_message($link, 'photo', (string)($_GET['texte'] ?? ''), null, $photo);
    is_int($result) ? api_json(['ok' => true, 'id' => $result]) : api_error(link_is_open($link) ? 400 : 403, link_is_open($link) ? 'incomplet' : 'termine', $result);
}
