# Suivis des animaux confiés (01/11/2026)

Demande : quand une structure (association, refuge, élevage) confie un animal à un adoptant, une famille d'accueil ou un
particulier, un **lien à identifiant unique, avec QR code**, garde le contact pendant un temps : les nouvelles arrivent sur
le compte de la structure. La personne n'a pas besoin de compte. Sur sa fiche de l'animal, un indicateur montre qu'il y a
un suivi et combien de temps il reste.

## Site (fait)

- Tables `animal_links` (suivi : référence `XXXX-XXXX` affichée, jeton secret du lien dont seule l'empreinte est gardée,
  fiche JSON de l'animal, durée 3 / 6 / 12 mois ou sans fin pour une famille d'accueil) et `animal_link_messages`
  (nouvelles, questions, pesées, photos ; réponses de la structure). Migration :
  `database/migrations/2026-11-01-suivis-animaux-confies.sql`. Photos : `storage/suivis/<id du suivi>/`.
- Pages : `/compte/suivis?o=ID` (liste, création, QR code), `/compte/suivi?o=ID&id=…` (échanges, réponse, fin du suivi),
  `/suivi?c=<jeton>` (page de la personne, sans compte : fiche, temps restant avec barre, envoi, échanges).
- Droits : créer, répondre, terminer = responsable, soins, trésorier ; consultation lit ; famille d'accueil seule : rien.
- E-mails : lien envoyé à la personne si son adresse est donnée ; la structure (adresse de contact, sinon ses responsables)
  est prévenue de chaque envoi ; la personne est prévenue des réponses.
- Limites : 30 envois par jour et par suivi, photo de 8 Mo au plus (JPEG, PNG, WebP reconnus au contenu), fiche de 200 Ko.

## API

Côté structure (jeton de l'appareil, paramètre `o`) :
- `GET /api/liens` → `{links:[{id, reference, kind, animal_ref, animal_name, species, recipient_name, recipient_email,
  requests, interval_days, created_at, ends_at, closed_at, opened, open, unread, last_news_at}]}` ;
- `POST /api/liens` (JSON : kind `adoption`/`accueil`/`particulier`, animal_name, species, animal_ref = SyncId,
  recipient_name, recipient_email, months 3/6/12/0, requests [`nouvelles`,`photo`,`pesee`], interval_days, card {…})
  → `{link, reference, url}` (l'adresse contient le jeton : donnée une seule fois, à mettre dans le QR code) ;
- `GET /api/liens/messages?id=…` → `{link, messages:[{id, author famille/structure, author_name, kind, body, weight_kg,
  photo (adresse), created_at, read}]}` (les envois deviennent lus) ;
- `POST /api/liens/repondre?id=…` (body), `POST /api/liens/fiche?id=…` (card), `POST /api/liens/fermer?id=…`,
  `GET /api/liens/photo?id=…&m=…`.

Côté personne (`Authorization: Bearer <jeton du lien>`, sans compte) :
- `GET /api/suivi` (`?lu=0` : sans marquer les réponses comme lues) → `{reference, kind, organisation{name, kind, email, phone, address}, animal{ref, name, species}, card,
  requests, interval_days, created_at, ends_at, closed_at, open, days_left (null = sans fin, 0 = terminé), status
  (« Suivi en cours : encore 45 jours… »), messages}` ;
- `POST /api/suivi/message` (kind `nouvelle`/`question`/`pesee`, body, weight_kg) ; `POST /api/suivi/photo?texte=…`
  (corps brut) ; `GET /api/suivi/photo?m=…`. Suivi terminé : 403 `termine` (l'historique reste lisible).

Fiche (`card`, envoyée par le logiciel) : `species, sex, breed, color, birthDate, chip, sterilized, weightKg, diet,
allergies, notes` (texte) et listes `vaccines` [{date, name, next}], `treatments` [{name, dosage, until}], `advice` [texte].

## Reste à faire

- Logiciel Windows : bouton « Suivi par lien / QR code » sur l'adoption et le placement ; « Ajouter un animal par lien de
  suivi » (QR code ou lien collé) qui crée l'animal avec sa fiche ; indicateur sur la fiche (temps restant) ; nouvelles
  reçues sur la fiche côté structure. Puis Android (canal Alpha).
- Annuaire des structures volontaires dans « À proximité » et questions (deux choix séparés de la structure).
