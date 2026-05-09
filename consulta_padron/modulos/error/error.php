<?php
// modulos/error/error.php
// Pagina de error generica del sistema Fiscalizar — Consulta Padron.
// Se carga cuando ocurre un error inesperado en cualquier modulo.
// Muestra mensaje amigable y permite volver al inicio sin perder la sesion.
// Acceso: todos los niveles autenticados.

verificar_sesion();

require_once 'includes/navbar.php';
?>

<div class="d-flex flex-column align-items-center justify-content-center" style="min-height:40vh;">
    <div class="text-center" style="max-width:480px;">
        <div style="font-size:3rem; margin-bottom:1rem;">⚠️</div>
        <div class="modulo-titulo mb-2">Ocurrió un error</div>
        <p class="text-secondary mb-4" style="font-size:0.95rem;">
            <?php echo isset($mensaje_error)
                ? htmlspecialchars($mensaje_error, ENT_QUOTES, 'UTF-8')
                : 'El sistema encontró un problema procesando tu solicitud.'; ?>
        </p>
        <a href="index.php?mod=buscador" class="btn btn-acento">Volver al inicio</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>