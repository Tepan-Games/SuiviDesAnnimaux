<?php /** @var array $errors @var array $values */ ?>
<div class="etroit">
    <form class="formulaire" method="post" action="/compte/structure/nouvelle">
        <h1>Nouvelle structure</h1>
        <p class="discret">Vous en serez responsable : vous inviterez ensuite les membres (bénévoles, familles d’accueil, trésorier…).</p>
        <?php if ($errors): ?><div class="message error"><?= e(implode(' ', $errors)) ?></div><?php endif ?>
        <?= csrf_field() ?>
        <label for="name">Nom de la structure</label>
        <input type="text" id="name" name="name" required value="<?= e($values['name']) ?>">
        <label>Type</label>
        <div class="choix">
            <?php foreach (ORGANISATION_KINDS as $kind): ?>
                <label><input type="radio" name="kind" value="<?= e($kind) ?>" <?= $values['kind'] === $kind ? 'checked' : '' ?>> <?= e($kind) ?></label>
            <?php endforeach ?>
        </div>
        <div class="actions-formulaire"><button class="bouton" type="submit">Créer la structure</button><a href="/compte">Annuler</a></div>
    </form>
</div>
