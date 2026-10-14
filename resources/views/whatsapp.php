<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$actions = is_array($actions ?? null) ? $actions : [];
$createdAction = is_array($createdAction ?? null) ? $createdAction : null;
?>
<section class="surface">
    <p class="eyebrow">Acciones asistidas</p>
    <h1>WhatsApp</h1>
    <p class="hint">Prepara un mensaje desde un registro existente. Revisa el texto antes de continuar a WhatsApp.</p>

    <?php if (is_string($error ?? null) && $error !== ''): ?>
        <p class="notice notice--error" role="alert"><?= $escape($error) ?></p>
    <?php endif; ?>

    <?php if ($createdAction !== null): ?>
        <section class="surface" aria-labelledby="whatsapp-confirmation-title">
            <p class="eyebrow">Mensaje preparado</p>
            <h2 id="whatsapp-confirmation-title">Confirma el mensaje</h2>
            <p><strong>Destino:</strong> <?= $escape($createdAction['recipient_phone_snapshot'] ?? '') ?></p>
            <p><strong>Mensaje:</strong> <?= $escape($createdAction['message_snapshot'] ?? '') ?></p>
            <form method="post" action="<?= $escape($basePath) ?>/admin/whatsapp/<?= (int) ($createdAction['id'] ?? 0) ?>/open">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken ?? '') ?>">
                <button class="button" type="submit">Continuar a WhatsApp</button>
            </form>
        </section>
    <?php endif; ?>

    <form method="post" action="<?= $escape($basePath) ?>/admin/whatsapp" class="form-stack">
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken ?? '') ?>">
        <label for="whatsapp-context-type">Tipo de registro</label>
        <select id="whatsapp-context-type" name="context_type" required>
            <option value="candidate">Candidato</option>
            <option value="lease">Arrendamiento</option>
            <option value="maintenance_request">Solicitud de mantenimiento</option>
        </select>

        <label for="whatsapp-context-id">ID del registro</label>
        <input id="whatsapp-context-id" name="context_id" inputmode="numeric" pattern="[1-9][0-9]*" required>

        <label for="whatsapp-template">Plantilla</label>
        <select id="whatsapp-template" name="template_key" required>
            <option value="candidate_follow_up">Seguimiento de candidato</option>
            <option value="lease_contact">Contacto de arrendamiento</option>
            <option value="maintenance_follow_up">Seguimiento de mantenimiento</option>
        </select>

        <button class="button" type="submit">Preparar mensaje</button>
    </form>
</section>

<section class="surface" aria-labelledby="whatsapp-history-title">
    <p class="eyebrow">Registro interno</p>
    <h2 id="whatsapp-history-title">Historial de acciones</h2>
    <?php if ($actions === []): ?>
        <p class="empty-state">Todavía no hay acciones preparadas.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Fecha UTC</th>
                    <th>Registro</th>
                    <th>Destino</th>
                    <th>Mensaje</th>
                    <th>Auditoría</th>
                    <th>Acción</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($actions as $action): ?>
                    <tr>
                        <td><?= $escape($action['created_at'] ?? '') ?></td>
                        <td><?= $escape($action['context_type'] ?? '') ?> #<?= (int) ($action['context_id'] ?? 0) ?></td>
                        <td><?= $escape($action['recipient_phone_snapshot'] ?? '') ?></td>
                        <td><?= $escape($action['message_snapshot'] ?? '') ?></td>
                        <td><?= ($action['opened_at'] ?? null) === null ? 'Sin abrir' : 'Abierto ' . $escape($action['opened_at']) ?></td>
                        <td>
                            <form method="post" action="<?= $escape($basePath) ?>/admin/whatsapp/<?= (int) ($action['id'] ?? 0) ?>/open">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken ?? '') ?>">
                                <button class="button button--quiet" type="submit">Abrir</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
