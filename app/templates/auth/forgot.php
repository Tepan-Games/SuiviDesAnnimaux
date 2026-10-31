<?php /** @var bool $sent */ ?>
<div class="etroit">
    <form class="formulaire" method="post" action="/mot-de-passe-oublie">
        <h1>Mot de passe oublié</h1>
        <?php if ($sent): ?>
            <div class="message ok">Si un compte existe avec cette adresse, un e-mail vient d’être envoyé avec un lien pour choisir un nouveau mot de passe.</div>
        <?php else: ?>
            <p class="discret">Indiquez l’adresse de votre compte : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>
            <?= csrf_field() ?>
            <label for="email">Adresse e-mail</label>
            <input type="email" id="email" name="email" required autocomplete="email">
            <div class="actions-formulaire"><button class="bouton" type="submit">Envoyer le lien</button></div>
        <?php endif ?>
    </form>
</div>
