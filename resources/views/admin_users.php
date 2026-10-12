<?php

declare(strict_types=1);

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<section class="surface">
    <p class="eyebrow">Apps</p>
    <h1>Usuarios</h1>
    <p class="hint">Administra accesos y consulta el estado de las cuentas existentes.</p>
</section>

<section class="users-grid" aria-label="Usuarios registrados">
    <?php foreach ($users as $listedUser): ?>
        <article class="user-card">
            <div class="user-card__head">
                <strong><?= $escape($listedUser['full_name']) ?></strong>
                <span class="status-pill<?= $listedUser['is_active'] ? ' status-pill--active' : '' ?>">
                    <?= $listedUser['is_active'] ? 'Activo' : 'Inactivo' ?>
                </span>
            </div>
            <div class="user-card__email"><?= $escape($listedUser['email']) ?></div>
            <div class="user-card__role"><?= $escape($listedUser['role']) ?></div>
        </article>
    <?php endforeach; ?>
</section>

<?php if ($canManageUsers): ?>
    <section class="surface user-create">
        <h2>Agregar usuario</h2>
        <form method="post" action="<?= $escape($basePath) ?>/admin/users">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label>Nombre <input name="full_name" autocomplete="name" required></label>
            <label>Correo <input type="email" name="email" autocomplete="email" required></label>
            <label>Rol
                <select name="role">
                    <option value="admin">Administrador</option>
                    <option value="gestor">Gestor</option>
                </select>
            </label>
            <label>Contraseña temporal <input type="password" name="temporary_password" minlength="12" autocomplete="new-password" required></label>
            <button type="submit">Crear usuario</button>
        </form>
    </section>
<?php endif; ?>
