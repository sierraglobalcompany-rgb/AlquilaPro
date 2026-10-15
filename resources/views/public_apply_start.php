<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$unit = is_array($unit ?? null) ? $unit : [];
$wizardSteps = is_array($wizardSteps ?? null) ? array_values($wizardSteps) : [];
$action = (string) ($action ?? '');
$csrfToken = (string) ($csrfToken ?? '');
$error = is_string($error ?? null) ? $error : null;
?>
<section>
    <p class="eyebrow">Solicitud de arrendamiento</p>
    <h1>Inicia tu solicitud</h1>
    <p class="hint">Completa tus datos básicos para recibir un enlace seguro de continuación.</p>

    <?php if ($wizardSteps !== []): ?>
        <nav aria-label="Progreso de la solicitud">
            <p class="hint">Paso 1 de <?= $escape(count($wizardSteps)) ?></p>
            <ol>
                <?php foreach ($wizardSteps as $index => $step): ?>
                    <li <?= $index === 0 ? 'aria-current="step"' : '' ?>><?= $escape($step['label'] ?? '') ?></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <article class="surface">
        <h2><?= $escape($unit['property_name'] ?? 'Unidad disponible') ?></h2>
        <p><?= $escape($unit['city'] ?? '') ?><?= !empty($unit['sector']) ? ' · ' . $escape($unit['sector']) : '' ?></p>
        <?php if (!empty($unit['location_label'])): ?>
            <p><?= $escape($unit['location_label']) ?></p>
        <?php endif; ?>
        <?php if (isset($unit['monthly_price'])): ?>
            <p><strong>$<?= $escape(number_format((float) $unit['monthly_price'], 0, ',', '.')) ?>/mes</strong></p>
        <?php endif; ?>
    </article>

    <?php if ($error !== null): ?>
        <p role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= $escape($action) ?>">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">

        <label>
            Tipo de documento
            <input name="document_type" maxlength="24" required autocomplete="off">
        </label>
        <label>
            Número de documento
            <input name="document_number" maxlength="64" required autocomplete="off">
        </label>
        <label>
            Nombre completo
            <input name="full_name" maxlength="190" required autocomplete="name">
        </label>
        <label>
            Celular o WhatsApp
            <input name="phone" maxlength="50" required autocomplete="tel">
        </label>
        <label>
            Correo electrónico
            <input type="email" name="email" maxlength="190" required autocomplete="email">
        </label>
        <label>
            <input type="checkbox" name="consent" value="1" required>
            Acepto el tratamiento de mis datos para gestionar esta solicitud.
        </label>

        <button type="submit">Iniciar solicitud</button>
    </form>

    <p><a href="<?= $escape($basePath) ?>/catalog">Volver al catálogo</a></p>
</section>
