<?php /** @var array $organisation @var array $state */ ?>
<?php if ($state['too_new']): ?>
    <div class="message error">Une partie des données vient d’une version plus récente du logiciel que le site ne sait pas encore
        lire : elle n’est pas affichée.</div>
<?php endif ?>
<?php if (online_is_foster_only((string)$organisation['roles'])): ?>
    <div class="message info">Famille d’accueil : seuls les animaux qui vous sont confiés sont affichés.</div>
<?php endif ?>
<?php if ($organisation['notice'] ?? null): ?>
    <div class="message <?= $organisation['notice'][0] ?>"><?= e($organisation['notice'][1]) ?></div>
<?php endif ?>
