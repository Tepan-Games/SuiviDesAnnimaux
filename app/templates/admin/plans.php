<?php /** @var array $plans */
$euros = fn(int $cents) => rtrim(rtrim(number_format($cents / 100, 2, ',', ''), '0'), ',');
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Formules</h1></div>
        <?php require __DIR__ . '/_nav.php' ?>
        <p class="discret">Prix TTC affichés sur la page Tarifs (seulement les formules proposées). Une formule retirée reste attribuée aux
            structures qui l’ont ; elle n’est plus proposée. La limite d’animaux compte les animaux présents (vide : illimité).</p>
        <div class="defilement"><table class="tableau">
            <tr><th>Code</th><th>Nom</th><th>Par mois (€)</th><th>Par an (€)</th><th>Animaux</th><th>Ordre</th><th>Proposée</th><th>Licences</th><th></th></tr>
            <?php foreach ($plans as $plan): $form = 'formule-' . $plan['code']; ?>
                <tr>
                    <td><code><?= e($plan['code']) ?></code></td>
                    <td><input form="<?= e($form) ?>" type="text" name="name" value="<?= e($plan['name']) ?>" size="16" aria-label="Nom"></td>
                    <td><input form="<?= e($form) ?>" type="text" inputmode="decimal" name="monthly" value="<?= e($euros((int)$plan['monthly_price_cents'])) ?>" size="4" aria-label="Prix par mois"></td>
                    <td><input form="<?= e($form) ?>" type="text" inputmode="decimal" name="yearly" value="<?= e($euros((int)$plan['yearly_price_cents'])) ?>" size="4" aria-label="Prix par an"></td>
                    <td><input form="<?= e($form) ?>" type="text" inputmode="numeric" name="max_animals" value="<?= e($plan['max_animals'] ?? '') ?>" size="5" placeholder="illimité" aria-label="Animaux"></td>
                    <td><input form="<?= e($form) ?>" type="text" inputmode="numeric" name="sort_order" value="<?= (int)$plan['sort_order'] ?>" size="3" aria-label="Ordre"></td>
                    <td><input form="<?= e($form) ?>" type="checkbox" name="active" value="1" <?= $plan['active'] ? 'checked' : '' ?> aria-label="Proposée"></td>
                    <td><a href="/admin/clients?formule=<?= e(rawurlencode($plan['code'])) ?>"><?= (int)$plan['licences'] ?></a></td>
                    <td><form id="<?= e($form) ?>" method="post" action="/admin/formules"><?= csrf_field() ?><input type="hidden" name="action" value="modifier">
                        <input type="hidden" name="code" value="<?= e($plan['code']) ?>"><button class="bouton petit" type="submit">Enregistrer</button></form></td>
                </tr>
            <?php endforeach ?>
        </table></div>
        <form class="carte" method="post" action="/admin/formules" style="margin-top:18px;max-width:520px">
            <h3>Nouvelle formule</h3>
            <?= csrf_field() ?><input type="hidden" name="action" value="nouvelle">
            <label for="code">Code (définitif)</label><input type="text" id="code" name="code" placeholder="ex. refuge" pattern="[a-z][a-z0-9_-]{1,29}">
            <label for="name">Nom</label><input type="text" id="name" name="name">
            <label for="monthly">Prix par mois (€)</label><input type="text" id="monthly" name="monthly" inputmode="decimal" value="0">
            <label for="yearly">Prix par an (€)</label><input type="text" id="yearly" name="yearly" inputmode="decimal" value="0">
            <label for="max_animals">Animaux (vide : illimité)</label><input type="text" id="max_animals" name="max_animals" inputmode="numeric">
            <label for="sort_order">Ordre</label><input type="text" id="sort_order" name="sort_order" inputmode="numeric" value="10">
            <label class="case"><input type="checkbox" name="active" value="1" checked> Proposée sur la page Tarifs</label>
            <div class="actions-formulaire"><button class="bouton" type="submit">Créer la formule</button></div>
        </form>
    </div>
</section>
