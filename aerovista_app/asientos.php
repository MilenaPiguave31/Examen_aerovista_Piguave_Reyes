<?php
/**
 * AeroVista · Selección de Asientos
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Selección de Asientos';

$vueloId = (int)($_GET['vuelo_id'] ?? 0);
if (!$vueloId) { header('Location: /'); exit; }

// Cargar vuelo
$stmt = getDB()->prepare("
    SELECT v.*,
           al.nombre AS aerolinea, al.logo_url,
           ap_o.codigo AS origen_codigo, ap_o.ciudad AS origen_ciudad,
           ap_d.codigo AS destino_codigo, ap_d.ciudad AS destino_ciudad,
           (v.precio_base + v.impuestos) AS precio_total
    FROM vuelos v
    JOIN aerolineas al    ON al.id   = v.aerolinea_id
    JOIN aeropuertos ap_o ON ap_o.id = v.origen_id
    JOIN aeropuertos ap_d ON ap_d.id = v.destino_id
    WHERE v.id = ?
");
$stmt->execute([$vueloId]);
$vuelo = $stmt->fetch();
if (!$vuelo) { header('Location: /'); exit; }

// Guardar vuelo en sesión
$_SESSION['vuelo_id'] = $vueloId;

// Cargar asientos agrupados por fila
$stmtA = getDB()->prepare("
    SELECT * FROM asientos WHERE vuelo_id = ? ORDER BY fila ASC, columna ASC
");
$stmtA->execute([$vueloId]);
$asientosRaw = $stmtA->fetchAll();

// Agrupar por fila
$filas = [];
foreach ($asientosRaw as $a) {
    $filas[$a['fila']][$a['columna']] = $a;
}
ksort($filas);

$busqueda  = $_SESSION['busqueda'] ?? [];
$pasajeros = (int)($busqueda['pasajeros'] ?? 1);

include __DIR__ . '/includes/header.php';
?>

<main class="pt-24 pb-12 px-4 md:px-8 max-w-7xl mx-auto min-h-screen">

  <!-- Breadcrumb / Stepper -->
  <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
      <h1 class="text-3xl font-extrabold text-primary tracking-tight">Selección de Asientos</h1>
      <p class="text-on-surface-variant text-sm mt-1">
        Vuelo <?= e($vuelo['origen_codigo']) ?>–<?= e($vuelo['destino_codigo']) ?>
        &nbsp;·&nbsp; <?= e($vuelo['avion'] ?? 'Avión comercial') ?>
      </p>
    </div>
    <!-- Stepper -->
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-2">
        <span class="flex items-center justify-center w-7 h-7 rounded-full bg-surface-container-high text-on-surface-variant text-[11px] font-bold">✓</span>
        <span class="text-xs font-medium text-on-surface-variant">Vuelo</span>
      </div>
      <div class="h-px w-8 bg-outline-variant/30"></div>
      <div class="flex items-center gap-2">
        <span class="flex items-center justify-center w-7 h-7 rounded-full bg-primary text-white text-[11px] font-bold">2</span>
        <span class="text-xs font-bold text-primary">Asientos</span>
      </div>
      <div class="h-px w-8 bg-outline-variant/30"></div>
      <div class="flex items-center gap-2">
        <span class="flex items-center justify-center w-7 h-7 rounded-full border border-outline-variant text-on-surface-variant text-[11px] font-bold">3</span>
        <span class="text-xs font-medium text-on-surface-variant">Pago</span>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

    <!-- ── Mapa de cabina ── -->
    <div class="lg:col-span-8 bg-surface-container-low rounded-3xl p-6 md:p-10 overflow-hidden">

      <!-- Leyenda -->
      <div class="flex flex-wrap justify-center gap-6 mb-8">
        <div class="flex items-center gap-2">
          <div class="w-5 h-5 rounded bg-white border border-outline-variant/30"></div>
          <span class="text-xs font-medium text-on-surface-variant">Disponible</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-5 h-5 rounded bg-outline-variant"></div>
          <span class="text-xs font-medium text-on-surface-variant">Ocupado</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-5 h-5 rounded bg-primary"></div>
          <span class="text-xs font-medium text-on-surface-variant">Seleccionado</span>
        </div>
        <div class="flex items-center gap-2">
          <div class="w-5 h-5 rounded bg-secondary-container/20 border-2 border-secondary-container/50"></div>
          <span class="text-xs font-medium text-on-surface-variant">Salida Emergencia</span>
        </div>
      </div>

      <!-- Cuerpo del avión -->
      <div class="relative max-w-xs mx-auto bg-white rounded-t-[180px] rounded-b-[30px] pt-20 pb-10 shadow-inner border border-outline-variant/10">
        <!-- Cabina -->
        <div class="absolute top-2 left-1/2 -translate-x-1/2 w-24 h-14 bg-surface-container-highest rounded-t-full flex items-center justify-center">
          <span class="material-symbols-outlined text-primary/20 text-2xl">flight</span>
        </div>

        <!-- Columnas header -->
        <div class="px-6">
          <div class="grid grid-cols-7 gap-1 mb-3 text-center">
            <?php foreach (['A','B','C','','D','E','F'] as $col): ?>
              <span class="text-[9px] font-bold text-outline uppercase tracking-tighter <?= $col === '' ? 'italic' : '' ?>">
                <?= $col === '' ? 'Pas' : $col ?>
              </span>
            <?php endforeach; ?>
          </div>

          <!-- Filas de asientos -->
          <div class="flex flex-col gap-1.5 max-h-96 overflow-y-auto no-scrollbar">
            <?php foreach ($filas as $numFila => $cols): ?>
              <?php $esEmergencia = ($numFila === 12); ?>
              <div class="grid grid-cols-7 gap-1 items-center <?= $esEmergencia ? 'bg-secondary-container/5 -mx-1 px-1 py-1 rounded' : '' ?>">
                <?php foreach (['A','B','C',null,'D','E','F'] as $col): ?>
                  <?php if ($col === null): ?>
                    <span class="text-[9px] font-bold text-outline-variant text-center"><?= $numFila ?></span>
                  <?php else:
                    $asiento = $cols[$col] ?? null;
                    if (!$asiento) continue;
                    $isOcupado   = $asiento['estado'] === 'ocupado';
                    $isEmerg     = (bool)$asiento['es_emergencia'];
                    $precioExtra = (float)$asiento['precio_extra'];
                    $data = json_encode([
                        'id'      => $asiento['id'],
                        'numero'  => $asiento['numero'],
                        'extra'   => $precioExtra,
                        'emerg'   => $isEmerg,
                        'tipo'    => $asiento['tipo'],
                    ]);
                    ?>
                    <button
                      type="button"
                      class="seat-btn aspect-square rounded text-[8px] font-bold transition-all
                        <?php if ($isOcupado): ?>
                          bg-outline-variant cursor-not-allowed opacity-40
                        <?php elseif ($isEmerg): ?>
                          bg-white border-2 border-secondary-container/40 hover:border-secondary-container hover:bg-secondary-container/10
                        <?php else: ?>
                          bg-white border border-outline-variant/30 hover:border-primary hover:bg-primary/5
                        <?php endif; ?>"
                      data-asiento='<?= $data ?>'
                      <?= $isOcupado ? 'disabled' : '' ?>
                      title="Asiento <?= e($asiento['numero']) ?><?= $precioExtra > 0 ? ' (+$'.number_format($precioExtra,0).')' : '' ?>">
                    </button>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
              <?php if ($esEmergencia): ?>
                <div class="flex justify-between px-2 mb-1">
                  <span class="text-[8px] uppercase font-bold text-secondary-container/60 tracking-widest">Salida Emergencia</span>
                  <span class="text-[8px] uppercase font-bold text-secondary-container/60 tracking-widest">Salida Emergencia</span>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Panel de resumen ── -->
    <div class="lg:col-span-4 flex flex-col gap-6 sticky top-24">
      <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm border border-outline-variant/10">
        <h2 class="text-xl font-bold text-primary mb-5">Resumen de Selección</h2>

        <!-- Info del vuelo -->
        <div class="flex items-center gap-4 pb-5 border-b border-outline-variant/10 mb-5">
          <div class="w-12 h-12 bg-surface-container-low rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-primary">flight_takeoff</span>
          </div>
          <div>
            <p class="text-[10px] uppercase font-bold text-on-surface-variant tracking-widest">Trayecto</p>
            <p class="text-sm font-semibold text-primary">
              <?= e($vuelo['origen_codigo']) ?> – <?= e($vuelo['destino_codigo']) ?>
            </p>
            <p class="text-xs text-on-surface-variant"><?= fechaCorta($vuelo['fecha_salida']) ?></p>
          </div>
        </div>

        <!-- Asientos seleccionados (dinámico) -->
        <div id="selected-seats-panel" class="mb-5">
          <p class="text-[10px] uppercase font-bold text-on-surface-variant tracking-widest mb-2">
            Asientos Seleccionados (<?= $pasajeros ?> necesario<?= $pasajeros > 1 ? 's' : '' ?>)
          </p>
          <div id="selected-list" class="text-sm text-on-surface-variant">
            <em>Ninguno seleccionado aún</em>
          </div>
        </div>

        <!-- Totales -->
        <div class="pt-5 border-t border-outline-variant/10">
          <div class="flex justify-between items-end mb-5">
            <p class="text-sm font-medium text-on-surface-variant">Precio Total</p>
            <div class="text-right">
              <p class="text-2xl font-black text-primary" id="total-price">
                $<?= number_format($vuelo['precio_total'] * $pasajeros, 2) ?>
              </p>
              <p class="text-[10px] text-on-surface-variant">Incluye tasas e impuestos</p>
            </div>
          </div>
          <form action="/checkout.php" method="POST" id="asientos-form">
            <?= csrfField() ?>
            <input type="hidden" name="vuelo_id"   value="<?= $vueloId ?>"/>
            <input type="hidden" name="asientos_json" id="asientos_json" value="[]"/>
            <button type="submit" id="confirmar-btn" disabled
                    class="w-full bg-secondary text-white font-bold py-4 rounded-xl
                           hover:opacity-90 active:scale-[0.98] transition-all
                           shadow-lg shadow-secondary/20 disabled:opacity-40 disabled:cursor-not-allowed">
              Confirmar Asientos
            </button>
          </form>
          <a href="/resultados.php?<?= http_build_query($busqueda) ?>"
             class="block w-full mt-3 py-3 text-sm font-semibold text-center text-on-surface-variant hover:text-primary transition-colors">
            Volver al paso anterior
          </a>
        </div>
      </div>

      <!-- Promo upgrade -->
      <div class="bg-primary p-6 rounded-3xl text-white relative overflow-hidden">
        <div class="relative z-10">
          <h3 class="font-bold text-lg leading-tight mb-2">¿Necesitas más espacio?</h3>
          <p class="text-xs text-on-primary-container leading-relaxed mb-3">
            Mejora a Business Class por solo $89 más y disfruta de embarque prioritario y menú gourmet.
          </p>
          <button type="button" class="text-xs font-bold border-b border-white pb-1">Ver ofertas de Upgrade</button>
        </div>
        <div class="absolute -right-4 -bottom-4 opacity-10">
          <span class="material-symbols-outlined text-[120px]">workspace_premium</span>
        </div>
      </div>
    </div>

  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
  const MAX_SEATS    = <?= $pasajeros ?>;
  const BASE_PRICE   = <?= $vuelo['precio_total'] * $pasajeros ?>;
  let selected       = [];   // [{id, numero, extra, emerg}]

  const selectedList  = document.getElementById('selected-list');
  const totalPrice    = document.getElementById('total-price');
  const confirmarBtn  = document.getElementById('confirmar-btn');
  const asientosJson  = document.getElementById('asientos_json');

  document.querySelectorAll('.seat-btn:not([disabled])').forEach(btn => {
    btn.addEventListener('click', () => {
      const data = JSON.parse(btn.dataset.asiento);
      const idx  = selected.findIndex(s => s.id === data.id);

      if (idx >= 0) {
        // Deseleccionar
        selected.splice(idx, 1);
        btn.classList.remove('bg-primary', 'text-white', 'ring-2', 'ring-primary');
        btn.classList.add('bg-white', 'border', 'border-outline-variant/30');
      } else {
        // Seleccionar
        if (selected.length >= MAX_SEATS) {
          alert('Ya seleccionaste ' + MAX_SEATS + ' asiento(s). Deselecciona uno primero.');
          return;
        }
        selected.push(data);
        btn.classList.remove('bg-white', 'border', 'border-outline-variant/30');
        btn.classList.add('bg-primary', 'text-white', 'ring-2', 'ring-primary', 'ring-offset-1');
        btn.textContent = data.numero;
      }
      updatePanel();
    });
  });

  function updatePanel() {
    if (selected.length === 0) {
      selectedList.innerHTML = '<em>Ninguno seleccionado aún</em>';
    } else {
      selectedList.innerHTML = selected.map(s =>
        `<div class="flex justify-between py-1">
          <span class="font-bold text-primary">${s.numero}${s.emerg ? ' <span class="text-[9px] text-secondary-container font-bold">EMERG</span>' : ''}</span>
          ${s.extra > 0 ? `<span class="text-secondary text-xs font-bold">+$${s.extra.toFixed(2)}</span>` : '<span class="text-xs text-on-surface-variant">Incluido</span>'}
        </div>`
      ).join('');
    }

    const extraTotal = selected.reduce((sum, s) => sum + s.extra, 0);
    totalPrice.textContent = '$' + (BASE_PRICE + extraTotal).toLocaleString('en-US', {minimumFractionDigits:2});

    confirmarBtn.disabled = selected.length !== MAX_SEATS;
    asientosJson.value = JSON.stringify(selected);
  }
</script>
