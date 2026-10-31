<?php
// Copier ce fichier en app/config.php (jamais versionné) et le remplir avec les accès donnés par OVH.
return [
    // Adresse publique du site, sans « / » final.
    'base_url' => 'https://suivi-des-animaux.fr',

    // Base MySQL créée dans l'espace client OVH (Hébergements › Bases de données).
    'db' => [
        'dsn' => 'mysql:host=xxxxx.mysql.db;dbname=xxxxx;charset=utf8mb4',
        'user' => 'xxxxx',
        'password' => 'xxxxx',
    ],

    // E-mails : « mail » (fonction mail() d'OVH) en ligne, « log » en local (écrits dans storage/mails.log).
    // Expéditeur des e-mails automatiques (confirmation, mot de passe, invitations, réponses aux demandes).
    'mail' => [
        'mode' => 'mail',
        'from' => 'noreply@suivi-des-animaux.fr',
        'from_name' => 'Suivi des animaux',
    ],

    // Adresse qui reçoit les demandes d'assistance et les messages du formulaire de contact ; « Répondre » à un
    // e-mail du site écrit aussi à cette adresse.
    'support_email' => 'support@suivi-des-animaux.fr',

    // Lettre d'information : expéditeur et nombre d'e-mails par lot (limite d'envoi de l'hébergement).
    'newsletter' => [
        'from' => 'newsletter@suivi-des-animaux.fr',
        'from_name' => 'Suivi des animaux',
        'batch' => 50,
    ],

    // Liens de téléchargement des applications.
    'download_pc' => 'https://github.com/Tepan-Games/SuiviAnimaux-versions/releases/latest',
    'download_android' => 'https://play.google.com/store/apps/details?id=com.tepangames.suivianimaux',

    // Synchronisation des structures (facultatif) : taille maximale d'un fichier envoyé, en octets (30 Mo par défaut).
    // Rester sous les réglages post_max_size et upload_max_filesize de PHP chez OVH.
    // 'sync' => ['max_bytes' => 30 * 1024 * 1024],

    // Affiche les erreurs PHP (uniquement en local).
    'debug' => false,
];
