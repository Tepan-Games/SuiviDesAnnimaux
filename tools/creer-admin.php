<?php
declare(strict_types=1);

// Donne les droits d'administration du site à un compte existant (créé d'abord par la page d'inscription).
//   php tools/creer-admin.php adresse@exemple.fr
// Chez OVH : à lancer une fois en local avec les accès de la base en ligne, ou par une requête SQL dans phpMyAdmin :
//   UPDATE users SET is_admin = 1, email_verified_at = NOW() WHERE email = 'adresse@exemple.fr';
require __DIR__ . '/../app/bootstrap.php';

$email = mb_strtolower(trim($argv[1] ?? ''));
if ($email === '') {
    fwrite(STDERR, "Usage : php tools/creer-admin.php adresse@exemple.fr\n");
    exit(1);
}
$user = find_user_by_email($email);
if (!$user) {
    fwrite(STDERR, "Aucun compte avec l’adresse $email : créez-le d’abord sur la page d’inscription.\n");
    exit(1);
}
q('UPDATE users SET is_admin = 1, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [now(), $user['id']]);
audit('admin.creation', $email, (int)$user['id']);
echo "$email est maintenant administrateur du site.\n";
