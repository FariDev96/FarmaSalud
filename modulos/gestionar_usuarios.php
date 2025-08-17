<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['usuario_id']) || (int)($_SESSION['rol_id'] ?? 0) !== 1) {
  header("Location: login.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// filtros
$filtro_rol = $_GET['rol'] ?? '';
$roles = $conn->query("SELECT id, nombre FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// query principal
$sql = "SELECT u.*, r.nombre AS rol
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id";
$params = [];
if ($filtro_rol !== '') { $sql .= " WHERE u.rol_id = ?"; $params[] = $filtro_rol; }
$sql .= " ORDER BY u.fecha_registro DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// métricas (opcionales)
$total = count($usuarios);
$totClientes = 0; $totAdmins = 0;
foreach ($usuarios as $u) { $u['rol_id']==2 ? $totClientes++ : ($u['rol_id']==1 ? $totAdmins++ : null); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de usuarios — FarmaSalud</title>
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

    .fs-card{ background:var(--fs-surface); border:1px solid var(--fs-border); border-radius:16px; box-shadow:var(--fs-shadow); }
    .btn-fs-primary{
      --bs-btn-bg:var(--fs-primary); --bs-btn-border-color:var(--fs-primary);
      --bs-btn-hover-bg:var(--fs-primary-600); --bs-btn-hover-border-color:var(--fs-primary-600);
      --bs-btn-color:#fff; border-radius:12px; font-weight:600; box-shadow:0 8px 22px rgba(37,99,235,.25);
    }
    .btn-fs-ghost{ background:#fff; color:var(--fs-text); border:1px solid var(--fs-border); border-radius:12px; font-weight:600; }
    .btn-fs-ghost:hover{ background:rgba(2,6,23,.04); }

    .table thead th{ background:#eef4ff; color:#0f172a; border-bottom:1px solid var(--fs-border); }
    .table tbody tr{ transition: background .15s ease, box-shadow .25s ease; }
    .table tbody tr:hover{ background: rgba(2,6,23,.03); box-shadow: inset 0 0 0 9999px rgba(2,6,23,.015); }

    .badge-ok{ background:rgba(16,185,129,.12); color:#065f46; border:1px solid rgba(16,185,129,.35); }
    .badge-warn{ background:rgba(245,158,11,.12); color:#9a3412; border:1px solid rgba(245,158,11,.35); }
    .badge-no{ background:rgba(220,38,38,.12); color:#7f1d1d; border:1px solid rgba(220,38,38,.35); }

    @media (max-width: 576px){
      .hide-xs{ display:none !important; }
      .tools-stack>*{ width:100% !important; }
    }
  </style>
</head>
<body>

<main class="py-4">
  <div class="container">

    <!-- Encabezado y acciones -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-people" style="font-size:1.4rem;color:var(--fs-primary);"></i>
        <h1 class="h5 mb-0">Gestión de usuarios</h1>
        <span class="badge text-bg-light border ms-2">Total: <?= $total ?></span>
        <span class="badge text-bg-light border ms-1">Clientes: <?= $totClientes ?></span>
        <span class="badge text-bg-light border ms-1 hide-xs">Admins: <?= $totAdmins ?></span>
      </div>
      <div class="d-flex tools-stack gap-2">
        <a href="admin.php" class="btn btn-fs-ghost"><i class="bi bi-arrow-left"></i> Volver</a>
        <!-- NOTA: crear siempre como Cliente -->
        <a href="agregar_usuario.php" class="btn btn-fs-primary"><i class="bi bi-person-plus"></i> Nuevo usuario</a>
      </div>
    </div>

    <!-- Filtros -->
    <div class="fs-card p-3 p-md-4 mb-3">
      <form method="GET" class="row g-2 align-items-center">
        <div class="col-12 col-md-5">
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="q" type="text" class="form-control" placeholder="Buscar por nombre, correo, teléfono o ciudad…">
          </div>
        </div>
        <div class="col-12 col-md-3">
          <select name="rol" class="form-select" onchange="this.form.submit()">
            <option value="">— Todos los roles —</option>
            <?php foreach($roles as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= ($filtro_rol!=='' && (int)$filtro_rol===(int)$r['id'])?'selected':'' ?>>
                <?= h($r['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col d-none d-md-block text-end text-secondary small">
          Consejo: escribe “300”, “Bogotá”, “ana@” para filtrar al vuelo.
        </div>
      </form>
    </div>

    <!-- Tabla -->
    <div class="fs-card p-2">
      <?php if (empty($usuarios)): ?>
        <div class="alert alert-warning m-3"><i class="bi bi-info-circle"></i> No se encontraron usuarios.</div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0" id="tabla">
          <thead>
            <tr>
              <th style="width:72px;" class="text-center">ID</th>
              <th style="min-width:220px;">Nombre / Correo</th>
              <th style="min-width:120px;">Teléfono</th>
              <th class="hide-xs" style="min-width:220px;">Dirección / Ciudad</th>
              <th style="min-width:110px;">Perfil</th>
              <th class="hide-xs" style="min-width:110px;">Datos</th>
              <th class="hide-xs" style="min-width:120px;">Rol</th>
              <th class="hide-xs" style="min-width:150px;">Registro</th>
              <th style="min-width:220px;" class="text-center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($usuarios as $u):
              $faltantes = [];
              if (trim((string)$u['telefono'])==='')     $faltantes[]='teléfono';
              if (trim((string)$u['direccion'])==='')    $faltantes[]='dirección';
              if (trim((string)$u['ciudad'])==='')       $faltantes[]='ciudad';
              // puedes exigir otros: departamento, documento, fecha_nacimiento…

              $perfilOk = empty($faltantes);
              $consent  = (int)($u['acepta_datos'] ?? 0) === 1;

              // whatsapp link (CO por defecto si no tiene indicativo)
              $wa = preg_replace('/\D+/', '', (string)($u['telefono'] ?? ''));
              if ($wa !== '') {
                if (strlen($wa) <= 10 && substr($wa,0,2)!=='57') $wa = '57'.$wa;
              }
              $waLink = $wa ? "https://wa.me/{$wa}?text=" . rawurlencode("Hola ".($u['nombre']??'').", te contactamos de FarmaSalud.") : '';
              $mailto = "mailto:".rawurlencode((string)$u['correo'])."?subject=".rawurlencode("FarmaSalud soporte");
            ?>
            <tr data-filter="<?= h(mb_strtolower(($u['nombre']??'').' '.($u['correo']??'').' '.($u['telefono']??'').' '.($u['ciudad']??''))) ?>">
              <td class="text-center"><?= (int)$u['id'] ?></td>
              <td>
                <div class="fw-semibold"><?= h($u['nombre'] ?? '') ?></div>
                <div class="text-secondary small"><?= h($u['correo'] ?? '') ?></div>
              </td>
              <td>
                <?php if (!empty($u['telefono'])): ?>
                  <a href="tel:<?= h($u['telefono']) ?>" class="link-primary"><?= h($u['telefono']) ?></a>
                <?php else: ?>
                  <span class="text-secondary">—</span>
                <?php endif; ?>
              </td>
              <td class="hide-xs">
                <div class="text-truncate" style="max-width:320px;"><?= $u['direccion'] ? h($u['direccion']) : '<span class="text-secondary">—</span>' ?></div>
                <div class="text-secondary small"><?= $u['ciudad'] ? h($u['ciudad']) : '' ?> <?= $u['departamento'] ? '· '.h($u['departamento']) : '' ?></div>
              </td>
              <td>
                <?php if ($perfilOk): ?>
                  <span class="badge badge-ok" data-bs-toggle="tooltip" title="Perfil completo">Completo</span>
                <?php else: ?>
                  <span class="badge badge-warn" data-bs-toggle="tooltip" title="Faltan: <?= h(implode(', ', $faltantes)) ?>">Incompleto</span>
                <?php endif; ?>
              </td>
              <td class="hide-xs">
                <?php if ($consent): ?>
                  <span class="badge text-bg-light border"><i class="bi bi-check-circle text-success"></i> Consentido</span>
                <?php else: ?>
                  <span class="badge text-bg-light border"><i class="bi bi-x-circle text-danger"></i> Sin consentimiento</span>
                <?php endif; ?>
              </td>
              <td class="hide-xs"><?= h($u['rol'] ?? '') ?></td>
              <td class="hide-xs">
                <div class="small"><?= $u['fecha_registro'] ? h($u['fecha_registro']) : '—' ?></div>
                <div class="text-secondary small">Últ: <?= $u['ultimo_acceso'] ? h($u['ultimo_acceso']) : 'Nunca' ?></div>
              </td>
              <td class="text-center">
                <div class="btn-group btn-group-sm" role="group">
                  <a class="btn btn-outline-secondary" href="<?= $mailto ?>" data-bs-toggle="tooltip" title="Enviar correo"><i class="bi bi-envelope"></i></a>
                  <a class="btn btn-outline-success <?= $wa?'':'disabled' ?>" <?= $wa ? 'href="'.$waLink.'"' : '' ?> data-bs-toggle="tooltip" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                  <a class="btn btn-outline-primary" href="editar_usuario.php?id=<?= (int)$u['id'] ?>"><i class="bi bi-pencil-square"></i> <span class="hide-xs">Editar</span></a>
                  <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmDel" data-id="<?= (int)$u['id'] ?>" data-name="<?= h($u['nombre'] ?? '') ?>">
                    <i class="bi bi-trash"></i> <span class="hide-xs">Eliminar</span>
                  </button>
                </div>
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
        ¿Seguro que deseas eliminar a <strong id="delName">este usuario</strong>? Esta acción no se puede deshacer.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-fs-ghost" data-bs-dismiss="modal">Cancelar</button>
        <a id="delGo" class="btn btn-danger" href="#"><i class="bi bi-trash"></i> Sí, eliminar</a>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // tooltips
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

  // búsqueda
  const q = document.getElementById('q');
  const rows = Array.from(document.querySelectorAll('#tabla tbody tr'));
  q?.addEventListener('input', () => {
    const needle = (q.value || '').toLowerCase().trim();
    rows.forEach(tr => {
      const hay = (tr.getAttribute('data-filter') || '').toLowerCase();
      tr.style.display = hay.includes(needle) ? '' : 'none';
    });
  });

  // modal eliminar
  const modal = document.getElementById('confirmDel');
  modal?.addEventListener('show.bs.modal', (ev) => {
    const btn = ev.relatedTarget;
    const id = btn?.getAttribute('data-id');
    const nm = btn?.getAttribute('data-name') || 'este usuario';
    modal.querySelector('#delName').textContent = nm;
    modal.querySelector('#delGo').setAttribute('href', 'eliminar_usuario.php?id=' + id);
  });
</script>
</body>
</html>



