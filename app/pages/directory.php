<?php
declare(strict_types=1);

// ===== Annuaire des structures inscrites, questions du public (pages et API) =====

/** /annuaire?q=adresse : structures inscrites autour d'une adresse. */
function page_directory(): void
{
    $query = input('q');
    $results = null;
    $error = null;
    if ($query !== '') {
        $position = geocode_address($query);
        if ($position === null) {
            $error = 'Adresse introuvable : indiquez une commune ou un code postal.';
        } else {
            $results = directory_near($position[0], $position[1], (float)(input('km') ?: DIRECTORY_DEFAULT_KM));
        }
    }
    view('directory/search', ['query' => $query, 'results' => $results, 'error' => $error], 'Annuaire des structures');
}

/** /structure?id=… : page publique d'une structure inscrite, avec le formulaire de question si elle les accepte. */
function page_directory_organisation(): void
{
    $organisation = directory_organisation((int)input('id'));
    if (!$organisation) {
        not_found();
    }
    $sent = null;
    $error = null;
    $values = ['name' => '', 'email' => '', 'body' => ''];
    if (is_post()) {
        csrf_check();
        $values = ['name' => input('name'), 'email' => input('email'), 'body' => input('body')];
        if (input('website') !== '') {
            redirect('/structure?id=' . (int)$organisation['id']);
        }
        $result = ask_question((int)$organisation['id'], $values['name'], $values['email'], $values['body']);
        if (is_array($result)) {
            $sent = $values['email'];
            $values = ['name' => '', 'email' => '', 'body' => ''];
        } else {
            $error = $result;
        }
    }
    view('directory/organisation', ['organisation' => $organisation, 'sent' => $sent, 'error' => $error, 'values' => $values], $organisation['name']);
}

/** /question?c=… : question posée et réponse de la structure. */
function page_question(): void
{
    $question = find_question_by_token(input('c'));
    if (!$question) {
        http_response_code(404);
        view('error', ['message' => 'Cette question est introuvable : vérifiez que le lien est complet.'], 'Question');
        return;
    }
    header('Referrer-Policy: no-referrer');
    view('directory/question', ['question' => $question], 'Votre question');
}

/** /compte/questions?o=ID : questions reçues par la structure, réponse. */
function page_questions(): void
{
    $user = require_login();
    $organisation = organisation_for_member((int)input('o'), (int)$user['id']);
    if (!$organisation || !can_see_links($organisation['roles'])) {
        not_found();
    }
    $canWrite = can_follow_animals($organisation['roles']);
    if (is_post()) {
        csrf_check();
        if (!$canWrite) {
            http_response_code(403);
            exit('Vos rôles ne permettent pas de répondre.');
        }
        if (input('action') === 'classer') {
            q('UPDATE organisation_questions SET closed_at = ? WHERE id = ? AND organisation_id = ?', [now(), (int)input('question'), $organisation['id']]);
            flash('Question classée.');
        } else {
            $error = answer_question($organisation, (int)input('question'), $user, input('answer'));
            $error ? flash($error, 'error') : flash('Réponse envoyée par e-mail.');
        }
        redirect('/compte/questions?o=' . (int)$organisation['id']);
    }
    view('account/questions', ['organisation' => $organisation, 'questions' => organisation_questions((int)$organisation['id']), 'canWrite' => $canWrite],
        'Questions reçues');
}

// ----- API (publique, sans jeton) -----

/** GET /api/annuaire?lat=…&lon=…&km=30 : structures inscrites autour d'un point. */
function page_api_directory(): void
{
    api_require_method('GET');
    $lat = api_input('lat');
    $lon = api_input('lon');
    if (!is_numeric($lat) || !is_numeric($lon) || abs((float)$lat) > 90 || abs((float)$lon) > 180) {
        api_error(400, 'position', 'Position non valable.');
    }
    $km = api_input('km') === '' ? DIRECTORY_DEFAULT_KM : (float)api_input('km');
    header('Cache-Control: public, max-age=600');
    api_json(['organisations' => array_map('api_directory_entry', directory_near((float)$lat, (float)$lon, $km))]);
}

/** POST /api/question (organisation, name, email, body) : question à une structure qui les accepte. */
function page_api_question(): void
{
    api_require_method('POST');
    $result = ask_question((int)api_input('organisation'), api_input('name'), api_input('email'), api_input('body'));
    if (is_string($result)) {
        $tooMany = str_starts_with($result, 'Beaucoup');
        api_error($tooMany ? 429 : 400, $tooMany ? 'trop_essais' : 'incomplet', $result);
    }
    api_json(['ok' => true, 'url' => $result[1]]);
}
