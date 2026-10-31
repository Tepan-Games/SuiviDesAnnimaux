# Site suivi-des-animaux.fr

Site PHP 8.1+ / MySQL sans framework ni Composer (hébergement OVH « Perso », déploiement Git d'OVH depuis `main`).
Vitrine, comptes, structures (associations, pensions, élevages, familles d'accueil), membres et invitations, licences,
assistance, administration. Logiciel Windows : dépôt `C:\Users\arcuc\Projects\Veterinaire` ; Android :
`C:\Users\arcuc\Projects\SuiviAnimaux-Mobile`. Éditeur affiché : TepanSofts (GitHub reste `Tepan-Games`).

## Structure

- `www/` : seul dossier public (`index.php` aiguille les adresses, `.htaccess`, `assets/`, `bientot.html`).
- `.htaccess` racine : le déploiement Git copie tout le dépôt ; seul `www/` est servi, `app/`, `database/`, `tools/`,
  `storage/` sont interdits (et ont leur propre `.htaccess` « Require all denied »).
- `app/lib/` : helpers (db, q, e, url, csrf, flash, audit, view), auth, organisations (membres, invitations, licences),
  tickets, mail (mode `log` en local : `storage/mails.log`).
- `app/pages/` : fonctions `page_*` appelées par `www/index.php` ; `app/templates/` : écrans.
- `database/schema.sql` : schéma complet (un changement s'applique à la main dans phpMyAdmin chez OVH).
- Rôles (`ROLES`) identiques au logiciel (`SpaceRoles` : responsable, soins, accueil, tresorier, consultation).

## Règles

- Avant l'ouverture, `www/.htaccess` réserve le site à l'adresse IP de l'utilisateur ; les autres voient `bientot.html`.
- Sécurité : requêtes préparées, `csrf_field()`/`csrf_check()` sur chaque formulaire, jetons à usage unique stockés en
  sha256, `app/config.php` jamais versionné (dépôt **public**).
- Après chaque modification : `powershell -ExecutionPolicy Bypass -File tools/test-local.ps1` (MySQL de test isolé de
  Laragon sur le port 3399, serveur PHP sur http://localhost:8080, parcours `tools/parcours-test.php`) ; `-Garder` laisse
  le site ouvert. Étendre `parcours-test.php` à chaque fonctionnalité.
- Interface et textes en français. Commits signés « Tepan Games » <arcuce@hotmail.com>.

## Prochaine étape : synchronisation des structures par le site

Voir `docs/synchronisation-par-le-site.md`.
