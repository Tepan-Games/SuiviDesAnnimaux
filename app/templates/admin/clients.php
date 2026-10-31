<?php /** @var array $clients @var array $filters @var array $plans */
$query = http_build_query(array_filter($filters, fn($v) => $v !== ''));
$alertClass = ['fin_depassee' => 'rouge', 'depassement' => 'rouge', 'fin_proche' => 'orange', 'sans_licence' => 'gris'];
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <h1>Clients et licences</h1>
            <a class="bouton petit secondaire" href="/admin/clients?<?= e($query === '' ? 'format=csv' : "$query&format=csv") ?>">Exporter (CSV)</a>
        </div>
        <?php require __DIR__ . '/_nav.php' ?>
        <form class="filtres" method="get" action="/admin/clients">
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Structure, titulaire, e-mail, SIRET" aria-label="Rechercher">
            <select name="etat" aria-label="État de la licence"><option value="">Tous les états</option>
                <?php foreach (LICENCE_STATUSES as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['etat'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
            </select>
            <select name="formule" aria-label="Formule"><option value="">Toutes les formules</option>
                <?php foreach ($plans as $plan): ?><option value="<?= e($plan['code']) ?>" <?= $filters['formule'] === $plan['code'] ? 'selected' : '' ?>><?= e($plan['name']) ?></option><?php endforeach ?>
            </select>
            <select name="type" aria-label="Type"><option value="">Tous les types</option>
                <?php foreach (ORGANISATION_KINDS as $kind): ?><option value="<?= e($kind) ?>" <?= $filters['type'] === $kind ? 'selected' : '' ?>><?= e($kind) ?></option><?php endforeach ?>
            </select>
            <select name="alerte" aria-label="Alerte"><option value="">Toutes</option>
                <?php foreach (CLIENT_ALERT_FILTERS as $key => $label): ?><option value="<?= e($key) ?>" <?= $filters['alerte'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
            </select>
            <button class="bouton petit" type="submit">Filtrer</button>
            <?php if ($query !== ''): ?><a class="petit" href="/admin/clients">Tout afficher</a><?php endif ?>
        </form>
        <p class="discret petit"><?= count($clients) ?> structure<?= count($clients) > 1 ? 's' : '' ?></p>
        <div class="defilement"><table class="tableau">
            <tr><th>Structure</th><th>Titulaire</th><th>Membres</th><th>Formule</th><th>État</th><th>Fin</th><th>Animaux</th><th>Dernières données</th><th>Créée le</th></tr>
            <?php foreach ($clients as $client): ?>
                <tr>
                    <td><a href="/admin/client?id=<?= (int)$client['id'] ?>"><?= e($client['name']) ?></a><br><span class="discret petit"><?= e($client['kind']) ?></span></td>
                    <td><?= e($client['owner_name']) ?><br><span class="discret petit"><?= e($client['owner_email']) ?></span></td>
                    <td><?= (int)$client['members'] ?></td>
                    <td><?= e($client['plan_name'] ?? '—') ?><?= $client['plan_code'] && $client['billing'] === 'an' ? '<br><span class="discret petit">annuelle</span>' : '' ?></td>
                    <td><span class="pastille <?= $client['licence_status'] === 'active' ? '' : 'orange' ?>"><?= e(LICENCE_STATUSES[$client['licence_status']] ?? '—') ?></span>
                        <?php foreach ($client['alerts'] as $key => $label): ?><br><span class="pastille <?= $alertClass[$key] ?? 'orange' ?>"><?= e($label) ?></span><?php endforeach ?></td>
                    <td><?= e(format_date($client['ends_at'])) ?></td>
                    <td><?= (int)$client['usage']['present'] ?><?= $client['max_animals'] !== null && $client['plan_code'] ? ' / ' . (int)$client['max_animals'] : '' ?>
                        <?= $client['usage']['animals'] > $client['usage']['present'] ? '<br><span class="discret petit">' . (int)$client['usage']['animals'] . ' en tout</span>' : '' ?></td>
                    <td><?= $client['usage']['synced_at'] ? e(format_date(date('Y-m-d H:i:s', (int)$client['usage']['synced_at']), true)) : '<span class="discret">—</span>' ?></td>
                    <td><?= e(format_date($client['created_at'])) ?></td>
                </tr>
            <?php endforeach ?>
            <?php if (!$clients): ?><tr><td colspan="9" class="discret">Aucune structure.</td></tr><?php endif ?>
        </table></div>
    </div>
</section>
