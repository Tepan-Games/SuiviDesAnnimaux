<?php
declare(strict_types=1);

// Échéances des licences : à lancer chaque jour (tâche planifiée « Cron » de l'hébergement OVH, langage PHP, fichier
// tools/echeances-licences.php). Envoie les e-mails de période de grâce, passe les licences à « Expirée » et supprime
// les données synchronisées après le délai de conservation (réglages : Administration › Réglages).
//   php tools/echeances-licences.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/../app/bootstrap.php';

$report = process_licence_deadlines();
audit('echeances.automatiques', "{$report['grace']} en grâce, {$report['expirees']} expirée(s), {$report['supprimees']} suppression(s)", null);
echo date('Y-m-d H:i') . " : {$report['grace']} en période de grâce, {$report['expirees']} expirée(s), {$report['supprimees']} suppression(s) de données\n";
