<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 4, 6])) {
    header("Location: login.php");
    exit;
}

// Obtener ventas
$sql = "SELECT v.id, v.fecha, u1.nombre AS registrado_por, u2.nombre AS cliente,
               p.nombre AS producto, v.cantidad, v.precio_unitario, v.estado_entrega, v.observaciones
        FROM ventas v
        JOIN usuarios u1 ON v.usuario_id = u1.id
        JOIN usuarios u2 ON v.cliente_id = u2.id
        JOIN productos p ON v.producto_id = p.id
        ORDER BY v.fecha DESC";

$ventas = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seguimiento de Entregas - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🚚 Seguimiento de Entregas</h2>
        <div>
            <a href="<?= $_SESSION['rol_id'] == 6 ? 'distribuidor.php' : 'farmaceutico.php' ?>" class="btn btn-secondary me-2">← Volver al panel</a>
            <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['mensaje'])): ?>
        <div class="alert alert-success"><?= $_SESSION['mensaje'] ?></div>
        <?php unset($_SESSION['mensaje']); ?>
    <?php endif; ?>

    <?php if (empty($ventas)): ?>
        <div class="alert alert-info">No hay ventas registradas.</div>
    <?php else: ?>
        <div class="table-responsive shadow bg-white rounded">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-primary">
                    <tr>
                        <th>Fecha</th>
                        <th>Registrado por</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Observaciones</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ventas as $v): ?>
                        <tr>
                            <td><?= $v['fecha'] ?></td>
                            <td><?= htmlspecialchars($v['registrado_por']) ?></td>
                            <td><?= htmlspecialchars($v['cliente']) ?></td>
                            <td><?= htmlspecialchars($v['producto']) ?></td>
                            <td><?= $v['cantidad'] ?></td>
                            <td>$<?= number_format($v['precio_unitario'] * $v['cantidad'], 0, ',', '.') ?></td>
                            <td>
                                <span class="badge bg-<?= $v['estado_entrega'] == 'Enviado' ? 'warning' : 'success' ?>">
                                    <?= $v['estado_entrega'] ?>
                                </span>
                            </td>
                            <td><?= nl2br(htmlspecialchars($v['observaciones'])) ?></td>
                            <td>
                                <?php if (in_array($_SESSION['rol_id'], [1, 4])): ?>
                                    <?php foreach (['En camino', 'Entregado'] as $estado): ?>
                                        <?php if ($estado !== $v['estado_entrega']): ?>
                                            <a href="ver_ventas.php?id=<?= $v['id'] ?>&cambiar_estado=<?= $estado ?>" class="btn btn-sm btn-outline-primary">
                                                Cambiar a <?= $estado ?>
                                            </a><br>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php elseif ($_SESSION['rol_id'] == 6): ?>
                                    <form method="POST" action="marcar_entrega.php" class="d-inline-block">
                                        <input type="hidden" name="venta_id" value="<?= $v['id'] ?>">
                                        <select name="estado_entrega" class="form-select form-select-sm" required>
                                            <option value="">-- Estado --</option>
                                            <option value="En camino">En camino</option>
                                            <option value="Entregado">Entregado</option>
                                        </select><br>
                                        <textarea name="observaciones" rows="2" class="form-control form-control-sm" placeholder="Observaciones..."></textarea><br>
                                        <button type="submit" class="btn btn-sm btn-success">Actualizar</button>
                                    </form>
                                <?php endif; ?>
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



