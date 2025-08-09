<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: modulos/login.php");
    exit;
}

// Validar ID del pedido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "❌ ID de pedido inválido.";
    exit;
}

$pedido_id = $_GET['id'];

// Procesar cambio de estado si se envió desde el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_estado'])) {
    $nuevo_estado = $_POST['nuevo_estado'];
    $estados_validos = ['Pendiente', 'Enviado', 'Entregado', 'Cancelado'];

    if (in_array($nuevo_estado, $estados_validos)) {
        $stmtUpdate = $conn->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
        $stmtUpdate->execute([$nuevo_estado, $pedido_id]);
        header("Location: ver_pedido.php?id=$pedido_id");
        exit;
    } else {
        $mensaje = "❌ Estado inválido.";
    }
}

// Obtener datos del pedido
$stmt = $conn->prepare("
    SELECT p.*, u.nombre AS cliente 
    FROM pedidos p
    JOIN usuarios u ON p.usuario_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    echo "❌ Pedido no encontrado.";
    exit;
}

// Obtener detalles del pedido
$stmtDetalle = $conn->prepare("
    SELECT d.*, pr.nombre 
    FROM pedido_detalles d
    JOIN productos pr ON d.producto_id = pr.id
    WHERE d.pedido_id = ?
");
$stmtDetalle->execute([$pedido_id]);
$detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle del Pedido - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📝 Resumen del Pedido #<?= $pedido['id'] ?></h2>
        <div>
            <a href="admin.php" class="btn btn-secondary me-2">← Volver al panel</a>
            <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
        </div>
    </div>

    <p><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente']) ?></p>
    <p><strong>Fecha:</strong> <?= $pedido['fecha_pedido'] ?></p>
    <p><strong>Estado actual:</strong> <?= $pedido['estado'] ?></p>

    <?php if (isset($mensaje)) echo "<div class='alert alert-danger'>$mensaje</div>"; ?>

    <h3>🧾 Productos del Pedido</h3>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-primary">
                <tr>
                    <th>Producto</th>
                    <th>Precio</th>
                    <th>Cantidad</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                foreach ($detalles as $item):
                    $subtotal = $item['precio_unitario'] * $item['cantidad'];
                    $total += $subtotal;
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['nombre']) ?></td>
                    <td><?= '$' . number_format($item['precio_unitario'], 0, ',', '.') ?></td>
                    <td><?= $item['cantidad'] ?></td>
                    <td><?= '$' . number_format($subtotal, 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h3>Total pagado: <?= '$' . number_format($total, 0, ',', '.') ?></h3>

    <h3>📌 Cambiar Estado del Pedido</h3>
    <form method="post" class="w-50">
        <div class="mb-3">
            <label for="nuevo_estado" class="form-label">Seleccionar nuevo estado:</label>
            <select name="nuevo_estado" id="nuevo_estado" class="form-select" required>
                <?php
                $estados = ['Pendiente', 'Enviado', 'Entregado', 'Cancelado'];
                foreach ($estados as $estado):
                ?>
                    <option value="<?= $estado ?>" <?= $estado == $pedido['estado'] ? 'selected' : '' ?>>
                        <?= $estado ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-warning">Actualizar Estado</button>
    </form>
</div>

</body>
</html>

