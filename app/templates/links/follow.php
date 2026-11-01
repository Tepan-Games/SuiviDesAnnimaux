<?php /** @var array $link @var string $token @var array $messages @var array $card @var ?string $error */
$open = link_is_open($link);
$days = link_days_left($link);
$self = '/suivi?c=' . $token;
$labels = ['species' => 'Espèce', 'sex' => 'Sexe', 'breed' => 'Race', 'color' => 'Robe', 'birthDate' => 'Naissance',
    'chip' => 'Identification', 'sterilized' => 'Stérilisé', 'weightKg' => 'Dernier poids (kg)', 'diet' => 'Alimentation',
    'allergies' => 'Allergies', 'notes' => 'À savoir'];
$lists = ['vaccines' => 'Vaccins', 'treatments' => 'Traitements en cours', 'advice' => 'Conseils de la structure'];
$requests = array_filter(explode(',', $link['requests']));
?>
<section class="page">
    <div class="contenu etroit" style="width:min(760px,100% - 32px)">
        <div class="titre-page">
            <div><span class="pastille">Suivi <?= e($link['reference']) ?></span><h1><?= e($link['animal_name']) ?></h1></div>
        </div>
        <div class="message <?= $open ? 'ok' : '' ?>" style="<?= $open ? '' : 'background:var(--fond)' ?>">
            <strong><?= e(link_remaining_text($link)) ?></strong> avec <strong><?= e($link['organisation_name']) ?></strong>
            <?php if ($open && $requests): ?><br><span class="petit">Ce que la structure aimerait recevoir : <?= e(implode(', ', array_map(fn($r) => mb_strtolower(LINK_REQUESTS[$r] ?? $r), $requests))) ?>.</span><?php endif ?>
            <?php if ($open && $days !== null && $days > 0): ?>
                <div style="background:rgba(0,0,0,.08);border-radius:99px;height:8px;margin-top:10px;overflow:hidden" aria-hidden="true">
                    <?php $total = max(1, (strtotime($link['ends_at']) - strtotime($link['created_at'])) / 86400); ?>
                    <div style="background:var(--vert);height:100%;width:<?= (int)round(100 * (1 - $days / $total)) ?>%"></div>
                </div>
            <?php endif ?>
        </div>
        <?php if ($error): ?><div class="message error"><?= e($error) ?></div><?php endif ?>

        <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr));align-items:start">
            <div class="carte">
                <h3>Sa fiche</h3>
                <?php $shown = false; foreach ($labels as $key => $label): if (isset($card[$key]) && is_scalar($card[$key]) && (string)$card[$key] !== ''): $shown = true ?>
                    <p class="petit" style="margin:4px 0"><span class="discret"><?= e($label) ?> :</span> <?= e(is_bool($card[$key]) ? ($card[$key] ? 'oui' : 'non') : (string)$card[$key]) ?></p>
                <?php endif; endforeach ?>
                <?php foreach ($lists as $key => $label): if (!empty($card[$key]) && is_array($card[$key])): $shown = true ?>
                    <p class="petit" style="margin:10px 0 2px"><strong><?= e($label) ?></strong></p>
                    <ul class="petit"><?php foreach ($card[$key] as $item): ?>
                        <li><?= e(is_array($item) ? implode(' · ', array_filter(array_map(fn($v) => is_scalar($v) ? (string)$v : '', $item))) : (string)$item) ?></li>
                    <?php endforeach ?></ul>
                <?php endif; endforeach ?>
                <?php if (!$shown): ?><p class="discret petit"><?= e($link['species'] ?: 'Fiche transmise par la structure avec le logiciel.') ?></p><?php endif ?>
                <h3 style="margin-top:18px">La structure</h3>
                <p class="petit"><?= e($link['organisation_name']) ?><br>
                    <?= $link['phone'] !== '' ? e($link['phone']) . '<br>' : '' ?>
                    <?= $link['contact_email'] !== '' ? '<a href="mailto:' . e($link['contact_email']) . '">' . e($link['contact_email']) . '</a><br>' : '' ?>
                    <?= $link['address'] !== '' ? '<span class="discret">' . e($link['address']) . '</span>' : '' ?></p>
            </div>
            <?php if ($open): ?>
                <form class="carte" method="post" action="<?= e($self) ?>" enctype="multipart/form-data">
                    <h3>Donner des nouvelles</h3>
                    <?= csrf_field() ?>
                    <div class="choix">
                        <label><input type="radio" name="kind" value="nouvelle" checked> Nouvelles</label>
                        <label><input type="radio" name="kind" value="question"> Une question</label>
                    </div>
                    <label for="suivi-texte">Message</label>
                    <textarea id="suivi-texte" name="body" rows="4" placeholder="Comment se passent ses premiers jours ?"></textarea>
                    <label for="suivi-poids">Poids (kg, facultatif)</label>
                    <input type="text" id="suivi-poids" name="weight" inputmode="decimal" placeholder="4,2">
                    <label for="suivi-photo">Photo (facultatif)</label>
                    <input type="file" id="suivi-photo" name="photo" accept="image/jpeg,image/png,image/webp">
                    <div class="actions-formulaire"><button class="bouton" type="submit">Envoyer à la structure</button></div>
                </form>
            <?php endif ?>
        </div>

        <h2 style="margin-top:28px">Échanges</h2>
        <div class="messages-demande">
            <?php if (!$messages): ?><p class="discret">Rien pour le moment.</p><?php endif ?>
            <?php foreach ($messages as $m): ?>
                <div class="bulle <?= $m['author'] === 'structure' ? 'equipe' : '' ?>">
                    <div class="auteur"><?= e($m['author'] === 'structure' ? $link['organisation_name'] : 'Vous') ?> · <?= e(format_date($m['created_at'], true)) ?></div>
                    <?php if ($m['weight_kg'] !== null): ?><p><strong><?= e(str_replace('.', ',', (string)(float)$m['weight_kg'])) ?> kg</strong></p><?php endif ?>
                    <?php if ($m['photo']): ?><p><img src="<?= e($self . '&photo=' . (int)$m['id']) ?>" alt="Photo envoyée" style="max-width:320px;width:100%;border-radius:10px"></p><?php endif ?>
                    <?php if ($m['body'] !== ''): ?><div><?= nl2br(e($m['body'])) ?></div><?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
        <p class="discret petit" style="margin-top:24px">Avec le logiciel ou l’application <a href="/telecharger">Suivi des animaux</a>,
            ce lien (ou son QR code) ajoute <?= e($link['animal_name']) ?> chez vous avec sa fiche, et le suivi s’affiche sur sa fiche.
            Gardez ce lien pour vous : il donne accès au suivi.</p>
    </div>
</section>
