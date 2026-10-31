<?php /** @var array $tickets @var string $status */
$filters = ['actives' => 'À traiter'] + TICKET_STATUSES + ['toutes' => 'Toutes'];
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Suivi des demandes</h1></div>
        <?php require __DIR__ . '/_nav.php' ?>
        <nav class="sous-navigation" aria-label="État">
            <?php foreach ($filters as $key => $label): ?><a href="/admin/assistance?etat=<?= $key ?>" class="<?= $status === $key ? 'actif' : '' ?>"><?= e($label) ?></a><?php endforeach ?>
        </nav>
        <div class="defilement"><table class="tableau">
            <tr><th>N°</th><th>Objet</th><th>Type</th><th>Demandeur</th><th>Structure</th><th>Priorité</th><th>État</th><th>Mise à jour</th></tr>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= (int)$ticket['id'] ?></td>
                    <td><a href="/admin/ticket?id=<?= (int)$ticket['id'] ?>"><?= e($ticket['subject']) ?></a></td>
                    <td><?= e($ticket['category']) ?></td>
                    <td><?= e($ticket['user_name']) ?><br><span class="discret petit"><?= e($ticket['user_email']) ?></span></td>
                    <td><?= e($ticket['organisation_name'] ?? '—') ?></td>
                    <td><span class="pastille <?= in_array($ticket['priority'], ['haute', 'urgente'], true) ? 'rouge' : 'gris' ?>"><?= e(TICKET_PRIORITIES[$ticket['priority']] ?? '') ?></span></td>
                    <td><?= e(TICKET_STATUSES[$ticket['status']] ?? '') ?></td>
                    <td><?= e(format_date($ticket['updated_at'], true)) ?></td>
                </tr>
            <?php endforeach ?>
            <?php if (!$tickets): ?><tr><td colspan="8" class="discret">Aucune demande.</td></tr><?php endif ?>
        </table></div>
    </div>
</section>
