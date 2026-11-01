<?php /** @var array $organisation @var array $link @var array $messages @var bool $canWrite */
$o = (int)$organisation['id'];
$photoBase = "/compte/suivi/photo?o=$o&id=" . (int)$link['id'] . '&m=';
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <div><span class="pastille"><?= e(LINK_KINDS[$link['kind']][0] ?? $link['kind']) ?> · <?= e($link['reference']) ?></span>
                <h1><?= e($link['animal_name']) ?></h1></div>
            <a class="bouton petit secondaire" href="/compte/suivis?o=<?= $o ?>">← Tous les suivis</a>
        </div>
        <p class="discret"><?= $link['recipient_name'] !== '' ? e($link['recipient_name']) . ' · ' : '' ?><?= $link['recipient_email'] !== '' ? e($link['recipient_email']) . ' · ' : '' ?>
            <?= e(link_status($link)) ?> · <?= $link['opened_at'] ? 'lien ouvert le ' . e(format_date($link['opened_at'])) : 'lien pas encore ouvert' ?></p>

        <div class="messages-demande" style="margin-top:18px">
            <?php if (!$messages): ?><p class="discret">Pas encore de nouvelles.</p><?php endif ?>
            <?php foreach ($messages as $m): ?>
                <div class="bulle <?= $m['author'] === 'structure' ? 'equipe' : '' ?>">
                    <div class="auteur"><?= e($m['author_name'] ?: ($m['author'] === 'structure' ? $organisation['name'] : 'La personne')) ?>
                        · <?= e(LINK_MESSAGE_KINDS[$m['kind']] ?? 'Réponse') ?> · <?= e(format_date($m['created_at'], true)) ?></div>
                    <?php if ($m['weight_kg'] !== null): ?><p><strong><?= e(str_replace('.', ',', (string)(float)$m['weight_kg'])) ?> kg</strong></p><?php endif ?>
                    <?php if ($m['photo']): ?><p><a href="<?= e($photoBase . (int)$m['id']) ?>"><img src="<?= e($photoBase . (int)$m['id']) ?>" alt="Photo envoyée" style="max-width:320px;width:100%;border-radius:10px"></a></p><?php endif ?>
                    <?php if ($m['body'] !== ''): ?><div><?= nl2br(e($m['body'])) ?></div><?php endif ?>
                </div>
            <?php endforeach ?>
        </div>

        <?php if ($canWrite): ?>
            <form class="carte" method="post" action="/compte/suivi?o=<?= $o ?>&amp;id=<?= (int)$link['id'] ?>" style="margin-top:18px">
                <?= csrf_field() ?><input type="hidden" name="action" value="repondre">
                <label for="reponse">Répondre ou donner un conseil</label>
                <textarea id="reponse" name="body" rows="4" required></textarea>
                <div class="actions-formulaire"><button class="bouton" type="submit">Envoyer</button>
                    <?= $link['recipient_email'] !== '' ? '<span class="discret petit">La personne est prévenue par e-mail.</span>' : '' ?></div>
            </form>
            <?php if (link_is_open($link)): ?>
                <form method="post" action="/compte/suivi?o=<?= $o ?>&amp;id=<?= (int)$link['id'] ?>" onsubmit="return confirm('Terminer ce suivi ? La personne ne pourra plus rien envoyer.')" style="margin-top:12px">
                    <?= csrf_field() ?><input type="hidden" name="action" value="fermer">
                    <button class="bouton petit danger" type="submit">Terminer le suivi</button>
                </form>
            <?php endif ?>
        <?php endif ?>
    </div>
</section>
