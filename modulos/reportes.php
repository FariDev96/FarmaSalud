<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: login.php");
    exit;
}

// Consultas de reportes
$estadisticas = $conn->query("
    SELECT estado, COUNT(*) AS cantidad
    FROM pedidos
    GROUP BY estado
")->fetchAll(PDO::FETCH_ASSOC);

$totalProductos = $conn->query("SELECT COUNT(*) FROM productos")->fetchColumn();
$stockBajo = $conn->query("SELECT COUNT(*) FROM productos WHERE stock < 10")->fetchColumn();

$usuariosPorRol = $conn->query("
    SELECT r.nombre AS rol, COUNT(u.id) AS cantidad
    FROM usuarios u
    JOIN roles r ON u.rol_id = r.id
    GROUP BY r.nombre
")->fetchAll(PDO::FETCH_ASSOC);

$pedidosMes = $conn->query("
    SELECT DATE_FORMAT(fecha_pedido, '%Y-%m') AS mes, COUNT(*) AS cantidad
    FROM pedidos
    GROUP BY mes
    ORDER BY mes DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📊 Reportes - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📊 Reportes Generales</h2>
        <div>
            <a href="admin.php" class="btn btn-secondary me-2">← Volver al panel</a>
            <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Pedidos por estado -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">📦 Pedidos por Estado</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($estadisticas as $e): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <?= $e['estado'] ?>
                            <strong><?= $e['cantidad'] ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Productos -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-success text-white">🏷 Productos</div>
                <div class="card-body">
                    <p>Total de productos: <strong><?= $totalProductos ?></strong></p>
                    <p>Stock bajo (&lt; 10): <strong class="text-danger"><?= $stockBajo ?></strong></p>
                </div>
            </div>
        </div>

        <!-- Usuarios por rol -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-info text-white">👤 Usuarios por Rol</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($usuariosPorRol as $r): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <?= $r['rol'] ?>
                            <strong><?= $r['cantidad'] ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Pedidos por mes -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-warning text-dark">📆 Pedidos por Mes</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($pedidosMes as $p): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <?= $p['mes'] ?>
                            <strong><?= $p['cantidad'] ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

</body>
</html>


