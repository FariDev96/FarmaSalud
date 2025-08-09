<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: gestionar_usuarios.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("Usuario no encontrado.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $rol_id = $_POST['rol_id'];

    $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, correo = ?, rol_id = ? WHERE id = ?");
    $stmt->execute([$nombre, $correo, $rol_id, $id]);

    header("Location: gestionar_usuarios.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>✏️ Editar Usuario</h2>
        <a href="gestionar_usuarios.php" class="btn btn-secondary">← Volver</a>
    </div>

    <form method="POST" class="bg-white shadow p-4 rounded">
        <div class="mb-3">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Correo electrónico</label>
            <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Rol asignado</label>
            <select name="rol_id" class="form-select" required>
                <?php
                $roles = $conn->query("SELECT id, nombre FROM roles")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($roles as $rol) {
                    $selected = $usuario['rol_id'] == $rol['id'] ? 'selected' : '';
                    echo "<option value='{$rol['id']}' $selected>{$rol['nombre']}</option>";
                }
                ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">💾 Guardar cambios</button>
    </form>
</div>

</body>
</html>

