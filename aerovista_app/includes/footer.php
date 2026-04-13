<?php /** AeroVista · Pie de página compartido */ ?>
<!-- Footer -->
<footer class="bg-surface py-12 border-t border-surface-container mt-auto">
  <div class="flex flex-col md:flex-row justify-between items-center px-8 w-full max-w-7xl mx-auto gap-8">
    <div class="flex flex-col items-center md:items-start gap-1">
      <span class="text-primary font-black text-lg tracking-tighter">AeroVista</span>
      <p class="text-xs uppercase tracking-widest text-on-surface-variant">
        © <?= date('Y') ?> AeroVista Aviation. Todos los derechos reservados.
      </p>
    </div>
    <div class="flex flex-wrap justify-center gap-6 text-xs uppercase tracking-widest text-slate-500">
      <a class="hover:text-primary transition-colors" href="#">Política de Privacidad</a>
      <a class="hover:text-primary transition-colors" href="#">Términos de Servicio</a>
      <a class="hover:text-primary transition-colors" href="#">Centro de Ayuda</a>
      <a class="hover:text-primary transition-colors" href="#">Contacto</a>
    </div>
  </div>
</footer>
</body>
</html>
