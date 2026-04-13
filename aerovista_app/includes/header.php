<?php
/**
 * AeroVista · Cabecera HTML compartida
 * Variables esperadas (opcionales):
 *  $pageTitle  string  - Título de la página
 *  $bodyClass  string  - Clases extra para <body>
 *  $activeNav  string  - Enlace activo: 'reservas' | ''
 */
$pageTitle = $pageTitle ?? 'AeroVista';
$bodyClass = $bodyClass ?? '';
$activeNav = $activeNav ?? '';
?>
<!DOCTYPE html>
<html lang="es" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= e($pageTitle) ?> · AeroVista</title>
  <meta name="description" content="AeroVista – Precision in every flight. Reserva tus vuelos con el sistema más avanzado de aviación."/>

  <!-- Tailwind CSS CDN (compatible con hosting compartido) -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

  <!-- Tailwind Config personalizado (Design System AeroVista) -->
  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary":                    "#001e40",
            "on-primary":                 "#ffffff",
            "primary-container":          "#003366",
            "on-primary-container":       "#799dd6",
            "primary-fixed":              "#d5e3ff",
            "primary-fixed-dim":          "#a7c8ff",
            "on-primary-fixed":           "#001b3c",
            "on-primary-fixed-variant":   "#1f477b",
            "secondary":                  "#9f4200",
            "on-secondary":               "#ffffff",
            "secondary-container":        "#fd6c00",
            "on-secondary-container":     "#562000",
            "secondary-fixed":            "#ffdbcb",
            "secondary-fixed-dim":        "#ffb692",
            "on-secondary-fixed":         "#341100",
            "on-secondary-fixed-variant": "#7a3000",
            "tertiary":                   "#002131",
            "on-tertiary":                "#ffffff",
            "tertiary-container":         "#00374f",
            "on-tertiary-container":      "#00a6e5",
            "tertiary-fixed":             "#c6e7ff",
            "tertiary-fixed-dim":         "#83cfff",
            "on-tertiary-fixed":          "#001e2d",
            "on-tertiary-fixed-variant":  "#004c6b",
            "surface":                    "#fbf8fe",
            "surface-dim":                "#dcd9de",
            "surface-bright":             "#fbf8fe",
            "surface-container-lowest":   "#ffffff",
            "surface-container-low":      "#f6f2f8",
            "surface-container":          "#f0edf2",
            "surface-container-high":     "#eae7ed",
            "surface-container-highest":  "#e4e1e7",
            "surface-variant":            "#e4e1e7",
            "on-surface":                 "#1b1b1f",
            "on-surface-variant":         "#43474f",
            "surface-tint":               "#3a5f94",
            "inverse-surface":            "#303034",
            "inverse-on-surface":         "#f3f0f5",
            "inverse-primary":            "#a7c8ff",
            "background":                 "#fbf8fe",
            "on-background":              "#1b1b1f",
            "outline":                    "#737780",
            "outline-variant":            "#c3c6d1",
            "error":                      "#ba1a1a",
            "on-error":                   "#ffffff",
            "error-container":            "#ffdad6",
            "on-error-container":         "#93000a",
          },
          borderRadius: {
            DEFAULT: "0.25rem",
            lg: "0.5rem",
            xl: "0.75rem",
            "2xl": "1rem",
            "3xl": "1.5rem",
            full: "9999px",
          },
          fontFamily: {
            headline: ["Inter"],
            body: ["Inter"],
            label: ["Inter"],
          },
        },
      },
    };
  </script>

  <style>
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    body { font-family: 'Inter', sans-serif; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  </style>
</head>
<body class="bg-surface text-on-surface antialiased <?= e($bodyClass) ?>">

<!-- Top Navigation Bar -->
<nav class="fixed top-0 w-full z-50 bg-white/70 backdrop-blur-xl shadow-sm h-16">
  <div class="flex justify-between items-center px-6 md:px-8 h-full w-full max-w-screen-2xl mx-auto">
    <!-- Logo -->
    <a href="/index.php" class="text-xl font-bold text-primary tracking-tighter select-none">
      AeroVista
    </a>

    <!-- Nav links -->
    <div class="hidden md:flex items-center gap-8">
      <a href="/mis_reservas.php"
         class="font-medium text-sm tracking-tight transition-colors duration-300
                <?= $activeNav === 'reservas' ? 'text-primary border-b-2 border-primary pb-1' : 'text-slate-500 hover:text-secondary' ?>">
        Mis Reservas
      </a>
    </div>

    <!-- Auth buttons -->
    <div class="flex items-center gap-3 md:gap-4">
      <?php if (estaLogueado()): ?>
        <span class="hidden sm:inline text-sm font-medium text-on-surface-variant">
          Hola, <?= e($_SESSION['usuario_nombre']) ?>
        </span>
        <?php if (esAdmin()): ?>
          <a href="/admin/index.php"
             class="hidden sm:inline text-sm font-medium text-secondary hover:underline">
            Admin
          </a>
        <?php endif; ?>
        <a href="/logout.php"
           class="text-sm font-medium text-slate-500 hover:text-secondary transition-colors duration-300">
          Salir
        </a>
      <?php else: ?>
        <a href="/login.php"
           class="text-sm font-medium text-slate-500 hover:text-secondary transition-colors duration-300">
          Iniciar Sesión
        </a>
        <a href="/registro.php"
           class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90 transition-all active:scale-95">
          Registrarse
        </a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- Flash message (se mostrará al principio del contenido si se llama flashRender()) -->
