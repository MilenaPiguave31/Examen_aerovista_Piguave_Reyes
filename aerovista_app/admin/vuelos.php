<?php
/**
 * AeroVista Admin · CRUD de Vuelos
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!esAdmin()) { header('Location: /admin/login.php'); exit; }

$db    = getDB();
$error = '';
$ok    = '';

// ── CRUD: Eliminar vuelo ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrfValidar()) { $error = 'Token inválido.'; }
    else {
        $action = $_POST['action'];

        if ($action === 'delete') {
            $id = (int)$_POST['vuelo_id'];
            $db->prepare("DELETE FROM vuelos WHERE id=?")->execute([$id]);
            $ok = 'Vuelo eliminado correctamente.';

        } elseif ($action === 'create' || $action === 'update') {
            $fields = [
                'numero_vuelo'    => trim($_POST['numero_vuelo']),
                'aerolinea_id'    => (int)$_POST['aerolinea_id'],
                'origen_id'       => (int)$_POST['origen_id'],
                'destino_id'      => (int)$_POST['destino_id'],
                'fecha_salida'    => $_POST['fecha_salida'],
                'fecha_llegada'   => $_POST['fecha_llegada'],
                'precio_base'     => (float)$_POST['precio_base'],
                'impuestos'       => (float)$_POST['impuestos'],
                'capacidad'       => (int)$_POST['capacidad'],
                'asientos_disponibles' => (int)$_POST['capacidad'],
                'avion'           => trim($_POST['avion'] ?? ''),
                'estado'          => $_POST['estado'] ?? 'programado',
            ];
            if ($action === 'create') {
                $sql = "INSERT INTO vuelos (numero_vuelo,aerolinea_id,origen_id,destino_id,fecha_salida,fecha_llegada,precio_base,impuestos,capacidad,asientos_disponibles,avion,estado)
                        VALUES (:numero_vuelo,:aerolinea_id,:origen_id,:destino_id,:fecha_salida,:fecha_llegada,:precio_base,:impuestos,:capacidad,:asientos_disponibles,:avion,:estado)";
                $stmt = $db->prepare($sql);
                $stmt->execute($fields);
                $newId = (int)$db->lastInsertId();
                // Generar asientos
                $db->prepare("CALL generar_asientos(?,?)")->execute([$newId, $fields['capacidad']]);
                $ok = 'Vuelo creado correctamente.';
            } else {
                $id = (int)$_POST['vuelo_id'];
                $sql = "UPDATE vuelos SET numero_vuelo=:numero_vuelo,aerolinea_id=:aerolinea_id,origen_id=:origen_id,
                        destino_id=:destino_id,fecha_salida=:fecha_salida,fecha_llegada=:fecha_llegada,
                        precio_base=:precio_base,impuestos=:impuestos,avion=:avion,estado=:estado
                        WHERE id=:id";
                $fields['id'] = $id;
                unset($fields['capacidad'], $fields['asientos_disponibles']);
                $db->prepare($sql)->execute($fields);
                $ok = 'Vuelo actualizado correctamente.';
            }
        } elseif ($action === 'update_estado') {
            $id     = (int)$_POST['vuelo_id'];
            $estado = $_POST['estado'];
            $db->prepare("UPDATE vuelos SET estado=? WHERE id=?")->execute([$estado, $id]);
            $ok = 'Estado actualizado.';
        }
    }
}

// ── Paginación ──
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');

$whereSQL = $search ? "WHERE v.numero_vuelo LIKE :q OR ap_o.codigo LIKE :q OR ap_d.codigo LIKE :q" : '';
$params   = $search ? [':q' => "%{$search}%"] : [];

$total = $db->prepare("SELECT COUNT(*) FROM vuelos v JOIN aeropuertos ap_o ON ap_o.id=v.origen_id JOIN aeropuertos ap_d ON ap_d.id=v.destino_id $whereSQL");
$total->execute($params);
$totalRows = (int)$total->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmtV = $db->prepare("
    SELECT v.*, al.nombre AS aerolinea, ap_o.codigo AS origen, ap_d.codigo AS destino
    FROM vuelos v
    JOIN aerolineas al    ON al.id  = v.aerolinea_id
    JOIN aeropuertos ap_o ON ap_o.id = v.origen_id
    JOIN aeropuertos ap_d ON ap_d.id = v.destino_id
    $whereSQL
    ORDER BY v.fecha_salida DESC
    LIMIT $limit OFFSET $offset
");
$stmtV->execute($params);
$vuelos = $stmtV->fetchAll();

// Datos para los selects del formulario
$aerolineas  = $db->query("SELECT id, nombre FROM aerolineas WHERE activo=1 ORDER BY nombre")->fetchAll();
$aeropuertos = $db->query("SELECT id, codigo, ciudad FROM aeropuertos WHERE activo=1 ORDER BY ciudad")->fetchAll();

$estados = ['programado','a_tiempo','retrasado','cancelado','completado'];

// Vuelo a editar
$editVuelo = null;
if (isset($_GET['edit'])) {
    $editVuelo = $db->prepare("SELECT * FROM vuelos WHERE id=?")->execute([(int)$_GET['edit']]) ? null : null;
    $stmtE = $db->prepare("SELECT * FROM vuelos WHERE id=?");
    $stmtE->execute([(int)$_GET['edit']]);
    $editVuelo = $stmtE->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vuelos · Admin AeroVista</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <script>tailwind.config={theme:{extend:{colors:{"primary":"#001e40","secondary":"#9f4200","secondary-container":"#fd6c00","surface":"#fbf8fe","surface-container-low":"#f6f2f8","surface-container":"#f0edf2","surface-container-high":"#eae7ed","surface-container-highest":"#e4e1e7","surface-container-lowest":"#ffffff","on-surface":"#1b1b1f","on-surface-variant":"#43474f","outline-variant":"#c3c6d1","primary-fixed":"#d5e3ff","error":"#ba1a1a"}}}};</script>
  <style>body{font-family:'Inter',sans-serif;}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="bg-surface text-on-surface antialiased">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="ml-64 min-h-screen">
    <header class="fixed top-0 right-0 left-64 h-16 bg-white/70 backdrop-blur-xl z-30 shadow-sm flex items-center justify-between px-8">
      <h1 class="text-xl font-bold text-primary tracking-tighter">Gestión de Vuelos</h1>
      <button onclick="document.getElementById('modal-vuelo').classList.remove('hidden')"
              class="bg-secondary text-white px-5 py-2 rounded-lg font-bold text-sm flex items-center gap-2 hover:opacity-90 transition-all">
        <span class="material-symbols-outlined text-sm">add_circle</span>
        Nuevo Vuelo
      </button>
    </header>

    <div class="pt-24 pb-12 px-8 max-w-7xl mx-auto space-y-6">

      <?php if ($ok):  ?><div class="bg-green-100 border border-green-400 text-green-800 px-5 py-3 rounded-xl text-sm font-medium"><?= e($ok) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-800 px-5 py-3 rounded-xl text-sm font-medium"><?= e($error) ?></div><?php endif; ?>

      <!-- Buscador -->
      <form method="GET" class="flex gap-3">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Buscar por número de vuelo o ruta..."
               class="flex-1 bg-surface-container-highest border-none rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary outline-none"/>
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-lg font-bold text-sm hover:opacity-90">Buscar</button>
        <?php if ($search): ?><a href="/admin/vuelos.php" class="px-5 py-2.5 rounded-lg font-bold text-sm bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest transition-all">Limpiar</a><?php endif; ?>
      </form>

      <!-- Tabla de vuelos -->
      <section class="bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm">
        <div class="p-6 border-b border-surface-container-high/50 flex items-center justify-between">
          <div>
            <h2 class="text-base font-bold text-primary">Vuelos Registrados</h2>
            <p class="text-xs text-on-surface-variant"><?= $totalRows ?> vuelos totales</p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="bg-surface-container-low/50">
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Número</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Aerolínea</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Ruta</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Salida</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Precio</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Asientos</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Estado</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-container-high/30">
              <?php foreach ($vuelos as $v): ?>
                <tr class="hover:bg-surface-container-low/30 transition-colors">
                  <td class="px-6 py-4 font-bold text-primary"><?= e($v['numero_vuelo']) ?></td>
                  <td class="px-6 py-4"><?= e($v['aerolinea']) ?></td>
                  <td class="px-6 py-4 font-semibold"><?= e($v['origen']) ?> → <?= e($v['destino']) ?></td>
                  <td class="px-6 py-4 text-xs"><?= fechaCorta($v['fecha_salida']) ?> <?= hora($v['fecha_salida']) ?></td>
                  <td class="px-6 py-4"><?= precio((float)$v['precio_base'] + (float)$v['impuestos']) ?></td>
                  <td class="px-6 py-4">
                    <span class="<?= $v['asientos_disponibles'] < 20 ? 'text-error font-bold' : 'text-on-surface-variant' ?>"><?= $v['asientos_disponibles'] ?>/<?= $v['capacidad'] ?></span>
                  </td>
                  <td class="px-6 py-4"><?= badgeEstado($v['estado']) ?></td>
                  <td class="px-6 py-4">
                    <div class="flex gap-3 items-center">
                      <a href="/admin/vuelos.php?edit=<?= $v['id'] ?>"
                         class="text-primary hover:text-secondary transition-colors">
                        <span class="material-symbols-outlined text-xl">edit</span>
                      </a>
                      <form method="POST" onsubmit="return confirm('¿Eliminar vuelo <?= e($v['numero_vuelo']) ?>? Esta acción no se puede deshacer.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"   value="delete"/>
                        <input type="hidden" name="vuelo_id" value="<?= $v['id'] ?>"/>
                        <button type="submit" class="text-error hover:opacity-70 transition-opacity">
                          <span class="material-symbols-outlined text-xl">delete</span>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($vuelos)): ?>
                <tr><td colspan="8" class="px-6 py-12 text-center text-on-surface-variant">Sin vuelos registrados</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
          <div class="px-6 py-4 bg-surface-container-low/30 flex justify-between items-center">
            <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">
              Página <?= $page ?> de <?= $totalPages ?>
            </p>
            <div class="flex gap-1">
              <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>"
                   class="w-8 h-8 flex items-center justify-center rounded text-sm font-bold
                          <?= $p === $page ? 'bg-primary text-white' : 'bg-white text-primary border border-outline-variant/20 hover:bg-surface-container' ?>">
                  <?= $p ?>
                </a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <!-- ── Modal Crear/Editar Vuelo ── -->
  <div id="modal-vuelo" class="<?= ($editVuelo || ($_SERVER['REQUEST_METHOD']==='POST' && !$ok)) ? '' : 'hidden' ?> fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-8 w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-primary"><?= $editVuelo ? 'Editar Vuelo' : 'Nuevo Vuelo' ?></h2>
        <button onclick="document.getElementById('modal-vuelo').classList.add('hidden')"
                class="text-on-surface-variant hover:text-primary transition-colors">
          <span class="material-symbols-outlined text-2xl">close</span>
        </button>
      </div>

      <form method="POST" class="space-y-5">
        <?= csrfField() ?>
        <input type="hidden" name="action"   value="<?= $editVuelo ? 'update' : 'create' ?>"/>
        <?php if ($editVuelo): ?>
          <input type="hidden" name="vuelo_id" value="<?= $editVuelo['id'] ?>"/>
        <?php endif; ?>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Número de Vuelo</label>
            <input type="text" name="numero_vuelo" required
                   value="<?= e($editVuelo['numero_vuelo'] ?? '') ?>"
                   class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary"/>
          </div>
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Aerolínea</label>
            <select name="aerolinea_id" required class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary">
              <?php foreach ($aerolineas as $al): ?>
                <option value="<?= $al['id'] ?>" <?= ($editVuelo['aerolinea_id'] ?? '') == $al['id'] ? 'selected' : '' ?>>
                  <?= e($al['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Origen</label>
            <select name="origen_id" required class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary">
              <?php foreach ($aeropuertos as $ap): ?>
                <option value="<?= $ap['id'] ?>" <?= ($editVuelo['origen_id'] ?? '') == $ap['id'] ? 'selected' : '' ?>>
                  <?= e($ap['codigo']) ?> – <?= e($ap['ciudad']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Destino</label>
            <select name="destino_id" required class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary">
              <?php foreach ($aeropuertos as $ap): ?>
                <option value="<?= $ap['id'] ?>" <?= ($editVuelo['destino_id'] ?? '') == $ap['id'] ? 'selected' : '' ?>>
                  <?= e($ap['codigo']) ?> – <?= e($ap['ciudad']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Salida</label>
            <input type="datetime-local" name="fecha_salida" required
                   value="<?= $editVuelo ? substr($editVuelo['fecha_salida'],0,16) : '' ?>"
                   class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary"/>
          </div>
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Llegada</label>
            <input type="datetime-local" name="fecha_llegada" required
                   value="<?= $editVuelo ? substr($editVuelo['fecha_llegada'],0,16) : '' ?>"
                   class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary"/>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Precio Base</label>
            <input type="number" name="precio_base" step="0.01" min="0" required
                   value="<?= e($editVuelo['precio_base'] ?? '') ?>"
                   class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary"/>
          </div>
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Impuestos</label>
            <input type="number" name="impuestos" step="0.01" min="0" required
                   value="<?= e($editVuelo['impuestos'] ?? '') ?>"
                   class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary"/>
          </div>
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Capacidad</label>
            <input type="number" name="capacidad" min="1" required
                   value="<?= e($editVuelo['capacidad'] ?? '180') ?>"
                   <?= $editVuelo ? 'readonly title="No se puede cambiar en edición"' : '' ?>
                   class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary <?= $editVuelo ? 'opacity-60' : '' ?>"/>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Modelo de Avión</label>
            <input type="text" name="avion"
                   value="<?= e($editVuelo['avion'] ?? '') ?>"
                   placeholder="Ej. Airbus A321neo"
                   class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary"/>
          </div>
          <div>
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant block mb-1.5">Estado</label>
            <select name="estado" class="w-full bg-surface-container-highest border-none rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary">
              <?php foreach ($estados as $est): ?>
                <option value="<?= $est ?>" <?= ($editVuelo['estado'] ?? 'programado') === $est ? 'selected' : '' ?>>
                  <?= ucfirst(str_replace('_', ' ', $est)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="flex gap-4 pt-4">
          <button type="submit"
                  class="flex-1 bg-secondary text-white py-3 rounded-lg font-bold hover:opacity-90 transition-all">
            <?= $editVuelo ? 'Guardar Cambios' : 'Crear Vuelo' ?>
          </button>
          <button type="button"
                  onclick="document.getElementById('modal-vuelo').classList.add('hidden')"
                  class="px-6 py-3 bg-surface-container-high text-on-surface-variant rounded-lg font-bold hover:bg-surface-container-highest transition-all">
            Cancelar
          </button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
