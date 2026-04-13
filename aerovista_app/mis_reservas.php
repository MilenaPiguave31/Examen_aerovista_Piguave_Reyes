<?php
/**
 * AeroVista · Mis Reservas
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!estaLogueado()) {
    header('Location: /login.php?redirect=' . urlencode('/mis_reservas.php'));
    exit;
}

$pageTitle = 'Mis Reservas';
$activeNav = 'reservas';

$reservas = getDB()->prepare("
    SELECT r.codigo_pnr, r.precio_total, r.estado, r.tipo_viaje, r.created_at,
           v.numero_vuelo, v.fecha_salida, v.fecha_llegada,
           al.nombre AS aerolinea,
           ap_o.codigo AS origen, ap_o.ciudad AS origen_ciudad,
           ap_d.codigo AS destino, ap_d.ciudad AS destino_ciudad,
           (SELECT COUNT(*) FROM pasajeros p WHERE p.reserva_id=r.id) AS num_pasajeros,
           (SELECT GROUP_CONCAT(a.numero ORDER BY a.numero SEPARATOR ', ')
            FROM reserva_asientos ra
            JOIN asientos a ON a.id=ra.asiento_id
            WHERE ra.reserva_id=r.id) AS asientos
    FROM reservas r
    JOIN vuelos v ON v.id=r.vuelo_id
    JOIN aerolineas al ON al.id=v.aerolinea_id
    JOIN aeropuertos ap_o ON ap_o.id=v.origen_id
    JOIN aeropuertos ap_d ON ap_d.id=v.destino_id
    WHERE r.usuario_id=?
    ORDER BY r.created_at DESC
");
$reservas->execute([$_SESSION['usuario_id']]);
$misReservas = $reservas->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<main class="pt-24 pb-16 px-4 md:px-8 max-w-5xl mx-auto min-h-screen">

  <header class="mb-10">
    <h1 class="text-4xl font-black text-primary tracking-tight mb-2">Mis Reservas</h1>
    <p class="text-on-surface-variant">
      Hola <?= e($_SESSION['usuario_nombre']) ?> — tienes <?= count($misReservas) ?> reserva<?= count($misReservas) !== 1 ? 's' : '' ?>.
    </p>
  </header>

  <?php flashRender(); ?>

  <?php if (empty($misReservas)): ?>
    <div class="flex flex-col items-center justify-center py-20 text-center">
      <span class="material-symbols-outlined text-6xl text-outline-variant mb-6">luggage</span>
      <h2 class="text-2xl font-bold text-primary mb-3">Aún no tienes reservas</h2>
      <p class="text-on-surface-variant mb-8 max-w-md">
        Busca y reserva tu próximo vuelo para verlo aquí.
      </p>
      <a href="/index.php"
         class="bg-secondary text-white px-10 py-4 rounded-xl font-bold hover:opacity-90 transition-all shadow-lg shadow-secondary/20">
        Buscar Vuelos
      </a>
    </div>

  <?php else: ?>
    <div class="space-y-5">
      <?php foreach ($misReservas as $r):
        $isPast   = strtotime($r['fecha_salida']) < time();
        $isCancelled = $r['estado'] === 'cancelada';
      ?>
        <div class="bg-surface-container-lowest rounded-2xl overflow-hidden shadow-sm border border-outline-variant/10
                    <?= $isCancelled ? 'opacity-60' : '' ?>">
          <!-- Cabecera de la tarjeta -->
          <div class="flex flex-col md:flex-row md:items-center justify-between p-6 gap-4 border-b border-surface-container">
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center">
                <span class="material-symbols-outlined text-primary">flight_takeoff</span>
              </div>
              <div>
                <div class="flex items-center gap-3 mb-1">
                  <span class="font-mono font-bold text-secondary-container text-sm"><?= e($r['codigo_pnr']) ?></span>
                  <?= badgeEstado($r['estado']) ?>
                </div>
                <p class="font-black text-primary text-lg tracking-tight">
                  <?= e($r['origen']) ?> → <?= e($r['destino']) ?>
                </p>
                <p class="text-on-surface-variant text-xs"><?= e($r['aerolinea']) ?> · <?= e($r['numero_vuelo']) ?></p>
              </div>
            </div>
            <div class="text-right">
              <p class="text-2xl font-black text-primary"><?= precio((float)$r['precio_total']) ?></p>
              <p class="text-xs text-on-surface-variant"><?= $r['num_pasajeros'] ?> pasajero<?= $r['num_pasajeros']>1?'s':'' ?></p>
            </div>
          </div>

          <!-- Detalles del vuelo -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-6 p-6">
            <div>
              <p class="text-[10px] uppercase font-bold text-outline tracking-widest mb-1">Fecha de salida</p>
              <p class="font-bold text-primary text-sm"><?= fechaCorta($r['fecha_salida']) ?></p>
              <p class="text-on-surface-variant text-xs"><?= hora($r['fecha_salida']) ?></p>
            </div>
            <div>
              <p class="text-[10px] uppercase font-bold text-outline tracking-widest mb-1">Llegada</p>
              <p class="font-bold text-primary text-sm"><?= fechaCorta($r['fecha_llegada']) ?></p>
              <p class="text-on-surface-variant text-xs"><?= hora($r['fecha_llegada']) ?></p>
            </div>
            <?php if ($r['asientos']): ?>
              <div>
                <p class="text-[10px] uppercase font-bold text-outline tracking-widest mb-1">Asientos</p>
                <p class="font-bold text-secondary text-sm"><?= e($r['asientos']) ?></p>
              </div>
            <?php endif; ?>
            <div>
              <p class="text-[10px] uppercase font-bold text-outline tracking-widest mb-1">Reservado el</p>
              <p class="font-bold text-primary text-sm"><?= fechaCorta($r['created_at']) ?></p>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- CTA nueva búsqueda -->
  <div class="mt-12 text-center">
    <a href="/index.php"
       class="inline-flex items-center gap-2 bg-primary text-white px-8 py-4 rounded-xl font-bold hover:opacity-90 transition-all">
      <span class="material-symbols-outlined">search</span>
      Buscar Nuevo Vuelo
    </a>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
