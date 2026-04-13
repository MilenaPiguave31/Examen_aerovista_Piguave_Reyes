<?php
/**
 * AeroVista · Login de Usuario
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (estaLogueado()) { header('Location: /'); exit; }

$error  = '';
$redirect = $_GET['redirect'] ?? '/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidar()) {
        $error = 'Sesión expirada. Recarga la página.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = getDB()->prepare("SELECT * FROM usuarios WHERE email=? AND activo=1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            iniciarSesion($user);
            header('Location: ' . ($user['rol'] === 'admin' ? '/admin/index.php' : $redirect));
            exit;
        }
        $error = 'Email o contraseña incorrectos.';
    }
}

$pageTitle = 'Iniciar Sesión';
include __DIR__ . '/includes/header.php';
?>

<main class="pt-24 pb-20 px-4 min-h-screen flex items-center justify-center">
  <div class="w-full max-w-md">

    <div class="text-center mb-8">
      <h1 class="text-4xl font-black text-primary tracking-tight mb-2">Bienvenido de vuelta</h1>
      <p class="text-on-surface-variant">Inicia sesión para gestionar tus reservas</p>
    </div>

    <div class="bg-surface-container-lowest rounded-2xl p-8 shadow-sm border border-outline-variant/10">

      <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-lg mb-5 text-sm font-medium">
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <?= csrfField() ?>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Email</label>
          <input type="email" name="email" required autofocus
                 value="<?= e($_POST['email'] ?? '') ?>"
                 placeholder="usuario@ejemplo.com"
                 class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Contraseña</label>
          <input type="password" name="password" required
                 class="bg-surface-container-highest border-none rounded-lg p-3 focus:ring-2 focus:ring-primary focus:bg-white transition-all outline-none"/>
        </div>
        <button type="submit"
                class="w-full bg-primary text-white py-3 rounded-lg font-bold hover:opacity-90 transition-all active:scale-[0.98]">
          Iniciar Sesión
        </button>
      </form>

      <p class="text-center text-sm text-on-surface-variant mt-6">
        ¿No tienes cuenta?
        <a href="/registro.php" class="font-bold text-secondary hover:underline">Regístrate gratis</a>
      </p>
    </div>

    <!-- Credenciales de demo -->
    <div class="mt-6 p-4 bg-surface-container-low rounded-xl border border-outline-variant/10 text-sm">
      <p class="font-bold text-on-surface-variant mb-2 text-xs uppercase tracking-widest">Demo</p>
      <div class="space-y-1 text-xs text-on-surface-variant">
        <p>Admin: <code class="bg-surface-container px-1 rounded">admin@aerovista.com</code> / <code class="bg-surface-container px-1 rounded">Admin@2024</code></p>
        <p>Cliente: <code class="bg-surface-container px-1 rounded">carlos@example.com</code> / <code class="bg-surface-container px-1 rounded">Test@1234</code></p>
      </div>
    </div>

  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
