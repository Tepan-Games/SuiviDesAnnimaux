<?php /** @var string $error @var string $email */ ?>
<div class="etroit">
    <form class="formulaire" method="post" action="/connexion">
        <h1>Connexion</h1>
        <?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif ?>
        <?= csrf_field() ?>
        <label for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email" required autocomplete="email" value="<?= e($email) ?>">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <div class="actions-formulaire">
            <button class="bouton" type="submit">Se connecter</button>
            <a href="/mot-de-passe-oublie">Mot de passe oublié ?</a>
        </div>
        <p class="discret petit" style="margin-top:18px">Pas encore de compte ? <a href="/inscription">Créer un compte</a></p>
    </form>
</div>
