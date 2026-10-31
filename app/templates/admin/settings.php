<?php /** @var array $licences */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Réglages</h1></div>
        <?php require __DIR__ . '/_nav.php' ?>
        <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
            <form class="carte" method="post" action="/admin/reglages">
                <h3>Échéances des licences</h3>
                <?= csrf_field() ?><input type="hidden" name="action" value="reglages">
                <?php foreach (LICENCE_SETTINGS as $key => [$label, $default, $min, $max]): $field = str_replace('.', '_', $key); ?>
                    <?php if ($max === 1): ?>
                        <label class="case"><input type="checkbox" name="<?= e($field) ?>" value="1" <?= site_setting($key) ? 'checked' : '' ?>> <?= e($label) ?></label>
                    <?php else: ?>
                        <label for="<?= e($field) ?>"><?= e($label) ?></label>
                        <input type="number" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= site_setting($key) ?>" min="<?= $min ?>" max="<?= $max ?>">
                    <?php endif ?>
                <?php endforeach ?>
                <div class="actions-formulaire"><button class="bouton" type="submit">Enregistrer</button></div>
            </form>
            <div class="carte">
                <h3>Comment ça marche</h3>
                <ol class="petit">
                    <li>À la date de fin, la licence reste active pendant la <strong>période de grâce</strong> ; les responsables reçoivent un e-mail et voient une alerte.</li>
                    <li>À la fin de la grâce, elle passe à <strong>Expirée</strong> : les appareils lisent encore les données mais n’en envoient plus ; l’interface en ligne les montre encore. Nouvel e-mail avec la date de suppression.</li>
                    <li>Après le délai de conservation, les <strong>données synchronisées sont supprimées du site</strong> (pas des appareils), avec un dernier e-mail. Sans suppression automatique, elles sont gardées.</li>
                </ol>
                <p class="petit">Prolonger ou enregistrer la licence remet tout à zéro. Les changements de réglages s’appliquent aussi aux licences déjà échues.</p>
                <p class="petit">Ce passage en revue est fait chaque jour par la tâche planifiée <code>tools/echeances-licences.php</code> (voir le README) ; vous pouvez le lancer tout de suite :</p>
                <form method="post" action="/admin/reglages"><?= csrf_field() ?><input type="hidden" name="action" value="echeances">
                    <button class="bouton petit secondaire" type="submit">Passer les échéances en revue maintenant</button></form>
            </div>
        </div>
        <h2 style="margin-top:28px">Licences avec une date de fin</h2>
        <div class="defilement"><table class="tableau">
            <tr><th>Structure</th><th>Facturation</th><th>Fin</th><th>Grâce jusqu’au</th><th>Suppression des données</th><th>Étape</th></tr>
            <?php foreach ($licences as $licence): $timeline = licence_timeline($licence); $phase = licence_phase($licence); ?>
                <tr>
                    <td><a href="/admin/client?id=<?= (int)$licence['organisation_id'] ?>"><?= e($licence['organisation_name']) ?></a></td>
                    <td><?= $licence['billing'] === 'an' ? 'Annuelle' : 'Mensuelle' ?></td>
                    <td><?= e(format_date($timeline['end'])) ?></td>
                    <td><?= e(format_date($timeline['grace_end'])) ?></td>
                    <td><?= site_setting('licence.suppression_auto') ? e(format_date($timeline['deletion'])) : '<span class="discret">désactivée</span>' ?></td>
                    <td><span class="pastille <?= match ($phase) { 'en_cours' => '', 'grace' => 'orange', 'expiree' => 'rouge', default => 'gris' } ?>"><?= e(match ($phase) {
                        'en_cours' => 'En cours', 'grace' => 'Période de grâce', 'expiree' => 'Expirée', default => LICENCE_STATUSES[$phase] ?? $phase }) ?></span></td>
                </tr>
            <?php endforeach ?>
            <?php if (!$licences): ?><tr><td colspan="6" class="discret">Aucune licence avec une date de fin.</td></tr><?php endif ?>
        </table></div>
    </div>
</section>
