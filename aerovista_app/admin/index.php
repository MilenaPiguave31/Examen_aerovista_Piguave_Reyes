<?php
/**
 * AeroVista Admin · Dashboard (Control Tower)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!esAdmin()) { header('Location: /admin/login.php'); exit; }

$db = getDB();

// ── KPIs del día ──
$hoy = date('Y-m-d');
$vuelosHoy    = $db->query("SELECT COUNT(*) FROM vuelos WHERE DATE(fecha_salida)='$hoy' AND estado NOT IN ('cancelado')")->fetchColumn();
$usuariosHoy  = $db->query("SELECT COUNT(*) FROM usuarios WHERE DATE(created_at)='$hoy' AND rol='cliente'")->fetchColumn();
$ventasHoy    = $db->query("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE DATE(fecha_pago)='$hoy' AND estado='procesado'")->fetchColumn();
$reservasTotal = $db->query("SELECT COUNT(*) FROM reservas WHERE estado='confirmada'")->fetchColumn();

// Ingresos mensuales (últimos 7 meses)
$ingresosMes = $db->query("
    SELECT DATE_FORMAT(fecha_pago,'%b') AS mes,
           MONTH(fecha_pago) AS mes_num,
           COALESCE(SUM(monto),0) AS total
    FROM pagos
    WHERE estado='procesado'
      AND fecha_pago >= DATE_SUB(NOW(), INTERVAL 7 MONTH)
    GROUP BY mes_num, mes
    ORDER BY mes_num ASC
")->fetchAll();

// Últimas reservas
$ultimasReservas = $db->query("
    SELECT r.codigo_pnr, r.precio_total, r.estado, r.created_at,
           ap_o.codigo AS origen, ap_d.codigo AS destino,
           u.nombre AS usuario_nombre, u.email AS usuario_email
    FROM reservas r
    JOIN vuelos v        ON v.id   = r.vuelo_id
    JOIN aeropuertos ap_o ON ap_o.id = v.origen_id
    JOIN aeropuertos ap_d ON ap_d.id = v.destino_id
    LEFT JOIN usuarios u ON u.id = r.usuario_id
    ORDER BY r.created_at DESC
    LIMIT 5
")->fetchAll();

// Vuelos de hoy
$vuelosListHoy = $db->query("
    SELECT v.numero_vuelo, v.fecha_salida, v.fecha_llegada, v.estado,
           ap_o.codigo AS origen, ap_d.codigo AS destino, al.nombre AS aerolinea
    FROM vuelos v
    JOIN aerolineas al    ON al.id  = v.aerolinea_id
    JOIN aeropuertos ap_o ON ap_o.id = v.origen_id
    JOIN aeropuertos ap_d ON ap_d.id = v.destino_id
    WHERE DATE(v.fecha_salida)='$hoy'
    ORDER BY v.fecha_salida ASC
    LIMIT 8
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Control Tower · AeroVista</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: {
          "primary":"#001e40","on-primary":"#ffffff","secondary":"#9f4200","on-secondary":"#ffffff",
          "secondary-container":"#fd6c00","surface":"#fbf8fe","surface-container-low":"#f6f2f8",
          "surface-container":"#f0edf2","surface-container-high":"#eae7ed",
          "surface-container-highest":"#e4e1e7","surface-container-lowest":"#ffffff",
          "on-surface":"#1b1b1f","on-surface-variant":"#43474f","outline-variant":"#c3c6d1",
          "primary-fixed":"#d5e3ff","tertiary-fixed":"#c6e7ff","secondary-fixed":"#ffdbcb",
        }
      }}
    };
  </script>
  <style>body{font-family:'Inter',sans-serif;}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="bg-surface text-on-surface antialiased">

  <!-- ── Sidebar ── -->
  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <!-- ── Área principal ── -->
  <main class="ml-64 min-h-screen bg-surface">
    <!-- Topbar -->
    <header class="fixed top-0 right-0 left-64 h-16 bg-white/70 backdrop-blur-xl z-30 shadow-sm flex items-center justify-between px-8">
      <div>
        <h1 class="text-xl font-bold text-primary tracking-tighter">Control Tower</h1>
        <p class="text-[10px] text-on-surface-variant uppercase tracking-widest">System Administrator</p>
      </div>
      <div class="flex items-center gap-4">
        <span class="text-sm text-on-surface-variant"><?= date('d M Y') ?></span>
        <div class="w-9 h-9 bg-primary rounded-full flex items-center justify-center">
          <span class="text-white font-bold text-sm"><?= strtoupper(substr($_SESSION['usuario_nombre'], 0, 2)) ?></span>
        </div>
      </div>
    </header>

    <div class="pt-24 pb-12 px-8 max-w-7xl mx-auto space-y-8">

      <!-- ── KPIs ── -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <?php
        $kpis = [
            ['label'=>'Vuelos hoy',     'value'=>$vuelosHoy,               'icon'=>'flight',      'color'=>'primary-fixed/30',   'text_color'=>'primary'],
            ['label'=>'Nuevos usuarios','value'=>$usuariosHoy,             'icon'=>'person_add',  'color'=>'tertiary-fixed/30',  'text_color'=>'primary'],
            ['label'=>'Ventas hoy',     'value'=>'$'.number_format((float)$ventasHoy,2), 'icon'=>'payments', 'color'=>'secondary-fixed/30', 'text_color'=>'secondary'],
            ['label'=>'Reservas totales','value'=>$reservasTotal,          'icon'=>'luggage',     'color'=>'primary-fixed/20',   'text_color'=>'primary'],
        ];
        foreach ($kpis as $kpi): ?>
          <div class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/10 flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider mb-1"><?= $kpi['label'] ?></p>
              <p class="text-3xl font-black text-<?= $kpi['text_color'] ?>"><?= $kpi['value'] ?></p>
            </div>
            <div class="w-12 h-12 rounded-lg bg-<?= $kpi['color'] ?> flex items-center justify-center">
              <span class="material-symbols-outlined text-<?= $kpi['text_color'] ?>"><?= $kpi['icon'] ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </section>

      <!-- ── Gráfico de ingresos mensuales ── -->
      <?php if (!empty($ingresosMes)):
        $maxIngresos = max(array_column($ingresosMes, 'total')) ?: 1;
      ?>
      <section class="bg-surface-container-low p-8 rounded-xl">
        <div class="flex items-center justify-between mb-8">
          <h2 class="text-lg font-bold text-primary">Ingresos Mensuales</h2>
        </div>
        <div class="h-52 flex items-end gap-3 px-2">
          <?php foreach ($ingresosMes as $im):
            $height = ($im['total'] / $maxIngresos) * 100;
          ?>
            <div class="flex-1 flex flex-col items-center gap-1 group relative">
              <div class="absolute -top-7 opacity-0 group-hover:opacity-100 bg-primary text-white text-[10px] px-2 py-1 rounded transition-opacity whitespace-nowrap">
                $<?= number_format((float)$im['total'], 0) ?>
              </div>
              <div class="w-full bg-primary/30 rounded-t hover:bg-primary/60 transition-colors"
                   style="height:<?= max(4, $height) ?>%"></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="flex justify-around mt-2 px-2">
          <?php foreach ($ingresosMes as $im): ?>
            <span class="text-[10px] font-bold text-on-surface-variant tracking-widest uppercase"><?= $im['mes'] ?></span>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- ── Últimas reservas ── -->
        <section class="bg-surface-container-lowest rounded-xl overflow-hidden">
          <div class="p-6 border-b border-surface-container-high/50 flex items-center justify-between">
            <div>
              <h2 class="text-base font-bold text-primary">Últimas Reservas</h2>
              <p class="text-xs text-on-surface-variant">5 reservas más recientes</p>
            </div>
            <a href="/admin/reservas.php" class="text-xs font-bold text-secondary hover:underline">Ver todas →</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead>
                <tr class="bg-surface-container-low/40">
                  <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">PNR</th>
                  <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Ruta</th>
                  <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Total</th>
                  <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Estado</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-container-high/20">
                <?php foreach ($ultimasReservas as $r): ?>
                  <tr class="hover:bg-surface-container-low/30 transition-colors">
                    <td class="px-5 py-4 font-mono font-bold text-primary"><?= e($r['codigo_pnr']) ?></td>
                    <td class="px-5 py-4 font-semibold"><?= e($r['origen']) ?> → <?= e($r['destino']) ?></td>
                    <td class="px-5 py-4"><?= precio((float)$r['precio_total']) ?></td>
                    <td class="px-5 py-4"><?= badgeEstado($r['estado']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($ultimasReservas)): ?>
                  <tr><td colspan="4" class="px-5 py-8 text-center text-on-surface-variant text-sm">Sin reservas aún</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- ── Vuelos de hoy ── -->
        <section class="bg-surface-container-lowest rounded-xl overflow-hidden">
          <div class="p-6 border-b border-surface-container-high/50 flex items-center justify-between">
            <div>
              <h2 class="text-base font-bold text-primary">Vuelos de Hoy</h2>
              <p class="text-xs text-on-surface-variant"><?= date('d \d\e F \d\e Y') ?></p>
            </div>
            <a href="/admin/vuelos.php" class="text-xs font-bold text-secondary hover:underline">Gestionar →</a>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead>
                <tr class="bg-surface-container-low/40">
                  <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Vuelo</th>
                  <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Ruta</th>
                  <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Salida</th>
                  <th class="px-5 py-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Estado</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-surface-container-high/20">
                <?php foreach ($vuelosListHoy as $v): ?>
                  <tr class="hover:bg-surface-container-low/30 transition-colors">
                    <td class="px-5 py-4 font-bold text-primary"><?= e($v['numero_vuelo']) ?></td>
                    <td class="px-5 py-4 font-semibold"><?= e($v['origen']) ?> → <?= e($v['destino']) ?></td>
                    <td class="px-5 py-4 font-mono text-sm"><?= hora($v['fecha_salida']) ?></td>
                    <td class="px-5 py-4"><?= badgeEstado($v['estado']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($vuelosListHoy)): ?>
                  <tr><td colspan="4" class="px-5 py-8 text-center text-on-surface-variant text-sm">No hay vuelos programados para hoy</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

      </div>
    </div>
  </main>
</body>
</html>
