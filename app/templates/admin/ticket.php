<?php /** @var array $ticket @var array $messages */ ?>
<section class="page">
    <div class="contenu" style="max-width:860px">
        <div class="titre-page">
            <div><h1>n°<?= (int)$ticket['id'] ?> · <?= e($ticket['subject']) ?></h1>
                <span class="discret petit"><?= e($ticket['category']) ?> · <?= e($ticket['user_name']) ?> (<?= e($ticket['user_email']) ?>)<?= $ticket['organisation_name'] ? ' · ' . e($ticket['organisation_name']) : '' ?> · ouverte le <?= e(format_date($ticket['created_at'], true)) ?></span></div>
            <a class="bouton petit secondaire" href="/admin/assistance">← Demandes</a>
        </div>
        <div class="messages-demande">
            <?php foreach ($messages as $message): ?>
                <div class="bulle <?= (int)$message['is_staff'] ? 'equipe' : '' ?>">
                    <div class="auteur"><?= e($message['name']) ?><?= (int)$message['is_staff'] ? ' (équipe)' : '' ?> · <?= e(format_date($message['created_at'], true)) ?></div>
                    <?= nl2br(e($message['body'])) ?>
                </div>
            <?php endforeach ?>
        </div>
        <form class="carte" method="post" action="/admin/ticket?id=<?= (int)$ticket['id'] ?>">
            <?= csrf_field() ?>
            <label for="body">Réponse <span class="discret petit">(envoyée par e-mail au demandeur)</span></label>
            <textarea id="body" name="body"></textarea>
            <div class="grille" style="grid-template-columns:1fr 1fr">
                <div><label for="status">État</label><select id="status" name="status">
                    <option value="">Inchangé (« en attente » si vous répondez)</option>
                    <?php foreach (TICKET_STATUSES as $key => $label): ?><option value="<?= $key ?>"><?= e($label) ?></option><?php endforeach ?>
                </select></div>
                <div><label for="priority">Priorité</label><select id="priority" name="priority">
                    <?php foreach (TICKET_PRIORITIES as $key => $label): ?><option value="<?= $key ?>" <?= $ticket['priority'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
                </select></div>
            </div>
            <div class="actions-formulaire"><button class="bouton" type="submit">Enregistrer</button></div>
        </form>
    </div>
</section>
