<?php /** @var array $invitation @var ?array $user */ ?>
<div class="etroit">
    <div class="formulaire">
        <h1>Invitation</h1>
        <p>Vous êtes invité à rejoindre <strong><?= e($invitation['organisation_name']) ?></strong> comme <?= e(mb_strtolower(role_labels($invitation['roles']))) ?>.</p>
        <?php if ($user): ?>
            <?php if (mb_strtolower($user['email']) !== mb_strtolower($invitation['email'])): ?>
                <div class="message info">L’invitation a été envoyée à <?= e($invitation['email']) ?> ; vous êtes connecté avec <?= e($user['email']) ?>. Elle sera rattachée à ce compte.</div>
            <?php endif ?>
            <form method="post" action="/invitation">
                <?= csrf_field() ?><input type="hidden" name="c" value="<?= e(input('c')) ?>">
                <div class="actions-formulaire"><button class="bouton" type="submit">Rejoindre « <?= e($invitation['organisation_name']) ?> »</button></div>
            </form>
        <?php else: ?>
            <p class="discret">Créez votre compte (gratuit) ou connectez-vous : vous reviendrez ici pour accepter.</p>
            <div class="actions-formulaire">
                <a class="bouton" href="/inscription?email=<?= urlencode($invitation['email']) ?>">Créer mon compte</a>
                <a class="bouton secondaire" href="/connexion?email=<?= urlencode($invitation['email']) ?>">Me connecter</a>
            </div>
        <?php endif ?>
    </div>
</div>
