<?php
/** @var string $content @var string $pageTitle */
$user = current_user();
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$active = fn(string $prefix) => ($prefix === '/' ? $path === '/' : str_starts_with($path, $prefix)) ? 'actif' : '';
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ? "$pageTitle — Suivi des animaux" : 'Suivi des animaux') ?></title>
    <meta name="description" content="Suivi des animaux : soins, traitements, pesées, repas, stock et adoptions, sur ordinateur et téléphone. Gratuit pour les particuliers, en ligne pour les associations, pensions et élevages.">
    <link rel="icon" href="/favicon.ico">
    <link rel="stylesheet" href="/assets/site.css?v=2">
</head>
<body>
<header class="entete">
    <div class="contenu">
        <a class="marque" href="/"><img src="/assets/img/logo.png" alt="">Suivi des animaux</a>
        <nav class="navigation" aria-label="Navigation principale">
            <a href="/fonctionnalites" class="<?= $active('/fonctionnalites') ?>">Fonctionnalités</a>
            <a href="/tarifs" class="<?= $active('/tarifs') ?>">Tarifs</a>
            <a href="/annuaire" class="<?= $active('/annuaire') ?>">Annuaire</a>
            <a href="/telecharger" class="<?= $active('/telecharger') ?>">Télécharger</a>
            <?php if ($user): ?>
                <a href="/compte" class="<?= $active('/compte') ?>">Mon compte</a>
                <?php if ((int)$user['is_admin']): ?><a href="/admin" class="<?= $active('/admin') ?>">Administration</a><?php endif ?>
                <form method="post" action="/deconnexion"><?= csrf_field() ?><button type="submit">Déconnexion</button></form>
            <?php else: ?>
                <a href="/connexion" class="<?= $active('/connexion') ?>">Connexion</a>
                <a class="bouton orange petit" href="/inscription">Créer un compte</a>
            <?php endif ?>
        </nav>
    </div>
</header>
<main>
    <?php $flashes = take_flashes(); if ($flashes): ?>
        <div class="contenu">
            <?php foreach ($flashes as $flash): ?><div class="message <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endforeach ?>
        </div>
    <?php endif ?>
    <?= $content ?>
</main>
<footer class="pied">
    <div class="contenu">
        <div>© <?= date('Y') ?> TepanSofts — Suivi des animaux</div>
        <nav aria-label="Informations">
            <a href="/contact">Contact</a>
            <a href="/mentions-legales">Mentions légales</a>
            <a href="/confidentialite">Confidentialité</a>
        </nav>
    </div>
</footer>
</body>
</html>
