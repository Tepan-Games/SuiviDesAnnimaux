<?php /** @var array $question */ ?>
<section class="page">
    <div class="contenu etroit" style="width:min(760px,100% - 32px)">
        <div class="titre-page"><div><span class="pastille"><?= e($question['organisation_name']) ?></span><h1>Votre question</h1></div></div>
        <div class="messages-demande">
            <div class="bulle"><div class="auteur"><?= e($question['name']) ?> · <?= e(format_date($question['created_at'], true)) ?></div><?= nl2br(e($question['body'])) ?></div>
            <?php if ($question['answer'] !== null): ?>
                <div class="bulle equipe"><div class="auteur"><?= e($question['organisation_name']) ?> · <?= e(format_date($question['answered_at'], true)) ?></div><?= nl2br(e($question['answer'])) ?></div>
            <?php else: ?>
                <p class="discret">Pas encore de réponse : elle vous sera aussi envoyée par e-mail.</p>
            <?php endif ?>
        </div>
    </div>
</section>
