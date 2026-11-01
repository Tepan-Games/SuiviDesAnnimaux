<?php
declare(strict_types=1);

// ===== Suivis des animaux confiés : lien à identifiant unique (et QR code) remis par une structure =====
// La personne qui reçoit l'animal (adoptant, famille d'accueil, particulier) n'a pas besoin de compte : le lien contient un
// jeton secret (seule son empreinte est gardée). Ses nouvelles arrivent sur le compte de la structure, sur le site et dans
// le logiciel (API /api/liens/…), et la structure y répond.

const LINK_KINDS = [
    'adoption' => ['Adoption', 'l’adoptant'],
    'accueil' => ['Famille d’accueil', 'la famille d’accueil'],
    'particulier' => ['Particulier', 'la personne'],
];

/** Ce que la personne peut envoyer ; « reponse » est réservé à la structure. */
const LINK_MESSAGE_KINDS = ['nouvelle' => 'Nouvelles', 'question' => 'Question', 'pesee' => 'Pesée', 'photo' => 'Photo'];

/** Nouvelles que la structure peut demander. */
const LINK_REQUESTS = ['nouvelles' => 'Des nouvelles', 'photo' => 'Une photo', 'pesee' => 'Une pesée'];

/** Durées proposées, en mois (0 = jusqu'à la fin du suivi, pour une famille d'accueil). */
const LINK_DURATIONS = [3 => '3 mois', 6 => '6 mois', 12 => '1 an', 0 => 'Jusqu’à ce que la structure le ferme'];

/** Taille maximale d'une photo envoyée. */
const LINK_PHOTO_MAX_BYTES = 8 * 1024 * 1024;

/** Envois de la personne par jour (contre les abus). */
const LINK_MESSAGES_PER_DAY = 30;

/** Fiche de l'animal : taille maximale du JSON envoyé par le logiciel. */
const LINK_CARD_MAX_BYTES = 200 * 1024;

/** Rôles qui peuvent créer un suivi, répondre et le fermer (la consultation lit seulement). */
function can_follow_animals(string $roles): bool
{
    return (bool)array_intersect(['responsable', 'soins', 'tresorier'], explode(',', $roles));
}

/** Membres qui voient les suivis : tous sauf une famille d'accueil seule (elle ne voit que ses animaux). */
function can_see_links(string $roles): bool
{
    return (bool)array_intersect(['responsable', 'soins', 'tresorier', 'consultation'], explode(',', $roles));
}

function link_url(string $token): string
{
    return url('/suivi?c=' . $token);
}

/** Identifiant affiché XXXX-XXXX (mêmes caractères que les codes d'appareil : pas de 0/O, 1/I/L). */
function new_link_reference(): string
{
    for ($attempt = 0; $attempt < 20; $attempt++) {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= DEVICE_CODE_ALPHABET[random_int(0, strlen(DEVICE_CODE_ALPHABET) - 1)];
        }
        $code = substr($code, 0, 4) . '-' . substr($code, 4);
        if (!q('SELECT 1 FROM animal_links WHERE reference = ?', [$code])->fetch()) {
            return $code;
        }
    }
    throw new RuntimeException('Identifiant de suivi impossible à créer.');
}

/** Fiche JSON valable (objet, taille limitée) ou null. */
function normalize_link_card(mixed $card): ?string
{
    if (is_string($card)) {
        $card = trim($card) === '' ? null : json_decode($card, true);
    }
    if (!is_array($card)) {
        return null;
    }
    $json = json_encode($card, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false && strlen($json) <= LINK_CARD_MAX_BYTES ? $json : null;
}

/**
 * Nouveau suivi : renvoie [id, référence, jeton] (le jeton n'est donné qu'une fois, dans le lien et le QR code).
 * $data : kind, animal_name, species, animal_ref, recipient_name, recipient_email, months, requests (liste), interval_days, card.
 */
function create_animal_link(array $organisation, array $user, array $data): array
{
    $kind = isset(LINK_KINDS[$data['kind'] ?? '']) ? $data['kind'] : 'adoption';
    $months = (int)($data['months'] ?? 3);
    if (!array_key_exists($months, LINK_DURATIONS)) {
        $months = 3;
    }
    $requests = array_values(array_intersect(array_keys(LINK_REQUESTS), (array)($data['requests'] ?? [])));
    $token = random_token();
    $reference = new_link_reference();
    $email = mb_strtolower(trim((string)($data['recipient_email'] ?? '')));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = '';
    }
    q('INSERT INTO animal_links (reference, organisation_id, kind, animal_ref, animal_name, species, recipient_name, recipient_email,
          card, requests, interval_days, token_hash, created_by, created_at, ends_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $reference, $organisation['id'], $kind,
        mb_substr(trim((string)($data['animal_ref'] ?? '')), 0, 64),
        mb_substr(trim((string)$data['animal_name']), 0, 120),
        mb_substr(trim((string)($data['species'] ?? '')), 0, 60),
        mb_substr(trim((string)($data['recipient_name'] ?? '')), 0, 150),
        $email,
        normalize_link_card($data['card'] ?? null),
        implode(',', $requests),
        max(0, min(365, (int)($data['interval_days'] ?? 0))),
        token_hash($token), $user['id'], now(),
        $months > 0 ? date('Y-m-d 23:59:59', strtotime("+$months months")) : null,
    ]);
    $id = (int)db()->lastInsertId();
    audit('suivi.creation', "Structure n°{$organisation['id']} : suivi $reference ({$data['animal_name']}, $kind)", (int)$user['id']);
    if ($email !== '') {
        $role = LINK_KINDS[$kind][1];
        send_mail($email, "Suivi de {$data['animal_name']} avec {$organisation['name']}",
            "Bonjour,\n\n« {$organisation['name']} » vous propose de garder le contact au sujet de {$data['animal_name']}"
            . ($months > 0 ? " pendant " . LINK_DURATIONS[$months] : '') . ".\n\n"
            . "Ouvrez ce lien pour retrouver sa fiche, envoyer des nouvelles ou une photo, et poser vos questions :\n"
            . link_url($token) . "\n\nIdentifiant du suivi : $reference\n\n"
            . "Aucun compte n’est nécessaire. Avec le logiciel ou l’application Suivi des animaux, ce lien ajoute aussi "
            . "l’animal chez vous, avec sa fiche. Gardez ce lien pour vous : il donne accès au suivi." . mail_footer(),
            ['reply_to' => $organisation['contact_email'] ?: (string)config('support_email')]);
    }
    return [$id, $reference, $token];
}

/** Suivi d'après le jeton du lien (même terminé : la personne garde l'historique), avec sa structure. */
function find_link_by_token(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{48}$/', $token)) {
        return null;
    }
    $link = q('SELECT l.*, o.name AS organisation_name, o.kind AS organisation_kind, o.contact_email, o.phone, o.address
               FROM animal_links l JOIN organisations o ON o.id = l.organisation_id WHERE l.token_hash = ?', [token_hash($token)])->fetch();
    if (!$link) {
        return null;
    }
    if ($link['opened_at'] === null) {
        q('UPDATE animal_links SET opened_at = ?, last_seen_at = ? WHERE id = ?', [now(), now(), $link['id']]);
    } elseif ($link['last_seen_at'] === null || strtotime($link['last_seen_at']) < time() - 60) {
        q('UPDATE animal_links SET last_seen_at = ? WHERE id = ?', [now(), $link['id']]);
    }
    return $link;
}

/** Suivi d'une structure (null s'il n'est pas à elle). */
function find_organisation_link(int $organisationId, int $linkId): ?array
{
    return q('SELECT * FROM animal_links WHERE id = ? AND organisation_id = ?', [$linkId, $organisationId])->fetch() ?: null;
}

/** Suivi encore ouvert aux nouveaux envois. */
function link_is_open(array $link): bool
{
    return $link['closed_at'] === null && ($link['ends_at'] === null || strtotime($link['ends_at']) >= time());
}

function link_status(array $link): string
{
    if ($link['closed_at'] !== null) {
        return 'Terminé le ' . format_date($link['closed_at']);
    }
    if ($link['ends_at'] !== null && strtotime($link['ends_at']) < time()) {
        return 'Terminé le ' . format_date($link['ends_at']);
    }
    return $link['ends_at'] === null ? 'En cours' : 'En cours jusqu’au ' . format_date($link['ends_at']);
}

/** Suivis d'une structure, avec le nombre de messages non lus et la dernière nouvelle. */
function organisation_links(int $organisationId): array
{
    return q("SELECT l.*,
                (SELECT COUNT(*) FROM animal_link_messages m WHERE m.link_id = l.id AND m.author = 'famille' AND m.read_at IS NULL) AS unread,
                (SELECT MAX(m.created_at) FROM animal_link_messages m WHERE m.link_id = l.id AND m.author = 'famille') AS last_news_at
              FROM animal_links l WHERE l.organisation_id = ? ORDER BY l.closed_at IS NOT NULL, l.created_at DESC", [$organisationId])->fetchAll();
}

function link_messages(int $linkId): array
{
    return q('SELECT * FROM animal_link_messages WHERE link_id = ? ORDER BY id', [$linkId])->fetchAll();
}

/** La structure a lu les envois de la personne. */
function mark_link_read(int $linkId): void
{
    q("UPDATE animal_link_messages SET read_at = ? WHERE link_id = ? AND author = 'famille' AND read_at IS NULL", [now(), $linkId]);
}

/** La personne a lu les réponses de la structure. */
function mark_link_read_by_family(int $linkId): void
{
    q("UPDATE animal_link_messages SET read_at = ? WHERE link_id = ? AND author = 'structure' AND read_at IS NULL", [now(), $linkId]);
}

/** Trop d'envois aujourd'hui pour ce suivi. */
function link_too_many_messages(int $linkId): bool
{
    return (int)q("SELECT COUNT(*) FROM animal_link_messages WHERE link_id = ? AND author = 'famille' AND created_at > ?",
        [$linkId, date('Y-m-d H:i:s', time() - 86400)])->fetchColumn() >= LINK_MESSAGES_PER_DAY;
}

/** Poids saisi (« 4,2 », « 4.250 ») en kg, ou null. */
function parse_weight(string $value): ?float
{
    $value = str_replace(',', '.', trim($value));
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    $weight = (float)$value;
    return $weight > 0 && $weight < 2000 ? round($weight, 3) : null;
}

/**
 * Envoi de la personne (nouvelle, question, pesée, photo). $photo : contenu binaire déjà lu, ou null.
 * Renvoie l'id du message, ou un message d'erreur.
 */
function add_family_message(array $link, string $kind, string $body, ?float $weight, ?string $photo): int|string
{
    if (!link_is_open($link)) {
        return 'Ce suivi est terminé : vous pouvez encore lire les échanges, mais plus en envoyer.';
    }
    if (link_too_many_messages((int)$link['id'])) {
        return 'Beaucoup d’envois aujourd’hui : réessayez demain.';
    }
    $kind = isset(LINK_MESSAGE_KINDS[$kind]) ? $kind : 'nouvelle';
    if ($photo !== null) {
        $kind = 'photo';
    } elseif ($weight !== null) {
        $kind = 'pesee';
    }
    $body = mb_substr(trim($body), 0, 5000);
    if ($body === '' && $weight === null && $photo === null) {
        return 'Écrivez quelques mots, indiquez un poids ou joignez une photo.';
    }
    $extension = null;
    if ($photo !== null) {
        $extension = photo_extension($photo);
        if ($extension === null) {
            return 'La photo doit être une image JPEG, PNG ou WebP.';
        }
        if (strlen($photo) > LINK_PHOTO_MAX_BYTES) {
            return 'Photo trop volumineuse (8 Mo au plus).';
        }
    }
    q('INSERT INTO animal_link_messages (link_id, author, author_name, kind, body, weight_kg, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$link['id'], 'famille', $link['recipient_name'], $kind, $body, $weight, now()]);
    $id = (int)db()->lastInsertId();
    if ($photo !== null) {
        $dir = link_photo_dir((int)$link['id']);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException("Dossier impossible à créer : $dir");
        }
        $name = "$id-" . bin2hex(random_bytes(6)) . ".$extension";
        file_put_contents("$dir/$name", $photo, LOCK_EX);
        q('UPDATE animal_link_messages SET photo = ? WHERE id = ?', [$name, $id]);
    }
    notify_organisation_of_message($link, $kind, $body, $weight);
    return $id;
}

/** Réponse ou conseil de la structure ; prévient la personne par e-mail si elle a donné son adresse. */
function add_organisation_message(array $link, array $organisation, array $user, string $body): ?string
{
    $body = mb_substr(trim($body), 0, 5000);
    if ($body === '') {
        return 'Écrivez votre réponse.';
    }
    q('INSERT INTO animal_link_messages (link_id, author, author_name, kind, body, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$link['id'], 'structure', $user['name'] ?: $organisation['name'], 'reponse', $body, now()]);
    mark_link_read((int)$link['id']);
    if ($link['recipient_email'] !== '') {
        send_mail($link['recipient_email'], "{$organisation['name']} : message au sujet de {$link['animal_name']}",
            "Bonjour,\n\n« {$organisation['name']} » vous a écrit au sujet de {$link['animal_name']} :\n\n$body\n\n"
            . "Pour répondre ou envoyer des nouvelles, ouvrez le lien de suivi que vous avez reçu (identifiant {$link['reference']})."
            . mail_footer(), ['reply_to' => $organisation['contact_email'] ?: (string)config('support_email')]);
    }
    return null;
}

/** Prévient la structure (adresse de contact, sinon ses responsables) d'un envoi de la personne. */
function notify_organisation_of_message(array $link, string $kind, string $body, ?float $weight): void
{
    $recipients = $link['contact_email'] !== '' ? [$link['contact_email']]
        : array_column(q("SELECT u.email FROM memberships m JOIN users u ON u.id = m.user_id
                          WHERE m.organisation_id = ? AND FIND_IN_SET('responsable', m.roles)", [$link['organisation_id']])->fetchAll(), 'email');
    $who = $link['recipient_name'] !== '' ? $link['recipient_name'] : LINK_KINDS[$link['kind']][1] ?? 'la personne';
    $what = match ($kind) {
        'question' => 'une question',
        'pesee' => 'une pesée (' . str_replace('.', ',', (string)(float)$weight) . ' kg)',
        'photo' => 'une photo',
        default => 'des nouvelles',
    };
    $text = "{$link['animal_name']} (suivi {$link['reference']}) : $who a envoyé $what.\n\n" . ($body !== '' ? "$body\n\n" : '')
        . 'Voir et répondre : ' . url("/compte/suivi?o={$link['organisation_id']}&id={$link['id']}") . mail_footer();
    foreach ($recipients as $email) {
        send_mail($email, ($kind === 'question' ? 'Question' : 'Nouvelles') . " : {$link['animal_name']}", $text,
            $link['recipient_email'] !== '' ? ['reply_to' => $link['recipient_email']] : []);
    }
}

function close_animal_link(array $link, int $userId): void
{
    q('UPDATE animal_links SET closed_at = ? WHERE id = ? AND closed_at IS NULL', [now(), $link['id']]);
    audit('suivi.fin', "Structure n°{$link['organisation_id']} : suivi {$link['reference']}", $userId);
}

function link_photo_dir(int $linkId): string
{
    return STORAGE_DIR . '/suivis/' . $linkId;
}

/** Extension d'après le contenu (jamais d'après le nom envoyé), ou null si ce n'est pas une image acceptée. */
function photo_extension(string $data): ?string
{
    return match (true) {
        str_starts_with($data, "\xFF\xD8\xFF") => 'jpg',
        str_starts_with($data, "\x89PNG\r\n\x1A\n") => 'png',
        strlen($data) > 12 && substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP' => 'webp',
        default => null,
    };
}

/** Envoie la photo d'un message du suivi (null : rien à envoyer). */
function send_link_photo(array $link, int $messageId): bool
{
    $message = q('SELECT photo FROM animal_link_messages WHERE id = ? AND link_id = ?', [$messageId, $link['id']])->fetch();
    if (!$message || !$message['photo'] || !preg_match('/^[0-9a-z-]+\.(jpg|png|webp)$/', $message['photo'])) {
        return false;
    }
    $file = link_photo_dir((int)$link['id']) . '/' . $message['photo'];
    if (!is_file($file)) {
        return false;
    }
    $type = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($file, PATHINFO_EXTENSION)];
    header("Content-Type: $type");
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=86400');
    readfile($file);
    return true;
}

/** Messages tels que le logiciel les reçoit (adresse de la photo comprise). */
function api_link_messages(array $link, string $photoPath): array
{
    return array_map(fn(array $m) => [
        'id' => (int)$m['id'],
        'author' => $m['author'],
        'author_name' => $m['author_name'],
        'kind' => $m['kind'],
        'body' => $m['body'],
        'weight_kg' => $m['weight_kg'] === null ? null : (float)$m['weight_kg'],
        'photo' => $m['photo'] ? $photoPath . (int)$m['id'] : null,
        'created_at' => date(DATE_ATOM, strtotime($m['created_at'])),
        'read' => $m['read_at'] !== null,
    ], link_messages((int)$link['id']));
}

/** Suivi tel que le logiciel de la structure le reçoit. */
function api_link_summary(array $link): array
{
    return [
        'id' => (int)$link['id'],
        'reference' => $link['reference'],
        'kind' => $link['kind'],
        'animal_ref' => $link['animal_ref'],
        'animal_name' => $link['animal_name'],
        'species' => $link['species'],
        'recipient_name' => $link['recipient_name'],
        'recipient_email' => $link['recipient_email'],
        'requests' => array_values(array_filter(explode(',', $link['requests']))),
        'interval_days' => (int)$link['interval_days'],
        'created_at' => date(DATE_ATOM, strtotime($link['created_at'])),
        'ends_at' => $link['ends_at'] ? date(DATE_ATOM, strtotime($link['ends_at'])) : null,
        'closed_at' => $link['closed_at'] ? date(DATE_ATOM, strtotime($link['closed_at'])) : null,
        'opened' => $link['opened_at'] !== null,
        'open' => link_is_open($link),
        'unread' => (int)($link['unread'] ?? 0),
        'last_news_at' => !empty($link['last_news_at']) ? date(DATE_ATOM, strtotime($link['last_news_at'])) : null,
    ];
}
