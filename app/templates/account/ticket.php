<?php /** @var array $ticket @var array $messages */ ?>
<section class="page">
    <div class="contenu" style="max-width:820px">
        <div class="titre-page">
            <div><h1>n°<?= (int)$ticket['id'] ?> · <?= e($ticket['subject']) ?></h1>
                <span class="pastille"><?= e(TICKET_STATUSES[$ticket['status']] ?? $ticket['status']) ?></span>
                <span class="discret petit"><?= e($ticket['category']) ?><?= $ticket['organisation_name'] ? ' · ' . e($ticket['organisation_name']) : '' ?></span></div>
            <a class="bouton petit secondaire" href="/compte/assistance">← Mes demandes</a>
        </div>
        <div class="messages-demande">
            <?php foreach ($messages as $message): ?>
                <div class="bulle <?= (int)$message['is_staff'] ? 'equipe' : '' ?>">
                    <div class="auteur"><?= (int)$message['is_staff'] ? 'Équipe Suivi des animaux' : e($message['name']) ?> · <?= e(format_date($message['created_at'], true)) ?></div>
                    <?= nl2br(e($message['body'])) ?>
                </div>
            <?php endforeach ?>
        </div>
        <?php if ($ticket['status'] !== 'ferme'): ?>
            <form class="carte" method="post" action="/compte/demande?id=<?= (int)$ticket['id'] ?>">
                <?= csrf_field() ?>
                <label for="body">Répondre</label>
                <textarea id="body" name="body"></textarea>
                <div class="actions-formulaire">
                    <button class="bouton" type="submit">Envoyer</button>
                    <?php if ($ticket['status'] !== 'resolu'): ?>
                        <button class="bouton secondaire" type="submit" name="action" value="resolu">Mon problème est résolu</button>
                    <?php endif ?>
                </div>
            </form>
        <?php endif ?>
    </div>
</section>
