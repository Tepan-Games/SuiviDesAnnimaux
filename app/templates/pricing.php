<?php /** @var array $plans */ ?>
<section class="page">
    <div class="contenu">
        <h1>Tarifs</h1>
        <p class="discret">Les particuliers et les familles d’accueil utilisent l’application gratuitement. Les structures choisissent une formule selon le nombre d’animaux suivis ; les membres et les familles d’accueil sont illimités.</p>
        <div class="grille">
            <div class="carte tarif">
                <h3>Particulier</h3>
                <p class="prix">Gratuit</p>
                <p class="periode">pour toujours</p>
                <ul>
                    <li>Animaux illimités</li>
                    <li>Synchronisation par votre OneDrive, Google Drive ou NAS</li>
                    <li>Partage avec vos proches</li>
                </ul>
                <a class="bouton secondaire" href="/telecharger">Télécharger</a>
            </div>
            <?php foreach ($plans as $plan): $highlight = $plan['code'] === 'association'; ?>
                <div class="carte tarif<?= $highlight ? ' mis-en-avant' : '' ?>">
                    <h3><?= e($plan['name']) ?></h3>
                    <p class="prix"><?= e(format_price((int)$plan['monthly_price_cents'])) ?></p>
                    <p class="periode"><?= (int)$plan['monthly_price_cents'] > 0
                            ? 'par mois, ou ' . e(format_price((int)$plan['yearly_price_cents'])) . ' par an'
                            : 'pour essayer' ?></p>
                    <ul>
                        <li><?= $plan['max_animals'] === null ? 'Animaux illimités' : 'Jusqu’à ' . (int)$plan['max_animals'] . ' animaux suivis' ?></li>
                        <li>Membres et familles d’accueil illimités</li>
                        <li>Données hébergées en France</li>
                        <li>Rôles et accès contrôlés par le serveur</li>
                    </ul>
                    <a class="bouton<?= $highlight ? '' : ' secondaire' ?>" href="/inscription?type=Association">Commencer</a>
                </div>
            <?php endforeach ?>
        </div>
        <p class="discret petit" style="margin-top:24px">Prix TTC. Chaque structure commence avec la formule Découverte ; vous changez de formule depuis votre compte quand vous le souhaitez.</p>
    </div>
</section>
