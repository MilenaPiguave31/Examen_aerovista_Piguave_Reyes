<?php
/** AeroVista Admin · Sidebar de navegación */
$currentPage = basename($_SERVER['PHP_SELF']);
$navItems = [
    ['href' => '/admin/index.php',    'icon' => 'dashboard',    'label' => 'Dashboard',   'file' => 'index.php'],
    ['href' => '/admin/vuelos.php',   'icon' => 'flight_takeoff','label' => 'Vuelos',      'file' => 'vuelos.php'],
    ['href' => '/admin/usuarios.php', 'icon' => 'group',         'label' => 'Usuarios',    'file' => 'usuarios.php'],
    ['href' => '/admin/reservas.php', 'icon' => 'luggage',       'label' => 'Reservas',    'file' => 'reservas.php'],
];
?>
<aside class="h-screen w-64 fixed left-0 top-0 bg-surface-container-low flex flex-col p-5 gap-3 z-40 border-r-0">
  <a href="/admin/index.php" class="font-black text-primary text-2xl tracking-tighter mb-6 px-2 block">AeroVista</a>
  <nav class="flex flex-col gap-1 flex-grow">
    <?php foreach ($navItems as $item):
      $active = ($currentPage === $item['file']);
    ?>
      <a href="<?= $item['href'] ?>"
         class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium transition-all duration-150
                <?= $active ? 'bg-white text-primary font-semibold shadow-sm' : 'text-on-surface-variant hover:bg-white/60 hover:text-primary' ?>">
        <span class="material-symbols-outlined text-xl"><?= $item['icon'] ?></span>
        <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="flex flex-col gap-2 mt-auto">
    <a href="/admin/vuelos.php#nuevo"
       class="bg-secondary text-white px-4 py-3 rounded-lg font-bold text-sm flex items-center justify-center gap-2 hover:opacity-90 transition-all">
      <span class="material-symbols-outlined text-lg">add</span>
      Nuevo Vuelo
    </a>
    <a href="/logout.php"
       class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm text-on-surface-variant hover:bg-white/60 hover:text-primary transition-all">
      <span class="material-symbols-outlined text-xl">logout</span>
      Cerrar Sesión
    </a>
  </div>
</aside>
