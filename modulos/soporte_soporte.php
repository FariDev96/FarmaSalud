<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 3) {
    header("Location: modulos/login.php");
    exit;
}

// Cambiar estado del mensaje
if (isset($_GET['cambiar_estado'], $_GET['id'])) {
    $nuevo = $_GET['cambiar_estado'] === 'Pendiente' ? 'Resuelto' : 'Pendiente';
    $stmt = $conn->prepare("UPDATE mensajes_soporte SET estado = ? WHERE id = ?");
    $stmt->execute([$nuevo, $_GET['id']]);
    header("Location: soporte_soporte.php");
    exit;
}

// Obtener mensajes
$stmt = $conn->query("
    SELECT m.id, m.asunto, m.mensaje, m.estado, m.fecha, u.nombre AS remitente
    FROM mensajes_soporte m
    JOIN usuarios u ON m.usuario_id = u.id
    ORDER BY m.fecha DESC
");
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Soporte</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function confirmarCambio(nombre, estado) {
            return confirm(`¿Marcar el mensaje de ${nombre} como ${estado}?`);
        }
    </script>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🎧 Panel de Soporte Técnico</h2>
        <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
    </div>

    <?php if (empty($mensajes)): ?>
        <div class="alert alert-info">No hay mensajes de soporte registrados.</div>
    <?php else: ?>
        <div class="table-responsive shadow bg-white rounded">
            <table class="table table-hover align-middle">
                <thead class="table-success">
                    <tr>
                        <th>Remitente</th>
                        <th>Asunto</th>
                        <th>Mensaje</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mensajes as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['remitente']) ?></td>
                            <td><?= htmlspecialchars($m['asunto']) ?></td>
                            <td><?= nl2br(htmlspecialchars($m['mensaje'])) ?></td>
                            <td><?= $m['fecha'] ?></td>
                            <td>
                                <span class="badge bg-<?= $m['estado'] === 'Pendiente' ? 'warning' : 'success' ?>">
                                    <?= $m['estado'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="soporte_soporte.php?cambiar_estado=<?= $m['estado'] ?>&id=<?= $m['id'] ?>"
                                   onclick="return confirmarCambio('<?= htmlspecialchars($m['remitente']) ?>', '<?= $m['estado'] === 'Pendiente' ? 'Resuelto' : 'Pendiente' ?>')"
                                   class="btn btn-outline-primary btn-sm">
                                    Marcar como <?= $m['estado'] === 'Pendiente' ? 'Resuelto' : 'Pendiente' ?>
                                </a>
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

