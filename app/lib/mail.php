<?php
declare(strict_types=1);

/**
 * Envoi d'un e-mail texte. En ligne : fonction mail() de l'hébergement OVH ; en local (mode « log ») : le message est
 * écrit dans storage/mails.log pour pouvoir suivre les liens de confirmation.
 */
function send_mail(string $to, string $subject, string $body): bool
{
    if (config('mail.mode') === 'log') {
        $entry = "==== " . now() . "\nÀ : $to\nObjet : $subject\n\n$body\n\n";
        return file_put_contents(STORAGE_DIR . '/mails.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }
    $from = config('mail.from');
    $name = mb_encode_mimeheader((string)config('mail.from_name'), 'UTF-8');
    $headers = [
        "From: $name <$from>",
        "Reply-To: $from",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    return mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers), "-f$from");
}

/** Signature commune des e-mails. */
function mail_footer(): string
{
    return "\n\n—\nSuivi des animaux\n" . url('/');
}
