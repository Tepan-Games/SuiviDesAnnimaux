# Suivi des animaux — site et service en ligne

Site de **suivi-des-animaux.fr** : vitrine du logiciel (Windows) et de l'application (Android), comptes, structures
(associations, pensions, élevages, familles d'accueil), membres et invitations, licences, suivi des demandes
d'assistance, et administration. PHP 8.1+ et MySQL, sans dépendance à installer : prévu pour l'hébergement
mutualisé OVH « Perso ».

## Organisation

| Dossier | Rôle |
|---|---|
| `www/` | Seul dossier public : `index.php` (aiguillage des adresses), `.htaccess`, feuille de style, images |
| `app/lib/` | Fonctions : base, sécurité, comptes, structures et licences, assistance, e-mails |
| `app/pages/` | Pages (vitrine, comptes, espace client, administration) |
| `app/templates/` | Mise en page et écrans |
| `database/schema.sql` | Structure de la base (à importer une fois) |
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
6. **E-mails** : créer l'adresse `contact@suivi-des-animaux.fr` dans *E-mails* (expéditeur des messages du site).
7. **Mises à jour** : pousser sur `main`, puis *Multisite › Git › Déployer* (ou déploiement automatique par le
   webhook proposé par OVH). Un changement de `database/schema.sql` s'applique à la main dans phpMyAdmin.

## Test local (Laragon)

```powershell
powershell -ExecutionPolicy Bypass -File tools/test-local.ps1
```

Démarre une base MySQL de test dans `test-output/`, importe le schéma, lance le serveur PHP sur
http://localhost:8080 et passe le parcours complet (inscription, confirmation, structure, invitation, assistance,
administration). Les e-mails sont écrits dans `storage/mails.log`.

## À venir

- Interface en ligne des fiches des animaux (lecture et saisie depuis un navigateur).
- API de synchronisation pour le logiciel et l'application (structures), avec filtrage par rôle.
- Paiement des abonnements en ligne (Stripe) et factures.
