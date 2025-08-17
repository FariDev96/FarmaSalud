<?php
session_start();
require_once __DIR__ . '/../config/db.php';

/* ====== Auth: solo Soporte (rol 3) ====== */
if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 3) {
  header("Location: login.php");
  exit;
}

/* ====== Helpers ====== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableHasCol(PDO $c, string $t, string $col): bool {
  try {
    $q = $c->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $q->execute([$t,$col]);
    return (int)$q->fetchColumn() > 0;
  } catch(Throwable $e){ return false; }
}
function safeCount(PDO $c, string $sql, array $p=[]): int {
  try { $st=$c->prepare($sql); $st->execute($p); return (int)$st->fetchColumn(); } catch(Throwable $e){ return 0; }
}
function paginar(PDO $conn, string $baseSql, array $params, int $page, int $perPage): array {
  $countSql = "SELECT COUNT(*) FROM ($baseSql) X";
  $st = $conn->prepare($countSql); $st->execute($params);
  $total = (int)$st->fetchColumn();
  $pages = max(1,(int)ceil($total/$perPage));
  $page  = max(1,min($page,$pages));
  $off   = ($page-1)*$perPage;
  $sql   = $baseSql." LIMIT $perPage OFFSET $off";
  $st = $conn->prepare($sql); $st->execute($params);
  return [$st->fetchAll(PDO::FETCH_ASSOC), $total, $pages, $page];
}
function nameInitials(string $name): string {
  $name = trim(preg_replace('/\s+/', ' ', $name));
  if ($name==='') return 'SP';
  $p = explode(' ', $name);
  $first = mb_substr($p[0]??'',0,1);
  $last  = mb_substr($p[count($p)-1]??'',0,1);
  $ini = strtoupper($first.($last?:''));
  return $ini ?: 'SP';
}
function resolveAvatarUrl(?string $avatar): ?string {
  if (!$avatar) return null;
  if (preg_match('~^(https?:)?//~i', $avatar) || str_starts_with($avatar,'data:image/')) return $avatar;
  $p1 = __DIR__ . '/../uploads/'  . $avatar;
  $p2 = __DIR__ . '/../imagenes/' . $avatar;
  if (is_file($p1)) return '../uploads/'  . rawurlencode($avatar);
  if (is_file($p2)) return '../imagenes/' . rawurlencode($avatar);
  return null;
}

/* ====== Detección de columnas opcionales (tickets) ====== */
$hasArchivo   = tableHasCol($conn,'mensajes_soporte','archivo');
$hasEstado    = tableHasCol($conn,'mensajes_soporte','estado');
$hasRespuesta = tableHasCol($conn,'mensajes_soporte','respuesta');
$hasPrioridad = tableHasCol($conn,'mensajes_soporte','prioridad');

/* ====== Estados disponibles ====== */
$ESTADOS = ['Pendiente','En progreso','Resuelto'];

/* ====== Perfil del agente (navbar + offcanvas) ====== */
$uid    = (int)$_SESSION['usuario_id'];
$nombre = $_SESSION['nombre'] ?? 'Agente';

$emailCol       = tableHasCol($conn,'usuarios','email') ? 'email' : (tableHasCol($conn,'usuarios','correo') ? 'correo' : null);
$hasAvatarUser  = tableHasCol($conn,'usuarios','avatar');
$hasTelUser     = tableHasCol($conn,'usuarios','telefono');
$celColUser     = tableHasCol($conn,'usuarios','celular') ? 'celular' : (tableHasCol($conn,'usuarios','whatsapp') ? 'whatsapp' : null);
$hasExtUser     = tableHasCol($conn,'usuarios','extension');
$hasDirUser     = tableHasCol($conn,'usuarios','direccion');
$hasHorarioUser = tableHasCol($conn,'usuarios','horario');
$hasNotasUser   = tableHasCol($conn,'usuarios','notas');

$userCols = ['id','nombre'];
if ($emailCol)       $userCols[] = $emailCol;
if ($hasAvatarUser)  $userCols[] = 'avatar';
if ($hasTelUser)     $userCols[] = 'telefono';
if ($celColUser)     $userCols[] = $celColUser;
if ($hasExtUser)     $userCols[] = 'extension';
if ($hasDirUser)     $userCols[] = 'direccion';
if ($hasHorarioUser) $userCols[] = 'horario';
if ($hasNotasUser)   $userCols[] = 'notas';

$list = implode(',', array_map(fn($c)=>"`$c`", $userCols));
$st   = $conn->prepare("SELECT $list FROM usuarios WHERE id=? LIMIT 1");
$st->execute([$uid]);
$perfil = $st->fetch(PDO::FETCH_ASSOC) ?: [];

$avatarUrl = $hasAvatarUser ? resolveAvatarUrl($perfil['avatar'] ?? null) : null;
$avatarIni = nameInitials($nombre);

/* ====== CSRF ====== */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* ====== Acciones (POST) ====== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $_SESSION['flash'] = '❌ CSRF inválido. Vuelve a intentarlo.';
    header("Location: soporte_soporte.php");
    exit;
  }

  $action = $_POST['action'] ?? '';

  // (A) Actualizar perfil rápido (avatar + contacto)
  if ($action === 'quick_profile') {
    try{
      $updates=[]; $params=[];
      if ($hasTelUser) {
        $tel = trim($_POST['telefono'] ?? '');
        $updates[]='telefono=?'; $params[] = ($tel===''?null:$tel);
      }
      if ($celColUser) {
        $cel = trim($_POST['cel'] ?? '');
        $updates[]="`$celColUser`=?"; $params[] = ($cel===''?null:$cel);
      }
      if ($hasExtUser) {
        $ext = trim($_POST['extension'] ?? '');
        $updates[]='extension=?'; $params[] = ($ext===''?null:$ext);
      }
      if ($hasDirUser) {
        $dir = trim($_POST['direccion'] ?? '');
        $updates[]='direccion=?'; $params[] = ($dir===''?null:$dir);
      }
      if ($hasHorarioUser) {
        $hor = trim($_POST['horario'] ?? '');
        $updates[]='horario=?'; $params[] = ($hor===''?null:$hor);
      }
      if ($hasNotasUser) {
        $not = trim($_POST['notas'] ?? '');
        $updates[]='notas=?'; $params[] = ($not===''?null:$not);
      }

      if ($hasAvatarUser && isset($_FILES['avatar']) && $_FILES['avatar']['error']!==UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) throw new Exception('Error al subir la imagen.');
        if ($_FILES['avatar']['size'] > 2*1024*1024) throw new Exception('La imagen supera 2 MB.');
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'],true)) throw new Exception('Formato no permitido (jpg, png, webp).');
        if (!is_dir(__DIR__.'/../uploads')) @mkdir(__DIR__.'/../uploads',0775,true);
        $fname = 'support_avatar_'.$uid.'_'.time().'.'.$ext;
        $dest = __DIR__.'/../uploads/'.$fname;
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'],$dest)) throw new Exception('No se pudo guardar la imagen.');
        $updates[]='avatar=?'; $params[]=$fname;
      }

      if ($updates){
        $params[]=$uid;
        $sql="UPDATE usuarios SET ".implode(', ',$updates)." WHERE id=?";
        $conn->prepare($sql)->execute($params);
      }

      $_SESSION['flash'] = '✅ Datos de perfil actualizados.';
    }catch(Throwable $e){
      $_SESSION['flash'] = '❌ '.$e->getMessage();
    }
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
  }

  // (B) Toggle estado (ciclo Pendiente -> En progreso -> Resuelto -> Pendiente)
  if ($action === 'toggle' && $hasEstado) {
    $id = (int)($_POST['id'] ?? 0);
    $cur = $_POST['current'] ?? 'Pendiente';
    $idx = array_search($cur, $ESTADOS, true);
    if ($idx === false) $idx = 0;
    $nuevo = $ESTADOS[($idx + 1) % count($ESTADOS)];
    try{
      $conn->prepare("UPDATE mensajes_soporte SET estado=? WHERE id=?")->execute([$nuevo,$id]);
      $_SESSION['flash'] = "✅ Ticket #$id marcado como {$nuevo}.";
    }catch(Throwable $e){
      $_SESSION['flash'] = "❌ Error al cambiar estado: ".$e->getMessage();
    }
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
  }

  // (C) Guardar respuesta (con opción marcar resuelto)
  if ($action === 'reply' && $hasRespuesta) {
    $id = (int)($_POST['id'] ?? 0);
    $r  = trim($_POST['respuesta'] ?? '');
    $marcarResuelto = isset($_POST['mark_resuelto']) && $hasEstado;
    if ($id && $r!=='') {
      try{
        $conn->prepare(
          "UPDATE mensajes_soporte
           SET respuesta = CONCAT(COALESCE(respuesta,''), CASE WHEN COALESCE(respuesta,'')='' THEN '' ELSE '\n---\n' END, ?)
           WHERE id=?"
        )->execute([$r,$id]);
        if ($marcarResuelto) {
          $conn->prepare("UPDATE mensajes_soporte SET estado='Resuelto' WHERE id=?")->execute([$id]);
        }
        $_SESSION['flash'] = "✅ Respuesta guardada en ticket #$id.";
      }catch(Throwable $e){
        $_SESSION['flash'] = "❌ Error al responder: ".$e->getMessage();
      }
    }
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
  }

  // (D) Cambiar prioridad (si existe la columna)
  if ($action === 'prio' && $hasPrioridad) {
    $id = (int)($_POST['id'] ?? 0);
    $prio = in_array($_POST['prioridad'] ?? '', ['Alta','Media','Baja'], true) ? $_POST['prioridad'] : 'Media';
    try{
      $conn->prepare("UPDATE mensajes_soporte SET prioridad=? WHERE id=?")->execute([$prio,$id]);
      $_SESSION['flash'] = "✅ Prioridad actualizada (#$id → $prio).";
    }catch(Throwable $e){
      $_SESSION['flash'] = "❌ Error al actualizar prioridad.";
    }
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
  }

  // (E) Acciones masivas
  if ($action === 'bulk') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $op  = $_POST['bulk_action'] ?? '';
    if ($ids) {
      try{
        if ($op==='delete') {
          $in = implode(',', array_fill(0, count($ids), '?'));
          $conn->prepare("DELETE FROM mensajes_soporte WHERE id IN ($in)")->execute($ids);
          $_SESSION['flash'] = "🗑️ ".count($ids)." ticket(s) eliminados.";
        } elseif ($hasEstado && in_array($op, $ESTADOS, true)) {
          $in = implode(',', array_fill(0, count($ids), '?'));
          $params = array_merge([$op], $ids);
          $conn->prepare("UPDATE mensajes_soporte SET estado=? WHERE id IN ($in)")->execute($params);
          $_SESSION['flash'] = "✅ ".count($ids)." ticket(s) marcados como $op.";
        }
      }catch(Throwable $e){
        $_SESSION['flash'] = "❌ Error en acción masiva.";
      }
    }
    header("Location: ".$_SERVER['REQUEST_URI']);
    exit;
  }
}

/* ====== Filtros ====== */
$q       = trim($_GET['q']  ?? '');
$estadoF = trim($_GET['estado'] ?? '');
$d1      = trim($_GET['d1'] ?? '');
$d2      = trim($_GET['d2'] ?? '');
$prioF   = trim($_GET['prio'] ?? '');
$pp      = max(5,min(50,(int)($_GET['pp'] ?? 10)));
$p       = max(1,(int)($_GET['p'] ?? 1));

/* ====== Base SELECT ====== */
$select = "m.id, m.asunto, m.mensaje, m.fecha, u.nombre AS remitente";
$select .= $hasEstado    ? ", COALESCE(m.estado,'Pendiente') AS estado" : ", '' AS estado";
$select .= $hasArchivo   ? ", m.archivo" : "";
$select .= $hasRespuesta ? ", COALESCE(m.respuesta,'') AS respuesta" : "";
$select .= $hasPrioridad ? ", COALESCE(m.prioridad,'Media') AS prioridad" : "";

$where = []; $params=[];
if ($q!==''){
  $like = "%$q%";
  $where[] = "(m.asunto LIKE ? OR m.mensaje LIKE ? OR u.nombre LIKE ?)";
  array_push($params, $like, $like, $like);
}
if ($hasEstado && $estadoF!==''){
  $where[] = "COALESCE(m.estado,'Pendiente') = ?";
  $params[] = $estadoF;
}
if ($hasPrioridad && in_array($prioF, ['Alta','Media','Baja'], true)){
  $where[] = "COALESCE(m.prioridad,'Media') = ?";
  $params[] = $prioF;
}
if ($d1!==''){ $where[]="DATE(m.fecha)>=?"; $params[]=$d1; }
if ($d2!==''){ $where[]="DATE(m.fecha)<=?"; $params[]=$d2; }

$sqlBase = "SELECT $select
            FROM mensajes_soporte m
            JOIN usuarios u ON u.id = m.usuario_id".
            ($where ? " WHERE ".implode(" AND ",$where) : "").
           " ORDER BY m.fecha DESC";

/* ====== Paginación ====== */
[$mensajes, $total, $pages, $p] = paginar($conn,$sqlBase,$params,$p,$pp);

/* ====== KPIs ====== */
$tot  = safeCount($conn, "SELECT COUNT(*) FROM mensajes_soporte");
$pen  = $hasEstado ? safeCount($conn, "SELECT COUNT(*) FROM mensajes_soporte WHERE LOWER(COALESCE(estado,'')) IN ('pendiente','')") : $tot;
$res  = safeCount($conn, "SELECT COUNT(*) FROM mensajes_soporte WHERE LOWER(COALESCE(estado,''))='resuelto'");
$prog = $hasEstado ? safeCount($conn, "SELECT COUNT(*) FROM mensajes_soporte WHERE LOWER(COALESCE(estado,''))='en progreso'") : 0;

/* ====== Export CSV ====== */
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=soporte_'.date('Ymd_His').'.csv');
  $out=fopen('php://output','w');
  $hdr = ['id','remitente','asunto','mensaje','fecha'];
  if ($hasEstado)    $hdr[]='estado';
  if ($hasPrioridad) $hdr[]='prioridad';
  if ($hasArchivo)   $hdr[]='archivo';
  if ($hasRespuesta) $hdr[]='respuesta';
  fputcsv($out,$hdr);
  $st = $conn->prepare($sqlBase); $st->execute($params);
  while($r=$st->fetch(PDO::FETCH_ASSOC)){
    $row = [$r['id'],$r['remitente'],$r['asunto'],$r['mensaje'],$r['fecha']];
    if ($hasEstado)    $row[]=$r['estado'];
    if ($hasPrioridad) $row[]=$r['prioridad'] ?? 'Media';
    if ($hasArchivo)   $row[]=$r['archivo'] ?? '';
    if ($hasRespuesta) $row[]=$r['respuesta'] ?? '';
    fputcsv($out,$row);
  }
  fclose($out); exit;
}

/* Respuestas rápidas (plantillas) */
$PLANTILLAS = [
  "Hola, gracias por contactarnos. Estamos revisando tu caso y te daremos respuesta pronto.",
  "Hemos actualizado tu pedido. ¿Puedes confirmar si el problema persiste?",
  "Tu ticket fue resuelto. Si necesitas algo más, responde a este mensaje."
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🎧 Panel de Soporte</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{ --fs-border:#e8edf3; }
    body{ background:#f6f9fc; }
    .kpi{ border:1px solid var(--fs-border); border-radius:16px; background:#fff; padding:16px; }
    .table td,.table th{ vertical-align:middle; }
    .badge-state{ font-size:.85rem; }
    .avatar { width:36px; height:36px; border-radius:50%; overflow:hidden; border:2px solid rgba(0,0,0,.08); display:grid; place-items:center; background:#0f172a; color:#fff; font-weight:700; cursor:pointer; }
    .avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
  </style>
</head>
<body>

<div class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">🎧 Panel de Soporte</h2>
    <div class="d-flex align-items-center gap-2">
      <span class="text-muted small d-none d-sm-inline">Hola, <strong><?= h($nombre) ?></strong></span>

      <!-- Avatar: abre offcanvas -->
      <a class="avatar" data-bs-toggle="offcanvas" href="#offPerfilSoporte" role="button" aria-controls="offPerfilSoporte" title="Mi perfil">
        <?php if ($avatarUrl): ?>
          <img src="<?= h($avatarUrl) ?>" alt="Avatar de <?= h($nombre) ?>">
        <?php else: ?>
          <span><?= h($avatarIni) ?></span>
        <?php endif; ?>
      </a>

      <a href="logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
    </div>
  </div>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert <?= str_starts_with($_SESSION['flash'],'✅') ? 'alert-success' : 'alert-info' ?>">
      <?= $_SESSION['flash']; unset($_SESSION['flash']); ?>
    </div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="kpi text-center"><div class="text-secondary">Total</div><div class="display-6"><?= $tot ?></div></div></div>
    <div class="col-6 col-md-3"><div class="kpi text-center"><div class="text-secondary">Pendientes</div><div class="display-6"><?= $pen ?></div></div></div>
    <div class="col-6 col-md-3"><div class="kpi text-center"><div class="text-secondary">En progreso</div><div class="display-6"><?= $prog ?></div></div></div>
    <div class="col-6 col-md-3"><div class="kpi text-center"><div class="text-secondary">Resueltos</div><div class="display-6"><?= $res ?></div></div></div>
  </div>

  <!-- Filtros -->
  <form class="row g-2 align-items-end mb-3">
    <div class="col-12 col-md-4">
      <label class="form-label">Buscar</label>
      <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Asunto, mensaje o remitente…">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Estado</label>
      <select name="estado" class="form-select">
        <option value="">Todos</option>
        <?php foreach($ESTADOS as $e): ?>
          <option value="<?= h($e) ?>" <?= $estadoF===$e?'selected':'' ?>><?= h($e) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($hasPrioridad): ?>
    <div class="col-6 col-md-2">
      <label class="form-label">Prioridad</label>
      <select name="prio" class="form-select">
        <option value="">Todas</option>
        <?php foreach(['Alta','Media','Baja'] as $e): ?>
          <option value="<?= h($e) ?>" <?= $prioF===$e?'selected':'' ?>><?= h($e) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
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
        <?php foreach([5,10,15,20,30,50] as $n): ?>
          <option value="<?= $n ?>" <?= $pp===$n?'selected':'' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-1 d-grid">
      <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
    </div>
  </form>

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
    <div class="text-secondary small">Resultados: <strong><?= $total ?></strong></div>
    <div class="d-flex gap-2">
      <form method="POST" class="d-flex gap-2 align-items-center" id="bulkForm">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="action" value="bulk">
        <select name="bulk_action" class="form-select form-select-sm">
          <option value="Resuelto">Marcar Resuelto</option>
          <option value="En progreso">Marcar En progreso</option>
          <option value="Pendiente">Marcar Pendiente</option>
          <option value="delete">Eliminar seleccionados</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary">Aplicar</button>
      </form>
      <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($_SERVER['REQUEST_URI'] . (str_contains($_SERVER['REQUEST_URI'],'?') ? '&' : '?') . 'export=csv') ?>">
        <i class="bi bi-download"></i> Exportar CSV
      </a>
    </div>
  </div>

  <?php if (!$mensajes): ?>
    <div class="alert alert-info">No hay mensajes con los filtros actuales.</div>
  <?php else: ?>
    <div class="table-responsive shadow bg-white rounded">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-success">
          <tr>
            <th><input type="checkbox" id="chkAll"></th>
            <th>#</th>
            <th>Remitente</th>
            <th>Asunto</th>
            <th>Mensaje</th>
            <th>Fecha</th>
            <th>Estado</th>
            <?php if ($hasPrioridad): ?><th>Prioridad</th><?php endif; ?>
            <?php if ($hasArchivo): ?><th>Adjunto</th><?php endif; ?>
            <?php if ($hasRespuesta): ?><th>Respuesta</th><?php endif; ?>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($mensajes as $m): ?>
            <tr>
              <td><input type="checkbox" form="bulkForm" name="ids[]" value="<?= (int)$m['id'] ?>" class="chk"></td>
              <td><?= (int)$m['id'] ?></td>
              <td><?= h($m['remitente']) ?></td>
              <td><?= h($m['asunto']) ?></td>
              <td style="max-width:360px"><?= nl2br(h($m['mensaje'])) ?></td>
              <td><?= h($m['fecha']) ?></td>
              <td>
                <?php $est = $m['estado'] ?? 'Pendiente';
                  $cls = $est==='Resuelto'?'success':($est==='En progreso'?'info':'warning'); ?>
                <span class="badge text-bg-<?= $cls ?> badge-state"><?= h($est) ?></span>
              </td>
              <?php if ($hasPrioridad): ?>
                <td>
                  <form method="POST" class="d-flex gap-1 align-items-center">
                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="action" value="prio">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <select name="prioridad" class="form-select form-select-sm">
                      <?php foreach(['Alta','Media','Baja'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($m['prioridad'] ?? 'Media')===$opt?'selected':'' ?>><?= $opt ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-secondary">OK</button>
                  </form>
                </td>
              <?php endif; ?>
              <?php if ($hasArchivo): ?>
                <td>
                  <?php if (!empty($m['archivo'])): ?>
                    <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= '../uploads/'.rawurlencode($m['archivo']) ?>">
                      <i class="bi bi-paperclip"></i> Ver
                    </a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
              <?php if ($hasRespuesta): ?>
                <td style="max-width:260px" class="small text-muted"><?= nl2br(h($m['respuesta'])) ?></td>
              <?php endif; ?>
              <td class="text-end">
                <div class="d-flex flex-wrap gap-1 justify-content-end">
                  <?php if ($hasEstado): ?>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="current" value="<?= h($m['estado']) ?>">
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick="return confirm('¿Cambiar estado del ticket #<?= (int)$m['id'] ?>?')">
                      Siguiente estado
                    </button>
                  </form>
                  <?php endif; ?>

                  <?php if ($hasRespuesta): ?>
                  <button class="btn btn-sm btn-outline-primary" type="button"
                          data-bs-toggle="collapse" data-bs-target="#reply<?= (int)$m['id'] ?>">
                    Responder
                  </button>
                  <?php endif; ?>
                </div>

                <?php if ($hasRespuesta): ?>
                <div class="collapse mt-2" id="reply<?= (int)$m['id'] ?>">
                  <form method="POST">
                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <div class="mb-1 d-flex gap-2">
                      <select class="form-select form-select-sm" onchange="insPlantilla(this, 'ta<?= (int)$m['id'] ?>')">
                        <option value="">Plantillas…</option>
                        <?php foreach ($PLANTILLAS as $t): ?>
                          <option value="<?= h($t) ?>"><?= h(mb_strimwidth($t,0,50,'…','UTF-8')) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <?php if ($hasEstado): ?>
                        <div class="form-check ms-2">
                          <input class="form-check-input" type="checkbox" name="mark_resuelto" id="mr<?= (int)$m['id'] ?>">
                          <label for="mr<?= (int)$m['id'] ?>" class="form-check-label small">Marcar Resuelto</label>
                        </div>
                      <?php endif; ?>
                    </div>
                    <textarea id="ta<?= (int)$m['id'] ?>" name="respuesta" class="form-control mb-1" rows="2" placeholder="Escribe una respuesta breve..." required></textarea>
                    <button class="btn btn-sm btn-primary">Guardar respuesta</button>
                  </form>
                </div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <nav class="mt-3">
      <ul class="pagination pagination-sm">
        <?php
          $qs = $_GET; unset($qs['p']);
          $base='?'.http_build_query($qs);
          for($i=1;$i<=$pages;$i++):
        ?>
          <li class="page-item <?= $i===$p?'active':'' ?>"><a class="page-link" href="<?= $base.'&p='.$i ?>"><?= $i ?></a></li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<!-- OFFCANVAS: Perfil rápido del soporte -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offPerfilSoporte" aria-labelledby="offPerfilSoporteLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offPerfilSoporteLabel"><i class="bi bi-person-gear"></i> Mi información</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>
  <div class="offcanvas-body">
    <form method="POST" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="action" value="quick_profile">

      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="avatar" style="width:64px;height:64px;">
          <?php if ($avatarUrl): ?>
            <img id="avatarPreview" src="<?= h($avatarUrl) ?>" alt="Avatar">
          <?php else: ?>
            <span id="avatarInitials" style="font-size:1.2rem;"><?= h($avatarIni) ?></span>
          <?php endif; ?>
        </div>
        <?php if ($hasAvatarUser): ?>
        <div class="flex-fill">
          <label class="form-label">Foto (JPG/PNG/WebP, máx 2 MB)</label>
          <input type="file" name="avatar" id="avatarInput" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/*">
        </div>
        <?php endif; ?>
      </div>

      <div class="mb-2">
        <label class="form-label">Nombre</label>
        <input type="text" class="form-control" value="<?= h($perfil['nombre'] ?? $nombre) ?>" disabled>
      </div>

      <?php if ($emailCol): ?>
      <div class="mb-2">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" value="<?= h($perfil[$emailCol] ?? '') ?>" disabled>
      </div>
      <?php endif; ?>

      <?php if ($hasTelUser): ?>
      <div class="mb-2">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="<?= h($perfil['telefono'] ?? '') ?>" placeholder="Ej. 601 555 1234">
      </div>
      <?php endif; ?>

      <?php if ($celColUser): ?>
      <div class="mb-2">
        <label class="form-label"><?= $celColUser==='whatsapp'?'WhatsApp / Celular':'Celular' ?></label>
        <input type="text" name="cel" class="form-control" value="<?= h($perfil[$celColUser] ?? '') ?>" placeholder="Ej. 300 123 4567">
      </div>
      <?php endif; ?>

      <?php if ($hasExtUser): ?>
      <div class="mb-2">
        <label class="form-label">Extensión</label>
        <input type="text" name="extension" class="form-control" value="<?= h($perfil['extension'] ?? '') ?>" placeholder="Ej. 104">
      </div>
      <?php endif; ?>

      <?php if ($hasDirUser): ?>
      <div class="mb-2">
        <label class="form-label">Dirección (sede)</label>
        <input type="text" name="direccion" class="form-control" value="<?= h($perfil['direccion'] ?? '') ?>">
      </div>
      <?php endif; ?>

      <?php if ($hasHorarioUser): ?>
      <div class="mb-2">
        <label class="form-label">Horario</label>
        <input type="text" name="horario" class="form-control" value="<?= h($perfil['horario'] ?? '') ?>" placeholder="L–V 8:00–17:00">
      </div>
      <?php endif; ?>

      <?php if ($hasNotasUser): ?>
      <div class="mb-3">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="2" placeholder="Observaciones, especialidad, etc."><?= h($perfil['notas'] ?? '') ?></textarea>
      </div>
      <?php endif; ?>

      <div class="d-grid gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // checkboxes masivos
  const chkAll = document.getElementById('chkAll');
  if (chkAll){
    chkAll.addEventListener('change', ()=>{
      document.querySelectorAll('.chk').forEach(c => c.checked = chkAll.checked);
    });
  }
  // insertar plantilla en textarea de respuesta
  function insPlantilla(sel, taId){
    const v = sel.value || '';
    if (!v) return;
    const ta = document.getElementById(taId);
    ta.value = (ta.value ? ta.value + '\n' : '') + v;
    sel.selectedIndex = 0;
    ta.focus();
  }
  // Preview avatar
  const input = document.getElementById('avatarInput');
  if (input) {
    input.addEventListener('change', () => {
      const f = input.files && input.files[0];
      if (!f) return;
      if (!/^image\//.test(f.type)) { alert('Selecciona una imagen.'); input.value=''; return; }
      const url = URL.createObjectURL(f);
      let prev = document.getElementById('avatarPreview');
      const ini  = document.getElementById('avatarInitials');
      if (ini) ini.style.display='none';
      if (!prev) {
        prev = document.createElement('img');
        prev.id = 'avatarPreview';
        prev.style.width='100%'; prev.style.height='100%'; prev.style.objectFit='cover';
        document.querySelector('#offPerfilSoporte .avatar').appendChild(prev);
      }
      prev.src = url;
    });
  }
</script>
</body>
</html>




