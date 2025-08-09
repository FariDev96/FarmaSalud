<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? null;

if ($id) {
    try {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Si hay clave foránea, muestra el error (puedes cambiar esto por un mensaje visual)
        die("❌ No se puede eliminar el usuario: " . $e->getMessage());
    }
}

header("Location: gestionar_usuarios.php");
exit;
