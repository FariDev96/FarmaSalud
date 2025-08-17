<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit;
}

if (empty($_SESSION['carrito']) || !is_array($_SESSION['carrito'])) {
  echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Confirmar Pedido</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
  <body class="bg-light"><div class="container py-5">
  <div class="alert alert-info">Tu carrito está vacío.</div>
  <a href="productos.php" class="btn btn-primary">Ir al catálogo</a>
  </div></body></html>';
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$mensaje = '';
$resumen = [];
$total   = 0;
$pedido_id = null;

try {
  // Aísla toda la operación
  $conn->beginTransaction();

  $usuario_id = (int)$_SESSION['usuario_id'];

  // Crea el pedido (controla columnas opcionales según tu schema)
  $stmt = $conn->prepare("INSERT INTO pedidos (usuario_id, fecha_pedido, estado) VALUES (?, NOW(), 'pendiente')");
  $stmt->execute([$usuario_id]);
  $pedido_id = (int)$conn->lastInsertId();

  // Preparados
  $qProducto = $conn->prepare("SELECT id, nombre, precio, stock FROM productos WHERE id = ? FOR UPDATE");
  $insDet    = $conn->prepare("
      INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario)
      VALUES (?, ?, ?, ?)
  ");
  $updStock  = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");

  foreach ($_SESSION['carrito'] as $item) {
    $prodId   = (int)($item['id'] ?? 0);
    $cantidad = max(1, (int)($item['cantidad'] ?? 0));
    if ($prodId <= 0 || $cantidad <= 0) throw new RuntimeException('Ítem inválido en el carrito.');

    // Bloquea fila y toma datos reales
    $qProducto->execute([$prodId]);
    $p = $qProducto->fetch(PDO::FETCH_ASSOC);
    if (!$p) throw new RuntimeException('Producto no encontrado (ID '.$prodId.').');

    // Verifica stock
    if ((int)$p['stock'] < $cantidad) {
      throw new RuntimeException('Stock insuficiente para "'.$p['nombre'].'". Disponible: '.$p['stock']);
    }

    $precioUnit = (float)$p['precio']; // precio confiable (BD)
    $insDet->execute([$pedido_id, $prodId, $cantidad, $precioUnit]);
    $updStock->execute([$cantidad, $prodId]);

    $subtotal = $precioUnit * $cantidad;
    $total   += $subtotal;

    $resumen[] = [
      'nombre'   => $p['nombre'],
      'precio'   => $precioUnit,
      'cantidad' => $cantidad,
      'subtotal' => $subtotal
    ];
  }

  // (Opcional) Si tu tabla pedidos tiene columna total, actualízala sin romper si no existe
  try {
    $updTotal = $conn->prepare("UPDATE pedidos SET total = ? WHERE id = ?");
    $updTotal->execute([$total, $pedido_id]);
  } catch (Throwable $e) {
    // Ignora si no existe la columna 'total'
  }

  // Registra venta en 'ventas' si existe la tabla (para Finanzas)
  try {
    $conn->prepare("INSERT INTO ventas (pedido_id, total, fecha_venta) VALUES (?, ?, NOW())")
         ->execute([$pedido_id, $total]);
  } catch (Throwable $e) {
    // Si no existe la tabla de ventas, seguimos sin romper
  }

  $conn->commit();
  $_SESSION['carrito'] = [];
  $mensaje = "✅ Pedido #{$pedido_id} confirmado exitosamente.";

} catch (Throwable $e) {
  if ($conn->inTransaction()) $conn->rollBack();
  $mensaje = "❌ Error al confirmar el pedido: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Confirmar Pedido - FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{ background:#f6f9fc; }
    .card-fs{ background:#fff; border:1px solid #e8edf3; border-radius:16px; box-shadow:0 12px 28px rgba(2,6,23,.06); }
  </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark" style="background:#2563eb">
  <div class="container-fluid">
    <span class="navbar-brand">FarmaSalud | Confirmación de Pedido</span>
    <div class="ms-auto d-flex gap-2">
      <a href="productos.php" class="btn btn-sm btn-light">← Volver al catálogo</a>
    </div>
  </div>
</nav>

<div class="container py-4">
  <?php if (!empty($mensaje)): ?>
    <div class="alert <?= str_contains($mensaje, '✅') ? 'alert-success' : 'alert-danger' ?>">
      <?= h($mensaje) ?>
    </div>
  <?php endif; ?>

  <?php if ($pedido_id && $resumen): ?>
    <div class="card-fs p-3 mb-3">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0">🧾 Resumen del pedido #<?= (int)$pedido_id ?></h5>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">Imprimir</button>
      </div>
    </div>

    <div class="table-responsive card-fs p-2">
      <table class="table table-bordered table-hover align-middle mb-0">
        <thead class="table-light">
        <tr>
          <th>Producto</th>
          <th class="text-end">Precio</th>
          <th class="text-center">Cantidad</th>
          <th class="text-end">Subtotal</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($resumen as $item): ?>
          <tr>
            <td><?= h($item['nombre']) ?></td>
            <td class="text-end">$<?= number_format($item['precio'], 0, ',', '.') ?></td>
            <td class="text-center"><?= (int)$item['cantidad'] ?></td>
            <td class="text-end">$<?= number_format($item['subtotal'], 0, ',', '.') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="text-end mt-3">
      <h4 class="text-primary">Total pagado: $<?= number_format($total, 0, ',', '.') ?></h4>
    </div>

    <div class="d-flex gap-2 mt-3">
      <a class="btn btn-outline-primary" href="ver_pedido.php?id=<?= (int)$pedido_id ?>">
        Ver pedido
      </a>
      <a class="btn btn-primary" href="productos.php">
        Seguir comprando
      </a>
    </div>
  <?php endif; ?>
</div>

</body>
</html>



