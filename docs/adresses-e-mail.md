# Adresses e-mail de suivi-des-animaux.fr (OVH)

L'hébergement Perso d'OVH comprend des comptes e-mail (offre MX Plan) sur le domaine. Plan proposé :

| Adresse | Type | Rôle |
|---|---|---|
| `support@suivi-des-animaux.fr` | **vrai compte** (boîte) | reçoit l'assistance, les messages du formulaire de contact et les réponses des utilisateurs |
| `noreply@suivi-des-animaux.fr` | **vrai compte** (petite boîte) | expéditeur des e-mails automatiques du site (confirmation d'adresse, mot de passe, invitations, réponses aux demandes). Une vraie boîte reçoit les retours « adresse inconnue », utiles pour repérer les fautes de frappe |
| `newsletter@suivi-des-animaux.fr` | **redirection** vers support@ (ou vrai compte plus tard) | expéditeur des lettres d'information ; les réponses arrivent au support |

## 1. Créer les adresses

1. Espace client OVH : https://www.ovh.com/manager/ › **Web Cloud** › **E-mails** › `suivi-des-animaux.fr`.
   (Si le domaine n'apparaît pas, l'hébergement n'est pas encore activé : attendre le mail d'OVH.)
2. Onglet **Comptes e-mail** › **Créer un compte e-mail** : créer `support`, puis `noreply`. Mot de passe long, gardé
   dans un gestionnaire de mots de passe.
3. Onglet **Redirections** › **Ajouter une redirection** : de `newsletter@suivi-des-animaux.fr` vers
   `support@suivi-des-animaux.fr`, « Conserver une copie » : non.
4. Lire le courrier : webmail https://www.ovhcloud.com/fr/mail/ , ou ajouter le compte `support@` dans Outlook
   (serveur IMAP `ssl0.ovh.net`, port 993 ; SMTP `ssl0.ovh.net`, port 465, identifiant = adresse complète).
   Possible aussi : redirection de `support@` vers arcuce@hotmail.com **en gardant une copie**.

## 2. Éviter le dossier « courrier indésirable » (SPF, DKIM, DMARC)

Dans **Web Cloud › Noms de domaine › suivi-des-animaux.fr › Zone DNS** :

- **SPF** : OVH crée en général l'entrée tout seul. Vérifier qu'il existe un enregistrement TXT (ou SPF) sur le domaine
  lui-même contenant `v=spf1 include:mx.ovh.com ~all`. Sinon : **Ajouter une entrée › SPF**, choisir la valeur OVH.
- **DKIM** : **Web Cloud › E-mails › suivi-des-animaux.fr**, onglet **Informations générales** (ou **Sécurité**),
  rubrique DKIM › **Activer**. OVH ajoute les entrées DNS lui-même (quelques heures).
- **DMARC** : **Ajouter une entrée › TXT**, sous-domaine `_dmarc`, valeur
  `v=DMARC1; p=none; rua=mailto:support@suivi-des-animaux.fr`
  (`p=none` = on observe d'abord ; passer à `p=quarantine` après quelques semaines sans problème).

Vérifier ensuite en envoyant un e-mail depuis le site vers l'adresse donnée par https://www.mail-tester.com
(objectif : 9/10 ou plus).

## 3. Réglages du site (`app/config.php` sur OVH, jamais versionné)

```php
'mail' => ['mode' => 'mail', 'from' => 'noreply@suivi-des-animaux.fr', 'from_name' => 'Suivi des animaux'],
'support_email' => 'support@suivi-des-animaux.fr',
'newsletter' => ['from' => 'newsletter@suivi-des-animaux.fr', 'from_name' => 'Suivi des animaux', 'batch' => 50],
```

Tous les e-mails du site ont « Répondre à : support@ » ; ceux du formulaire de contact, l'adresse de la personne.

## 4. Lettre d'information

Rédigée et envoyée depuis *Administration › Lettres d'information* (voir le README). Avant le premier envoi, appliquer
`database/migrations/2026-10-31-lettre-information.sql` dans phpMyAdmin si la base existait déjà. Commencer par
s'envoyer un essai et le passer dans https://www.mail-tester.com.
