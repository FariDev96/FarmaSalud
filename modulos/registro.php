<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$okMsg = $errMsg = '';

// CSRF simple
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
      throw new Exception('Sesión inválida. Vuelve a intentar.');
    }

    // ---- inputs
    $nombre   = trim($_POST['nombre'] ?? '');
    $correo   = strtolower(trim($_POST['correo'] ?? ''));
    $pass1    = (string)($_POST['contrasena'] ?? '');
    $pass2    = (string)($_POST['contrasena2'] ?? '');
    $consent  = isset($_POST['acepta_datos']) ? 1 : 0; // Ley de datos
    $rol_id   = 2; // siempre Cliente

    // ---- validaciones
    if ($nombre === '' || $correo === '' || $pass1 === '' || $pass2 === '') {
      throw new Exception('Todos los campos son obligatorios.');
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
      throw new Exception('El correo no es válido.');
    }
    if (strlen($pass1) < 8) {
      throw new Exception('La contraseña debe tener mínimo 8 caracteres.');
    }
    if ($pass1 !== $pass2) {
      throw new Exception('Las contraseñas no coinciden.');
    }
    if (!$consent) {
      throw new Exception('Debes aceptar el tratamiento de datos personales.');
    }

    // ---- verificar correo único
    $st = $conn->prepare('SELECT id FROM usuarios WHERE correo = ? LIMIT 1');
    $st->execute([$correo]);
    if ($st->fetch()) {
      throw new Exception('Ya existe una cuenta con ese correo.');
    }

    // ---- insertar
    $hash = password_hash($pass1, PASSWORD_DEFAULT);
    $ins = $conn->prepare("
      INSERT INTO usuarios (nombre, correo, contrasena, rol_id, acepta_datos, fecha_registro)
      VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $ins->execute([$nombre, $correo, $hash, $rol_id, $consent]);

    $okMsg = '¡Registro exitoso! Ya puedes iniciar sesión.';
    // opcional: redirigir tras 2s
    echo '<meta http-equiv="refresh" content="2;url=login.php">';
  } catch (Throwable $e) {
    $errMsg = $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">

  <!-- Inter + Bootstrap + Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b;
      --fs-primary:#2563eb; --fs-primary-600:#1d4ed8; --fs-accent:#10b981;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    body{
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
      background:
        radial-gradient(1000px 600px at 12% -18%, rgba(37,99,235,.06), transparent 60%),
        radial-gradient(900px 550px at 100% 0%, rgba(16,185,129,.06), transparent 60%),
        linear-gradient(180deg, var(--fs-bg) 0%, #fff 100%);
      color:var(--fs-text);
      letter-spacing:.2px;
    }
    .fs-card{ border-radius:var(--fs-radius); background:var(--fs-surface); border:1px solid var(--fs-border); box-shadow:var(--fs-shadow); }
    .btn-fs-primary{ --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary); --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600); --bs-btn-color:#fff; font-weight:600; border-radius:12px; }
    .fs-brand{ display:flex; align-items:center; gap:.6rem; font-weight:700; color:var(--fs-text); text-decoration:none; }
    .fs-badge{ width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-weight:900;color:#052e1a;background:linear-gradient(135deg,#93c5fd,var(--fs-primary)); box-shadow:0 6px 14px rgba(37,99,235,.22); }
    .input-group .form-control{ border-right:0; }
    .input-group-text.toggle{ background:#fff; border-left:0; cursor:pointer; }
    .small-muted{ color:var(--fs-dim); font-size:.9rem; }
  </style>
</head>
<body>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a class="fs-brand" href="login.php">
      <span class="fs-badge">FS</span><span>FarmaSalud</span>
    </a>
    <a href="login.php" class="btn btn-outline-secondary btn-sm">Iniciar sesión</a>
  </div>

  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
      <div class="fs-card p-4">
        <h1 class="h4 mb-3 text-center">Crear cuenta</h1>
        <p class="small-muted text-center mb-4">Regístrate para comprar más fácil. Podrás completar tus datos de envío en “Mi perfil”.</p>

        <?php if ($okMsg): ?>
          <div class="alert alert-success"><?= htmlspecialchars($okMsg) ?></div>
        <?php endif; ?>
        <?php if ($errMsg): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($errMsg) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

          <div class="mb-3">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="nombre" class="form-control" maxlength="120" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Correo electrónico</label>
            <input type="email" name="correo" class="form-control" maxlength="150" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <div class="input-group">
              <input type="password" name="contrasena" id="pass1" class="form-control" minlength="8" autocomplete="new-password" required>
              <span class="input-group-text toggle" onclick="toggle('pass1', this)" title="Mostrar/ocultar"><i class="bi bi-eye"></i></span>
            </div>
            <div class="form-text">Mínimo 8 caracteres.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Repetir contraseña</label>
            <div class="input-group">
              <input type="password" name="contrasena2" id="pass2" class="form-control" minlength="8" autocomplete="new-password" required>
              <span class="input-group-text toggle" onclick="toggle('pass2', this)" title="Mostrar/ocultar"><i class="bi bi-eye"></i></span>
            </div>
          </div>

          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" id="acepta_datos" name="acepta_datos" required>
            <label class="form-check-label" for="acepta_datos">
              Acepto el tratamiento de datos personales (Ley 1581 de 2012).
            </label>
          </div>

          <button type="submit" class="btn btn-fs-primary w-100">
            <i class="bi bi-person-plus"></i> Registrarme
          </button>
        </form>

        <div class="text-center mt-3">
          ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggle(id, el){
  var i = document.getElementById(id);
  var icon = el.querySelector('i');
  if(i.type === 'password'){ i.type = 'text'; icon.className='bi bi-eye-slash'; }
  else{ i.type = 'password'; icon.className='bi bi-eye'; }
}
</script>
</body>
</html>


