<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || !in_array((int)($_SESSION['rol_id'] ?? 0), [1,4])) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function tableCols(PDO $conn, string $table): array {
  try {
    $cols = [];
    foreach ($conn->query("SHOW COLUMNS FROM `{$table}`") as $r) $cols[] = $r['Field'];
    return $cols;
  } catch(Throwable $e){ return []; }
}

$mensaje = '';
$detalleExito = null;

/* =========================
   CARGA INICIAL DE DATOS
   ========================= */
$productos = $conn->query("SELECT id, nombre, precio, stock FROM productos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$clientes  = $conn->query("SELECT id, nombre, correo, telefono, direccion, ciudad, departamento, codigo_postal FROM usuarios WHERE rol_id = 2 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

/* Mapa de direcciones por usuario (desde tabla direcciones) */
$dirCols = tableCols($conn, 'direcciones');
$direcciones = []; // [usuario_id] => [ {id,label}, ... ]
if ($dirCols) {
  $dirs = $conn->query("SELECT * FROM direcciones ORDER BY usuario_id, es_predeterminada DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($dirs as $d) {
    $uid = (int)($d['usuario_id'] ?? 0);
    $id  = (int)($d['id'] ?? 0);
    if (!$uid || !$id) continue;

    // Etiqueta tolerante a diferentes esquemas
    $label = '';
    if (array_key_exists('direccion', $d) && $d['direccion']) {
      $label = $d['direccion'];
    } else {
      $parts = [];
      // esquema "líneas"
      foreach (['linea1','linea2'] as $k) { if (!empty($d[$k])) $parts[] = $d[$k]; }
      // comunes
      foreach (['ciudad','departamento','estado','codigo_postal','cp','pais'] as $k) { if (!empty($d[$k])) $parts[] = $d[$k]; }
      $label = $parts ? implode(', ', $parts) : ('Dirección #'.$id);
      if (!empty($d['referencias'])) $label .= ' — '.$d['referencias'];
      if (!empty($d['referencia']))  $label .= ' — '.$d['referencia']; // por si existe singular
    }
    if (!empty($d['alias'])) $label = $d['alias'].' — '.$label;
    if (!isset($direcciones[$uid])) $direcciones[$uid] = [];
    $direcciones[$uid][] = ['id'=>$id, 'label'=>$label];
  }
}

/* Fallback: si un cliente NO tiene filas en direcciones, ofrecer "Usar datos de perfil" (id=0) */
foreach ($clientes as $c) {
  $uid = (int)$c['id'];
  $parts = array_filter([$c['direccion'] ?? null, $c['ciudad'] ?? null, $c['departamento'] ?? null, $c['codigo_postal'] ?? null]);
  $txt   = implode(', ', $parts);
  if ($txt) {
    if (empty($direcciones[$uid])) $direcciones[$uid] = [];
    // Evitar duplicar si ya hay algo cargado
    $direcciones[$uid][] = ['id'=>0, 'label'=>'Usar datos de perfil: '.$txt];
  }
}

/* =========================
   POST
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $cliente_id   = (int)($_POST['cliente_id'] ?? 0);
  $producto_id  = (int)($_POST['producto_id'] ?? 0);
  $cantidad     = (int)($_POST['cantidad'] ?? 0);
  $entrega      = $_POST['entrega'] ?? '';
  $direccion_id = isset($_POST['direccion_id']) ? (int)$_POST['direccion_id'] : 0;

  // Campos para "agregar nueva dirección"
  $add_dir   = ($_POST['add_dir'] ?? '0') === '1';
  $n_calle   = trim($_POST['n_calle'] ?? '');
  $n_numero  = trim($_POST['n_numero'] ?? '');
  $n_ciudad  = trim($_POST['n_ciudad'] ?? '');
  $n_estado  = trim($_POST['n_estado'] ?? '');
  $n_cp      = trim($_POST['n_cp'] ?? '');
  $n_pais    = trim($_POST['n_pais'] ?? '');
  $n_ref     = trim($_POST['n_ref'] ?? '');

  // Validación base
  if (!$cliente_id)              $mensaje = "❌ Debes seleccionar un cliente.";
  elseif (!$producto_id)         $mensaje = "❌ Debes seleccionar un producto.";
  elseif ($cantidad <= 0)        $mensaje = "❌ La cantidad debe ser mayor a cero.";
  elseif (!in_array($entrega, ['retiro','envio'], true)) {
                                 $mensaje = "❌ Debes seleccionar el tipo de entrega.";
  } elseif ($entrega === 'envio') {
    if ($add_dir) {
      $tieneDireccionNueva = ($n_calle || $n_ciudad || $n_pais);
      if (!$tieneDireccionNueva || !$n_calle || !$n_ciudad || !$n_pais) {
        $mensaje = "❌ Para crear una dirección nueva completa Calle, Ciudad y País.";
      }
    } else {
      // Permitimos seleccionar id=0 (perfil) o una id>0
      if ($direccion_id < 0) $mensaje = "❌ Selecciona una dirección válida para el envío.";
    }
  }

  // Producto/stock
  if (!$mensaje) {
    $stmt = $conn->prepare("SELECT nombre, precio, stock FROM productos WHERE id = ?");
    $stmt->execute([$producto_id]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prod) {
      $mensaje = "❌ El producto no existe.";
    } elseif ((int)$prod['stock'] < $cantidad) {
      $mensaje = "❌ Stock insuficiente. Disponible: ".(int)$prod['stock'];
    }
  }

  if (!$mensaje) {
    try {
      $conn->beginTransaction();

      /* 1) Si es ENVÍO y:
            a) add_dir=1 → crear nueva dirección con los campos del formulario
            b) direccion_id = 0 → crear una fila usando los datos del PERFIL (usuarios)
      */
      if ($entrega === 'envio' && $dirCols) {
        if ($add_dir) {
          // Construir columnas según existan
          $cols = ['usuario_id']; $params = [$cliente_id];

          // Preferencia 1: columna "direccion" de texto corrido
          if (in_array('direccion', $dirCols, true)) {
            $texto = trim($n_calle.' '.$n_numero.', '.$n_ciudad.', '.$n_estado.' '.$n_cp.', '.$n_pais.($n_ref ? ' — '.$n_ref : ''));
            $cols[]='direccion'; $params[]=$texto;
          }

          // Preferencia 2: esquema linea1/linea2/ciudad/...
          $linea1 = trim($n_calle.($n_numero?(' '.$n_numero):''));
          if (in_array('linea1', $dirCols, true)) { $cols[]='linea1'; $params[]=$linea1; }
          if (in_array('linea2', $dirCols, true)) { $cols[]='linea2'; $params[]=''; }
          if (in_array('ciudad', $dirCols, true)) { $cols[]='ciudad'; $params[]=$n_ciudad; }
          if (in_array('departamento', $dirCols, true)) { $cols[]='departamento'; $params[]=$n_estado; }
          if (in_array('codigo_postal', $dirCols, true)) { $cols[]='codigo_postal'; $params[]=$n_cp; }
          if (in_array('pais', $dirCols, true)) { $cols[]='pais'; $params[]=$n_pais; }
          if (in_array('referencias', $dirCols, true)) { $cols[]='referencias'; $params[]=$n_ref; }
          if (in_array('alias', $dirCols, true)) { $cols[]='alias'; $params[]='Nueva'; }
          if (in_array('es_predeterminada', $dirCols, true)) { $cols[]='es_predeterminada'; $params[] = 0; }

          $ph = rtrim(str_repeat('?,', count($params)), ',');
          $sqlIns = "INSERT INTO direcciones (".implode(',', $cols).") VALUES ($ph)";
          $conn->prepare($sqlIns)->execute($params);
          $direccion_id = (int)$conn->lastInsertId();

        } elseif ($direccion_id === 0) {
          // Crear a partir del PERFIL del usuario
          $us = $conn->prepare("SELECT nombre, telefono, direccion, ciudad, departamento, codigo_postal FROM usuarios WHERE id=?");
          $us->execute([$cliente_id]);
          $u = $us->fetch(PDO::FETCH_ASSOC);

          $perfilTxt = trim(implode(', ', array_filter([$u['direccion'] ?? '', $u['ciudad'] ?? '', $u['departamento'] ?? '', $u['codigo_postal'] ?? ''])));
          if (!$perfilTxt) { throw new RuntimeException('El cliente no tiene datos de dirección en el perfil.'); }

          $cols = ['usuario_id']; $params = [$cliente_id];

          if (in_array('direccion', $dirCols, true)) { $cols[]='direccion'; $params[]=$perfilTxt; }

          // También en formato por campos si existen
          if (in_array('linea1', $dirCols, true)) { $cols[]='linea1'; $params[] = $u['direccion'] ?? ''; }
          if (in_array('ciudad', $dirCols, true)) { $cols[]='ciudad'; $params[] = $u['ciudad'] ?? ''; }
          if (in_array('departamento', $dirCols, true)) { $cols[]='departamento'; $params[] = $u['departamento'] ?? ''; }
          if (in_array('codigo_postal', $dirCols, true)) { $cols[]='codigo_postal'; $params[] = $u['codigo_postal'] ?? ''; }
          if (in_array('alias', $dirCols, true)) { $cols[]='alias'; $params[]='Perfil'; }
          if (in_array('nombre_receptor', $dirCols, true)) { $cols[]='nombre_receptor'; $params[]=$u['nombre'] ?? ''; }
          if (in_array('telefono', $dirCols, true)) { $cols[]='telefono'; $params[]=$u['telefono'] ?? ''; }
          if (in_array('es_predeterminada', $dirCols, true)) { $cols[]='es_predeterminada'; $params[] = 1; }

          $ph = rtrim(str_repeat('?,', count($params)), ',');
          $sqlIns = "INSERT INTO direcciones (".implode(',', $cols).") VALUES ($ph)";
          $conn->prepare($sqlIns)->execute($params);
          $direccion_id = (int)$conn->lastInsertId();
        }
      }

      /* 2) Insertar venta (tolerante a columnas) */
      $ventasCols = tableCols($conn, 'ventas');
      $vCols = ['usuario_id','cliente_id','producto_id','cantidad','precio_unitario'];
      $vVals = [(int)$_SESSION['usuario_id'], $cliente_id, $producto_id, $cantidad, (float)$prod['precio']];

      if (in_array('estado_entrega',$ventasCols,true)) { $vCols[]='estado_entrega'; $vVals[]='Pendiente'; }
      if (in_array('tipo_entrega',$ventasCols,true))   { $vCols[]='tipo_entrega';   $vVals[]=$entrega; }
      if (in_array('observaciones',$ventasCols,true)) {
        $obs = ($entrega==='retiro') ? 'Retiro en tienda' : 'Envío a domicilio';
        $vCols[]='observaciones'; $vVals[]=$obs;
      }
      if (in_array('direccion_id',$ventasCols,true) && $entrega==='envio') {
        $vCols[]='direccion_id'; $vVals[]=$direccion_id ?: null;
      }

      $ph = rtrim(str_repeat('?,', count($vVals)), ',');
      $sqlV = "INSERT INTO ventas (".implode(',', $vCols).") VALUES ($ph)";
      $conn->prepare($sqlV)->execute($vVals);

      /* 3) Descontar stock */
      $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?")->execute([$cantidad, $producto_id]);

      $conn->commit();

      $detalleExito = [
        'producto'    => $prod['nombre'],
        'cantidad'    => $cantidad,
        'precio_unit' => $prod['precio'],
        'subtotal'    => $cantidad * (float)$prod['precio'],
        'entrega'     => $entrega,
        'direccion_id'=> $direccion_id
      ];
      $mensaje = "✅ Venta registrada correctamente.";
    } catch(Throwable $e){
      $conn->rollBack();
      $mensaje = "❌ Error al registrar la venta: ".$e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registrar Venta - FarmaSalud</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root{ --fs-primary:#0d6efd; --fs-border:#e8edf3; }
.navbar{ background:var(--fs-primary); }
.card{ border:1px solid var(--fs-border); border-radius:16px; }
.card-header{ background:#f8fafc; border-bottom:1px solid var(--fs-border); }
.btn-primary{ background:var(--fs-primary); border-color:var(--fs-primary); }
.small-dim{ color:#64748b; }
@media (min-width: 992px){ .sticky-summary{ position: sticky; top: 80px; } }
</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark px-3">
  <span class="navbar-brand">FarmaSalud</span>
  <div class="ms-auto">
    <a href="farmaceutico.php" class="btn btn-light btn-sm me-2"><i class="bi bi-house"></i> Panel</a>
    <a href="logout.php" class="btn btn-danger btn-sm">Cerrar sesión</a>
  </div>
</nav>

<div class="container my-4">
  <?php if ($mensaje): ?>
    <div class="alert <?= (strpos($mensaje,'✅')!==false) ? 'alert-success' : 'alert-danger' ?>"><?= $mensaje ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-receipt"></i> Registrar venta</h5></div>
        <div class="card-body">
          <form method="POST" id="frmVenta" novalidate>
            <div class="mb-3">
              <label class="form-label">Cliente <span class="text-danger">*</span></label>
              <select class="form-select" name="cliente_id" id="cliente" required>
                <option value="">— Selecciona —</option>
                <?php foreach($clientes as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= h($c['nombre']) ?> — <?= h($c['correo']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="small small-dim mt-1">Al seleccionar, se cargarán sus direcciones (o los datos de su perfil si no tiene).</div>
            </div>

            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Producto <span class="text-danger">*</span></label>
                <select class="form-select" name="producto_id" id="producto" required>
                  <option value="">— Selecciona —</option>
                  <?php foreach($productos as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" data-precio="<?= (float)$p['precio'] ?>" data-stock="<?= (int)$p['stock'] ?>">
                      <?= h($p['nombre']) ?> — Stock: <?= (int)$p['stock'] ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="cantidad" id="cantidad" min="1" required>
              </div>
              <div class="col-6">
                <label class="form-label">Precio unitario</label>
                <div class="input-group"><span class="input-group-text">$</span>
                  <input type="text" class="form-control" id="precio_unitario" value="0" readonly>
                </div>
              </div>
            </div>

            <hr class="my-4">

            <div class="mb-2 fw-semibold">Entrega <span class="text-danger">*</span></div>
            <div class="mb-3">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="entrega" id="ret" value="retiro" required>
                <label class="form-check-label" for="ret">Retiro en tienda</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="entrega" id="env" value="envio" required>
                <label class="form-check-label" for="env">Envío a domicilio</label>
              </div>
            </div>

            <div id="envioBox" class="border rounded p-3 d-none">
              <div class="mb-3">
                <label class="form-label">Dirección del cliente</label>
                <select class="form-select" name="direccion_id" id="direccionSelect"><option value="">— Selecciona —</option></select>
              </div>

              <details>
                <summary class="mb-2">➕ Agregar nueva dirección</summary>
                <input type="hidden" name="add_dir" id="add_dir" value="0">
                <div class="row g-2 mt-1">
                  <div class="col-12 col-md-6"><label class="form-label">Calle *</label><input class="form-control" name="n_calle"></div>
                  <div class="col-12 col-md-3"><label class="form-label">Número</label><input class="form-control" name="n_numero"></div>
                  <div class="col-12 col-md-3"><label class="form-label">Ciudad *</label><input class="form-control" name="n_ciudad"></div>
                  <div class="col-12 col-md-4"><label class="form-label">Departamento/Estado</label><input class="form-control" name="n_estado"></div>
                  <div class="col-12 col-md-4"><label class="form-label">Código postal</label><input class="form-control" name="n_cp"></div>
                  <div class="col-12 col-md-4"><label class="form-label">País *</label><input class="form-control" name="n_pais"></div>
                  <div class="col-12"><label class="form-label">Referencias</label><input class="form-control" name="n_ref" placeholder="Piso, interior, referencia…"></div>
                </div>
              </details>
              <div class="small small-dim mt-2">Si no eliges una dirección existente ni completas una nueva, no podrás registrar la venta.</div>
            </div>

            <hr class="my-4">

            <div class="mb-3">
              <label class="form-label">Subtotal</label>
              <div class="input-group"><span class="input-group-text">$</span>
                <input type="text" class="form-control" id="subtotal" value="0" readonly>
              </div>
            </div>

            <button type="submit" id="btnSubmit" class="btn btn-primary w-100" disabled>
              <i class="bi bi-check-circle"></i> Registrar venta
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-4">
      <div class="card shadow-sm sticky-summary">
        <div class="card-header"><h6 class="mb-0"><i class="bi bi-card-checklist"></i> Resumen</h6></div>
        <div class="card-body">
          <?php if ($detalleExito): ?>
            <ul class="list-group">
              <li class="list-group-item d-flex justify-content-between"><span>Producto</span><strong><?= h($detalleExito['producto']) ?></strong></li>
              <li class="list-group-item d-flex justify-content-between"><span>Cantidad</span><strong><?= (int)$detalleExito['cantidad'] ?></strong></li>
              <li class="list-group-item d-flex justify-content-between"><span>Precio unit.</span><strong>$<?= number_format($detalleExito['precio_unit'],0,',','.') ?></strong></li>
              <li class="list-group-item d-flex justify-content-between"><span>Subtotal</span><strong>$<?= number_format($detalleExito['subtotal'],0,',','.') ?></strong></li>
              <li class="list-group-item d-flex justify-content-between"><span>Entrega</span><strong><?= $detalleExito['entrega']==='retiro'?'Retiro':'Envío' ?></strong></li>
            </ul>
          <?php else: ?>
            <p class="small small-dim mb-0">Aquí verás el detalle una vez registres la venta.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const dirMap = <?= json_encode($direcciones, JSON_UNESCAPED_UNICODE) ?>;
const selCliente = document.getElementById('cliente');
const selProducto= document.getElementById('producto');
const inpCant    = document.getElementById('cantidad');
const precioUI   = document.getElementById('precio_unitario');
const subtotalUI = document.getElementById('subtotal');
const btnSubmit  = document.getElementById('btnSubmit');
const radioRet   = document.getElementById('ret');
const radioEnv   = document.getElementById('env');
const envioBox   = document.getElementById('envioBox');
const dirSelect  = document.getElementById('direccionSelect');
const addDir     = document.getElementById('add_dir');

function fmt(n){ n = Number(n||0); return n.toLocaleString('es-CO'); }
function currentProducto(){
  const opt = selProducto.options[selProducto.selectedIndex];
  if (!opt || !opt.value) return null;
  return { precio:Number(opt.dataset.precio||0), stock:Number(opt.dataset.stock||0) };
}
function refreshDirecciones(){
  dirSelect.innerHTML = '<option value="">— Selecciona —</option>';
  const uid = selCliente.value;
  if (uid && dirMap[uid]) {
    for (const d of dirMap[uid]) {
      const o = document.createElement('option'); o.value=d.id; o.textContent=d.label; dirSelect.appendChild(o);
    }
  }
}
function recalc(){
  const p = currentProducto();
  if (p){
    precioUI.value = fmt(p.precio);
    const qty = Number(inpCant.value||0);
    subtotalUI.value = fmt(qty * p.precio);
  } else { precioUI.value='0'; subtotalUI.value='0'; }
  validateForm();
}
function validateForm(){
  const hasCliente = !!selCliente.value;
  const hasProd    = !!selProducto.value;
  const p          = currentProducto();
  const qty        = Number(inpCant.value||0);
  const okQty      = p && qty>0 && qty<=p.stock;

  let okEntrega = false;
  if (radioRet.checked) okEntrega = true;
  else if (radioEnv.checked){
    const details = envioBox.querySelector('details');
    const open = details && details.hasAttribute('open');
    const n_calle = envioBox.querySelector('[name="n_calle"]').value.trim();
    const n_ciudad= envioBox.querySelector('[name="n_ciudad"]').value.trim();
    const n_pais  = envioBox.querySelector('[name="n_pais"]').value.trim();
    if (open && (n_calle || n_ciudad || n_pais)){
      addDir.value = '1';
      okEntrega = (n_calle && n_ciudad && n_pais);
    } else {
      addDir.value = '0';
      okEntrega = dirSelect.value !== '';
    }
  }
  btnSubmit.disabled = !(hasCliente && hasProd && okQty && okEntrega);
}
selCliente.addEventListener('change', ()=>{ refreshDirecciones(); validateForm(); });
selProducto.addEventListener('change', recalc);
inpCant.addEventListener('input', recalc);
radioRet.addEventListener('change', ()=>{ envioBox.classList.add('d-none'); validateForm(); });
radioEnv.addEventListener('change', ()=>{ envioBox.classList.remove('d-none'); refreshDirecciones(); validateForm(); });
dirSelect.addEventListener('change', validateForm);
for (const el of envioBox.querySelectorAll('input')) el.addEventListener('input', validateForm);
recalc(); validateForm();
</script>
</body>
</html>


