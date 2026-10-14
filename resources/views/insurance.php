<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$policies = $policies ?? [];
$canManage = (bool) ($canManage ?? false);
$policyStatusLabels = [
    'upcoming' => 'Próxima',
    'active' => 'Vigente',
    'expired' => 'Vencida',
    'cancelled' => 'Cancelada',
];
$claimStatusLabels = [
    'draft' => 'Borrador',
    'filed' => 'Presentada',
    'in_review' => 'En revisión',
    'approved' => 'Aprobada',
    'partial' => 'Parcial',
    'rejected' => 'Rechazada',
];
$transitions = [
    'draft' => ['filed'],
    'filed' => ['in_review', 'approved', 'partial', 'rejected'],
    'in_review' => ['approved', 'partial', 'rejected'],
    'approved' => [],
    'partial' => [],
    'rejected' => [],
];
?>
<section class="surface">
    <p class="eyebrow">Administración</p>
    <h1>Seguros</h1>
    <p class="hint">Pólizas, vigencias y reclamaciones del arrendamiento.</p>
</section>

<?php if ($canManage): ?>
    <section class="surface">
        <h2>Registrar póliza</h2>
        <form method="post" action="<?= $escape($basePath) ?>/admin/insurance/policies">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <div class="form-grid">
                <label>Arrendamiento #<input type="number" min="1" name="lease_id" required></label>
                <label>Aseguradora<input name="provider_name" maxlength="190" required></label>
                <label>Producto<input name="product_name" maxlength="190" required></label>
                <label>Número de póliza<input name="policy_number" maxlength="190" required></label>
                <label>Inicio de cobertura<input type="date" name="coverage_start" required></label>
                <label>Fin de cobertura<input type="date" name="coverage_end" required></label>
                <label>Póliza anterior (opcional)<input type="number" min="1" name="supersedes_policy_id"></label>
            </div>
            <label>Coberturas (JSON)
                <textarea name="coverage_json" rows="6" required>[{"name":"Responsabilidad civil","limit_amount":null,"deductible_amount":null,"notes":null}]</textarea>
            </label>
            <label>Notas internas<textarea name="internal_notes" maxlength="1000" rows="3"></textarea></label>
            <button class="button" type="submit">Guardar póliza</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($policies === []): ?>
    <section class="surface"><p class="empty-state">No hay pólizas registradas.</p></section>
<?php else: ?>
    <?php foreach ($policies as $policy): ?>
        <section class="surface">
            <div class="user-card__head">
                <h2><?= $escape($policy['provider_name']) ?> · <?= $escape($policy['product_name']) ?></h2>
                <span class="status-pill"><?= $escape($policyStatusLabels[$policy['status']] ?? $policy['status']) ?></span>
            </div>
            <p>Póliza: <?= $escape($policy['policy_number']) ?></p>
            <p>Arrendamiento #<?= $escape($policy['lease_id']) ?> · Vigencia <?= $escape($policy['coverage_start']) ?> a <?= $escape($policy['coverage_end']) ?></p>
            <?php if ($policy['supersedes_policy_id'] !== null): ?>
                <p>Reemplaza póliza #<?= $escape($policy['supersedes_policy_id']) ?></p>
            <?php endif; ?>
            <?php if ($policy['internal_notes'] !== null): ?>
                <p>Nota interna: <?= $escape($policy['internal_notes']) ?></p>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" action="<?= $escape($basePath) ?>/admin/insurance/policies/<?= $escape($policy['id']) ?>/notes">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <label>Notas internas<textarea name="internal_notes" maxlength="1000" rows="2"><?= $escape($policy['internal_notes'] ?? '') ?></textarea></label>
                    <button class="button button--quiet" type="submit">Guardar notas</button>
                </form>
            <?php endif; ?>

            <h3>Coberturas</h3>
            <?php if ($policy['coverage'] === []): ?>
                <p class="empty-state">Sin coberturas registradas.</p>
            <?php else: ?>
                <div class="users-grid">
                    <?php foreach ($policy['coverage'] as $coverage): ?>
                        <article class="user-card">
                            <strong><?= $escape($coverage['name']) ?></strong>
                            <div>Límite: <?= $escape($coverage['limit_amount'] ?? '—') ?></div>
                            <div>Deducible: <?= $escape($coverage['deductible_amount'] ?? '—') ?></div>
                            <?php if ($coverage['notes'] !== null): ?><div><?= $escape($coverage['notes']) ?></div><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <h3>Documentos de póliza</h3>
            <?php if ($policy['documents'] === []): ?>
                <p class="empty-state">Sin documentos.</p>
            <?php else: ?>
                <div class="users-grid">
                    <?php foreach ($policy['documents'] as $document): ?>
                        <article class="user-card">
                            <strong><?= $escape($document['label']) ?></strong>
                            <div><?= $escape($document['mime']) ?> · <?= $escape($document['size_bytes']) ?> bytes</div>
                            <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/insurance/policies/<?= $escape($policy['id']) ?>/documents/<?= $escape($document['document_id']) ?>">Abrir</a>
                            <?php if ($canManage && $policy['status'] !== 'cancelled' && $policy['claims'] === []): ?>
                                <form method="post" action="<?= $escape($basePath) ?>/admin/insurance/policies/<?= $escape($policy['id']) ?>/documents/<?= $escape($document['document_id']) ?>/remove">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                    <button class="button button--quiet" type="submit">Retirar</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($canManage): ?>
                <form method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/insurance/policies/<?= $escape($policy['id']) ?>/documents">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <label>Etiqueta<input name="label" maxlength="190" required></label>
                    <label>Documento (PNG, JPEG, WebP o PDF; máximo 5 MiB)
                        <input type="file" name="document" accept="image/png,image/jpeg,image/webp,application/pdf" required>
                    </label>
                    <button class="button button--quiet" type="submit">Añadir documento</button>
                </form>
            <?php endif; ?>

            <?php if ($canManage && $policy['status'] !== 'cancelled'): ?>
                <form method="post" action="<?= $escape($basePath) ?>/admin/insurance/policies/<?= $escape($policy['id']) ?>/cancel">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <label>Motivo de cancelación<input name="reason" maxlength="500" required></label>
                    <button class="button button--quiet" type="submit">Cancelar póliza</button>
                </form>
            <?php endif; ?>

            <h3>Reclamaciones</h3>
            <?php if ($policy['claims'] === []): ?>
                <p class="empty-state">No hay reclamaciones.</p>
            <?php else: ?>
                <?php foreach ($policy['claims'] as $claim): ?>
                    <article class="user-card">
                        <div class="user-card__head">
                            <strong><?= $escape($claim['description']) ?></strong>
                            <span class="status-pill"><?= $escape($claimStatusLabels[$claim['status']] ?? $claim['status']) ?></span>
                        </div>
                        <div>Incidente: <?= $escape($claim['incident_on']) ?> · Monto reclamado: <?= $escape($claim['claimed_amount'] ?? '—') ?></div>
                        <?php if ($claim['radicado'] !== null): ?><div>Radicado: <?= $escape($claim['radicado']) ?></div><?php endif; ?>
                        <?php if ($claim['filed_on'] !== null): ?><div>Presentada: <?= $escape($claim['filed_on']) ?></div><?php endif; ?>
                        <?php if ($claim['resolved_on'] !== null): ?><div>Resuelta: <?= $escape($claim['resolved_on']) ?></div><?php endif; ?>
                        <?php if ($claim['resolution_notes'] !== null): ?><div><?= $escape($claim['resolution_notes']) ?></div><?php endif; ?>

                        <h4>Checklist</h4>
                        <?php if ($claim['checklist'] === []): ?><p class="hint">Sin elementos configurados.</p><?php endif; ?>
                        <?php foreach ($claim['checklist'] as $item): ?>
                            <div><?= $item['completed'] ? '✓' : '○' ?> <?= $escape($item['label']) ?><?= $item['required'] ? ' · Requerido' : '' ?></div>
                        <?php endforeach; ?>
                        <?php if ($canManage && !in_array($claim['status'], ['approved', 'partial', 'rejected'], true)): ?>
                            <form method="post" action="<?= $escape($basePath) ?>/admin/insurance/claims/<?= $escape($claim['id']) ?>/checklist">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Editar checklist (JSON)
                                    <textarea name="checklist_json" rows="5"><?= $escape(json_encode($claim['checklist'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) ?></textarea>
                                </label>
                                <button class="button button--quiet" type="submit">Guardar checklist</button>
                            </form>
                        <?php endif; ?>

                        <h4>Documentos de reclamación</h4>
                        <?php foreach ($claim['documents'] as $document): ?>
                            <div>
                                <a href="<?= $escape($basePath) ?>/admin/insurance/claims/<?= $escape($claim['id']) ?>/documents/<?= $escape($document['document_id']) ?>"><?= $escape($document['label']) ?></a>
                                <span><?= $escape($document['mime']) ?> · <?= $escape($document['size_bytes']) ?> bytes</span>
                                <?php if ($canManage && $claim['status'] === 'draft'): ?>
                                    <form method="post" action="<?= $escape($basePath) ?>/admin/insurance/claims/<?= $escape($claim['id']) ?>/documents/<?= $escape($document['document_id']) ?>/remove">
                                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                        <button class="button button--quiet" type="submit">Retirar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($canManage): ?>
                            <form method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/insurance/claims/<?= $escape($claim['id']) ?>/documents">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Etiqueta<input name="label" maxlength="190" required></label>
                                <label>Documento (PNG, JPEG, WebP o PDF; máximo 5 MiB)
                                    <input type="file" name="document" accept="image/png,image/jpeg,image/webp,application/pdf" required>
                                </label>
                                <button class="button button--quiet" type="submit">Añadir documento</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canManage && $claim['status'] === 'draft'): ?>
                            <form method="post" action="<?= $escape($basePath) ?>/admin/insurance/claims/<?= $escape($claim['id']) ?>">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Fecha del incidente<input type="date" name="incident_on" value="<?= $escape($claim['incident_on']) ?>" required></label>
                                <label>Descripción<textarea name="description" maxlength="1000" required><?= $escape($claim['description']) ?></textarea></label>
                                <label>Monto reclamado<input name="claimed_amount" inputmode="decimal" value="<?= $escape($claim['claimed_amount'] ?? '') ?>"></label>
                                <button class="button button--quiet" type="submit">Guardar borrador</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($canManage && ($transitions[$claim['status']] ?? []) !== []): ?>
                            <form method="post" action="<?= $escape($basePath) ?>/admin/insurance/claims/<?= $escape($claim['id']) ?>/transition">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Nuevo estado
                                    <select name="target_status" required>
                                        <?php foreach ($transitions[$claim['status']] as $target): ?>
                                            <option value="<?= $escape($target) ?>"><?= $escape($claimStatusLabels[$target]) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <?php if ($claim['status'] === 'draft'): ?>
                                    <label>Fecha de presentación<input type="date" name="filed_on" required></label>
                                <?php endif; ?>
                                <label>Radicado<input name="radicado" value="<?= $escape($claim['radicado'] ?? '') ?>"></label>
                                <label>Fecha de resolución<input type="date" name="resolved_on"></label>
                                <label>Notas de resolución<textarea name="resolution_notes" maxlength="1000"></textarea></label>
                                <button class="button button--quiet" type="submit">Aplicar transición</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($canManage): ?>
                <form method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/insurance/policies/<?= $escape($policy['id']) ?>/claims">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <h4>Crear reclamación</h4>
                    <label>Fecha del incidente<input type="date" name="incident_on" required></label>
                    <label>Descripción<textarea name="description" maxlength="1000" required></textarea></label>
                    <label>Monto reclamado (opcional)<input name="claimed_amount" inputmode="decimal"></label>
                    <label>Checklist inicial (JSON)<textarea name="checklist_json" rows="4">[]</textarea></label>
                    <label>Documento inicial (opcional)<input name="document_label" maxlength="190"></label>
                    <input type="file" name="document" accept="image/png,image/jpeg,image/webp,application/pdf">
                    <button class="button" type="submit">Crear borrador</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
