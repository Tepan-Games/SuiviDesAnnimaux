# Synchronisation des structures par le site (plan validé le 31/10/2026)

Demande : dans le logiciel Windows (puis Android), le cloud personnel (OneDrive, Google Drive, NAS WebDAV, dossier)
reste réservé aux espaces **Personnel**. Les autres espaces (association, pension, élevage, famille d'accueil…) se
connectent au site : formulaire adresse e-mail + mot de passe du site, ou code / QR code généré par le site (ou par une
personne connectée au site depuis son téléphone) pour ajouter un espace d'un autre type.

## Principe

Le moteur de synchronisation du logiciel (`SuiviAnimaux.Core/Sync/SyncEngine.cs`) n'utilise qu'une interface de
stockage de fichiers, `ISyncStore` : `ListAsync(dossier)`, `ReadAsync(chemin)`, `WriteAsync(chemin, contenu)`.
Le site n'a donc qu'à fournir ces trois opérations par structure : les fichiers vont dans `storage/sync/<id structure>/`
(protégé par `.htaccess`).

## Site (fait le 31/10/2026)

État : API et pages en place, vérifiées par `tools/parcours-test.php` (119 vérifications). Changement SQL : table
`devices` (à créer dans phpMyAdmin, voir `database/schema.sql`). Détails retenus :
- Jeton d'appareil : 48 caractères hexadécimaux, `Authorization: Bearer` (transmis par `www/.htaccess` chez OVH).
- Réponses de connexion : `{token, email, name, organisations:[{id, name, kind, roles:[…], licence, plan}]}`.
- Erreurs : code HTTP + `{error, message}` ; `error` : `identifiants`, `code`, `jeton` (401), `trop_essais` (429),
  `structure` (404, non membre), `absent` (404, fichier), `chemin` (400), `incomplet` (400), `licence` (403, écriture
  seulement), `taille` (413, 30 Mo par défaut, réglage `sync.max_bytes`), `methode` (405), `adresse` (404).
- En plus du plan : `POST /api/deconnexion` (révoque le jeton de l'appareil) ; un nouveau mot de passe choisi par
  « mot de passe oublié » déconnecte tous les appareils du compte.
- QR code dessiné dans le navigateur (`www/assets/js/qrcode.js`, MIT, Kazuhiko Arase).

Plan d'origine :

- Table `devices` (id, user_id, name, token_hash, created_at, last_seen_at, revoked_at) ; codes de connexion dans
  `user_tokens` (type `appareil`, 15 minutes, usage unique, format `XXXX-XXXX`).
- `app/lib/api.php` : réponses JSON, jeton `Authorization: Bearer`, limite des essais (`too_many_attempts`).
- Adresses :
  - `POST /api/connexion` (email, password, device) → `{token, email, organisations:[{id,name,kind,roles}]}` ;
  - `POST /api/code` (code, device) → même réponse ;
  - `GET /api/structures` → structures du compte ;
  - `GET /api/sync/liste?o=ID&dossier=…` → noms des fichiers ;
  - `GET /api/sync/fichier?o=ID&chemin=…` (404 si absent) ; `POST` même adresse, corps brut = contenu.
- Contrôles : membre de la structure ; chemin `^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$` ; taille maximale ; écriture
  refusée si licence `suspendue`/`expiree`/`resiliee` ; `devices.last_seen_at` mis à jour.
- Espace client : page « Appareils connectés » (liste, déconnexion) et « Connecter un appareil » (code + QR code
  `suivianimaux://site?code=XXXX-XXXX`).
- Étendre `tools/parcours-test.php` (connexion API, code, écriture/lecture/liste, refus d'un non-membre, chemin invalide).

## Logiciel Windows (dépôt Veterinaire)

- Core : `Sync/SiteStore.cs` (ISyncStore HTTP, `Identity` = `site:<hôte>:<id>`), `Sync/SiteAccount.cs` (connexion,
  code, structures ; adresse `https://suivi-des-animaux.fr`, remplacée par `SUIVI_ANIMAUX_SITE_URL` pour les tests).
- `SyncService` : fournisseur `site` (jeton chiffré DPAPI comme `Sync.WebDavSecret`, réglage `Sync.Organisation`,
  rôles reçus → `MyRoles`).
- Paramètres › Synchronisation : boutons OneDrive / Google Drive / NAS / dossier seulement si l'espace est
  « Personnel » (ou s'il utilise déjà l'un d'eux : ne pas couper les testeurs) ; sinon bouton « Se connecter au site ».
- Fenêtre `SiteDialog` : onglet « Adresse e-mail et mot de passe », onglet « Code de connexion » → liste des structures
  → choix.
- Menu des espaces : « Ajouter une structure du site… » : crée l'espace (nom et type de la structure), fichier
  `site-en-attente` (jeton chiffré) puis redémarrage sur l'espace, comme `invitation-en-attente.txt`.
- Smoke test : `SiteStore` contre un faux serveur local, ou le serveur PHP de test du site.
- Ensuite : Android (scan du QR code), invitations par le site au lieu de OneDrive pour les structures.
