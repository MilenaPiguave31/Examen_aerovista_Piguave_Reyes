<?php
/**
 * AeroVista · Pantalla de Inicio / Buscador de Vuelos
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Vuela con Precisión';

// Cargar aeropuertos para el autocompletado
$aeropuertos = getDB()
    ->query('SELECT codigo, ciudad, nombre, pais FROM aeropuertos WHERE activo=1 ORDER BY ciudad')
    ->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<main class="relative min-h-screen flex flex-col pt-16">

  <!-- ── Hero con overlay ── -->
  <section class="absolute inset-0 w-full h-[870px] z-0">
    <div class="absolute inset-0 bg-primary/60 mix-blend-multiply z-10"></div>
    <img
      src="https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=1920&q=80"
      alt="Avión surcando el cielo al atardecer"
      class="w-full h-full object-cover"
    />
  </section>

  <!-- ── Contenido principal sobre el hero ── -->
  <div class="relative z-20 flex-1 flex flex-col items-center justify-center px-6 text-center">

    <!-- Titular editorial -->
    <div class="mb-12">
      <h1 class="text-white text-5xl md:text-7xl font-extrabold tracking-tight mb-4">
        Precision in every <span class="text-secondary-container">flight</span>
      </h1>
      <p class="text-white/80 text-xl font-light max-w-2xl mx-auto">
        Experimenta el futuro de las reservas de aviación con el sistema de navegación avanzado de AeroVista.
      </p>
    </div>

    <!-- ── Tarjeta de búsqueda principal ── -->
    <div class="w-full max-w-6xl bg-surface-container-lowest rounded-xl shadow-2xl p-8 md:p-10">
      <form action="/resultados.php" method="GET" class="flex flex-col gap-8" id="search-form">

        <!-- Tipo de viaje -->
        <div class="flex justify-start gap-6 border-b border-outline-variant/20 pb-4">
          <label class="flex items-center gap-2 cursor-pointer group">
            <input type="radio" name="tipo_viaje" value="ida_vuelta" checked
                   class="w-4 h-4 text-secondary focus:ring-secondary border-outline-variant"/>
            <span class="text-sm font-semibold text-on-surface group-hover:text-secondary transition-colors">Ida y vuelta</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer group">
            <input type="radio" name="tipo_viaje" value="ida"
                   class="w-4 h-4 text-secondary focus:ring-secondary border-outline-variant"/>
            <span class="text-sm font-semibold text-on-surface-variant group-hover:text-secondary transition-colors">Solo ida</span>
          </label>
        </div>

        <!-- Grid de búsqueda -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">

          <!-- Origen & Destino -->
          <div class="md:col-span-5 grid grid-cols-2 gap-2 bg-surface-container-low p-2 rounded-lg">
            <div class="flex flex-col text-left p-3 bg-surface-container-lowest rounded-lg">
              <span class="text-[10px] uppercase font-bold text-outline tracking-wider">Origen</span>
              <div class="flex items-center gap-2 mt-1">
                <span class="material-symbols-outlined text-primary text-xl">flight_takeoff</span>
                <input type="text" name="origen" id="origen" required
                       placeholder="Ciudad o código IATA"
                       autocomplete="off"
                       class="font-bold text-primary bg-transparent border-none outline-none w-full text-sm"
                       list="aeropuertos-origen"/>
              </div>
            </div>
            <div class="flex flex-col text-left p-3 bg-surface-container-lowest rounded-lg relative">
              <span class="text-[10px] uppercase font-bold text-outline tracking-wider">Destino</span>
              <div class="flex items-center gap-2 mt-1">
                <span class="material-symbols-outlined text-primary text-xl">flight_land</span>
                <input type="text" name="destino" id="destino" required
                       placeholder="Ciudad o código IATA"
                       autocomplete="off"
                       class="font-bold text-primary bg-transparent border-none outline-none w-full text-sm"
                       list="aeropuertos-destino"/>
              </div>
              <!-- Botón intercambiar -->
              <button type="button" id="swap-btn"
                      class="absolute -left-4 top-1/2 -translate-y-1/2 bg-white border border-outline-variant/30 rounded-full p-1.5 shadow-md text-primary hover:text-secondary transition-colors">
                <span class="material-symbols-outlined text-sm leading-none">swap_horiz</span>
              </button>
            </div>
          </div>

          <!-- Fechas -->
          <div class="md:col-span-4 grid grid-cols-2 gap-2 bg-surface-container-low p-2 rounded-lg">
            <div class="flex flex-col text-left p-3 bg-surface-container-lowest rounded-lg">
              <span class="text-[10px] uppercase font-bold text-outline tracking-wider">Salida</span>
              <div class="flex items-center gap-2 mt-1">
                <span class="material-symbols-outlined text-primary text-xl">calendar_month</span>
                <input type="date" name="fecha_salida" id="fecha_salida" required
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                       value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                       class="font-bold text-primary bg-transparent border-none outline-none w-full text-sm"/>
              </div>
            </div>
            <div class="flex flex-col text-left p-3 bg-surface-container-lowest rounded-lg" id="fecha-regreso-box">
              <span class="text-[10px] uppercase font-bold text-outline tracking-wider">Regreso</span>
              <div class="flex items-center gap-2 mt-1">
                <span class="material-symbols-outlined text-primary text-xl">calendar_month</span>
                <input type="date" name="fecha_regreso" id="fecha_regreso"
                       min="<?= date('Y-m-d', strtotime('+2 days')) ?>"
                       value="<?= date('Y-m-d', strtotime('+14 days')) ?>"
                       class="font-bold text-primary bg-transparent border-none outline-none w-full text-sm"/>
              </div>
            </div>
          </div>

          <!-- Pasajeros -->
          <div class="md:col-span-3 bg-surface-container-low p-2 rounded-lg flex items-stretch">
            <div class="w-full flex flex-col text-left p-3 bg-surface-container-lowest rounded-lg">
              <span class="text-[10px] uppercase font-bold text-outline tracking-wider">Pasajeros</span>
              <div class="flex items-center justify-between gap-2 mt-2">
                <div class="flex flex-col gap-2 w-full">
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-on-surface-variant">Adultos</span>
                    <div class="flex items-center gap-2">
                      <button type="button" class="passenger-btn w-6 h-6 rounded-full bg-surface-container flex items-center justify-center text-primary font-bold text-sm" data-target="adultos" data-action="minus">−</button>
                      <input type="hidden" name="adultos" id="adultos" value="1"/>
                      <span id="adultos-display" class="font-bold text-primary w-4 text-center">1</span>
                      <button type="button" class="passenger-btn w-6 h-6 rounded-full bg-surface-container flex items-center justify-center text-primary font-bold text-sm" data-target="adultos" data-action="plus">+</button>
                    </div>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-on-surface-variant">Niños</span>
                    <div class="flex items-center gap-2">
                      <button type="button" class="passenger-btn w-6 h-6 rounded-full bg-surface-container flex items-center justify-center text-primary font-bold text-sm" data-target="ninos" data-action="minus">−</button>
                      <input type="hidden" name="ninos" id="ninos" value="0"/>
                      <span id="ninos-display" class="font-bold text-primary w-4 text-center">0</span>
                      <button type="button" class="passenger-btn w-6 h-6 rounded-full bg-surface-container flex items-center justify-center text-primary font-bold text-sm" data-target="ninos" data-action="plus">+</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Botón buscar -->
        <div class="flex justify-center md:justify-end">
          <button type="submit"
                  class="bg-secondary text-white px-12 py-4 rounded-lg font-bold text-lg flex items-center gap-3 shadow-lg hover:brightness-110 active:scale-[0.98] transition-all">
            Buscar Vuelos
            <span class="material-symbols-outlined">search</span>
          </button>
        </div>
      </form>
    </div>

    <!-- ── Destinos destacados ── -->
    <div class="mt-24 grid grid-cols-1 md:grid-cols-3 gap-6 w-full max-w-6xl pb-24 text-left">
      <a href="/resultados.php?origen=GYE&destino=JFK&fecha_salida=<?= date('Y-m-d', strtotime('+14 days')) ?>&adultos=1&tipo_viaje=ida"
         class="bg-surface-container-low rounded-xl overflow-hidden group cursor-pointer border border-transparent hover:border-secondary transition-all">
        <img src="https://images.unsplash.com/photo-1485871981521-5b1fd3805eee?w=600&q=80"
             alt="Nueva York"
             class="h-48 w-full object-cover grayscale group-hover:grayscale-0 transition-all duration-500"/>
        <div class="p-5">
          <span class="text-[10px] text-secondary font-bold uppercase tracking-widest">Oferta del mes</span>
          <h3 class="text-xl font-bold text-primary">Nueva York</h3>
          <p class="text-sm text-on-surface-variant mt-1">Desde $450 USD</p>
        </div>
      </a>
      <a href="/resultados.php?origen=GYE&destino=MAD&fecha_salida=<?= date('Y-m-d', strtotime('+20 days')) ?>&adultos=1&tipo_viaje=ida"
         class="bg-surface-container-low rounded-xl overflow-hidden group cursor-pointer border border-transparent hover:border-secondary transition-all">
        <img src="https://images.unsplash.com/photo-1543785734-4b6e564642f8?w=600&q=80"
             alt="Madrid"
             class="h-48 w-full object-cover grayscale group-hover:grayscale-0 transition-all duration-500"/>
        <div class="p-5">
          <span class="text-[10px] text-secondary font-bold uppercase tracking-widest">Business Class</span>
          <h3 class="text-xl font-bold text-primary">Madrid</h3>
          <p class="text-sm text-on-surface-variant mt-1">Desde $620 USD</p>
        </div>
      </a>
      <div class="bg-surface-container-low rounded-xl overflow-hidden group cursor-pointer border border-transparent hover:border-secondary transition-all">
        <div class="h-48 w-full bg-primary flex items-center justify-center p-8">
          <span class="material-symbols-outlined text-6xl text-on-tertiary-container opacity-40">explore</span>
        </div>
        <div class="p-5">
          <span class="text-[10px] text-secondary font-bold uppercase tracking-widest">Sorpresa</span>
          <h3 class="text-xl font-bold text-primary">Destinos Exóticos</h3>
          <p class="text-sm text-on-surface-variant mt-1">Ver todos los vuelos</p>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Datalists para autocompletado -->
<datalist id="aeropuertos-origen">
  <?php foreach ($aeropuertos as $ap): ?>
    <option value="<?= e($ap['codigo']) ?>"><?= e($ap['ciudad']) ?> – <?= e($ap['nombre']) ?></option>
  <?php endforeach; ?>
</datalist>
<datalist id="aeropuertos-destino">
  <?php foreach ($aeropuertos as $ap): ?>
    <option value="<?= e($ap['codigo']) ?>"><?= e($ap['ciudad']) ?> – <?= e($ap['nombre']) ?></option>
  <?php endforeach; ?>
</datalist>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
  // ── Intercambio de origen/destino ──
  document.getElementById('swap-btn').addEventListener('click', () => {
    const o = document.getElementById('origen');
    const d = document.getElementById('destino');
    [o.value, d.value] = [d.value, o.value];
  });

  // ── Ocultar fecha de regreso en solo ida ──
  document.querySelectorAll('[name="tipo_viaje"]').forEach(r => {
    r.addEventListener('change', () => {
      document.getElementById('fecha-regreso-box').style.opacity =
        r.value === 'ida' ? '0.4' : '1';
      document.getElementById('fecha_regreso').disabled = r.value === 'ida';
    });
  });

  // ── Botones de pasajeros ──
  document.querySelectorAll('.passenger-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const target  = btn.dataset.target;
      const action  = btn.dataset.action;
      const input   = document.getElementById(target);
      const display = document.getElementById(target + '-display');
      let val = parseInt(input.value);
      const min = target === 'adultos' ? 1 : 0;
      const max = 9;
      if (action === 'plus'  && val < max) val++;
      if (action === 'minus' && val > min) val--;
      input.value   = val;
      display.textContent = val;
    });
  });
</script>
