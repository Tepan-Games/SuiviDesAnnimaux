<?php /** @var array $organisation @var array $state @var array $animals @var array $items @var int $days @var array $stockAlerts
 * @var int $present @var bool $canWrite */
$o = (int)$organisation['id'];
$tab = 'accueil';
$today = date('Y-m-d');
$late = array_filter($items, fn($i) => substr($i['date'], 0, 10) < $today);
$now = array_filter($items, fn($i) => substr($i['date'], 0, 10) === $today);
$later = array_filter($items, fn($i) => substr($i['date'], 0, 10) > $today);
$animalLink = fn(array $item) => $item['animal'] !== null
    ? '<a href="/application/animal?o=' . $o . '&amp;a=' . e(rawurlencode($item['animal'])) . '">' . e($item['name']) . '</a>' : e($item['name']);
$canTasks = online_can_write($organisation, 'RecurringTasks');
$canFood = online_can_write($organisation, 'FoodLogs');
$canEvents = online_can_write($organisation, 'AnimalEvents');
?>
<section class="page">
    <div class="contenu">
        <?php require __DIR__ . '/_title.php' ?>
        <?php require __DIR__ . '/_header.php' ?>
        <div class="chiffres">
            <a class="chiffre lien-chiffre" href="/application/animaux?o=<?= $o ?>"><strong><?= $present ?></strong><?= $present > 1 ? 'animaux présents' : 'animal présent' ?></a>
            <div class="chiffre"><strong><?= count($late) ?></strong>en retard</div>
            <div class="chiffre"><strong><?= count($now) ?></strong>aujourd’hui</div>
            <div class="chiffre"><strong><?= count($later) ?></strong>dans les <?= $days ?> jours</div>
        </div>
        <?php if ($state['synced_at']): ?>
            <p class="discret petit">Dernières données reçues le <?= e(format_date(date('Y-m-d H:i:s', (int)$state['synced_at']), true)) ?>.</p>
        <?php elseif (!$animals): ?>
            <div class="carte">
                <p>Aucune donnée pour le moment. Vous pouvez commencer ici en ajoutant un animal, ou connecter le logiciel Windows
                    (<strong>Paramètres › Synchronisation › Se connecter au site</strong>) pour retrouver les fiches déjà saisies.</p>
                <?php if (online_can_write($organisation, 'Animals')): ?>
                    <a class="bouton petit" href="/application/fiche?o=<?= $o ?>&amp;t=Animals">Ajouter un animal</a>
                <?php endif ?>
            </div>
        <?php endif ?>

        <div class="titre-section">
            <h2>Échéances</h2>
            <form method="get" action="/application/accueil" class="filtres">
                <input type="hidden" name="o" value="<?= $o ?>">
                <select name="jours" aria-label="Période" onchange="this.form.submit()">
                    <?php foreach ([7 => '7 prochains jours', 30 => '30 prochains jours', 90 => '3 prochains mois'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $days === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach ?>
                </select>
                <noscript><button class="bouton petit" type="submit">Afficher</button></noscript>
            </form>
        </div>
        <?php if (!$items): ?>
            <div class="carte"><p class="discret">Rien à prévoir pour le moment.</p></div>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Quand</th><th>Animal</th><th>Quoi</th><th>Détail</th><th></th></tr>
                <?php foreach ($items as $item):
                    $date = substr($item['date'], 0, 10);
                    $withTime = strlen($item['date']) > 10 && substr($item['date'], 11, 5) !== '00:00'; ?>
                    <tr class="<?= $date < $today ? 'en-retard' : '' ?>">
                        <td><?= e(format_date($item['date'], $withTime)) ?>
                            <?php if ($date < $today): ?><br><span class="pastille rouge">en retard</span><?php elseif ($date === $today): ?><br><span class="pastille orange">aujourd’hui</span><?php endif ?></td>
                        <td><?= $animalLink($item) ?></td>
                        <td><span class="pastille gris"><?= e($item['category']) ?></span><br><?= e($item['title']) ?></td>
                        <td class="petit"><?= nl2br(e($item['detail'])) ?></td>
                        <td class="boutons-ligne">
                            <?php if ($item['table'] === 'Treatments' && $canWrite): ?>
                                <form method="post" action="/application/prise?o=<?= $o ?>&amp;id=<?= e(rawurlencode($item['id'])) ?>">
                                    <?= csrf_field() ?><input type="hidden" name="retour" value="accueil">
                                    <button class="bouton petit" name="statut" value="Donné">Donné</button>
                                    <button class="bouton petit secondaire" name="statut" value="Oublié">Oublié</button>
                                </form>
                            <?php elseif ($item['table'] === 'RecurringTasks' && $canTasks): ?>
                                <form method="post" action="/application/taches?o=<?= $o ?>">
                                    <?= csrf_field() ?><input type="hidden" name="retour" value="accueil"><input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                    <button class="bouton petit" name="action" value="fait"><?= $item['category'] === 'Frais' ? 'Payé' : 'Fait' ?></button>
                                    <button class="bouton petit secondaire" name="action" value="passer">Passer</button>
                                </form>
                            <?php elseif ($item['table'] === 'FoodLogs' && $canFood): ?>
                                <a class="bouton petit" href="/application/fiche?o=<?= $o ?>&amp;t=FoodLogs&amp;a=<?= e(rawurlencode((string)$item['animal'])) ?>">Noter</a>
                            <?php elseif ($item['table'] === 'FollowUps'): ?>
                                <?php $target = match ($item['title']) { 'Repas et eau' => 'FoodLogs', 'Photo' => 'AnimalPhotos', default => 'AnimalEvents' };
                                if (online_can_write($organisation, $target)): ?>
                                    <a class="bouton petit" href="/application/fiche?o=<?= $o ?>&amp;t=<?= $target ?>&amp;a=<?= e(rawurlencode((string)$item['animal'])) ?><?= $item['title'] === 'Pesée' ? '&amp;type=Pes%C3%A9e' : '' ?>">Noter</a>
                                <?php endif ?>
                            <?php elseif ($item['table'] === 'AnimalEvents' && $canEvents): ?>
                                <a class="bouton petit secondaire" href="/application/fiche?o=<?= $o ?>&amp;t=AnimalEvents&amp;id=<?= e(rawurlencode($item['id'])) ?>">Modifier</a>
                            <?php elseif ($item['table'] === 'AnimalExpenses' && online_can_write($organisation, 'AnimalExpenses')): ?>
                                <a class="bouton petit secondaire" href="/application/fiche?o=<?= $o ?>&amp;t=AnimalExpenses&amp;id=<?= e(rawurlencode($item['id'])) ?>">Modifier</a>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>

        <?php if ($stockAlerts): ?>
            <h2 class="section-fiche">Stock à surveiller</h2>
            <div class="defilement"><table class="tableau">
                <tr><th>Article</th><th>Quantité</th><th>Alerte</th></tr>
                <?php foreach ($stockAlerts as $item): ?>
                    <tr><td><?= e($item['Name'] ?? '') ?></td><td><?= e(online_number($item['Quantity'] ?? 0)) ?> <?= e($item['Unit'] ?? '') ?></td>
                        <td><?php if ($item['_low']): ?><span class="pastille orange">à racheter</span> <?php endif ?>
                            <?php if ($item['_expiry']): ?><span class="pastille <?= $item['_expiry'] < $today ? 'rouge' : 'orange' ?>"><?= $item['_expiry'] < $today ? 'périmé le' : 'périme le' ?> <?= e(format_date($item['_expiry'])) ?></span><?php endif ?></td></tr>
                <?php endforeach ?>
            </table></div>
            <p><a href="/application/stock?o=<?= $o ?>">Tout le stock</a></p>
        <?php endif ?>
    </div>
</section>
