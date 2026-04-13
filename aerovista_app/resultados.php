<?php
/**
 * AeroVista · Resultados de Búsqueda de Vuelos
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Resultados de Búsqueda';

// ── Parámetros de búsqueda ──
$origen      = strtoupper(trim($_GET['origen']      ?? ''));
$destino     = strtoupper(trim($_GET['destino']     ?? ''));
$fechaSalida = $_GET['fecha_salida'] ?? date('Y-m-d', strtotime('+7 days'));
$tipoViaje   = $_GET['tipo_viaje']  ?? 'ida';
$adultos     = max(1, (int)($_GET['adultos'] ?? 1));
$ninos       = max(0, (int)($_GET['ninos']  ?? 0));
$pasajeros   = $adultos + $ninos;

// Filtros opcionales de la sidebar
$precioMax   = (int)($_GET['precio_max']  ?? 9999);
$aerolinea   = $_GET['aerolinea'] ?? '';
$escalas     = $_GET['escalas']   ?? '';    // 'directo' | '1'

// Guardar búsqueda en sesión para el flujo de reserva
$_SESSION['busqueda'] = [
    'origen'       => $origen,
    'destino'      => $destino,
    'fecha_salida' => $fechaSalida,
    'tipo_viaje'   => $tipoViaje,
    'adultos'      => $adultos,
    'ninos'        => $ninos,
    'pasajeros'    => $pasajeros,
];

// ── Consulta de vuelos ──
$sql = "
    SELECT v.*,
           al.nombre AS aerolinea_nombre, al.logo_url AS aerolinea_logo,
           ap_o.codigo AS origen_codigo, ap_o.ciudad AS origen_ciudad,
           ap_d.codigo AS destino_codigo, ap_d.ciudad AS destino_ciudad,
           TIMESTAMPDIFF(MINUTE, v.fecha_salida, v.fecha_llegada) AS duracion_min,
           (v.precio_base + v.impuestos) AS precio_total
    FROM vuelos v
    JOIN aerolineas al  ON al.id  = v.aerolinea_id
    JOIN aeropuertos ap_o ON ap_o.id = v.origen_id
    JOIN aeropuertos ap_d ON ap_d.id = v.destino_id
    WHERE ap_o.codigo = ?
      AND ap_d.codigo = ?
      AND DATE(v.fecha_salida) = ?
      AND v.asientos_disponibles >= ?
      AND v.estado NOT IN ('cancelado','completado')
      AND (v.precio_base + v.impuestos) <= ?
";
$params = [$origen, $destino, $fechaSalida, $pasajeros, $precioMax];

if ($aerolinea) {
    $sql    .= ' AND al.codigo = ?';
    $params[] = $aerolinea;
}

$sql .= ' ORDER BY v.precio_base ASC';

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$vuelos = $stmt->fetchAll();

// ── Aerolíneas disponibles para el filtro ──
$aerolineas = getDB()
    ->query('SELECT codigo, nombre FROM aerolineas WHERE activo=1 ORDER BY nombre')
    ->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<main class="pt-24 pb-12 px-4 md:px-8 max-w-screen-2xl mx-auto min-h-screen">

  <!-- ── Barra de resumen ── -->
  <section class="mb-8 p-5 md:p-6 bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/10
                  flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div class="flex flex-wrap items-center gap-4 md:gap-6">
      <div class="flex items-center gap-3">
        <span class="material-symbols-outlined text-primary">flight_takeoff</span>
        <span class="text-primary font-bold text-lg tracking-tight">
          <?= e($origen) ?> → <?= e($destino) ?>
        </span>
      </div>
      <div class="hidden md:block h-8 w-px bg-outline-variant/30"></div>
      <div class="flex items-center gap-2 text-on-surface-variant font-medium text-sm">
        <span class="material-symbols-outlined text-sm">calendar_today</span>
        <span><?= fechaCorta($fechaSalida) ?></span>
      </div>
      <div class="hidden md:block h-8 w-px bg-outline-variant/30"></div>
      <div class="flex items-center gap-2 text-on-surface-variant font-medium text-sm">
        <span class="material-symbols-outlined text-sm">group</span>
        <span><?= $pasajeros ?> Pasajero<?= $pasajeros > 1 ? 's' : '' ?></span>
      </div>
    </div>
    <a href="/index.php?<?= http_build_query($_GET) ?>"
       class="flex items-center gap-2 px-5 py-2.5 bg-surface-container-high rounded-lg text-primary font-semibold text-sm hover:bg-surface-container-highest transition-colors">
      <span class="material-symbols-outlined text-sm">edit</span>
      Editar búsqueda
    </a>
  </section>

  <div class="flex flex-col md:grid md:grid-cols-12 gap-8">

    <!-- ── Sidebar de filtros ── -->
    <aside class="md:col-span-3 space-y-6">
      <form method="GET" id="filter-form">
        <!-- Preservar params de búsqueda -->
        <input type="hidden" name="origen"       value="<?= e($origen) ?>"/>
        <input type="hidden" name="destino"      value="<?= e($destino) ?>"/>
        <input type="hidden" name="fecha_salida" value="<?= e($fechaSalida) ?>"/>
        <input type="hidden" name="tipo_viaje"   value="<?= e($tipoViaje) ?>"/>
        <input type="hidden" name="adultos"      value="<?= $adultos ?>"/>
        <input type="hidden" name="ninos"        value="<?= $ninos ?>"/>

        <div class="p-6 bg-surface-container-low rounded-xl">
          <h3 class="text-primary font-bold text-base mb-6">Filtros</h3>

          <!-- Rango de precio -->
          <div class="mb-8">
            <label class="block text-on-surface-variant text-sm font-semibold mb-4">Precio máximo</label>
            <input type="range" name="precio_max" id="precio-range"
                   min="100" max="3000" step="50"
                   value="<?= $precioMax < 9999 ? $precioMax : 3000 ?>"
                   class="w-full h-2 bg-outline-variant/30 rounded-lg appearance-none cursor-pointer accent-secondary"/>
            <div class="flex justify-between mt-3 text-xs font-bold text-primary">
              <span>$100</span>
              <span id="precio-label">$<?= $precioMax < 9999 ? number_format($precioMax) : '3,000' ?></span>
            </div>
          </div>

          <!-- Aerolíneas -->
          <div class="mb-8">
            <label class="block text-on-surface-variant text-sm font-semibold mb-4 uppercase tracking-widest">Aerolíneas</label>
            <div class="space-y-3">
              <?php foreach ($aerolineas as $al): ?>
                <label class="flex items-center gap-3 cursor-pointer group">
                  <input type="checkbox" name="aerolinea[]" value="<?= e($al['codigo']) ?>"
                         <?= ($aerolinea === $al['codigo']) ? 'checked' : '' ?>
                         class="w-5 h-5 rounded border-outline-variant text-secondary focus:ring-secondary/20"/>
                  <span class="text-sm font-medium text-on-surface group-hover:text-primary transition-colors">
                    <?= e($al['nombre']) ?>
                  </span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Botón aplicar -->
          <button type="submit"
                  class="w-full py-2.5 bg-primary text-white rounded-lg font-bold text-sm hover:opacity-90 transition-all">
            Aplicar filtros
          </button>
        </div>
      </form>

      <!-- Spot promocional -->
      <div class="relative overflow-hidden rounded-xl h-56 flex items-end p-6 group">
        <img src="https://images.unsplash.com/photo-1515859005217-8a1f08870f59?w=600&q=80"
             alt="Ciudad de Nueva York"
             class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"/>
        <div class="absolute inset-0 bg-gradient-to-t from-primary/80 to-transparent"></div>
        <div class="relative z-10">
          <span class="text-white/80 text-xs font-bold uppercase tracking-widest mb-1 block">Oferta Exclusiva</span>
          <h4 class="text-white font-bold text-xl leading-tight">Explora la Gran Manzana</h4>
        </div>
      </div>
    </aside>

    <!-- ── Lista de resultados ── -->
    <section class="md:col-span-9 space-y-4">

      <?php if (empty($vuelos)): ?>
        <div class="bg-surface-container-low/50 border border-dashed border-outline-variant/30 rounded-xl p-12 flex flex-col items-center justify-center text-center">
          <span class="material-symbols-outlined text-5xl text-outline-variant mb-4">flight_off</span>
          <h4 class="text-primary font-bold text-xl mb-2">Sin resultados</h4>
          <p class="text-on-surface-variant text-sm max-w-md">
            No encontramos vuelos de <strong><?= e($origen) ?></strong> a <strong><?= e($destino) ?></strong>
            para el <strong><?= fechaCorta($fechaSalida) ?></strong>.
            Prueba con otras fechas o destinos.
          </p>
          <a href="/index.php" class="mt-6 bg-secondary text-white px-8 py-3 rounded-lg font-bold text-sm hover:opacity-90 transition-all">
            Nueva búsqueda
          </a>
        </div>

      <?php else: ?>
        <p class="text-on-surface-variant text-sm font-medium">
          <?= count($vuelos) ?> vuelo<?= count($vuelos) > 1 ? 's' : '' ?> encontrado<?= count($vuelos) > 1 ? 's' : '' ?>
        </p>

        <?php foreach ($vuelos as $v): ?>
          <div class="bg-surface-container-lowest hover:bg-white border border-transparent hover:border-outline-variant/20
                      transition-all rounded-xl overflow-hidden p-6">
            <div class="flex flex-col lg:flex-row items-center gap-6 lg:gap-8">

              <!-- Logo aerolínea -->
              <div class="w-28 flex flex-col items-center gap-2">
                <div class="w-16 h-16 bg-surface-container-low rounded-full flex items-center justify-center overflow-hidden border border-outline-variant/10 p-2">
                  <?php if ($v['aerolinea_logo']): ?>
                    <img src="<?= e($v['aerolinea_logo']) ?>" alt="<?= e($v['aerolinea_nombre']) ?>"
                         class="w-full h-full object-contain"/>
                  <?php else: ?>
                    <span class="material-symbols-outlined text-primary text-2xl">airlines</span>
                  <?php endif; ?>
                </div>
                <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest text-center">
                  <?= e($v['aerolinea_nombre']) ?>
                </span>
              </div>

              <!-- Información del vuelo -->
              <div class="flex-grow grid grid-cols-3 items-center gap-4 text-center">
                <div>
                  <p class="text-2xl font-black text-primary tracking-tighter"><?= hora($v['fecha_salida']) ?></p>
                  <p class="text-xs font-semibold text-on-surface-variant"><?= e($v['origen_codigo']) ?></p>
                  <p class="text-[10px] text-on-surface-variant"><?= e($v['origen_ciudad']) ?></p>
                </div>
                <div class="relative px-4">
                  <div class="absolute top-1/2 left-0 w-full h-[1px] bg-outline-variant/40 -translate-y-1/2"></div>
                  <div class="relative z-10 flex flex-col items-center bg-surface-container-lowest px-2">
                    <span class="text-[10px] font-bold text-secondary uppercase tracking-widest mb-1">
                      <?= duracion((int)$v['duracion_min']) ?>
                    </span>
                    <span class="material-symbols-outlined text-primary text-base">flight_takeoff</span>
                    <span class="text-[10px] font-medium text-on-surface-variant mt-1">Directo</span>
                  </div>
                </div>
                <div>
                  <p class="text-2xl font-black text-primary tracking-tighter"><?= hora($v['fecha_llegada']) ?></p>
                  <p class="text-xs font-semibold text-on-surface-variant"><?= e($v['destino_codigo']) ?></p>
                  <p class="text-[10px] text-on-surface-variant"><?= e($v['destino_ciudad']) ?></p>
                </div>
              </div>

              <div class="hidden lg:block h-full w-px bg-outline-variant/20 self-stretch"></div>

              <!-- Precio y CTA -->
              <div class="w-full lg:w-52 flex flex-col items-center lg:items-end justify-center gap-1">
                <div class="flex items-baseline gap-1">
                  <span class="text-sm font-bold text-primary">$</span>
                  <span class="text-3xl font-black text-primary tracking-tighter">
                    <?= number_format($v['precio_total'], 0) ?>
                  </span>
                  <span class="text-xs font-bold text-on-surface-variant">USD</span>
                </div>
                <div class="text-[10px] text-on-surface-variant mb-1">por persona</div>
                <?php if ($v['asientos_disponibles'] < 15): ?>
                  <div class="flex items-center gap-1 text-error text-[10px] font-bold uppercase tracking-wider mb-2">
                    <span class="material-symbols-outlined text-sm">warning</span>
                    <?= $v['asientos_disponibles'] ?> asientos disponibles
                  </div>
                <?php endif; ?>
                <a href="/asientos.php?vuelo_id=<?= $v['id'] ?>"
                   class="w-full py-3 bg-secondary text-white rounded-lg font-bold text-sm text-center
                          hover:shadow-lg hover:shadow-secondary/20 transition-all uppercase tracking-widest">
                  Seleccionar
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

      <?php endif; ?>

    </section>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
  // Actualizar etiqueta del slider de precio
  const range = document.getElementById('precio-range');
  const label = document.getElementById('precio-label');
  if (range) {
    range.addEventListener('input', () => {
      label.textContent = '$' + parseInt(range.value).toLocaleString();
    });
  }
</script>
