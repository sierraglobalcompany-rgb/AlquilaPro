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

$humanMoney = static function (mixed $amount): ?string {
    return is_numeric($amount) ? '$ ' . number_format((float) $amount, 0, ',', '.') . ' COP' : null;
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
                <div class="candidate-shell__location">
                    <?php if ($unitLabel !== ''): ?>
                        <p class="muted candidate-shell__unit"><?= $candidateIcon('building') ?><?= $escape($unitLabel) ?></p>
                    <?php endif; ?>
                    <?php if ($propertyLocation !== ''): ?>
                        <p class="muted candidate-shell__property-address"><?= $candidateIcon('map-pin') ?><?= $escape($propertyLocation) ?></p>
                    <?php endif; ?>
                </div>
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
        $milestones = [
            ['label' => 'Solicitud', 'verified' => $createdAt !== null, 'completed' => $submittedAt !== null, 'detail' => $createdAt, 'raw_date' => $candidate['created_at'] ?? null],
            ['label' => 'Información laboral', 'verified' => trim((string) ($candidate['employment_status'] ?? '')) !== '', 'completed' => false, 'detail' => null, 'raw_date' => null],
            ['label' => 'Referencias', 'verified' => count($candidate['references'] ?? []) > 0, 'completed' => false, 'detail' => null, 'raw_date' => null],
            ['label' => 'Estudio de aseguradora', 'verified' => $screening !== null, 'completed' => false, 'detail' => null, 'raw_date' => null],
            ['label' => 'Contrato', 'verified' => $contractContext === null ? null : count($contractRows) > 0, 'completed' => false, 'detail' => null, 'raw_date' => null],
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
                                <li class="<?= $milestone['completed'] ? 'candidate-milestone--completed' : ($milestone['verified'] === null ? 'candidate-milestone--unavailable' : ($milestone['verified'] ? 'candidate-milestone--recorded' : 'candidate-milestone--unrecorded')) ?>" data-milestone-recorded="<?= $milestone['verified'] === null ? 'unknown' : ($milestone['verified'] ? 'yes' : 'no') ?>" data-milestone-completed="<?= $milestone['completed'] ? 'yes' : 'no' ?>">
                                    <span class="candidate-milestone-marker" aria-hidden="true"><?= $milestone['completed'] ? '✓' : ($milestone['verified'] ? '•' : '·') ?></span>
                                    <strong><?= $escape($milestone['label']) ?></strong>
                                    <span><?= $milestone['completed'] ? 'Completada' : ($milestone['verified'] === null ? 'Sin acceso' : ($milestone['verified'] ? 'Con registro' : 'Sin registro')) ?></span>
                                    <?php if ($milestone['detail'] !== null): ?>
                                        <time datetime="<?= $escape($milestone['raw_date']) ?>"><?= $escape($milestone['detail']) ?></time>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <?php if ($createdAt === null && $submittedAt === null && $updatedAt === null): ?>
                            <p class="muted">No hay fechas registradas para esta solicitud.</p>
                        <?php endif; ?>
                        <p class="candidate-progress-footer"><?php if ($updatedAt !== null): ?>Última actualización: <time datetime="<?= $escape($candidate['updated_at']) ?>"><?= $escape($updatedAt) ?></time> · <?php endif; ?>Los registros no implican aprobación ni porcentaje de avance.</p>
                    </section>
                    <section class="content-card candidate-card candidate-card--next" aria-labelledby="candidate-next-title">
                        <h3 id="candidate-next-title"><?= $candidateIcon('files') ?>Secciones para consultar</h3>
                        <ul class="candidate-next-list">
                            <?php foreach ($reviewSections as $section): ?>
                                <li>
                                    <span class="candidate-next-marker" aria-hidden="true"><?= $candidateIcon($candidateSectionIcons[$section['key']] ?? 'files') ?></span>
                                    <a href="<?= $escape($sectionHref($section['key'])) ?>"><strong><?= $escape($section['title']) ?></strong><span><?= $escape($section['detail']) ?></span></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
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
        <?php
        $workStatus = trim((string) ($candidate['employment_status'] ?? ''));
        $workEmployer = trim((string) ($candidate['employer_name'] ?? ''));
        $workOccupation = trim((string) ($candidate['occupation'] ?? ''));
        $workNotes = trim((string) ($candidate['employment_notes'] ?? ''));
        $workIncome = $humanMoney($candidate['monthly_income'] ?? null);
        $workHasDetails = $workEmployer !== '' || $workOccupation !== '';
        $workHasAnything = $workStatus !== '' || $workHasDetails || $workIncome !== null || $workNotes !== '';
        ?>
        <section class="candidate-section" data-candidate-section-body="work">
            <section class="content-card candidate-card candidate-work-panel" aria-labelledby="candidate-work-title">
                <div class="candidate-work-heading">
                    <div class="candidate-work-heading__copy">
                        <h2 id="candidate-work-title"><?= $candidateIcon('briefcase') ?>Información laboral</h2>
                        <p class="muted">Información declarada por el candidato</p>
                    </div>
                    <?php if ($workStatus !== ''): ?>
                        <div class="candidate-work-status"><span>Estado laboral</span><strong><?= $escape($workStatus) ?></strong></div>
                    <?php endif; ?>
                </div>

                <?php if (!$workHasAnything): ?>
                    <p class="empty-state">No se registró información laboral.</p>
                <?php else: ?>
                    <?php if ($workHasDetails || $workIncome !== null): ?>
                        <div class="candidate-work-layout<?= $workHasDetails && $workIncome !== null ? ' candidate-work-layout--split' : '' ?>">
                            <?php if ($workHasDetails): ?>
                                <div class="candidate-work-facts" aria-label="Datos laborales declarados">
                                    <?php if ($workEmployer !== ''): ?>
                                        <div class="candidate-fact"><?= $candidateIcon('building') ?><dl><dt>Empresa</dt><dd><?= $escape($workEmployer) ?></dd></dl></div>
                                    <?php endif; ?>
                                    <?php if ($workOccupation !== ''): ?>
                                        <div class="candidate-fact"><?= $candidateIcon('account') ?><dl><dt>Ocupación</dt><dd><?= $escape($workOccupation) ?></dd></dl></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($workIncome !== null): ?>
                                <div class="candidate-work-income" aria-label="Ingresos declarados">
                                    <h3><?= $candidateIcon('wallet') ?>Ingresos declarados</h3>
                                    <dl class="candidate-work-money">
                                        <div><dt>Ingreso mensual</dt><dd><?= $escape($workIncome) ?></dd></div>
                                    </dl>
                                    <p class="candidate-work-income-context">Monto declarado, no verificado. No representa una evaluación de solvencia.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($workNotes !== ''): ?>
                        <div class="candidate-work-notes" aria-labelledby="candidate-work-notes-title">
                            <h3 id="candidate-work-notes-title">Observaciones laborales</h3>
                            <p><?= nl2br($escape($workNotes)) ?></p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </section>

    <?php elseif ($activeSection === 'household'): ?>
        <?php
        $occupantsCount = ($candidate['occupants_count'] ?? null) === null ? null : (int) $candidate['occupants_count'];
        $occupantsNotes = trim((string) ($candidate['occupants_notes'] ?? ''));
        $petsNotes = trim((string) ($candidate['pets_notes'] ?? ''));
        $householdOccupants = is_array($householdOccupants ?? null) ? $householdOccupants : [];
        $householdPets = is_array($householdPets ?? null) ? $householdPets : [];
        $householdCanManage = ($householdCanManage ?? $canManageCandidates ?? false) === true;
        $householdManual = is_array($householdManual ?? null) ? $householdManual : ['authorized' => false];
        $householdManualDocument = is_array($householdManual['document'] ?? null) ? $householdManual['document'] : null;
        $householdPhotoPath = rtrim((string) ($householdPhotoPath ?? ($basePath . '/admin/candidates/' . (int) $candidate['id'] . '/household')), '/');
        $petsAnswered = ($candidate['pets_answered_at'] ?? null) !== null;
        $petDeclarationResponse = !$petsAnswered
            ? 'No confirmado'
            : (!empty($candidate['pets_declared']) ? 'Sí, se declararon mascotas' : 'No se declararon mascotas');
        $registeredOccupants = count($householdOccupants);
        $registeredTotal = 1 + $registeredOccupants;
        $registeredPetCount = count($householdPets);
        $petSizeLabel = static function (mixed $value): string {
            $raw = trim((string) $value);

            return match (strtolower($raw)) {
                'small' => 'Pequeño',
                'medium' => 'Mediano',
                'large' => 'Grande',
                default => $raw,
            };
        };
        $occupantsMatch = $occupantsCount !== null && $occupantsCount === $registeredTotal;
        $householdFormAction = $householdPhotoPath;
        $householdRecordUrl = static fn (string $kind, int $recordId): string => $householdPhotoPath . '/' . rawurlencode($kind) . '/' . $recordId . '/photo';
        $householdInitials = static function (mixed $fullName): string {
            $parts = preg_split('/\s+/u', trim((string) $fullName)) ?: [];
            $initials = '';
            foreach (array_slice(array_values(array_filter($parts, static fn (string $part): bool => $part !== '')), 0, 2) as $part) {
                $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            }
            return $initials !== '' ? $initials : '—';
        };
        ?>
        <section class="candidate-section" data-candidate-section-body="household">
            <div class="candidate-household-grid">
                <section class="content-card candidate-card candidate-household-summary" data-household-region="summary" aria-labelledby="candidate-household-title">
                    <div class="candidate-household-heading">
                        <h2 id="candidate-household-title"><?= $candidateIcon('home') ?>Resumen del hogar</h2>
                        <p class="muted">Datos declarados en la solicitud; no constituyen una evaluación de riesgo.</p>
                    </div>
                    <div class="candidate-household-facts">
                        <p><span>Total declarado</span><strong><?= $occupantsCount === null ? 'Cantidad no declarada' : $escape($occupantsCount . ($occupantsCount === 1 ? ' ocupante declarado' : ' ocupantes declarados')) ?></strong></p>
                        <p><span>Fichas adicionales</span><strong><?= $escape($registeredOccupants) ?> registradas</strong></p>
                        <p><span>Mascotas</span><strong><?= $escape($registeredPetCount . ($registeredPetCount === 1 ? ' ficha registrada' : ' fichas registradas')) ?></strong></p>
                    </div>
                </section>

                <section class="content-card candidate-card candidate-household-information" data-household-region="information" aria-labelledby="candidate-household-information-title">
                    <h3 id="candidate-household-information-title"><?= $candidateIcon('history') ?>Estado de la información</h3>
                    <dl class="candidate-household-details">
                        <div><dt>Solicitante</dt><dd><?= $escape($candidate['full_name'] ?? 'Sin nombre registrado') ?></dd></div>
                        <div><dt>Personas en fichas</dt><dd><?= $escape($registeredTotal) ?>, incluido el solicitante</dd></div>
                        <div><dt>Respuesta de mascotas</dt><dd><?= $escape($petDeclarationResponse) ?><?php if ($petsAnswered): ?> <small class="candidate-household-response-date">Registrada <time datetime="<?= $escape(str_replace(' ', 'T', (string) $candidate['pets_answered_at'])) ?>"><?= $escape($candidate['pets_answered_at']) ?></time></small><?php endif; ?></dd></div>
                    </dl>
                    <?php if (!$occupantsMatch && ($occupantsCount !== null || $householdOccupants !== [])): ?>
                        <p class="candidate-household-notice">El total declarado y las fichas disponibles no coinciden. Revisa la información; los registros anteriores se conservan sin completar personas automáticamente.</p>
                    <?php elseif ($occupantsCount === null): ?>
                        <p class="muted">No hay un total de ocupantes declarado.</p>
                    <?php endif; ?>
                </section>

                <section class="content-card candidate-card candidate-household-occupants" data-household-region="occupants" aria-labelledby="candidate-household-occupants-title">
                    <div class="candidate-household-region-heading">
                        <h3 id="candidate-household-occupants-title"><?= $candidateIcon('users') ?>Ocupantes del hogar</h3>
                        <p class="muted">Solicitante principal y personas adicionales declaradas.</p>
                    </div>
                    <div class="candidate-household-records">
                        <article class="candidate-household-record candidate-household-record--applicant" data-household-applicant>
                            <span class="candidate-household-avatar candidate-household-avatar--initials" aria-label="Solicitante principal"><?= $escape($householdInitials($candidate['full_name'] ?? '')) ?></span>
                            <div class="candidate-household-record__body">
                                <h4><?= $escape($candidate['full_name'] ?? 'Solicitante principal') ?></h4>
                                <p>Solicitante principal</p>
                            </div>
                        </article>
                        <?php if ($householdOccupants === []): ?>
                            <p class="empty-state">No hay fichas de otras personas registradas.</p>
                        <?php else: ?>
                            <?php foreach ($householdOccupants as $occupant): ?>
                                <article class="candidate-household-record">
                                    <?php if (!empty($occupant['has_photo'])): ?>
                                        <img class="candidate-household-avatar" src="<?= $escape($householdRecordUrl('occupant', (int) $occupant['id'])) ?>" alt="Fotografía de <?= $escape($occupant['full_name'] ?? 'ocupante') ?>" loading="lazy">
                                    <?php else: ?>
                                        <span class="candidate-household-avatar candidate-household-avatar--initials" aria-label="Iniciales de <?= $escape($occupant['full_name'] ?? 'ocupante') ?>"><?= $escape($householdInitials($occupant['full_name'] ?? '')) ?></span>
                                    <?php endif; ?>
                                    <div class="candidate-household-record__body">
                                        <h4><?= $escape($occupant['full_name'] ?? '') ?></h4>
                                        <p><?= $escape($occupant['relationship'] ?? 'Relación no indicada') ?><?php if (($occupant['age_years'] ?? null) !== null): ?> · <?= $escape($occupant['age_years']) ?> años aprox.<?php endif; ?></p>
                                        <?php if (trim((string) ($occupant['occupation_or_activity'] ?? '')) !== ''): ?><p><?= $escape($occupant['occupation_or_activity']) ?></p><?php endif; ?>
                                        <?php if (trim((string) ($occupant['notes'] ?? '')) !== ''): ?><p class="candidate-household-record__notes"><?= nl2br($escape($occupant['notes'])) ?></p><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($householdCanManage): ?>
                        <details class="candidate-household-manager">
                            <summary>Administrar fichas de ocupantes</summary>
                            <?php foreach ($householdOccupants as $occupant): ?>
                                <form class="candidate-household-edit" method="post" enctype="multipart/form-data" action="<?= $escape($householdFormAction . '/occupant') ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="record_id" value="<?= $escape($occupant['id']) ?>">
                                    <label>Nombre<input name="full_name" maxlength="190" required value="<?= $escape($occupant['full_name'] ?? '') ?>"></label>
                                    <label>Relación<input name="relationship" maxlength="100" required value="<?= $escape($occupant['relationship'] ?? '') ?>"></label>
                                    <label>Edad aproximada<input type="number" name="age_years" min="0" max="120" value="<?= $escape($occupant['age_years'] ?? '') ?>"></label>
                                    <label>Ocupación o actividad<input name="occupation_or_activity" maxlength="190" value="<?= $escape($occupant['occupation_or_activity'] ?? '') ?>"></label>
                                    <label>Notas<textarea name="notes" maxlength="5000"><?= $escape($occupant['notes'] ?? '') ?></textarea></label>
                                    <label>Fotografía opcional<input type="file" name="photo" accept="image/png,image/jpeg"></label>
                                    <label><input type="checkbox" name="photo_consent" value="1"> Confirmo que existe autorización para guardar la foto.</label>
                                    <?php if (!empty($occupant['has_photo'])): ?><label><input type="checkbox" name="remove_photo" value="1"> Retirar fotografía</label><?php endif; ?>
                                    <button class="button button--quiet" type="submit">Guardar cambios</button>
                                </form>
                                <form method="post" action="<?= $escape($householdFormAction . '/occupant') ?>" onsubmit="return confirm('¿Retirar esta ficha de ocupante?')">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="record_id" value="<?= $escape($occupant['id']) ?>">
                                    <button class="button button--quiet" type="submit">Retirar ficha</button>
                                </form>
                            <?php endforeach; ?>
                            <form class="candidate-household-edit" method="post" enctype="multipart/form-data" action="<?= $escape($householdFormAction . '/occupant') ?>">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <h4>Nueva ficha</h4>
                                <label>Nombre<input name="full_name" maxlength="190" required></label><label>Relación<input name="relationship" maxlength="100" required></label>
                                <label>Edad aproximada<input type="number" name="age_years" min="0" max="120"></label><label>Ocupación o actividad<input name="occupation_or_activity" maxlength="190"></label>
                                <label>Notas<textarea name="notes" maxlength="5000"></textarea></label><label>Fotografía opcional<input type="file" name="photo" accept="image/png,image/jpeg"></label>
                                <label><input type="checkbox" name="photo_consent" value="1"> Confirmo que existe autorización para guardar la foto.</label><button class="button" type="submit">Crear ficha</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </section>

                <section class="content-card candidate-card candidate-household-pets" data-household-region="pets" aria-labelledby="candidate-household-pets-title">
                    <h3 id="candidate-household-pets-title"><?= $candidateIcon('account') ?>Mascotas <span class="candidate-household-count"><?= $escape($registeredPetCount . ($registeredPetCount === 1 ? ' ficha registrada' : ' fichas registradas')) ?></span></h3>
                    <?php if ($householdPets === []): ?>
                        <p class="empty-state">No hay fichas de mascotas registradas.</p>
                    <?php else: ?>
                        <div class="candidate-household-records">
                            <?php foreach ($householdPets as $pet): ?>
                                <article class="candidate-household-record">
                                    <?php if (!empty($pet['has_photo'])): ?><img class="candidate-household-avatar" src="<?= $escape($householdRecordUrl('pet', (int) $pet['id'])) ?>" alt="Fotografía de <?= $escape($pet['name'] ?? 'mascota') ?>" loading="lazy">
                                    <?php else: ?><span class="candidate-household-avatar candidate-household-avatar--initials" aria-label="Iniciales de <?= $escape($pet['name'] ?? 'mascota') ?>"><?= $escape($householdInitials($pet['name'] ?? '')) ?></span><?php endif; ?>
                                    <div class="candidate-household-record__body"><h4><?= $escape($pet['name'] ?? '') ?></h4><p><?= $escape($pet['species'] ?? 'Especie no indicada') ?><?php if (trim((string) ($pet['breed'] ?? '')) !== ''): ?> · <?= $escape($pet['breed']) ?><?php endif; ?></p>
                                        <?php if (($pet['age_years'] ?? null) !== null || trim((string) ($pet['size_category'] ?? '')) !== ''): ?><p><?php if (($pet['age_years'] ?? null) !== null): ?><?= $escape($pet['age_years']) ?> años aprox.<?php endif; ?><?php if (trim((string) ($pet['size_category'] ?? '')) !== ''): ?><?= ($pet['age_years'] ?? null) !== null ? ' · ' : '' ?><?= $escape($petSizeLabel($pet['size_category'] ?? null)) ?><?php endif; ?></p><?php endif; ?>
                                        <?php if (($pet['vaccination_declaration'] ?? null) !== null): ?><p>Declaración: <?= $pet['vaccination_declaration'] === 'declared_vaccinated' ? 'vacunación declarada' : 'no vacunación declarada' ?></p><?php endif; ?>
                                        <?php if (trim((string) ($pet['notes'] ?? '')) !== ''): ?><p class="candidate-household-record__notes"><?= nl2br($escape($pet['notes'])) ?></p><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($householdCanManage): ?>
                        <details class="candidate-household-manager"><summary>Administrar fichas de mascotas</summary>
                            <?php foreach ($householdPets as $pet): ?>
                                <form class="candidate-household-edit" method="post" enctype="multipart/form-data" action="<?= $escape($householdFormAction . '/pet') ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="record_id" value="<?= $escape($pet['id']) ?>">
                                    <label>Nombre<input name="name" maxlength="190" required value="<?= $escape($pet['name'] ?? '') ?>"></label><label>Especie<input name="species" maxlength="80" required value="<?= $escape($pet['species'] ?? '') ?>"></label>
                                    <label>Raza<input name="breed" maxlength="120" value="<?= $escape($pet['breed'] ?? '') ?>"></label><label>Tamaño<input name="size_category" maxlength="32" value="<?= $escape($pet['size_category'] ?? '') ?>"></label>
                                    <label>Edad aproximada<input type="number" name="age_years" min="0" max="100" value="<?= $escape($pet['age_years'] ?? '') ?>"></label>
                                    <label>Declaración de vacunación<select name="vaccination_declaration"><option value="">Sin declarar</option><option value="declared_vaccinated" <?= ($pet['vaccination_declaration'] ?? '') === 'declared_vaccinated' ? 'selected' : '' ?>>Declara vacunación</option><option value="declared_not_vaccinated" <?= ($pet['vaccination_declaration'] ?? '') === 'declared_not_vaccinated' ? 'selected' : '' ?>>Declara no vacunación</option></select></label>
                                    <label>Notas<textarea name="notes" maxlength="5000"><?= $escape($pet['notes'] ?? '') ?></textarea></label><label>Fotografía opcional<input type="file" name="photo" accept="image/png,image/jpeg"></label>
                                    <label><input type="checkbox" name="photo_consent" value="1"> Confirmo que existe autorización para guardar la foto.</label><?php if (!empty($pet['has_photo'])): ?><label><input type="checkbox" name="remove_photo" value="1"> Retirar fotografía</label><?php endif; ?>
                                    <button class="button button--quiet" type="submit">Guardar cambios</button>
                                </form>
                                <form method="post" action="<?= $escape($householdFormAction . '/pet') ?>" onsubmit="return confirm('¿Retirar esta ficha de mascota?')"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="record_id" value="<?= $escape($pet['id']) ?>"><button class="button button--quiet" type="submit">Retirar ficha</button></form>
                            <?php endforeach; ?>
                            <form class="candidate-household-edit" method="post" enctype="multipart/form-data" action="<?= $escape($householdFormAction . '/pet') ?>">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><h4>Nueva ficha</h4>
                                <label>Nombre<input name="name" maxlength="190" required></label><label>Especie<input name="species" maxlength="80" required></label><label>Raza<input name="breed" maxlength="120"></label><label>Tamaño<input name="size_category" maxlength="32"></label>
                                <label>Edad aproximada<input type="number" name="age_years" min="0" max="100"></label><label>Declaración de vacunación<select name="vaccination_declaration"><option value="">Sin declarar</option><option value="declared_vaccinated">Declara vacunación</option><option value="declared_not_vaccinated">Declara no vacunación</option></select></label>
                                <label>Notas<textarea name="notes" maxlength="5000"></textarea></label><label>Fotografía opcional<input type="file" name="photo" accept="image/png,image/jpeg"></label><label><input type="checkbox" name="photo_consent" value="1"> Confirmo que existe autorización para guardar la foto.</label><button class="button" type="submit">Crear ficha</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </section>

                <section class="content-card candidate-card candidate-household-rules" data-household-region="rules" aria-labelledby="candidate-household-rules-title">
                    <h3 id="candidate-household-rules-title"><?= $candidateIcon('files') ?>Reglas y observaciones</h3>
                    <?php if ($occupantsNotes === '' && $petsNotes === ''): ?><p class="muted">No hay observaciones adicionales declaradas.</p><?php endif; ?>
                    <?php if ($occupantsNotes !== ''): ?><div class="candidate-household-notes"><strong>Observaciones sobre ocupantes</strong><p><?= nl2br($escape($occupantsNotes)) ?></p></div><?php endif; ?>
                    <?php if ($petsNotes !== ''): ?><div class="candidate-household-notes"><strong>Observaciones sobre mascotas</strong><p><?= nl2br($escape($petsNotes)) ?></p></div><?php endif; ?>
                    <?php if (!$householdManual['authorized']): ?>
                        <p class="muted">La información del manual no está disponible para este perfil.</p>
                    <?php elseif ($householdManualDocument === null): ?>
                        <p class="muted">Manual de convivencia no registrado.</p>
                        <p><a class="button button--quiet" href="<?= $escape($householdManual['module_url']) ?>">Abrir Documentos de la propiedad</a></p>
                    <?php else: ?>
                        <p>Fuente: manual de convivencia vigente de la propiedad.</p>
                        <p><strong><?= $escape($householdManualDocument['title']) ?></strong> · Versión #<?= $escape($householdManualDocument['id']) ?> · <?= $escape($householdManualDocument['created_at']) ?></p>
                        <p><a class="button button--quiet" href="<?= $escape($householdManual['module_url']) ?>">Ver versión en Documentos</a></p>
                    <?php endif; ?>
                </section>

                <section class="content-card candidate-card candidate-household-next" data-household-region="next-steps" aria-labelledby="candidate-household-next-title">
                    <h3 id="candidate-household-next-title"><?= $candidateIcon('history') ?>Secciones para consultar</h3>
                    <ul class="candidate-household-next-list">
                        <?php foreach ([['work', 'Información laboral', 'Consultar datos laborales declarados'], ['references', 'Referencias', 'Consultar referencias registradas'], ['documents', 'Documentos', 'Consultar los requisitos adjuntos']] as [$key, $label, $description]): ?>
                            <?php if (in_array($key, array_column($candidateSections, 'key'), true)): ?><li><a href="<?= $escape($sectionHref($key)) ?>"><strong><?= $escape($label) ?></strong><span><?= $escape($description) ?></span></a></li><?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>
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
        <?php
        $documentRequirements = is_array($documentRequirements ?? null) ? $documentRequirements : [];
        $documentsByRequirement = [];
        foreach ($documents as $document) {
            $documentsByRequirement[(int) $document['requirement_id']] = $document;
        }
        $requiredCount = 0;
        $receivedRequiredCount = 0;
        $activeRequirementIds = [];
        $pendingRequired = [];
        foreach ($documentRequirements as $requirement) {
            $requirementId = (int) $requirement['id'];
            $activeRequirementIds[$requirementId] = true;
            if ($requirement['is_required']) {
                ++$requiredCount;
                if (isset($documentsByRequirement[$requirementId])) {
                    ++$receivedRequiredCount;
                } else {
                    $pendingRequired[] = $requirement;
                }
            }
        }
        $otherDocuments = array_values(array_filter(
            $documents,
            static fn (array $document): bool => !isset($activeRequirementIds[(int) $document['requirement_id']]),
        ));
        ?>
        <section class="candidate-section" data-candidate-section-body="documents">
            <div class="candidate-documents-layout">
                <section class="content-card candidate-card candidate-documents-main" aria-labelledby="candidate-documents-title">
                    <div class="candidate-documents-heading">
                        <div>
                            <p class="eyebrow">Documentación</p>
                            <h2 id="candidate-documents-title">Documentos del candidato</h2>
                            <p class="muted">Archivos recibidos y requisitos reales de esta unidad.</p>
                        </div>
                    </div>
                    <?php if ($documentRequirements !== []): ?>
                        <div class="candidate-document-list" aria-label="Requisitos documentales activos">
                            <?php foreach ($documentRequirements as $requirement): ?>
                                <?php
                                $attached = $documentsByRequirement[(int) $requirement['id']] ?? null;
                                $received = $attached !== null;
                                ?>
                                <article class="candidate-document-row" data-document-status="<?= $received ? 'received' : ($requirement['is_required'] ? 'pending' : 'optional') ?>">
                                    <span class="candidate-document-icon" aria-hidden="true"><?= $candidateIcon('files') ?></span>
                                    <div class="candidate-document-info">
                                        <h3><?= $escape($requirement['label']) ?></h3>
                                        <?php if (trim((string) ($requirement['description'] ?? '')) !== ''): ?>
                                            <p class="muted"><?= $escape($requirement['description']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($received): ?>
                                            <p class="muted"><?= $escape($attached['original_name'] ?? 'Documento') ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="candidate-document-actions">
                                        <span class="candidate-document-state"><?= $received ? 'Recibido' : ($requirement['is_required'] ? 'Pendiente de recibir' : 'Opcional, no aportado') ?></span>
                                        <span class="candidate-document-kind"><?= $requirement['is_required'] ? 'Obligatorio' : 'Opcional' ?></span>
                                        <?php if ($received): ?>
                                            <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/candidates/<?= (int) $candidate['id'] ?>/documents/<?= (int) $attached['id'] ?>">Abrir documento</a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($documents === []): ?>
                        <p class="empty-state">No hay requisitos documentales activos configurados para esta unidad. No hay documentos adjuntos.</p>
                    <?php else: ?>
                        <p class="muted">No hay requisitos documentales activos configurados para esta unidad.</p>
                    <?php endif; ?>
                    <?php if ($otherDocuments !== []): ?>
                        <section class="candidate-document-history" aria-labelledby="candidate-document-history-title">
                            <h3 id="candidate-document-history-title">Otros documentos recibidos</h3>
                            <p class="muted">Se conservan los documentos aportados a requisitos que ya no están activos.</p>
                            <div class="candidate-document-list">
                                <?php foreach ($otherDocuments as $document): ?>
                                    <article class="candidate-document-row">
                                        <span class="candidate-document-icon" aria-hidden="true"><?= $candidateIcon('files') ?></span>
                                        <div class="candidate-document-info">
                                            <h3><?= $escape($document['requirement_label']) ?></h3>
                                            <p class="muted"><?= $escape($document['original_name'] ?? 'Documento') ?></p>
                                        </div>
                                        <div class="candidate-document-actions">
                                            <span class="candidate-document-state">Recibido anteriormente</span>
                                            <a class="button button--quiet" href="<?= $escape($basePath) ?>/admin/candidates/<?= (int) $candidate['id'] ?>/documents/<?= (int) $document['id'] ?>">Abrir documento</a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                </section>
                <?php if ($documentRequirements !== []): ?>
                    <aside class="candidate-documents-side" aria-label="Resumen documental">
                        <section class="content-card candidate-card candidate-documents-summary" aria-labelledby="candidate-documents-summary-title">
                            <h3 id="candidate-documents-summary-title">Estado de recepción</h3>
                            <?php if ($requiredCount > 0): ?>
                                <p class="candidate-documents-count" data-document-required-count="<?= $requiredCount ?>" data-document-received-count="<?= $receivedRequiredCount ?>">
                                    <strong><?= $receivedRequiredCount ?> de <?= $requiredCount ?></strong>
                                    <span>requisitos obligatorios recibidos</span>
                                </p>
                                <progress value="<?= $receivedRequiredCount ?>" max="<?= $requiredCount ?>" aria-label="Requisitos obligatorios recibidos"></progress>
                                <p class="muted"><?= $receivedRequiredCount ?> de <?= $requiredCount ?> requisitos obligatorios recibidos. Esto no indica revisión ni aprobación.</p>
                            <?php else: ?>
                                <p class="muted">Esta unidad solo tiene requisitos documentales opcionales.</p>
                            <?php endif; ?>
                        </section>
                        <?php if ($pendingRequired !== []): ?>
                            <section class="content-card candidate-card candidate-documents-pending" aria-labelledby="candidate-documents-pending-title">
                                <h3 id="candidate-documents-pending-title">Requisitos pendientes</h3>
                                <ul>
                                    <?php foreach ($pendingRequired as $requirement): ?>
                                        <li><?= $escape($requirement['label']) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>
                    </aside>
                <?php endif; ?>
            </div>
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
