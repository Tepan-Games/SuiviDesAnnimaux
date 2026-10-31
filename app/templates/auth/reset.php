<?php /** @var string $token @var string $error */ ?>
<div class="etroit">
    <form class="formulaire" method="post" action="/nouveau-mot-de-passe">
        <h1>Nouveau mot de passe</h1>
        <?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif ?>
        <?= csrf_field() ?>
        <input type="hidden" name="t" value="<?= e($token) ?>">
        <label for="password">Nouveau mot de passe <span class="discret petit">(au moins <?= PASSWORD_MIN_LENGTH ?> caractères)</span></label>
        <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
        <div class="actions-formulaire"><button class="bouton" type="submit">Enregistrer</button></div>
    </form>
</div>
