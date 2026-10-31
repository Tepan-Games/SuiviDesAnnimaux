<?php /** @var array $stats @var int $revenue @var array $expiring @var array $tickets @var array $alerts @var array $byPlan */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Administration</h1></div>
        <?php require __DIR__ . '/_nav.php' ?>
        <div class="chiffres">
            <?php foreach ($stats as $label => $value): ?>
                <div class="chiffre"><strong><?= (int)$value ?></strong><?= e($label) ?></div>
            <?php endforeach ?>
            <div class="chiffre"><strong><?= e(format_price($revenue)) ?></strong>Revenu mensuel des licences actives</div>
        </div>
        <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
            <div class="carte">
                <h3>À régulariser</h3>
                <?php if (!$alerts): ?><p class="discret">Rien : aucune licence en grâce ou expirée, aucune structure au-delà de sa formule.</p><?php endif ?>
                <?php foreach ($alerts as $client): ?>
                    <p><a href="/admin/client?id=<?= (int)$client['id'] ?>"><?= e($client['name']) ?></a> · <?= e($client['plan_name'] ?? '—') ?>
                        <?php foreach (array_intersect_key($client['alerts'], ['grace' => 1, 'expiree' => 1, 'depassement' => 1]) as $label): ?><span class="pastille rouge"><?= e($label) ?></span> <?php endforeach ?></p>
                <?php endforeach ?>
                <p class="petit"><a href="/admin/reglages">Échéances et réglages</a></p>
            </div>
            <div class="carte">
                <h3>Licences par formule</h3>
                <div class="defilement"><table class="tableau">
                    <tr><th>Formule</th><th>Actives</th><th>Essai</th><th>Total</th></tr>
                    <?php foreach ($byPlan as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= (int)$row['active'] ?></td><td><?= (int)$row['essai'] ?></td><td><?= (int)$row['total'] ?></td></tr><?php endforeach ?>
                </table></div>
                <p class="petit"><a href="/admin/formules">Modifier les formules</a></p>
            </div>
            <div class="carte">
                <h3>Licences qui se terminent dans les 30 jours</h3>
                <?php if (!$expiring): ?><p class="discret">Aucune.</p><?php endif ?>
                <?php foreach ($expiring as $row): ?>
                    <p><a href="/admin/client?id=<?= (int)$row['id'] ?>"><?= e($row['name']) ?></a> · <?= e($row['plan_name']) ?> · <span class="pastille orange"><?= e(format_date($row['ends_at'])) ?></span></p>
                <?php endforeach ?>
            </div>
            <div class="carte">
                <h3>Demandes à traiter</h3>
                <?php if (!$tickets): ?><p class="discret">Aucune.</p><?php endif ?>
                <?php foreach ($tickets as $ticket): ?>
                    <p><a href="/admin/ticket?id=<?= (int)$ticket['id'] ?>">n°<?= (int)$ticket['id'] ?> · <?= e($ticket['subject']) ?></a>
                        <span class="discret petit">— <?= e($ticket['user_name']) ?></span>
                        <?php if (in_array($ticket['priority'], ['haute', 'urgente'], true)): ?><span class="pastille rouge"><?= e(TICKET_PRIORITIES[$ticket['priority']]) ?></span><?php endif ?></p>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</section>
