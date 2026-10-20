<?php

declare(strict_types=1);

use AlquilaPro\UI\HumanLabels;

$candidateIcon = require __DIR__ . '/partials/icons.php';
$candidateSectionIcons = ['overview' => 'file-signature', 'work' => 'briefcase', 'household' => 'users', 'references' => 'account', 'documents' => 'files', 'screening' => 'shield', 'contracts' => 'file-signature', 'manage' => 'settings'];

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$activeSection = is_string($activeSection ?? null) ? $activeSection : 'overview';
$candidateSections = is_array($candidateSections ?? null) ? array_values($candidateSections) : [
    ['key' => 'overview', 'label' => 'Solicitud'],
];
$allowedTransitions = match ((string) $candidate['status']) {
    'new' => ['incomplete', 'withdrawn'],
    'incomplete' => ['withdrawn'],
    'verification' => ['pending_screening', 'withdrawn'],
    'pending_screening' => ['withdrawn'],
    default => [],
};
$screening = is_array($screening ?? null) ? $screening : null;
$screeningStatus = (string) ($screening['status'] ?? '');
$screeningTerminal = in_array($screeningStatus, ['approved', 'rejected'], true);
$screeningEditable = $canManageCandidates
    && (string) $candidate['status'] === 'pending_screening'
    && !$screeningTerminal;
$screeningTransitions = match ($screeningStatus) {
    'pending' => ['pending', 'in_progress', 'approved', 'rejected'],
    'in_progress' => ['in_progress', 'approved', 'rejected'],
    default => [],
};
$contractContext = is_array($contractContext ?? null) ? $contractContext : null;
$contractRows = $contractContext === null || !is_array($contractContext['contracts'] ?? null)
    ? []
    : $contractContext['contracts'];
$canCreateContract = $contractContext !== null && (bool) ($contractContext['can_create'] ?? false);

$nameParts = preg_split('/\s+/', trim((string) ($candidate['full_name'] ?? ''))) ?: [];
$initials = '';
foreach (array_slice(array_values(array_filter($nameParts, static fn (string $part): bool => $part !== '')), 0, 2) as $part) {
    $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = $initials !== '' ? $initials : 'AP';

$sectionHref = static fn (string $key): string => $basePath
    . '/admin/candidates/' . rawurlencode((string) $candidate['id'])
    . '?section=' . rawurlencode($key);
$activeSectionLabel = 'Solicitud';
foreach ($candidateSections as $section) {
    if ((string) ($section['key'] ?? '') === $activeSection) {
        $activeSectionLabel = (string) ($section['label'] ?? 'Solicitud');
        break;
    }
}

$humanDate = static function (mixed $value, bool $withTime = false): ?string {
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable) {
        return null;
    }

    return $date->format($withTime ? 'd/m/Y · H:i' : 'd/m/Y');
};

$birthDate = $humanDate($candidate['birth_date'] ?? null);
$createdAt = $humanDate($candidate['created_at'] ?? null, true);
$submittedAt = $humanDate($candidate['submitted_at'] ?? null, true);
$updatedAt = $humanDate($candidate['updated_at'] ?? null, true);
$statusCode = (string) ($candidate['status'] ?? '');
$statusLabel = HumanLabels::candidateStatus($statusCode);
$statusDescription = HumanLabels::candidateStatusDescription($statusCode);
$unitLabel = trim((string) ($candidate['property_name'] ?? ''));
$unitCode = trim((string) ($candidate['unit_code'] ?? $candidate['unit_id'] ?? ''));
if ($unitCode !== '') {
    $unitLabel = trim($unitLabel . ' · ' . $unitCode, " ·\t\n\r\0\x0B");
}
$propertyDetails = is_array($propertyDetails ?? null) ? $propertyDetails : null;
$propertyStreet = $propertyDetails !== null ? trim((string) ($propertyDetails['address'] ?? '')) : '';
$propertyCity = $propertyDetails !== null ? trim((string) ($propertyDetails['city'] ?? '')) : '';
$propertyLocation = $propertyStreet === '' ? '' : $propertyStreet . ($propertyCity !== '' ? ', ' . $propertyCity : '');
?>

<div class="candidate-page">
    <a class="text-link candidate-back" href="<?= $escape($basePath) ?>/admin/candidates">← Volver a candidatos</a>

    <header class="content-card candidate-shell" data-candidate-shell>
        <div class="candidate-shell__identity">
            <div class="candidate-avatar" aria-hidden="true"><?= $escape($initials) ?></div>
            <div class="candidate-shell__copy">
                <div class="candidate-shell__title">
                    <h1><?= $escape($candidate['full_name'] ?? '') ?></h1>
                    <span class="status-pill"><?= $escape($statusLabel) ?></span>
                </div>
                <?php if ($unitLabel !== ''): ?>
                    <p class="muted candidate-shell__unit"><?= $candidateIcon('building') ?><?= $escape($unitLabel) ?></p>
                <?php endif; ?>
                <?php if ($propertyLocation !== ''): ?>
                    <p class="muted candidate-shell__property-address"><?= $candidateIcon('map-pin') ?><?= $escape($propertyLocation) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($activeSection !== 'overview'): ?>
            <div class="candidate-shell__status"><p class="muted"><?= $escape($statusDescription) ?></p></div>
        <?php endif; ?>
    </header>

    <nav class="content-card candidate-tabs" aria-label="Secciones del candidato">
        <div class="button-row candidate-tabs__links">
            <?php foreach ($candidateSections as $section): ?>
                <?php
                $key = (string) ($section['key'] ?? '');
                $label = (string) ($section['label'] ?? '');
                $current = $key === $activeSection;
                ?>
                <a class="button <?= $current ? 'button--secondary' : 'button--quiet' ?> candidate-tab"
                   href="<?= $escape($sectionHref($key)) ?>"<?= $current ? ' aria-current="page"' : '' ?>><?= $candidateIcon($candidateSectionIcons[$key] ?? 'files') ?><span><?= $escape($label) ?></span></a>
            <?php endforeach; ?>
        </div>
    </nav>

    <details class="content-card candidate-section-menu">
        <summary>Sección: <?= $escape($activeSectionLabel) ?></summary>
        <nav class="candidate-section-menu__nav" aria-label="Secciones del candidato en móvil">
            <?php foreach ($candidateSections as $section): ?>
                <?php
                $key = (string) ($section['key'] ?? '');
                $label = (string) ($section['label'] ?? '');
                $current = $key === $activeSection;
                ?>
                <a class="candidate-section-menu__link"
                   href="<?= $escape($sectionHref($key)) ?>"<?= $current ? ' aria-current="page"' : '' ?>><?= $candidateIcon($candidateSectionIcons[$key] ?? 'files') ?><span><?= $escape($label) ?></span></a>
            <?php endforeach; ?>
        </nav>
    </details>

    <?php if ($activeSection === 'overview'): ?>
        <?php
        $coverPhotoId = is_int($coverPhotoId ?? null) && $coverPhotoId > 0 ? $coverPhotoId : null;
        $humanMoney = static function (mixed $amount): ?string {
            return is_numeric($amount) ? '$ ' . number_format((float) $amount, 0, ',', '.') . ' COP' : null;
        };
        $milestones = [
            ['label' => 'Solicitud', 'verified' => $createdAt !== null, 'detail' => $createdAt, 'raw_date' => $candidate['created_at'] ?? null],
            ['label' => 'Información laboral', 'verified' => trim((string) ($candidate['employment_status'] ?? '')) !== '', 'detail' => null, 'raw_date' => null],
            ['label' => 'Referencias', 'verified' => count($candidate['references'] ?? []) > 0, 'detail' => null, 'raw_date' => null],
            ['label' => 'Estudio de aseguradora', 'verified' => $screening !== null, 'detail' => null, 'raw_date' => null],
            ['label' => 'Contrato', 'verified' => count($contractRows) > 0, 'detail' => null, 'raw_date' => null],
        ];
        $reviewSections = [
            ['key' => 'work', 'title' => 'Información laboral', 'detail' => 'Consultar la información declarada'],
            ['key' => 'references', 'title' => 'Referencias', 'detail' => 'Consultar referencias registradas'],
            ['key' => 'screening', 'title' => 'Estudio de aseguradora', 'detail' => 'Consultar el estado del estudio'],
        ];
        ?>
        <section class="candidate-section" data-candidate-section-body="overview">
            <div class="candidate-overview-grid">
                <div data-candidate-row="summary">
                    <section class="content-card candidate-card candidate-card--primary" aria-labelledby="candidate-summary-title">
                        <div class="candidate-summary-layout">
                            <div class="candidate-summary-contact">
                                <div class="candidate-summary-heading">
                                    <h2 id="candidate-summary-title"><?= $candidateIcon('file-signature') ?>Resumen de la solicitud</h2>
                                    <p class="muted">Información principal de la postulación</p>
                                </div>
                                <div class="candidate-fact-grid">
                                    <div class="candidate-fact"><?= $candidateIcon('account') ?><dl><dt>Nombre completo</dt><dd><?= $escape($candidate['full_name'] ?? '') ?></dd></dl></div>
                                    <div class="candidate-fact"><?= $candidateIcon('building') ?><dl><dt>Inmueble</dt><dd><?= $escape($unitLabel !== '' ? $unitLabel : 'Sin unidad registrada') ?></dd></dl></div>
                                    <div class="candidate-fact"><?= $candidateIcon('phone') ?><dl><dt>Celular o WhatsApp</dt><dd><?= trim((string) ($candidate['phone'] ?? '')) !== '' ? $escape($candidate['phone']) : 'Sin teléfono registrado' ?></dd></dl></div>
                                    <?php if ($propertyLocation !== ''): ?>
                                        <div class="candidate-fact candidate-fact--property-address"><?= $candidateIcon('map-pin') ?><dl><dt>Dirección del inmueble</dt><dd><?= $escape($propertyLocation) ?></dd></dl></div>
                                    <?php endif; ?>
                                    <div class="candidate-fact"><?= $candidateIcon('mail') ?><dl><dt>Correo</dt><dd><?= trim((string) ($candidate['email'] ?? '')) !== '' ? $escape($candidate['email']) : 'Sin correo registrado' ?></dd></dl></div>
                                    <div class="candidate-fact"><?= $candidateIcon('calendar') ?><dl><dt>Fecha de solicitud</dt><dd><?php if ($submittedAt !== null): ?><time datetime="<?= $escape($candidate['submitted_at']) ?>"><?= $escape($submittedAt) ?></time><?php else: ?>Sin envío registrado<?php endif; ?></dd></dl></div>
                                </div>
                            </div>
                            <aside class="candidate-state" aria-labelledby="candidate-state-title">
                                <h3 id="candidate-state-title">Estado actual</h3>
                                <div class="candidate-state-panel">
                                    <h4><?= $escape($statusLabel) ?></h4>
                                    <p><?= $escape($statusDescription) ?></p>
                                </div>
                            </aside>
                        </div>
                    </section>
                </div>

                <div class="candidate-secondary-grid" data-candidate-row="process">
                    <section class="content-card candidate-card candidate-card--progress" aria-labelledby="candidate-dates-title">
                        <h3 id="candidate-dates-title"><?= $candidateIcon('history') ?>Progreso de la solicitud</h3>
                        <p class="muted candidate-progress-subtitle">Fechas relevantes y otros hitos registrados</p>
                        <ol class="candidate-progress-list" aria-label="Hitos con registro verificable">
                            <?php foreach ($milestones as $milestone): ?>
                                <li class="<?= $milestone['verified'] ? 'candidate-milestone--recorded' : 'candidate-milestone--unrecorded' ?>" data-milestone-recorded="<?= $milestone['verified'] ? 'yes' : 'no' ?>">
                                    <span class="candidate-milestone-marker" aria-hidden="true"><?= $milestone['verified'] ? '✓' : '·' ?></span>
                                    <strong><?= $escape($milestone['label']) ?></strong>
                                    <span><?= $milestone['verified'] ? 'Con registro' : 'Sin registro' ?></span>
                                    <?php if ($milestone['detail'] !== null): ?>
                                        <time datetime="<?= $escape($milestone['raw_date']) ?>"><?= $escape($milestone['detail']) ?></time>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <?php if ($createdAt === null && $submittedAt === null && $updatedAt === null): ?>
                            <p class="muted">No hay fechas registradas para esta solicitud.</p>
                        <?php endif; ?>
                        <?php if ($updatedAt !== null): ?><p class="candidate-progress-last-update">Última actualización: <time datetime="<?= $escape($candidate['updated_at']) ?>"><?= $escape($updatedAt) ?></time></p><?php endif; ?>
                        <p class="candidate-progress-disclaimer">Los indicadores señalan registros existentes; no representan aprobación ni porcentaje de avance.</p>
                    </section>
                    <section class="content-card candidate-card candidate-card--next" aria-labelledby="candidate-next-title">
                        <h3 id="candidate-next-title"><?= $candidateIcon('history') ?>Apartados para revisar</h3>
                        <ol class="candidate-next-list">
                            <?php foreach ($reviewSections as $index => $section): ?>
                                <li>
                                    <span class="candidate-next-number" aria-hidden="true"><?= $index + 1 ?></span>
                                    <div><a href="<?= $escape($sectionHref($section['key'])) ?>"><?= $escape($section['title']) ?></a><p><?= $escape($section['detail']) ?></p></div>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                </div>

                <div class="candidate-detail-grid" data-candidate-row="details">
                    <section class="content-card candidate-card candidate-card--personal" aria-labelledby="candidate-personal-title">
                        <h3 id="candidate-personal-title"><?= $candidateIcon('account') ?>Detalles del candidato</h3>
                        <dl class="candidate-detail-facts">
                            <div><dt>Documento</dt><dd><?= trim((string) ($candidate['document_number'] ?? '')) !== '' ? $escape(strtoupper((string) ($candidate['document_type'] ?? '')) . ' ' . $candidate['document_number']) : 'Sin documento registrado' ?></dd></div>
                            <?php if ($birthDate !== null): ?><div><dt>Fecha de nacimiento</dt><dd><?= $escape($birthDate) ?></dd></div><?php endif; ?>
                        </dl>
                        <?php if ($birthDate === null && trim((string) ($candidate['current_address'] ?? '')) === ''): ?>
                            <p class="muted">Sin datos personales adicionales.</p>
                        <?php endif; ?>
                    </section>

                    <section class="content-card candidate-card candidate-card--contact" aria-labelledby="candidate-contact-title">
                        <h3 id="candidate-contact-title"><?= $candidateIcon('phone') ?>Información de contacto</h3>
                        <dl class="candidate-detail-facts">
                            <div><dt>Teléfono principal</dt><dd><?= trim((string) ($candidate['phone'] ?? '')) !== '' ? $escape($candidate['phone']) : 'Sin teléfono registrado' ?></dd></div>
                            <div><dt>Correo electrónico</dt><dd><?= trim((string) ($candidate['email'] ?? '')) !== '' ? $escape($candidate['email']) : 'Sin correo registrado' ?></dd></div>
                            <?php if (trim((string) ($candidate['current_address'] ?? '')) !== ''): ?>
                                <div><dt>Dirección actual del candidato</dt><dd><?= $escape($candidate['current_address']) ?></dd></div>
                            <?php endif; ?>
                        </dl>
                    </section>

                    <section class="content-card candidate-card candidate-card--property" aria-labelledby="candidate-property-title">
                        <h3 id="candidate-property-title"><?= $candidateIcon('building') ?>Información del inmueble</h3>
                        <?php if ($propertyDetails === null): ?>
                            <p class="candidate-property-name"><?= $escape($unitLabel !== '' ? $unitLabel : 'Unidad no disponible') ?></p>
                            <p class="muted">Detalles limitados por permisos.</p>
                        <?php else: ?>
                            <div class="candidate-property-main">
                                <?php if ($coverPhotoId !== null): ?>
                                    <img class="candidate-property-photo" src="<?= $escape($basePath) ?>/admin/units/<?= (int) $propertyDetails['unit_id'] ?>/media/<?= $coverPhotoId ?>" alt="Foto registrada de la unidad">
                                <?php else: ?>
                                    <div class="candidate-property-placeholder"><?= $candidateIcon('building') ?><small>Sin foto registrada</small></div>
                                <?php endif; ?>
                                <div>
                                    <p class="candidate-property-name"><?= $escape($unitLabel !== '' ? $unitLabel : $propertyDetails['unit_code']) ?></p>
                                    <dl class="candidate-detail-facts">
                                        <?php if (trim((string) ($propertyDetails['address'] ?? '')) !== ''): ?><div><dt>Dirección del inmueble</dt><dd><?= $escape($propertyDetails['address']) ?><?= trim((string) ($propertyDetails['city'] ?? '')) !== '' ? ', ' . $escape($propertyDetails['city']) : '' ?></dd></div><?php endif; ?>
                                        <div><dt>Tipo</dt><dd><?= ($propertyDetails['type'] ?? '') === 'apartment' ? 'Apartamento' : 'Parqueadero' ?></dd></div>
                                        <?php if ($humanMoney($propertyDetails['monthly_price']) !== null): ?><div><dt>Canon mensual</dt><dd><?= $escape($humanMoney($propertyDetails['monthly_price'])) ?></dd></div><?php endif; ?>
                                        <?php if ($humanMoney($propertyDetails['administration_fee']) !== null): ?><div><dt>Administración</dt><dd><?= $escape($humanMoney($propertyDetails['administration_fee'])) ?></dd></div><?php endif; ?>
                                        <?php if (is_numeric($propertyDetails['area_m2'])): ?><div><dt>Área</dt><dd><?= $escape($propertyDetails['area_m2']) ?> m²</dd></div><?php endif; ?>
                                        <?php if ($propertyDetails['bedrooms'] !== null): ?><div><dt>Habitaciones</dt><dd><?= (int) $propertyDetails['bedrooms'] ?></dd></div><?php endif; ?>
                                        <?php if ($propertyDetails['bathrooms'] !== null): ?><div><dt>Baños</dt><dd><?= $escape($propertyDetails['bathrooms']) ?></dd></div><?php endif; ?>
                                    </dl>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </section>

    <?php elseif ($activeSection === 'work'): ?>
        <section class="candidate-section" data-candidate-section-body="work">
            <section class="content-card">
                <div class="section-heading"><div><p class="eyebrow">Información laboral</p><h2>Información laboral</h2></div></div>
                <dl class="account-details">
                    <div><dt>Estado laboral</dt><dd><?= $escape($candidate['employment_status'] ?? '') ?></dd></div>
                    <div><dt>Empresa</dt><dd><?= $escape($candidate['employer_name'] ?? '') ?></dd></div>
                    <div><dt>Ocupación</dt><dd><?= $escape($candidate['occupation'] ?? '') ?></dd></div>
                    <div><dt>Ingreso mensual</dt><dd><?= $escape($candidate['monthly_income'] ?? '') ?></dd></div>
                    <div><dt>Notas</dt><dd><?= $escape($candidate['employment_notes'] ?? '') ?></dd></div>
                </dl>
            </section>
        </section>

    <?php elseif ($activeSection === 'household'): ?>
        <section class="candidate-section" data-candidate-section-body="household">
            <section class="content-card">
                <div class="section-heading"><div><p class="eyebrow">Hogar</p><h2>Ocupantes y mascotas</h2></div></div>
                <dl class="account-details">
                    <div><dt>Ocupantes</dt><dd><?= $escape($candidate['occupants_count'] ?? '') ?></dd></div>
                    <div><dt>Detalle de ocupantes</dt><dd><?= $escape($candidate['occupants_notes'] ?? '') ?></dd></div>
                    <div><dt>Mascotas</dt><dd><?= !empty($candidate['pets_declared']) ? 'Sí' : 'No' ?></dd></div>
                    <div><dt>Detalle de mascotas</dt><dd><?= $escape($candidate['pets_notes'] ?? '') ?></dd></div>
                </dl>
            </section>
        </section>

    <?php elseif ($activeSection === 'references'): ?>
        <section class="candidate-section" data-candidate-section-body="references">
            <section class="content-card">
                <div class="section-heading"><div><p class="eyebrow">Referencias</p><h2>Referencias</h2></div></div>
                <?php if (($candidate['references'] ?? []) === []): ?>
                    <p class="empty-state">No hay referencias registradas.</p>
                <?php else: ?>
                    <div class="users-grid">
                        <?php foreach ($candidate['references'] as $reference): ?>
                            <article class="user-card">
                                <strong><?= $escape($reference['name']) ?></strong>
                                <div><?= $escape($reference['reference_type'] ?? '') ?></div>
                                <div><?= $escape($reference['relationship'] ?? '') ?></div>
                                <div><?= $escape($reference['phone']) ?></div>
                                <div><?= $escape($reference['email'] ?? '') ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>

    <?php elseif ($activeSection === 'documents'): ?>
        <section class="candidate-section" data-candidate-section-body="documents">
            <section class="content-card">
                <div class="section-heading"><div><p class="eyebrow">Documentos</p><h2>Documentos</h2></div></div>
                <?php if ($documents === []): ?>
                    <p class="empty-state">No hay documentos adjuntos.</p>
                <?php else: ?>
                    <div class="users-grid">
                        <?php foreach ($documents as $document): ?>
                            <article class="user-card">
                                <strong><?= $escape($document['requirement_label']) ?></strong>
                                <div><?= $escape($document['original_name'] ?? 'Documento') ?></div>
                                <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/documents/<?= $escape($document['id']) ?>">Abrir documento</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </section>

    <?php elseif ($activeSection === 'screening'): ?>
        <section class="candidate-section" data-candidate-section-body="screening">
            <section class="content-card">
                <div class="section-heading"><div><p class="eyebrow">Aseguradora</p><h2>Estudio de aseguradora</h2></div></div>
                <?php if ($screening === null): ?>
                    <?php if ($screeningEditable): ?>
                        <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/screening">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <label>Proveedor<input type="text" name="provider" value="SURA" maxlength="120" required></label>
                            <label>Producto<input type="text" name="product" maxlength="190"></label>
                            <label>Correo de contacto<input type="email" name="contact_email" maxlength="190"></label>
                            <label>URL del estudio<input type="url" name="portal_url" maxlength="2048"></label>
                            <label>Notas internas<textarea name="notes"></textarea></label>
                            <label>Soporte / radicado<input type="text" name="support_reference" maxlength="255"></label>
                            <button type="submit">Crear estudio</button>
                        </form>
                    <?php else: ?>
                        <p class="empty-state">No hay estudio de aseguradora registrado.</p>
                    <?php endif; ?>
                <?php elseif ($screeningEditable): ?>
                    <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/screening">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <label>Proveedor<input type="text" name="provider" value="<?= $escape($screening['provider']) ?>" maxlength="120" required></label>
                        <label>Producto<input type="text" name="product" value="<?= $escape($screening['product'] ?? '') ?>" maxlength="190"></label>
                        <label>Estado<select name="status" required><?php foreach ($screeningTransitions as $status): ?><option value="<?= $escape($status) ?>"<?= $status === $screeningStatus ? ' selected' : '' ?>><?= $escape(HumanLabels::screeningStatus((string) $status)) ?></option><?php endforeach; ?></select></label>
                        <label>Correo de contacto<input type="email" name="contact_email" value="<?= $escape($screening['contact_email'] ?? '') ?>" maxlength="190"></label>
                        <label>URL del estudio<input type="url" name="portal_url" value="<?= $escape($screening['portal_url'] ?? '') ?>" maxlength="2048"></label>
                        <label>Resultado<textarea name="result"><?= $escape($screening['result'] ?? '') ?></textarea></label>
                        <label>Notas internas<textarea name="notes"><?= $escape($screening['notes'] ?? '') ?></textarea></label>
                        <label>Soporte / radicado<input type="text" name="support_reference" value="<?= $escape($screening['support_reference'] ?? '') ?>" maxlength="255"></label>
                        <button type="submit">Actualizar estudio</button>
                    </form>
                <?php else: ?>
                    <dl class="account-details">
                        <div><dt>Proveedor</dt><dd><?= $escape($screening['provider']) ?></dd></div>
                        <div><dt>Producto</dt><dd><?= $escape($screening['product'] ?? '') ?></dd></div>
                        <div><dt>Estado</dt><dd><?= $escape(HumanLabels::screeningStatus((string) $screening['status'])) ?></dd></div>
                        <div><dt>Correo de contacto</dt><dd><?= $escape($screening['contact_email'] ?? '') ?></dd></div>
                        <div><dt>URL</dt><dd><?= $escape($screening['portal_url'] ?? '') ?></dd></div>
                        <div><dt>Resultado</dt><dd><?= $escape($screening['result'] ?? '') ?></dd></div>
                        <div><dt>Notas</dt><dd><?= $escape($screening['notes'] ?? '') ?></dd></div>
                        <div><dt>Soporte / radicado</dt><dd><?= $escape($screening['support_reference'] ?? '') ?></dd></div>
                    </dl>
                <?php endif; ?>
            </section>
        </section>

    <?php elseif ($activeSection === 'contracts' && $contractContext !== null): ?>
        <section class="candidate-section" data-candidate-section-body="contracts">
            <section class="content-card" data-contract-context>
                <div class="section-heading"><div><p class="eyebrow">Contratos</p><h2>Contratos</h2></div></div>
                <?php if ($contractRows === []): ?>
                    <p class="empty-state">No hay contratos registrados para este candidato.</p>
                <?php else: ?>
                    <div class="users-grid">
                        <?php foreach ($contractRows as $contractRow): ?>
                            <article class="user-card">
                                <div class="user-card__head"><strong>Contrato #<?= $escape($contractRow['id']) ?></strong><span class="status-pill"><?= $escape($contractRow['status']) ?></span></div>
                                <div><?= $escape($contractRow['template_name'] ?? '') ?> · <?= $escape($contractRow['unit_code'] ?? '') ?></div>
                                <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/contracts/<?= $escape($contractRow['id']) ?>">Abrir contrato</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($canCreateContract): ?><a class="button" href="<?= $escape($basePath) ?>/admin/contracts?candidate_id=<?= (int) $candidate['id'] ?>">Crear contrato</a><?php endif; ?>
            </section>
        </section>

    <?php elseif ($activeSection === 'manage' && $canManageCandidates): ?>
        <section class="candidate-section" data-candidate-section-body="manage">
            <section class="content-card">
                <div class="section-heading"><div><p class="eyebrow">Administración</p><h2>Administrar</h2></div></div>
                <?php if ($allowedTransitions !== []): ?>
                    <form class="stack-form" method="post" action="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/status">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <label>Nuevo estado<select name="status" required><?php foreach ($allowedTransitions as $status): ?><option value="<?= $escape($status) ?>"><?= $escape(HumanLabels::candidateStatus((string) $status)) ?></option><?php endforeach; ?></select></label>
                        <button type="submit">Actualizar estado</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= $escape($basePath) ?>/admin/candidates/<?= $escape($candidate['id']) ?>/token/regenerate">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <button type="submit">Regenerar enlace de continuación</button>
                </form>
            </section>
        </section>
    <?php endif; ?>
</div>
