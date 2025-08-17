<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: gestionar_usuarios.php");
    exit;
}

// Cargar usuario
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$usuario) {
    die("Usuario no encontrado.");
}

// Solo roles permitidos en edición (sin visitante ni admin)
$rolesPermitidos = [2, 4]; // 2=Cliente, 4=Farmacéutico (ajusta si difieren en tu BD)

// Intentar traer nombres de roles permitidos
$roles = [];
try {
    $in  = implode(',', array_fill(0, count($rolesPermitidos), '?'));
    $q   = $conn->prepare("SELECT id, nombre FROM roles WHERE id IN ($in) ORDER BY nombre");
    $q->execute($rolesPermitidos);
    $roles = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // Fallback básico por si la tabla roles no existe:
    $roles = [
        ['id' => 2, 'nombre' => 'Cliente'],
        ['id' => 4, 'nombre' => 'Farmacéutico'],
    ];
}

$errores = [];
$ok = false;

// Post: actualizar
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $rol_id = isset($_POST['rol_id']) ? (int)$_POST['rol_id'] : (int)$usuario['rol_id'];
    $nuevaPassword = trim($_POST['nueva_password'] ?? '');

    // Validaciones básicas
    if ($nombre === '') $errores[] = "El nombre es obligatorio.";
    if ($correo === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) $errores[] = "Correo inválido.";
    // Si el usuario editado NO es admin, solo se aceptan roles permitidos
    $esAdminEditado = ((int)$usuario['rol_id'] === 1);
    if (!$esAdminEditado && !in_array($rol_id, $rolesPermitidos, true)) {
        $errores[] = "Rol no permitido.";
    }

    // Correo único
    try {
        $u = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = ? AND id <> ?");
        $u->execute([$correo, $id]);
        if ((int)$u->fetchColumn() > 0) {
            $errores[] = "Ese correo ya está registrado.";
        }
    } catch (Throwable $e) {
        // si falla, no bloqueamos, pero es raro
    }

    // No permitir dejar sin admins: si intentaran quitar rol admin al último admin
    if ($esAdminEditado && $rol_id !== 1) {
        try {
            $c = $conn->query("SELECT COUNT(*) FROM usuarios WHERE rol_id = 1")->fetchColumn();
            if ((int)$c <= 1) {
                $errores[] = "No puedes quitar el rol de administrador al único administrador del sistema.";
            }
        } catch (Throwable $e) {
            // si no existe la tabla/columna, ignoramos este check
        }
    }

    if (!$errores) {
        // Construimos UPDATE dinámico
        $campos = ["nombre = ?", "correo = ?"];
        $params = [$nombre, $correo];

        // Rol: solo editable si el usuario NO es admin
        if (!$esAdminEditado) {
            $campos[] = "rol_id = ?";
            $params[] = $rol_id;
        }

        // Contraseña opcional
        if ($nuevaPassword !== '') {
            if (strlen($nuevaPassword) < 6) {
                $errores[] = "La nueva contraseña debe tener al menos 6 caracteres.";
            } else {
                $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
                $campos[] = "contrasena = ?";
                $params[] = $hash;
            }
        }

        if (!$errores) {
            $params[] = $id;
            $sql = "UPDATE usuarios SET " . implode(", ", $campos) . " WHERE id = ?";
            $up = $conn->prepare($sql);
            $up->execute($params);
            $ok = true;
            // Refrescar los datos visualizados
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Usuario — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --fs-border:#e8edf3; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    .card-fs{ background:#fff; border:1px solid var(--fs-border); border-radius:16px; box-shadow:var(--fs-shadow); }
  </style>
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">✏️ Editar Usuario</h2>
    <a href="gestionar_usuarios.php" class="btn btn-secondary">← Volver</a>
  </div>

  <?php if ($ok): ?>
    <div class="alert alert-success">Cambios guardados correctamente.</div>
  <?php endif; ?>

  <?php if ($errores): ?>
    <div class="alert alert-danger">
      <strong>Revisa lo siguiente:</strong>
      <ul class="mb-0">
        <?php foreach ($errores as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="POST" class="card-fs p-4">
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label">Nombre completo</label>
        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Rol</label>
        <?php if ((int)$usuario['rol_id'] === 1): ?>
          <!-- Si el usuario editado es ADMIN, no permitimos cambiar su rol desde aquí -->
          <input class="form-control" value="Administrador" disabled>
          <input type="hidden" name="rol_id" value="1">
          <div class="form-text text-danger">No se puede cambiar el rol de un administrador desde este formulario.</div>
        <?php else: ?>
          <select name="rol_id" class="form-select" required>
            <?php foreach ($roles as $rol): ?>
              <option value="<?= (int)$rol['id'] ?>" <?= ((int)$usuario['rol_id'] === (int)$rol['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($rol['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>

      <div class="col-12">
        <details class="mt-2">
          <summary class="mb-2 fw-semibold">Cambiar contraseña (opcional)</summary>
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Nueva contraseña</label>
              <input type="password" name="nueva_password" class="form-control" placeholder="Deja en blanco para no cambiar">
              <div class="form-text">Mínimo 6 caracteres.</div>
            </div>
          </div>
        </details>
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button type="submit" class="btn btn-primary">💾 Guardar cambios</button>
      <a href="gestionar_usuarios.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </form>
</div>
</body>
</html>


