<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$scope = $scope === 'lease' ? 'lease' : 'property';
$documents = is_array($documents ?? null) ? $documents : [];
$history = is_array($history ?? null) ? $history : [];
$scopeId = is_int($scopeId ?? null) ? $scopeId : null;
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
            <select name="scope">
                <option value="property" <?= $scope === 'property' ? 'selected' : '' ?>>Propiedad</option>
                <option value="lease" <?= $scope === 'lease' ? 'selected' : '' ?>>Arrendamiento</option>
            </select>
        </label>
        <label>ID de propiedad o arrendamiento<input type="number" name="id" min="1" value="<?= $escape($scopeId ?? '') ?>" required></label>
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

    <?php if ($canManage): ?>
        <section class="surface">
            <h2>Agregar versión</h2>
            <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/documents">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="scope" value="<?= $escape($scope) ?>">
                <input type="hidden" name="scope_id" value="<?= $escape($scopeId) ?>">
                <label>Tipo
                    <select name="type" required>
                        <?php foreach ($typeOptions as $type => $label): ?>
                            <option value="<?= $escape($type) ?>"><?= $escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Título<input name="title" maxlength="190" required></label>
                <label>Reemplaza versión
                    <select name="supersedes_document_id">
                        <option value="">Documento nuevo</option>
                        <?php foreach ($documents as $document): ?>
                            <option value="<?= $escape($document['id']) ?>">#<?= $escape($document['id']) ?> · <?= $escape($document['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Archivo privado (PNG, JPEG, WebP o PDF; máximo 10 MiB)
                    <input type="file" name="document" accept="application/pdf,image/png,image/jpeg,image/webp">
                </label>
                <label>Contenido JSON para manual o inventario
                    <textarea name="content_json" rows="8" maxlength="100000">{}</textarea>
                </label>
                <button class="button" type="submit">Guardar versión</button>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
