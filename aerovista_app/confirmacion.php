<?php
/**
 * AeroVista · Confirmación de Reserva (Boarding Pass)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = '¡Reserva Confirmada!';

$pnr      = $_SESSION['reserva_pnr'] ?? '';
$reservaId = (int)($_SESSION['reserva_id'] ?? 0);

if (!$pnr) { header('Location: /'); exit; }

// Cargar datos de la reserva
$stmt = getDB()->prepare("
    SELECT r.*,
           v.numero_vuelo, v.fecha_salida, v.fecha_llegada, v.avion,
           al.nombre AS aerolinea,
           ap_o.codigo AS origen, ap_o.ciudad AS origen_ciudad,
           ap_d.codigo AS destino, ap_d.ciudad AS destino_ciudad
    FROM reservas r
    JOIN vuelos v        ON v.id  = r.vuelo_id
    JOIN aerolineas al   ON al.id = v.aerolinea_id
    JOIN aeropuertos ap_o ON ap_o.id = v.origen_id
    JOIN aeropuertos ap_d ON ap_d.id = v.destino_id
    WHERE r.codigo_pnr = ?
");
$stmt->execute([$pnr]);
$reserva = $stmt->fetch();
if (!$reserva) { header('Location: /'); exit; }

// Pasajeros y asientos
$stmtP = getDB()->prepare("
    SELECT p.nombre, p.apellido, p.tipo_pasajero, a.numero AS asiento
    FROM pasajeros p
    LEFT JOIN reserva_asientos ra ON ra.pasajero_id = p.id
    LEFT JOIN asientos a          ON a.id = ra.asiento_id
    WHERE p.reserva_id = ?
");
$stmtP->execute([$reservaId]);
$pasajerosList = $stmtP->fetchAll();

// Limpiar sesión de reserva
unset($_SESSION['reserva_pnr'], $_SESSION['reserva_id']);

include __DIR__ . '/includes/header.php';
?>

<main class="pt-24 pb-20 px-6 max-w-4xl mx-auto">

  <!-- Header de éxito -->
  <div class="flex flex-col items-center text-center mb-12">
    <div class="w-20 h-20 bg-emerald-100 rounded-full flex items-center justify-center mb-6 animate-bounce">
      <span class="material-symbols-outlined text-emerald-600 text-5xl" style="font-variation-settings:'wght' 700">check_circle</span>
    </div>
    <h1 class="text-4xl md:text-5xl font-black text-primary tracking-tight mb-3">¡Reserva Confirmada!</h1>
    <p class="text-on-surface-variant font-medium max-w-lg">
      Tu viaje con AeroVista está listo. Hemos enviado los detalles a
      <strong><?= e($reserva['email_contacto']) ?></strong>.
    </p>
  </div>

  <!-- ── Boarding Pass Digital ── -->
  <div class="relative">
    <div class="absolute inset-0 bg-primary-container/5 rounded-[2rem] transform rotate-1 scale-105"></div>
    <div class="relative bg-surface-container-lowest rounded-[2rem] overflow-hidden shadow-2xl shadow-primary/5 border border-outline-variant/10">

      <!-- Cabecera del ticket -->
      <div class="bg-primary p-8 md:p-10 text-white flex justify-between items-start">
        <div>
          <p class="text-on-primary-container text-xs font-bold uppercase tracking-widest mb-1">Boarding Pass</p>
          <h2 class="text-2xl font-bold tracking-tighter"><?= e($reserva['aerolinea']) ?></h2>
          <p class="text-on-primary-container text-sm mt-1"><?= e($reserva['numero_vuelo']) ?></p>
        </div>
        <div class="text-right">
          <p class="text-on-primary-container text-xs font-bold uppercase tracking-widest mb-1">Reserva (PNR)</p>
          <p class="font-mono text-3xl font-bold text-secondary-container"><?= e($pnr) ?></p>
        </div>
      </div>

      <!-- Ruta del vuelo -->
      <div class="p-8 md:p-10">
        <div class="flex justify-between items-center mb-10">
          <div class="flex-1">
            <span class="text-on-surface-variant text-xs font-bold uppercase tracking-widest block mb-2">Origen</span>
            <h3 class="text-5xl font-black text-primary"><?= e($reserva['origen']) ?></h3>
            <p class="text-on-surface-variant text-sm"><?= e($reserva['origen_ciudad']) ?></p>
          </div>
          <div class="flex flex-col items-center px-6 flex-1">
            <span class="material-symbols-outlined text-secondary text-3xl mb-2">flight_takeoff</span>
            <div class="w-full h-px border-t border-dashed border-outline-variant relative">
              <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-surface-container-lowest px-2">
                <span class="text-[10px] font-bold text-outline uppercase tracking-tighter">Directo</span>
              </div>
            </div>
          </div>
          <div class="flex-1 text-right">
            <span class="text-on-surface-variant text-xs font-bold uppercase tracking-widest block mb-2">Destino</span>
            <h3 class="text-5xl font-black text-primary"><?= e($reserva['destino']) ?></h3>
            <p class="text-on-surface-variant text-sm"><?= e($reserva['destino_ciudad']) ?></p>
          </div>
        </div>

        <!-- Detalles del vuelo -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 py-6 border-t border-surface-container">
          <div>
            <p class="text-outline text-[10px] font-bold uppercase tracking-widest">Fecha</p>
            <p class="text-primary font-bold mt-1"><?= fechaCorta($reserva['fecha_salida']) ?></p>
          </div>
          <div>
            <p class="text-outline text-[10px] font-bold uppercase tracking-widest">Embarque</p>
            <p class="text-primary font-bold mt-1">
              <?= date('H:i', strtotime($reserva['fecha_salida']) - 2700) ?> <!-- 45 min antes -->
            </p>
          </div>
          <div>
            <p class="text-outline text-[10px] font-bold uppercase tracking-widest">Salida</p>
            <p class="text-primary font-bold mt-1"><?= hora($reserva['fecha_salida']) ?></p>
          </div>
          <div>
            <p class="text-outline text-[10px] font-bold uppercase tracking-widest">Llegada</p>
            <p class="text-primary font-bold mt-1"><?= hora($reserva['fecha_llegada']) ?></p>
          </div>
        </div>

        <!-- Pasajeros y QR -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pt-6 border-t border-surface-container items-center">
          <div class="md:col-span-2 space-y-4">
            <?php foreach ($pasajerosList as $p): ?>
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center">
                  <span class="material-symbols-outlined text-primary text-lg">person</span>
                </div>
                <div>
                  <p class="text-xs text-outline font-medium">Pasajero</p>
                  <p class="text-primary font-semibold text-sm">
                    <?= e($p['nombre'] . ' ' . $p['apellido']) ?>
                    <?php if ($p['asiento']): ?>
                      <span class="ml-2 px-2 py-0.5 bg-primary/10 text-primary text-xs font-bold rounded">
                        Asiento <?= e($p['asiento']) ?>
                      </span>
                    <?php endif; ?>
                  </p>
                </div>
              </div>
            <?php endforeach; ?>

            <!-- Totales -->
            <div class="flex items-center gap-3 pt-2">
              <div class="w-10 h-10 rounded-full bg-surface-container flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-lg">payments</span>
              </div>
              <div>
                <p class="text-xs text-outline font-medium">Total Pagado</p>
                <p class="text-primary font-semibold text-sm"><?= precio((float)$reserva['precio_total']) ?> USD</p>
              </div>
            </div>
          </div>

          <!-- QR simulado -->
          <div class="flex flex-col items-center justify-center p-4 bg-surface-container-low rounded-2xl">
            <div class="bg-white p-3 rounded-xl shadow-sm mb-2">
              <!-- QR generado con API pública -->
              <img src="https://api.qrserver.com/v1/create-qr-code/?size=96x96&data=AEROVISTA-<?= urlencode($pnr) ?>"
                   alt="QR Code" class="w-24 h-24"/>
            </div>
            <p class="text-[10px] font-bold text-outline uppercase tracking-widest">Escanear para embarque</p>
          </div>
        </div>
      </div>

      <!-- Efecto de perforación del ticket -->
      <div class="relative h-6 bg-surface-container-lowest border-t border-dashed border-outline-variant/30">
        <div class="absolute top-0 left-0 w-full h-full flex justify-between">
          <div class="w-6 h-6 rounded-full bg-surface -ml-3 border border-outline-variant/20"></div>
          <div class="w-6 h-6 rounded-full bg-surface -mr-3 border border-outline-variant/20"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Botones de acción -->
  <div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center items-center">
    <button onclick="window.print()"
            class="w-full sm:w-auto bg-secondary text-white px-8 py-4 rounded-xl font-bold flex items-center justify-center gap-2
                   hover:brightness-110 transition-all shadow-lg shadow-secondary/20">
      <span class="material-symbols-outlined">print</span>
      Imprimir Ticket
    </button>
    <a href="/mis_reservas.php"
       class="w-full sm:w-auto bg-surface-container-low text-primary px-8 py-4 rounded-xl font-bold flex items-center justify-center gap-2
              hover:bg-surface-container transition-all border border-outline-variant/20">
      <span class="material-symbols-outlined">luggage</span>
      Ver Mis Reservas
    </a>
    <a href="/index.php"
       class="w-full sm:w-auto bg-primary text-white px-8 py-4 rounded-xl font-bold flex items-center justify-center gap-2
              hover:bg-primary-container transition-all">
      <span class="material-symbols-outlined">home</span>
      Volver al Inicio
    </a>
  </div>

  <!-- Sección de ayuda -->
  <div class="mt-16 bg-surface-container-low p-8 rounded-3xl text-center">
    <p class="text-on-surface-variant text-sm mb-4">¿Necesitas ayuda con tu reserva o quieres realizar cambios?</p>
    <div class="flex flex-wrap justify-center gap-6">
      <a href="#" class="flex items-center gap-2 text-primary font-bold text-sm hover:underline">
        <span class="material-symbols-outlined text-lg">support_agent</span>
        Centro de Ayuda
      </a>
      <a href="#" class="flex items-center gap-2 text-primary font-bold text-sm hover:underline">
        <span class="material-symbols-outlined text-lg">mail</span>
        Contáctanos
      </a>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
