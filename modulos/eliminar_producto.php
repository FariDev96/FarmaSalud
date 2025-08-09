<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol_id'] != 1 || !isset($_GET['id'])) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'];

// Eliminar producto
$stmt = $conn->prepare("DELETE FROM productos WHERE id = ?");
$stmt->execute([$id]);

header("Location: gestionar_productos.php");
exit;
