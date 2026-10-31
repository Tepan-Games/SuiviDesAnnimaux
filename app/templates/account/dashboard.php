<?php /** @var array $user @var array $organisations @var array $tickets */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Bonjour <?= e($user['name']) ?></h1></div>
        <?php require __DIR__ . '/_nav.php' ?>
        <?php if (!$user['email_verified_at']): ?>
            <div class="message info">
                Confirmez votre adresse e-mail avec le lien qui vous a été envoyé.
                <form class="en-ligne-form" method="post" action="/compte"><?= csrf_field() ?><input type="hidden" name="action" value="renvoyer">
                    <button class="bouton petit secondaire" type="submit">Renvoyer l’e-mail</button></form>
            </div>
        <?php endif ?>

        <div class="titre-page">
            <h2>Mes structures</h2>
            <a class="bouton petit" href="/compte/structure/nouvelle">Ajouter une structure</a>
        </div>
        <?php if (!$organisations): ?>
            <div class="carte">
                <p>Vous n’êtes membre d’aucune structure. En tant que particulier, l’application suffit : <a href="/telecharger">téléchargez-la</a> et synchronisez-la avec votre propre cloud.</p>
                <p class="discret">Une association vous a invité ? Ouvrez le lien de l’invitation reçue par e-mail.</p>
            </div>
        <?php else: ?>
            <div class="grille">
                <?php foreach ($organisations as $organisation): ?>
                    <div class="carte">
                        <span class="etiquette"><?= e($organisation['kind']) ?></span>
                        <h3><a href="/compte/structure?id=<?= (int)$organisation['id'] ?>"><?= e($organisation['name']) ?></a></h3>
                        <p class="discret petit">
                            Vos rôles : <?= e(role_labels($organisation['roles'])) ?><br>
                            <?= (int)$organisation['members'] ?> membre<?= (int)$organisation['members'] > 1 ? 's' : '' ?>
                            · formule <?= e($organisation['plan_name'] ?? '—') ?>
                            <?php if ($organisation['licence_status'] && $organisation['licence_status'] !== 'active'): ?>
                                · <span class="pastille orange"><?= e(LICENCE_STATUSES[$organisation['licence_status']] ?? $organisation['licence_status']) ?></span>
                            <?php endif ?>
                        </p>
                        <a class="bouton petit secondaire" href="/compte/structure?id=<?= (int)$organisation['id'] ?>">Gérer</a>
                    </div>
                <?php endforeach ?>
            </div>
        <?php endif ?>

        <div class="titre-page" style="margin-top:32px">
            <h2>Mes demandes d’assistance</h2>
            <a class="bouton petit secondaire" href="/compte/assistance">Nouvelle demande</a>
        </div>
        <?php if (!$tickets): ?>
            <p class="discret">Aucune demande pour le moment.</p>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>N°</th><th>Objet</th><th>État</th><th>Mise à jour</th></tr>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?= (int)$ticket['id'] ?></td>
                        <td><a href="/compte/demande?id=<?= (int)$ticket['id'] ?>"><?= e($ticket['subject']) ?></a></td>
                        <td><span class="pastille <?= $ticket['status'] === 'attente' ? 'orange' : ($ticket['status'] === 'ferme' || $ticket['status'] === 'resolu' ? 'gris' : '') ?>"><?= e(TICKET_STATUSES[$ticket['status']] ?? $ticket['status']) ?></span></td>
                        <td><?= e(format_date($ticket['updated_at'], true)) ?></td>
                    </tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>
    </div>
</section>
