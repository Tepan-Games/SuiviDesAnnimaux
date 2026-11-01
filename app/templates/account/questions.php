<?php /** @var array $organisation @var array $questions @var bool $canWrite */
$o = (int)$organisation['id'];
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <div><span class="pastille"><?= e($organisation['name']) ?></span><h1>Questions reçues</h1></div>
            <a class="bouton petit secondaire" href="/compte/structure?id=<?= $o ?>">← La structure</a>
        </div>
        <?php if (!$organisation['accepts_questions']): ?>
            <p class="message">La structure n’accepte pas de nouvelles questions (réglage « Annuaire et questions » de la structure).</p>
        <?php endif ?>
        <?php if (!$questions): ?><p class="discret">Aucune question pour le moment.</p><?php endif ?>
        <?php foreach ($questions as $question): ?>
            <div class="carte" style="margin-bottom:12px">
                <div class="discret petit"><?= e($question['name']) ?> · <a href="mailto:<?= e($question['email']) ?>"><?= e($question['email']) ?></a> · <?= e(format_date($question['created_at'], true)) ?>
                    <?= $question['answered_at'] ? ' · <span class="pastille">répondu</span>' : ($question['closed_at'] ? ' · <span class="pastille gris">classée</span>' : ' · <span class="pastille orange">à répondre</span>') ?></div>
                <p><?= nl2br(e($question['body'])) ?></p>
                <?php if ($question['answer'] !== null): ?>
                    <div class="messages-demande"><div class="bulle equipe"><?= nl2br(e($question['answer'])) ?></div></div>
                <?php elseif ($canWrite && !$question['closed_at']): ?>
                    <form method="post" action="/compte/questions?o=<?= $o ?>">
                        <?= csrf_field() ?><input type="hidden" name="question" value="<?= (int)$question['id'] ?>">
                        <label for="reponse-<?= (int)$question['id'] ?>">Réponse (envoyée par e-mail)</label>
                        <textarea id="reponse-<?= (int)$question['id'] ?>" name="answer" rows="3"></textarea>
                        <div class="actions-formulaire">
                            <button class="bouton petit" type="submit" name="action" value="repondre">Répondre</button>
                            <button class="bouton petit secondaire" type="submit" name="action" value="classer" formnovalidate>Classer sans répondre</button>
                        </div>
                    </form>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
</section>
