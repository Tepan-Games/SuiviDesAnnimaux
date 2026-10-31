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
    'mail' => [
        'mode' => 'mail',
        'from' => 'contact@suivi-des-animaux.fr',
        'from_name' => 'Suivi des animaux',
    ],

    // Adresse qui reçoit les nouvelles demandes d'assistance.
    'support_email' => 'contact@suivi-des-animaux.fr',

    // Liens de téléchargement des applications.
    'download_pc' => 'https://github.com/Tepan-Games/SuiviAnimaux-versions/releases/latest',
    'download_android' => 'https://play.google.com/store/apps/details?id=com.tepangames.suivianimaux',

    // Affiche les erreurs PHP (uniquement en local).
    'debug' => false,
];
