<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$types = [
    'bank_account' => 'Cuenta bancaria',
    'breb_key' => 'Llave Bre-B',
    'breb_qr' => 'Código QR Bre-B',
    'wallet' => 'Billetera digital',
    'manual' => 'Instrucciones manuales',
];
$keyTypes = [
    'identity_document' => 'Documento de identidad',
    'mobile' => 'Número celular',
    'email' => 'Correo electrónico',
    'alphanumeric' => 'Llave alfanumérica',
    'merchant_code' => 'Código de comercio',
];
$detailFields = [
    'recipient_name' => 'Titular',
    'institution_name' => 'Entidad',
    'account_type' => 'Tipo de cuenta',
    'account_number' => 'Número de cuenta',
    'breb_key_value' => 'Llave Bre-B',
    'wallet_identifier' => 'Identificador de billetera',
    'instructions' => 'Instrucciones',
];
?>
<section class="surface">
    <p class="eyebrow">Cartera</p>
    <h1>Medios de pago</h1>
    <p class="hint">Configura las opciones de pago disponibles por propiedad.</p>
    <p><a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/finance">Volver a cartera</a></p>
</section>

<?php if ($canManage): ?>
    <section class="surface">
        <h2>Agregar medio de pago</h2>
        <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/finance/payment-methods">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>Tipo
                <select name="type" required>
                    <?php foreach ($types as $value => $label): ?>
                        <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nombre visible<input name="label" required maxlength="120"></label>
            <label>Propiedad
                <select name="property_id">
                    <option value="">Global</option>
                    <?php foreach ($properties as $property): ?>
                        <option value="<?= (int) $property['id'] ?>"><?= $escape($property['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Titular<input name="recipient_name" maxlength="190"></label>
            <label>Entidad financiera o proveedor<input name="institution_name" maxlength="190"></label>
            <label>Tipo de cuenta<input name="account_type" maxlength="40"></label>
            <label>Número de cuenta<input name="account_number" maxlength="120"></label>
            <label>Tipo de llave Bre-B
                <select name="breb_key_type">
                    <option value="">Selecciona</option>
                    <?php foreach ($keyTypes as $value => $label): ?>
                        <option value="<?= $escape($value) ?>"><?= $escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Valor de llave Bre-B<input name="breb_key_value" maxlength="190"></label>
            <label>Identificador de billetera<input name="wallet_identifier" maxlength="190"></label>
            <label>Instrucciones<textarea name="instructions" maxlength="1000" rows="3"></textarea></label>
            <label>Orden<input type="number" name="sort_order" min="1" value="1" required></label>
            <input type="hidden" name="is_active" value="0">
            <label><input type="checkbox" name="is_active" value="1" checked> Activo</label>
            <label>Código QR privado (PNG, JPEG o WebP; máximo 2 MiB)
                <input type="file" name="qr" accept="image/png,image/jpeg,image/webp">
            </label>
            <button class="button" type="submit">Crear medio de pago</button>
        </form>
    </section>
<?php endif; ?>

<section class="surface">
    <h2>Medios configurados</h2>
    <?php if ($methods === []): ?>
        <p class="empty-state">Aún no hay medios de pago configurados.</p>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($methods as $method): ?>
                <?php
                $typeLabel = $types[$method['type']] ?? 'Medio de pago';
                $keyTypeLabel = $keyTypes[$method['breb_key_type'] ?? ''] ?? null;
                ?>
                <article class="content-card">
                    <div>
                        <h3><?= $escape($method['label']) ?></h3>
                        <p><?= $escape($typeLabel) ?> · <?= $escape($method['property_label']) ?> · Orden: <?= (int) $method['sort_order'] ?> · <?= $method['is_active'] ? 'Activo' : 'Inactivo' ?></p>
                        <?php if ($method['has_qr']): ?><p>QR disponible</p><?php endif; ?>
                        <?php if ($keyTypeLabel !== null): ?><p>Tipo de llave: <?= $escape($keyTypeLabel) ?></p><?php endif; ?>
                        <?php foreach ($detailFields as $field => $label): ?>
                            <?php if (is_string($method[$field] ?? null) && $method[$field] !== ''): ?>
                                <p><?= $escape($label) ?>: <?= nl2br($escape($method[$field])) ?></p>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($canManage): ?>
                        <details>
                            <summary>Editar configuración</summary>
                            <form class="stack-form" method="post" enctype="multipart/form-data" action="<?= $escape($basePath) ?>/admin/finance/payment-methods/<?= (int) $method['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <label>Tipo
                                    <select name="type" required>
                                        <?php foreach ($types as $value => $label): ?>
                                            <option value="<?= $escape($value) ?>" <?= $method['type'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Nombre visible<input name="label" required maxlength="120" value="<?= $escape($method['label']) ?>"></label>
                                <label>Propiedad
                                    <select name="property_id">
                                        <option value="" <?= $method['property_id'] === null ? 'selected' : '' ?>>Global</option>
                                        <?php foreach ($properties as $property): ?>
                                            <option value="<?= (int) $property['id'] ?>" <?= $method['property_id'] === (int) $property['id'] ? 'selected' : '' ?>><?= $escape($property['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Titular<input name="recipient_name" maxlength="190" value="<?= $escape($method['recipient_name']) ?>"></label>
                                <label>Entidad financiera o proveedor<input name="institution_name" maxlength="190" value="<?= $escape($method['institution_name']) ?>"></label>
                                <label>Tipo de cuenta<input name="account_type" maxlength="40" value="<?= $escape($method['account_type']) ?>"></label>
                                <label>Número de cuenta<input name="account_number" maxlength="120" value="<?= $escape($method['account_number']) ?>"></label>
                                <label>Tipo de llave Bre-B
                                    <select name="breb_key_type">
                                        <option value="">Selecciona</option>
                                        <?php foreach ($keyTypes as $value => $label): ?>
                                            <option value="<?= $escape($value) ?>" <?= $method['breb_key_type'] === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Valor de llave Bre-B<input name="breb_key_value" maxlength="190" value="<?= $escape($method['breb_key_value']) ?>"></label>
                                <label>Identificador de billetera<input name="wallet_identifier" maxlength="190" value="<?= $escape($method['wallet_identifier']) ?>"></label>
                                <label>Instrucciones<textarea name="instructions" maxlength="1000" rows="3"><?= $escape($method['instructions']) ?></textarea></label>
                                <label>Orden<input type="number" name="sort_order" min="1" value="<?= (int) $method['sort_order'] ?>" required></label>
                                <input type="hidden" name="is_active" value="0">
                                <label><input type="checkbox" name="is_active" value="1" <?= $method['is_active'] ? 'checked' : '' ?>> Activo</label>
                                <label>Reemplazar código QR privado (PNG, JPEG o WebP; máximo 2 MiB)
                                    <input type="file" name="qr" accept="image/png,image/jpeg,image/webp">
                                </label>
                                <?php if ($method['has_qr']): ?>
                                    <label><input type="checkbox" name="remove_qr" value="1"> Quitar código QR</label>
                                <?php endif; ?>
                                <button class="button" type="submit">Guardar cambios</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
