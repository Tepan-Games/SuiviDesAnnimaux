<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$items = ['/admin' => 'Tableau de bord', '/admin/clients' => 'Clients et licences', '/admin/formules' => 'Formules', '/admin/utilisateurs' => 'Comptes', '/admin/assistance' => 'Suivi des demandes', '/admin/lettres' => 'Lettres d’information', '/admin/journal' => 'Journal'];
?>
<nav class="sous-navigation" aria-label="Administration">
    <?php foreach ($items as $href => $label): ?>
        <a href="<?= $href ?>" class="<?= $path === $href || ($href !== '/admin' && str_starts_with((string)$path, rtrim($href, 's'))) ? 'actif' : '' ?>"><?= e($label) ?></a>
    <?php endforeach ?>
</nav>
