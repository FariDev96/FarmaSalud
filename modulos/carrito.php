<?php
session_start();

// Verificar sesión y rol cliente
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 2) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Función para agregar producto al carrito
function agregarAlCarrito($producto_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM productos WHERE id = ?");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($producto) {
        $encontrado = false;
        foreach ($_SESSION['carrito'] as &$item) {
            if ($item['id'] == $producto_id) {
                if ($item['cantidad'] < $producto['stock']) {
                    $item['cantidad']++;
                }
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

// Función para quitar una unidad del carrito
function quitarDelCarrito($id) {
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

// Función para eliminar producto del carrito
function eliminarDelCarrito($id) {
    foreach ($_SESSION['carrito'] as $i => $item) {
        if ($item['id'] == $id) {
            unset($_SESSION['carrito'][$i]);
            $_SESSION['carrito'] = array_values($_SESSION['carrito']);
            break;
        }
    }
}

// Agregar producto al carrito
if (isset($_GET['agregar'])) {
    agregarAlCarrito($_GET['agregar']);
}

// Quitar una unidad
if (isset($_GET['quitar'])) {
    quitarDelCarrito($_GET['quitar']);
}

// Eliminar producto
if (isset($_GET['eliminar'])) {
    eliminarDelCarrito($_GET['eliminar']);
}

// Verificar si la información de dirección está completa
$usuarioId = $_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT direccion, ciudad, departamento FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $usuarioId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$direccionCompleta = !empty($userData['direccion']) && !empty($userData['ciudad']) && !empty($userData['departamento']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --fs-bg: #f6f9fc;
            --fs-surface: #fff;
            --fs-border: #e8edf3;
            --fs-text: #0f172a;
            --fs-dim: #64748b;
            --fs-primary: #2563eb;
            --fs-primary-600: #1d4ed8;
            --fs-accent: #10b981;
            --fs-radius: 16px;
            --fs-shadow: 0 12px 28px rgba(2, 6, 23, .06);
        }
        body {
            font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
            background: #f6f9fc;
            color: var(--fs-text);
            letter-spacing: .2px;
        }
        a { text-decoration: none; }

        /* NAV */
        .fs-navbar {
            background: var(--fs-primary);
            border-bottom: 1px solid var(--fs-border);
        }
        .fs-navbar .fs-brand {
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            font-weight: 700;
            color: var(--fs-text);
        }

        .btn-fs-primary {
            --bs-btn-bg: var(--fs-primary);
            --bs-btn-border-color: var(--fs-primary);
            --bs-btn-hover-bg: var(--fs-primary-600);
            --bs-btn-hover-border-color: var(--fs-primary-600);
            --bs-btn-color: #fff;
            font-weight: 600;
            border-radius: 12px;
            box-shadow: 0 8px 22px rgba(37, 99, 235, .25);
        }

        .btn-fs-ghost {
            background: #fff;
            color: var(--fs-text);
            border: 1px solid var(--fs-border);
            border-radius: 12px;
            font-weight: 600;
        }

        .btn-fs-ghost:hover {
            background: rgba(2, 6, 23, .04);
        }

        .table thead th {
            background-color: var(--fs-primary);
            color: #fff;
        }

        .table td, .table th {
            vertical-align: middle;
        }

        .fs-hero-card {
            background: var(--fs-surface);
            border-radius: 16px;
            box-shadow: var(--fs-shadow);
            padding: 1.5rem;
        }

        .fs-title {
            color: var(--fs-primary);
            font-size: 2rem;
        }

        .fs-lead {
            color: var(--fs-dim);
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav class="navbar navbar-expand-lg navbar-dark">
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

        <?php if (!$direccionCompleta): ?>
            <div class="alert alert-warning mt-4">
                <strong>Atención:</strong> Para confirmar tu pedido, debes completar tu dirección. <a href="perfil.php">Haz clic aquí para actualizar tu información.</a>
            </div>
        <?php else: ?>
            <div class="text-end mt-4">
                <h4>Total: <span class="text-primary">$<?= number_format($total, 0, ',', '.') ?></span></h4>

                <?php 
                    $whatsappMessage = "Pedido de FarmaSalud:\n";
                    foreach ($_SESSION['carrito'] as $item) {
                        $whatsappMessage .= "Producto: " . $item['nombre'] . " | Cantidad: " . $item['cantidad'] . " | Subtotal: $" . number_format($item['precio'] * $item['cantidad'], 0, ',', '.') . "\n";
                    }
                    $whatsappMessage .= "Total: $" . number_format($total, 0, ',', '.');
                    $whatsappLink = "https://wa.me/573170613008?text=" . urlencode($whatsappMessage);
                ?>

                <a href="<?= $whatsappLink ?>" target="_blank" class="btn btn-fs-primary btn-lg mt-2">✅ Confirmar Pedido y Enviar a WhatsApp</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>



