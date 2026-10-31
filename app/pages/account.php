<?php
declare(strict_types=1);

// ===== Inscription, connexion, mot de passe =====

function page_register(): void
{
    if (current_user()) {
        redirect('/compte');
    }
    $errors = [];
    $values = ['name' => '', 'email' => input('email'), 'kind' => input('type', 'Particulier'), 'organisation' => ''];
    if (is_post()) {
        csrf_check();
        $values = ['name' => input('name'), 'email' => mb_strtolower(input('email')), 'kind' => input('kind'), 'organisation' => input('organisation')];
        $errors = validate_registration($values['name'], $values['email'], (string)($_POST['password'] ?? ''));
        $structure = $values['kind'] !== 'Particulier';
        if ($structure && mb_strlen($values['organisation']) < 2) {
            $errors[] = 'Indiquez le nom de votre structure.';
        }
        if (empty($_POST['cgu'])) {
            $errors[] = 'Acceptez les conditions d’utilisation pour créer votre compte.';
        }
        if (!$errors) {
            $userId = create_user($values['name'], $values['email'], (string)$_POST['password']);
            $user = q('SELECT * FROM users WHERE id = ?', [$userId])->fetch();
            if ($structure) {
                create_organisation($userId, $values['organisation'], $values['kind']);
            }
            audit('compte.creation', $values['email'] . ($structure ? " ({$values['kind']})" : ''), $userId);
            send_verification_mail($user);
            login_user($user);
            if (!empty($_SESSION['pending_invitation'])) {
                redirect('/invitation?c=' . $_SESSION['pending_invitation']);
            }
            flash('Compte créé. Un e-mail de confirmation vient de vous être envoyé.');
            redirect('/compte');
        }
    }
    view('auth/register', ['errors' => $errors, 'values' => $values], 'Créer un compte');
}

function page_login(): void
{
    if (current_user()) {
        redirect('/compte');
    }
    $error = '';
    $email = input('email');
    if (is_post()) {
        csrf_check();
        $result = attempt_login($email, (string)($_POST['password'] ?? ''));
        if (is_string($result)) {
            $error = $result;
        } else {
            login_user($result);
            $next = $_SESSION['after_login'] ?? '/compte';
            unset($_SESSION['after_login']);
            redirect(str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : '/compte');
        }
    }
    view('auth/login', ['error' => $error, 'email' => $email], 'Connexion');
}

function page_logout(): void
{
    if (is_post()) {
        csrf_check();
        logout_user();
    }
    redirect('/');
}

function page_confirm(): void
{
    $userId = consume_user_token('verify', input('t'));
    if ($userId === null) {
        flash('Ce lien de confirmation n’est plus valable. Vous pouvez en demander un autre depuis votre compte.', 'error');
        redirect(current_user() ? '/compte' : '/connexion');
    }
    q('UPDATE users SET email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [now(), $userId]);
    flash('Adresse confirmée, merci !');
    redirect(current_user() ? '/compte' : '/connexion');
}

function page_forgot_password(): void
{
    $sent = false;
    if (is_post()) {
        csrf_check();
        // Même réponse que l'adresse existe ou non (ne révèle pas qui a un compte).
        if ($user = find_user_by_email(input('email'))) {
            send_password_reset_mail($user);
        }
        $sent = true;
    }
    view('auth/forgot', ['sent' => $sent], 'Mot de passe oublié');
}

function page_reset_password(): void
{
    $token = input('t');
    $error = '';
    if (is_post()) {
        csrf_check();
        $password = (string)($_POST['password'] ?? '');
        if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Le mot de passe doit faire au moins ' . PASSWORD_MIN_LENGTH . ' caractères.';
        } elseif (($userId = consume_user_token('reset', $token)) === null) {
            flash('Ce lien n’est plus valable : demandez-en un nouveau.', 'error');
            redirect('/mot-de-passe-oublie');
        } else {
            // Lien reçu par e-mail : l'adresse est aussi confirmée.
            q('UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), now(), $userId]);
            audit('compte.mot_de_passe', '', $userId);
            flash('Mot de passe changé : vous pouvez vous connecter.');
            redirect('/connexion');
        }
    }
    view('auth/reset', ['token' => $token, 'error' => $error], 'Nouveau mot de passe');
}

// ===== Espace client =====

function page_account(): void
{
    $user = require_login();
    if (is_post() && input('action') === 'renvoyer') {
        csrf_check();
        send_verification_mail($user);
        flash('Un nouvel e-mail de confirmation vient de vous être envoyé.');
        redirect('/compte');
    }
    view('account/dashboard', [
        'user' => $user,
        'organisations' => user_organisations((int)$user['id']),
        'tickets' => q('SELECT * FROM tickets WHERE user_id = ? ORDER BY updated_at DESC LIMIT 5', [$user['id']])->fetchAll(),
    ], 'Mon compte');
}

function page_profile(): void
{
    $user = require_login();
    $errors = [];
    if (is_post()) {
        csrf_check();
        $name = input('name');
        if (mb_strlen($name) < 2) {
            $errors[] = 'Indiquez votre nom.';
        }
        $new = (string)($_POST['new_password'] ?? '');
        if ($new !== '') {
            if (!password_verify((string)($_POST['current_password'] ?? ''), $user['password_hash'])) {
                $errors[] = 'Le mot de passe actuel est incorrect.';
            } elseif (mb_strlen($new) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'Le nouveau mot de passe doit faire au moins ' . PASSWORD_MIN_LENGTH . ' caractères.';
            }
        }
        if (!$errors) {
            q('UPDATE users SET name = ? WHERE id = ?', [$name, $user['id']]);
            if ($new !== '') {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
                audit('compte.mot_de_passe');
            }
            flash('Profil enregistré.');
            redirect('/compte/profil');
        }
    }
    view('account/profile', ['user' => $user, 'errors' => $errors], 'Mon profil');
}

/** Interface en ligne du logiciel (en préparation : elle s'appuiera sur le service de synchronisation). */
function page_online_app(): void
{
    $user = require_login();
    view('account/online_app', ['organisations' => user_organisations((int)$user['id'])], 'Interface en ligne');
}
