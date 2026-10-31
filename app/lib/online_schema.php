<?php
declare(strict_types=1);

// ===== Interface en ligne : formulaires des tables synchronisées (mêmes colonnes et listes que SuiviAnimaux.Core) =====
// Type d'un champ : text, textarea, date, datetime, number, int, bool, tristate (Oui / Non / Non renseigné → 1 / 0 / null),
// select (liste « options », ou fonction « choices » selon l'animal), ref (identifiant d'une autre table), file (photo ou document).

const ONLINE_SPECIES_GROUPS = [
    'Chats et chiens' => ['Chat', 'Chien'],
    'Lapins, rongeurs et furets' => ['Lapin', 'Cobaye', 'Hamster', 'Rat', 'Souris', 'Gerbille', 'Chinchilla', 'Furet'],
    'Chevaux, poneys et ânes' => ['Cheval', 'Poney', 'Âne'],
    'Oiseaux' => ['Perruche', 'Perroquet', 'Canari', 'Pigeon'],
    'Volailles' => ['Poule', 'Canard', 'Oie', 'Dinde', 'Caille'],
    'Animaux de ferme' => ['Chèvre', 'Mouton', 'Cochon', 'Vache'],
    'Reptiles' => ['Tortue', 'Serpent', 'Lézard'],
    'Poissons' => ['Poisson'],
    'Autres' => ['Autre'],
];

/** Types d'événement réservés à certaines familles (Models/SpeciesProfile.cs) ; les autres valent pour toutes. */
const ONLINE_EVENT_GROUPS = [
    'Maréchal-ferrant' => ['Chevaux, poneys et ânes'],
    'Parage des onglons' => ['Animaux de ferme'],
    'Coupe des griffes' => ['Chats et chiens', 'Lapins, rongeurs et furets', 'Oiseaux', 'Reptiles'],
    'Tonte' => ['Chats et chiens', 'Lapins, rongeurs et furets', 'Chevaux, poneys et ânes', 'Animaux de ferme'],
    'Ostéopathie' => ['Chats et chiens', 'Chevaux, poneys et ânes', 'Animaux de ferme'],
    'Soins dentaires' => ['Chats et chiens', 'Lapins, rongeurs et furets', 'Chevaux, poneys et ânes'],
    'Hibernation' => ['Reptiles'],
    'Mue' => ['Oiseaux', 'Volailles', 'Reptiles'],
    'Ponte' => ['Oiseaux', 'Volailles', 'Reptiles'],
    'Couvaison' => ['Oiseaux', 'Volailles'],
    'Changement de poulailler' => ['Volailles'],
    'Chaleurs' => ['Chats et chiens', 'Lapins, rongeurs et furets', 'Chevaux, poneys et ânes', 'Animaux de ferme'],
    'Saillie' => ['Chats et chiens', 'Lapins, rongeurs et furets', 'Chevaux, poneys et ânes', 'Animaux de ferme'],
    'Mise bas' => ['Chats et chiens', 'Lapins, rongeurs et furets', 'Chevaux, poneys et ânes', 'Animaux de ferme'],
    'Vaccination' => ['Chats et chiens', 'Lapins, rongeurs et furets', 'Chevaux, poneys et ânes', 'Oiseaux', 'Volailles', 'Animaux de ferme'],
    'Vermifuge' => ['Chats et chiens', 'Lapins, rongeurs et furets', 'Chevaux, poneys et ânes', 'Oiseaux', 'Volailles', 'Animaux de ferme', 'Reptiles'],
];
const ONLINE_FEMALE_EVENTS = ['Ponte', 'Couvaison', 'Chaleurs', 'Mise bas'];
const ONLINE_EVENT_TYPES = ['Consultation', 'Vaccination', 'Pesée', 'Changement d’alimentation', 'Vermifuge', 'Antiparasitaire', 'Opération',
    'Soins dentaires', 'Maréchal-ferrant', 'Parage des onglons', 'Coupe des griffes', 'Tonte', 'Ostéopathie', 'Hibernation', 'Analyse',
    'Observation', 'Ponte', 'Couvaison', 'Changement de poulailler', 'Adoption', 'Chaleurs', 'Saillie', 'Mise bas', 'Mue', 'Autre'];
const ONLINE_EXPENSE_CATEGORIES = ['Vétérinaire', 'Médicament', 'Alimentation', 'Pension', 'Maréchal-ferrant', 'Assurance', 'Identification',
    'Transport', 'Matériel', 'Entretien', 'Autre'];

/** Tri français : « Non renseigné » en tête, « Autre » à la fin (Choices.Alphabetical). */
function online_alphabetical(array $values): array
{
    $rank = fn(string $v) => match ($v) { 'Non renseigné' => 0, 'Autre', 'Autres' => 2, default => 1 };
    $key = fn(string $v) => strtr(mb_strtolower($v), ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c', 'œ' => 'oe']);
    usort($values, fn($a, $b) => [$rank($a), $key($a)] <=> [$rank($b), $key($b)]);
    return array_values(array_unique($values));
}

function online_species_group(string $species): string
{
    foreach (ONLINE_SPECIES_GROUPS as $group => $list) {
        if (in_array($species, $list, true)) {
            return $group;
        }
    }
    return 'Autres';
}

/** Types d'événement qui ont un sens pour l'animal (Choices.EventTypesFor), plus le type déjà enregistré. */
function online_event_types(?array $animal, ?string $current = null): array
{
    $types = ONLINE_EVENT_TYPES;
    if ($animal) {
        $group = online_species_group((string)($animal['Species'] ?? ''));
        $types = array_filter($types, fn($t) => ($group === 'Autres' || !isset(ONLINE_EVENT_GROUPS[$t]) || in_array($group, ONLINE_EVENT_GROUPS[$t], true))
            && (($animal['Sex'] ?? '') !== 'Mâle' || !in_array($t, ONLINE_FEMALE_EVENTS, true)));
    }
    if ($current) {
        $types[] = $current;
    }
    return online_alphabetical($types);
}

function online_expense_categories(?array $animal, ?string $current = null): array
{
    $equine = $animal && online_species_group((string)($animal['Species'] ?? '')) === 'Chevaux, poneys et ânes';
    $list = array_filter(ONLINE_EXPENSE_CATEGORIES, fn($c) => $equine || $c !== 'Maréchal-ferrant');
    if ($current) {
        $list[] = $current;
    }
    return online_alphabetical($list);
}

/**
 * Formulaires : table => [titre, liée à un animal (AnimalId), finances, champs]. Champ : colonne => [libellé, type, options…].
 * Options : required, options, choices (fonction de l'animal et de la valeur), ref (table), default, section, help, accept,
 * finance (montants : seulement pour les rôles qui voient les finances).
 */
function online_forms(): array
{
    static $forms = null;
    if ($forms !== null) {
        return $forms;
    }
    $species = online_alphabetical(array_merge(...array_values(ONLINE_SPECIES_GROUPS)));
    $forms = [
        'Animals' => ['title' => 'Animal', 'animal' => false, 'fields' => [
            'Name' => ['Nom', 'text', 'required' => true, 'section' => 'Identité'],
            'Species' => ['Espèce', 'select', 'options' => $species, 'required' => true, 'default' => 'Chat'],
            'Sex' => ['Sexe', 'select', 'options' => ['Femelle', 'Mâle', 'Inconnu'], 'required' => true, 'default' => 'Inconnu'],
            'Breed' => ['Race', 'text'],
            'BirthDate' => ['Date de naissance', 'date'],
            'BirthDateEstimated' => ['Date de naissance estimée', 'bool'],
            'CoatColor' => ['Robe', 'text'],
            'HeightCm' => ['Taille au garrot (cm)', 'int'],
            'ChipNumber' => ['Numéro de puce', 'text'],
            'TattooNumber' => ['Tatouage', 'text'],
            'UelnNumber' => ['SIRE / UELN', 'text'],
            'Sterilized' => ['Stérilisé', 'tristate'],
            'PhotoPath' => ['Photo principale', 'file', 'accept' => 'image/*'],
            'Status' => ['Statut', 'select', 'options' => ['Présent', 'Adopté', 'Disparu', 'Décédé'], 'default' => 'Présent', 'section' => 'Situation'],
            'OwnershipType' => ['Appartient à', 'select', 'options' => ['Famille', 'Famille d’accueil', 'Association', 'À vérifier'], 'default' => 'Association'],
            'AssociationName' => ['Association', 'text'],
            'ArrivalDate' => ['Date d’arrivée', 'date'],
            'Origin' => ['Origine', 'text'],
            'BoardingContactId' => ['Pension / écurie', 'ref', 'ref' => 'Contacts'],
            'BoardingType' => ['Hébergement', 'select', 'options' => online_alphabetical(['Non renseigné', 'Box', 'Box et paddock', 'Pré', 'Pension complète',
                'Demi-pension', 'Pension travail', 'Chez soi', 'Autre']), 'default' => 'Non renseigné'],
            'MotherId' => ['Mère', 'ref', 'ref' => 'Animals', 'section' => 'Famille et pedigree'],
            'FatherId' => ['Père', 'ref', 'ref' => 'Animals'],
            'LitterName' => ['Portée', 'text'],
            'PedigreeRegistry' => ['Livre généalogique', 'text', 'help' => 'LOOF, LOF…'],
            'PedigreeNumber' => ['Numéro d’inscription', 'text'],
            'BreederName' => ['Éleveur ou affixe', 'text'],
            'VeterinarianContactId' => ['Vétérinaire', 'ref', 'ref' => 'Contacts', 'section' => 'Santé'],
            'Allergies' => ['Allergies', 'textarea'],
            'InsuranceName' => ['Assurance santé', 'text'],
            'InsuranceNumber' => ['Numéro de contrat', 'text'],
            'TargetWeightKg' => ['Poids idéal (kg)', 'number'],
            'WeightAlertPercent' => ['Alerte de variation de poids (%)', 'number', 'default' => 10],
            'DietType' => ['Régime', 'select', 'options' => online_alphabetical(['Non renseigné', 'Croquettes', 'Pâtée', 'Mixte', 'Alimentation vétérinaire',
                'Ration ménagère', 'Autre']), 'default' => 'Non renseigné', 'section' => 'Alimentation'],
            'DietBrand' => ['Marque', 'text'],
            'DietName' => ['Aliment', 'text'],
            'DailyFoodQuantityGrams' => ['Quantité par jour (g)', 'number'],
            'MealsPerDay' => ['Repas par jour', 'int'],
            'DailyWaterMl' => ['Eau par jour (ml)', 'number'],
            'DietStartDate' => ['Début du régime', 'date'],
            'DietDailyTracking' => ['Suivre les repas chaque jour (rappel à l’accueil)', 'bool'],
            'FeedingNotes' => ['Consignes', 'textarea'],
            'CoopName' => ['Poulailler', 'text', 'section' => 'Volailles'],
            'LayingStatus' => ['Ponte', 'select', 'options' => ['Non renseigné', 'En ponte', 'Ponte réduite', 'Ne pond pas', 'Pause saisonnière'], 'default' => 'Non renseigné'],
            'BroodingStatus' => ['Couvaison', 'select', 'options' => ['Non renseigné', 'Ne couve pas', 'Couve', 'Avec poussins'], 'default' => 'Non renseigné'],
            'AvailableForAdoption' => ['À l’adoption', 'bool', 'section' => 'Adoption et notes'],
            'CharacterNotes' => ['Caractère', 'textarea'],
            'CompatibilityNotes' => ['Ententes (chats, chiens, enfants)', 'textarea'],
            'AdoptionRequirements' => ['Conditions d’adoption', 'textarea'],
            'AdoptionContact' => ['Contact pour l’adoption', 'text'],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'AnimalEvents' => ['title' => 'Événement du journal', 'animal' => true, 'fields' => [
            'EventDate' => ['Date', 'date', 'required' => true, 'default' => 'today', 'help' => 'Une date à venir est un rendez-vous.'],
            'Type' => ['Type', 'select', 'choices' => 'online_event_types', 'required' => true, 'default' => 'Consultation'],
            'Title' => ['Titre', 'text'],
            'Description' => ['Détail', 'textarea'],
            'WeightKg' => ['Poids (kg)', 'number'],
            'NextDueDate' => ['Prochaine échéance (rappel)', 'date'],
            'Veterinarian' => ['Vétérinaire ou intervenant', 'text'],
            'AttachmentPath' => ['Pièce jointe', 'file'],
        ]],
        'Treatments' => ['title' => 'Traitement', 'animal' => true, 'fields' => [
            'MedicationName' => ['Médicament', 'text', 'required' => true],
            'Pathology' => ['Pour soigner', 'text'],
            'Dosage' => ['Dose', 'text', 'help' => 'ex. ½ comprimé, 0,5 ml'],
            'Route' => ['Voie', 'select', 'options' => online_alphabetical(['Orale', 'Cutanée', 'Injection', 'Ophtalmique', 'Auriculaire', 'Autre']), 'default' => 'Orale'],
            'FrequencyValue' => ['Tous les', 'int', 'default' => 1],
            'FrequencyUnit' => ['Unité', 'select', 'options' => ['heure(s)', 'jour(s)', 'semaine(s)', 'mois'], 'default' => 'jour(s)'],
            'TimesOfDay' => ['Moments de la journée', 'text', 'help' => 'ex. 8 h, 20 h'],
            'StartDate' => ['Début', 'date', 'required' => true, 'default' => 'today'],
            'EndDate' => ['Fin', 'date'],
            'NextAdministration' => ['Prochaine prise', 'datetime', 'default' => 'now'],
            'StockItemId' => ['Produit du stock', 'ref', 'ref' => 'StockItems'],
            'DoseQuantity' => ['Quantité décomptée du stock à chaque prise', 'number'],
            'Prescriber' => ['Prescripteur', 'text'],
            'Status' => ['État', 'select', 'options' => ['En cours', 'Suspendu', 'Terminé'], 'default' => 'En cours'],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'AnimalDocuments' => ['title' => 'Document', 'animal' => true, 'fields' => [
            'DocumentDate' => ['Date', 'date', 'required' => true, 'default' => 'today'],
            'Type' => ['Type', 'select', 'options' => online_alphabetical(['Ordonnance', 'Facture', 'Analyse', 'Compte-rendu', 'Certificat', 'Photo médicale', 'Autre']),
                'required' => true, 'default' => 'Ordonnance'],
            'Title' => ['Titre', 'text', 'required' => true],
            'FilePath' => ['Fichier', 'file', 'required' => true],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'AnimalPhotos' => ['title' => 'Photo', 'animal' => true, 'fields' => [
            'FilePath' => ['Photo', 'file', 'accept' => 'image/*', 'required' => true],
            'PhotoDate' => ['Date', 'date', 'required' => true, 'default' => 'today'],
            'Caption' => ['Légende', 'text'],
        ]],
        'Placements' => ['title' => 'Étape du parcours', 'animal' => true, 'fields' => [
            'Kind' => ['Type', 'select', 'options' => ['Arrivée', 'Famille d’accueil', 'Famille', 'Association / refuge', 'Adoption', 'Retour', 'Autre'],
                'required' => true, 'default' => 'Famille d’accueil'],
            'StartDate' => ['Du', 'date', 'required' => true, 'default' => 'today'],
            'EndDate' => ['Au', 'date', 'help' => 'Vide : en cours.'],
            'ContactId' => ['Chez', 'ref', 'ref' => 'Contacts'],
            'Location' => ['Lieu', 'text'],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'FollowUps' => ['title' => 'Suivi demandé', 'animal' => true, 'fields' => [
            'Type' => ['Suivi', 'select', 'options' => ['Pesée', 'Repas et eau', 'Photo', 'Observation'], 'required' => true, 'default' => 'Pesée'],
            'IntervalValue' => ['Tous les', 'int', 'default' => 1],
            'IntervalUnit' => ['Unité', 'select', 'options' => ['jour(s)', 'semaine(s)'], 'default' => 'semaine(s)'],
            'StartDate' => ['Du', 'date', 'required' => true, 'default' => 'today'],
            'EndDate' => ['Au', 'date', 'required' => true, 'default' => '+1 month'],
            'RequestedBy' => ['Demandé par', 'text'],
            'Notes' => ['Consignes', 'textarea'],
        ]],
        'FoodLogs' => ['title' => 'Repas ou eau', 'animal' => true, 'fields' => [
            'LogDate' => ['Date', 'date', 'required' => true, 'default' => 'today'],
            'Kind' => ['Quoi', 'select', 'options' => ['Repas', 'Eau'], 'required' => true, 'default' => 'Repas'],
            'FoodType' => ['Type d’aliment', 'text'],
            'Brand' => ['Marque', 'text'],
            'Product' => ['Produit', 'text'],
            'Quantity' => ['Quantité donnée (g ou ml)', 'number'],
            'Leftover' => ['Reste dans la gamelle', 'number'],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'AnimalExpenses' => ['title' => 'Dépense', 'animal' => true, 'finance' => true, 'fields' => [
            'ExpenseDate' => ['Date', 'date', 'required' => true, 'default' => 'today'],
            'Category' => ['Catégorie', 'select', 'choices' => 'online_expense_categories', 'required' => true, 'default' => 'Vétérinaire'],
            'Description' => ['Description', 'text', 'required' => true],
            'Amount' => ['Montant (€)', 'number', 'required' => true],
            'ReimbursedAmount' => ['Remboursé (€)', 'number', 'default' => 0],
            'PaidBy' => ['Payé par', 'select', 'options' => ['Famille', 'Association', 'Autre'], 'default' => 'Association'],
            'InsuranceStatus' => ['Assurance', 'select', 'options' => ['', 'À envoyer', 'Envoyée', 'Remboursée', 'Refusée'], 'default' => ''],
            'InsuranceRefund' => ['Remboursé par l’assurance (€)', 'number', 'default' => 0],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'Adoptions' => ['title' => 'Adoption', 'animal' => true, 'finance' => true, 'fields' => [
            'AdoptionDate' => ['Date', 'date', 'required' => true, 'default' => 'today'],
            'ContactId' => ['Adoptant', 'ref', 'ref' => 'Contacts'],
            'Fee' => ['Frais d’adoption (€)', 'number', 'default' => 0],
            'FeePaid' => ['Frais payés', 'bool'],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'Contacts' => ['title' => 'Contact', 'animal' => false, 'fields' => [
            'Name' => ['Nom', 'text', 'required' => true],
            'Role' => ['Rôle', 'select', 'options' => online_alphabetical(['Vétérinaire', 'Famille d’accueil', 'Adoptant', 'Association', 'Pension / écurie',
                'Maréchal-ferrant', 'Toiletteur', 'Éleveur', 'Pet-sitter', 'Autre']), 'default' => 'Autre'],
            'Phone' => ['Téléphone', 'text'],
            'Email' => ['E-mail', 'text', 'help' => 'Pour une famille d’accueil : l’adresse de son compte du site, pour qu’elle voie ses animaux.'],
            'Address' => ['Adresse', 'textarea'],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'StockItems' => ['title' => 'Article du stock', 'animal' => false, 'fields' => [
            'Name' => ['Nom', 'text', 'required' => true],
            'Category' => ['Catégorie', 'select', 'options' => ['Médicament', 'Antiparasitaire', 'Soin et hygiène', 'Nourriture', 'Litière', 'Matériel', 'À classer'],
                'default' => 'À classer'],
            'Quantity' => ['Quantité', 'number', 'required' => true, 'default' => 0],
            'Unit' => ['Unité', 'select', 'options' => ['comprimé(s)', 'dose(s)', 'ml', 'g', 'kg', 'sachet(s)', 'boîte(s)', 'pipette(s)', 'sac(s)', 'unité(s)'],
                'default' => 'unité(s)'],
            'MinimumQuantity' => ['Alerte sous', 'number', 'default' => 0],
            'ExpiryDate' => ['Péremption', 'date'],
            'Gtin' => ['Code de la boîte (GTIN)', 'text'],
            'LotNumber' => ['Numéro de lot', 'text'],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'RecurringTasks' => ['title' => 'Frais ou entretien', 'animal' => false, 'fields' => [
            'Kind' => ['Type', 'select', 'options' => ['Frais', 'Entretien'], 'required' => true, 'default' => 'Entretien'],
            'Title' => ['Intitulé', 'text', 'required' => true],
            'AnimalId' => ['Animal', 'ref', 'ref' => 'Animals', 'help' => 'Obligatoire pour des frais (la dépense lui est rattachée).'],
            'Equipment' => ['Équipement', 'text', 'help' => 'Cage, box, aquarium…'],
            'IntervalValue' => ['Tous les', 'int', 'default' => 1],
            'IntervalUnit' => ['Unité', 'select', 'options' => ['jour(s)', 'semaine(s)', 'mois', 'an(s)'], 'default' => 'mois'],
            'NextDueDate' => ['Prochaine échéance', 'date', 'required' => true, 'default' => 'today'],
            'Category' => ['Catégorie de la dépense', 'select', 'choices' => 'online_expense_categories', 'default' => 'Pension', 'finance' => true],
            'Amount' => ['Montant (€)', 'number', 'finance' => true],
            'PaidBy' => ['Payé par', 'select', 'options' => ['Famille', 'Association', 'Autre'], 'default' => 'Association', 'finance' => true],
            'ContactId' => ['Prestataire', 'ref', 'ref' => 'Contacts'],
            'Active' => ['Active', 'bool', 'default' => 1],
            'Notes' => ['Notes', 'textarea'],
        ]],
        'EggCounts' => ['title' => 'Ponte', 'animal' => false, 'fields' => [
            'CountDate' => ['Date', 'date', 'required' => true, 'default' => 'today'],
            'CoopName' => ['Poulailler', 'text'],
            'AnimalId' => ['Poule', 'ref', 'ref' => 'Animals'],
            'Eggs' => ['Œufs', 'int', 'required' => true, 'default' => 0],
            'Notes' => ['Notes', 'textarea'],
        ]],
    ];
    return $forms;
}

/** Colonnes de date de création et de mise à jour posées par le logiciel (« aaaa-mm-jj hh:mm:ss », heure locale). */
const ONLINE_CREATED_COLUMN = ['Animals' => 'CreatedAt', 'AnimalEvents' => 'CreatedAt', 'Treatments' => 'CreatedAt', 'AnimalDocuments' => 'CreatedAt',
    'AnimalPhotos' => 'CreatedAt', 'Placements' => 'CreatedAt', 'FollowUps' => 'CreatedAt', 'FoodLogs' => 'CreatedAt', 'AnimalExpenses' => 'CreatedAt',
    'Adoptions' => 'CreatedAt', 'Contacts' => 'CreatedAt', 'RecurringTasks' => 'CreatedAt', 'EggCounts' => 'CreatedAt'];
const ONLINE_UPDATED_COLUMN = ['Animals' => 'UpdatedAt', 'StockItems' => 'UpdatedAt'];
