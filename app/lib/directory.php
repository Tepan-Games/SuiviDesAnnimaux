<?php
declare(strict_types=1);

// ===== Annuaire des structures inscrites et questions du public =====
// Deux choix séparés de chaque structure, désactivés par défaut : apparaître dans l'annuaire (page publique, recherche
// « À proximité » du logiciel et de l'application) et accepter les questions. Une question n'a pas besoin de compte : la
// réponse part par e-mail et reste lisible avec le lien de la question.

/** Rayon de recherche par défaut et maximal, en km. */
const DIRECTORY_DEFAULT_KM = 30;
const DIRECTORY_MAX_KM = 200;

/** Questions par adresse IP et par heure, et par adresse e-mail et par jour (contre les abus). */
const QUESTIONS_PER_IP_HOUR = 5;
const QUESTIONS_PER_EMAIL_DAY = 10;

/**
 * Position d'une adresse : [latitude, longitude, commune] ou null. En ligne : Base Adresse Nationale
 * (api-adresse.data.gouv.fr, données ouvertes) ; réglage geocode.mode = « off » pour ne rien demander, « test » pour les tests.
 */
function geocode_address(string $address): ?array
{
    $address = trim($address);
    if ($address === '') {
        return null;
    }
    $mode = (string)config('geocode.mode', 'ban');
    if ($mode === 'off') {
        return null;
    }
    if ($mode === 'test') {
        return str_contains(mb_strtolower($address), 'nîmes') ? [43.836699, 4.360054, 'Nîmes'] : [48.856614, 2.352222, 'Paris'];
    }
    $context = stream_context_create(['http' => ['timeout' => 4, 'header' => "User-Agent: suivi-des-animaux.fr\r\n"]]);
    $json = @file_get_contents('https://api-adresse.data.gouv.fr/search/?limit=1&q=' . rawurlencode($address), false, $context);
    $data = $json === false ? null : json_decode($json, true);
    $feature = $data['features'][0] ?? null;
    if (!is_array($feature) || ($feature['properties']['score'] ?? 0) < 0.4) {
        return null;
    }
    [$lon, $lat] = $feature['geometry']['coordinates'];
    return [round((float)$lat, 6), round((float)$lon, 6), (string)($feature['properties']['city'] ?? '')];
}

/** Enregistre les choix de l'annuaire et des questions ; l'adresse est géolocalisée si elle a changé. Renvoie un avertissement, ou null. */
function save_directory_settings(array $organisation, array $values): ?string
{
    $listed = !empty($values['directory_listed']);
    $questions = !empty($values['accepts_questions']);
    $website = trim((string)($values['website'] ?? ''));
    if ($website !== '' && !preg_match('#^https?://#i', $website)) {
        $website = 'https://' . $website;
    }
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        $website = '';
    }
    $lat = $organisation['latitude'];
    $lon = $organisation['longitude'];
    $city = $organisation['city'];
    $warning = null;
    $address = (string)($values['address'] ?? $organisation['address']);
    if ($lat === null || $address !== $organisation['address'] || ($listed && $lat === null)) {
        $position = geocode_address($address);
        [$lat, $lon, $city] = $position ?? [null, null, ''];
        if ($listed && $position === null) {
            $warning = 'Adresse introuvable : la structure n’apparaîtra dans la recherche « À proximité » qu’avec une adresse complète (numéro, rue, code postal, commune).';
        }
    }
    q('UPDATE organisations SET directory_listed = ?, accepts_questions = ?, description = ?, species = ?, website = ?, city = ?, latitude = ?, longitude = ?
       WHERE id = ?', [(int)$listed, (int)$questions, mb_substr(trim((string)($values['description'] ?? '')), 0, 600),
        mb_substr(trim((string)($values['species'] ?? '')), 0, 200), mb_substr($website, 0, 255), mb_substr($city, 0, 120), $lat, $lon, $organisation['id']]);
    audit('structure.annuaire', "Structure n°{$organisation['id']} : annuaire " . ($listed ? 'oui' : 'non') . ', questions ' . ($questions ? 'oui' : 'non'));
    return $warning;
}

/** Structures inscrites à l'annuaire autour d'un point, les plus proches d'abord (distance en km). */
function directory_near(float $lat, float $lon, float $km, int $limit = 50): array
{
    $km = max(1.0, min((float)DIRECTORY_MAX_KM, $km));
    // Pré-filtre rectangulaire (index), puis distance exacte (formule de haversine).
    $dLat = $km / 111.0;
    $dLon = $km / max(1.0, 111.0 * cos(deg2rad($lat)));
    $rows = q('SELECT id, name, kind, phone, contact_email, address, city, website, description, species, accepts_questions, latitude, longitude
               FROM organisations WHERE directory_listed = 1 AND latitude BETWEEN ? AND ? AND longitude BETWEEN ? AND ?',
        [$lat - $dLat, $lat + $dLat, $lon - $dLon, $lon + $dLon])->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $distance = haversine_km($lat, $lon, (float)$row['latitude'], (float)$row['longitude']);
        if ($distance <= $km) {
            $result[] = $row + ['distance_km' => round($distance, 1)];
        }
    }
    usort($result, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);
    return array_slice($result, 0, $limit);
}

function haversine_km(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;
    return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** Structure de l'annuaire (publique), ou null si elle n'y est pas inscrite. */
function directory_organisation(int $id): ?array
{
    return q('SELECT * FROM organisations WHERE id = ? AND directory_listed = 1', [$id])->fetch() ?: null;
}

/** Structure telle que le logiciel la reçoit. */
function api_directory_entry(array $o): array
{
    return [
        'id' => (int)$o['id'], 'name' => $o['name'], 'kind' => $o['kind'], 'phone' => $o['phone'], 'email' => $o['contact_email'],
        'address' => $o['address'], 'city' => $o['city'], 'website' => $o['website'], 'description' => $o['description'],
        'species' => $o['species'], 'accepts_questions' => (bool)$o['accepts_questions'],
        'latitude' => (float)$o['latitude'], 'longitude' => (float)$o['longitude'],
        'distance_km' => isset($o['distance_km']) ? (float)$o['distance_km'] : null,
        'url' => url('/structure?id=' . (int)$o['id']),
    ];
}

/** Trop de questions depuis cette adresse IP ou cette adresse e-mail. */
function too_many_questions(string $email): bool
{
    $ip = (int)q('SELECT COUNT(*) FROM organisation_questions WHERE ip = ? AND created_at > ?', [client_ip(), date('Y-m-d H:i:s', time() - 3600)])->fetchColumn();
    $mail = (int)q('SELECT COUNT(*) FROM organisation_questions WHERE email = ? AND created_at > ?', [$email, date('Y-m-d H:i:s', time() - 86400)])->fetchColumn();
    return $ip >= QUESTIONS_PER_IP_HOUR || $mail >= QUESTIONS_PER_EMAIL_DAY;
}

/**
 * Question à une structure qui les accepte : enregistrée, envoyée à la structure (« Répondre » écrit à la personne),
 * accusé de réception avec le lien de la question. Renvoie [id, lien] ou un message d'erreur.
 */
function ask_question(int $organisationId, string $name, string $email, string $body): array|string
{
    $organisation = q('SELECT * FROM organisations WHERE id = ? AND accepts_questions = 1', [$organisationId])->fetch();
    if (!$organisation) {
        return 'Cette structure ne reçoit pas de questions par le site.';
    }
    $name = mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? ''), 0, 120);
    $email = mb_strtolower(trim($email));
    $body = mb_substr(trim($body), 0, 4000);
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($body) < 10) {
        return 'Indiquez votre nom, une adresse e-mail valide et votre question (10 caractères au moins).';
    }
    if (too_many_questions($email)) {
        return 'Beaucoup de questions envoyées : réessayez un peu plus tard.';
    }
    $token = random_token();
    q('INSERT INTO organisation_questions (organisation_id, name, email, body, token_hash, ip, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$organisationId, $name, $email, $body, token_hash($token), client_ip(), now()]);
    $id = (int)db()->lastInsertId();
    $link = url('/question?c=' . $token);
    $recipients = $organisation['contact_email'] !== '' ? [$organisation['contact_email']]
        : array_column(q("SELECT u.email FROM memberships m JOIN users u ON u.id = m.user_id
                          WHERE m.organisation_id = ? AND FIND_IN_SET('responsable', m.roles)", [$organisationId])->fetchAll(), 'email');
    foreach ($recipients as $to) {
        send_mail($to, "Question de $name", "$name ($email) vous pose une question par suivi-des-animaux.fr :\n\n$body\n\n"
            . 'Répondre sur le site : ' . url("/compte/questions?o=$organisationId") . "\n(ou répondez directement à cet e-mail)" . mail_footer(),
            ['reply_to' => $email]);
    }
    send_mail($email, "Votre question à {$organisation['name']}", "Bonjour $name,\n\nVotre question a bien été transmise à « {$organisation['name']} » :\n\n$body\n\n"
        . "La réponse vous sera envoyée par e-mail. Vous pouvez aussi la retrouver ici :\n$link" . mail_footer(),
        ['reply_to' => $organisation['contact_email'] ?: (string)config('support_email')]);
    return [$id, $link];
}

function find_question_by_token(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{48}$/', $token)) {
        return null;
    }
    return q('SELECT q.*, o.name AS organisation_name, o.phone, o.contact_email FROM organisation_questions q
              JOIN organisations o ON o.id = q.organisation_id WHERE q.token_hash = ?', [token_hash($token)])->fetch() ?: null;
}

function organisation_questions(int $organisationId): array
{
    return q('SELECT * FROM organisation_questions WHERE organisation_id = ? ORDER BY answered_at IS NOT NULL, closed_at IS NOT NULL, created_at DESC LIMIT 200',
        [$organisationId])->fetchAll();
}

/** Réponse de la structure, envoyée par e-mail à la personne. */
function answer_question(array $organisation, int $questionId, array $user, string $answer): ?string
{
    $question = q('SELECT * FROM organisation_questions WHERE id = ? AND organisation_id = ?', [$questionId, $organisation['id']])->fetch();
    $answer = mb_substr(trim($answer), 0, 5000);
    if (!$question || $answer === '') {
        return 'Écrivez votre réponse.';
    }
    q('UPDATE organisation_questions SET answer = ?, answered_by = ?, answered_at = ? WHERE id = ?', [$answer, $user['id'], now(), $questionId]);
    send_mail($question['email'], "Réponse de {$organisation['name']}", "Bonjour {$question['name']},\n\n« {$organisation['name']} » a répondu à votre question :\n\n"
        . "> " . str_replace("\n", "\n> ", $question['body']) . "\n\n$answer" . mail_footer(),
        ['reply_to' => $organisation['contact_email'] ?: (string)config('support_email')]);
    audit('question.reponse', "Structure n°{$organisation['id']} : question n°$questionId", (int)$user['id']);
    return null;
}
