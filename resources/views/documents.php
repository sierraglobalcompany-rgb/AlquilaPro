<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$scope = $scope === 'lease' ? 'lease' : 'property';
$documents = is_array($documents ?? null) ? $documents : [];
$history = is_array($history ?? null) ? $history : [];
$scopeId = is_int($scopeId ?? null) ? $scopeId : null;
$formValues = is_array($formValues ?? null) ? $formValues : [];
$formErrors = is_array($formErrors ?? null) ? $formErrors : [];
$formValue = static fn (string $key, mixed $default = ''): string => $escape($formValues[$key] ?? $default);
$fieldError = static fn (string $field): ?string => is_string($formErrors[$field] ?? null) ? $formErrors[$field] : null;
$prettyJson = static fn (array $content): string => (string) json_encode(
    $content,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
);
$typeOptions = $scope === 'lease'
    ? ['lease_document' => 'Documento del arrendamiento', 'inventory_initial' => 'Inventario inicial', 'inventory_final' => 'Inventario final', 'other' => 'Otro']
    : ['property_document' => 'Documento de la propiedad', 'manual_convivencia' => 'Manual de convivencia', 'other' => 'Otro'];
?>
<section class="surface">
    <p class="eyebrow">Administración</p>
    <h1>Documentos privados</h1>
    <p class="hint">Consulta versiones vigentes e historial. Los archivos se entregan mediante una ruta autenticada.</p>
    <form class="stack-form" method="get" action="<?= $escape($basePath) ?>/admin/documents">
        <label>Ámbito
            <select id="document-scope" name="scope" aria-invalid="<?= $fieldError('scope') !== null ? 'true' : 'false' ?>"<?= $fieldError('scope') !== null ? ' aria-describedby="document-scope-error"' : '' ?>>
                <option value="property" <?= $scope === 'property' ? 'selected' : '' ?>>Propiedad</option>
                <option value="lease" <?= $scope === 'lease' ? 'selected' : '' ?>>Arrendamiento</option>
            </select>
            <?php if ($fieldError('scope') !== null): ?><span class="form-field-error" id="document-scope-error"><?= $escape($fieldError('scope')) ?></span><?php endif; ?>
        </label>
        <label>ID de propiedad o arrendamiento
            <input id="document-scope_id" type="number" name="id" min="1" value="<?= $formValue('scope_id', $scopeId ?? '') ?>" required aria-invalid="<?= $fieldError('scope_id') !== null ? 'true' : 'false' ?>"<?= $fieldError('scope_id') !== null ? ' aria-describedby="document-scope_id-error"' : '' ?>>
            <?php if ($fieldError('scope_id') !== null): ?><span class="form-field-error" id="document-scope_id-error"><?= $escape($fieldError('scope_id')) ?></span><?php endif; ?>
        </label>
        <button class="button button--quiet" type="submit">Consultar</button>
    </form>
</section>

<?php if (is_array($historyDocument ?? null)): ?>
    <section class="surface">
        <h2>Historial: <?= $escape($historyDocument['title']) ?></h2>
        <p><a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/documents?scope=<?= $escape($scope) ?>&amp;id=<?= $escape($scopeId ?? '') ?>">Volver a documentos vigentes</a></p>
        <div class="users-grid">
            <?php foreach ($history as $version): ?>
                <article class="user-card">
                    <div class="user-card__head">
                        <strong><?= $escape($version['title']) ?></strong>
                        <span class="status-pill"><?= $escape($version['type']) ?></span>
                    </div>
                    <div>Versión #<?= $escape($version['id']) ?> · <?= $escape($version['created_at']) ?></div>
                    <?php if (is_string($version['file_url'])): ?>
                        <a class="button button--quiet" href="<?= $escape($version['file_url']) ?>">Descargar archivo</a>
                    <?php endif; ?>
                    <?php if (is_array($version['content_json'])): ?>
                        <pre><?= $escape($prettyJson($version['content_json'])) ?></pre>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php elseif ($scopeId !== null): ?>
    <section class="surface">
        <h2>Versiones vigentes</h2>
        <?php if ($documents === []): ?>
            <p class="empty-state">No hay documentos vigentes para este ámbito.</p>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($documents as $document): ?>
                    <article class="user-card">
                        <div class="user-card__head">
                            <strong><?= $escape($document['title']) ?></strong>
                            <span class="status-pill"><?= $escape($document['type']) ?></span>
                        </div>
                        <div>Versión #<?= $escape($document['id']) ?> · <?= $escape($document['created_at']) ?></div>
                        <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/documents/<?= $escape($document['id']) ?>">Ver historial</a>
                        <?php if (is_string($document['file_url'])): ?>
                            <a class="button button--quiet" href="<?= $escape($document['file_url']) ?>">Descargar archivo</a>
                        <?php endif; ?>
                        <?php if (is_array($document['content_json'])): ?>
                            <pre><?= $escape($prettyJson($document['content_json'])) ?></pre>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($canManage && ($scopeId !== null || $formErrors !== [])): ?>
        <section class="surface">
            <h2>Agregar versión</h2>
            <?php if ($fieldError('form') !== null): ?><p class="error" id="document-form-error" role="alert"><?= $escape($fieldError('form')) ?></p><?php endif; ?>
            <form id="document-version-form" class="stack-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/documents"<?= $fieldError('form') !== null ? ' aria-describedby="document-form-error"' : '' ?>>
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="scope" value="<?= $escape($scope) ?>">
                <input type="hidden" name="scope_id" value="<?= $formValue('scope_id', $scopeId ?? '') ?>">
                <label>Tipo
                    <select id="document-type" name="type" required aria-invalid="<?= $fieldError('type') !== null ? 'true' : 'false' ?>"<?= $fieldError('type') !== null ? ' aria-describedby="document-type-error"' : '' ?>>
                        <?php foreach ($typeOptions as $type => $label): ?>
                            <option value="<?= $escape($type) ?>" <?= ($formValues['type'] ?? '') === $type ? 'selected' : '' ?>><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($fieldError('type') !== null): ?><span class="form-field-error" id="document-type-error"><?= $escape($fieldError('type')) ?></span><?php endif; ?>
                </label>
                <label>Título
                    <input id="document-title" name="title" maxlength="190" required value="<?= $formValue('title') ?>" aria-invalid="<?= $fieldError('title') !== null ? 'true' : 'false' ?>"<?= $fieldError('title') !== null ? ' aria-describedby="document-title-error"' : '' ?>>
                    <?php if ($fieldError('title') !== null): ?><span class="form-field-error" id="document-title-error"><?= $escape($fieldError('title')) ?></span><?php endif; ?>
                </label>
                <label>Reemplaza versión
                    <select id="document-supersedes_document_id" name="supersedes_document_id" aria-invalid="<?= $fieldError('supersedes_document_id') !== null ? 'true' : 'false' ?>"<?= $fieldError('supersedes_document_id') !== null ? ' aria-describedby="document-supersedes_document_id-error"' : '' ?>>
                        <option value="" <?= ($formValues['supersedes_document_id'] ?? '') === '' ? 'selected' : '' ?>>Documento nuevo</option>
                        <?php foreach ($documents as $document): ?>
                            <option value="<?= $escape($document['id']) ?>" <?= ($formValues['supersedes_document_id'] ?? '') === (string) $document['id'] ? 'selected' : '' ?>>#<?= $escape($document['id']) ?> · <?= $escape($document['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($fieldError('supersedes_document_id') !== null): ?><span class="form-field-error" id="document-supersedes_document_id-error"><?= $escape($fieldError('supersedes_document_id')) ?></span><?php endif; ?>
                </label>
                <label>Archivo privado (PNG, JPEG, WebP o PDF; máximo 10 MiB)
                    <input id="document-file" type="file" name="document" accept="application/pdf,image/png,image/jpeg,image/webp" aria-invalid="<?= $fieldError('document') !== null ? 'true' : 'false' ?>"<?= $fieldError('document') !== null ? ' aria-describedby="document-file-error"' : '' ?>>
                    <?php if ($fieldError('document') !== null): ?><span class="form-field-error" id="document-file-error"><?= $escape($fieldError('document')) ?></span><?php endif; ?>
                </label>
                <label>Contenido JSON para manual o inventario
                    <textarea id="document-content_json" name="content_json" rows="8" maxlength="100000" aria-invalid="<?= $fieldError('content_json') !== null ? 'true' : 'false' ?>"<?= $fieldError('content_json') !== null ? ' aria-describedby="document-content_json-error"' : '' ?>><?= $formValue('content_json', '{}') ?></textarea>
                    <?php if ($fieldError('content_json') !== null): ?><span class="form-field-error" id="document-content_json-error"><?= $escape($fieldError('content_json')) ?></span><?php endif; ?>
                </label>
                <button class="button" type="submit">Guardar versión</button>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
