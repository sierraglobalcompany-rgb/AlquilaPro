<?php

declare(strict_types=1);
?>
<h1>Iniciar sesión</h1>
<p class="hint">Ingresa para administrar Alquila Pro.</p>
<?php if (is_string($error ?? null) && $error !== ''): ?><p role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<?php require __DIR__ . '/partials/login_form.php'; ?>
