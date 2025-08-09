<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: login.php");
    exit;
}

// Búsqueda
$busqueda = $_GET['buscar'] ?? '';
$estado = $_GET['estado'] ?? '';

// Cambiar estado
if (isset($_GET['cambiar_estado'], $_GET['id'])) {
    $nuevo = $_GET['cambiar_estado'] === 'Pendiente' ? 'Resuelto' : 'Pendiente';
    $stmt = $conn->prepare("UPDATE mensajes_soporte SET estado = ? WHERE id = ?");
    $stmt->execute([$nuevo, $_GET['id']]);
    header("Location: soporte_admin.php");
    exit;
}

// Consulta principal
$sql = "SELECT m.id, m.asunto, m.mensaje, m.estado, m.fecha, u.nombre AS remitente 
        FROM mensajes_soporte m 
        JOIN usuarios u ON m.usuario_id = u.id";
$condiciones = [];
$params = [];

if ($estado) {
    $condiciones[] = "m.estado = ?";
    $params[] = $estado;
}
if ($busqueda) {
    $condiciones[] = "(m.asunto LIKE ? OR m.mensaje LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
if ($condiciones) {
    $sql .= " WHERE " . implode(" AND ", $condiciones);
}
$sql .= " ORDER BY m.fecha DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📨 Soporte - FarmaSalud</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function confirmarCambio(nombre, nuevoEstado) {
            return confirm(`¿Deseas marcar el mensaje de ${nombre} como ${nuevoEstado}?`);
        }
    </script>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📨 Mensajes de Soporte</h2>
        <div>
            <a href="admin.php" class="btn btn-secondary me-2">← Volver</a>
            <a href="logout.php" class="btn btn-danger">Cerrar sesión</a>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" class="row g-3 align-items-center mb-4">
        <div class="col-md-3">
            <label for="estado" class="form-label">Filtrar por estado:</label>
            <select name="estado" id="estado" class="form-select" onchange="this.form.submit()">
                <option value="">-- Todos --</option>
                <option value="Pendiente" <?= $estado == 'Pendiente' ? 'selected' : '' ?>>Pendientes</option>
                <option value="Resuelto" <?= $estado == 'Resuelto' ? 'selected' : '' ?>>Resueltos</option>
            </select>
        </div>
        <div class="col-md-6">
            <label for="buscar" class="form-label">Buscar:</label>
            <input type="text" name="buscar" id="buscar" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por asunto o mensaje">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">🔍 Buscar</button>
        </div>
    </form>

    <?php if (empty($mensajes)): ?>
        <div class="alert alert-info">No hay mensajes encontrados.</div>
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
                                <a href="soporte_admin.php?cambiar_estado=<?= $m['estado'] ?>&id=<?= $m['id'] ?>"
                                   onclick="return confirmarCambio('<?= htmlspecialchars($m['remitente']) ?>', '<?= $m['estado'] === 'Pendiente' ? 'Resuelto' : 'Pendiente' ?>')"
                                   class="btn btn-outline-primary btn-sm">
                                   Cambiar a <?= $m['estado'] === 'Pendiente' ? 'Resuelto' : 'Pendiente' ?>
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


