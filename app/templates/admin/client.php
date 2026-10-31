<?php /** @var array $organisation @var ?array $licence @var array $plans @var array $members @var array $tickets @var array $log */
$id = (int)$organisation['id'];
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <div><span class="pastille"><?= e($organisation['kind']) ?></span><h1><?= e($organisation['name']) ?></h1></div>
            <a class="bouton petit secondaire" href="/admin/clients">← Clients</a>
        </div>
        <?php require __DIR__ . '/_nav.php' ?>
        <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
            <form class="carte" method="post" action="/admin/client?id=<?= $id ?>">
                <h3>Licence</h3>
                <?= csrf_field() ?>
                <label for="plan">Formule</label>
                <select id="plan" name="plan">
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= e($plan['code']) ?>" <?= ($licence['plan_code'] ?? '') === $plan['code'] ? 'selected' : '' ?>><?= e($plan['name']) ?> — <?= e(format_price((int)$plan['monthly_price_cents'])) ?>/mois</option>
                    <?php endforeach ?>
                </select>
                <label for="status">État</label>
                <select id="status" name="status">
                    <?php foreach (LICENCE_STATUSES as $key => $label): ?><option value="<?= $key ?>" <?= ($licence['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
                </select>
                <label for="billing">Facturation</label>
                <select id="billing" name="billing">
                    <option value="mois" <?= ($licence['billing'] ?? 'mois') === 'mois' ? 'selected' : '' ?>>Mensuelle</option>
                    <option value="an" <?= ($licence['billing'] ?? '') === 'an' ? 'selected' : '' ?>>Annuelle</option>
                </select>
                <label for="starts_at">Début</label><input type="date" id="starts_at" name="starts_at" value="<?= e($licence['starts_at'] ?? date('Y-m-d')) ?>">
                <label for="ends_at">Fin <span class="discret petit">(vide : sans fin)</span></label><input type="date" id="ends_at" name="ends_at" value="<?= e($licence['ends_at'] ?? '') ?>">
                <label for="notes">Notes internes</label><textarea id="notes" name="notes"><?= e($licence['notes'] ?? '') ?></textarea>
                <div class="actions-formulaire"><button class="bouton" type="submit">Enregistrer la licence</button></div>
            </form>
            <div class="carte">
                <h3>Coordonnées</h3>
                <p>Responsable : <?= e($organisation['owner_name']) ?> — <a href="mailto:<?= e($organisation['owner_email']) ?>"><?= e($organisation['owner_email']) ?></a></p>
                <p class="discret petit">
                    E-mail : <?= e($organisation['contact_email'] ?: '—') ?><br>
                    Téléphone : <?= e($organisation['phone'] ?: '—') ?><br>
                    Adresse : <?= e($organisation['address'] ?: '—') ?><br>
                    SIRET / RNA : <?= e($organisation['siret'] ?: '—') ?><br>
                    Créée le <?= e(format_date($organisation['created_at'])) ?>
                </p>
                <h3>Membres (<?= count($members) ?>)</h3>
                <?php foreach ($members as $member): ?>
                    <p class="petit"><?= e($member['name']) ?> · <?= e($member['email']) ?> · <span class="discret"><?= e(role_labels($member['roles'])) ?></span></p>
                <?php endforeach ?>
            </div>
        </div>
        <h2 style="margin-top:28px">Demandes d’assistance</h2>
        <?php if (!$tickets): ?><p class="discret">Aucune.</p><?php endif ?>
        <?php foreach ($tickets as $ticket): ?>
            <p><a href="/admin/ticket?id=<?= (int)$ticket['id'] ?>">n°<?= (int)$ticket['id'] ?> · <?= e($ticket['subject']) ?></a> · <?= e(TICKET_STATUSES[$ticket['status']] ?? '') ?> · <span class="discret petit"><?= e($ticket['user_name']) ?></span></p>
        <?php endforeach ?>
        <h2 style="margin-top:28px">Historique</h2>
        <div class="defilement"><table class="tableau">
            <tr><th>Date</th><th>Action</th><th>Détail</th></tr>
            <?php foreach ($log as $entry): ?><tr><td><?= e(format_date($entry['created_at'], true)) ?></td><td><?= e($entry['action']) ?></td><td><?= e($entry['details']) ?></td></tr><?php endforeach ?>
        </table></div>
    </div>
</section>
