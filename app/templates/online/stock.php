<?php /** @var array $organisation @var array $state @var array $items @var array $alerts @var string $category @var string $search @var array $categories @var bool $canWrite */
$o = (int)$organisation['id'];
$tab = 'stock';
$today = date('Y-m-d');
?>
<section class="page">
    <div class="contenu">
        <?php require __DIR__ . '/_title.php' ?>
        <?php require __DIR__ . '/_header.php' ?>
        <div class="titre-section">
            <form class="filtres" method="get" action="/application/stock">
                <input type="hidden" name="o" value="<?= $o ?>">
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Nom, notes, code de la boîte" aria-label="Rechercher">
                <select name="categorie" aria-label="Catégorie">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $value): ?><option value="<?= e($value) ?>" <?= $category === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach ?>
                </select>
                <button class="bouton petit" type="submit">Afficher</button>
            </form>
            <?php if ($canWrite): ?><a class="bouton petit" href="/application/fiche?o=<?= $o ?>&amp;t=StockItems">Ajouter un article</a><?php endif ?>
        </div>
        <?php if (!$items): ?>
            <div class="carte"><p class="discret">Aucun article.</p></div>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Article</th><th>Catégorie</th><th>Quantité</th><th>Péremption</th><?php if ($canWrite): ?><th>Ajouter / retirer</th><th></th><?php endif ?></tr>
                <?php foreach ($items as $id => $item): $alert = $alerts[$id] ?? null; ?>
                    <tr><td><strong><?= e($item['Name'] ?? '') ?></strong>
                            <?= ($item['LotNumber'] ?? '') !== '' ? '<br><span class="discret petit">Lot ' . e($item['LotNumber']) . '</span>' : '' ?>
                            <?= ($item['Notes'] ?? '') !== '' ? '<br><span class="discret petit">' . nl2br(e($item['Notes'])) . '</span>' : '' ?></td>
                        <td><?= e($item['Category'] ?? '') ?></td>
                        <td><?= e(online_number($item['Quantity'] ?? 0)) ?> <?= e($item['Unit'] ?? '') ?>
                            <?= $alert && $alert['_low'] ? '<br><span class="pastille orange">à racheter</span>' : '' ?></td>
                        <td><?= ($item['ExpiryDate'] ?? '') !== '' && $item['ExpiryDate'] !== null ? e(format_date((string)$item['ExpiryDate'])) : '' ?>
                            <?= $alert && $alert['_expiry'] ? '<br><span class="pastille ' . ($alert['_expiry'] < $today ? 'rouge">périmé' : 'orange">bientôt périmé') . '</span>' : '' ?></td>
                        <?php if ($canWrite): ?>
                            <td><form method="post" action="/application/stock?o=<?= $o ?>" class="ajustement">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="categorie" value="<?= e($category) ?>">
                                    <input type="text" inputmode="decimal" name="quantite" placeholder="+10 ou -1" aria-label="Quantité à ajouter ou retirer" size="7">
                                    <button class="bouton petit secondaire" type="submit">OK</button>
                                </form></td>
                            <td><a class="bouton petit secondaire" href="/application/fiche?o=<?= $o ?>&amp;t=StockItems&amp;id=<?= e(rawurlencode((string)$id)) ?>">Modifier</a></td>
                        <?php endif ?></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>
    </div>
</section>
