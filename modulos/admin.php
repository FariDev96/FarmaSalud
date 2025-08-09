<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: login.php");
    exit;
}

// Obtener pedidos
$sql = "SELECT p.id, u.nombre AS cliente, p.fecha_pedido, p.estado
        FROM pedidos p
        JOIN usuarios u ON p.usuario_id = u.id
        ORDER BY p.fecha_pedido DESC";
$stmt = $conn->query($sql);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nombre = $_SESSION['nombre'] ?? 'Administrador';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container-fluid">
    <span class="navbar-brand">FarmaSalud | Admin</span>
    <div class="text-white">
        Bienvenido, <?= htmlspecialchars($nombre) ?> |
        <a href="logout.php" class="btn btn-sm btn-light ms-2">🔒 Cerrar sesión</a>
    </div>
  </div>
</nav>

<!-- CONTENIDO -->
<div class="container mt-4">

    <h2 class="mb-4">📋 Pedidos Recibidos</h2>

    <!-- ENLACES RÁPIDOS -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="gestionar_productos.php" class="btn btn-outline-primary">📦 Gestionar productos</a>
        <a href="gestionar_usuarios.php" class="btn btn-outline-primary">👥 Gestionar usuarios</a>
        <a href="reportes.php" class="btn btn-outline-primary">📊 Ver reportes</a>
        <a href="soporte_admin.php" class="btn btn-outline-primary">📨 Ver soporte</a>
        <a href="reportar_danado.php" class="btn btn-outline-primary">🧾 Reportar producto dañado</a>
        <a href="ver_danados.php" class="btn btn-outline-primary">📜 Ver productos dañados</a>
        <a href="inventario_filtro.php" class="btn btn-outline-primary">🔍 Inventario con filtros</a>
        <a href="registrar_venta.php" class="btn btn-outline-primary">💸 Registrar venta</a>
    </div>

    <!-- TABLA DE PEDIDOS -->
    <?php if (empty($pedidos)): ?>
        <div class="alert alert-warning">No hay pedidos registrados.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td><?= $pedido['id'] ?></td>
                            <td><?= htmlspecialchars($pedido['cliente']) ?></td>
                            <td><?= $pedido['fecha_pedido'] ?></td>
                            <td><?= $pedido['estado'] ?></td>
                            <td>
                                <a href="ver_pedido.php?id=<?= $pedido['id'] ?>" class="btn btn-sm btn-info">👁 Ver</a>
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




