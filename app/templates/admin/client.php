<?php /** @var array $organisation @var ?array $licence @var array $alerts @var array $usage @var array $plans @var array $members
 * @var array $invitations @var array $devices @var array $tickets @var array $log */
$id = (int)$organisation['id'];
$action = "/admin/client?id=$id";
$hidden = fn(string $action) => csrf_field() . '<input type="hidden" name="action" value="' . e($action) . '">';
?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <div><span class="pastille"><?= e($organisation['kind']) ?></span><h1><?= e($organisation['name']) ?></h1></div>
            <a class="bouton petit secondaire" href="/admin/clients">← Clients</a>
        </div>
        <?php require __DIR__ . '/_nav.php' ?>
        <?php foreach ($alerts as $key => $label): ?>
            <div class="message <?= in_array($key, ['fin_depassee', 'depassement'], true) ? 'error' : 'info' ?>"><?= e(match ($key) {
                'fin_depassee' => "La date de fin de la licence ({$label}) est passée alors qu’elle est encore « " . (LICENCE_STATUSES[$licence['status']] ?? '') . ' » : prolongez-la ou changez son état.',
                'depassement' => "Plus d’animaux présents que la formule n’en permet : $label.",
                'fin_proche' => "La licence se termine bientôt : $label.",
                default => 'Cette structure n’a pas de licence : enregistrez-en une ci-dessous.',
            }) ?></div>
        <?php endforeach ?>

        <div class="chiffres">
            <div class="chiffre"><strong><?= (int)$usage['present'] ?><?= ($licence['max_animals'] ?? null) !== null ? ' / ' . (int)$licence['max_animals'] : '' ?></strong>Animaux présents<?= ($licence['max_animals'] ?? null) === null && $licence ? ' (illimité)' : '' ?></div>
            <div class="chiffre"><strong><?= (int)$usage['animals'] ?></strong>Animaux en tout</div>
            <div class="chiffre"><strong><?= count($members) ?></strong>Membres</div>
            <div class="chiffre"><strong><?= count($devices) ?></strong>Appareils connectés</div>
            <div class="chiffre"><strong><?= e(format_bytes((int)$usage['storage'])) ?></strong>Données synchronisées</div>
        </div>
        <p class="discret petit">Dernières données reçues : <?= $usage['synced_at'] ? e(format_date(date('Y-m-d H:i:s', (int)$usage['synced_at']), true)) : 'jamais' ?></p>

        <div class="grille" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
            <form class="carte" method="post" action="<?= $action ?>">
                <h3>Licence</h3>
                <?= $hidden('licence') ?>
                <label for="plan">Formule</label>
                <select id="plan" name="plan">
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= e($plan['code']) ?>" <?= ($licence['plan_code'] ?? '') === $plan['code'] ? 'selected' : '' ?>><?= e($plan['name']) ?> — <?= e(format_price((int)$plan['monthly_price_cents'])) ?>/mois<?= $plan['active'] ? '' : ' (retirée)' ?></option>
                    <?php endforeach ?>
                </select>
                <label for="status">État</label>
                <select id="status" name="status">
                    <?php foreach (LICENCE_STATUSES as $key => $label): ?><option value="<?= $key ?>" <?= ($licence['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?>
                </select>
                <label for="billing">Facturation</label>
                <select id="billing" name="billing">
                    <option value="mois" <?= ($licence['billing'] ?? 'mois') === 'mois' ? 'selected' : '' ?>>Mensuelle</option>
                    <option value="an" <?= ($licence['billing'] ?? '') === 'an' ? 'selected' : '' ?>>Annuelle</option>
                </select>
                <label for="starts_at">Début</label><input type="date" id="starts_at" name="starts_at" value="<?= e($licence['starts_at'] ?? date('Y-m-d')) ?>">
                <label for="ends_at">Fin <span class="discret petit">(vide : sans fin)</span></label><input type="date" id="ends_at" name="ends_at" value="<?= e($licence['ends_at'] ?? '') ?>">
                <label for="notes">Notes internes</label><textarea id="notes" name="notes"><?= e($licence['notes'] ?? '') ?></textarea>
                <div class="actions-formulaire"><button class="bouton" type="submit">Enregistrer la licence</button></div>
            </form>
            <div class="carte">
                <h3>Actions rapides</h3>
                <?php if ($licence): ?>
                    <p class="petit">Formule <strong><?= e($licence['plan_name']) ?></strong>, <?= e(mb_strtolower(LICENCE_STATUSES[$licence['status']] ?? '')) ?>,
                        <?= $licence['ends_at'] ? 'jusqu’au ' . e(format_date($licence['ends_at'])) : 'sans date de fin' ?>
                        · <?= e(format_price((int)($licence['billing'] === 'an' ? $licence['yearly_price_cents'] : $licence['monthly_price_cents']))) ?><?= $licence['billing'] === 'an' ? ' par an' : ' par mois' ?></p>
                    <div class="boutons">
                        <form method="post" action="<?= $action ?>"><?= $hidden('prolonger') ?><input type="hidden" name="duree" value="mois"><button class="bouton petit secondaire" type="submit">Prolonger d’un mois</button></form>
                        <form method="post" action="<?= $action ?>"><?= $hidden('prolonger') ?><input type="hidden" name="duree" value="an"><button class="bouton petit secondaire" type="submit">Prolonger d’un an</button></form>
                        <?php if ($licence['status'] !== 'active'): ?>
                            <form method="post" action="<?= $action ?>"><?= $hidden('etat') ?><input type="hidden" name="status" value="active"><button class="bouton petit" type="submit">Réactiver</button></form>
                        <?php endif ?>
                        <?php if ($licence['status'] !== 'suspendue'): ?>
                            <form method="post" action="<?= $action ?>" onsubmit="return confirm('Suspendre la licence ? Les appareils ne pourront plus envoyer de données (la lecture reste possible).')"><?= $hidden('etat') ?><input type="hidden" name="status" value="suspendue"><button class="bouton petit danger" type="submit">Suspendre</button></form>
                        <?php endif ?>
                        <?php if ($licence['status'] !== 'resiliee'): ?>
                            <form method="post" action="<?= $action ?>" onsubmit="return confirm('Résilier la licence ?')"><?= $hidden('etat') ?><input type="hidden" name="status" value="resiliee"><button class="bouton petit danger" type="submit">Résilier</button></form>
                        <?php endif ?>
                    </div>
                    <p class="discret petit">Prolonger part de la date de fin (ou d’aujourd’hui si elle est passée) et réactive une licence suspendue, expirée ou résiliée.
                        Suspendue, expirée ou résiliée : les appareils lisent encore les données mais n’en envoient plus.</p>
                <?php else: ?>
                    <p class="discret">Enregistrez d’abord une licence.</p>
                <?php endif ?>
                <h3>Écrire aux responsables</h3>
                <form method="post" action="<?= $action ?>"><?= $hidden('ecrire') ?>
                    <label for="subject">Objet</label><input type="text" id="subject" name="subject" value="Votre licence Suivi des animaux">
                    <label for="body">Message</label><textarea id="body" name="body" rows="5">Bonjour,

</textarea>
                    <div class="actions-formulaire"><button class="bouton petit" type="submit">Envoyer</button></div>
                </form>
            </div>
            <form class="carte" method="post" action="<?= $action ?>">
                <h3>Coordonnées</h3>
                <?= $hidden('infos') ?>
                <label for="org-name">Nom</label><input type="text" id="org-name" name="name" value="<?= e($organisation['name']) ?>">
                <label for="org-kind">Type</label>
                <select id="org-kind" name="kind"><?php foreach (ORGANISATION_KINDS as $kind): ?><option <?= $organisation['kind'] === $kind ? 'selected' : '' ?>><?= e($kind) ?></option><?php endforeach ?></select>
                <label for="org-email">E-mail de contact</label><input type="email" id="org-email" name="contact_email" value="<?= e($organisation['contact_email']) ?>">
                <label for="org-phone">Téléphone</label><input type="tel" id="org-phone" name="phone" value="<?= e($organisation['phone']) ?>">
                <label for="org-address">Adresse</label><input type="text" id="org-address" name="address" value="<?= e($organisation['address']) ?>">
                <label for="org-siret">SIRET ou n° RNA</label><input type="text" id="org-siret" name="siret" value="<?= e($organisation['siret']) ?>">
                <p class="discret petit">Titulaire : <?= e($organisation['owner_name']) ?> — <a href="mailto:<?= e($organisation['owner_email']) ?>"><?= e($organisation['owner_email']) ?></a><br>
                    Créée le <?= e(format_date($organisation['created_at'])) ?></p>
                <div class="actions-formulaire"><button class="bouton petit" type="submit">Enregistrer</button></div>
            </form>
        </div>

        <h2 style="margin-top:28px">Membres</h2>
        <div class="defilement"><table class="tableau">
            <tr><th>Nom</th><th>Adresse</th><th>Rôles</th><th>Dernière connexion</th><th></th></tr>
            <?php foreach ($members as $member): $owner = (int)$member['user_id'] === (int)$organisation['owner_user_id']; ?>
                <tr>
                    <td><?= e($member['name']) ?><?= $owner ? ' <span class="pastille">titulaire</span>' : '' ?></td>
                    <td><?= e($member['email']) ?></td>
                    <td><?= e(role_labels($member['roles'])) ?></td>
                    <td><?= e(format_date($member['last_login_at'], true)) ?></td>
                    <td>
                        <details>
                            <summary>Modifier</summary>
                            <form method="post" action="<?= $action ?>"><?= $hidden('roles') ?><input type="hidden" name="member" value="<?= (int)$member['user_id'] ?>">
                                <?php foreach (ROLES as $key => [$label]): ?>
                                    <label class="case"><input type="checkbox" name="roles[]" value="<?= $key ?>" <?= in_array($key, explode(',', $member['roles']), true) ? 'checked' : '' ?>> <?= e($label) ?></label>
                                <?php endforeach ?>
                                <button class="bouton petit" type="submit">Enregistrer les rôles</button>
                            </form>
                            <?php if (!$owner): ?>
                                <form method="post" action="<?= $action ?>" onsubmit="return confirm('Faire de ce membre le titulaire de la structure ?')"><?= $hidden('titulaire') ?><input type="hidden" name="member" value="<?= (int)$member['user_id'] ?>">
                                    <button class="bouton petit secondaire" type="submit" style="margin-top:8px">Désigner titulaire</button></form>
                                <form method="post" action="<?= $action ?>" onsubmit="return confirm('Retirer ce membre de la structure ?')"><?= $hidden('retirer') ?><input type="hidden" name="member" value="<?= (int)$member['user_id'] ?>">
                                    <button class="bouton petit danger" type="submit" style="margin-top:8px">Retirer de la structure</button></form>
                            <?php endif ?>
                        </details>
                    </td>
                </tr>
            <?php endforeach ?>
        </table></div>
        <form class="carte" method="post" action="<?= $action ?>" style="margin-top:14px">
            <?= $hidden('ajouter') ?>
            <label for="add-email">Ajouter un compte existant</label>
            <input type="email" id="add-email" name="email" placeholder="adresse@exemple.fr">
            <?php foreach (ROLES as $key => [$label]): ?><label class="case"><input type="checkbox" name="roles[]" value="<?= $key ?>"> <?= e($label) ?></label><?php endforeach ?>
            <div class="actions-formulaire"><button class="bouton petit" type="submit">Ajouter</button></div>
        </form>
        <?php if ($invitations): ?>
            <p class="discret petit">Invitations en attente : <?php foreach ($invitations as $i => $invitation): ?><?= $i ? ', ' : '' ?><?= e($invitation['email']) ?> (jusqu’au <?= e(format_date($invitation['expires_at'])) ?>)<?php endforeach ?></p>
        <?php endif ?>

        <h2 style="margin-top:28px">Appareils connectés</h2>
        <?php if (!$devices): ?><p class="discret">Aucun.</p><?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Appareil</th><th>Compte</th><th>Connecté le</th><th>Dernière activité</th></tr>
                <?php foreach ($devices as $device): ?>
                    <tr><td><?= e($device['name']) ?></td><td><?= e($device['email']) ?></td><td><?= e(format_date($device['created_at'], true)) ?></td><td><?= e(format_date($device['last_seen_at'], true)) ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>

        <h2 style="margin-top:28px">Demandes d’assistance</h2>
        <?php if (!$tickets): ?><p class="discret">Aucune.</p><?php endif ?>
        <?php foreach ($tickets as $ticket): ?>
            <p><a href="/admin/ticket?id=<?= (int)$ticket['id'] ?>">n°<?= (int)$ticket['id'] ?> · <?= e($ticket['subject']) ?></a> · <?= e(TICKET_STATUSES[$ticket['status']] ?? '') ?> · <span class="discret petit"><?= e($ticket['user_name']) ?></span></p>
        <?php endforeach ?>
        <h2 style="margin-top:28px">Historique</h2>
        <div class="defilement"><table class="tableau">
            <tr><th>Date</th><th>Par</th><th>Action</th><th>Détail</th></tr>
            <?php foreach ($log as $entry): ?><tr><td><?= e(format_date($entry['created_at'], true)) ?></td><td class="petit"><?= e($entry['email'] ?? '') ?></td><td><?= e($entry['action']) ?></td><td><?= e($entry['details']) ?></td></tr><?php endforeach ?>
            <?php if (!$log): ?><tr><td colspan="4" class="discret">Rien pour le moment.</td></tr><?php endif ?>
        </table></div>
    </div>
</section>
