<?php /** @var array $user @var array $errors */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Mon profil</h1></div>
        <?php require __DIR__ . '/_nav.php' ?>
        <form class="formulaire" method="post" action="/compte/profil" style="max-width:560px;margin-left:0">
            <?php if ($errors): ?><div class="message error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
            <?= csrf_field() ?>
            <label for="name">Nom</label>
            <input type="text" id="name" name="name" value="<?= e($user['name']) ?>" required>
            <label>Adresse e-mail</label>
            <p><?= e($user['email']) ?> <?= $user['email_verified_at'] ? '<span class="pastille">confirmée</span>' : '<span class="pastille orange">à confirmer</span>' ?></p>
            <h3 style="margin-top:22px">Changer de mot de passe</h3>
            <label for="current_password">Mot de passe actuel</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password">
            <label for="new_password">Nouveau mot de passe</label>
            <input type="password" id="new_password" name="new_password" minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
            <div class="actions-formulaire"><button class="bouton" type="submit">Enregistrer</button></div>
        </form>
    </div>
</section>
