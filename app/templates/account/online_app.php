<?php /** @var array $organisations */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Interface en ligne</h1></div>
        <?php require __DIR__ . '/_nav.php' ?>
        <div class="message info">L’interface en ligne est en préparation : elle permettra de consulter et de compléter les fiches des animaux de vos structures depuis un navigateur, sans installer le logiciel. En attendant, utilisez l’application Windows ou Android.</div>
        <div class="grille">
            <?php foreach ($organisations as $organisation): ?>
                <div class="carte">
                    <span class="etiquette"><?= e($organisation['kind']) ?></span>
                    <h3><?= e($organisation['name']) ?></h3>
                    <p class="discret petit">Animaux, soins, rendez-vous, suivis demandés et familles d’accueil de la structure.</p>
                    <span class="pastille gris">Bientôt disponible</span>
                </div>
            <?php endforeach ?>
            <div class="carte">
                <h3>Applications</h3>
                <p class="discret petit">Le logiciel Windows et l’application Android se connectent avec ce même compte.</p>
                <a class="bouton petit secondaire" href="/telecharger">Télécharger</a>
            </div>
        </div>
    </div>
</section>
