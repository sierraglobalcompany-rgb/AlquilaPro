<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="surface">
    <p class="eyebrow">Seguridad</p>
    <h1>Auditoría</h1>
    <p class="hint">Consulta las acciones administrativas recientes registradas por el sistema.</p>
</section>

<section class="users-grid" aria-label="Registro de auditoría">
    <?php if ($auditRows === []): ?>
        <article class="user-card">
            <strong>Sin eventos registrados</strong>
        </article>
    <?php else: ?>
        <?php foreach ($auditRows as $row): ?>
            <article class="user-card">
                <div class="user-card__head">
                    <strong><?= $escape($row['action']) ?></strong>
                    <span><?= $escape($row['occurred_at']) ?></span>
                </div>
                <div><?= $escape($row['summary']) ?></div>
                <div class="user-card__role">
                    <?= $escape($row['entity_type']) ?>
                    <?php if ($row['entity_id'] !== null): ?>
                        #<?= $escape($row['entity_id']) ?>
                    <?php endif; ?>
                </div>
                <div class="user-card__email">
                    Actor: <?= $row['actor_user_id'] === null ? 'Sistema' : '#' . $escape($row['actor_user_id']) ?>
                    <?php if ($row['ip_address'] !== null): ?>
                        · IP <?= $escape($row['ip_address']) ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
