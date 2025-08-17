<?php
// export/export_ventas.php
require_once __DIR__.'/../config/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!in_array($_SESSION['rol_id'] ?? 0, [1,4,6])) { http_response_code(403); exit('No autorizado'); }

$pdo = db();
$from = $_GET['desde'] ?? null;
$to   = $_GET['hasta'] ?? null;

$sql = "SELECT v.id, v.fecha, u.nombre AS registrado_por, c.nombre AS cliente, p.nombre AS producto,
               v.cantidad, v.precio_unitario, (v.cantidad*v.precio_unitario) AS total,
               v.estado_entrega, v.observaciones
        FROM ventas v
        LEFT JOIN usuarios u ON u.id = v.usuario_id
        LEFT JOIN usuarios c ON c.id = v.cliente_id
        LEFT JOIN productos p ON p.id = v.producto_id
        WHERE 1=1";
$params = [];
if ($from) { $sql .= " AND DATE(v.fecha) >= :f"; $params[':f'] = $from; }
if ($to)   { $sql .= " AND DATE(v.fecha) <= :t"; $params[':t'] = $to; }
$sql .= " ORDER BY v.fecha DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=\"ventas_farmasalud.csv\"');
$out = fopen('php://output', 'w');
fputcsv($out, ['id','fecha','registrado_por','cliente','producto','cantidad','precio_unitario','total','estado','observaciones']);
foreach ($rows as $r) {
  fputcsv($out, [$r['id'],$r['fecha'],$r['registrado_por'],$r['cliente'],$r['producto'],$r['cantidad'],$r['precio_unitario'],$r['total'],$r['estado_entrega'],$r['observaciones']]);
}
fclose($out);
