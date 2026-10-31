<?php /** @var array $organisation @var array $state @var array $contacts @var string $search @var string $role @var array $roles @var bool $canWrite */
$o = (int)$organisation['id'];
$tab = 'contacts';
?>
<section class="page">
    <div class="contenu">
        <?php require __DIR__ . '/_title.php' ?>
        <?php require __DIR__ . '/_header.php' ?>
        <div class="titre-section">
            <form class="filtres" method="get" action="/application/contacts">
                <input type="hidden" name="o" value="<?= $o ?>">
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Nom, téléphone, e-mail, adresse" aria-label="Rechercher">
                <select name="role" aria-label="Rôle">
                    <option value="">Tous les rôles</option>
                    <?php foreach ($roles as $value): ?><option value="<?= e($value) ?>" <?= $role === $value ? 'selected' : '' ?>><?= e($value) ?></option><?php endforeach ?>
                </select>
                <button class="bouton petit" type="submit">Afficher</button>
            </form>
            <?php if ($canWrite): ?><a class="bouton petit" href="/application/fiche?o=<?= $o ?>&amp;t=Contacts">Ajouter un contact</a><?php endif ?>
        </div>
        <?php if (!$contacts): ?>
            <div class="carte"><p class="discret">Aucun contact.</p></div>
        <?php else: ?>
            <div class="defilement"><table class="tableau">
                <tr><th>Nom</th><th>Rôle</th><th>Téléphone</th><th>E-mail</th><th>Adresse</th><th></th></tr>
                <?php foreach ($contacts as $id => $contact): ?>
                    <tr><td><strong><?= e($contact['Name'] ?? '') ?></strong><?= ($contact['Notes'] ?? '') !== '' ? '<br><span class="discret petit">' . nl2br(e($contact['Notes'])) . '</span>' : '' ?></td>
                        <td><?= e($contact['Role'] ?? '') ?></td>
                        <td><?= ($contact['Phone'] ?? '') !== '' ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', (string)$contact['Phone'])) . '">' . e($contact['Phone']) . '</a>' : '' ?></td>
                        <td><?= ($contact['Email'] ?? '') !== '' ? '<a href="mailto:' . e($contact['Email']) . '">' . e($contact['Email']) . '</a>' : '' ?></td>
                        <td class="petit"><?= nl2br(e($contact['Address'] ?? '')) ?></td>
                        <td><?php if ($canWrite): ?><a class="bouton petit secondaire" href="/application/fiche?o=<?= $o ?>&amp;t=Contacts&amp;id=<?= e(rawurlencode((string)$id)) ?>">Modifier</a><?php endif ?></td></tr>
                <?php endforeach ?>
            </table></div>
        <?php endif ?>
    </div>
</section>
