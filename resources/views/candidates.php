<?php

declare(strict_types=1);

use AlquilaPro\UI\HumanLabels;

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
                    <span class="status-pill"><?= $escape(HumanLabels::candidateStatus((string) $candidate['status'])) ?></span>
                </div>
                <?php
                $unitLabel = trim((string) ($candidate['unit_code'] ?? $candidate['unit_id'] ?? ''));
                $location = array_values(array_filter([
                    trim((string) ($candidate['property_name'] ?? '')),
                    $unitLabel,
                ], static fn (string $value): bool => $value !== ''));
                $contact = array_values(array_filter([
                    trim((string) ($candidate['phone'] ?? '')),
                    trim((string) ($candidate['email'] ?? '')),
                ], static fn (string $value): bool => $value !== ''));
                ?>
                <?php if ($location !== []): ?><div><?= $escape(implode(' · ', $location)) ?></div><?php endif; ?>
                <?php if ($contact !== []): ?><div class="hint"><?= $escape(implode(' · ', $contact)) ?></div><?php endif; ?>
                <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>">Ver candidato</a>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
