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
  tickets, mail (mode `log` en local : `storage/mails.log`), devices (appareils connectés, codes `XXXX-XXXX`), api (JSON,
  jeton `Authorization: Bearer`, stockage de synchronisation `storage/sync/<id structure>/`).
- Interface en ligne (`/application/…`, `app/pages/online.php`, `app/lib/online.php`, écrans `app/templates/online/`) :
  état d'une structure reconstitué depuis `storage/sync/<id>/` comme `SyncEngine` du logiciel (instantané + journaux
  après les curseurs, modification la plus récente gagnante), cache `storage/cache/etat-<id>.json` ; droits comme
  `SpaceAccess` (famille d'accueil seule : animaux confiés ; dépenses : responsable, trésorier, consultation) ; fichiers
  servis seulement s'ils sont cités par un animal visible. Lecture seule ; prochaine étape : saisie écrite en journal.
- `app/pages/` : fonctions `page_*` appelées par `www/index.php` (`api.php` : adresses `/api/…`, sans session ni
  cookie) ; `app/templates/` : écrans.
- `database/schema.sql` : schéma complet (un changement s'applique à la main dans phpMyAdmin chez OVH) ; chaque
  changement a aussi son fichier `database/migrations/<date>-<sujet>.sql` pour la base déjà en ligne.
- E-mails (`docs/adresses-e-mail.md`) : `mail.from` = noreply@ (e-mails automatiques), `support_email` = support@
  (assistance, contact, « Répondre » de tous les e-mails), `newsletter.from` = newsletter@. Lettre d'information :
  `app/lib/newsletter.php`, `app/pages/newsletter.php` (administration `/admin/lettres`, `/desinscription`) ; seulement
  aux comptes abonnés (`users.newsletter_at`) à l'adresse confirmée, envoi par lots, jeton de désinscription par envoi.
- Administration des clients (`app/pages/admin.php`) : `admin_clients()` (filtres, alertes `licence_alerts()`, utilisation
  `organisation_usage()` lue dans les données synchronisées), actions de la fiche client par `action`, formules
  `/admin/formules` (jamais supprimées). Toute action sur une structure est notée dans `audit_log` avec un détail
  commençant par « Structure n°<id> » (historique de la fiche).
- Échéances des licences (`app/lib/licences.php`) : `licence_timeline()` (fin, fin de grâce, suppression),
  `licence_phase()`, `licence_is_read_only()` (utilisé par l'API), `process_licence_deadlines()` lancé chaque jour par
  `tools/echeances-licences.php` (cron OVH) ou depuis `/admin/reglages` ; réglages dans la table `site_settings`
  (`site_setting()`, `LICENCE_SETTINGS` ; les points des noms deviennent « _ » dans les formulaires).
- Rôles (`ROLES`) identiques au logiciel (`SpaceRoles` : responsable, soins, accueil, tresorier, consultation).

## Règles

- Avant l'ouverture, `www/.htaccess` réserve le site à l'adresse IP de l'utilisateur ; les autres voient `bientot.html`.
- Sécurité : requêtes préparées, `csrf_field()`/`csrf_check()` sur chaque formulaire, jetons à usage unique stockés en
  sha256, `app/config.php` jamais versionné (dépôt **public**).
- Après chaque modification : `powershell -ExecutionPolicy Bypass -File tools/test-local.ps1` (MySQL de test isolé de
  Laragon sur le port 3399, serveur PHP sur http://localhost:8080, parcours `tools/parcours-test.php`) ; `-Garder` laisse
  le site ouvert. Sous Linux (MariaDB) : `bash tools/test-local.sh`. Étendre `parcours-test.php` à chaque fonctionnalité.
- Interface et textes en français. Commits signés « Tepan Games » <arcuce@hotmail.com>.

## Synchronisation des structures par le site

Voir `docs/synchronisation-par-le-site.md`. Partie site faite (API, appareils, codes) ; reste le logiciel Windows
(`SiteStore`, `SiteAccount`, `SiteDialog`) puis Android.
