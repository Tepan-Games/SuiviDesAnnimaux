<?php /** @var string $query @var ?array $results @var ?string $error */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Associations, refuges et éleveurs inscrits</h1></div>
        <p class="discret">Les structures qui utilisent Suivi des animaux et ont choisi d’apparaître ici. Certaines acceptent vos
            questions : adoption, premiers jours avec un animal, conseils.</p>
        <form class="carte" method="get" action="/annuaire" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end">
            <div style="flex:1;min-width:220px"><label for="annuaire-q">Commune, code postal ou adresse</label>
                <input type="text" id="annuaire-q" name="q" value="<?= e($query) ?>" required></div>
            <button class="bouton" type="submit">Chercher</button>
        </form>
        <?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif ?>
        <?php if ($results !== null): ?>
            <h2 style="margin-top:24px"><?= count($results) ?> structure<?= count($results) > 1 ? 's' : '' ?> à moins de <?= DIRECTORY_DEFAULT_KM ?> km</h2>
            <?php if (!$results): ?><p class="discret">Aucune structure inscrite près de cette adresse pour le moment.</p><?php endif ?>
            <div class="grille">
                <?php foreach ($results as $o): ?>
                    <a class="carte" href="/structure?id=<?= (int)$o['id'] ?>" style="text-decoration:none;color:inherit">
                        <span class="etiquette"><?= e($o['kind']) ?></span>
                        <h3><?= e($o['name']) ?></h3>
                        <p class="discret petit"><?= e($o['city']) ?> · <?= e(str_replace('.', ',', (string)$o['distance_km'])) ?> km
                            <?= $o['accepts_questions'] ? ' · <strong>répond aux questions</strong>' : '' ?></p>
                        <?php if ($o['description'] !== ''): ?><p class="petit"><?= e(mb_strimwidth($o['description'], 0, 160, '…')) ?></p><?php endif ?>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</section>
