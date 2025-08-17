<?php
// export/export_inventario.php
require_once __DIR__.'/../config/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!in_array($_SESSION['rol_id'] ?? 0, [1,4])) { http_response_code(403); exit('No autorizado'); }

$pdo = db();
$q = $pdo->query("SELECT id, nombre, descripcion, precio, stock, fecha_caducidad FROM productos ORDER BY nombre ASC");
$rows = $q ? $q->fetchAll() : [];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=\"inventario_farmasalud.csv\"');
$out = fopen('php://output', 'w');
fputcsv($out, ['id','nombre','descripcion','precio','stock','fecha_caducidad']);
foreach ($rows as $r) {
  fputcsv($out, [$r['id'],$r['nombre'],$r['descripcion'],$r['precio'],$r['stock'],$r['fecha_caducidad']]);
}
fclose($out);
