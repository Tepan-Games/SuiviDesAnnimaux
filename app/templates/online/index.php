<?php /** @var array $organisations */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Interface en ligne</h1></div>
        <?php require __DIR__ . '/../account/_nav.php' ?>
        <p class="discret">Consultez depuis un navigateur les fiches des animaux de vos structures, telles que le logiciel Windows et
            l’application Android les ont synchronisées par le site. La modification en ligne arrive bientôt : en attendant,
            utilisez le logiciel ou l’application.</p>
        <div class="grille">
            <?php foreach ($organisations as $organisation): ?>
                <div class="carte">
                    <span class="etiquette"><?= e($organisation['kind']) ?></span>
                    <h3><?= e($organisation['name']) ?></h3>
                    <?php if ($organisation['notice']): ?><div class="message <?= $organisation['notice'][0] ?> petit"><?= e($organisation['notice'][1]) ?></div><?php endif ?>
                    <?php if ($organisation['synced_at'] === null): ?>
                        <p class="discret petit">Aucune donnée reçue pour le moment. Dans le logiciel Windows, ouvrez l’espace de la
                            structure puis <strong>Paramètres › Synchronisation › Se connecter au site</strong>.</p>
                    <?php else: ?>
                        <p class="petit"><?= (int)$organisation['present_count'] ?> <?= $organisation['present_count'] > 1 ? 'animaux présents' : 'animal présent' ?>
                            <span class="discret">· <?= (int)$organisation['animal_count'] ?> en tout</span></p>
                        <p class="discret petit">Dernières données reçues le <?= e(format_date(date('Y-m-d H:i:s', (int)$organisation['synced_at']), true)) ?></p>
                        <a class="bouton petit" href="/application/animaux?o=<?= (int)$organisation['id'] ?>">Voir les animaux</a>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
            <?php if (!$organisations): ?>
                <div class="carte">
                    <h3>Aucune structure</h3>
                    <p class="discret petit">L’interface en ligne présente les animaux des associations, pensions, élevages et familles
                        d’accueil dont vous êtes membre.</p>
                    <a class="bouton petit secondaire" href="/compte/structure/nouvelle">Créer une structure</a>
                </div>
            <?php endif ?>
            <div class="carte">
                <h3>Applications</h3>
                <p class="discret petit">Le logiciel Windows et l’application Android se connectent avec ce même compte.</p>
                <a class="bouton petit secondaire" href="/telecharger">Télécharger</a>
            </div>
        </div>
    </div>
</section>
