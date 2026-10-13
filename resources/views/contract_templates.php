<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$typeLabel = static fn (string $type): string => match ($type) {
    'urban_housing' => 'Vivienda urbana',
    'independent_parking' => 'Parqueadero independiente',
    default => $type,
};
?>
<section class="page-stack" aria-labelledby="contract-templates-title">
    <header class="page-heading">
        <div>
            <p class="eyebrow">Contratos</p>
            <h1 id="contract-templates-title">Plantillas de contrato</h1>
            <p>Administra textos versionados sin editar el historial.</p>
        </div>
    </header>

    <?php if ($canManage): ?>
        <article class="content-card content-card--stacked" data-contract-template-create>
            <h2>Nueva plantilla</h2>
            <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/contract-templates">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <label>Nombre<input name="name" required maxlength="190"></label>
                <label>Tipo
                    <select name="document_type" required>
                        <option value="urban_housing">Vivienda urbana</option>
                        <option value="independent_parking">Parqueadero independiente</option>
                    </select>
                </label>
                <button class="button" type="submit">Crear plantilla</button>
            </form>
        </article>
    <?php endif; ?>

    <?php if ($templates === []): ?>
        <p class="empty-state">Aún no hay plantillas contractuales.</p>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($templates as $template): ?>
                <article class="content-card">
                    <div>
                        <h2><?= $escape($template['name']) ?></h2>
                        <p><?= $escape($typeLabel((string) $template['document_type'])) ?></p>
                        <p class="muted"><?= $template['is_active'] ? 'Activa' : 'Inactiva' ?></p>
                    </div>
                    <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contract-templates/<?= (int) $template['id'] ?>">Ver</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>