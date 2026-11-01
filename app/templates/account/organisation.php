<?php /** @var array $organisation @var bool $manage @var array $members @var array $invitations @var ?array $licence @var ?string $newLink @var array $user */
$id = (int)$organisation['id'];
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <div><span class="pastille"><?= e($organisation['kind']) ?></span><h1><?= e($organisation['name']) ?></h1></div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <?php if (can_see_links($organisation['roles'])): ?><a class="bouton petit" href="/compte/suivis?o=<?= $id ?>">Suivis des animaux confiés</a><?php endif ?>
                <?php if (can_see_links($organisation['roles'])): ?><a class="bouton petit secondaire" href="/compte/questions?o=<?= $id ?>">Questions reçues</a><?php endif ?>
                <a class="bouton petit secondaire" href="/compte">← Mon compte</a>
            </div>
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
                        · <span class="pastille <?= $licence['status'] === 'active' ? '' : 'orange' ?>"><?= e(LICENCE_STATUSES[$licence['status']] ?? $licence['status']) ?></span></p>
                    <p class="discret petit">
                        <?= $licence['max_animals'] === null ? 'Animaux illimités' : 'Jusqu’à ' . (int)$licence['max_animals'] . ' animaux suivis' ?><br>
                        Depuis le <?= e(format_date($licence['starts_at'])) ?><?= $licence['ends_at'] ? ' · jusqu’au ' . e(format_date($licence['ends_at'])) : '' ?>
                    </p>
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
            <?php if ($manage): ?>
                <form class="carte" method="post" action="/compte/structure?id=<?= $id ?>">
                    <h3>Annuaire et questions</h3>
                    <?= csrf_field() ?><input type="hidden" name="action" value="annuaire">
                    <label class="case"><input type="checkbox" name="directory_listed" value="1" <?= $organisation['directory_listed'] ? 'checked' : '' ?>>
                        <span><strong>Apparaître dans l’annuaire</strong> — <span class="discret">page publique et recherche « À proximité » du logiciel et de l’application (nom, type, adresse, téléphone, site)</span></span></label>
                    <label class="case"><input type="checkbox" name="accepts_questions" value="1" <?= $organisation['accepts_questions'] ? 'checked' : '' ?>>
                        <span><strong>Accepter les questions</strong> — <span class="discret">le public peut vous écrire par le site ; les questions arrivent à l’adresse de contact</span></span></label>
                    <label for="org-description">Présentation (600 caractères)</label>
                    <textarea id="org-description" name="description" rows="3" maxlength="600"><?= e($organisation['description']) ?></textarea>
                    <label for="org-species">Animaux accueillis</label><input type="text" id="org-species" name="species" value="<?= e($organisation['species']) ?>" placeholder="Chats, chiens, lapins…">
                    <label for="org-website">Site internet</label><input type="text" id="org-website" name="website" value="<?= e($organisation['website']) ?>">
                    <p class="discret petit"><?= $organisation['latitude'] !== null ? 'Adresse située : ' . e($organisation['city']) . '.' : 'L’adresse (carte Informations) sert à vous situer dans la recherche « À proximité ».' ?></p>
                    <div class="actions-formulaire"><button class="bouton petit" type="submit">Enregistrer</button>
                        <?php if ($organisation['directory_listed']): ?><a class="petit" href="/structure?id=<?= $id ?>">Voir la page publique</a><?php endif ?></div>
                </form>
            <?php endif ?>
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
