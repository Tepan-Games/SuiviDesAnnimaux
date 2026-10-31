<?php /** @var array $organisation @var array $state @var array $animals @var int $total @var array $placements
 * @var string $search @var string $status @var string $species @var array $speciesList */
$o = (int)$organisation['id'];
$contacts = $state['rows']['Contacts'] ?? [];
$tab = 'animaux';
?>
<section class="page">
    <div class="contenu">
        <?php require __DIR__ . '/_title.php' ?>
        <?php require __DIR__ . '/_header.php' ?>
        <div class="titre-section">
        <form class="filtres" method="get" action="/application/animaux">
            <input type="hidden" name="o" value="<?= $o ?>">
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Nom, puce, tatouage, race" aria-label="Rechercher">
            <select name="statut" aria-label="Statut">
                <?php foreach (['Présent' => 'Présents', 'Adopté' => 'Adoptés', 'Disparu' => 'Disparus', 'Décédé' => 'Décédés', 'Tous' => 'Tous'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach ?>
            </select>
            <?php if (count($speciesList) > 1): ?>
                <select name="espece" aria-label="Espèce">
                    <option value="">Toutes les espèces</option>
                    <?php foreach ($speciesList as $value): ?>
                        <option value="<?= e($value) ?>" <?= $species === $value ? 'selected' : '' ?>><?= e($value) ?></option>
                    <?php endforeach ?>
                </select>
            <?php endif ?>
            <button class="bouton petit" type="submit">Afficher</button>
        </form>
        <?php if (online_can_write($organisation, 'Animals')): ?><a class="bouton petit" href="/application/fiche?o=<?= $o ?>&amp;t=Animals">Ajouter un animal</a><?php endif ?>
        </div>
        <p class="discret petit"><?= count($animals) ?> <?= count($animals) > 1 ? 'animaux affichés' : 'animal affiché' ?> sur <?= $total ?>.
            <?php if ($state['synced_at']): ?>Dernières données reçues le <?= e(format_date(date('Y-m-d H:i:s', (int)$state['synced_at']), true)) ?>.<?php endif ?></p>
        <?php if (!$animals): ?>
            <div class="carte"><p class="discret">Aucun animal ne correspond.</p></div>
        <?php else: ?>
            <div class="liste-animaux">
                <?php foreach ($animals as $id => $animal):
                    $photo = online_file_name($animal['PhotoPath'] ?? null);
                    $placement = $placements[$id];
                    $age = online_age($animal['BirthDate'] ?? null);
                    ?>
                    <a class="carte animal" href="/application/animal?o=<?= $o ?>&amp;a=<?= e(rawurlencode((string)$id)) ?>">
                        <?php if ($photo): ?><img class="vignette" src="<?= e(online_file_url($o, $photo)) ?>" alt="" loading="lazy">
                        <?php else: ?><span class="vignette vide" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string)($animal['Name'] ?? '?'), 0, 1))) ?></span><?php endif ?>
                        <span>
                            <strong><?= e($animal['Name'] ?? '') ?></strong>
                            <?php if (($animal['Status'] ?? 'Présent') !== 'Présent'): ?><span class="pastille gris"><?= e($animal['Status']) ?></span><?php endif ?>
                            <?php if (!empty($animal['AvailableForAdoption'])): ?><span class="pastille orange">À l’adoption</span><?php endif ?>
                            <br><span class="discret petit"><?= e(implode(' · ', array_filter([$animal['Species'] ?? '', $animal['Sex'] ?? '', $animal['Breed'] ?? '', $age]))) ?></span>
                            <?php if ($placement): ?>
                                <br><span class="discret petit"><?= e($placement['Kind'] ?? '') ?><?= isset($contacts[$placement['ContactId'] ?? '']) ? ' : ' . e($contacts[$placement['ContactId']]['Name'] ?? '') : '' ?></span>
                            <?php endif ?>
                        </span>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</section>
