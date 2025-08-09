<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: login.php");
    exit;
}

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $contrasena = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);
    $rol_id = $_POST['rol_id'];

    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, correo, contrasena, rol_id, fecha_registro) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$nombre, $correo, $contrasena, $rol_id]);
    
    $mensaje = "✅ Usuario agregado correctamente.";
}

$roles = $conn->query("SELECT id, nombre FROM roles")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Usuario - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <span class="navbar-brand">FarmaSalud | Admin</span>
        <a href="gestionar_usuarios.php" class="btn btn-sm btn-light ms-auto">← Volver</a>
    </div>
</nav>

<div class="container mt-5">
    <div class="card shadow p-4">
        <h2 class="mb-4">➕ Agregar Usuario</h2>

        <?php if ($mensaje): ?>
            <div class="alert alert-success"><?= $mensaje ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nombre:</label>
                <input type="text" name="nombre" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Correo:</label>
                <input type="email" name="correo" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Contraseña:</label>
                <input type="password" name="contrasena" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Rol:</label>
                <select name="rol_id" class="form-select" required>
                    <option value="">-- Selecciona un rol --</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100">Guardar usuario</button>
        </form>
    </div>
</div>

</body>
</html>


