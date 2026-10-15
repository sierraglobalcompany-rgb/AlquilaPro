<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$quickActions = is_array($quickActions ?? null) ? $quickActions : [];
?>
<section class="surface hero-card">
    <p class="eyebrow">Inicio</p>
    <h1>Hola, <?= $escape($user['full_name'] ?? '') ?></h1>
    <p class="hint">Accede a las funciones disponibles para tu cuenta desde estos accesos directos o desde Apps.</p>
    <p class="role-pill"><?= $escape($user['role'] ?? '') ?></p>
</section>

<section class="page-stack" aria-labelledby="dashboard-actions-title">
    <div class="section-heading">
        <h2 id="dashboard-actions-title">Accesos rápidos</h2>
    </div>
    <?php if ($quickActions !== []): ?>
        <div class="card-list dashboard-actions">
            <?php foreach ($quickActions as $action): ?>
                <a class="app-card dashboard-action" href="<?= $escape($action['href']) ?>">
                    <span class="app-card__copy">
                        <strong><?= $escape($action['label']) ?></strong>
                        <span><?= $escape($action['description']) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-state">No hay acciones rápidas disponibles para tu cuenta.</p>
    <?php endif; ?>
</section>
