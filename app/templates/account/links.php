<?php /** @var array $organisation @var array $links @var bool $canWrite @var array $kinds @var ?array $created @var array $errors @var array $values */
$o = (int)$organisation['id'];
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <div><span class="pastille"><?= e($organisation['name']) ?></span><h1>Suivis des animaux confiés</h1></div>
            <a class="bouton petit secondaire" href="/compte/structure?id=<?= $o ?>">← La structure</a>
        </div>
        <p class="discret">Un lien à identifiant unique, avec son QR code, remis à l’adoptant, à la famille d’accueil ou au
            particulier : sans créer de compte, la personne retrouve la fiche de l’animal, envoie des nouvelles, une pesée, une
            photo, pose ses questions, et tout arrive ici. Avec le logiciel ou l’application, le lien ajoute aussi l’animal chez elle.</p>

        <?php if ($created): ?>
            <div class="carte" style="margin:18px 0">
                <h2 style="margin-top:0">Suivi de <?= e($created['animal']) ?> créé · <?= e($created['reference']) ?></h2>
                <div class="grille" style="grid-template-columns:auto 1fr;align-items:start">
                    <div class="qr-code" data-qr="<?= e($created['url']) ?>" role="img" aria-label="QR code du suivi"></div>
                    <div>
                        <p>À scanner avec le téléphone de la personne, à imprimer avec le contrat, ou à lui envoyer :</p>
                        <div class="lien-copie"><?= e($created['url']) ?></div>
                        <p class="discret petit"><?= $created['email'] !== '' ? 'Le lien a aussi été envoyé à ' . e($created['email']) . '.' : 'Aucune adresse e-mail donnée : remettez le lien ou le QR code vous-même.' ?>
                            Il n’est affiché qu’une fois : il donne accès au suivi, comme une clé.</p>
                        <button class="bouton petit secondaire" type="button" onclick="window.print()">Imprimer</button>
                    </div>
                </div>
                <script src="/assets/js/qrcode.js"></script>
                <script src="/assets/js/appareils.js"></script>
            </div>
        <?php endif ?>

        <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr));align-items:start">
            <div>
                <h2>Suivis</h2>
                <?php if (!$links): ?><p class="discret">Aucun suivi pour le moment.</p><?php endif ?>
                <?php foreach ($links as $link): ?>
                    <a class="carte" style="display:block;margin-bottom:10px;text-decoration:none;color:inherit" href="/compte/suivi?o=<?= $o ?>&amp;id=<?= (int)$link['id'] ?>">
                        <strong><?= e($link['animal_name']) ?></strong><?= $link['species'] !== '' ? ' <span class="discret">· ' . e($link['species']) . '</span>' : '' ?>
                        <?php if ((int)$link['unread'] > 0): ?> <span class="pastille orange"><?= (int)$link['unread'] ?> nouveau<?= (int)$link['unread'] > 1 ? 'x' : '' ?></span><?php endif ?>
                        <div class="discret petit"><?= e(LINK_KINDS[$link['kind']][0] ?? $link['kind']) ?><?= $link['recipient_name'] !== '' ? ' · ' . e($link['recipient_name']) : '' ?>
                            · <?= e($link['reference']) ?> · <?= e(link_status($link)) ?>
                            <?= $link['opened_at'] === null ? ' · <span class="pastille gris">lien pas encore ouvert</span>' : '' ?>
                            <?= $link['last_news_at'] ? ' · dernières nouvelles le ' . e(format_date($link['last_news_at'])) : '' ?></div>
                    </a>
                <?php endforeach ?>
            </div>
            <?php if ($canWrite): ?>
                <form class="carte" method="post" action="/compte/suivis?o=<?= $o ?>">
                    <h3>Nouveau suivi</h3>
                    <?= csrf_field() ?>
                    <?php if ($errors): ?><div class="message error"><?= e(implode(' ', $errors)) ?></div><?php endif ?>
                    <label>L’animal part</label>
                    <div class="choix">
                        <?php foreach ($kinds as $key): [$label] = LINK_KINDS[$key] ?>
                            <label><input type="radio" name="kind" value="<?= $key ?>" <?= $values['kind'] === $key ? 'checked' : '' ?>> <?= e($label) ?></label>
                        <?php endforeach ?>
                    </div>
                    <label for="lien-animal">Nom de l’animal</label><input type="text" id="lien-animal" name="animal_name" required value="<?= e($values['animal_name']) ?>">
                    <label for="lien-espece">Espèce</label><input type="text" id="lien-espece" name="species" value="<?= e($values['species']) ?>" placeholder="Chat, Chien, Lapin…">
                    <label for="lien-nom">Nom de la personne</label><input type="text" id="lien-nom" name="recipient_name" value="<?= e($values['recipient_name']) ?>">
                    <label for="lien-email">Son adresse e-mail (facultatif : le lien lui est envoyé)</label><input type="email" id="lien-email" name="recipient_email" value="<?= e($values['recipient_email']) ?>">
                    <label for="lien-duree">Durée du suivi</label>
                    <select id="lien-duree" name="months">
                        <?php foreach (LINK_DURATIONS as $months => $label): ?>
                            <option value="<?= $months ?>" <?= (string)$months === (string)$values['months'] ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach ?>
                    </select>
                    <label>Ce que vous demandez</label>
                    <?php foreach (LINK_REQUESTS as $key => $label): ?>
                        <label class="case"><input type="checkbox" name="requests[]" value="<?= $key ?>" <?= in_array($key, $values['requests'], true) ? 'checked' : '' ?>> <?= e($label) ?></label>
                    <?php endforeach ?>
                    <p class="discret petit">Depuis le logiciel, la fiche de l’animal (identification, vaccins, traitements) est jointe au suivi.</p>
                    <div class="actions-formulaire"><button class="bouton" type="submit">Créer le lien et le QR code</button></div>
                </form>
            <?php elseif ($kinds === []): ?>
                <div class="carte"><p class="discret"><?= e(link_kind_refusal($organisation)) ?></p></div>
            <?php endif ?>
        </div>
    </div>
</section>
