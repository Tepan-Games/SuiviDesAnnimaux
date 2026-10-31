<section class="page">
    <div class="contenu">
        <h1>Télécharger</h1>
        <div class="grille">
            <div class="carte">
                <h3>Windows 10 et 11</h3>
                <p>Un seul fichier, rien à installer. Le logiciel se met à jour tout seul.</p>
                <a class="bouton" href="<?= e(config('download_pc')) ?>">Télécharger pour Windows</a>
                <p class="discret petit" style="margin-top:12px">Si Windows affiche « Windows a protégé votre ordinateur » : « Informations complémentaires » puis « Exécuter quand même ».</p>
            </div>
            <div class="carte">
                <h3>Android</h3>
                <p>L’application pour le quotidien : prises, pesées, repas, photos, scan des boîtes et des vignettes.</p>
                <?php require APP_ROOT . '/templates/_google_play.php' ?>
            </div>
            <div class="carte">
                <h3>Pour une structure</h3>
                <p>Créez le compte de votre association, pension ou élevage, puis connectez l’application avec ce compte : vos membres reçoivent une invitation.</p>
                <a class="bouton secondaire" href="/inscription?type=Association">Créer le compte de la structure</a>
            </div>
        </div>
    </div>
</section>
