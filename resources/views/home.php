<?php

declare(strict_types=1);

use AlquilaPro\UI\HumanLabels;

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$quickActions = is_array($quickActions ?? null) ? $quickActions : [];
$attentionSignals = is_array($attentionSignals ?? null) ? $attentionSignals : [];
?>
<section class="surface hero-card">
    <p class="eyebrow">Inicio</p>
    <h1>Hola, <?= $escape($user['full_name'] ?? '') ?></h1>
    <p class="hint">Accede a las funciones disponibles para tu cuenta desde estos accesos directos o desde Apps.</p>
    <p class="role-pill"><?= $escape(HumanLabels::role((string) ($user['role'] ?? ''))) ?></p>
</section>

<?php if ($attentionSignals !== []): ?>
    <section class="page-stack" data-dashboard-attention aria-labelledby="dashboard-attention-title">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Prioridades</p>
                <h2 id="dashboard-attention-title">Atención requerida</h2>
            </div>
        </div>
        <div class="card-list">
            <?php foreach ($attentionSignals as $signal): ?>
                <a
                    class="app-card"
                    data-dashboard-signal="<?= $escape($signal['key']) ?>"
                    href="<?= $escape($signal['href']) ?>"
                >
                    <span class="app-card__copy">
                        <strong><?= $escape($signal['label']) ?></strong>
                        <span>Revisar ahora</span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

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
