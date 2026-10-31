<?php /** @var array $clients @var string $search */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <h1>Clients et licences</h1>
            <form method="get" action="/admin/clients" style="display:flex;gap:8px"><input type="text" name="q" value="<?= e($search) ?>" placeholder="Nom ou adresse e-mail"><button class="bouton petit" type="submit">Chercher</button></form>
        </div>
        <?php require __DIR__ . '/_nav.php' ?>
        <div class="defilement"><table class="tableau">
            <tr><th>Structure</th><th>Type</th><th>Responsable</th><th>Membres</th><th>Formule</th><th>État</th><th>Fin</th><th>Créée le</th></tr>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><a href="/admin/client?id=<?= (int)$client['id'] ?>"><?= e($client['name']) ?></a></td>
                    <td><?= e($client['kind']) ?></td>
                    <td><?= e($client['owner_name']) ?><br><span class="discret petit"><?= e($client['owner_email']) ?></span></td>
                    <td><?= (int)$client['members'] ?></td>
                    <td><?= e($client['plan_name'] ?? '—') ?></td>
                    <td><span class="pastille <?= $client['licence_status'] === 'active' ? '' : 'orange' ?>"><?= e(LICENCE_STATUSES[$client['licence_status']] ?? '—') ?></span></td>
                    <td><?= e(format_date($client['ends_at'])) ?></td>
                    <td><?= e(format_date($client['created_at'])) ?></td>
                </tr>
            <?php endforeach ?>
            <?php if (!$clients): ?><tr><td colspan="8" class="discret">Aucune structure.</td></tr><?php endif ?>
        </table></div>
    </div>
</section>
