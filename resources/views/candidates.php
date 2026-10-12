<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="surface">
    <p class="eyebrow">Apps</p>
    <h1>Candidatos</h1>
    <p class="hint">Consulta las solicitudes recibidas y abre cada expediente para revisar su información.</p>
</section>

<?php if ($candidates === []): ?>
    <section class="surface">
        <p class="empty-state">Aún no hay candidatos registrados.</p>
    </section>
<?php else: ?>
    <section class="users-grid" aria-label="Candidatos registrados">
        <?php foreach ($candidates as $candidate): ?>
            <article class="user-card">
                <div class="user-card__head">
                    <strong><?= $escape($candidate['full_name']) ?></strong>
                    <span class="status-pill"><?= $escape($candidate['status']) ?></span>
                </div>
                <div><?= $escape(strtoupper((string) $candidate['document_type'])) ?> <?= $escape($candidate['document_number']) ?></div>
                <div><?= $escape($candidate['property_name'] ?? '') ?> · <?= $escape($candidate['unit_code'] ?? $candidate['unit_id']) ?></div>
                <div><?= $escape($candidate['phone']) ?></div>
                <div class="hint">
                    Creado: <?= $escape($candidate['created_at']) ?>
                    <?php if (($candidate['submitted_at'] ?? null) !== null): ?> · Enviado: <?= $escape($candidate['submitted_at']) ?><?php endif; ?>
                </div>
                <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>">Ver candidato</a>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
