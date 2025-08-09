<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 4) {
    header("Location: login.php");
    exit;
}

$hoy = date('Y-m-d');
$limite = date('Y-m-d', strtotime('+30 days'));

$sql = "SELECT id, nombre, descripcion, stock, fecha_caducidad
        FROM productos
        WHERE fecha_caducidad IS NOT NULL
          AND fecha_caducidad BETWEEN ? AND ?
        ORDER BY fecha_caducidad ASC";

$stmt = $conn->prepare($sql);
$stmt->execute([$hoy, $limite]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alertas de Caducidad - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-danger">
    <div class="container-fluid">
        <span class="navbar-brand">FarmaSalud | Farmacéutico</span>
        <div class="ms-auto">
            <a href="farmaceutico.php" class="btn btn-sm btn-light">← Volver</a>
            <a href="logout.php" class="btn btn-sm btn-outline-light ms-2">Cerrar sesión</a>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <h2 class="mb-4 text-center">⚠️ Productos Próximos a Caducar</h2>

    <?php if (empty($productos)): ?>
        <div class="alert alert-success text-center">✅ No hay productos próximos a caducar.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-warning">
                    <tr>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Stock</th>
                        <th>Fecha de Caducidad</th>
                        <th>Días Restantes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><?= htmlspecialchars($p['descripcion']) ?></td>
                            <td><?= $p['stock'] ?></td>
                            <td><?= $p['fecha_caducidad'] ?></td>
                            <td>
                                <?php
                                    $dias = (strtotime($p['fecha_caducidad']) - strtotime($hoy)) / 86400;
                                    echo round($dias) . ' días';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

