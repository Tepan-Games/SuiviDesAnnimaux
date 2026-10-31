<?php /** @var array $organisations @var array $tickets @var array $errors */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page"><h1>Assistance</h1></div>
        <?php require __DIR__ . '/_nav.php' ?>
        <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
            <form class="carte" method="post" action="/compte/assistance">
                <h3>Nouvelle demande</h3>
                <?php if ($errors): ?><div class="message error"><?= e(implode(' ', $errors)) ?></div><?php endif ?>
                <?= csrf_field() ?>
                <label for="category">Type</label>
                <select id="category" name="category"><?php foreach (TICKET_CATEGORIES as $category): ?><option><?= e($category) ?></option><?php endforeach ?></select>
                <?php if ($organisations): ?>
                    <label for="organisation">Structure concernée</label>
                    <select id="organisation" name="organisation"><option value="">Aucune</option>
                        <?php foreach ($organisations as $organisation): ?><option value="<?= (int)$organisation['id'] ?>"><?= e($organisation['name']) ?></option><?php endforeach ?>
                    </select>
                <?php endif ?>
                <label for="subject">Objet</label>
                <input type="text" id="subject" name="subject" maxlength="200" required value="<?= e(input('subject')) ?>">
                <label for="body">Description</label>
                <textarea id="body" name="body" required placeholder="Ce que vous faisiez, ce qui s’est passé, l’appareil (Windows, Android) et la version si possible."><?= e(input('body')) ?></textarea>
                <div class="actions-formulaire"><button class="bouton" type="submit">Envoyer</button></div>
            </form>
            <div>
                <h3>Mes demandes</h3>
                <?php if (!$tickets): ?><p class="discret">Aucune demande pour le moment.</p><?php endif ?>
                <?php foreach ($tickets as $ticket): ?>
                    <div class="carte" style="margin-bottom:10px">
                        <a href="/compte/demande?id=<?= (int)$ticket['id'] ?>"><strong>n°<?= (int)$ticket['id'] ?> · <?= e($ticket['subject']) ?></strong></a><br>
                        <span class="pastille <?= $ticket['status'] === 'attente' ? 'orange' : '' ?>"><?= e(TICKET_STATUSES[$ticket['status']] ?? $ticket['status']) ?></span>
                        <span class="discret petit">mis à jour le <?= e(format_date($ticket['updated_at'], true)) ?></span>
                    </div>
                <?php endforeach ?>
            </div>
        </div>
    </div>
</section>
