<?php /** @var array $stats @var int $revenue @var array $expiring @var array $tickets */ ?>
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
