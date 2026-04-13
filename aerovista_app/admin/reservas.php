<?php
/**
 * AeroVista Admin · Listado de Reservas
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!esAdmin()) { header('Location: /admin/login.php'); exit; }

$db = getDB();
$ok = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValidar()) {
    $action    = $_POST['action'] ?? '';
    $reservaId = (int)($_POST['reserva_id'] ?? 0);
    if ($action === 'cancel' && $reservaId) {
        $db->prepare("UPDATE reservas SET estado='cancelada' WHERE id=?")->execute([$reservaId]);
        $db->prepare("UPDATE vuelos v JOIN reservas r ON r.vuelo_id=v.id SET v.asientos_disponibles = v.asientos_disponibles + (SELECT COUNT(*) FROM pasajeros p WHERE p.reserva_id=?) WHERE r.id=?")->execute([$reservaId, $reservaId]);
        $ok = 'Reserva cancelada.';
    }
}

$page   = max(1,(int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page-1)*$limit;
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['estado'] ?? '';

$where  = []; $params = [];
if ($search) { $where[] = "(r.codigo_pnr LIKE :q OR u.email LIKE :q OR ap_o.codigo LIKE :q)"; $params[':q'] = "%$search%"; }
if ($statusFilter) { $where[] = "r.estado=:estado"; $params[':estado'] = $statusFilter; }
$whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';

$total = $db->prepare("SELECT COUNT(*) FROM reservas r JOIN vuelos v ON v.id=r.vuelo_id JOIN aeropuertos ap_o ON ap_o.id=v.origen_id LEFT JOIN usuarios u ON u.id=r.usuario_id $whereSQL");
$total->execute($params);
$totalRows = (int)$total->fetchColumn();
$totalPages = max(1,ceil($totalRows/$limit));

$stmt = $db->prepare("
    SELECT r.*, v.numero_vuelo, v.fecha_salida,
           ap_o.codigo AS origen, ap_d.codigo AS destino,
           al.nombre AS aerolinea,
           CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,'')) AS usuario,
           u.email AS usuario_email,
           (SELECT COUNT(*) FROM pasajeros p WHERE p.reserva_id=r.id) AS num_pasajeros
    FROM reservas r
    JOIN vuelos v ON v.id=r.vuelo_id
    JOIN aerolineas al ON al.id=v.aerolinea_id
    JOIN aeropuertos ap_o ON ap_o.id=v.origen_id
    JOIN aeropuertos ap_d ON ap_d.id=v.destino_id
    LEFT JOIN usuarios u ON u.id=r.usuario_id
    $whereSQL
    ORDER BY r.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$reservas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reservas · Admin AeroVista</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <script>tailwind.config={theme:{extend:{colors:{"primary":"#001e40","secondary":"#9f4200","surface":"#fbf8fe","surface-container-low":"#f6f2f8","surface-container":"#f0edf2","surface-container-high":"#eae7ed","surface-container-highest":"#e4e1e7","surface-container-lowest":"#ffffff","on-surface":"#1b1b1f","on-surface-variant":"#43474f","outline-variant":"#c3c6d1","error":"#ba1a1a"}}}};</script>
  <style>body{font-family:'Inter',sans-serif;}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="bg-surface text-on-surface antialiased">
  <?php include __DIR__ . '/includes/sidebar.php'; ?>
  <main class="ml-64 min-h-screen">
    <header class="fixed top-0 right-0 left-64 h-16 bg-white/70 backdrop-blur-xl z-30 shadow-sm flex items-center px-8">
      <h1 class="text-xl font-bold text-primary tracking-tighter">Reservas del Sistema</h1>
    </header>
    <div class="pt-24 pb-12 px-8 max-w-7xl mx-auto space-y-6">
      <?php if ($ok): ?><div class="bg-green-100 border border-green-400 text-green-800 px-5 py-3 rounded-xl text-sm"><?= e($ok) ?></div><?php endif; ?>
      <form method="GET" class="flex flex-wrap gap-3">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="PNR, email, ruta..."
               class="flex-1 min-w-0 bg-surface-container-highest border-none rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary outline-none"/>
        <select name="estado" class="bg-surface-container-highest border-none rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
          <option value="">Todos los estados</option>
          <?php foreach (['pendiente','confirmada','cancelada','completada'] as $est): ?>
            <option value="<?= $est ?>" <?= $statusFilter===$est?'selected':''?>><?= ucfirst($est) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-lg font-bold text-sm hover:opacity-90">Filtrar</button>
      </form>
      <section class="bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm">
        <div class="p-6 border-b border-surface-container-high/50">
          <h2 class="text-base font-bold text-primary">Todas las Reservas</h2>
          <p class="text-xs text-on-surface-variant"><?= $totalRows ?> reservas totales</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="bg-surface-container-low/50">
                <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">PNR</th>
                <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Ruta</th>
                <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Fecha</th>
                <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Pasajeros</th>
                <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Total</th>
                <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Cliente</th>
                <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Estado</th>
                <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-container-high/20">
              <?php foreach ($reservas as $r): ?>
                <tr class="hover:bg-surface-container-low/20 transition-colors">
                  <td class="px-5 py-4 font-mono font-bold text-primary"><?= e($r['codigo_pnr']) ?></td>
                  <td class="px-5 py-4 font-semibold"><?= e($r['origen'].'→'.$r['destino']) ?></td>
                  <td class="px-5 py-4 text-xs"><?= fechaCorta($r['fecha_salida']) ?></td>
                  <td class="px-5 py-4 text-center"><?= $r['num_pasajeros'] ?></td>
                  <td class="px-5 py-4 font-bold"><?= precio((float)$r['precio_total']) ?></td>
                  <td class="px-5 py-4 text-xs text-on-surface-variant"><?= e(trim($r['usuario']) ?: '(Invitado)') ?></td>
                  <td class="px-5 py-4"><?= badgeEstado($r['estado']) ?></td>
                  <td class="px-5 py-4">
                    <?php if ($r['estado'] === 'confirmada'): ?>
                      <form method="POST" onsubmit="return confirm('¿Cancelar reserva <?= e($r['codigo_pnr']) ?>?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"    value="cancel"/>
                        <input type="hidden" name="reserva_id" value="<?= $r['id'] ?>"/>
                        <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded bg-red-100 text-red-700 hover:bg-red-200 transition-all">
                          Cancelar
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($reservas)): ?>
                <tr><td colspan="8" class="px-5 py-12 text-center text-on-surface-variant">Sin reservas</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
          <div class="px-6 py-4 bg-surface-container-low/30 flex justify-between items-center">
            <p class="text-[10px] font-bold text-on-surface-variant uppercase">Página <?= $page ?>/<?= $totalPages ?></p>
            <div class="flex gap-1">
              <?php for ($p=1; $p<=$totalPages; $p++): ?>
                <a href="?page=<?=$p?>&q=<?=urlencode($search)?>&estado=<?=urlencode($statusFilter)?>"
                   class="w-8 h-8 flex items-center justify-center rounded text-sm font-bold <?=$p===$page?'bg-primary text-white':'bg-white text-primary border border-outline-variant/20'?>"><?=$p?></a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
