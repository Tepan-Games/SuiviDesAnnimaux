<?php /** @var array $devices @var ?string $code @var array $organisations */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Appareils connectés</h1></div>
        <?php require __DIR__ . '/_nav.php' ?>

        <div class="grille">
            <div class="carte">
                <h2>Connecter un appareil</h2>
                <?php if ($code !== null): ?>
                    <p>Dans le logiciel ou l’application : <strong>Ajouter une structure du site…</strong>, onglet
                        <strong>Code de connexion</strong>, puis tapez ce code ou scannez le QR code.</p>
                    <p class="code-appareil" id="code-appareil"><?= e($code) ?></p>
                    <div class="qr-code" data-qr="<?= e(device_code_link($code)) ?>" role="img" aria-label="QR code de connexion"></div>
                    <p class="discret petit">Valable <?= DEVICE_CODE_MINUTES ?> minutes, pour un seul appareil.</p>
                    <script src="/assets/js/qrcode.js"></script>
                    <script src="/assets/js/appareils.js"></script>
                <?php else: ?>
                    <p>Pour synchroniser une structure sur un ordinateur ou un téléphone sans y taper votre mot de passe,
                        générez un code de connexion à usage unique.</p>
                    <?php if (!$organisations): ?>
                        <p class="discret petit">Vous n’êtes membre d’aucune structure pour le moment : rien ne sera synchronisé par le site.</p>
                    <?php endif ?>
                    <form method="post" action="/compte/appareils"><?= csrf_field() ?><input type="hidden" name="action" value="code">
                        <button class="bouton" type="submit">Générer un code</button></form>
                <?php endif ?>
            </div>
            <div class="carte">
                <h2>Comment ça marche</h2>
                <p class="petit">Les espaces <strong>Personnel</strong> se synchronisent avec votre propre cloud (OneDrive, Google Drive, NAS).
                    Les structures (association, pension, élevage, famille d’accueil) se synchronisent par le site : chaque
                    appareil se connecte une fois, avec votre adresse e-mail et votre mot de passe, ou avec un code.</p>
                <p class="discret petit">Un appareil perdu ou prêté ? Déconnectez-le ci-dessous : il ne pourra plus rien lire ni envoyer.</p>
            </div>
        </div>

        <h2 style="margin-top:32px">Appareils</h2>
        <?php if (!$devices): ?>
            <p class="discret">Aucun appareil connecté.</p>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Appareil</th><th>Connecté le</th><th>Dernière synchronisation</th><th></th></tr>
                <?php foreach ($devices as $device): ?>
                    <tr>
                        <td><?= e($device['name']) ?></td>
                        <td><?= e(format_date($device['created_at'], true)) ?></td>
                        <td><?= e(format_date($device['last_seen_at'], true)) ?></td>
                        <td><form class="en-ligne-form" method="post" action="/compte/appareils"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="deconnecter"><input type="hidden" name="device" value="<?= (int)$device['id'] ?>">
                            <button class="bouton petit secondaire" type="submit">Déconnecter</button></form></td>
                    </tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>
    </div>
</section>
