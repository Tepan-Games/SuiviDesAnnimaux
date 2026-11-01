<?php /** @var array $organisation @var ?string $sent @var ?string $error @var array $values */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <div><span class="pastille"><?= e($organisation['kind']) ?></span><h1><?= e($organisation['name']) ?></h1></div>
            <a class="bouton petit secondaire" href="/annuaire">← Annuaire</a>
        </div>
        <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));align-items:start">
            <div class="carte">
                <?php if ($organisation['description'] !== ''): ?><p><?= nl2br(e($organisation['description'])) ?></p><?php endif ?>
                <?php if ($organisation['species'] !== ''): ?><p class="petit"><span class="discret">Animaux :</span> <?= e($organisation['species']) ?></p><?php endif ?>
                <p class="petit">
                    <?= $organisation['address'] !== '' ? e($organisation['address']) . '<br>' : '' ?>
                    <?= $organisation['phone'] !== '' ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $organisation['phone'])) . '">' . e($organisation['phone']) . '</a><br>' : '' ?>
                    <?= $organisation['website'] !== '' ? '<a href="' . e($organisation['website']) . '" rel="noopener nofollow" target="_blank">' . e(preg_replace('#^https?://#', '', $organisation['website'])) . '</a>' : '' ?>
                </p>
            </div>
            <?php if ($organisation['accepts_questions']): ?>
                <form class="carte" method="post" action="/structure?id=<?= (int)$organisation['id'] ?>">
                    <h3>Poser une question</h3>
                    <?php if ($sent): ?><div class="message ok">Question envoyée. La réponse arrivera à <?= e($sent) ?> ; un e-mail de confirmation vous donne aussi un lien pour la retrouver.</div><?php endif ?>
                    <?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif ?>
                    <?= csrf_field() ?>
                    <input type="text" name="website" class="cache" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <label for="question-nom">Votre nom</label><input type="text" id="question-nom" name="name" required value="<?= e($values['name']) ?>">
                    <label for="question-email">Votre adresse e-mail (pour la réponse)</label><input type="email" id="question-email" name="email" required value="<?= e($values['email']) ?>">
                    <label for="question-texte">Votre question</label><textarea id="question-texte" name="body" rows="5" required minlength="10"><?= e($values['body']) ?></textarea>
                    <p class="discret petit">Votre nom, votre adresse et votre question sont transmis à la structure, seulement pour vous répondre.</p>
                    <div class="actions-formulaire"><button class="bouton" type="submit">Envoyer</button></div>
                </form>
            <?php endif ?>
        </div>
    </div>
</section>
