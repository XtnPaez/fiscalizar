<?php
// fiscalizacion/modulos/fiscal/fiscal.php
// Pantalla principal del fiscal.
// Busqueda en tiempo real por DNI o apellido.
// Sugerencias AJAX (hasta 3). Buscar muestra todos los resultados.
// Seleccionar persona con toque/click en la fila.
// Dos botones de voto: REGULAR u OBSERVADO.
// Confirmacion via modal antes de registrar.
// Acceso: solo fiscales autenticados.

verificar_sesion_fiscal();

if ($_SESSION['rol'] !== 'fiscal') {
    header('Location: index.php?mod=dashboard');
    exit;
}

require_once 'includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="modulo-titulo mb-0">
        <?php echo htmlspecialchars($_SESSION['nombre_mesa'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <span class="badge" style="background-color:#1a1a2e;color:#fff;font-size:0.75rem;">
        <?php echo strtoupper($_SESSION['tipo_mesa']); ?>
    </span>
</div>

<!-- Buscador — input y boton en la misma fila -->
<div class="mb-4">
    <div class="d-flex gap-2 position-relative">
        <div class="flex-grow-1 position-relative">
            <input type="text"
                id="input-busqueda"
                class="form-control fiscal-busqueda"
                placeholder="DNI o apellido..."
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false">

            <!-- Sugerencias AJAX — debajo del input, no tapa el boton -->
            <div id="sugerencias" class="list-group position-absolute w-100"
                style="z-index:1000;display:none;top:100%;left:0;
                       background:#fff;border:1px solid #dee2e6;border-radius:6px;
                       box-shadow:0 4px 12px rgba(0,0,0,0.1);"></div>
        </div>

        <!-- Boton buscar al costado, fondo celeste -->
        <button id="btn-buscar"
            class="btn btn-sm fw-semibold"
            style="background-color:#4f8ef7;color:#fff;border:none;
                   white-space:nowrap;padding:0 1.2rem;">
            Buscar
        </button>
    </div>
</div>

<!-- Resultados de busqueda -->
<div id="resultados" class="mb-4" style="display:none;">
    <div class="modulo-titulo" style="font-size:0.9rem;">Resultados</div>
    <div id="lista-resultados"></div>
</div>

<!-- Panel de voto — aparece al seleccionar una persona -->
<div id="panel-voto" class="card border-0 shadow-sm mb-4" style="display:none;">
    <div class="card-body">
        <div class="mb-3">
            <div id="voto-dni"    class="text-secondary" style="font-size:0.85rem;"></div>
            <div id="voto-nombre" class="fw-semibold"    style="font-size:1.1rem;"></div>
        </div>
        <div class="d-grid gap-2">
            <button id="btn-regular"
                class="btn fw-bold"
                style="background-color:#28a745;color:#fff;font-size:1.1rem;padding:0.75rem;">
                VOTO REGULAR
            </button>
            <button id="btn-observado"
                class="btn fw-bold"
                style="background-color:#dc3545;color:#fff;font-size:1.1rem;padding:0.75rem;">
                VOTO OBSERVADO
            </button>
            <button id="btn-cancelar" class="btn btn-sm fw-semibold"
                style="background-color:#6c757d;color:#fff;">
                Cancelar
            </button>
        </div>
    </div>
</div>

<!-- Mensaje de exito -->
<div id="msg-exito" class="alert alert-success text-center fw-semibold"
    style="display:none;font-size:1rem;">
    ✓ Voto registrado
</div>

<!-- Modal de confirmacion -->
<div class="modal fade" id="modal-confirmar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h6 class="modal-title fw-semibold">Confirmar voto</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-3">
                <div id="modal-texto" style="font-size:0.95rem;"></div>
            </div>
            <div class="modal-footer border-0 justify-content-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm"
                    data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-acento btn-sm"
                    id="btn-confirmar">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
(function () {

    // --- Estado interno ---
    let personaSeleccionada = null; // { dni, apellido, nombre }
    let tipoVotoPendiente   = null; // 'regular' | 'observado'
    let timeoutSugerencias  = null;
    const modalConfirmar    = new bootstrap.Modal(document.getElementById('modal-confirmar'));

    // --- Referencias DOM ---
    const inputBusqueda   = document.getElementById('input-busqueda');
    const divSugerencias  = document.getElementById('sugerencias');
    const btnBuscar       = document.getElementById('btn-buscar');
    const divResultados   = document.getElementById('resultados');
    const listaResultados = document.getElementById('lista-resultados');
    const panelVoto       = document.getElementById('panel-voto');
    const votoDni         = document.getElementById('voto-dni');
    const votoNombre      = document.getElementById('voto-nombre');
    const btnRegular      = document.getElementById('btn-regular');
    const btnObservado    = document.getElementById('btn-observado');
    const btnCancelar     = document.getElementById('btn-cancelar');
    const msgExito        = document.getElementById('msg-exito');
    const modalTexto      = document.getElementById('modal-texto');
    const btnConfirmar    = document.getElementById('btn-confirmar');

    // --- Sugerencias en tiempo real ---
    inputBusqueda.addEventListener('input', function () {
        clearTimeout(timeoutSugerencias);
        const q = this.value.trim();

        if (q.length < 2) {
            ocultarSugerencias();
            return;
        }

        timeoutSugerencias = setTimeout(function () {
            fetch('modulos/fiscal/buscar.php?q=' + encodeURIComponent(q) + '&modo=sugerencias')
                .then(r => r.json())
                .then(data => mostrarSugerencias(data))
                .catch(() => ocultarSugerencias());
        }, 250);
    });

    function mostrarSugerencias(data) {
        if (!data || data.length === 0) {
            ocultarSugerencias();
            return;
        }
        divSugerencias.innerHTML = '';
        data.forEach(function (p) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action py-2';
            if (p.ya_voto == 1) {
                item.classList.add('text-secondary');
                item.style.opacity = '0.6';
            }
            item.innerHTML =
                '<span class="text-secondary me-2" style="font-size:0.8rem;">' +
                escHtml(p.dni) + '</span>' +
                '<strong>' + escHtml(p.apellido) + '</strong> ' +
                escHtml(p.nombre) +
                (p.ya_voto == 1 ? ' <span class="badge bg-danger ms-1" style="font-size:0.7rem;">YA VOTÓ</span>' : '');

            item.addEventListener('click', function () {
                ocultarSugerencias();
                inputBusqueda.value = p.apellido + ' ' + p.nombre;
                if (p.ya_voto != 1) {
                    seleccionarPersona(p);
                }
            });
            divSugerencias.appendChild(item);
        });
        divSugerencias.style.display = 'block';
    }

    function ocultarSugerencias() {
        divSugerencias.style.display = 'none';
        divSugerencias.innerHTML = '';
    }

    // Cerrar sugerencias al tocar fuera
    document.addEventListener('click', function (e) {
        if (!divSugerencias.contains(e.target) && e.target !== inputBusqueda) {
            ocultarSugerencias();
        }
    });

    // --- Buscar todos los resultados ---
    btnBuscar.addEventListener('click', function () {
        const q = inputBusqueda.value.trim();
        if (q.length < 2) return;
        ocultarSugerencias();
        limpiarSeleccion();

        fetch('modulos/fiscal/buscar.php?q=' + encodeURIComponent(q) + '&modo=buscar')
            .then(r => r.json())
            .then(data => mostrarResultados(data))
            .catch(() => {
                listaResultados.innerHTML = '<p class="text-secondary">Error al buscar. Intenta de nuevo.</p>';
                divResultados.style.display = 'block';
            });
    });

    // Buscar al presionar Enter
    inputBusqueda.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') btnBuscar.click();
    });

    function mostrarResultados(data) {
        listaResultados.innerHTML = '';

        if (!data || data.length === 0) {
            listaResultados.innerHTML = '<p class="text-secondary" style="font-size:0.9rem;">Sin resultados.</p>';
            divResultados.style.display = 'block';
            return;
        }

        const tabla = document.createElement('table');
        tabla.className = 'table table-hover table-bordered align-middle';
        tabla.style.fontSize = '0.85rem';

        tabla.innerHTML = '<thead><tr><th>DNI</th><th>Apellido</th><th>Nombre</th></tr></thead>';
        const tbody = document.createElement('tbody');

        data.forEach(function (p) {
            const tr = document.createElement('tr');
            if (p.ya_voto == 1) {
                tr.style.opacity = '0.5';
                tr.style.color   = '#6c757d';
            } else {
                tr.style.cursor = 'pointer';
                tr.classList.add('fiscal-resultado');
                tr.addEventListener('click', function () {
                    seleccionarPersona(p);
                    divResultados.style.display = 'none';
                });
            }
            tr.innerHTML =
                '<td>' + escHtml(p.dni) + '</td>' +
                '<td><strong>' + escHtml(p.apellido) + '</strong>' +
                (p.ya_voto == 1 ? ' <span class="badge bg-danger ms-1" style="font-size:0.7rem;">YA VOTÓ</span>' : '') +
                '</td>' +
                '<td>' + escHtml(p.nombre) + '</td>';
            tbody.appendChild(tr);
        });

        tabla.appendChild(tbody);
        listaResultados.appendChild(tabla);
        divResultados.style.display = 'block';
    }

    // --- Seleccionar persona ---
    function seleccionarPersona(p) {
        personaSeleccionada = p;
        votoDni.textContent    = 'DNI: ' + p.dni;
        votoNombre.textContent = p.apellido + ' ' + p.nombre;
        panelVoto.style.display = 'block';
        panelVoto.scrollIntoView({ behavior: 'smooth', block: 'center' });
        msgExito.style.display  = 'none';
    }

    function limpiarSeleccion() {
        personaSeleccionada    = null;
        tipoVotoPendiente      = null;
        panelVoto.style.display = 'none';
        msgExito.style.display  = 'none';
    }

    // --- Botones de voto ---
    btnRegular.addEventListener('click', function () {
        abrirConfirmacion('regular');
    });

    btnObservado.addEventListener('click', function () {
        abrirConfirmacion('observado');
    });

    btnCancelar.addEventListener('click', function () {
        limpiarSeleccion();
        inputBusqueda.value = '';
        divResultados.style.display = 'none';
    });

    function abrirConfirmacion(tipo) {
        tipoVotoPendiente = tipo;
        const tipoTexto   = tipo === 'regular' ? 'VOTO REGULAR' : 'VOTO OBSERVADO';
        modalTexto.innerHTML =
            '<strong>' + tipoTexto + '</strong><br>' +
            escHtml(personaSeleccionada.apellido) + ' ' +
            escHtml(personaSeleccionada.nombre) +
            '<br><span class="text-secondary" style="font-size:0.85rem;">DNI ' +
            escHtml(String(personaSeleccionada.dni)) + '</span>';
        modalConfirmar.show();
    }

    // --- Confirmar voto ---
    btnConfirmar.addEventListener('click', function () {
        modalConfirmar.hide();

        const formData = new FormData();
        formData.append('dni',       personaSeleccionada.dni);
        formData.append('tipo_voto', tipoVotoPendiente);

        fetch('modulos/fiscal/registrar_voto.php', {
            method: 'POST',
            body:   formData
        })
        .then(r => r.json())
        .then(function (data) {
            if (data.ok) {
                // Exito: mostrar mensaje motivador aleatorio y limpiar
                panelVoto.style.display = 'none';
                msgExito.textContent    = mensajeAleatorio();
                msgExito.style.display  = 'block';
                inputBusqueda.value     = '';
                divResultados.style.display = 'none';
                personaSeleccionada     = null;
                tipoVotoPendiente       = null;

                // Volver al buscador limpio despues de 2 segundos
                setTimeout(function () {
                    msgExito.style.display = 'none';
                    inputBusqueda.focus();
                }, 2000);

            } else {
                alert((data.error || 'No se pudo registrar el voto.') + ' Avisale al fiscal general.');
            }
        })
        .catch(function () {
            alert('Error de conexion. Intenta de nuevo. Avisale al fiscal general.');
        });
    });

    // --- Mensajes motivadores al registrar voto exitoso ---
    const mensajesExito = [
        'Cada voto cuenta, literalmente.',
        'Democracia en proceso.',
        'Misión cumplida, fiscal.',
        'Otro voto, otra garantía.',
        'Seguimos cuidando la elección.',
        'Bien ahí, guardian electoral.',
        'Controlado y registrado.',
        'La democracia te agradece.',
        'Fiscalizando como campeón.',
        'Un clic por transparencia.',
        'Voto registrado correctamente.',
        'Otro paso democrático.',
        'Todo en orden, fiscal.',
        'Precisión electoral activada.',
        'Excelente trabajo de fiscalización.',
        'Democracia nivel experto.',
        'Sumando confianza al proceso.',
        'Fiscal presente, fraude ausente.',
        'Seguimos firmes en mesa.',
        'Voto confirmado, mate pendiente.',
        'Transparencia desbloqueada.',
        'Otro voto bajo control.',
        'Fiscalizando con estilo.',
        'La urna sonríe.',
        'Todo registrado perfectamente.',
        'Check democrático realizado.',
        'Cuidando votos, cuidando derechos.',
        'Mesa protegida exitosamente.',
        'Registro exitoso, siga siga.',
        'Un voto más, impecable.'
    ];

    function mensajeAleatorio() {
        return mensajesExito[Math.floor(Math.random() * mensajesExito.length)];
    }
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Focus al cargar
    inputBusqueda.focus();

})();
</script>
