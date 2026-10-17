<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$typeLabel = match ((string) $template['document_type']) {
    'urban_housing' => 'Vivienda urbana',
    'independent_parking' => 'Parqueadero independiente',
    default => (string) $template['document_type'],
};
?>
<section class="page-stack" aria-labelledby="contract-template-title">
    <header class="page-heading">
        <div>
            <p class="eyebrow">Contratos</p>
            <h1 id="contract-template-title"><?= $escape($template['name']) ?></h1>
            <p><?= $escape($typeLabel) ?> · <?= $template['is_active'] ? 'Activa' : 'Inactiva' ?></p>
        </div>
        <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contract-templates">Volver</a>
    </header>

    <?php if ($canManage): ?>
        <article class="content-card content-card--stacked">
            <h2>Estado</h2>
            <form method="post" action="<?= $escape($basePath) ?>/admin/contract-templates/<?= (int) $template['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="active" value="<?= $template['is_active'] ? '0' : '1' ?>">
                <button class="button button--quiet" type="submit"><?= $template['is_active'] ? 'Desactivar' : 'Activar' ?></button>
            </form>
        </article>

        <article class="content-card content-card--stacked">
            <h2>Nueva versión</h2>
            <p class="muted">Escribe el contenido del contrato. Cada guardado crea una versión nueva.</p>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/contract-templates/<?= (int) $template['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <input type="hidden" name="action" value="new_version">
                <label>Contenido del contrato
                    <textarea name="body_template" rows="12" required></textarea>
                </label>

                <details>
                    <summary>Datos manuales del contrato</summary>
                    <p class="muted">Agrega sólo los datos que una persona deberá completar al crear el contrato.</p>
                    <p class="muted">El identificador se usa dentro del texto entre llaves dobles, por ejemplo <code>{{custom.note}}</code>.</p>

                    <?php for ($index = 0; $index < 50; $index++): ?>
                        <?php if ($index === 1): ?>
                            <details>
                                <summary>Más variables manuales</summary>
                        <?php endif; ?>

                        <fieldset>
                            <legend>Variable <?= $index + 1 ?></legend>
                            <label>Identificador
                                <input type="text" name="variables[<?= $index ?>][key]" placeholder="custom.note">
                            </label>
                            <label>Nombre visible
                                <input type="text" name="variables[<?= $index ?>][label]" placeholder="Nota especial">
                            </label>
                            <label class="check-row">
                                <input type="checkbox" name="variables[<?= $index ?>][required]" value="1">
                                Obligatoria
                            </label>
                        </fieldset>

                        <?php if ($index === 49): ?>
                            </details>
                        <?php endif; ?>
                    <?php endfor; ?>
                </details>

                <button class="button" type="submit">Crear versión</button>
            </form>
        </article>
    <?php endif; ?>

    <section aria-labelledby="contract-template-versions-title">
        <h2 id="contract-template-versions-title">Versiones</h2>
        <?php if ($versions === []): ?>
            <p class="empty-state">Esta plantilla todavía no tiene versiones.</p>
        <?php else: ?>
            <div class="card-list">
                <?php foreach (array_reverse($versions) as $version): ?>
                    <?php
                    $variables = json_decode((string) $version['variables_json'], true);
                    $variables = is_array($variables) ? $variables : [];
                    ?>
                    <article class="content-card content-card--stacked">
                        <div>
                            <h3>Versión <?= (int) $version['version_number'] ?></h3>
                            <p class="muted"><?= $escape($version['created_at']) ?></p>
                        </div>
                        <pre><?= $escape($version['body_template']) ?></pre>
                        <?php if ($variables !== []): ?>
                            <div>
                                <strong>Variables manuales</strong>
                                <ul>
                                    <?php foreach ($variables as $variable): ?>
                                        <li>
                                            <code><?= $escape($variable['key'] ?? '') ?></code>
                                            — <?= $escape($variable['label'] ?? '') ?>
                                            <?= !empty($variable['required']) ? '(obligatoria)' : '(opcional)' ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</section>
