<?php
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 2) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Agregar producto
if (isset($_GET['agregar'])) {
    $producto_id = $_GET['agregar'];
    $stmt = $conn->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($producto) {
        $encontrado = false;
        foreach ($_SESSION['carrito'] as &$item) {
            if ($item['id'] == $producto_id) {
                $item['cantidad']++;
                $encontrado = true;
                break;
            }
        }
        if (!$encontrado) {
            $producto['cantidad'] = 1;
            $_SESSION['carrito'][] = $producto;
        }
    }
}

// Quitar unidad
if (isset($_GET['quitar'])) {
    $id = $_GET['quitar'];
    foreach ($_SESSION['carrito'] as $i => &$item) {
        if ($item['id'] == $id) {
            $item['cantidad']--;
            if ($item['cantidad'] <= 0) {
                unset($_SESSION['carrito'][$i]);
                $_SESSION['carrito'] = array_values($_SESSION['carrito']);
            }
            break;
        }
    }
}

// Eliminar producto
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    foreach ($_SESSION['carrito'] as $i => $item) {
        if ($item['id'] == $id) {
            unset($_SESSION['carrito'][$i]);
            $_SESSION['carrito'] = array_values($_SESSION['carrito']);
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- NAV -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <span class="navbar-brand">🛒 Carrito | FarmaSalud</span>
        <div class="ms-auto">
            <a href="productos.php" class="btn btn-sm btn-light">← Volver</a>
            <a href="logout.php" class="btn btn-sm btn-danger ms-2">Cerrar sesión</a>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <h2 class="mb-4">🧺 Productos en tu carrito</h2>

    <?php if (empty($_SESSION['carrito'])): ?>
        <div class="alert alert-info">No hay productos en el carrito.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-success">
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $total = 0;
                    foreach ($_SESSION['carrito'] as $item):
                        $subtotal = $item['precio'] * $item['cantidad'];
                        $total += $subtotal;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($item['nombre']) ?></td>
                        <td>$<?= number_format($item['precio'], 0, ',', '.') ?></td>
                        <td>
                            <a href="carrito.php?quitar=<?= $item['id'] ?>" class="btn btn-sm btn-outline-danger">➖</a>
                            <span class="mx-2"><?= $item['cantidad'] ?></span>
                            <a href="carrito.php?agregar=<?= $item['id'] ?>" class="btn btn-sm btn-outline-success">➕</a>
                        </td>
                        <td>$<?= number_format($subtotal, 0, ',', '.') ?></td>
                        <td>
                            <a href="carrito.php?eliminar=<?= $item['id'] ?>" class="btn btn-sm btn-outline-secondary">❌ Quitar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-end mt-4">
            <h4>Total: <span class="text-primary">$<?= number_format($total, 0, ',', '.') ?></span></h4>
            <a href="confirmar_pedido.php" class="btn btn-success btn-lg mt-2">✅ Confirmar Pedido</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

