<?php /** @var array $organisation @var array $state @var array $animals @var array $counts @var int $days @var array $byCoop @var int $total @var bool $canWrite */
$o = (int)$organisation['id'];
$tab = 'pontes';
?>
<section class="page">
    <div class="contenu">
        <?php require __DIR__ . '/_title.php' ?>
        <?php require __DIR__ . '/_header.php' ?>
        <div class="titre-section">
            <form method="get" action="/application/pontes" class="filtres">
                <input type="hidden" name="o" value="<?= $o ?>">
                <select name="jours" aria-label="Période">
                    <?php foreach ([7 => '7 derniers jours', 30 => '30 derniers jours', 90 => '3 derniers mois', 365 => '12 derniers mois'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $days === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
                <button class="bouton petit" type="submit">Afficher</button>
            </form>
            <?php if ($canWrite): ?><a class="bouton petit" href="/application/fiche?o=<?= $o ?>&amp;t=EggCounts">Noter une ponte</a><?php endif ?>
        </div>
        <div class="chiffres">
            <div class="chiffre"><strong><?= $total ?></strong>œufs</div>
            <div class="chiffre"><strong><?= e(online_number($total / max(1, $days), 1)) ?></strong>par jour en moyenne</div>
            <?php foreach ($byCoop as $coop => $eggs): ?><div class="chiffre"><strong><?= (int)$eggs ?></strong><?= e($coop) ?></div><?php endforeach ?>
        </div>
        <?php if (!$counts): ?>
            <div class="carte"><p class="discret">Aucune ponte notée sur cette période.</p></div>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Date</th><th>Poulailler</th><th>Poule</th><th>Œufs</th><th>Notes</th><th></th></tr>
                <?php foreach ($counts as $id => $count): $animalId = (string)($count['AnimalId'] ?? ''); ?>
                    <tr><td><?= e(format_date((string)($count['CountDate'] ?? ''))) ?></td><td><?= e($count['CoopName'] ?? '') ?></td>
                        <td><?= isset($animals[$animalId]) ? e($animals[$animalId]['Name'] ?? '') : '' ?></td><td><strong><?= (int)($count['Eggs'] ?? 0) ?></strong></td>
                        <td class="petit"><?= nl2br(e($count['Notes'] ?? '')) ?></td>
                        <td><?php if ($canWrite): ?><a class="bouton petit secondaire" href="/application/fiche?o=<?= $o ?>&amp;t=EggCounts&amp;id=<?= e(rawurlencode((string)$id)) ?>">Modifier</a><?php endif ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>
    </div>
</section>
