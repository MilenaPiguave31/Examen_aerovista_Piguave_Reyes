<?php
/**
 * AeroVista Admin · Login de Administrador
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (esAdmin()) { header('Location: /admin/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidar()) {
        $error = 'Sesión expirada. Recarga la página.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        $stmt = getDB()->prepare("SELECT * FROM usuarios WHERE email=? AND activo=1 AND rol='admin'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            iniciarSesion($user);
            header('Location: /admin/index.php');
            exit;
        }
        $error = 'Credenciales inválidas o no tienes permisos de administrador.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin · AeroVista</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <style>body{font-family:'Inter',sans-serif;}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="min-h-screen bg-[#001e40] flex items-center justify-center px-4">

  <div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-10">
      <h1 class="text-4xl font-black text-white tracking-tighter">AeroVista</h1>
      <p class="text-white/60 text-sm mt-1 uppercase tracking-widest">Control Tower · Admin</p>
    </div>

    <div class="bg-white rounded-2xl p-8 shadow-2xl">
      <h2 class="text-2xl font-bold text-[#001e40] mb-6">Iniciar Sesión</h2>

      <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded-lg mb-5 text-sm font-medium">
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <?= csrfField() ?>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-[#43474f] mb-2">Email</label>
          <input type="email" name="email" required autofocus
                 value="<?= e($_POST['email'] ?? '') ?>"
                 class="w-full bg-[#f0edf2] border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
        </div>
        <div>
          <label class="block text-xs font-bold uppercase tracking-widest text-[#43474f] mb-2">Contraseña</label>
          <input type="password" name="password" required
                 class="w-full bg-[#f0edf2] border-none rounded-lg px-4 py-3 focus:ring-2 focus:ring-[#001e40] outline-none transition-all"/>
        </div>
        <button type="submit"
                class="w-full bg-[#001e40] text-white py-3 rounded-lg font-bold hover:opacity-90 transition-all active:scale-[0.98]">
          Ingresar al Panel
        </button>
      </form>

      <div class="mt-6 text-center">
        <a href="/index.php" class="text-xs text-[#737780] hover:text-[#001e40] transition-colors">
          ← Volver al sitio principal
        </a>
      </div>
    </div>

    <p class="text-center text-white/40 text-xs mt-6">
      © <?= date('Y') ?> AeroVista Aviation. Acceso restringido.
    </p>
  </div>
</body>
</html>
