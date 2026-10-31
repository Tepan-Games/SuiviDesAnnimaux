<?php /** @var array $organisation @var array $state @var ?string $tab */ ?>
<nav class="sous-navigation" aria-label="Rubriques de la structure">
    <?php foreach (online_tabs($organisation, $state) as $key => [$label, $href]): ?>
        <a href="<?= e($href) ?>" class="<?= ($tab ?? '') === $key ? 'actif' : '' ?>"><?= e($label) ?></a>
    <?php endforeach ?>
</nav>
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
