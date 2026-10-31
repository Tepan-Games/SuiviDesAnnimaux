<?php /** @var array $organisation @var array $state @var array $animal @var array $animals @var array $contacts @var array $events
 * @var array $treatments @var array $placements @var array $documents @var array $photos @var array $followUps @var array $weights
 * @var bool $finance @var array $expenses @var array $adoptions */
$o = (int)$organisation['id'];
$photo = online_file_name($animal['PhotoPath'] ?? null);
$date = fn($value) => $value ? format_date((string)$value) : '';
$contactName = fn($id) => isset($contacts[(string)$id]) ? (string)($contacts[(string)$id]['Name'] ?? '') : '';
$animalLink = function ($id) use ($animals, $o) {
    if (!$id || !isset($animals[(string)$id])) {
        return '';
    }
    return '<a href="/application/animal?o=' . $o . '&amp;a=' . e(rawurlencode((string)$id)) . '">' . e($animals[(string)$id]['Name'] ?? '') . '</a>';
};
/** Ligne « libellé : valeur » d'une liste de définitions, seulement si la valeur est renseignée. */
$field = function (string $label, mixed $value, bool $html = false): string {
    if ($value === null || $value === '' || $value === 'Non renseigné') {
        return '';
    }
    return '<dt>' . e($label) . '</dt><dd>' . ($html ? $value : nl2br(e($value))) . '</dd>';
};
$sterilized = match ($animal['Sterilized'] ?? null) { 1, '1' => 'Oui', 0, '0' => 'Non', default => '' };
$birth = $date($animal['BirthDate'] ?? null);
if ($birth !== '') {
    $birth .= (!empty($animal['BirthDateEstimated']) ? ' (estimée)' : '') . ' · ' . online_age($animal['BirthDate']);
}
$lastWeight = $weights[0] ?? null;
$currentTreatments = array_filter($treatments, fn($t) => ($t['Status'] ?? '') === 'En cours');
$otherTreatments = array_filter($treatments, fn($t) => ($t['Status'] ?? '') !== 'En cours');
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <div><span class="pastille"><?= e($organisation['name']) ?></span><h1><?= e($animal['Name'] ?? '') ?></h1></div>
            <a class="bouton petit secondaire" href="/application/animaux?o=<?= $o ?>">← Tous les animaux</a>
        </div>
        <?php require __DIR__ . '/_header.php' ?>

        <div class="fiche-entete carte">
            <?php if ($photo): ?><a href="<?= e(online_file_url($o, $photo)) ?>"><img src="<?= e(online_file_url($o, $photo)) ?>" alt="Photo de <?= e($animal['Name'] ?? '') ?>"></a><?php endif ?>
            <div>
                <p><strong><?= e(implode(' · ', array_filter([$animal['Species'] ?? '', $animal['Sex'] ?? '', $animal['Breed'] ?? '']))) ?></strong></p>
                <p>
                    <span class="pastille <?= ($animal['Status'] ?? 'Présent') === 'Présent' ? '' : 'gris' ?>"><?= e($animal['Status'] ?? 'Présent') ?></span>
                    <?php if (!empty($animal['AvailableForAdoption'])): ?><span class="pastille orange">À l’adoption</span><?php endif ?>
                    <?php if (($animal['Allergies'] ?? '') !== ''): ?><span class="pastille rouge">Allergies</span><?php endif ?>
                    <?php if ($currentTreatments): ?><span class="pastille orange"><?= count($currentTreatments) ?> traitement<?= count($currentTreatments) > 1 ? 's' : '' ?> en cours</span><?php endif ?>
                </p>
                <?php if ($lastWeight): ?>
                    <p class="petit">Dernier poids : <strong><?= e(online_number($lastWeight['WeightKg'], 3)) ?> kg</strong> le <?= e($date($lastWeight['EventDate'])) ?>
                        <?php if (($animal['TargetWeightKg'] ?? null) !== null): ?><span class="discret">· objectif <?= e(online_number($animal['TargetWeightKg'], 3)) ?> kg</span><?php endif ?></p>
                <?php endif ?>
            </div>
        </div>

        <div class="grille fiche">
            <div class="carte">
                <h2>Identité</h2>
                <dl class="champs">
                    <?= $field('Naissance', $birth) ?>
                    <?= $field('Puce', $animal['ChipNumber'] ?? '') ?>
                    <?= $field('Tatouage', $animal['TattooNumber'] ?? '') ?>
                    <?= $field('UELN', $animal['UelnNumber'] ?? '') ?>
                    <?= $field('Robe', $animal['CoatColor'] ?? '') ?>
                    <?= $field('Taille', ($animal['HeightCm'] ?? null) !== null ? $animal['HeightCm'] . ' cm' : '') ?>
                    <?= $field('Stérilisé', $sterilized) ?>
                    <?= $field('Arrivée', $date($animal['ArrivalDate'] ?? null)) ?>
                    <?= $field('Origine', $animal['Origin'] ?? '') ?>
                    <?= $field('Mère', $animalLink($animal['MotherId'] ?? null), true) ?>
                    <?= $field('Père', $animalLink($animal['FatherId'] ?? null), true) ?>
                    <?= $field('Portée', $animal['LitterName'] ?? '') ?>
                    <?= $field('Livre généalogique', trim(($animal['PedigreeRegistry'] ?? '') . ' ' . ($animal['PedigreeNumber'] ?? ''))) ?>
                    <?= $field('Éleveur', $animal['BreederName'] ?? '') ?>
                </dl>
            </div>
            <div class="carte">
                <h2>Santé</h2>
                <dl class="champs">
                    <?= $field('Allergies', $animal['Allergies'] ?? '') ?>
                    <?= $field('Vétérinaire', $contactName($animal['VeterinarianContactId'] ?? null)) ?>
                    <?= $field('Assurance', trim(($animal['InsuranceName'] ?? '') . (($animal['InsuranceNumber'] ?? '') !== '' ? ' — n° ' . $animal['InsuranceNumber'] : ''))) ?>
                </dl>
                <?php $diet = implode('', [
                    $field('Régime', $animal['DietType'] ?? ''),
                    $field('Aliment', trim(($animal['DietBrand'] ?? '') . ' ' . ($animal['DietName'] ?? ''))),
                    $field('Quantité par jour', ($animal['DailyFoodQuantityGrams'] ?? null) !== null ? online_number($animal['DailyFoodQuantityGrams']) . ' g' : ''),
                    $field('Repas par jour', $animal['MealsPerDay'] ?? ''),
                    $field('Eau par jour', ($animal['DailyWaterMl'] ?? null) !== null ? online_number($animal['DailyWaterMl']) . ' ml' : ''),
                    $field('Consignes', $animal['FeedingNotes'] ?? ''),
                ]) ?>
                <?php if ($diet !== ''): ?><h3>Alimentation</h3><dl class="champs"><?= $diet ?></dl><?php endif ?>
            </div>
            <?php if (($animal['Notes'] ?? '') !== '' || ($animal['CharacterNotes'] ?? '') !== '' || ($animal['CompatibilityNotes'] ?? '') !== ''): ?>
                <div class="carte">
                    <h2>Notes</h2>
                    <dl class="champs">
                        <?= $field('Caractère', $animal['CharacterNotes'] ?? '') ?>
                        <?= $field('Ententes', $animal['CompatibilityNotes'] ?? '') ?>
                        <?= $field('Notes', $animal['Notes'] ?? '') ?>
                    </dl>
                </div>
            <?php endif ?>
        </div>

        <?php if ($followUps): ?>
            <h2 class="section-fiche">Suivis demandés</h2>
            <div class="defilement"><table class="tableau">
                <tr><th>Suivi</th><th>Fréquence</th><th>Période</th><th>Demandé par</th><th>Notes</th></tr>
                <?php foreach ($followUps as $followUp): ?>
                    <tr><td><?= e($followUp['Type'] ?? '') ?></td>
                        <td>tous les <?= (int)($followUp['IntervalValue'] ?? 1) ?> <?= e($followUp['IntervalUnit'] ?? '') ?></td>
                        <td>du <?= e($date($followUp['StartDate'] ?? null)) ?> au <?= e($date($followUp['EndDate'] ?? null)) ?></td>
                        <td><?= e($followUp['RequestedBy'] ?? '') ?></td><td><?= nl2br(e($followUp['Notes'] ?? '')) ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>

        <h2 class="section-fiche">Traitements</h2>
        <?php if (!$treatments): ?>
            <p class="discret">Aucun traitement.</p>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Médicament</th><th>Dose</th><th>Fréquence</th><th>Période</th><th>État</th><th>Prescripteur</th></tr>
                <?php foreach ([...$currentTreatments, ...$otherTreatments] as $treatment): ?>
                    <tr><td><strong><?= e($treatment['MedicationName'] ?? '') ?></strong><?= ($treatment['Notes'] ?? '') !== '' ? '<br><span class="discret petit">' . nl2br(e($treatment['Notes'])) . '</span>' : '' ?></td>
                        <td><?= e(trim(($treatment['Dosage'] ?? '') . ' ' . (($treatment['Route'] ?? '') !== '' ? '(' . $treatment['Route'] . ')' : ''))) ?></td>
                        <td>Tous les <?= (int)($treatment['FrequencyValue'] ?? 1) ?> <?= e($treatment['FrequencyUnit'] ?? '') ?><?= ($treatment['TimesOfDay'] ?? '') !== '' ? '<br><span class="discret petit">' . e($treatment['TimesOfDay']) . '</span>' : '' ?></td>
                        <td>du <?= e($date($treatment['StartDate'] ?? null)) ?><?= ($treatment['EndDate'] ?? null) ? ' au ' . e($date($treatment['EndDate'])) : '' ?></td>
                        <td><span class="pastille <?= ($treatment['Status'] ?? '') === 'En cours' ? 'orange' : 'gris' ?>"><?= e($treatment['Status'] ?? '') ?></span></td>
                        <td><?= e($treatment['Prescriber'] ?? '') ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>

        <h2 class="section-fiche">Journal</h2>
        <?php if (!$events): ?>
            <p class="discret">Aucun événement (soins, vaccins, pesées, visites).</p>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Date</th><th>Type</th><th>Détail</th><th>Poids</th><th>Prochaine échéance</th></tr>
                <?php foreach ($events as $event):
                    $attachment = online_file_name($event['AttachmentPath'] ?? null); ?>
                    <tr><td><?= e($date($event['EventDate'] ?? null)) ?></td>
                        <td><?= e($event['Type'] ?? '') ?></td>
                        <td><?= ($event['Title'] ?? '') !== '' ? '<strong>' . e($event['Title']) . '</strong><br>' : '' ?><?= nl2br(e($event['Description'] ?? '')) ?>
                            <?= ($event['Veterinarian'] ?? '') !== '' ? '<br><span class="discret petit">' . e($event['Veterinarian']) . '</span>' : '' ?>
                            <?= $attachment ? '<br><a class="petit" href="' . e(online_file_url($o, $attachment)) . '">Pièce jointe</a>' : '' ?></td>
                        <td><?= ($event['WeightKg'] ?? null) !== null ? e(online_number($event['WeightKg'], 3)) . ' kg' : '' ?></td>
                        <td><?= e($date($event['NextDueDate'] ?? null)) ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>

        <?php if ($placements): ?>
            <h2 class="section-fiche">Parcours</h2>
            <div class="defilement"><table class="tableau">
                <tr><th>Période</th><th>Type</th><th>Chez</th><th>Notes</th></tr>
                <?php foreach ($placements as $placement): ?>
                    <tr><td>du <?= e($date($placement['StartDate'] ?? null)) ?><?= ($placement['EndDate'] ?? null) ? ' au ' . e($date($placement['EndDate'])) : '' ?>
                            <?= online_is_current($placement) ? ' <span class="pastille">en cours</span>' : '' ?></td>
                        <td><?= e($placement['Kind'] ?? '') ?></td>
                        <td><?= e(trim($contactName($placement['ContactId'] ?? null) . ' ' . ($placement['Location'] ?? ''))) ?></td>
                        <td><?= nl2br(e($placement['Notes'] ?? '')) ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>

        <?php if ($photos): ?>
            <h2 class="section-fiche">Photos</h2>
            <div class="galerie">
                <?php foreach ($photos as $item): $name = online_file_name($item['FilePath']); ?>
                    <figure><a href="<?= e(online_file_url($o, $name)) ?>"><img src="<?= e(online_file_url($o, $name)) ?>" alt="<?= e($item['Caption'] ?? '') ?>" loading="lazy"></a>
                        <figcaption class="petit discret"><?= e($date($item['PhotoDate'] ?? null)) ?><?= ($item['Caption'] ?? '') !== '' ? ' · ' . e($item['Caption']) : '' ?></figcaption></figure>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <?php if ($documents): ?>
            <h2 class="section-fiche">Documents</h2>
            <div class="defilement"><table class="tableau">
                <tr><th>Date</th><th>Type</th><th>Titre</th><th>Notes</th></tr>
                <?php foreach ($documents as $document): $name = online_file_name($document['FilePath'] ?? null); ?>
                    <tr><td><?= e($date($document['DocumentDate'] ?? null)) ?></td><td><?= e($document['Type'] ?? '') ?></td>
                        <td><?= $name ? '<a href="' . e(online_file_url($o, $name)) . '">' . e($document['Title'] ?? 'Document') . '</a>' : e($document['Title'] ?? '') ?></td>
                        <td><?= nl2br(e($document['Notes'] ?? '')) ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>

        <?php if ($finance && ($expenses || $adoptions)): ?>
            <h2 class="section-fiche">Dépenses et adoption</h2>
            <?php if ($expenses): ?>
                <div class="defilement"><table class="tableau">
                    <tr><th>Date</th><th>Catégorie</th><th>Description</th><th>Montant</th><th>Remboursé</th><th>Payé par</th></tr>
                    <?php foreach ($expenses as $expense): ?>
                        <tr><td><?= e($date($expense['ExpenseDate'] ?? null)) ?></td><td><?= e($expense['Category'] ?? '') ?></td>
                            <td><?= e($expense['Description'] ?? '') ?></td><td><?= e(online_money($expense['Amount'] ?? 0)) ?></td>
                            <td><?= (float)($expense['ReimbursedAmount'] ?? 0) + (float)($expense['InsuranceRefund'] ?? 0) > 0
                                    ? e(online_money((float)($expense['ReimbursedAmount'] ?? 0) + (float)($expense['InsuranceRefund'] ?? 0))) : '' ?></td>
                            <td><?= e($expense['PaidBy'] ?? '') ?></td></tr>
                    <?php endforeach ?>
                    <tr><td colspan="3"><strong>Total</strong></td><td><strong><?= e(online_money(array_sum(array_map(fn($x) => (float)($x['Amount'] ?? 0), $expenses)))) ?></strong></td><td colspan="2"></td></tr>
                </table></div>
            <?php endif ?>
            <?php foreach ($adoptions as $adoption): ?>
                <p>Adoption le <?= e($date($adoption['AdoptionDate'] ?? null)) ?><?= $contactName($adoption['ContactId'] ?? null) !== '' ? ' par ' . e($contactName($adoption['ContactId'])) : '' ?>
                    · frais <?= e(online_money($adoption['Fee'] ?? 0)) ?> <span class="pastille <?= !empty($adoption['FeePaid']) ? '' : 'orange' ?>"><?= !empty($adoption['FeePaid']) ? 'payés' : 'à payer' ?></span></p>
            <?php endforeach ?>
        <?php endif ?>
    </div>
</section>
