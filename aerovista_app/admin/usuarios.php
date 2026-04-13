<?php
/**
 * AeroVista Admin · Gestión de Usuarios
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!esAdmin()) { header('Location: /admin/login.php'); exit; }

$db  = getDB();
$ok  = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidar()) {
        $error = 'Token inválido.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'toggle_active') {
            $id     = (int)$_POST['usuario_id'];
            $activo = (int)$_POST['activo'];
            $db->prepare("UPDATE usuarios SET activo=? WHERE id=?")->execute([$activo, $id]);
            $ok = 'Estado del usuario actualizado.';
        } elseif ($action === 'change_role') {
            $id  = (int)$_POST['usuario_id'];
            $rol = in_array($_POST['rol'], ['cliente','admin']) ? $_POST['rol'] : 'cliente';
            $db->prepare("UPDATE usuarios SET rol=? WHERE id=?")->execute([$rol, $id]);
            $ok = 'Rol actualizado.';
        }
    }
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
$search = trim($_GET['q'] ?? '');
$rolFilter = $_GET['rol'] ?? '';

$where   = [];
$params  = [];
if ($search) { $where[] = "(u.nombre LIKE :q OR u.apellido LIKE :q OR u.email LIKE :q)"; $params[':q'] = "%$search%"; }
if ($rolFilter) { $where[] = "u.rol=:rol"; $params[':rol'] = $rolFilter; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $db->prepare("SELECT COUNT(*) FROM usuarios u $whereSQL");
$total->execute($params);
$totalRows  = (int)$total->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmtU = $db->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM reservas r WHERE r.usuario_id=u.id AND r.estado='confirmada') AS reservas_count
    FROM usuarios u
    $whereSQL
    ORDER BY u.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmtU->execute($params);
$usuarios = $stmtU->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Usuarios · Admin AeroVista</title>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <script>tailwind.config={theme:{extend:{colors:{"primary":"#001e40","secondary":"#9f4200","surface":"#fbf8fe","surface-container-low":"#f6f2f8","surface-container":"#f0edf2","surface-container-high":"#eae7ed","surface-container-highest":"#e4e1e7","surface-container-lowest":"#ffffff","on-surface":"#1b1b1f","on-surface-variant":"#43474f","outline-variant":"#c3c6d1","error":"#ba1a1a"}}}};</script>
  <style>body{font-family:'Inter',sans-serif;}.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;}</style>
</head>
<body class="bg-surface text-on-surface antialiased">

  <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <main class="ml-64 min-h-screen">
    <header class="fixed top-0 right-0 left-64 h-16 bg-white/70 backdrop-blur-xl z-30 shadow-sm flex items-center px-8">
      <h1 class="text-xl font-bold text-primary tracking-tighter">Gestión de Usuarios</h1>
    </header>

    <div class="pt-24 pb-12 px-8 max-w-7xl mx-auto space-y-6">

      <?php if ($ok):  ?><div class="bg-green-100 border border-green-400 text-green-800 px-5 py-3 rounded-xl text-sm font-medium"><?= e($ok) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-800 px-5 py-3 rounded-xl text-sm font-medium"><?= e($error) ?></div><?php endif; ?>

      <!-- Filtros -->
      <form method="GET" class="flex flex-wrap gap-3 items-center">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Buscar por nombre o email..."
               class="flex-1 min-w-0 bg-surface-container-highest border-none rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary outline-none"/>
        <select name="rol" class="bg-surface-container-highest border-none rounded-lg px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
          <option value="">Todos los roles</option>
          <option value="cliente" <?= $rolFilter==='cliente'?'selected':'' ?>>Clientes</option>
          <option value="admin"   <?= $rolFilter==='admin'?'selected':'' ?>>Admins</option>
        </select>
        <button type="submit" class="bg-primary text-white px-5 py-2.5 rounded-lg font-bold text-sm hover:opacity-90">Filtrar</button>
        <?php if ($search || $rolFilter): ?>
          <a href="/admin/usuarios.php" class="px-5 py-2.5 rounded-lg font-bold text-sm bg-surface-container-high text-on-surface-variant hover:bg-surface-container-highest">Limpiar</a>
        <?php endif; ?>
      </form>

      <section class="bg-surface-container-lowest rounded-xl overflow-hidden shadow-sm">
        <div class="p-6 border-b border-surface-container-high/50">
          <h2 class="text-base font-bold text-primary">Usuarios Registrados</h2>
          <p class="text-xs text-on-surface-variant"><?= $totalRows ?> usuarios totales</p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="bg-surface-container-low/50">
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Usuario</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Email</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Rol</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Reservas</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Registrado</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Estado</th>
                <th class="px-6 py-4 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-container-high/20">
              <?php foreach ($usuarios as $u): ?>
                <tr class="hover:bg-surface-container-low/20 transition-colors">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center">
                        <span class="text-primary font-bold text-sm"><?= strtoupper(substr($u['nombre'],0,1).substr($u['apellido'],0,1)) ?></span>
                      </div>
                      <div>
                        <p class="font-semibold text-on-surface"><?= e($u['nombre'].' '.$u['apellido']) ?></p>
                        <?php if ($u['telefono']): ?><p class="text-xs text-on-surface-variant"><?= e($u['telefono']) ?></p><?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-on-surface-variant"><?= e($u['email']) ?></td>
                  <td class="px-6 py-4">
                    <span class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-tighter
                      <?= $u['rol']==='admin' ? 'bg-primary/10 text-primary' : 'bg-surface-container text-on-surface-variant' ?>">
                      <?= $u['rol'] ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 font-bold text-primary"><?= $u['reservas_count'] ?></td>
                  <td class="px-6 py-4 text-xs text-on-surface-variant"><?= fechaCorta($u['created_at']) ?></td>
                  <td class="px-6 py-4">
                    <span class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-tighter
                      <?= $u['activo'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                      <?= $u['activo'] ? 'Activo' : 'Bloqueado' ?>
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <?php if ($u['id'] !== (int)$_SESSION['usuario_id']): ?>
                      <div class="flex gap-2">
                        <form method="POST">
                          <?= csrfField() ?>
                          <input type="hidden" name="action" value="toggle_active"/>
                          <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>"/>
                          <input type="hidden" name="activo" value="<?= $u['activo'] ? 0 : 1 ?>"/>
                          <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg transition-all
                            <?= $u['activo'] ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200' ?>">
                            <?= $u['activo'] ? 'Bloquear' : 'Activar' ?>
                          </button>
                        </form>
                        <?php if ($u['rol'] !== 'admin'): ?>
                          <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="change_role"/>
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>"/>
                            <input type="hidden" name="rol" value="admin"/>
                            <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition-all"
                                    onclick="return confirm('¿Hacer admin a <?= e($u['nombre']) ?>?')">
                              → Admin
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <span class="text-xs text-on-surface-variant italic">Tu cuenta</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($usuarios)): ?>
                <tr><td colspan="7" class="px-6 py-12 text-center text-on-surface-variant">Sin usuarios registrados</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="px-6 py-4 bg-surface-container-low/30 flex justify-between items-center">
            <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">Página <?= $page ?> de <?= $totalPages ?></p>
            <div class="flex gap-1">
              <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&rol=<?= urlencode($rolFilter) ?>"
                   class="w-8 h-8 flex items-center justify-center rounded text-sm font-bold
                          <?= $p===$page ? 'bg-primary text-white' : 'bg-white text-primary border border-outline-variant/20 hover:bg-surface-container' ?>">
                  <?= $p ?>
                </a>
              <?php endfor; ?>
            </div>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
