<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$stmt = $conn->query("SELECT id, nombre, descripcion, precio, stock, fecha_caducidad, imagen FROM productos ORDER BY nombre ASC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$usuario = $_SESSION['nombre'] ?? null;
$rol = $_SESSION['rol_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FarmaSalud - Inicio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background-color: #0d6efd;
        }
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        .producto-img {
            height: 150px;
            object-fit: contain;
            transition: 0.3s ease;
            background-color: #f8f8f8;
            padding: 10px;
        }
        .producto-img:hover {
            filter: brightness(0.85);
        }
        .card {
            border-radius: 12px;
        }
        .btn-outline-secondary {
            border-color: #0d6efd;
            color: #0d6efd;
        }
        .btn-outline-secondary:hover {
            background-color: #0d6efd;
            color: white;
        }
        .modal-header {
            background-color: #0d6efd;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark px-4">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
        <img src="../imagenes/Logo Farmasalud.png" alt="FarmaSalud Logo">
        <span class="fw-bold">FarmaSalud</span>
    </a>
    <div class="ms-auto d-flex align-items-center">
        <?php if ($usuario): ?>
            <span class="text-white me-3">Hola, <?= htmlspecialchars($usuario) ?></span>
            <?php if ($rol == 2): ?>
                <a href="modulos/cliente.php" class="btn btn-light btn-sm me-2">Mi cuenta</a>
            <?php endif; ?>
            <a href="modulos/logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
        <?php else: ?>
            <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#loginModal">Iniciar sesión</button>
        <?php endif; ?>
    </div>
</nav>

<!-- CONTENIDO -->
<div class="container mt-5">
    <h2 class="mb-4 text-primary">🛍 Productos disponibles</h2>

    <?php if (empty($productos)): ?>
        <div class="alert alert-warning">No hay productos por mostrar.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($productos as $p): ?>
                <?php
                    $img = (!empty($p['imagen']) && file_exists(__DIR__ . '/../imagenes/' . $p['imagen']))
                        ? '../imagenes/' . $p['imagen']
                        : '../imagenes/generico.png';
                ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <img src="<?= $img ?>" class="card-img-top producto-img" alt="Producto">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($p['nombre']) ?></h5>
                            <p class="card-text"><?= htmlspecialchars($p['descripcion']) ?></p>
                            <p><strong>$<?= number_format($p['precio'], 0, ',', '.') ?></strong></p>
                            <p class="text-muted">Stock: <?= $p['stock'] ?> | Caduca: <?= $p['fecha_caducidad'] ?></p>
                            <?php if ($usuario && $rol == 2): ?>
                                <a href="modulos/carrito.php?agregar=<?= $p['id'] ?>" class="btn btn-primary mt-auto">🛒 Agregar</a>
                            <?php else: ?>
                                <button type="button" class="btn btn-outline-secondary mt-auto abrirModalLogin">🛒 Inicia sesión para comprar</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL LOGIN -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow">
      <div class="modal-header text-white">
        <h5 class="modal-title" id="loginModalLabel">👤 Accede a tu cuenta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center">
        <p>¿Ya tienes una cuenta?</p>
        <a href="login.php" class="btn btn-primary w-100 mb-3">🔑 Iniciar Sesión</a>
        <p>¿Eres nuevo aquí?</p>
        <a href="registro.php" class="btn btn-outline-primary w-100">📝 Registrarse</a>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mostrar modal al hacer clic en "Inicia sesión para comprar"
    document.querySelectorAll('.abrirModalLogin').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = new bootstrap.Modal(document.getElementById('loginModal'));
            modal.show();
        });
    });
</script>
</body>
</html>




