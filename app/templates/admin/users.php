<?php /** @var array $users @var string $search */ ?>
<section class="page">
    <div class="contenu">
        <div class="titre-page">
            <h1>Comptes</h1>
            <form method="get" action="/admin/utilisateurs" style="display:flex;gap:8px"><input type="text" name="q" value="<?= e($search) ?>" placeholder="Nom ou adresse e-mail"><button class="bouton petit" type="submit">Chercher</button></form>
        </div>
        <?php require __DIR__ . '/_nav.php' ?>
        <div class="defilement"><table class="tableau">
            <tr><th>Nom</th><th>Adresse</th><th>État</th><th>Créé le</th><th>Dernière connexion</th><th></th></tr>
            <?php foreach ($users as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?><?= (int)$row['is_admin'] ? ' <span class="pastille">admin</span>' : '' ?></td>
                    <td><?= e($row['email']) ?></td>
                    <td><?php if ($row['disabled_at']): ?><span class="pastille rouge">désactivé</span>
                        <?php elseif (!$row['email_verified_at']): ?><span class="pastille orange">non confirmé</span>
                        <?php else: ?><span class="pastille">actif</span><?php endif ?></td>
                    <td><?= e(format_date($row['created_at'])) ?></td>
                    <td><?= e(format_date($row['last_login_at'], true)) ?></td>
                    <td>
                        <form class="en-ligne-form" method="post" action="/admin/utilisateurs">
                            <?= csrf_field() ?><input type="hidden" name="user" value="<?= (int)$row['id'] ?>"><input type="hidden" name="q" value="<?= e($search) ?>">
                            <?php if (!$row['email_verified_at']): ?><button class="bouton petit secondaire" name="action" value="confirmer">Confirmer</button><?php endif ?>
                            <?php if ($row['disabled_at']): ?><button class="bouton petit secondaire" name="action" value="reactiver">Réactiver</button>
                            <?php else: ?><button class="bouton petit danger" name="action" value="desactiver">Désactiver</button><?php endif ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach ?>
        </table></div>
    </div>
</section>
