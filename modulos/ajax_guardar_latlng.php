<?php
// Guarda lat/lng en direcciones (POST: id, lat, lng)
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array((int)($_SESSION['rol_id'] ?? 0), [1,4,6])) {
  http_response_code(403); exit('No autorizado');
}
require_once __DIR__ . '/../config/db.php';

$id  = (int)($_POST['id']  ?? 0);
$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;

if ($id <= 0 || $lat === null || $lng === null) { http_response_code(400); exit('Parámetros inválidos'); }

try {
  $stmt = $conn->prepare("UPDATE direcciones SET lat=?, lng=? WHERE id=?");
  $stmt->execute([$lat, $lng, $id]);
  header('Content-Type: application/json'); echo json_encode(['ok'=>1]);
} catch (Throwable $e) {
  http_response_code(500); echo 'Error: '.$e->getMessage();
}
