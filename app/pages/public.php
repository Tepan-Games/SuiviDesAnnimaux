<?php
declare(strict_types=1);

// ===== Pages de la vitrine =====

function page_home(): void
{
    view('home', [], 'Le suivi de vos animaux, seul ou à plusieurs');
}

function page_features(): void
{
    view('features', [], 'Fonctionnalités');
}

function page_pricing(): void
{
    view('pricing', ['plans' => plans()], 'Tarifs');
}

function page_download(): void
{
    view('download', [], 'Télécharger');
}

function page_contact(): void
{
    $sent = false;
    $errors = [];
    if (is_post()) {
        csrf_check();
        $name = input('name');
        $email = input('email');
        $message = input('message');
        // Champ caché rempli seulement par les robots.
        if (input('website') !== '') {
            redirect('/contact');
        }
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 10) {
            $errors[] = 'Indiquez votre nom, une adresse e-mail valide et votre message.';
        } else {
            send_mail((string)config('support_email'), "Contact : $name", "$name <$email>\n\n$message");
            $sent = true;
        }
    }
    view('contact', ['sent' => $sent, 'errors' => $errors], 'Contact');
}

function page_legal(): void
{
    view('legal', [], 'Mentions légales');
}

function page_privacy(): void
{
    view('privacy', [], 'Confidentialité');
}
