<?php /** @var array $errors @var array $values */
$kinds = array_merge(['Particulier'], ORGANISATION_KINDS);
?>
<div class="etroit">
    <form class="formulaire" method="post" action="/inscription">
        <h1>Créer un compte</h1>
        <p class="discret">Gratuit pour les particuliers et les familles d’accueil. Les structures commencent avec la formule Découverte.</p>
        <?php if ($errors): ?><div class="message error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
        <?= csrf_field() ?>
        <label>Vous êtes</label>
        <div class="choix">
            <?php foreach ($kinds as $kind): ?>
                <label><input type="radio" name="kind" value="<?= e($kind) ?>" <?= $values['kind'] === $kind ? 'checked' : '' ?>> <?= e($kind === 'Particulier' ? 'Un particulier' : $kind) ?></label>
            <?php endforeach ?>
        </div>
        <label for="organisation">Nom de la structure <span class="discret petit">(sauf particulier)</span></label>
        <input type="text" id="organisation" name="organisation" value="<?= e($values['organisation']) ?>" placeholder="Ex. : Les Pattes de Valensole">
        <label for="name">Votre nom</label>
        <input type="text" id="name" name="name" required autocomplete="name" value="<?= e($values['name']) ?>">
        <label for="email">Adresse e-mail</label>
        <input type="email" id="email" name="email" required autocomplete="email" value="<?= e($values['email']) ?>">
        <label for="password">Mot de passe <span class="discret petit">(au moins <?= PASSWORD_MIN_LENGTH ?> caractères)</span></label>
        <input type="password" id="password" name="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
        <label class="case"><input type="checkbox" name="newsletter" value="1" <?= $values['newsletter'] ? 'checked' : '' ?>> Je veux recevoir la lettre d’information (nouveautés, quelques fois par an ; désinscription en un clic).</label>
        <label class="case"><input type="checkbox" name="cgu" value="1"> J’accepte les conditions d’utilisation et la <a href="/confidentialite" target="_blank">politique de confidentialité</a>.</label>
        <div class="actions-formulaire">
            <button class="bouton" type="submit">Créer mon compte</button>
            <a href="/connexion">J’ai déjà un compte</a>
        </div>
    </form>
</div>
