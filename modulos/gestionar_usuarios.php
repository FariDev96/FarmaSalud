<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: login.php");
    exit;
}

$filtro_rol = $_GET['rol'] ?? '';

$roles = $conn->query("SELECT id, nombre FROM roles")->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON u.rol_id = r.id";
$params = [];

if ($filtro_rol !== '') {
    $sql .= " WHERE u.rol_id = ?";
    $params[] = $filtro_rol;
}

$sql .= " ORDER BY u.fecha_registro DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>👥 Gestión de Usuarios</h2>
        <div>
            <a href="admin.php" class="btn btn-secondary me-2">← Volver</a>
            <a href="agregar_usuario.php" class="btn btn-success">➕ Nuevo Usuario</a>
        </div>
    </div>

    <form method="GET" class="mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <label for="rol" class="col-form-label">Filtrar por rol:</label>
            </div>
            <div class="col-auto">
                <select name="rol" id="rol" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Todos --</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?= $rol['id'] ?>" <?= $filtro_rol == $rol['id'] ? 'selected' : '' ?>>
                            <?= $rol['nombre'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <div class="table-responsive shadow bg-white p-3 rounded">
        <table class="table table-striped align-middle">
            <thead class="table-primary">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Registro</th>
                    <th>Último Acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?= $usuario['id'] ?></td>
                        <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                        <td><?= htmlspecialchars($usuario['correo']) ?></td>
                        <td><?= $usuario['rol'] ?></td>
                        <td><?= $usuario['fecha_registro'] ?></td>
                        <td><?= $usuario['ultimo_acceso'] ?? 'Nunca' ?></td>
                        <td>
                            <a href="editar_usuario.php?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                            <a href="eliminar_usuario.php?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de eliminar este usuario?')">🗑 Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>


