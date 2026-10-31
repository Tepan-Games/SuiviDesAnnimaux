<?php /** @var array $entries */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Journal</h1></div>
        <?php require __DIR__ . '/_nav.php' ?>
        <div class="defilement"><table class="tableau">
            <tr><th>Date</th><th>Compte</th><th>Action</th><th>Détail</th><th>Adresse IP</th></tr>
            <?php foreach ($entries as $entry): ?>
                <tr><td><?= e(format_date($entry['created_at'], true)) ?></td><td><?= e($entry['email'] ?? '—') ?></td><td><?= e($entry['action']) ?></td><td><?= e($entry['details']) ?></td><td class="discret petit"><?= e($entry['ip']) ?></td></tr>
            <?php endforeach ?>
        </table></div>
    </div>
</section>
