<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
  header("Location: login.php");
  exit;
}

$nombreAdmin = $_SESSION['nombre'] ?? 'Admin';
$stmt = $conn->query("SELECT id, nombre, descripcion, precio, stock, fecha_caducidad, imagen FROM productos ORDER BY id DESC");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n){ return '$' . number_format((float)$n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de productos — FarmaSalud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#2563eb">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --fs-bg:#f6f9fc; --fs-surface:#fff; --fs-border:#e8edf3;
      --fs-text:#0f172a; --fs-dim:#64748b;
      --fs-primary:#2563eb; --fs-primary-600:#1d4ed8; --fs-accent:#10b981;
      --fs-radius:16px; --fs-shadow:0 12px 28px rgba(2,6,23,.06);
    }
    body{
      font-family:"Inter",system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial;
      background:
        radial-gradient(900px 500px at 8% -10%, rgba(37,99,235,.05), transparent 60%),
        radial-gradient(900px 500px at 100% 0%, rgba(16,185,129,.05), transparent 60%),
        linear-gradient(180deg, var(--fs-bg) 0%, #fff 100%);
      color:var(--fs-text);
      letter-spacing:.2px;
    }
    a{text-decoration:none}

    /* Navbar */
    .fs-navbar{ background:var(--fs-primary); }
    .fs-brand{ color:#fff; font-weight:700; }
    .fs-top-right{ color:#e6eefc; }

    /* Card contenedor */
    .fs-card{
      background:var(--fs-surface); border:1px solid var(--fs-border);
      border-radius:16px; box-shadow:var(--fs-shadow);
    }

    /* Herramientas */
    .fs-tools .form-control{
      border-radius:12px; border-color:var(--fs-border);
    }
    .btn-fs-primary{
      --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary);
      --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600);
      --bs-btn-color:#fff; border-radius:12px; font-weight:600; box-shadow:0 8px 22px rgba(37,99,235,.25);
    }
    .btn-fs-ghost{
      background:#fff; color:var(--fs-text); border:1px solid var(--fs-border); border-radius:12px; font-weight:600;
    }
    .btn-fs-ghost:hover{ background:rgba(2,6,23,.04); }

    /* Tabla */
    .table thead th{
      background:#eef4ff; color:#0f172a; border-bottom:1px solid var(--fs-border);
    }
    .table tbody tr{
      transition: background .15s ease, box-shadow .25s ease;
    }
    .table tbody tr:hover{
      background: rgba(2,6,23,.03);
      box-shadow: inset 0 0 0 9999px rgba(2,6,23,.015);
    }
    .thumb{
      width:48px; height:48px; object-fit:contain; background:#f8fafc; border-radius:8px; border:1px solid var(--fs-border);
      transition: filter .2s ease;
    }
    tr:hover .thumb{ filter: brightness(.9); }

    /* Badges stock/caducidad */
    .badge-low{ background:rgba(220,38,38,.12); color:#7f1d1d; border:1px solid rgba(220,38,38,.35); }
    .badge-mid{ background:rgba(245,158,11,.12); color:#9a3412; border:1px solid rgba(245,158,11,.35); }
    .badge-ok{ background:rgba(16,185,129,.12); color:#065f46; border:1px solid rgba(16,185,129,.35); }

    /* Acciones */
    .btn-row{
      border-radius:10px; font-weight:600;
    }
    .btn-row.edit{ --bs-btn-bg:#1d4ed8; --bs-btn-border-color:#1d4ed8; --bs-btn-color:#fff; }
    .btn-row.edit:hover{ background:#173db0; border-color:#173db0; }
    .btn-row.del{ --bs-btn-bg:#dc2626; --bs-btn-border-color:#dc2626; --bs-btn-color:#fff; }
    .btn-row.del:hover{ background:#b91c1c; border-color:#b91c1c; }

    /* Responsive helpers */
    @media (max-width: 576px){
      .hide-xs{ display:none; }
      .tools-stack > *{ width:100% !important; }
    }
  </style>
</head>
<body>

<nav class="navbar fs-navbar py-3">
  <div class="container">
    <span class="fs-brand">FarmaSalud | Admin</span>
    <div class="ms-auto fs-top-right small d-none d-sm-flex align-items-center gap-2">
      <i class="bi bi-person-circle"></i> Bienvenido, <?= h($nombreAdmin) ?>
      <a href="logout.php" class="btn btn-light btn-sm ms-2"><i class="bi bi-lock"></i> Cerrar sesión</a>
    </div>
  </div>
</nav>

<main class="py-4">
  <div class="container">
    <!-- Header + herramientas -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-box-seam" style="font-size:1.4rem;color:var(--fs-primary);"></i>
        <h1 class="h5 mb-0">Gestión de Productos</h1>
        <span class="badge text-bg-light border ms-2">Total: <?= count($productos) ?></span>
      </div>
      <div class="d-flex tools-stack fs-tools gap-2">
        <a href="admin.php" class="btn btn-fs-ghost"><i class="bi bi-arrow-left"></i> Volver</a>
        <a href="agregar_producto.php" class="btn btn-fs-primary"><i class="bi bi-plus-lg"></i> Agregar producto</a>
      </div>
    </div>

    <div class="fs-card p-3 p-md-4">
      <div class="row g-2 align-items-center mb-3">
        <div class="col-12 col-md-6">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="q" type="text" class="form-control" placeholder="Buscar por nombre o descripción…">
          </div>
        </div>
        <div class="col-12 col-md-6 text-md-end small text-secondary">
          Sugerencia: escribe “ibup” o “pomada” y filtra al vuelo.
        </div>
      </div>

      <?php if (empty($productos)): ?>
        <div class="alert alert-warning mb-0"><i class="bi bi-info-circle"></i> No hay productos cargados.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle" id="tabla">
          <thead>
            <tr>
              <th class="text-center" style="width:70px;">ID</th>
              <th style="width:80px;">Imagen</th>
              <th>Nombre</th>
              <th class="hide-xs" style="width:120px;">Precio</th>
              <th style="width:110px;">Stock</th>
              <th class="hide-xs" style="width:160px;">Caducidad</th>
              <th style="width:190px;" class="text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($productos as $p):
              $img = !empty($p['imagen']) ? "../imagenes/". $p['imagen'] : "../imagenes/generico.png";
              if (!@file_exists(__DIR__ . '/../imagenes/' . ($p['imagen'] ?? ''))) {
                $img = "../imagenes/generico.png";
              }
              $stock = (int)$p['stock'];
              $badge = $stock <= 0 ? 'badge-low' : ($stock <= 5 ? 'badge-mid' : 'badge-ok');
              $cad = $p['fecha_caducidad'];
            ?>
            <tr data-filter="<?= h(mb_strtolower($p['nombre'].' '.$p['descripcion'])) ?>">
              <td class="text-center"><?= (int)$p['id'] ?></td>
              <td><img src="<?= h($img) ?>" class="thumb" alt="img"></td>
              <td>
                <div class="fw-semibold"><?= h($p['nombre']) ?></div>
                <div class="text-secondary small d-none d-sm-block" style="max-width:520px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                  <?= h($p['descripcion']) ?>
                </div>
              </td>
              <td class="hide-xs"><?= money($p['precio']) ?></td>
              <td>
                <span class="badge <?= $badge ?>"><?= $stock ?></span>
              </td>
              <td class="hide-xs">
                <?php if ($cad && $cad !== '0000-00-00'): ?>
                  <span class="badge text-bg-light border"><?= h(date('d/m/Y', strtotime($cad))) ?></span>
                <?php else: ?>
                  <span class="text-secondary">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <a class="btn btn-sm btn-row edit me-1" href="editar_producto.php?id=<?= (int)$p['id'] ?>">
                  <i class="bi bi-pencil-square"></i> Editar
                </a>
                <button class="btn btn-sm btn-row del" data-bs-toggle="modal" data-bs-target="#confirmDel"
                        data-id="<?= (int)$p['id'] ?>" data-name="<?= h($p['nombre']) ?>">
                  <i class="bi bi-trash"></i> Eliminar
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Modal eliminar -->
<div class="modal fade" id="confirmDel" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger"></i> Confirmar eliminación</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        ¿Seguro que deseas eliminar <strong id="delName">este producto</strong>? Esta acción no se puede deshacer.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-fs-ghost" data-bs-dismiss="modal">Cancelar</button>
        <a id="delGo" href="#" class="btn btn-row del">Sí, eliminar</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Filtro en vivo
  const q = document.getElementById('q');
  const rows = Array.from(document.querySelectorAll('#tabla tbody tr'));
  q?.addEventListener('input', ()=> {
    const needle = (q.value || '').trim().toLowerCase();
    let shown = 0;
    rows.forEach(tr=>{
      const hay = tr.getAttribute('data-filter') || '';
      const show = hay.indexOf(needle) !== -1;
      tr.style.display = show ? '' : 'none';
      if (show) shown++;
    });
  });

  // Modal de eliminar
  const modal = document.getElementById('confirmDel');
  if (modal){
    modal.addEventListener('show.bs.modal', (ev)=>{
      const btn = ev.relatedTarget;
      const id  = btn?.getAttribute('data-id');
      const nm  = btn?.getAttribute('data-name') || 'este producto';
      modal.querySelector('#delName').textContent = nm;
      modal.querySelector('#delGo').setAttribute('href', 'eliminar_producto.php?id='+id);
    });
  }
</script>
</body>
</html>

