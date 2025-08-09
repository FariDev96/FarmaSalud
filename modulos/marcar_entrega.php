<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Asegurarse que el usuario es admin (1) o distribuidor (3 o 6, depende cómo definiste)
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol_id'], [1, 3, 6])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $venta_id = $_POST['venta_id'] ?? null;
    $nuevo_estado = $_POST['estado_entrega'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '');

    $permitidos = ['En camino', 'Entregado'];

    if ($venta_id && in_array($nuevo_estado, $permitidos)) {
        try {
            $stmt = $conn->prepare("UPDATE ventas SET estado_entrega = ?, observaciones = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $observaciones, $venta_id]);

            $_SESSION['mensaje'] = "✅ Entrega actualizada correctamente.";
        } catch (PDOException $e) {
            $_SESSION['mensaje'] = "❌ Error al actualizar entrega: " . $e->getMessage();
        }
    } else {
        $_SESSION['mensaje'] = "❌ Datos inválidos para la entrega.";
    }

    // Redirigir de vuelta al panel de entregas
    header("Location: ver_ventas.php");
    exit;
}
?>


