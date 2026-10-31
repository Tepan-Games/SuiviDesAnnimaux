# Suivi des animaux — site et service en ligne

Site de **suivi-des-animaux.fr** : vitrine du logiciel (Windows) et de l'application (Android), comptes, structures
(associations, pensions, élevages, familles d'accueil), membres et invitations, licences, suivi des demandes
d'assistance, lettre d'information, et administration. PHP 8.1+ et MySQL, sans dépendance à installer : prévu pour l'hébergement
mutualisé OVH « Perso ».

## Organisation

| Dossier | Rôle |
|---|---|
| `www/` | Seul dossier public : `index.php` (aiguillage des adresses), `.htaccess`, feuille de style, images |
| `app/lib/` | Fonctions : base, sécurité, comptes, structures et licences, assistance, e-mails |
| `app/pages/` | Pages (vitrine, comptes, espace client, administration) |
| `app/templates/` | Mise en page et écrans |
| `database/schema.sql` | Structure de la base (à importer une fois) ; `database/migrations/` : mises à jour d'une base existante |
| `tools/` | Outils : désigner un administrateur, test local |
| `storage/` | Journaux et e-mails de test (créé tout seul, jamais versionné) |

Sécurité : requêtes préparées, mots de passe hachés (`password_hash`), jeton contre la falsification de
formulaires (CSRF) sur chaque envoi, session protégée (HttpOnly, SameSite, HTTPS), limite des essais de connexion,
liens de confirmation, de mot de passe et d'invitation à usage unique dont seule l'empreinte est gardée.

## Mise en ligne chez OVH (déploiement Git)

1. **Espace client OVH › Hébergements › suivi-des-animaux.fr** :
   - *Informations générales* : PHP 8.2 ou plus récent ;
   - *Bases de données* : créer une base MySQL (noter serveur, nom, utilisateur, mot de passe) ;
   - *Multisite* : sur la ligne du domaine (et de `www.`), *Configurer Git* avec le dépôt
     `https://github.com/Tepan-Games/SuiviDesAnnimaux.git`, branche `main` (pour un dépôt privé, ajouter
     la clé SSH donnée par OVH dans *GitHub › Settings › Deploy keys*, en lecture seule). Le dépôt entier est
     copié dans le dossier du site : le `.htaccess` de la racine ne sert que `www/` et interdit `app/`,
     `database/`, `tools/` et `storage/`.
2. **phpMyAdmin** (lien dans *Bases de données*) : importer `database/schema.sql`.
3. **Configuration** (une seule fois, par FTP : identifiants dans *FTP-SSH*) : copier `app/config.example.php`
   en `app/config.php` dans le dossier du site, y mettre l'adresse `https://suivi-des-animaux.fr`, les accès
   de la base et `'mode' => 'mail'` pour les e-mails. Ce fichier n'est pas dans Git : les déploiements suivants
   ne le touchent pas.
4. **HTTPS** : *Certificat SSL* Let's Encrypt (gratuit), puis le site redirige tout seul vers https.
5. **Administrateur** : créer son compte sur `/inscription`, puis dans phpMyAdmin :
   `UPDATE users SET is_admin = 1, email_verified_at = NOW() WHERE email = 'votre@adresse';`
6. **E-mails** : créer `support@`, `noreply@` et `newsletter@suivi-des-animaux.fr`, et régler SPF, DKIM et DMARC :
   voir [docs/adresses-e-mail.md](docs/adresses-e-mail.md).
7. **Mises à jour** : pousser sur `main`, puis *Multisite › Git › Déployer* (ou déploiement automatique par le
   webhook proposé par OVH). Un changement de `database/schema.sql` s'applique à la main dans phpMyAdmin
   (fichiers de `database/migrations/`, dans l'ordre des dates).

## Administration des clients et des licences

*Administration › Clients et licences* : recherche (structure, titulaire, e-mail, SIRET), filtres (état, formule, type,
alertes : fin dépassée, fin dans les 30 jours, au-delà de la formule, sans licence), animaux présents comparés à la
limite de la formule (d'après les données synchronisées), dernières données reçues, export CSV (Excel). Fiche d'un
client : licence (formulaire complet, prolonger d'un mois ou d'un an, suspendre, réactiver, résilier), coordonnées,
membres (rôles, ajout d'un compte existant, retrait, changement de titulaire), message aux responsables, appareils
connectés, taille des données, demandes d'assistance et historique. *Formules* : noms, prix, limite d'animaux, ordre,
proposée ou retirée de la page Tarifs, création. Le tableau de bord liste les licences à régulariser (fin dépassée,
dépassement de la formule) et le nombre de licences par formule.

## Lettre d'information

*Administration › Lettres d'information* : nouvelle lettre (modèle proposé, `{nom}` et `{site}` remplacés à l'envoi),
aperçu, essai envoyé à soi-même, puis envoi aux abonnés par lots (`newsletter.batch`, 50 par défaut, à cause de la
limite d'envoi de l'hébergement : bouton « Envoyer le lot suivant »). Seuls les comptes qui ont coché la case (à
l'inscription ou dans le profil) et confirmé leur adresse la reçoivent. Chaque lettre a un lien de désinscription
personnel et les en-têtes `List-Unsubscribe` (désabonnement en un clic dans Gmail, Outlook…).

## Test local (Laragon)

```powershell
powershell -ExecutionPolicy Bypass -File tools/test-local.ps1
```

Démarre une base MySQL de test dans `test-output/`, importe le schéma, lance le serveur PHP sur
http://localhost:8080 et passe le parcours complet (inscription, confirmation, structure, invitation, assistance,
administration, lettre d'information, appareils, API de synchronisation et interface en ligne). Les e-mails sont écrits dans `storage/mails.log`.
Sous Linux avec MariaDB : `bash tools/test-local.sh`.

## Synchronisation des structures

Le logiciel et l'application se connectent au site (adresse e-mail et mot de passe, ou code à usage unique généré
dans *Mon compte › Appareils*, avec QR code) puis lisent et écrivent les fichiers de synchronisation de chaque
structure par l'API `/api/…` (voir `docs/synchronisation-par-le-site.md`). Les fichiers sont dans
`storage/sync/<id structure>/`, jamais servis par le web.

## Interface en ligne (`/application`)

Consultation depuis un navigateur des animaux des structures, d'après leurs fichiers de synchronisation : liste
(recherche, statut, espèce), fiche (identité, santé, alimentation, suivis demandés, traitements, journal des soins et
pesées, parcours, photos, documents ; dépenses et adoption pour les responsables, trésoriers et consultation). Une
famille d'accueil seule ne voit que les animaux qui lui sont confiés (placement en cours chez un contact qui a
l'adresse de son compte). L'état est reconstitué comme dans le logiciel (dernier état complet puis journaux) par
`app/lib/online.php` et gardé dans `storage/cache/` jusqu'au prochain fichier reçu. Lecture seule pour le moment.

## À venir

- Saisie depuis l'interface en ligne (pesées, soins, notes…), écrite comme un journal d'appareil.
- Filtrage par rôle des données synchronisées par le site.
- Paiement des abonnements en ligne (Stripe) et factures.
