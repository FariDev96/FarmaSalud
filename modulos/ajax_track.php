<?php
session_start();
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 6) {
  http_response_code(403); exit('No autorizado');
}
require_once __DIR__ . '/../config/db.php';

$venta_id = (int)($_POST['venta_id'] ?? 0);
$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
$vel = isset($_POST['vel']) ? (float)$_POST['vel'] : null;
$rum = isset($_POST['rum']) ? (float)$_POST['rum'] : null;
$pre = isset($_POST['pre']) ? (float)$_POST['pre'] : null;

if ($venta_id<=0 || $lat===null || $lng===null) { http_response_code(400); exit('Bad request'); }

try{
  $st = $conn->prepare("INSERT INTO entregas_tracking (venta_id, repartidor_id, lat, lng, velocidad, rumbo, precision_m)
                        VALUES (?,?,?,?,?,?,?)");
  $st->execute([$venta_id, (int)$_SESSION['usuario_id'], $lat, $lng, $vel, $rum, $pre]);
  header('Content-Type: application/json'); echo json_encode(['ok'=>1]);
}catch(Throwable $e){ http_response_code(500); echo 'Error: '.$e->getMessage(); }
