<?php
declare(strict_types=1);

/**
 * Envoi d'un e-mail texte. En ligne : fonction mail() de l'hébergement OVH ; en local (mode « log ») : le message est
 * écrit dans storage/mails.log pour pouvoir suivre les liens de confirmation.
 * Options : « from » et « from_name » (expéditeur, par défaut mail.from : noreply@), « reply_to » (par défaut
 * l'adresse d'assistance, pour que « Répondre » n'aille pas vers noreply@), « headers » (en-têtes en plus).
 */
function send_mail(string $to, string $subject, string $body, array $options = []): bool
{
    $from = (string)($options['from'] ?? config('mail.from'));
    $fromName = (string)($options['from_name'] ?? config('mail.from_name'));
    $replyTo = (string)($options['reply_to'] ?? (config('support_email') ?: $from));
    // Aucune adresse ne doit pouvoir ajouter d'en-tête (retour à la ligne dans une adresse saisie).
    foreach ([$to, $from, $replyTo] as $address) {
        if (preg_match('/[\r\n]/', $address)) {
            return false;
        }
    }
    $extra = array_values(array_filter((array)($options['headers'] ?? []), fn($h) => is_string($h) && !preg_match('/[\r\n]/', $h)));
    if (config('mail.mode') === 'log') {
        $entry = "==== " . now() . "\nDe : $fromName <$from>\nÀ : $to\nRépondre à : $replyTo\n"
            . ($extra ? implode("\n", $extra) . "\n" : '') . "Objet : $subject\n\n$body\n\n";
        return file_put_contents(STORAGE_DIR . '/mails.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }
    $headers = array_merge([
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . " <$from>",
        "Reply-To: $replyTo",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ], $extra);
    return mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers), "-f$from");
}

/** Signature commune des e-mails. */
function mail_footer(): string
{
    return "\n\n—\nSuivi des animaux\n" . url('/');
}
