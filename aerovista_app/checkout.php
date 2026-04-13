<?php
/**
 * AeroVista · Checkout y Pago
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Finalizar Reserva';

// ── Validar que venga del paso anterior ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_SESSION['checkout_data'])) {
    header('Location: /'); exit;
}

// Procesar datos recibidos del formulario de asientos (POST → guardar en sesión)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vuelo_id'])) {
    $vueloId       = (int)$_POST['vuelo_id'];
    $asientosJson  = $_POST['asientos_json'] ?? '[]';
    $asientosData  = json_decode($asientosJson, true) ?: [];
    $_SESSION['checkout_data'] = [
        'vuelo_id'     => $vueloId,
        'asientos'     => $asientosData,
    ];
}

$checkoutData = $_SESSION['checkout_data'] ?? [];
$vueloId      = $checkoutData['vuelo_id']  ?? 0;
$asientosData = $checkoutData['asientos']  ?? [];
$busqueda     = $_SESSION['busqueda']      ?? [];
$pasajeros    = (int)($busqueda['pasajeros'] ?? 1);

// Cargar vuelo
$stmt = getDB()->prepare("
    SELECT v.*,
           al.nombre AS aerolinea,
           ap_o.codigo AS origen_codigo, ap_o.ciudad AS origen_ciudad,
           ap_d.codigo AS destino_codigo, ap_d.ciudad AS destino_ciudad,
           (v.precio_base + v.impuestos) AS precio_unitario
    FROM vuelos v
    JOIN aerolineas al    ON al.id   = v.aerolinea_id
    JOIN aeropuertos ap_o ON ap_o.id = v.origen_id
    JOIN aeropuertos ap_d ON ap_d.id = v.destino_id
    WHERE v.id = ?
");
$stmt->execute([$vueloId]);
$vuelo = $stmt->fetch();
if (!$vuelo) { header('Location: /'); exit; }

$precioBase   = (float)$vuelo['precio_base']  * $pasajeros;
$impuestos    = (float)$vuelo['impuestos']    * $pasajeros;
$precioAsient = array_sum(array_column($asientosData, 'extra'));
$precioTotal  = $precioBase + $impuestos + $precioAsient;

// ── PROCESAR PAGO (submit del formulario de checkout) ──
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_contacto'])) {
    if (!csrfValidar()) {
        $errors[] = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $emailContacto = trim($_POST['email_contacto'] ?? '');
        $telefonoContacto = trim($_POST['telefono_contacto'] ?? '');
        $nombreTarjeta = trim($_POST['nombre_tarjeta'] ?? '');
        $numeroTarjeta = preg_replace('/\D/', '', $_POST['numero_tarjeta'] ?? '');
        $cvv = trim($_POST['cvv'] ?? '');

        // Validar pasajeros
        $pasajerosDatos = [];
        for ($i = 1; $i <= $pasajeros; $i++) {
            $nombre    = trim($_POST["p_nombre_{$i}"]    ?? '');
            $apellido  = trim($_POST["p_apellido_{$i}"]  ?? '');
            $fechaNac  = trim($_POST["p_fecha_nac_{$i}"] ?? '');
            $documento = trim($_POST["p_documento_{$i}"] ?? '');
            if (!$nombre || !$apellido || !$fechaNac || !$documento) {
                $errors[] = "Completa todos los datos del Pasajero {$i}.";
            } else {
                $pasajerosDatos[] = [
                    'nombre'    => $nombre,
                    'apellido'  => $apellido,
                    'fecha_nac' => $fechaNac,
                    'documento' => $documento,
                    'tipo'      => $i <= (int)($busqueda['adultos'] ?? 1) ? 'adulto' : 'nino',
                ];
            }
        }

        if (!validarEmail($emailContacto)) $errors[] = 'Email de contacto inválido.';
        if (strlen($numeroTarjeta) < 15)   $errors[] = 'Número de tarjeta inválido.';
        if (!$nombreTarjeta)               $errors[] = 'Ingresa el nombre en la tarjeta.';
        if (!$cvv)                         $errors[] = 'Ingresa el CVV.';

        if (empty($errors)) {
            $db  = getDB();
            $pnr = generarPNR();

            try {
                $db->beginTransaction();

                // Insertar reserva
                $stmtR = $db->prepare("
                    INSERT INTO reservas (codigo_pnr, usuario_id, vuelo_id, tipo_viaje,
                        precio_base, impuestos, precio_asientos, precio_total,
                        email_contacto, telefono_contacto, estado)
                    VALUES (?,?,?,?,?,?,?,?,?,?,'confirmada')
                ");
                $stmtR->execute([
                    $pnr,
                    estaLogueado() ? $_SESSION['usuario_id'] : null,
                    $vueloId,
                    $busqueda['tipo_viaje'] ?? 'ida',
                    $precioBase,
                    $impuestos,
                    $precioAsient,
                    $precioTotal,
                    $emailContacto,
                    $telefonoContacto ?: null,
                ]);
                $reservaId = (int)$db->lastInsertId();

                // Insertar pasajeros y asignar asientos
                foreach ($pasajerosDatos as $idx => $pd) {
                    $stmtP = $db->prepare("
                        INSERT INTO pasajeros (reserva_id, nombre, apellido, fecha_nacimiento, documento, tipo_pasajero)
                        VALUES (?,?,?,?,?,?)
                    ");
                    $stmtP->execute([
                        $reservaId,
                        $pd['nombre'], $pd['apellido'], $pd['fecha_nac'],
                        $pd['documento'], $pd['tipo'],
                    ]);
                    $pasajeroId = (int)$db->lastInsertId();

                    // Asignar asiento
                    if (isset($asientosData[$idx])) {
                        $asientoId = (int)$asientosData[$idx]['id'];
                        $stmtAS = $db->prepare("
                            INSERT INTO reserva_asientos (reserva_id, pasajero_id, asiento_id, precio_asiento)
                            VALUES (?,?,?,?)
                        ");
                        $stmtAS->execute([
                            $reservaId, $pasajeroId, $asientoId,
                            (float)$asientosData[$idx]['extra'],
                        ]);
                        // Marcar asiento como ocupado
                        $db->prepare("UPDATE asientos SET estado='ocupado' WHERE id=?")
                           ->execute([$asientoId]);
                    }
                }

                // Reducir asientos disponibles en el vuelo
                $db->prepare("UPDATE vuelos SET asientos_disponibles = asientos_disponibles - ? WHERE id=?")
                   ->execute([$pasajeros, $vueloId]);

                // Registrar pago
                $ultimos = substr($numeroTarjeta, -4);
                $stmtPago = $db->prepare("
                    INSERT INTO pagos (reserva_id, monto, metodo, estado, nombre_tarjeta, ultimos_digitos, fecha_pago)
                    VALUES (?,'tarjeta_credito','procesado',?,?,NOW())
                ");
                $stmtPago->execute([$reservaId, $precioTotal, $nombreTarjeta, $ultimos]);

                $db->commit();

                // Guardar PNR y limpiar sesión de checkout
                $_SESSION['reserva_pnr'] = $pnr;
                $_SESSION['reserva_id']  = $reservaId;
                unset($_SESSION['checkout_data'], $_SESSION['busqueda'], $_SESSION['vuelo_id']);

                header('Location: /confirmacion.php');
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Error al procesar la reserva. Intenta nuevamente.';
                if (APP_ENV === 'development') $errors[] = $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<main class="pt-24 pb-20 px-4 md:px-8 max-w-7xl mx-auto">

  <header class="mb-10">
    <h1 class="text-4xl md:text-5xl font-black text-primary tracking-tight mb-2">Finalizar Reserva</h1>
    <p class="text-on-surface-variant">Completa los detalles de los pasajeros y el pago para asegurar tu vuelo.</p>
  </header>

  <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-800 px-5 py-4 rounded-xl mb-8 space-y-1">
      <?php foreach ($errors as $err): ?>
        <p class="text-sm font-medium">• <?= e($err) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="POST" id="checkout-form">
    <?= csrfField() ?>
    <input type="hidden" name="email_contacto" id="hidden-email"/>
    <input type="hidden" name="telefono_contacto" id="hidden-telefono"/>
    <input type="hidden" name="nombre_tarjeta" id="hidden-nombre-tarjeta"/>
    <input type="hidden" name="numero_tarjeta" id="hidden-numero-tarjeta"/>
    <input type="hidden" name="cvv" id="hidden-cvv"/>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
      <!-- ── Columna izquierda: formularios ── -->
      <div class="lg:col-span-8 space-y-10">

        <!-- Pasajeros -->
        <section class="space-y-6">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary p-2 bg-surface-container-low rounded-xl">group</span>
            <h2 class="text-2xl font-bold text-primary">Información de Pasajeros</h2>
          </div>

          <?php for ($i = 1; $i <= $pasajeros; $i++): ?>
            <div class="bg-surface-container-low p-6 md:p-8 rounded-xl space-y-5">
              <div class="flex justify-between items-center">
                <h3 class="font-bold text-lg text-primary">
                  Pasajero <?= $i ?>
                  (<?= $i <= (int)($busqueda['adultos'] ?? 1) ? 'Adulto' : 'Niño' ?>)
                </h3>
                <?php if ($i === 1): ?>
                  <span class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Principal</span>
                <?php endif; ?>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Nombre</label>
                  <input type="text" name="p_nombre_<?= $i ?>" required
                         value="<?= e($_POST["p_nombre_{$i}"] ?? '') ?>"
                         placeholder="Ej. Juan"
                         class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
                </div>
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Apellido</label>
                  <input type="text" name="p_apellido_<?= $i ?>" required
                         value="<?= e($_POST["p_apellido_{$i}"] ?? '') ?>"
                         placeholder="Ej. Pérez"
                         class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
                </div>
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Fecha de Nacimiento</label>
                  <input type="date" name="p_fecha_nac_<?= $i ?>" required
                         value="<?= e($_POST["p_fecha_nac_{$i}"] ?? '') ?>"
                         class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
                </div>
                <div class="flex flex-col gap-1.5">
                  <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Cédula / Pasaporte</label>
                  <input type="text" name="p_documento_<?= $i ?>" required
                         value="<?= e($_POST["p_documento_{$i}"] ?? '') ?>"
                         placeholder="Ej. 0912345678"
                         class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
                </div>
              </div>
            </div>
          <?php endfor; ?>
        </section>

        <!-- Contacto -->
        <section class="space-y-5">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary p-2 bg-surface-container-low rounded-xl">contact_mail</span>
            <h2 class="text-2xl font-bold text-primary">Información de Contacto</h2>
          </div>
          <div class="bg-surface-container-low p-6 md:p-8 rounded-xl grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Email</label>
              <input type="email" id="email_contacto"
                     value="<?= e($_POST['email_contacto'] ?? ($_SESSION['usuario_email'] ?? '')) ?>"
                     placeholder="usuario@aerovista.com" required
                     class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Teléfono</label>
              <input type="tel" id="telefono_contacto"
                     value="<?= e($_POST['telefono_contacto'] ?? '') ?>"
                     placeholder="+593 99 123 4567"
                     class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
            </div>
          </div>
        </section>

        <!-- Pago -->
        <section class="space-y-5 pb-12">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary p-2 bg-surface-container-low rounded-xl">payments</span>
            <h2 class="text-2xl font-bold text-primary">Datos de Pago</h2>
          </div>

          <div class="bg-surface-container-low p-6 md:p-8 rounded-xl grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
            <!-- Tarjeta visual -->
            <div class="perspective-1000 w-full h-44 cursor-pointer group" id="card-container">
              <div class="relative w-full h-full text-white" id="card-inner" style="transform-style:preserve-3d;transition:transform .6s;">
                <!-- Frente -->
                <div class="absolute inset-0 p-6 rounded-2xl bg-gradient-to-br from-primary to-primary-container shadow-2xl flex flex-col justify-between overflow-hidden" style="backface-visibility:hidden;">
                  <div class="absolute top-0 right-0 p-3 opacity-20">
                    <span class="material-symbols-outlined text-5xl">flight</span>
                  </div>
                  <div class="flex justify-between items-start">
                    <div class="w-10 h-8 bg-gradient-to-br from-yellow-300 to-yellow-500 rounded-md opacity-80"></div>
                    <div class="flex gap-0.5">
                      <div class="w-7 h-7 rounded-full bg-red-500/80"></div>
                      <div class="w-7 h-7 rounded-full bg-orange-400/80 -ml-3"></div>
                    </div>
                  </div>
                  <div class="space-y-2">
                    <div class="text-xl tracking-[0.15em] font-mono" id="card-number-display">**** **** **** ****</div>
                    <div class="flex justify-between items-end">
                      <div>
                        <span class="text-[9px] uppercase opacity-60 block">Titular</span>
                        <span class="text-sm font-medium tracking-wide uppercase" id="card-name-display">NOMBRE APELLIDO</span>
                      </div>
                      <div>
                        <span class="text-[9px] uppercase opacity-60 block">Expira</span>
                        <span class="text-sm font-medium" id="card-exp-display">MM/YY</span>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- Reverso -->
                <div class="absolute inset-0 rounded-2xl bg-gradient-to-br from-primary-container to-primary shadow-2xl flex flex-col py-6 overflow-hidden" style="backface-visibility:hidden;transform:rotateY(180deg);">
                  <div class="w-full h-10 bg-slate-900 mb-4"></div>
                  <div class="px-6 flex items-center gap-4">
                    <div class="flex-1 h-8 bg-slate-200/20 rounded"></div>
                    <div class="w-14 h-7 bg-white text-primary text-center leading-7 font-bold rounded italic text-sm" id="card-cvv-display">CVV</div>
                  </div>
                </div>
              </div>
              <p class="text-center text-xs mt-2 text-on-surface-variant">Pasa el cursor para ver el reverso</p>
            </div>

            <!-- Formulario de tarjeta -->
            <div class="space-y-4">
              <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Número de Tarjeta</label>
                <input type="text" id="numero_tarjeta" maxlength="19" placeholder="0000 0000 0000 0000"
                       class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none font-mono"/>
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Nombre en la Tarjeta</label>
                <input type="text" id="nombre_tarjeta" placeholder="Como aparece en el plástico"
                       class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">Vencimiento</label>
                  <input type="text" id="vencimiento" maxlength="5" placeholder="MM/YY"
                         class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none font-mono"/>
                </div>
                <div class="flex flex-col gap-1">
                  <label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant">CVV</label>
                  <input type="text" id="cvv" maxlength="4" placeholder="123"
                         class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none font-mono"
                         onfocus="flipCard(true)" onblur="flipCard(false)"/>
                </div>
              </div>
            </div>
          </div>
        </section>

      </div>

      <!-- ── Columna derecha: resumen ── -->
      <aside class="lg:col-span-4 h-fit sticky top-24">
        <div class="bg-surface-container-lowest border border-outline-variant/10 rounded-2xl p-8 shadow-sm">
          <h2 class="text-xl font-bold text-primary mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined">receipt_long</span>
            Resumen del Precio
          </h2>
          <div class="space-y-3 mb-8">
            <div class="flex justify-between py-2">
              <span class="text-on-surface-variant text-sm">Tarifa base (×<?= $pasajeros ?>)</span>
              <span class="font-medium text-sm"><?= precio($precioBase) ?></span>
            </div>
            <div class="flex justify-between py-2 border-t border-outline-variant/10">
              <span class="text-on-surface-variant text-sm">Impuestos y tasas</span>
              <span class="font-medium text-sm"><?= precio($impuestos) ?></span>
            </div>
            <?php if ($precioAsient > 0): ?>
              <div class="flex justify-between py-2 border-t border-outline-variant/10">
                <span class="text-on-surface-variant text-sm">Asientos seleccionados</span>
                <span class="font-medium text-sm"><?= precio($precioAsient) ?></span>
              </div>
            <?php endif; ?>
            <div class="flex justify-between items-end py-5 border-t-2 border-primary/5 mt-4">
              <span class="text-lg font-bold text-primary uppercase tracking-tighter">Total</span>
              <div class="text-right">
                <div class="text-3xl font-black text-primary"><?= precio($precioTotal) ?></div>
                <div class="text-[10px] text-on-surface-variant font-bold">USD</div>
              </div>
            </div>
          </div>

          <button type="button" id="pay-btn"
                  class="w-full py-4 bg-secondary text-white rounded-xl font-bold text-lg
                         hover:opacity-90 active:scale-[0.98] transition-all shadow-lg shadow-secondary/20
                         flex items-center justify-center gap-2">
            Pagar Ahora
            <span class="material-symbols-outlined">lock</span>
          </button>

          <div class="mt-6 pt-5 border-t border-outline-variant/10">
            <div class="flex items-center gap-3 p-4 bg-surface-container-low rounded-xl">
              <span class="material-symbols-outlined text-green-600 text-2xl">verified_user</span>
              <div>
                <p class="text-[10px] font-bold text-primary uppercase">Pago Seguro SSL</p>
                <p class="text-[10px] text-on-surface-variant">Datos protegidos con encriptación de 256 bits.</p>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </form>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
  // ── Flip de tarjeta ──
  function flipCard(flip) {
    document.getElementById('card-inner').style.transform = flip ? 'rotateY(180deg)' : 'rotateY(0)';
  }
  document.getElementById('card-container').addEventListener('mouseenter', () => flipCard(false));

  // ── Actualizar display de la tarjeta en tiempo real ──
  document.getElementById('numero_tarjeta').addEventListener('input', e => {
    let val = e.target.value.replace(/\D/g,'').substring(0,16);
    e.target.value = val.replace(/(.{4})/g,'$1 ').trim();
    document.getElementById('card-number-display').textContent =
      (val + '................').substring(0,16).replace(/(.{4})/g,'$1 ').trim() || '**** **** **** ****';
  });
  document.getElementById('nombre_tarjeta').addEventListener('input', e => {
    document.getElementById('card-name-display').textContent = e.target.value.toUpperCase() || 'NOMBRE APELLIDO';
  });
  document.getElementById('vencimiento').addEventListener('input', e => {
    let val = e.target.value.replace(/\D/g,'');
    if (val.length >= 2) val = val.substring(0,2) + '/' + val.substring(2,4);
    e.target.value = val;
    document.getElementById('card-exp-display').textContent = val || 'MM/YY';
  });
  document.getElementById('cvv').addEventListener('input', e => {
    document.getElementById('card-cvv-display').textContent = e.target.value || 'CVV';
  });

  // ── Botón pagar: sincronizar campos ──
  document.getElementById('pay-btn').addEventListener('click', () => {
    document.getElementById('hidden-email').value         = document.getElementById('email_contacto').value;
    document.getElementById('hidden-telefono').value      = document.getElementById('telefono_contacto').value;
    document.getElementById('hidden-nombre-tarjeta').value = document.getElementById('nombre_tarjeta').value;
    document.getElementById('hidden-numero-tarjeta').value = document.getElementById('numero_tarjeta').value;
    document.getElementById('hidden-cvv').value           = document.getElementById('cvv').value;
    document.getElementById('checkout-form').submit();
  });
</script>
