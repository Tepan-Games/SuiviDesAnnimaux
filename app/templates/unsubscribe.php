<?php /** @var ?array $delivery @var string $token @var bool $done */ ?>
<div class="etroit">
    <div class="formulaire">
        <h1>Lettre d’information</h1>
        <?php if (!$delivery): ?>
            <p>Ce lien de désinscription n’est pas valable. Vous pouvez aussi ne plus recevoir la lettre en décochant la case de <a href="/compte/profil">votre profil</a>.</p>
        <?php elseif ($done): ?>
            <p>C’est fait : <?= e($delivery['email']) ?> ne recevra plus la lettre d’information.</p>
            <p class="discret">Les e-mails liés à votre compte (mot de passe, invitations, réponses à vos demandes) continuent d’arriver. Pour vous réabonner : <a href="/compte/profil">votre profil</a>.</p>
        <?php else: ?>
            <p>Ne plus envoyer la lettre d’information à <strong><?= e($delivery['email']) ?></strong> ?</p>
            <form method="post" action="/desinscription?t=<?= e($token) ?>">
                <div class="actions-formulaire"><button class="bouton" type="submit">Me désinscrire</button></div>
            </form>
        <?php endif ?>
    </div>
</div>
