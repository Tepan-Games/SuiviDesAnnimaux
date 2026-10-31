<?php /** @var array $organisation @var array $state @var array $animal @var array $animals @var array $contacts @var array $events
 * @var array $treatments @var array $placements @var array $documents @var array $photos @var array $followUps @var array $weights
 * @var bool $finance @var array $expenses @var array $adoptions @var array $foodLogs @var array $administrations @var array $can @var bool $canDelete */
$o = (int)$organisation['id'];
$tab = 'animaux';
$aid = e(rawurlencode((string)$animal['_id']));
/** Bouton « Ajouter » d'une section, si la personne peut écrire dans cette table. */
$add = fn(string $table, string $label, string $extra = '') => $can[$table] ?? false
    ? '<a class="bouton petit" href="/application/fiche?o=' . $o . '&amp;t=' . $table . '&amp;a=' . $aid . $extra . '">' . e($label) . '</a>' : '';
/** Lien « Modifier » d'une ligne. */
$edit = fn(string $table, array $row) => $can[$table] ?? false
    ? '<a class="petit" href="/application/fiche?o=' . $o . '&amp;t=' . $table . '&amp;id=' . e(rawurlencode((string)$row['_id'])) . '">Modifier</a>' : '';
/** Titre de section avec son bouton. */
$heading = fn(string $title, string $table, string $button) => '<div class="titre-section section-fiche" id="' . strtolower($table) . '"><h2>' . e($title) . '</h2>'
    . ($button !== '' && trim($button) !== '' ? '<div class="boutons">' . $button . '</div>' : '') . '</div>';
$treatmentNames = array_column(array_map(fn($t) => [$t['_id'], $t['MedicationName'] ?? ''], $treatments), 1, 0);
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
            <div class="boutons">
                <?php if ($can['Animals']): ?><a class="bouton petit" href="/application/fiche?o=<?= $o ?>&amp;t=Animals&amp;id=<?= $aid ?>">Modifier la fiche</a><?php endif ?>
                <a class="bouton petit secondaire" href="/application/animaux?o=<?= $o ?>">← Tous les animaux</a>
            </div>
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

        <?php if ($followUps || $can['FollowUps']): ?>
            <?= $heading('Suivis demandés', 'FollowUps', $add('FollowUps', 'Demander un suivi')) ?>
            <?php if (!$followUps): ?><p class="discret">Aucun suivi en cours (pesée, repas, photo ou observation à noter régulièrement).</p><?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Suivi</th><th>Fréquence</th><th>Période</th><th>Avancement</th><th>Demandé par</th><th>Notes</th><th></th></tr>
                <?php foreach ($followUps as $followUp): ?>
                    <tr><td><?= e($followUp['Type'] ?? '') ?></td>
                        <td><?= e(online_follow_up_interval($followUp)) ?></td>
                        <td>du <?= e($date($followUp['StartDate'] ?? null)) ?> au <?= e($date($followUp['EndDate'] ?? null)) ?></td>
                        <td class="petit"><?= e(online_follow_up_status($state, $followUp)['text']) ?></td>
                        <td><?= e($followUp['RequestedBy'] ?? '') ?></td><td><?= nl2br(e($followUp['Notes'] ?? '')) ?></td><td><?= $edit('FollowUps', $followUp) ?></td></tr>
                <?php endforeach ?>
            </table></div>
            <?php endif ?>
        <?php endif ?>

        <?= $heading('Traitements', 'Treatments', $add('Treatments', 'Ajouter un traitement')) ?>
        <?php if (!$treatments): ?>
            <p class="discret">Aucun traitement.</p>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Médicament</th><th>Dose</th><th>Fréquence</th><th>Période</th><th>État</th><th>Prochaine prise</th><th></th></tr>
                <?php foreach ([...$currentTreatments, ...$otherTreatments] as $treatment): ?>
                    <tr><td><strong><?= e($treatment['MedicationName'] ?? '') ?></strong><?= ($treatment['Notes'] ?? '') !== '' ? '<br><span class="discret petit">' . nl2br(e($treatment['Notes'])) . '</span>' : '' ?></td>
                        <td><?= e(trim(($treatment['Dosage'] ?? '') . ' ' . (($treatment['Route'] ?? '') !== '' ? '(' . $treatment['Route'] . ')' : ''))) ?></td>
                        <td>Tous les <?= (int)($treatment['FrequencyValue'] ?? 1) ?> <?= e($treatment['FrequencyUnit'] ?? '') ?><?= ($treatment['TimesOfDay'] ?? '') !== '' ? '<br><span class="discret petit">' . e($treatment['TimesOfDay']) . '</span>' : '' ?></td>
                        <td>du <?= e($date($treatment['StartDate'] ?? null)) ?><?= ($treatment['EndDate'] ?? null) ? ' au ' . e($date($treatment['EndDate'])) : '' ?></td>
                        <td><span class="pastille <?= ($treatment['Status'] ?? '') === 'En cours' ? 'orange' : 'gris' ?>"><?= e($treatment['Status'] ?? '') ?></span>
                            <?= ($treatment['Prescriber'] ?? '') !== '' ? '<br><span class="discret petit">' . e($treatment['Prescriber']) . '</span>' : '' ?></td>
                        <td><?php if (($treatment['Status'] ?? '') === 'En cours' && ($treatment['NextAdministration'] ?? null)): ?>
                                <?= e(format_date((string)$treatment['NextAdministration'], true)) ?>
                                <?php if ($can['Treatments']): ?>
                                    <form method="post" action="/application/prise?o=<?= $o ?>&amp;id=<?= e(rawurlencode((string)$treatment['_id'])) ?>" class="boutons-ligne">
                                        <?= csrf_field() ?>
                                        <button class="bouton petit" name="statut" value="Donné">Donné</button>
                                        <button class="bouton petit secondaire" name="statut" value="Oublié">Oublié</button>
                                    </form>
                                <?php endif ?>
                            <?php endif ?></td>
                        <td><?= $edit('Treatments', $treatment) ?></td></tr>
                <?php endforeach ?>
            </table></div>
            <?php if ($administrations): ?>
                <details><summary>Dernières prises notées</summary>
                    <ul class="petit">
                        <?php foreach ($administrations as $administration): ?>
                            <li><?= e(format_date((string)($administration['RecordedAt'] ?? $administration['ScheduledAt'] ?? ''), true)) ?> ·
                                <?= e($treatmentNames[(string)($administration['TreatmentId'] ?? '')] ?? 'Traitement') ?> ·
                                <span class="pastille <?= ($administration['Status'] ?? '') === 'Donné' ? '' : 'orange' ?>"><?= e($administration['Status'] ?? '') ?></span></li>
                        <?php endforeach ?>
                    </ul>
                </details>
            <?php endif ?>
        <?php endif ?>

        <?= $heading('Journal', 'AnimalEvents', $add('AnimalEvents', 'Ajouter un événement') . ' ' . $add('AnimalEvents', 'Noter une pesée', '&amp;type=Pes%C3%A9e')) ?>
        <?php if (!$events): ?>
            <p class="discret">Aucun événement (soins, vaccins, pesées, visites).</p>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Date</th><th>Type</th><th>Détail</th><th>Poids</th><th>Prochaine échéance</th><th></th></tr>
                <?php foreach ($events as $event):
                    $attachment = online_file_name($event['AttachmentPath'] ?? null); ?>
                    <tr><td><?= e($date($event['EventDate'] ?? null)) ?></td>
                        <td><?= e($event['Type'] ?? '') ?></td>
                        <td><?= ($event['Title'] ?? '') !== '' ? '<strong>' . e($event['Title']) . '</strong><br>' : '' ?><?= nl2br(e($event['Description'] ?? '')) ?>
                            <?= ($event['Veterinarian'] ?? '') !== '' ? '<br><span class="discret petit">' . e($event['Veterinarian']) . '</span>' : '' ?>
                            <?= $attachment ? '<br><a class="petit" href="' . e(online_file_url($o, $attachment)) . '">Pièce jointe</a>' : '' ?></td>
                        <td><?= ($event['WeightKg'] ?? null) !== null ? e(online_number($event['WeightKg'], 3)) . ' kg' : '' ?></td>
                        <td><?= e($date($event['NextDueDate'] ?? null)) ?></td><td><?= $edit('AnimalEvents', $event) ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>

        <?php if ($foodLogs || $can['FoodLogs'] && (!empty($animal['DietDailyTracking']) || ($animal['DietType'] ?? 'Non renseigné') !== 'Non renseigné')): ?>
            <?= $heading('Repas et eau (14 derniers jours)', 'FoodLogs', $add('FoodLogs', 'Noter un repas ou de l’eau')) ?>
            <?php if (!$foodLogs): ?><p class="discret">Rien de noté ces derniers jours.</p><?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Date</th><th>Quoi</th><th>Aliment</th><th>Donné</th><th>Reste</th><th>Notes</th><th></th></tr>
                <?php foreach ($foodLogs as $log): $unit = ($log['Kind'] ?? '') === 'Eau' ? ' ml' : ' g'; ?>
                    <tr><td><?= e($date($log['LogDate'] ?? null)) ?></td><td><?= e($log['Kind'] ?? '') ?></td>
                        <td><?= e(trim(($log['FoodType'] ?? '') . ' ' . ($log['Brand'] ?? '') . ' ' . ($log['Product'] ?? ''))) ?></td>
                        <td><?= ($log['Quantity'] ?? null) !== null ? e(online_number($log['Quantity']) . $unit) : '' ?></td>
                        <td><?= ($log['Leftover'] ?? null) !== null ? e(online_number($log['Leftover']) . $unit) : '' ?></td>
                        <td class="petit"><?= nl2br(e($log['Notes'] ?? '')) ?></td><td><?= $edit('FoodLogs', $log) ?></td></tr>
                <?php endforeach ?>
            </table></div>
            <?php endif ?>
        <?php endif ?>

        <?php if ($placements || $can['Placements']): ?>
            <?= $heading('Parcours', 'Placements', $add('Placements', 'Ajouter une étape')) ?>
            <?php if (!$placements): ?><p class="discret">Aucune étape (arrivée, famille d’accueil, adoption…).</p><?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Période</th><th>Type</th><th>Chez</th><th>Notes</th><th></th></tr>
                <?php foreach ($placements as $placement): ?>
                    <tr><td>du <?= e($date($placement['StartDate'] ?? null)) ?><?= ($placement['EndDate'] ?? null) ? ' au ' . e($date($placement['EndDate'])) : '' ?>
                            <?= online_is_current($placement) ? ' <span class="pastille">en cours</span>' : '' ?></td>
                        <td><?= e($placement['Kind'] ?? '') ?></td>
                        <td><?= e(trim($contactName($placement['ContactId'] ?? null) . ' ' . ($placement['Location'] ?? ''))) ?></td>
                        <td><?= nl2br(e($placement['Notes'] ?? '')) ?></td><td><?= $edit('Placements', $placement) ?></td></tr>
                <?php endforeach ?>
            </table></div>
            <?php endif ?>
        <?php endif ?>

        <?php if ($photos || $can['AnimalPhotos']): ?>
            <?= $heading('Photos', 'AnimalPhotos', $add('AnimalPhotos', 'Ajouter une photo')) ?>
            <?php if (!$photos): ?><p class="discret">Aucune photo.</p><?php endif ?>
            <div class="galerie">
                <?php foreach ($photos as $item): $name = online_file_name($item['FilePath']); ?>
                    <figure><a href="<?= e(online_file_url($o, $name)) ?>"><img src="<?= e(online_file_url($o, $name)) ?>" alt="<?= e($item['Caption'] ?? '') ?>" loading="lazy"></a>
                        <figcaption class="petit discret"><?= e($date($item['PhotoDate'] ?? null)) ?><?= ($item['Caption'] ?? '') !== '' ? ' · ' . e($item['Caption']) : '' ?>
                            <?= $edit('AnimalPhotos', $item) !== '' ? ' · ' . $edit('AnimalPhotos', $item) : '' ?></figcaption></figure>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <?php if ($documents || $can['AnimalDocuments']): ?>
            <?= $heading('Documents', 'AnimalDocuments', $add('AnimalDocuments', 'Ajouter un document')) ?>
            <?php if (!$documents): ?><p class="discret">Aucun document (ordonnances, factures, analyses…).</p><?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Date</th><th>Type</th><th>Titre</th><th>Notes</th><th></th></tr>
                <?php foreach ($documents as $document): $name = online_file_name($document['FilePath'] ?? null); ?>
                    <tr><td><?= e($date($document['DocumentDate'] ?? null)) ?></td><td><?= e($document['Type'] ?? '') ?></td>
                        <td><?= $name ? '<a href="' . e(online_file_url($o, $name)) . '">' . e($document['Title'] ?? 'Document') . '</a>' : e($document['Title'] ?? '') ?></td>
                        <td><?= nl2br(e($document['Notes'] ?? '')) ?></td><td><?= $edit('AnimalDocuments', $document) ?></td></tr>
                <?php endforeach ?>
            </table></div>
            <?php endif ?>
        <?php endif ?>

        <?php if ($finance): ?>
            <?= $heading('Dépenses et adoption', 'AnimalExpenses', $add('AnimalExpenses', 'Ajouter une dépense') . ' ' . $add('Adoptions', 'Noter une adoption')) ?>
            <?php if (!$expenses && !$adoptions): ?><p class="discret">Aucune dépense.</p><?php endif ?>
            <?php if ($expenses): ?>
                <div class="defilement"><table class="tableau">
                    <tr><th>Date</th><th>Catégorie</th><th>Description</th><th>Montant</th><th>Remboursé</th><th>Payé par</th><th></th></tr>
                    <?php foreach ($expenses as $expense): ?>
                        <tr><td><?= e($date($expense['ExpenseDate'] ?? null)) ?></td><td><?= e($expense['Category'] ?? '') ?></td>
                            <td><?= e($expense['Description'] ?? '') ?></td><td><?= e(online_money($expense['Amount'] ?? 0)) ?></td>
                            <td><?= (float)($expense['ReimbursedAmount'] ?? 0) + (float)($expense['InsuranceRefund'] ?? 0) > 0
                                    ? e(online_money((float)($expense['ReimbursedAmount'] ?? 0) + (float)($expense['InsuranceRefund'] ?? 0))) : '' ?></td>
                            <td><?= e($expense['PaidBy'] ?? '') ?><?= ($expense['InsuranceStatus'] ?? '') !== '' ? '<br><span class="pastille ' . (($expense['InsuranceStatus'] ?? '') === 'À envoyer' ? 'orange' : 'gris') . '">Assurance : ' . e(mb_strtolower((string)$expense['InsuranceStatus'])) . '</span>' : '' ?></td>
                            <td><?= $edit('AnimalExpenses', $expense) ?></td></tr>
                    <?php endforeach ?>
                    <tr><td colspan="3"><strong>Total</strong></td><td><strong><?= e(online_money(array_sum(array_map(fn($x) => (float)($x['Amount'] ?? 0), $expenses)))) ?></strong></td><td colspan="3"></td></tr>
                </table></div>
            <?php endif ?>
            <?php foreach ($adoptions as $adoption): ?>
                <p>Adoption le <?= e($date($adoption['AdoptionDate'] ?? null)) ?><?= $contactName($adoption['ContactId'] ?? null) !== '' ? ' par ' . e($contactName($adoption['ContactId'])) : '' ?>
                    · frais <?= e(online_money($adoption['Fee'] ?? 0)) ?> <span class="pastille <?= !empty($adoption['FeePaid']) ? '' : 'orange' ?>"><?= !empty($adoption['FeePaid']) ? 'payés' : 'à payer' ?></span>
                    <?= $edit('Adoptions', $adoption) ?></p>
            <?php endforeach ?>
        <?php endif ?>
    </div>
</section>
