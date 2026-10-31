<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$items = ['/compte' => 'Tableau de bord', '/application' => 'Interface en ligne', '/compte/appareils' => 'Appareils', '/compte/assistance' => 'Assistance', '/compte/profil' => 'Mon profil'];
?>
<nav class="sous-navigation" aria-label="Mon compte">
    <?php foreach ($items as $href => $label): ?>
        <a href="<?= $href ?>" class="<?= $path === $href ? 'actif' : '' ?>"><?= e($label) ?></a>
    <?php endforeach ?>
</nav>
