<?php
// modulos/buscador/buscador.php
// Modulo de busqueda por apellido, apellido nombre, o DNI.
// Acceso: todos los niveles autenticados.
// Una sola fila por DNI. Perfil unificado sin division por padron.
// Sugerencias en tiempo real via fetch al mismo modulo con ?sugerencias=1.
// Todo resultado es descargable en Excel.

verificar_sesion();

require_once 'includes/excel.php';

// --- Endpoint de sugerencias (JSON) ---
// Se llama via fetch desde el input de busqueda
// Devuelve hasta 5 coincidencias y termina
if (isset($_GET['sugerencias'])) {
    $q = strtoupper(trim($_GET['q'] ?? ''));
    if (strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }

    $tokens = preg_split('/\s+/', $q);

    if (count($tokens) >= 2) {
        $stmt = $pdo->prepare("
            SELECT dni, apellido, nombre
            FROM personas
            WHERE apellido LIKE :apellido AND nombre LIKE :nombre
            ORDER BY apellido ASC, nombre ASC
            LIMIT 5
        ");
        $stmt->execute([
            ':apellido' => $tokens[0] . '%',
            ':nombre'   => $tokens[1] . '%',
        ]);
    } elseif (ctype_digit($q)) {
        $stmt = $pdo->prepare("
            SELECT dni, apellido, nombre
            FROM personas
            WHERE dni = :dni
            LIMIT 5
        ");
        $stmt->execute([':dni' => $q]);
    } else {
        $stmt = $pdo->prepare("
            SELECT dni, apellido, nombre
            FROM personas
            WHERE apellido LIKE :apellido
            ORDER BY apellido ASC, nombre ASC
            LIMIT 5
        ");
        $stmt->execute([':apellido' => $q . '%']);
    }

    echo json_encode($stmt->fetchAll());
    exit;
}

// --- Query base: una fila por DNI con flags de padron ---
$sql_busqueda = "
    SELECT
        p.dni,
        p.apellido,
        p.nombre,
        pcd.sigla                           AS carrera,
        IF(pcd.dni IS NOT NULL, 'SI', 'NO') AS padron_cd,
        IF(pcp.dni IS NOT NULL, 'SI', 'NO') AS padron_cp
    FROM personas p
    LEFT JOIN padron_cd pcd ON p.dni = pcd.dni
    LEFT JOIN padron_cp pcp ON p.dni = pcp.dni
";

$resultados = [];
$busqueda   = trim($_GET['q'] ?? '');
$busco      = $busqueda !== '';
$total      = 0;

if ($busco) {

    if (ctype_digit($busqueda)) {
        $stmt = $pdo->prepare($sql_busqueda . " WHERE p.dni = :dni");
        $stmt->execute([':dni' => $busqueda]);
    } else {
        $tokens = preg_split('/\s+/', strtoupper(trim($busqueda)));
        if (count($tokens) >= 2) {
            $stmt = $pdo->prepare($sql_busqueda . "
                WHERE p.apellido LIKE :apellido AND p.nombre LIKE :nombre
                ORDER BY p.apellido ASC, p.nombre ASC
            ");
            $stmt->execute([':apellido' => $tokens[0] . '%', ':nombre' => $tokens[1] . '%']);
        } else {
            $stmt = $pdo->prepare($sql_busqueda . "
                WHERE p.apellido LIKE :apellido
                ORDER BY p.apellido ASC, p.nombre ASC
            ");
            $stmt->execute([':apellido' => $tokens[0] . '%']);
        }
    }

    $resultados = $stmt->fetchAll();
    $total      = count($resultados);

    // Exportar listado si se pidio
    if (isset($_GET['exportar']) && $_GET['exportar'] === '1' && !empty($resultados)) {
        $nombre = 'busqueda-' . preg_replace('/[^a-z0-9]/', '', strtolower($busqueda));
        exportar_excel($resultados, $nombre);
        exit;
    }

    // Un solo resultado: ir directo al perfil
    if ($total === 1) {
        header('Location: index.php?mod=buscador&perfil=' . $resultados[0]['dni']);
        exit;
    }
}

// --- Cargar perfil unificado ---
$perfil     = null;
$dni_perfil = intval($_GET['perfil'] ?? 0);

if ($dni_perfil > 0) {

    // Datos base con flags de padron
    $stmt = $pdo->prepare($sql_busqueda . " WHERE p.dni = :dni");
    $stmt->execute([':dni' => $dni_perfil]);
    $perfil = $stmt->fetch();

    if ($perfil) {

        // Referentes, partido, trabajo, sede y municipio — desde vista_padron_cd si esta en CD
        // Si solo esta en CP, desde vista_padron_cp
        // Estos datos son atributos de la persona, identicos en ambas vistas
        if ($perfil['padron_cd'] === 'SI') {
            $stmt = $pdo->prepare("
                SELECT referente_1, referente_2, referente_3,
                       partido, trabajo, sede, municipio
                FROM vista_padron_cd WHERE dni = :dni
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT referente_1, referente_2, referente_3,
                       partido, trabajo, sede, municipio
                FROM vista_padron_cp WHERE dni = :dni
            ");
        }
        $stmt->execute([':dni' => $dni_perfil]);
        $perfil['vinculos'] = $stmt->fetch();

        // Votaciones CD — solo si esta en padron CD
        if ($perfil['padron_cd'] === 'SI') {
            $stmt = $pdo->prepare("
                SELECT voto_cd_2021, voto_cd_2024
                FROM vista_padron_cd WHERE dni = :dni
            ");
            $stmt->execute([':dni' => $dni_perfil]);
            $perfil['votos_cd'] = $stmt->fetch();
        }

        // Votaciones CP — solo si esta en padron CP
        if ($perfil['padron_cp'] === 'SI') {
            $stmt = $pdo->prepare("
                SELECT auxiliar, voto_cp_2017, voto_cp_2019, voto_cp_2021, voto_cp_2024
                FROM vista_padron_cp WHERE dni = :dni
            ");
            $stmt->execute([':dni' => $dni_perfil]);
            $perfil['votos_cp'] = $stmt->fetch();
        }
    }

    // Exportar perfil individual
    if (isset($_GET['exportar']) && $_GET['exportar'] === '1' && $perfil) {
        $v = $perfil['vinculos'] ?? [];
        $fila = [
            'dni'         => $perfil['dni'],
            'apellido'    => $perfil['apellido'],
            'nombre'      => $perfil['nombre'],
            'carrera'     => $perfil['carrera']    ?? '—',
            'padron_cd'   => $perfil['padron_cd'],
            'padron_cp'   => $perfil['padron_cp'],
            'referente_1' => $v['referente_1']     ?? '—',
            'referente_2' => $v['referente_2']     ?? '—',
            'referente_3' => $v['referente_3']     ?? '—',
            'partido'     => $v['partido']          ?? '—',
            'trabajo'     => $v['trabajo']          ?? '—',
            'sede'        => $v['sede']             ?? '—',
            'municipio'   => $v['municipio']        ?? '—',
        ];
        if (!empty($perfil['votos_cd'])) {
            $fila['voto_cd_2021'] = $perfil['votos_cd']['voto_cd_2021'];
            $fila['voto_cd_2024'] = $perfil['votos_cd']['voto_cd_2024'];
        }
        if (!empty($perfil['votos_cp'])) {
            $fila['auxiliar']     = $perfil['votos_cp']['auxiliar'] ? 'SI' : 'NO';
            $fila['voto_cp_2017'] = $perfil['votos_cp']['voto_cp_2017'];
            $fila['voto_cp_2019'] = $perfil['votos_cp']['voto_cp_2019'];
            $fila['voto_cp_2021'] = $perfil['votos_cp']['voto_cp_2021'];
            $fila['voto_cp_2024'] = $perfil['votos_cp']['voto_cp_2024'];
        }
        exportar_excel([$fila], 'perfil-' . $dni_perfil);
        exit;
    }
}

// Funcion auxiliar para mostrar badge de voto
function badge_voto(string $valor): string {
    return $valor === 'SI'
        ? '<span class="badge" style="background-color:#a6d900;color:#1a1a2e;">SI</span>'
        : '<span class="text-secondary">NO</span>';
}

require_once 'includes/navbar.php';
?>

<div class="modulo-titulo">Buscador</div>

<!-- Formulario de busqueda con sugerencias -->
<form method="GET" action="index.php" class="mb-4" autocomplete="off">
    <input type="hidden" name="mod" value="buscador">
    <div class="row g-2 align-items-center">
        <div class="col-md-6 position-relative">
            <input
                type="text"
                name="q"
                id="input-busqueda"
                class="form-control"
                placeholder="Apellido, DNI, o apellido nombre"
                value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>"
                autofocus
            >
            <!-- Dropdown de sugerencias -->
            <ul id="sugerencias-lista"
                class="list-group position-absolute w-100"
                style="z-index:1000; display:none; top:100%; left:0;">
            </ul>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-acento">Buscar</button>
        </div>
        <?php if ($busco || $dni_perfil > 0): ?>
        <div class="col-auto">
            <a href="index.php?mod=buscador" class="btn btn-outline-secondary btn-sm">Limpiar</a>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php if ($busco && $total === 0): ?>
    <p class="text-secondary">No se encontraron resultados para
        <strong><?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?></strong>.
    </p>

<?php elseif ($total > 1): ?>
    <!-- Multiples resultados -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-secondary" style="font-size:0.85rem;">
            <?php echo $total; ?> resultado<?php echo $total !== 1 ? 's' : ''; ?>
        </span>
        <a href="index.php?mod=buscador&q=<?php echo urlencode($busqueda); ?>&exportar=1"
            class="btn btn-outline-secondary btn-sm">Descargar Excel</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
            <thead>
                <tr>
                    <th>DNI</th>
                    <th>Apellido</th>
                    <th>Nombre</th>
                    <th>Carrera</th>
                    <th>CD</th>
                    <th>CP</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $fila): ?>
                <tr>
                    <td><?php echo htmlspecialchars($fila['dni'],      ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($fila['apellido'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($fila['nombre'],   ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($fila['carrera'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo $fila['padron_cd'] === 'SI'
                        ? '<span class="badge" style="background-color:#a6d900;color:#1a1a2e;">SI</span>'
                        : '<span class="text-secondary">—</span>'; ?></td>
                    <td><?php echo $fila['padron_cp'] === 'SI'
                        ? '<span class="badge" style="background-color:#1a1a2e;color:#fff;">SI</span>'
                        : '<span class="text-secondary">—</span>'; ?></td>
                    <td>
                        <a href="index.php?mod=buscador&perfil=<?php echo $fila['dni']; ?>"
                            class="btn btn-sm btn-acento">Ver</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($perfil !== null): ?>
    <!-- Perfil unificado -->
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <div class="modulo-titulo mb-1">
                <?php echo htmlspecialchars($perfil['apellido'] . ', ' . $perfil['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($perfil['padron_cd'] === 'SI'): ?>
                    <span class="badge ms-1" style="background-color:#a6d900;color:#1a1a2e;font-size:0.75rem;">CD</span>
                <?php endif; ?>
                <?php if ($perfil['padron_cp'] === 'SI'): ?>
                    <span class="badge ms-1" style="background-color:#1a1a2e;color:#fff;font-size:0.75rem;">CP</span>
                <?php endif; ?>
            </div>
            <span class="text-secondary" style="font-size:0.85rem;">
                DNI <?php echo htmlspecialchars($perfil['dni'], ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($perfil['carrera']): ?>
                    &mdash; <?php echo htmlspecialchars($perfil['carrera'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?mod=buscador&perfil=<?php echo $perfil['dni']; ?>&exportar=1"
                class="btn btn-outline-secondary btn-sm">Descargar Excel</a>
            <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">Volver</a>
        </div>
    </div>

    <!-- Tabla de perfil unificado -->
    <div class="table-responsive">
        <table class="table table-bordered" style="font-size:0.88rem;">
            <tbody>
                <?php
                $v = $perfil['vinculos'] ?? [];
                $filas = [
                    'Referente 1' => $v['referente_1'] ?? '—',
                    'Referente 2' => $v['referente_2'] ?? '—',
                    'Referente 3' => $v['referente_3'] ?? '—',
                    'Partido'     => $v['partido']      ?? '—',
                    'Trabajo'     => $v['trabajo']      ?? '—',
                    'Sede'        => $v['sede']         ?? '—',
                    'Municipio'   => $v['municipio']    ?? '—',
                ];
                foreach ($filas as $label => $valor):
                ?>
                <tr>
                    <th style="width:200px;background-color:#f8f9fa;font-weight:500;">
                        <?php echo $label; ?>
                    </th>
                    <td><?php echo htmlspecialchars($valor ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>

                <?php if (!empty($perfil['votos_cd'])): ?>
                <tr>
                    <th style="background-color:#f8f9fa;font-weight:500;">Voto CD 2021</th>
                    <td><?php echo badge_voto($perfil['votos_cd']['voto_cd_2021']); ?></td>
                </tr>
                <tr>
                    <th style="background-color:#f8f9fa;font-weight:500;">Voto CD 2024</th>
                    <td><?php echo badge_voto($perfil['votos_cd']['voto_cd_2024']); ?></td>
                </tr>
                <?php endif; ?>

                <?php if (!empty($perfil['votos_cp'])): ?>
                <?php if ($perfil['votos_cp']['auxiliar']): ?>
                <tr>
                    <th style="background-color:#f8f9fa;font-weight:500;">Tipo CP</th>
                    <td>Docente auxiliar</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th style="background-color:#f8f9fa;font-weight:500;">Voto CP 2017</th>
                    <td><?php echo badge_voto($perfil['votos_cp']['voto_cp_2017']); ?></td>
                </tr>
                <tr>
                    <th style="background-color:#f8f9fa;font-weight:500;">Voto CP 2019</th>
                    <td><?php echo badge_voto($perfil['votos_cp']['voto_cp_2019']); ?></td>
                </tr>
                <tr>
                    <th style="background-color:#f8f9fa;font-weight:500;">Voto CP 2021</th>
                    <td><?php echo badge_voto($perfil['votos_cp']['voto_cp_2021']); ?></td>
                </tr>
                <tr>
                    <th style="background-color:#f8f9fa;font-weight:500;">Voto CP 2024</th>
                    <td><?php echo badge_voto($perfil['votos_cp']['voto_cp_2024']); ?></td>
                </tr>
                <?php endif; ?>

            </tbody>
        </table>
    </div>

<?php endif; ?>

<!-- Script de sugerencias en tiempo real -->
<script>
(function () {
    const input  = document.getElementById('input-busqueda');
    const lista  = document.getElementById('sugerencias-lista');
    let timer    = null;

    if (!input) return;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim();

        // Activar sugerencias con 2 o mas caracteres
        if (q.length < 2) {
            lista.style.display = 'none';
            lista.innerHTML = '';
            return;
        }

        // Esperar 250ms antes de hacer el fetch para no saturar
        timer = setTimeout(function () {
            fetch('index.php?mod=buscador&sugerencias=1&q=' + encodeURIComponent(q))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    lista.innerHTML = '';

                    if (data.length === 0) {
                        lista.style.display = 'none';
                        return;
                    }

                    data.forEach(function (persona) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item list-group-item-action';
                        li.style.cursor = 'pointer';
                        li.style.fontSize = '0.88rem';
                        li.textContent = persona.apellido + ', ' + persona.nombre + ' — ' + persona.dni;

                        // Al hacer click ir directo al perfil
                        li.addEventListener('click', function () {
                            window.location.href = 'index.php?mod=buscador&perfil=' + persona.dni;
                        });

                        lista.appendChild(li);
                    });

                    lista.style.display = 'block';
                })
                .catch(function () {
                    lista.style.display = 'none';
                });
        }, 250);
    });

    // Cerrar sugerencias al hacer click fuera del input
    document.addEventListener('click', function (e) {
        if (e.target !== input) {
            lista.style.display = 'none';
        }
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
