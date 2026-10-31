<?php /** @var array $organisation @var array $state @var string $table @var array $form @var array $fields @var array $values @var array $refs
 * @var ?string $id @var ?array $row @var ?array $animal @var ?string $animalId @var array $errors @var bool $canDelete @var string $back */
$o = (int)$organisation['id'];
$multipart = (bool)array_filter($fields, fn($f) => $f[1] === 'file');
$action = '/application/fiche?o=' . $o . '&t=' . rawurlencode($table) . ($id !== null ? '&id=' . rawurlencode($id) : '')
    . ($id === null && $animalId !== null ? '&a=' . rawurlencode($animalId) : '');
$section = null;
?>
<section class="page">
    <div class="contenu etroit-large">
        <div class="titre-page">
            <div><span class="pastille"><?= e($organisation['name']) ?><?= $animal ? ' · ' . e($animal['Name'] ?? '') : '' ?></span>
                <h1><?= $id === null ? 'Ajouter' : 'Modifier' ?> : <?= e(mb_strtolower($form['title'])) ?></h1></div>
            <a class="bouton petit secondaire" href="<?= e($back) ?>">← Retour</a>
        </div>
        <?php if ($errors): ?>
            <div class="message error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach ?></ul></div>
        <?php endif ?>
        <form method="post" action="<?= e($action) ?>" class="carte fiche-saisie"<?= $multipart ? ' enctype="multipart/form-data"' : '' ?>>
            <?= csrf_field() ?>
            <div class="champs-saisie">
            <?php foreach ($fields as $column => $field):
                [$label, $type] = $field;
                $value = $values[$column] ?? null;
                $name = e($column);
                $fieldId = 'champ-' . $name;
                $required = !empty($field['required']) && $type !== 'file' ? ' required' : '';
                if (isset($field['section']) && $field['section'] !== $section):
                    $section = $field['section']; ?>
                    </div><h2 class="section-saisie"><?= e($section) ?></h2><div class="champs-saisie">
                <?php endif ?>
                <div class="champ<?= $type === 'textarea' ? ' large' : '' ?>">
                    <?php if ($type === 'bool'): ?>
                        <label class="case"><input type="checkbox" name="<?= $name ?>" value="1" <?= !empty($value) ? 'checked' : '' ?>> <?= e($label) ?></label>
                    <?php else: ?>
                        <label for="<?= $fieldId ?>"><?= e($label) ?><?= !empty($field['required']) ? ' *' : '' ?></label>
                        <?php switch ($type):
                            case 'textarea': ?>
                                <textarea id="<?= $fieldId ?>" name="<?= $name ?>" rows="3"<?= $required ?>><?= e($value) ?></textarea>
                                <?php break;
                            case 'date': ?>
                                <input type="date" id="<?= $fieldId ?>" name="<?= $name ?>" value="<?= e(substr((string)$value, 0, 10)) ?>"<?= $required ?>>
                                <?php break;
                            case 'datetime': ?>
                                <input type="datetime-local" id="<?= $fieldId ?>" name="<?= $name ?>" value="<?= e($value ? date('Y-m-d\TH:i', strtotime((string)$value)) : '') ?>"<?= $required ?>>
                                <?php break;
                            case 'int': ?>
                                <input type="number" step="1" id="<?= $fieldId ?>" name="<?= $name ?>" value="<?= e($value) ?>"<?= $required ?>>
                                <?php break;
                            case 'number': ?>
                                <input type="text" inputmode="decimal" id="<?= $fieldId ?>" name="<?= $name ?>" value="<?= e($value === null ? '' : online_number($value, 3)) ?>"<?= $required ?>>
                                <?php break;
                            case 'tristate': ?>
                                <select id="<?= $fieldId ?>" name="<?= $name ?>">
                                    <?php foreach (['' => 'Non renseigné', 'Oui' => 'Oui', 'Non' => 'Non'] as $option => $text):
                                        $selected = match ($option) { 'Oui' => $value !== null && (int)$value === 1, 'Non' => $value !== null && $value !== '' && (int)$value === 0, default => $value === null || $value === '' }; ?>
                                        <option value="<?= e($option) ?>" <?= $selected ? 'selected' : '' ?>><?= e($text) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <?php break;
                            case 'select':
                                $options = isset($field['choices']) ? ($field['choices'])($animal, $row[$column] ?? null) : $field['options'];
                                if ((string)$value !== '' && !in_array((string)$value, $options, true)) {
                                    $options[] = (string)$value;
                                } ?>
                                <select id="<?= $fieldId ?>" name="<?= $name ?>"<?= $required ?>>
                                    <?php foreach ($options as $option): ?>
                                        <option value="<?= e($option) ?>" <?= (string)$value === $option ? 'selected' : '' ?>><?= e($option === '' ? '—' : $option) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <?php break;
                            case 'ref': ?>
                                <select id="<?= $fieldId ?>" name="<?= $name ?>"<?= $required ?>>
                                    <option value="">—</option>
                                    <?php foreach ($refs[$field['ref']] as $refId => $refName): ?>
                                        <option value="<?= e($refId) ?>" <?= (string)$value === (string)$refId ? 'selected' : '' ?>><?= e($refName) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <?php break;
                            case 'file':
                                $current = online_file_name($value); ?>
                                <?php if ($current): ?>
                                    <p class="petit">Fichier actuel : <a href="<?= e(online_file_url($o, $current)) ?>" target="_blank" rel="noopener">ouvrir</a>
                                        <?php if (empty($field['required'])): ?> · <label class="case en-ligne"><input type="checkbox" name="<?= $name ?>__retirer" value="1"> retirer</label><?php endif ?></p>
                                <?php endif ?>
                                <input type="file" id="<?= $fieldId ?>" name="<?= $name ?>"<?= isset($field['accept']) ? ' accept="' . e($field['accept']) . '"' : '' ?><?= !empty($field['required']) && !$current ? ' required' : '' ?>>
                                <?php break;
                            default: ?>
                                <input type="text" id="<?= $fieldId ?>" name="<?= $name ?>" value="<?= e($value) ?>"<?= $required ?>>
                        <?php endswitch ?>
                    <?php endif ?>
                    <?php if (isset($field['help'])): ?><p class="discret petit aide"><?= e($field['help']) ?></p><?php endif ?>
                </div>
            <?php endforeach ?>
            </div>
            <div class="actions-formulaire">
                <button class="bouton" type="submit">Enregistrer</button>
                <a class="bouton secondaire" href="<?= e($back) ?>">Annuler</a>
            </div>
        </form>
        <?php if ($canDelete): ?>
            <form method="post" action="/application/supprimer?o=<?= $o ?>&amp;t=<?= e(rawurlencode($table)) ?>&amp;id=<?= e(rawurlencode((string)$id)) ?>"
                  onsubmit="return confirm(<?= e(json_encode($table === 'Animals'
                      ? 'Supprimer cette fiche et tout son suivi (journal, traitements, photos, documents, dépenses) sur tous les appareils ?'
                      : 'Supprimer cette fiche sur tous les appareils ?', JSON_UNESCAPED_UNICODE)) ?>)">
                <?= csrf_field() ?>
                <button class="bouton danger petit" type="submit">Supprimer<?= $table === 'Animals' ? ' l’animal' : '' ?></button>
            </form>
        <?php endif ?>
        <p class="discret petit">Enregistré sur le site aussitôt ; le logiciel et l’application le reçoivent à leur prochaine synchronisation.</p>
    </div>
</section>
