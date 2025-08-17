<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
    header("Location: login.php");
    exit;
}

$mensajeOk = '';
$errores   = [];

/** Bloqueados en la creación: Admin (1) y Visitante (7) */
$rolesBloqueados = [1, 7];

/** Traer roles permitidos desde BD (todo lo que NO esté bloqueado) */
try {
    $place = implode(',', array_fill(0, count($rolesBloqueados), '?'));
    $stmt  = $conn->prepare("SELECT id, nombre FROM roles WHERE id NOT IN ($place) ORDER BY nombre ASC");
    $stmt->execute($rolesBloqueados);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Fallback mínimo si algo falla
    $roles = [
        ['id' => 2, 'nombre' => 'Cliente'],
        ['id' => 3, 'nombre' => 'Soporte'],
        ['id' => 4, 'nombre' => 'Farmacéutico'],
        ['id' => 5, 'nombre' => 'Contador'],
        ['id' => 6, 'nombre' => 'Distribuidor'],
    ];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre     = trim($_POST['nombre'] ?? '');
    $correo     = strtolower(trim($_POST['correo'] ?? ''));
    $passPlain  = (string)($_POST['contrasena'] ?? '');
    $rol_id     = (int)($_POST['rol_id'] ?? 0);

    // Validaciones
    if ($nombre === '')                                 $errores[] = "El nombre es obligatorio.";
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "Correo electrónico inválido.";
    if (strlen($passPlain) < 6)                         $errores[] = "La contraseña debe tener al menos 6 caracteres.";
    if (in_array($rol_id, $rolesBloqueados, true))      $errores[] = "Ese rol no se puede asignar desde aquí.";

    // Verificar que el rol existe y está permitido
    $idsPermitidos = array_map(fn($r) => (int)$r['id'], $roles);
    if (!in_array($rol_id, $idsPermitidos, true)) {
        $errores[] = "Rol seleccionado inválido.";
    }

    // Correo único
    if (!$errores) {
        try {
            $u = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = ?");
            $u->execute([$correo]);
            if ((int)$u->fetchColumn() > 0) {
                $errores[] = "Ese correo ya está registrado.";
            }
        } catch (Throwable $e) {
            // si falla la validación, dejamos continuar, pero no debería
        }
    }

    // Insert
    if (!$errores) {
        try {
            $hash = password_hash($passPlain, PASSWORD_DEFAULT);
            $ins  = $conn->prepare("
                INSERT INTO usuarios (nombre, correo, contrasena, rol_id, fecha_registro)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $ins->execute([$nombre, $correo, $hash, $rol_id]);
            $mensajeOk = "✅ Usuario agregado correctamente.";
            $_POST = []; // limpia formulario
        } catch (Throwable $e) {
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Agregar Usuario — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --fs-border:#e8edf3; --fs-shadow:0 12px 28px rgba(2,6,23,.06); }
    .navbar{ background:#2563eb; }
    .card-fs{ background:#fff; border:1px solid var(--fs-border); border-radius:16px; box-shadow:var(--fs-shadow); }
  </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container-fluid">
    <span class="navbar-brand fw-semibold">FarmaSalud | Admin</span>
    <a href="gestionar_usuarios.php" class="btn btn-sm btn-light ms-auto">← Volver</a>
  </div>
</nav>

<div class="container py-4">
  <div class="card-fs p-4">
    <h2 class="mb-3">➕ Agregar Usuario</h2>

    <?php if (!empty($mensajeOk)): ?>
      <div class="alert alert-success"><?= $mensajeOk ?></div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
      <div class="alert alert-danger">
        <strong>Revisa lo siguiente:</strong>
        <ul class="mb-0">
          <?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" required
                 value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Correo</label>
          <input type="email" name="correo" class="form-control" required
                 value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Contraseña</label>
          <div class="input-group">
            <input type="password" name="contrasena" id="pass" class="form-control" minlength="6" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePass">👁</button>
          </div>
          <div class="form-text">Mínimo 6 caracteres.</div>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Rol</label>
          <select name="rol_id" class="form-select" required>
            <option value="">-- Selecciona un rol --</option>
            <?php foreach ($roles as $rol): ?>
              <option value="<?= (int)$rol['id'] ?>"
                <?= (isset($_POST['rol_id']) && (int)$_POST['rol_id'] === (int)$rol['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($rol['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Se ocultan “Administrador” y “Visitante”.</div>
        </div>
      </div>

      <div class="mt-4">
        <button type="submit" class="btn btn-primary w-100">Guardar usuario</button>
      </div>
    </form>
  </div>
</div>

<script>
  const btn = document.getElementById('togglePass');
  const inp = document.getElementById('pass');
  btn?.addEventListener('click', () => {
    inp.type = (inp.type === 'password') ? 'text' : 'password';
    btn.classList.toggle('btn-outline-secondary');
    btn.classList.toggle('btn-outline-primary');
  });
</script>
</body>
</html>




