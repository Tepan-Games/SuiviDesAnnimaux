<?php /** @var array $organisation @var array $state @var array $tasks @var array $animals @var bool $finance @var bool $canWrite @var array $contacts */
$o = (int)$organisation['id'];
$tab = 'taches';
$today = date('Y-m-d');
$interval = fn(array $t) => 'Tous les ' . max(1, (int)($t['IntervalValue'] ?? 1)) . ' ' . ($t['IntervalUnit'] ?? '');
?>
<section class="page">
    <div class="contenu">
        <?php require __DIR__ . '/_title.php' ?>
        <?php require __DIR__ . '/_header.php' ?>
        <div class="titre-section">
            <p class="discret"><?= $finance ? 'Frais réguliers (pension, assurance…) et entretiens (litière, aquarium…).' : 'Entretiens réguliers (litière, aquarium, box…).' ?>
                « <?= $finance ? 'Payé' : 'Fait' ?> » note l’échéance et passe à la suivante<?= $finance ? ' ; des frais liés à un animal deviennent une dépense de sa fiche' : '' ?>.</p>
            <?php if ($canWrite): ?><a class="bouton petit" href="/application/fiche?o=<?= $o ?>&amp;t=RecurringTasks">Ajouter</a><?php endif ?>
        </div>
        <?php if (!$tasks): ?>
            <div class="carte"><p class="discret">Aucun frais ni entretien régulier.</p></div>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Échéance</th><th>Intitulé</th><th>Pour</th><th>Fréquence</th><?php if ($finance): ?><th>Montant</th><?php endif ?><th></th></tr>
                <?php foreach ($tasks as $id => $task):
                    $due = substr((string)($task['NextDueDate'] ?? ''), 0, 10);
                    $animalId = ($task['AnimalId'] ?? null) !== null ? (string)$task['AnimalId'] : null;
                    $fee = ($task['Kind'] ?? '') === 'Frais'; ?>
                    <tr class="<?= empty($task['Active']) ? 'inactif' : '' ?>">
                        <td><?= e(format_date($due)) ?><?php if (empty($task['Active'])): ?><br><span class="pastille gris">arrêtée</span>
                            <?php elseif ($due < $today): ?><br><span class="pastille rouge">en retard</span><?php elseif ($due === $today): ?><br><span class="pastille orange">aujourd’hui</span><?php endif ?></td>
                        <td><span class="pastille gris"><?= e($task['Kind'] ?? '') ?></span> <strong><?= e($task['Title'] ?? '') ?></strong>
                            <?= ($task['LastDoneDate'] ?? null) ? '<br><span class="discret petit">Dernière fois le ' . e(format_date((string)$task['LastDoneDate'])) . '</span>' : '' ?></td>
                        <td><?= $animalId !== null && isset($animals[$animalId]) ? '<a href="/application/animal?o=' . $o . '&amp;a=' . e(rawurlencode($animalId)) . '">' . e($animals[$animalId]['Name'] ?? '') . '</a>' : e($task['Equipment'] ?? '') ?>
                            <?= isset($contacts[(string)($task['ContactId'] ?? '')]) ? '<br><span class="discret petit">' . e($contacts[(string)$task['ContactId']]['Name'] ?? '') . '</span>' : '' ?></td>
                        <td><?= e($interval($task)) ?></td>
                        <?php if ($finance): ?><td><?= ($task['Amount'] ?? null) !== null ? e(online_money($task['Amount'])) : '' ?></td><?php endif ?>
                        <td class="boutons-ligne"><?php if ($canWrite): ?>
                            <?php if (!empty($task['Active'])): ?>
                                <form method="post" action="/application/taches?o=<?= $o ?>">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id) ?>">
                                    <button class="bouton petit" name="action" value="fait"><?= $fee ? 'Payé' : 'Fait' ?></button>
                                    <button class="bouton petit secondaire" name="action" value="passer">Passer</button>
                                </form>
                            <?php endif ?>
                            <a class="bouton petit secondaire" href="/application/fiche?o=<?= $o ?>&amp;t=RecurringTasks&amp;id=<?= e(rawurlencode((string)$id)) ?>">Modifier</a>
                        <?php endif ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>
    </div>
</section>
