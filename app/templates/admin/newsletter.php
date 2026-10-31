<?php /** @var array $newsletter @var bool $editable @var array $errors @var array $stats @var int $subscribers @var string $preview @var int $batch */
$id = (int)$newsletter['id'];
?>
<section class="page">
    <div class="contenu" style="max-width:900px">
        <div class="titre-page">
            <h1><?= e($newsletter['subject']) ?></h1>
            <a class="bouton petit secondaire" href="/admin/lettres">← Lettres</a>
        </div>
        <?php if ($errors): ?><div class="message error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
        <?php if ($editable): ?>
            <form class="carte" method="post" action="/admin/lettre?id=<?= $id ?>">
                <?= csrf_field() ?>
                <label for="subject">Objet</label>
                <input type="text" id="subject" name="subject" maxlength="200" required value="<?= e($newsletter['subject']) ?>">
                <label for="body">Texte <span class="discret petit">({nom} : nom du destinataire ; {site} : adresse du site ; le lien de désinscription est ajouté à la fin)</span></label>
                <textarea id="body" name="body" style="min-height:360px;font-family:inherit"><?= e($newsletter['body']) ?></textarea>
                <div class="actions-formulaire">
                    <button class="bouton secondaire" name="action" value="enregistrer">Enregistrer</button>
                    <button class="bouton secondaire" name="action" value="essai">M’envoyer un essai</button>
                    <button class="bouton petit danger" name="action" value="supprimer" formnovalidate onclick="return confirm('Supprimer ce brouillon ?')">Supprimer</button>
                </div>
                <h3 style="margin-top:24px">Envoyer</h3>
                <p><strong><?= $subscribers ?></strong> abonné(s) recevront cette lettre, par lots de <?= $batch ?> (limite d’envoi de l’hébergement).</p>
                <label class="case"><input type="checkbox" name="confirmation" value="1"> J’ai relu l’essai : envoyer la lettre aux <?= $subscribers ?> abonné(s). L’envoi ne peut pas être annulé.</label>
                <div class="actions-formulaire"><button class="bouton" name="action" value="envoyer">Envoyer aux abonnés</button></div>
            </form>
        <?php else: ?>
            <div class="chiffres">
                <div class="chiffre"><strong><?= $stats['sent'] ?> / <?= $stats['total'] ?></strong>Envoyés</div>
                <div class="chiffre"><strong><?= $stats['pending'] ?></strong>En attente</div>
                <div class="chiffre"><strong><?= $stats['failed'] ?></strong>Échecs</div>
                <div class="chiffre"><strong><?= $stats['unsubscribed'] ?></strong>Désinscriptions</div>
            </div>
            <?php if ($newsletter['finished_at']): ?>
                <p>Envoi terminé le <?= e(format_date($newsletter['finished_at'], true)) ?>.</p>
            <?php else: ?>
                <form method="post" action="/admin/lettre?id=<?= $id ?>">
                    <?= csrf_field() ?>
                    <p>Envoi en cours : cliquez pour envoyer les <?= $batch ?> suivants. Si l’hébergement refuse des e-mails, attendez une heure avant de continuer.</p>
                    <button class="bouton" name="action" value="lot">Envoyer le lot suivant</button>
                </form>
            <?php endif ?>
        <?php endif ?>
        <h3 style="margin-top:24px">Aperçu</h3>
        <div class="carte" style="white-space:pre-wrap"><?= e($preview) ?></div>
    </div>
</section>
