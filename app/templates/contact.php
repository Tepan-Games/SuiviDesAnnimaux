<?php /** @var bool $sent @var array $errors */ ?>
<div class="etroit">
    <form class="formulaire" method="post" action="/contact">
        <h1>Contact</h1>
        <?php if ($sent): ?>
            <div class="message ok">Merci, votre message est envoyé. Nous vous répondons par e-mail.</div>
        <?php else: ?>
            <p class="discret">Une question, une suggestion ? Les personnes qui ont un compte peuvent aussi passer par <a href="/compte/assistance">l’assistance</a> pour suivre leur demande.</p>
            <?php if ($errors): ?><div class="message error"><?= e(implode(' ', $errors)) ?></div><?php endif ?>
            <?= csrf_field() ?>
            <label for="name">Nom</label><input type="text" id="name" name="name" required value="<?= e(input('name')) ?>">
            <label for="email">Adresse e-mail</label><input type="email" id="email" name="email" required value="<?= e(input('email')) ?>">
            <label for="message">Message</label><textarea id="message" name="message" required><?= e(input('message')) ?></textarea>
            <input class="cache" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="actions-formulaire"><button class="bouton" type="submit">Envoyer</button></div>
        <?php endif ?>
    </form>
</div>
