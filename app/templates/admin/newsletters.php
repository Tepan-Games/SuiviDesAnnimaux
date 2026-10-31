<?php /** @var array $newsletters @var int $subscribers */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <h1>Lettres d’information</h1>
            <form method="post" action="/admin/lettres"><?= csrf_field() ?><button class="bouton petit" type="submit">Nouvelle lettre</button></form>
        </div>
        <?php require __DIR__ . '/_nav.php' ?>
        <p><strong><?= $subscribers ?></strong> abonné(s) à l’adresse confirmée. Expéditeur : <?= e(config('newsletter.from', config('mail.from'))) ?> ; les réponses arrivent à <?= e(config('support_email')) ?>.</p>
        <div class="defilement"><table class="tableau">
            <tr><th>Objet</th><th>État</th><th>Envoyés</th><th>Désinscriptions</th><th>Créée le</th></tr>
            <?php foreach ($newsletters as $row): ?>
                <tr>
                    <td><a href="/admin/lettre?id=<?= (int)$row['id'] ?>"><?= e($row['subject']) ?></a></td>
                    <td><?php if ($row['finished_at']): ?><span class="pastille">envoyée le <?= e(format_date($row['finished_at'])) ?></span>
                        <?php elseif ($row['queued_at']): ?><span class="pastille orange">envoi en cours</span>
                        <?php else: ?><span class="pastille gris">brouillon</span><?php endif ?></td>
                    <td><?= $row['queued_at'] ? (int)$row['sent'] . ' / ' . (int)$row['total'] : '—' ?></td>
                    <td><?= $row['queued_at'] ? (int)$row['unsubscribed'] : '—' ?></td>
                    <td><?= e(format_date($row['created_at'])) ?></td>
                </tr>
            <?php endforeach ?>
            <?php if (!$newsletters): ?><tr><td colspan="5" class="discret">Aucune lettre pour l’instant.</td></tr><?php endif ?>
        </table></div>
    </div>
</section>
