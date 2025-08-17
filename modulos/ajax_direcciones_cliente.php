<?php
// modulos/ajax_direcciones_cliente.php
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array((int)($_SESSION['rol_id'] ?? 0), [1,4])) {
  http_response_code(403); exit('No autorizado');
}
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$cid = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
$out = ['direcciones' => []];

if ($cid > 0) {
  // 1) Direcciones "formales"
  $st = $conn->prepare("SELECT id, alias, nombre_receptor, telefono, linea1, linea2, ciudad, departamento, codigo_postal, referencias, es_predeterminada
                        FROM direcciones
                        WHERE usuario_id = ?
                        ORDER BY es_predeterminada DESC, id DESC");
  $st->execute([$cid]);
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $d) {
    $partes = array_filter([$d['linea1'], $d['linea2'], $d['ciudad'], $d['departamento'], $d['codigo_postal']]);
    $label  = ($d['alias'] ? $d['alias'].' — ' : '') . implode(', ', $partes);
    if ($d['es_predeterminada']) { $label .= ' (predeterminada)'; }
    $out['direcciones'][] = ['id' => (int)$d['id'], 'label' => $label];
  }

  // 2) Fallback: datos del perfil en `usuarios` si no hay filas en `direcciones`
  if (empty($out['direcciones'])) {
    $u = $conn->prepare("SELECT nombre, telefono, direccion, ciudad, departamento, codigo_postal FROM usuarios WHERE id=?");
    $u->execute([$cid]);
    if ($row = $u->fetch(PDO::FETCH_ASSOC)) {
      $partes = array_filter([$row['direccion'], $row['ciudad'], $row['departamento'], $row['codigo_postal']]);
      $txt = implode(', ', $partes);
      if ($txt) {
        $out['direcciones'][] = ['id' => 0, 'label' => 'Usar datos de perfil: '.$txt, 'fromPerfil' => true];
      }
    }
  }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
