<?php
/**
 * AeroVista · Registro de Nuevos Usuarios
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (estaLogueado()) { header('Location: /'); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidar()) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $nombre   = trim($_POST['nombre']   ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $tel      = trim($_POST['telefono'] ?? '');
        $pass     = $_POST['password']      ?? '';
        $pass2    = $_POST['password2']     ?? '';

        if (!$nombre)                   $errors[] = 'El nombre es requerido.';
        if (!$apellido)                 $errors[] = 'El apellido es requerido.';
        if (!validarEmail($email))      $errors[] = 'Email inválido.';
        if (strlen($pass) < 8)         $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($pass !== $pass2)          $errors[] = 'Las contraseñas no coinciden.';

        if (empty($errors)) {
            // Verificar que no exista el email
            $stmt = getDB()->prepare("SELECT id FROM usuarios WHERE email=?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Ya existe una cuenta con ese email.';
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost'=>12]);
                $ins  = getDB()->prepare("INSERT INTO usuarios (nombre,apellido,email,password_hash,telefono,rol) VALUES (?,?,?,?,?,'cliente')");
                $ins->execute([$nombre, $apellido, $email, $hash, $tel ?: null]);

                // Auto-login
                $stmt2 = getDB()->prepare("SELECT * FROM usuarios WHERE email=?");
                $stmt2->execute([$email]);
                iniciarSesion($stmt2->fetch());

                flashSet('success', "¡Bienvenido a AeroVista, {$nombre}! Tu cuenta ha sido creada.");
                header('Location: /');
                exit;
            }
        }
    }
}

$pageTitle = 'Crear Cuenta';
include __DIR__ . '/includes/header.php';
?>

<main class="pt-24 pb-20 px-4 min-h-screen flex items-center justify-center">
  <div class="w-full max-w-lg">

    <div class="text-center mb-8">
      <h1 class="text-4xl font-black text-primary tracking-tight mb-2">Crea tu cuenta</h1>
      <p class="text-on-surface-variant">Únete a AeroVista y viaja con precisión</p>
    </div>

    <div class="bg-surface-container-lowest rounded-2xl p-8 shadow-sm border border-outline-variant/10">

      <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-lg mb-5">
          <?php foreach ($errors as $err): ?>
            <p class="text-sm font-medium">• <?= e($err) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">
        <?= csrfField() ?>
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Nombre</label>
            <input type="text" name="nombre" required
                   value="<?= e($_POST['nombre'] ?? '') ?>"
                   placeholder="Juan"
                   class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Apellido</label>
            <input type="text" name="apellido" required
                   value="<?= e($_POST['apellido'] ?? '') ?>"
                   placeholder="Pérez"
                   class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
          </div>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Email</label>
          <input type="email" name="email" required
                 value="<?= e($_POST['email'] ?? '') ?>"
                 placeholder="usuario@ejemplo.com"
                 class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Teléfono (opcional)</label>
          <input type="tel" name="telefono"
                 value="<?= e($_POST['telefono'] ?? '') ?>"
                 placeholder="+593 99 123 4567"
                 class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Contraseña</label>
            <input type="password" name="password" required minlength="8"
                   placeholder="Mínimo 8 caracteres"
                   class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Repetir Contraseña</label>
            <input type="password" name="password2" required
                   placeholder="Repite la contraseña"
                   class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
          </div>
        </div>
        <button type="submit"
                class="w-full bg-secondary text-white py-3.5 rounded-lg font-bold hover:opacity-90 transition-all active:scale-[0.98] mt-2">
          Crear Cuenta Gratis
        </button>
      </form>

      <p class="text-center text-sm text-on-surface-variant mt-6">
        ¿Ya tienes cuenta?
        <a href="/login.php" class="font-bold text-secondary hover:underline">Iniciar sesión</a>
      </p>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
