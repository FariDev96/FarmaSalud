<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// -------- util esquema --------
function tableCols(PDO $c, string $table): array {
  try { $out=[]; foreach($c->query("SHOW COLUMNS FROM `{$table}`") as $r) $out[]=$r['Field']; return $out; }
  catch(Throwable $e){ return []; }
}
$colsSoporte = tableCols($conn, 'mensajes_soporte');
$hasArchivo  = in_array('archivo', $colsSoporte, true);

// -------- acciones --------
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_id'], $_POST['estado_actual'])) {
  $id = (int)$_POST['toggle_id'];
  $nuevo = $_POST['estado_actual']==='Pendiente' ? 'Resuelto' : 'Pendiente';
  $stmt = $conn->prepare("UPDATE mensajes_soporte SET estado=? WHERE id=?");
  $stmt->execute([$nuevo, $id]);
  // log opcional
  try{
    $conn->prepare("INSERT INTO logs(usuario_id, accion, detalle) VALUES(?,?,?)")
         ->execute([(int)$_SESSION['usuario_id'], 'soporte_estado', "msg:$id => $nuevo"]);
  }catch(Throwable $e){}
  // volver preservando filtros
  $qs = $_POST['qs'] ?? '';
  header("Location: soporte_admin.php".($qs ? ('?'.$qs) : ''));
  exit;
}

// -------- filtros --------
$q       = trim($_GET['q']  ?? ($_GET['buscar'] ?? '')); // compat
$estado  = trim($_GET['est']?? ($_GET['estado'] ?? ''));
$d1      = trim($_GET['d1'] ?? '');
$d2      = trim($_GET['d2'] ?? '');
$perPage = max(5,min(100,(int)($_GET['pp'] ?? 15)));
$page    = max(1,(int)($_GET['p'] ?? 1));

// -------- kpis --------
$pend = (int)$conn->query("SELECT COUNT(*) FROM mensajes_soporte WHERE estado='Pendiente'")->fetchColumn();
$res  = (int)$conn->query("SELECT COUNT(*) FROM mensajes_soporte WHERE estado='Resuelto'")->fetchColumn();

// -------- consulta principal --------
$select = "m.id, m.asunto, m.mensaje, m.estado, m.fecha,
          u.nombre AS remitente, u.correo, COALESCE(u.telefono,'') AS telefono".
          ($hasArchivo ? ", COALESCE(m.archivo,'') AS archivo" : "");

$join = " JOIN usuarios u ON u.id = m.usuario_id ";
$where = []; $params = [];

if ($estado !== '') { $where[]="m.estado = ?"; $params[]=$estado; }
if ($q !== '') { $where[]="(m.asunto LIKE ? OR m.mensaje LIKE ? OR u.nombre LIKE ? OR u.correo LIKE ?)";
  $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%"; $params[]="%$q%";
}
if ($d1 !== '') { $where[]="DATE(m.fecha)>=?"; $params[]=$d1; }
if ($d2 !== '') { $where[]="DATE(m.fecha)<=?"; $params[]=$d2; }

$sqlBase = "FROM mensajes_soporte m $join".($where ? " WHERE ".implode(" AND ",$where) : "");
// total y páginas
$stmt = $conn->prepare("SELECT COUNT(*) ".$sqlBase);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1,(int)ceil($total/$perPage));
$page  = min($page,$pages);
$off   = ($page-1)*$perPage;

// export CSV
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename=soporte_'.date('Ymd_His').'.csv');
  $out = fopen('php://output','w');
  fputcsv($out, ['ID','Remitente','Correo','Teléfono','Estado','Fecha','Asunto','Mensaje', ($hasArchivo?'Archivo':'')]);
  $s = $conn->prepare("SELECT $select $sqlBase ORDER BY m.fecha DESC");
  $s->execute($params);
  while($r = $s->fetch(PDO::FETCH_ASSOC)){
    fputcsv($out, [
      $r['id'], $r['remitente'], $r['correo'], $r['telefono'], $r['estado'], $r['fecha'],
      $r['asunto'], $r['mensaje'], ($hasArchivo ? $r['archivo'] : '')
    ]);
  }
  exit;
}

$sql = "SELECT $select $sqlBase ORDER BY m.fecha DESC LIMIT $perPage OFFSET $off";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// QS para volver tras acciones
$qs = http_build_query([
  'q'=>$q,'est'=>$estado,'d1'=>$d1,'d2'=>$d2,'pp'=>$perPage,'p'=>$page
], '', '&');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Soporte — FarmaSalud</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{ --fs-border:#e8edf3; --fs-surface:#fff; }
.card-kpi{ border:1px solid var(--fs-border); border-radius:16px; padding:16px; background:var(--fs-surface); }
.badge-state{ font-size:.85rem; }
.table td,.table th{ vertical-align: middle; }
.msg-snippet{max-width: 520px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
@media print{
  .no-print{ display:none !important; }
  .table { font-size: 12px; }
}
</style>
</head>
<body class="bg-light">

<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h2 class="mb-2 mb-sm-0">📨 Soporte — Bandeja</h2>
    <div class="d-flex gap-2 no-print">
      <a href="admin.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2"></i> Panel</a>
      <a class="btn btn-outline-success btn-sm" href="?<?= h($qs) ?>&export=csv"><i class="bi bi-filetype-csv"></i> Exportar CSV</a>
      <button class="btn btn-outline-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
      <a href="logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card-kpi text-center">
        <div class="text-secondary">Pendientes</div>
        <div class="display-6"><?= $pend ?></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card-kpi text-center">
        <div class="text-secondary">Resueltos</div>
        <div class="display-6"><?= $res ?></div>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <form class="row g-2 align-items-end mb-3 no-print">
    <div class="col-12 col-md-4">
      <label class="form-label">Buscar</label>
      <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Asunto, mensaje, nombre o correo">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Estado</label>
      <select name="est" class="form-select">
        <option value="">Todos</option>
        <?php foreach(['Pendiente','Resuelto'] as $e): ?>
          <option value="<?= $e ?>" <?= $estado===$e?'selected':'' ?>><?= $e ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="d1" value="<?= h($d1) ?>" class="form-control">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="d2" value="<?= h($d2) ?>" class="form-control">
    </div>
    <div class="col-6 col-md-1">
      <label class="form-label">Por pág.</label>
      <select name="pp" class="form-select">
        <?php foreach([10,15,20,30,50,100] as $n): ?>
          <option value="<?= $n ?>" <?= $perPage===$n?'selected':'' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-1 d-grid">
      <button class="btn btn-primary"><i class="bi bi-funnel"></i> Aplicar</button>
    </div>
  </form>

  <?php if (!$mensajes): ?>
    <div class="alert alert-info">No hay mensajes con los filtros actuales.</div>
  <?php else: ?>
    <div class="table-responsive shadow bg-white rounded">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-success">
          <tr>
            <th>#</th>
            <th>Remitente</th>
            <th>Contacto</th>
            <th>Asunto</th>
            <th>Mensaje</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th class="text-end no-print">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($mensajes as $r): 
            $wa = $r['telefono'] ? 'https://wa.me/'.preg_replace('/\D/', '', $r['telefono']) : '';
            $mailto = 'mailto:'.rawurlencode($r['correo']).'?subject='.rawurlencode('Re: '.$r['asunto'].' [#'.$r['id'].']');
            $adj   = ($hasArchivo && $r['archivo']!=='') ? ('../uploads/'.basename($r['archivo'])) : '';
          ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td>
              <div class="fw-semibold"><?= h($r['remitente']) ?></div>
              <div class="small text-muted"><?= h($r['correo']) ?></div>
            </td>
            <td>
              <?php if($r['telefono']): ?>
                <div class="small"><?= h($r['telefono']) ?></div>
                <div class="d-flex gap-1 mt-1 no-print">
                  <a class="btn btn-sm btn-outline-secondary" href="tel:<?= h($r['telefono']) ?>"><i class="bi bi-telephone"></i></a>
                  <?php if($wa): ?><a class="btn btn-sm btn-outline-success" target="_blank" href="<?= h($wa) ?>"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= h($mailto) ?>"><i class="bi bi-envelope"></i></a>
                </div>
              <?php else: ?>
                <a class="btn btn-sm btn-outline-primary no-print" href="<?= h($mailto) ?>"><i class="bi bi-envelope"></i> Email</a>
              <?php endif; ?>
            </td>
            <td class="fw-semibold"><?= h($r['asunto']) ?></td>
            <td>
              <div class="msg-snippet"><?= h($r['mensaje']) ?></div>
              <div class="small">
                <?php if ($adj): ?>
                  <a class="link-primary" target="_blank" href="<?= h($adj) ?>"><i class="bi bi-paperclip"></i> Adjuntar</a>
                <?php endif; ?>
              </div>
            </td>
            <td><?= h($r['fecha']) ?></td>
            <td><span class="badge badge-state <?= $r['estado']==='Pendiente'?'bg-warning':'bg-success' ?>"><?= h($r['estado']) ?></span></td>
            <td class="text-end no-print">
              <div class="d-flex flex-wrap gap-1 justify-content-end">
                <button
                  class="btn btn-sm btn-outline-secondary"
                  data-bs-toggle="modal"
                  data-bs-target="#viewModal"
                  data-id="<?= (int)$r['id'] ?>"
                  data-remitente="<?= h($r['remitente']) ?>"
                  data-correo="<?= h($r['correo']) ?>"
                  data-telefono="<?= h($r['telefono']) ?>"
                  data-asunto="<?= h($r['asunto']) ?>"
                  data-mensaje="<?= h($r['mensaje']) ?>"
                  data-archivo="<?= h($adj) ?>"
                ><i class="bi bi-eye"></i> Ver</button>

                <form method="POST" class="ms-1" onsubmit="return confirm('¿Cambiar estado de este mensaje?');">
                  <input type="hidden" name="toggle_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="estado_actual" value="<?= h($r['estado']) ?>">
                  <input type="hidden" name="qs" value="<?= h($qs) ?>">
                  <button class="btn btn-sm <?= $r['estado']==='Pendiente'?'btn-primary':'btn-outline-secondary' ?>">
                    <i class="bi bi-check2-circle"></i>
                    <?= $r['estado']==='Pendiente'?'Marcar resuelto':'Marcar pendiente' ?>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- paginación -->
    <nav class="mt-3 no-print">
      <ul class="pagination pagination-sm">
        <?php
          $base = $_GET; unset($base['p']);
          $baseQS = http_build_query($base,'','&');
          for($i=1;$i<=$pages;$i++):
        ?>
          <li class="page-item <?= $i===$page?'active':'' ?>">
            <a class="page-link" href="?<?= $baseQS ?>&p=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>

  <?php endif; ?>
</div>

<!-- Modal de vista -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-chat-dots"></i> Detalle del mensaje</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><strong>Remitente:</strong> <span id="vmRem"></span> · <span id="vmCor"></span></div>
        <div class="mb-2"><strong>Asunto:</strong> <span id="vmAsu"></span></div>
        <div class="mb-3"><strong>Mensaje:</strong><br><div id="vmMsg" class="border rounded p-2 bg-light"></div></div>
        <div id="vmAdjWrap" class="d-none">
          <div class="mb-2"><strong>Adjunto:</strong> <a id="vmAdjLink" href="#" target="_blank"><i class="bi bi-paperclip"></i> Descargar</a></div>
          <div id="vmPreview" class="ratio ratio-16x9 border rounded overflow-hidden"></div>
        </div>
      </div>
      <div class="modal-footer">
        <a id="vmMailto" class="btn btn-primary" href="#"><i class="bi bi-envelope"></i> Responder por email</a>
        <a id="vmWa" class="btn btn-success d-none" target="_blank" href="#"><i class="bi bi-whatsapp"></i> WhatsApp</a>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Rellena el modal
const modal = document.getElementById('viewModal');
modal.addEventListener('show.bs.modal', ev=>{
  const btn = ev.relatedTarget;
  const id  = btn.dataset.id;
  const rem = btn.dataset.remitente;
  const cor = btn.dataset.correo;
  const tel = btn.dataset.telefono || '';
  const asu = btn.dataset.asunto;
  const msg = btn.dataset.mensaje;
  const adj = btn.dataset.archivo || '';

  modal.querySelector('#vmRem').textContent = rem;
  modal.querySelector('#vmCor').textContent = cor;
  modal.querySelector('#vmAsu').textContent = asu;
  modal.querySelector('#vmMsg').textContent = msg;

  const mailto = 'mailto:'+encodeURIComponent(cor)+'?subject='+encodeURIComponent('Re: '+asu+' [#'+id+']');
  modal.querySelector('#vmMailto').href = mailto;

  const waBtn = modal.querySelector('#vmWa');
  if (tel.trim()){
    waBtn.classList.remove('d-none');
    waBtn.href = 'https://wa.me/'+ tel.replace(/\\D/g,'');
  } else { waBtn.classList.add('d-none'); }

  const wrap = modal.querySelector('#vmAdjWrap');
  const link = modal.querySelector('#vmAdjLink');
  const prev = modal.querySelector('#vmPreview');
  prev.innerHTML = '';
  if (adj){
    wrap.classList.remove('d-none');
    link.href = adj;
    const ext = adj.split('.').pop().toLowerCase();
    if (['png','jpg','jpeg','gif','webp'].includes(ext)){
      const img = new Image(); img.src = adj; img.style.objectFit='contain'; img.style.width='100%'; img.style.height='100%';
      prev.appendChild(img);
    } else if (ext==='pdf'){
      const iframe = document.createElement('iframe'); iframe.src = adj; iframe.style.border='0';
      prev.appendChild(iframe);
    }
  } else {
    wrap.classList.add('d-none');
  }
});
</script>
</body>
</html>



