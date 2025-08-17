<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 6) {
  header("Location: login.php");
  exit;
}

$YO = (int)$_SESSION['usuario_id'];
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* =========================
   CSRF
   ========================= */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* =========================
   Utilidades de esquema
   ========================= */
function tableCols(PDO $c, string $t): array {
  try { $a=[]; foreach($c->query("SHOW COLUMNS FROM `{$t}`") as $r) $a[]=$r['Field']; return $a; }
  catch(Throwable $e){ return []; }
}
function enumValues(PDO $c, string $t, string $col): array {
  try{
    $st = $c->query("SHOW COLUMNS FROM `{$t}` LIKE '{$col}'");
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || stripos($row['Type'],'enum(') !== 0) return [];
    $raw = trim(substr($row['Type'], 5), ')');
    $vals = array_map(fn($s)=>trim($s,"'"), explode(',', $raw));
    return $vals;
  }catch(Throwable $e){ return []; }
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
function initials(string $name): string {
  $name = trim(preg_replace('/\s+/', ' ', $name));
  if ($name==='') return 'DI';
  $p = explode(' ', $name);
  $first = mb_substr($p[0]??'',0,1);
  $last  = mb_substr($p[count($p)-1]??'',0,1);
  $i = strtoupper($first.($last?:''));
  return $i ?: 'DI';
}

/* =========================
   Detección de columnas
   ========================= */
$ventasCols = tableCols($conn, 'ventas');
$dirCols    = tableCols($conn, 'direcciones');
$userCols   = tableCols($conn, 'usuarios');

$hasEstado       = in_array('estado_entrega', $ventasCols, true);
$hasDireccionId  = in_array('direccion_id',   $ventasCols, true);

// asignación opcional
$hasRepartidorId = in_array('repartidor_id',  $ventasCols, true) || in_array('distribuidor_id', $ventasCols, true);
$repartidorCol   = in_array('repartidor_id',  $ventasCols, true) ? 'repartidor_id'
                : (in_array('distribuidor_id',$ventasCols,true)  ? 'distribuidor_id' : null);

// estados permitidos según enum real
$estadosEnum = $hasEstado ? enumValues($conn, 'ventas', 'estado_entrega') : [];
$canIncidencia = in_array('Incidencia', $estadosEnum, true);

// teléfono del cliente
$phoneCol = null;
foreach (['telefono','celular','movil','phone'] as $c) {
  if (in_array($c, $userCols, true)) { $phoneCol = $c; break; }
}

// Dirección: columnas presentes
$dirPick = []; $latCol=null; $lngCol=null;
if ($dirCols) {
  foreach (['direccion','linea1','linea2','calle','numero','barrio','colonia','ciudad','localidad','municipio','estado','departamento','codigo_postal','cp','pais','referencia','referencias'] as $c) {
    if (in_array($c, $dirCols, true)) $dirPick[] = "d.$c";
  }
  foreach (['lat','latitude','latitud'] as $c) if (in_array($c, $dirCols, true)) { $latCol="d.$c"; break; }
  foreach (['lng','lon','longitud','longitude'] as $c) if (in_array($c, $dirCols, true)) { $lngCol="d.$c"; break; }
}
$selectDir = $hasDireccionId && $dirPick ? ", d.id AS dir_id, ".implode(',', $dirPick) : '';
if ($latCol && $lngCol) $selectDir .= ", $latCol AS _lat, $lngCol AS _lng";
$joinDir   = $hasDireccionId && $dirPick ? " LEFT JOIN direcciones d ON v.direccion_id = d.id" : '';

function dirLabel(array $r): string {
  if (!empty($r['direccion'])) return $r['direccion'];
  if (!empty($r['linea1']) || !empty($r['linea2'])) {
    $x = trim(($r['linea1'] ?? '').' '.($r['linea2'] ?? ''));
    $y = trim(($r['ciudad'] ?? '').', '.($r['departamento'] ?? ''));
    $cp= trim(($r['codigo_postal'] ?? $r['cp'] ?? ''));
    $txt = trim($x . ($y? " — $y":'') . ($cp? " ($cp)":''));
  } else {
    $parts=[];
    foreach (['calle','numero','barrio','colonia','ciudad','localidad','municipio','estado','departamento','codigo_postal','cp','pais'] as $f)
      if (isset($r[$f]) && trim((string)$r[$f])!=='') $parts[]=$r[$f];
    $txt = $parts ? implode(', ',$parts) : '';
  }
  $ref = $r['referencias'] ?? ($r['referencia'] ?? '');
  if (!empty($ref)) $txt .= ($txt?' — ':'').$ref;
  return trim($txt);
}

/* =========================
   Perfil del distribuidor
   ========================= */
$nombre = $_SESSION['nombre'] ?? 'Distribuidor';
$hasAvatarUser  = in_array('avatar', $userCols, true);
$hasTelUser     = in_array('telefono', $userCols, true);
$celColUser     = in_array('celular', $userCols, true) ? 'celular' : (in_array('whatsapp',$userCols,true) ? 'whatsapp' : null);
$hasDirUser     = in_array('direccion', $userCols, true);
$hasHorarioUser = in_array('horario', $userCols, true);
$hasNotasUser   = in_array('notas', $userCols, true);
$hasPlaca       = in_array('placa', $userCols, true);
$hasVehiculo    = in_array('vehiculo', $userCols, true);
$hasLicencia    = in_array('licencia_conduccion', $userCols, true);
$hasRutaZona    = in_array('ruta_zona', $userCols, true);
$emailCol       = in_array('email', $userCols, true) ? 'email' : (in_array('correo',$userCols,true) ? 'correo' : null);

// cargar perfil actual
$fields = ['id','nombre'];
if ($emailCol)       $fields[] = $emailCol;
if ($hasAvatarUser)  $fields[] = 'avatar';
if ($hasTelUser)     $fields[] = 'telefono';
if ($celColUser)     $fields[] = $celColUser;
if ($hasDirUser)     $fields[] = 'direccion';
if ($hasHorarioUser) $fields[] = 'horario';
if ($hasNotasUser)   $fields[] = 'notas';
if ($hasPlaca)       $fields[] = 'placa';
if ($hasVehiculo)    $fields[] = 'vehiculo';
if ($hasLicencia)    $fields[] = 'licencia_conduccion';
if ($hasRutaZona)    $fields[] = 'ruta_zona';

$list = implode(',', array_map(fn($c)=>"`$c`", $fields));
$st   = $conn->prepare("SELECT $list FROM usuarios WHERE id=? LIMIT 1");
$st->execute([$YO]);
$perfil = $st->fetch(PDO::FETCH_ASSOC) ?: [];
$avatarUrl = $hasAvatarUser ? resolveAvatarUrl($perfil['avatar'] ?? null) : null;
$avatarIni = initials($nombre);

/* ========== Acciones ========== */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!hash_equals($CSRF, $_POST['csrf'] ?? '')) {
    $_SESSION['flash'] = "❌ Sesión inválida. Intenta de nuevo.";
    header("Location: distribuidor.php");
    exit;
  }

  $action = $_POST['action'] ?? '';

  if ($action==='claim'){
    $venta_id = (int)($_POST['venta_id'] ?? 0);
    if ($venta_id && $hasRepartidorId && $repartidorCol){
      try{
        $sql = "UPDATE ventas SET $repartidorCol = ?"
             . ($hasEstado ? ", estado_entrega = 'En camino'":"")
             . " WHERE id=? "
             . ($hasEstado ? "AND estado_entrega='Pendiente' ":"")
             . "AND ($repartidorCol IS NULL OR $repartidorCol=0)";
        $conn->prepare($sql)->execute([$YO,$venta_id]);
        $_SESSION['flash']="✅ Pedido tomado.";
      }catch(Throwable $e){ $_SESSION['flash']="❌ No se pudo tomar: ".$e->getMessage(); }
    } else {
      try{
        $obs = 'Tomado por distribuidor: '.($_SESSION['nombre'] ?? "ID $YO");
        $conn->prepare("UPDATE ventas SET estado_entrega='En camino', observaciones=CONCAT(COALESCE(observaciones,''), CASE WHEN COALESCE(observaciones,'')='' THEN '' ELSE ' | ' END, ?) WHERE id=?")
             ->execute([$obs,$venta_id]);
        $_SESSION['flash']="✅ Pedido puesto En camino.";
      }catch(Throwable $e){ $_SESSION['flash']="❌ No se pudo actualizar: ".$e->getMessage(); }
    }
    header("Location: distribuidor.php"); exit;
  }

  if ($action==='update'){
    $venta_id = (int)($_POST['venta_id'] ?? 0);
    $estado   = $_POST['estado'] ?? '';
    $obs      = trim($_POST['observaciones'] ?? '');
    if ($venta_id){
      try{
        $sets=[]; $params=[];
        if ($estado==='Incidencia' && !$canIncidencia) {
          $estado = 'En camino';
          if ($obs!=='') $obs = 'INCIDENCIA: '.$obs;
        }
        if ($hasEstado && ($estado==='Pendiente' || $estado==='En camino' || $estado==='Entregado' || ($canIncidencia && $estado==='Incidencia'))){
          $sets[]="estado_entrega=?"; $params[]=$estado;
        }
        if ($obs!==''){
          if (in_array('observaciones',$ventasCols,true)){
            $sets[]="observaciones = CONCAT(COALESCE(observaciones,''), CASE WHEN COALESCE(observaciones,'')='' THEN '' ELSE ' | ' END, ?)";
            $params[]=$obs;
          }
        }
        if ($hasRepartidorId && $repartidorCol){ $sets[]="$repartidorCol=?"; $params[]=$YO; }
        if ($sets){
          $sql="UPDATE ventas SET ".implode(',',$sets)." WHERE id=?"; $params[]=$venta_id;
          $conn->prepare($sql)->execute($params);
          $_SESSION['flash']="✅ Actualizado.";
        } else {
          $_SESSION['flash']="⚠️ Nada que actualizar.";
        }
      }catch(Throwable $e){ $_SESSION['flash']="❌ Error: ".$e->getMessage(); }
    }
    header("Location: distribuidor.php"); exit;
  }

  if ($action==='quick_profile'){
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
      if ($hasDirUser) {
        $dir = trim($_POST['direccion'] ?? '');
        $updates[]="direccion=?"; $params[] = ($dir===''?null:$dir);
      }
      if ($hasHorarioUser) {
        $hor = trim($_POST['horario'] ?? '');
        $updates[]="horario=?"; $params[] = ($hor===''?null:$hor);
      }
      if ($hasNotasUser) {
        $not = trim($_POST['notas'] ?? '');
        $updates[]="notas=?"; $params[] = ($not===''?null:$not);
      }
      if ($hasPlaca) {
        $pla = strtoupper(trim($_POST['placa'] ?? ''));
        $updates[]="placa=?"; $params[] = ($pla===''?null:$pla);
      }
      if ($hasVehiculo) {
        $veh = trim($_POST['vehiculo'] ?? '');
        $updates[]="vehiculo=?"; $params[] = ($veh===''?null:$veh);
      }
      if ($hasLicencia) {
        $lic = strtoupper(trim($_POST['licencia_conduccion'] ?? ''));
        $updates[]="licencia_conduccion=?"; $params[] = ($lic===''?null:$lic);
      }
      if ($hasRutaZona) {
        $rz = trim($_POST['ruta_zona'] ?? '');
        $updates[]="ruta_zona=?"; $params[] = ($rz===''?null:$rz);
      }

      // Avatar
      if ($hasAvatarUser && isset($_FILES['avatar']) && $_FILES['avatar']['error']!==UPLOAD_ERR_NO_FILE) {
        if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) throw new Exception('Error al subir la imagen.');
        if ($_FILES['avatar']['size'] > 2*1024*1024) throw new Exception('La imagen supera 2 MB.');
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','webp'],true)) throw new Exception('Formato no permitido (jpg, png, webp).');
        if (!is_dir(__DIR__.'/../uploads')) @mkdir(__DIR__.'/../uploads',0775,true);
        $fname = 'driver_avatar_'.$YO.'_'.time().'.'.$ext;
        $dest = __DIR__.'/../uploads/'.$fname;
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'],$dest)) throw new Exception('No se pudo guardar la imagen.');
        $updates[]='avatar=?'; $params[]=$fname;
      }

      if ($updates){
        $params[]=$YO;
        $sql="UPDATE usuarios SET ".implode(', ',$updates)." WHERE id=?";
        $conn->prepare($sql)->execute($params);
      }
      $_SESSION['flash'] = '✅ Datos de perfil actualizados.';
    }catch(Throwable $e){
      $_SESSION['flash'] = '❌ '.$e->getMessage();
    }
    header("Location: distribuidor.php"); exit;
  }
}

/* ========== Filtros & paginación ========== */
function paginar(PDO $c, string $base, array $params, int $page, int $per): array {
  $stmt=$c->prepare("SELECT COUNT(*) FROM ($base) T"); $stmt->execute($params);
  $total=(int)$stmt->fetchColumn();
  $pages = max(1,(int)ceil($total/$per)); $page=max(1,min($page,$pages));
  $off = ($page-1)*$per;
  $sql = $base." LIMIT $per OFFSET $off";
  $st=$c->prepare($sql); $st->execute($params); $rows=$st->fetchAll(PDO::FETCH_ASSOC);
  return [$rows,$total,$pages,$page];
}

// GET filtros
$q        = trim($_GET['q']   ?? '');
$estadoF  = trim($_GET['est'] ?? '');
$desde    = trim($_GET['d1']  ?? '');
$hasta    = trim($_GET['d2']  ?? '');
$per      = max(5,min(50,(int)($_GET['pp'] ?? 10)));
$cp       = max(1,(int)($_GET['cp'] ?? 1)); // page para tomar
$mp       = max(1,(int)($_GET['mp'] ?? 1)); // page mis

// columnas comunes
$selCommon = "v.id, v.fecha, u.nombre AS cliente, ".($phoneCol ? "u.$phoneCol AS telefono," : "'' AS telefono,")."
             p.nombre AS producto, v.cantidad, ".($hasEstado ? "v.estado_entrega" : "NULL AS estado_entrega").",
             COALESCE(v.observaciones,'') AS observaciones
             $selectDir";
$joinCommon = " JOIN usuarios u ON v.cliente_id=u.id JOIN productos p ON v.producto_id=p.id $joinDir";

// --- PARA TOMAR ---
$whereClaim=[]; $pClaim=[];
if ($hasEstado) $whereClaim[] = "v.estado_entrega='Pendiente'";
if ($q!==''){ $whereClaim[]="(u.nombre LIKE ? OR p.nombre LIKE ?)"; $pClaim[]="%$q%"; $pClaim[]="%$q%"; }
if ($desde!==''){ $whereClaim[]="DATE(v.fecha)>=?"; $pClaim[]=$desde; }
if ($hasta!==''){ $whereClaim[]="DATE(v.fecha)<=?"; $pClaim[]=$hasta; }

$sqlClaimBase = "SELECT $selCommon FROM ventas v $joinCommon";
if ($whereClaim) $sqlClaimBase .= " WHERE ".implode(" AND ",$whereClaim);
$sqlClaimBase .= " ORDER BY v.fecha DESC";
[$claim,$tc,$pc,$cp] = paginar($conn,$sqlClaimBase,$pClaim,$cp,$per);

// --- MIS ENTREGAS ---
$whereMine=[]; $pMine=[];
if ($hasRepartidorId && $repartidorCol) $whereMine[] = "v.$repartidorCol = $YO";
if ($hasEstado){
  if ($estadoF!=='') $whereMine[]="v.estado_entrega=?";
  else {
    $in = $canIncidencia ? "'Pendiente','En camino','Incidencia'" : "'Pendiente','En camino'";
    $whereMine[]="v.estado_entrega IN ($in)";
  }
  if ($estadoF!=='') $pMine[]=$estadoF;
}
if ($q!==''){
  $whereMine[]="(u.nombre LIKE ? OR p.nombre LIKE ?".($hasDireccionId && $dirPick ? " OR CONCAT_WS(' ', ".implode(',',array_map(fn($x)=>substr($x,2),$dirPick)).") LIKE ?" : "").")";
  $pMine[]="%$q%"; $pMine[]="%$q%"; if ($hasDireccionId && $dirPick) $pMine[]="%$q%";
}
if ($desde!==''){ $whereMine[]="DATE(v.fecha)>=?"; $pMine[]=$desde; }
if ($hasta!==''){ $whereMine[]="DATE(v.fecha)<=?"; $pMine[]=$hasta; }

$sqlMineBase = "SELECT $selCommon FROM ventas v $joinCommon";
if ($whereMine) $sqlMineBase .= " WHERE ".implode(" AND ",$whereMine);
$sqlMineBase .= " ORDER BY v.fecha DESC";
[$mine,$tm,$pm,$mp] = paginar($conn,$sqlMineBase,$pMine,$mp,$per);

// KPIs
$cntTomar = $tc;
$cntPend  = 0; $cntCamino=0;
if ($hasEstado){
  foreach ($mine as $r){
    if ($r['estado_entrega']==='Pendiente') $cntPend++;
    if ($r['estado_entrega']==='En camino') $cntCamino++;
  }
} else { $cntPend = $tm; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Distribuidor — FarmaSalud</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css"/>
<style>
:root{ --fs-border:#e8edf3; --fs-surface:#fff; }
.card-kpi{ border:1px solid var(--fs-border); border-radius:16px; padding:16px; background:var(--fs-surface); }
.badge-state{ font-size:.85rem; }
.addr{ max-width: 420px; }
.delivery-card{
  border:1px solid var(--fs-border); border-radius:14px; background:#fff; padding:12px; margin-bottom:12px;
  box-shadow: 0 6px 16px rgba(2,6,23,.06);
}
.mapbox{ height:220px; border-radius:12px; overflow:hidden; }
.table td, .table th{ vertical-align: middle; }
/* Avatar */
.avatar { width:36px; height:36px; border-radius:50%; overflow:hidden; border:2px solid rgba(0,0,0,.08); display:grid; place-items:center; background:#0f172a; color:#fff; font-weight:700; cursor:pointer; }
.avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
</style>
</head>
<body class="bg-light">

<div class="container py-4">

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h2 class="mb-2 mb-sm-0">🚛 Panel del Distribuidor</h2>
    <div class="d-flex align-items-center gap-2">
      <span class="text-muted small d-none d-sm-inline">Hola, <strong><?= h($nombre) ?></strong></span>

      <!-- Avatar abre offcanvas -->
      <a class="avatar" data-bs-toggle="offcanvas" href="#offPerfilDistribuidor" role="button" aria-controls="offPerfilDistribuidor" title="Mi información">
        <?php if ($avatarUrl): ?>
          <img src="<?= h($avatarUrl) ?>" alt="Avatar de <?= h($nombre) ?>">
        <?php else: ?>
          <span><?= h($avatarIni) ?></span>
        <?php endif; ?>
      </a>

      <a href="ver_ventas.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-card-checklist"></i> Ver entregas</a>
      <a href="logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
    </div>
  </div>

  <p class="text-muted">Hola, <strong><?= h($_SESSION['nombre'] ?? 'Distribuidor') ?></strong>. Toma pedidos, inicia ruta, entrega o registra incidencias.</p>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-info"><?= $_SESSION['flash']; unset($_SESSION['flash']); ?></div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="card-kpi text-center"><div class="text-secondary">Para tomar</div><div class="display-6"><?= $cntTomar ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card-kpi text-center"><div class="text-secondary">Mis pendientes</div><div class="display-6"><?= $cntPend ?></div></div></div>
    <div class="col-6 col-md-3"><div class="card-kpi text-center"><div class="text-secondary">En camino</div><div class="display-6"><?= $cntCamino ?></div></div></div>
  </div>

  <!-- FILTROS -->
  <form class="row g-2 align-items-end mb-3">
    <div class="col-12 col-md-3">
      <label class="form-label">Buscar (cliente / producto / dirección)</label>
      <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Ej: Juan o Ibuprofeno">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Estado</label>
      <select name="est" class="form-select">
        <option value="">(Mis entregas) — Todos</option>
        <option value="Pendiente"  <?= $estadoF==='Pendiente'?'selected':'' ?>>Pendiente</option>
        <option value="En camino"  <?= $estadoF==='En camino'?'selected':'' ?>>En camino</option>
        <option value="Entregado"  <?= $estadoF==='Entregado'?'selected':'' ?>>Entregado</option>
        <?php if ($canIncidencia): ?>
          <option value="Incidencia" <?= $estadoF==='Incidencia'?'selected':'' ?>>Incidencia</option>
        <?php endif; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Desde</label>
      <input type="date" name="d1" value="<?= h($desde) ?>" class="form-control">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Hasta</label>
      <input type="date" name="d2" value="<?= h($hasta) ?>" class="form-control">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label">Por página</label>
      <select name="pp" class="form-select">
        <?php foreach([5,10,15,20,30,50] as $n): ?>
          <option value="<?= $n ?>" <?= $per===$n?'selected':'' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-1 d-grid">
      <button class="btn btn-primary"><i class="bi bi-funnel"></i> Aplicar</button>
    </div>
  </form>

  <!-- PARA TOMAR -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
      <strong>🧾 Entregas para tomar</strong>
      <?php if (!$hasRepartidorId): ?>
        <span class="badge bg-info ms-2">Sin asignación</span>
        <span class="text-muted small">Tu tabla <code>ventas</code> no tiene <code>repartidor_id</code>/<code>distribuidor_id</code>. Igualmente puedes pasar pedidos a <b>En camino</b>.</span>
      <?php endif; ?>
    </div>
    <div class="card-body p-2">
      <?php if (!$claim): ?>
        <div class="p-3 text-muted">No hay pedidos disponibles.</div>
      <?php else: ?>

        <!-- móvil (cards) -->
        <div class="d-md-none">
          <?php foreach ($claim as $r):
            $addr=dirLabel($r); $maps=$addr?'https://www.google.com/maps/search/?api=1&query='.urlencode($addr):''; $phone=trim((string)($r['telefono']??'')); $lat=$r['_lat']??null; $lng=$r['_lng']??null; ?>
            <div class="delivery-card" data-venta-id="<?= (int)$r['id'] ?>">
              <div class="d-flex justify-content-between">
                <div>
                  <div class="fw-bold"><?= h($r['cliente']) ?></div>
                  <div class="small text-muted"><?= h($r['fecha']) ?></div>
                </div>
                <span class="badge badge-state bg-warning"><?= $hasEstado ? h($r['estado_entrega']) : '—' ?></span>
              </div>
              <div class="mt-1"><i class="bi bi-box"></i> <?= h($r['producto']) ?> · x<?= (int)$r['cantidad'] ?></div>
              <div class="mt-1 addr"><i class="bi bi-geo-alt"></i> <?= $addr? h($addr):'<span class="text-muted">—</span>' ?></div>

              <?php if ($lat && $lng): ?>
                <div class="mapbox mt-2"
                     data-map data-venta="<?= (int)$r['id'] ?>" data-dir-id="<?= (int)($r['dir_id']??0) ?>"
                     data-lat="<?= h($lat) ?>" data-lng="<?= h($lng) ?>"></div>
                <div class="mt-1"><span class="badge bg-secondary me-1" data-dist>—</span><span class="badge bg-secondary" data-eta>—</span></div>
              <?php endif; ?>

              <div class="mt-2 d-flex flex-wrap gap-2">
                <?php if ($phone): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="tel:<?= h($phone) ?>"><i class="bi bi-telephone"></i></a>
                  <a class="btn btn-sm btn-outline-success" target="_blank" href="https://wa.me/<?= preg_replace('/\D/','',$phone) ?>"><i class="bi bi-whatsapp"></i></a>
                <?php endif; ?>
                <?php if ($maps): ?>
                  <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= h($maps) ?>"><i class="bi bi-map"></i> Mapa</a>
                <?php endif; ?>
                <?php if ($hasRepartidorId): ?>
                  <form method="POST" class="ms-auto">
                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="action" value="claim">
                    <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-primary"><i class="bi bi-hand-index-thumb"></i> Tomar</button>
                  </form>
                <?php endif; ?>
                <form method="POST">
                  <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="estado" value="En camino">
                  <button class="btn btn-sm btn-outline-primary"><i class="bi bi-truck"></i> En camino</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- desktop (tabla) -->
        <div class="table-responsive d-none d-md-block">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Fecha</th><th>Cliente</th><th>Producto</th><th class="text-center">Cant.</th><th>Contacto</th><th>Dirección</th><th>Estado</th><th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($claim as $r):
                $addr=dirLabel($r); $maps=$addr?'https://www.google.com/maps/search/?api=1&query='.urlencode($addr):''; $phone=trim((string)($r['telefono']??'')); $lat=$r['_lat']??null; $lng=$r['_lng']??null; ?>
              <tr data-venta-id="<?= (int)$r['id'] ?>">
                <td><?= h($r['fecha']) ?></td>
                <td><?= h($r['cliente']) ?></td>
                <td><?= h($r['producto']) ?></td>
                <td class="text-center"><?= (int)$r['cantidad'] ?></td>
                <td>
                  <?php if ($phone): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="tel:<?= h($phone) ?>"><i class="bi bi-telephone"></i></a>
                    <a class="btn btn-sm btn-outline-success" target="_blank" href="https://wa.me/<?= preg_replace('/\D/','',$phone) ?>"><i class="bi bi-whatsapp"></i></a>
                    <div class="small text-muted"><?= h($phone) ?></div>
                  <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </td>
                <td class="addr" data-dir-id="<?= (int)($r['dir_id']??0) ?>">
                  <?php if ($addr): ?>
                    <div class="small"><?= h($addr) ?></div>
                    <div class="d-flex gap-2 mt-1">
                      <?php if ($maps): ?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= h($maps) ?>"><i class="bi bi-map"></i> Mapa</a><?php endif; ?>
                      <?php if ($lat && $lng): ?><button class="btn btn-sm btn-outline-secondary" type="button" data-toggle-map data-lat="<?= h($lat) ?>" data-lng="<?= h($lng) ?>">Ver mini-mapa</button><?php endif; ?>
                    </div>
                    <?php if ($lat && $lng): ?>
                      <div class="mapbox mt-2 d-none"></div>
                      <div class="mt-1"><span class="badge bg-secondary me-1" data-dist>—</span><span class="badge bg-secondary" data-eta>—</span></div>
                    <?php endif; ?>
                  <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </td>
                <td><span class="badge badge-state bg-warning"><?= $hasEstado ? h($r['estado_entrega']) : '—' ?></span></td>
                <td class="text-end">
                  <div class="d-flex flex-wrap gap-1 justify-content-end">
                    <?php if ($hasRepartidorId): ?>
                      <form method="POST">
                        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                        <input type="hidden" name="action" value="claim">
                        <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-hand-index-thumb"></i> Tomar</button>
                      </form>
                    <?php endif; ?>
                    <form method="POST">
                      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="estado" value="En camino">
                      <button class="btn btn-sm btn-outline-primary"><i class="bi bi-truck"></i> En camino</button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Paginación para tomar -->
        <nav class="mt-3">
          <ul class="pagination pagination-sm">
            <?php $qs = $_GET; unset($qs['cp']); $base='?'.http_build_query($qs);
              for($i=1;$i<=$pc;$i++): ?>
              <li class="page-item <?= $i===$cp?'active':'' ?>"><a class="page-link" href="<?= $base.'&cp='.$i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
          </ul>
        </nav>

      <?php endif; ?>
    </div>
  </div>

  <!-- MIS ENTREGAS -->
  <div class="card shadow-sm">
    <div class="card-header bg-light"><strong>📦 Mis entregas</strong></div>
    <div class="card-body p-2">

      <?php if (!$mine): ?>
        <div class="p-3 text-muted">No tienes entregas en curso con los filtros actuales.</div>
      <?php else: ?>

        <!-- móvil (cards) -->
        <div class="d-md-none">
          <?php foreach ($mine as $r):
            $addr=dirLabel($r); $maps=$addr?'https://www.google.com/maps/search/?api=1&query='.urlencode($addr):''; $phone=trim((string)($r['telefono']??'')); $lat=$r['_lat']??null; $lng=$r['_lng']??null; ?>
            <div class="delivery-card" data-venta-id="<?= (int)$r['id'] ?>">
              <div class="d-flex justify-content-between">
                <div><div class="fw-bold"><?= h($r['cliente']) ?></div><div class="small text-muted"><?= h($r['fecha']) ?></div></div>
                <span class="badge badge-state <?= ($r['estado_entrega']??'')==='En camino'?'bg-info':'bg-warning' ?>"><?= $hasEstado? h($r['estado_entrega']) : '—' ?></span>
              </div>
              <div class="mt-1"><i class="bi bi-box"></i> <?= h($r['producto']) ?> · x<?= (int)$r['cantidad'] ?></div>
              <div class="mt-1 addr"><i class="bi bi-geo-alt"></i> <?= $addr? h($addr):'<span class="text-muted">—</span>' ?></div>

              <?php if ($lat && $lng): ?>
                <div class="mapbox mt-2" data-map data-venta="<?= (int)$r['id'] ?>" data-dir-id="<?= (int)($r['dir_id']??0) ?>" data-lat="<?= h($lat) ?>" data-lng="<?= h($lng) ?>"></div>
                <div class="mt-1"><span class="badge bg-secondary me-1" data-dist>—</span><span class="badge bg-secondary" data-eta>—</span></div>
              <?php endif; ?>

              <div class="small text-muted mt-1"><?= nl2br(h($r['observaciones'] ?? '')) ?></div>

              <div class="mt-2 d-flex flex-wrap gap-2">
                <?php if ($phone): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="tel:<?= h($phone) ?>"><i class="bi bi-telephone"></i></a>
                  <a class="btn btn-sm btn-outline-success" target="_blank" href="https://wa.me/<?= preg_replace('/\D/','',$phone) ?>"><i class="bi bi-whatsapp"></i></a>
                <?php endif; ?>
                <?php if ($maps): ?>
                  <a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= h($maps) ?>"><i class="bi bi-map"></i> Mapa</a>
                  <a class="btn btn-sm btn-outline-dark" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($addr) ?>"><i class="bi bi-compass"></i> Navegar</a>
                <?php endif; ?>

                <form method="POST" class="ms-auto">
                  <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="estado" value="En camino">
                  <button class="btn btn-sm btn-outline-primary"><i class="bi bi-truck"></i></button>
                </form>
                <form method="POST">
                  <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="estado" value="Entregado">
                  <button class="btn btn-sm btn-success"><i class="bi bi-box-seam"></i></button>
                </form>

                <?php if ($canIncidencia): ?>
                <details class="w-100">
                  <summary class="btn btn-sm btn-outline-danger mt-1">Incidencia</summary>
                  <form method="POST" class="mt-2">
                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="estado" value="Incidencia">
                    <textarea name="observaciones" rows="2" class="form-control mb-1" placeholder="Cliente ausente, dirección errónea..." required></textarea>
                    <button class="btn btn-sm btn-danger w-100">Guardar</button>
                  </form>
                </details>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- desktop (tabla) -->
        <div class="table-responsive d-none d-md-block">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Fecha</th><th>Cliente</th><th>Producto</th><th class="text-center">Cant.</th><th>Contacto</th><th>Dirección</th><th>Estado</th><th>Observaciones</th><th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($mine as $r):
              $addr=dirLabel($r); $maps=$addr?'https://www.google.com/maps/search/?api=1&query='.urlencode($addr):''; $phone=trim((string)($r['telefono']??'')); $lat=$r['_lat']??null; $lng=$r['_lng']??null; ?>
              <tr data-venta-id="<?= (int)$r['id'] ?>">
                <td><?= h($r['fecha']) ?></td>
                <td><?= h($r['cliente']) ?></td>
                <td><?= h($r['producto']) ?></td>
                <td class="text-center"><?= (int)$r['cantidad'] ?></td>
                <td>
                  <?php if ($phone): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="tel:<?= h($phone) ?>"><i class="bi bi-telephone"></i></a>
                    <a class="btn btn-sm btn-outline-success" target="_blank" href="https://wa.me/<?= preg_replace('/\D/','',$phone) ?>"><i class="bi bi-whatsapp"></i></a>
                    <div class="small text-muted"><?= h($phone) ?></div>
                  <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </td>
                <td class="addr" data-dir-id="<?= (int)($r['dir_id']??0) ?>">
                  <?php if ($addr): ?>
                    <div class="small"><?= h($addr) ?></div>
                    <div class="d-flex gap-2 mt-1">
                      <?php if ($maps): ?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= h($maps) ?>"><i class="bi bi-map"></i> Mapa</a><?php endif; ?>
                      <?php if ($lat && $lng): ?><button class="btn btn-sm btn-outline-secondary" type="button" data-toggle-map data-lat="<?= h($lat) ?>" data-lng="<?= h($lng) ?>">Ver mini-mapa</button><?php endif; ?>
                      <?php if ($maps): ?><a class="btn btn-sm btn-outline-dark" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($addr) ?>"><i class="bi bi-compass"></i> Navegar</a><?php endif; ?>
                    </div>
                    <?php if ($lat && $lng): ?>
                      <div class="mapbox mt-2 d-none"></div>
                      <div class="mt-1"><span class="badge bg-secondary me-1" data-dist>—</span><span class="badge bg-secondary" data-eta>—</span></div>
                    <?php endif; ?>
                  <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </td>
                <td><span class="badge badge-state <?= ($r['estado_entrega']??'')==='En camino'?'bg-info':'bg-warning' ?>"><?= $hasEstado? h($r['estado_entrega']) : '—' ?></span></td>
                <td style="max-width:240px"><?= nl2br(h($r['observaciones'] ?? '')) ?></td>
                <td class="text-end">
                  <div class="d-flex flex-wrap gap-1 justify-content-end">
                    <form method="POST">
                      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="estado" value="En camino">
                      <button class="btn btn-sm btn-outline-primary"><i class="bi bi-truck"></i> En camino</button>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="estado" value="Entregado">
                      <button class="btn btn-sm btn-success"><i class="bi bi-box-seam"></i> Entregado</button>
                    </form>

                    <?php if ($canIncidencia): ?>
                    <details>
                      <summary class="btn btn-sm btn-outline-danger">Incidencia</summary>
                      <form method="POST" class="mt-2">
                        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="venta_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="estado" value="Incidencia">
                        <textarea name="observaciones" rows="2" class="form-control mb-1" placeholder="Cliente ausente, dirección errónea..." required></textarea>
                        <button class="btn btn-sm btn-danger w-100">Guardar</button>
                      </form>
                    </details>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Paginación mis entregas -->
        <nav class="mt-3">
          <ul class="pagination pagination-sm">
            <?php $qs = $_GET; unset($qs['mp']); $base='?'.http_build_query($qs);
              for($i=1;$i<=$pm;$i++): ?>
              <li class="page-item <?= $i===$mp?'active':'' ?>"><a class="page-link" href="<?= $base.'&mp='.$i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
          </ul>
        </nav>

      <?php endif; ?>
    </div>
  </div>

</div>

<!-- OFFCANVAS Perfil Distribuidor -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offPerfilDistribuidor" aria-labelledby="offPerfilDistribuidorLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="offPerfilDistribuidorLabel"><i class="bi bi-person-gear"></i> Mi información</h5>
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

      <?php if ($hasDirUser): ?>
      <div class="mb-2">
        <label class="form-label">Dirección</label>
        <input type="text" name="direccion" class="form-control" value="<?= h($perfil['direccion'] ?? '') ?>">
      </div>
      <?php endif; ?>

      <?php if ($hasHorarioUser): ?>
      <div class="mb-2">
        <label class="form-label">Horario</label>
        <input type="text" name="horario" class="form-control" value="<?= h($perfil['horario'] ?? '') ?>" placeholder="L–V 8:00–17:00">
      </div>
      <?php endif; ?>

      <?php if ($hasPlaca): ?>
      <div class="mb-2">
        <label class="form-label">Placa vehículo</label>
        <input type="text" name="placa" class="form-control" value="<?= h($perfil['placa'] ?? '') ?>" placeholder="ABC123">
      </div>
      <?php endif; ?>

      <?php if ($hasVehiculo): ?>
      <div class="mb-2">
        <label class="form-label">Tipo de vehículo</label>
        <input type="text" name="vehiculo" class="form-control" value="<?= h($perfil['vehiculo'] ?? '') ?>" placeholder="Moto, automóvil, camioneta…">
      </div>
      <?php endif; ?>

      <?php if ($hasLicencia): ?>
      <div class="mb-2">
        <label class="form-label">Licencia de conducción</label>
        <input type="text" name="licencia_conduccion" class="form-control" value="<?= h($perfil['licencia_conduccion'] ?? '') ?>" placeholder="B1, C1…">
      </div>
      <?php endif; ?>

      <?php if ($hasRutaZona): ?>
      <div class="mb-2">
        <label class="form-label">Ruta / zona</label>
        <input type="text" name="ruta_zona" class="form-control" value="<?= h($perfil['ruta_zona'] ?? '') ?>" placeholder="Norte, Centro, Ruta 3…">
      </div>
      <?php endif; ?>

      <?php if ($hasNotasUser): ?>
      <div class="mb-3">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="2" placeholder="Observaciones de reparto, especificaciones del vehículo…"><?= h($perfil['notas'] ?? '') ?></textarea>
      </div>
      <?php endif; ?>

      <div class="d-grid gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ========= Preview avatar =========
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
      document.querySelector('#offPerfilDistribuidor .avatar').appendChild(prev);
    }
    prev.src = url;
  });
}

// ========= Geocoding y routing =========
async function geocode(addr){
  const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='+encodeURIComponent(addr);
  const r = await fetch(url, {headers:{'Accept-Language':'es'}});
  if (!r.ok) throw new Error('geo http');
  const j = await r.json();
  if (!j || !j[0]) throw new Error('No se pudo geocodificar');
  return {lat: parseFloat(j[0].lat), lng: parseFloat(j[0].lon)};
}
function mountLiveMap(container, dest, ventaId){
  const map = L.map(container,{ zoomControl:false }).setView([dest.lat, dest.lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);
  const dstMarker = L.marker([dest.lat, dest.lng]).addTo(map);
  let meMarker = null;

  const router = L.Routing.osrmv1({ serviceUrl: 'https://router.project-osrm.org/route/v1' });
  const routing = L.Routing.control({
    router, addWaypoints:false, draggableWaypoints:false, fitSelectedRoutes:true, show:false
  }).addTo(map);

  const $dist = container.parentElement.querySelector('[data-dist]');
  const $eta  = container.parentElement.querySelector('[data-eta]');

  routing.on('routesfound', e=>{
    const r = e.routes[0];
    if (!$dist || !$eta || !r) return;
    const km  = (r.summary.totalDistance/1000).toFixed(2);
    const min = Math.round(r.summary.totalTime/60);
    $dist.textContent = `${km} km`;
    $eta.textContent  = `${min} min`;
  });

  function setRouteFrom(lat, lng){
    const from = L.latLng(lat, lng);
    const to   = L.latLng(dest.lat, dest.lng);
    if (!meMarker) meMarker = L.marker(from, {opacity:0.9}).addTo(map);
    meMarker.setLatLng(from);
    routing.setWaypoints([from, to]);
  }

  if ('geolocation' in navigator){
    navigator.geolocation.watchPosition(pos=>{
      const c = pos.coords;
      setRouteFrom(c.latitude, c.longitude);
      fetch('ajax_track.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`venta_id=${encodeURIComponent(ventaId)}&lat=${c.latitude}&lng=${c.longitude}`+
             `&vel=${c.speed||''}&rum=${c.heading||''}&pre=${c.accuracy||''}`
      }).catch(()=>{});
    }, ()=>{}, {enableHighAccuracy:true, maximumAge:5000, timeout:10000});
  }

  setTimeout(()=> map.invalidateSize(), 250);
}

// Tarjetas móviles con [data-map]
document.querySelectorAll('[data-map]').forEach(async el=>{
  let lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
  const ventaId = parseInt(el.dataset.venta||'0',10);
  const addrEl  = el.closest('.delivery-card')?.querySelector('.addr');
  const addr    = addrEl ? addrEl.textContent.replace(/^📍\s*/,'').trim() : '';
  if (!(lat && lng) && addr){
    try{ const geo = await geocode(addr); lat=geo.lat; lng=geo.lng; }catch(_){}
  }
  if (lat && lng) mountLiveMap(el, {lat,lng}, ventaId);
});

// Tabla desktop: botón “Ver mini-mapa”
document.querySelectorAll('[data-toggle-map]').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const td   = btn.closest('td.addr');
    const row  = btn.closest('tr');
    const box  = td.querySelector('.mapbox');
    if (!box) return;

    let lat = parseFloat(btn.dataset.lat), lng = parseFloat(btn.dataset.lng);
    const addrTxt = td.querySelector('.small')?.textContent.trim() || '';

    if (!(lat && lng) && addrTxt){
      try{ const geo = await geocode(addrTxt); lat=geo.lat; lng=geo.lng; btn.dataset.lat=lat; btn.dataset.lng=lng; }catch(_){}
    }

    box.classList.toggle('d-none');
    if (!box.dataset.mounted && lat && lng){
      const ventaId = parseInt(row.dataset.ventaId||'0',10);
      mountLiveMap(box, {lat,lng}, ventaId);
      box.dataset.mounted = '1';
    }
  });
});
</script>
</body>
</html>








