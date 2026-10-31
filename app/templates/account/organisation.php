<?php /** @var array $organisation @var bool $manage @var array $members @var array $invitations @var ?array $licence @var array $usage @var ?string $newLink @var array $user */
$id = (int)$organisation['id'];
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <div><span class="pastille"><?= e($organisation['kind']) ?></span><h1><?= e($organisation['name']) ?></h1></div>
            <a class="bouton petit secondaire" href="/compte">← Mon compte</a>
        </div>

        <?php if ($newLink): ?>
            <div class="message ok">
                Invitation envoyée par e-mail. Vous pouvez aussi transmettre ce lien vous-même (valable 14 jours, utilisable une fois) :
                <div class="lien-copie" style="margin-top:8px"><?= e($newLink) ?></div>
            </div>
        <?php endif ?>

        <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
            <div class="carte">
                <h3>Licence</h3>
                <?php if ($licence): ?>
                    <p><strong><?= e($licence['plan_name']) ?></strong>
                        · <span class="pastille <?= licence_effective_status($licence) === 'active' ? '' : 'orange' ?>"><?= e(LICENCE_STATUSES[licence_effective_status($licence)] ?? $licence['status']) ?></span></p>
                    <p class="discret petit">
                        <?= (int)$usage['present'] ?> <?= $usage['present'] > 1 ? 'animaux présents' : 'animal présent' ?>
                        <?= $licence['max_animals'] === null ? '· animaux illimités' : 'sur ' . (int)$licence['max_animals'] . ' permis par la formule' ?><br>
                        Depuis le <?= e(format_date($licence['starts_at'])) ?><?= $licence['ends_at'] ? ' · jusqu’au ' . e(format_date($licence['ends_at'])) : '' ?>
                    </p>
                    <?php if ($licence['max_animals'] !== null && $usage['present'] > (int)$licence['max_animals']): ?>
                        <div class="message error petit">Votre structure suit plus d’animaux que sa formule n’en permet : passez à une formule supérieure.</div>
                    <?php endif ?>
                    <?php if ($notice = licence_notice($licence)): ?>
                        <div class="message <?= $notice[0] ?> petit"><?= e($notice[1]) ?></div>
                    <?php elseif (licence_is_read_only($licence)): ?>
                        <div class="message error petit">Licence <?= e(mb_strtolower(LICENCE_STATUSES[licence_effective_status($licence)] ?? '')) ?> : vos appareils lisent encore les données mais ne peuvent plus en envoyer.</div>
                    <?php endif ?>
                <?php endif ?>
                <p class="discret petit">Pour changer de formule, <a href="/compte/assistance">écrivez-nous</a> (le paiement en ligne arrive bientôt).</p>
            </div>
            <div class="carte">
                <h3>Informations</h3>
                <?php if ($manage): ?>
                    <form method="post" action="/compte/structure?id=<?= $id ?>">
                        <?= csrf_field() ?><input type="hidden" name="action" value="infos">
                        <label for="org-name">Nom</label><input type="text" id="org-name" name="name" value="<?= e($organisation['name']) ?>">
                        <label for="org-email">E-mail de contact</label><input type="email" id="org-email" name="contact_email" value="<?= e($organisation['contact_email']) ?>">
                        <label for="org-phone">Téléphone</label><input type="tel" id="org-phone" name="phone" value="<?= e($organisation['phone']) ?>">
                        <label for="org-address">Adresse</label><input type="text" id="org-address" name="address" value="<?= e($organisation['address']) ?>">
                        <label for="org-siret">SIRET ou n° RNA</label><input type="text" id="org-siret" name="siret" value="<?= e($organisation['siret']) ?>">
                        <div class="actions-formulaire"><button class="bouton petit" type="submit">Enregistrer</button></div>
                    </form>
                <?php else: ?>
                    <p class="discret petit">Vos rôles : <?= e(role_labels($organisation['roles'])) ?></p>
                <?php endif ?>
            </div>
        </div>

        <h2 style="margin-top:32px">Membres</h2>
        <div class="defilement"><table class="tableau">
            <tr><th>Nom</th><th>Adresse</th><th>Rôles</th><th>Dernière connexion</th><?php if ($manage): ?><th></th><?php endif ?></tr>
            <?php foreach ($members as $member): ?>
                <tr>
                    <td><?= e($member['name']) ?><?= (int)$member['user_id'] === (int)$user['id'] ? ' <span class="pastille gris">vous</span>' : '' ?></td>
                    <td><?= e($member['email']) ?></td>
                    <td><?= e(role_labels($member['roles'])) ?></td>
                    <td><?= e(format_date($member['last_login_at'], true)) ?></td>
                    <?php if ($manage): ?>
                        <td>
                            <details>
                                <summary>Modifier</summary>
                                <form method="post" action="/compte/structure?id=<?= $id ?>">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="roles"><input type="hidden" name="member" value="<?= (int)$member['user_id'] ?>">
                                    <?php foreach (ROLES as $key => [$label]): ?>
                                        <label class="case"><input type="checkbox" name="roles[]" value="<?= $key ?>" <?= in_array($key, explode(',', $member['roles']), true) ? 'checked' : '' ?>> <?= e($label) ?></label>
                                    <?php endforeach ?>
                                    <button class="bouton petit" type="submit">Enregistrer les rôles</button>
                                </form>
                                <form method="post" action="/compte/structure?id=<?= $id ?>" onsubmit="return confirm('Retirer ce membre de la structure ?')">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="retirer"><input type="hidden" name="member" value="<?= (int)$member['user_id'] ?>">
                                    <button class="bouton petit danger" type="submit" style="margin-top:8px">Retirer de la structure</button>
                                </form>
                            </details>
                        </td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
        </table></div>

        <?php if ($manage): ?>
            <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-top:24px">
                <form class="carte" method="post" action="/compte/structure?id=<?= $id ?>">
                    <h3>Inviter quelqu’un</h3>
                    <?= csrf_field() ?><input type="hidden" name="action" value="inviter">
                    <label for="invite-email">Adresse e-mail</label>
                    <input type="email" id="invite-email" name="email" required placeholder="prenom.nom@exemple.fr">
                    <label>Ce qu’elle pourra faire</label>
                    <?php foreach (ROLES as $key => [$label, $detail]): ?>
                        <label class="case"><input type="checkbox" name="roles[]" value="<?= $key ?>" <?= $key === 'soins' ? 'checked' : '' ?>> <span><strong><?= e($label) ?></strong> — <span class="discret"><?= e($detail) ?></span></span></label>
                    <?php endforeach ?>
                    <div class="actions-formulaire"><button class="bouton" type="submit">Envoyer l’invitation</button></div>
                </form>
                <div class="carte">
                    <h3>Invitations en attente</h3>
                    <?php if (!$invitations): ?>
                        <p class="discret">Aucune.</p>
                    <?php endif ?>
                    <?php foreach ($invitations as $invitation): ?>
                        <p><?= e($invitation['email']) ?> · <span class="discret petit"><?= e(role_labels($invitation['roles'])) ?>, jusqu’au <?= e(format_date($invitation['expires_at'])) ?></span>
                            <form class="en-ligne-form" method="post" action="/compte/structure?id=<?= $id ?>">
                                <?= csrf_field() ?><input type="hidden" name="action" value="annuler"><input type="hidden" name="invitation" value="<?= (int)$invitation['id'] ?>">
                                <button class="bouton petit danger" type="submit">Annuler</button>
                            </form>
                        </p>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>
    </div>
</section>
