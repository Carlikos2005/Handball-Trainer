<?php
// entrenamientos.php
define('SUPABASE_URL', 'https://tfeqqlechlakyqorlcga.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRmZXFxbGVjaGxha3lxb3JsY2dhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTk5NTU2MDAsImV4cCI6MjA3NTUzMTYwMH0.K9iVGTq3miOQ1VwDYRTRqUEwfnlq3UMWDv0K8AdTmxI');

session_start();

// Obtener ID del equipo desde la URL
$equipo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$fecha_seleccionada = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

if ($equipo_id === 0) {
    header('Location: index.php');
    exit;
}

// Función para peticiones a Supabase
function supabaseRequest($method, $endpoint, $data = null) {
    $ch = curl_init(SUPABASE_URL . $endpoint);
    $headers = [
        'apikey: ' . SUPABASE_KEY,
        'Authorization: Bearer ' . SUPABASE_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'data' => json_decode($response, true),
        'status' => $httpCode
    ];
}

// Obtener información del equipo
$equipoResponse = supabaseRequest('GET', '/rest/v1/equipos?id=eq.' . $equipo_id);
$equipo = ($equipoResponse['status'] === 200 && !empty($equipoResponse['data'])) ? $equipoResponse['data'][0] : null;

if (!$equipo) {
    header('Location: index.php');
    exit;
}

// Obtener jugadores del equipo
$jugadoresResponse = supabaseRequest('GET', '/rest/v1/jugadores?equipo_id=eq.' . $equipo_id . '&order=dorsal.asc');
$jugadores = ($jugadoresResponse['status'] === 200 && !empty($jugadoresResponse['data'])) ? $jugadoresResponse['data'] : [];

// Obtener eventos del equipo
$eventosResponse = supabaseRequest('GET', '/rest/v1/eventos?equipo_id=eq.' . $equipo_id . '&order=fecha.asc,hora.asc');
$eventos = ($eventosResponse['status'] === 200 && !empty($eventosResponse['data'])) ? $eventosResponse['data'] : [];

// Obtener categorías de ejercicios
$categoriasResponse = supabaseRequest('GET', '/rest/v1/categorias_ejercicios?order=id.asc');
$categorias = ($categoriasResponse['status'] === 200 && !empty($categoriasResponse['data'])) ? $categoriasResponse['data'] : [];

// Obtener ejercicios del equipo organizados por categoría
$ejerciciosResponse = supabaseRequest('GET', 
    '/rest/v1/ejercicios?equipo_id=eq.' . $equipo_id . 
    '&select=*,categorias_ejercicios(nombre)&order=categoria_id.asc,nombre.asc'
);
$ejercicios_raw = ($ejerciciosResponse['status'] === 200 && !empty($ejerciciosResponse['data'])) ? $ejerciciosResponse['data'] : [];

// Organizar ejercicios por categoría
$ejercicios_por_categoria = [];
foreach ($ejercicios_raw as $ejercicio) {
    $categoria_id = $ejercicio['categoria_id'];
    if (!isset($ejercicios_por_categoria[$categoria_id])) {
        $ejercicios_por_categoria[$categoria_id] = [
            'categoria_nombre' => $ejercicio['categorias_ejercicios']['nombre'],
            'ejercicios' => []
        ];
    }
    $ejercicios_por_categoria[$categoria_id]['ejercicios'][] = $ejercicio;
}

// Procesar formularios
$error = '';
$success = '';

// Crear evento (entrenamiento o partido)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear_evento') {
        $tipo = $_POST['tipo'];
        $fecha = $_POST['fecha'];
        $hora = $_POST['hora'];
        $camiseta = trim($_POST['camiseta']);
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if (empty($fecha) || empty($hora)) {
            $error = 'La fecha y hora son obligatorias.';
        } else {
            $result = supabaseRequest('POST', '/rest/v1/eventos', [
                'equipo_id' => $equipo_id,
                'tipo' => $tipo,
                'fecha' => $fecha,
                'hora' => $hora,
                'camiseta' => $camiseta,
                'descripcion' => $descripcion
            ]);
            
            if ($result['status'] >= 200 && $result['status'] < 300) {
                $evento_id = $result['data'][0]['id'];
                $success = ucfirst($tipo) . ' creado correctamente.';
                
                // Crear registros de asistencia para todos los jugadores (inicialmente no marcados)
                foreach ($jugadores as $jugador) {
                    supabaseRequest('POST', '/rest/v1/asistencia', [
                        'evento_id' => $evento_id,
                        'jugador_id' => $jugador['id'],
                        'asistio' => false
                    ]);
                }
                
                // Redirigir al evento creado
                header('Location: entrenamientos.php?id=' . $equipo_id . '&evento=' . $evento_id);
                exit;
            } else {
                $error = 'Error al crear el evento. Código: ' . $result['status'];
            }
        }
    }
    
    // Editar evento
    elseif ($_POST['accion'] === 'editar_evento') {
        $evento_id = intval($_POST['evento_id']);
        $hora = $_POST['hora'];
        $camiseta = trim($_POST['camiseta']);
        $descripcion = trim($_POST['descripcion'] ?? '');
        
        if (empty($hora)) {
            $error = 'La hora es obligatoria.';
        } else {
            $result = supabaseRequest('PATCH', '/rest/v1/eventos?id=eq.' . $evento_id, [
                'hora' => $hora,
                'camiseta' => $camiseta,
                'descripcion' => $descripcion
            ]);
            
            if ($result['status'] >= 200 && $result['status'] < 300) {
                $success = 'Evento actualizado correctamente.';
                // Recargar la página para mostrar los cambios
                header('Location: entrenamientos.php?id=' . $equipo_id . '&evento=' . $evento_id);
                exit;
            } else {
                $error = 'Error al actualizar el evento. Código: ' . $result['status'];
            }
        }
    }
    
    // Añadir ejercicio a entrenamiento
    elseif ($_POST['accion'] === 'añadir_ejercicio') {
        $evento_id = intval($_POST['evento_id']);
        $tipo_ejercicio = $_POST['tipo_ejercicio'];
        $ejercicio_id = $_POST['ejercicio_id'] ? intval($_POST['ejercicio_id']) : null;
        $nuevo_ejercicio = trim($_POST['nuevo_ejercicio'] ?? '');
        $duracion = intval($_POST['duracion'] ?? 0);
        $categoria_id = intval($_POST['categoria_id']);
        
        if ($tipo_ejercicio === 'nuevo' && empty($nuevo_ejercicio)) {
            $error = 'El nombre del nuevo ejercicio es obligatorio.';
        } elseif ($tipo_ejercicio === 'existente' && empty($ejercicio_id)) {
            $error = 'Debes seleccionar un ejercicio existente.';
        } else {
            // Si es un nuevo ejercicio, NO lo guardamos en la base de datos general
            // Solo lo añadimos directamente al entrenamiento
            if ($tipo_ejercicio === 'nuevo') {
                // Crear un "ejercicio temporal" solo para este entrenamiento
                // Usamos un ID negativo para indicar que es temporal
                $ejercicio_temporal_id = -time(); // ID temporal único
                
                // Añadir ejercicio al entrenamiento con nombre temporal
                $result = supabaseRequest('POST', '/rest/v1/ejercicios_entrenamiento', [
                    'evento_id' => $evento_id,
                    'ejercicio_temporal' => $nuevo_ejercicio, // Guardamos el nombre directamente
                    'categoria_temporal' => $categoria_id, // Guardamos la categoría también
                    'orden' => 1, // Orden temporal
                    'duracion' => $duracion
                ]);
                
                if ($result['status'] >= 200 && $result['status'] < 300) {
                    $success = 'Ejercicio añadido al entrenamiento.';
                } else {
                    $error = 'Error al añadir el ejercicio. Código: ' . $result['status'];
                }
            } else {
                // Ejercicio existente - añadirlo normalmente
                // Obtener el último orden
                $ordenResponse = supabaseRequest('GET', '/rest/v1/ejercicios_entrenamiento?evento_id=eq.' . $evento_id . '&select=orden&order=orden.desc&limit=1');
                $ultimo_orden = ($ordenResponse['status'] === 200 && !empty($ordenResponse['data'])) ? $ordenResponse['data'][0]['orden'] : 0;
                
                $result = supabaseRequest('POST', '/rest/v1/ejercicios_entrenamiento', [
                    'evento_id' => $evento_id,
                    'ejercicio_id' => $ejercicio_id,
                    'orden' => $ultimo_orden + 1,
                    'duracion' => $duracion
                ]);
                
                if ($result['status'] >= 200 && $result['status'] < 300) {
                    $success = 'Ejercicio añadido al entrenamiento.';
                } else {
                    $error = 'Error al añadir el ejercicio. Código: ' . $result['status'];
                }
            }
        }
    }
    
    // Guardar asistencia
    elseif ($_POST['accion'] === 'guardar_asistencia') {
        $evento_id = intval($_POST['evento_id']);
        $asistencias = $_POST['asistencias'] ?? [];
        
        foreach ($jugadores as $jugador) {
            $asistio = isset($asistencias[$jugador['id']]);
            
            // Verificar si ya existe registro
            $existeResponse = supabaseRequest('GET', '/rest/v1/asistencia?evento_id=eq.' . $evento_id . '&jugador_id=eq.' . $jugador['id']);
            $existe = ($existeResponse['status'] === 200 && !empty($existeResponse['data']));
            
            if ($existe) {
                // Actualizar
                supabaseRequest('PATCH', '/rest/v1/asistencia?evento_id=eq.' . $evento_id . '&jugador_id=eq.' . $jugador['id'], [
                    'asistio' => $asistio
                ]);
            } else {
                // Crear
                supabaseRequest('POST', '/rest/v1/asistencia', [
                    'evento_id' => $evento_id,
                    'jugador_id' => $jugador['id'],
                    'asistio' => $asistio
                ]);
            }
        }
        
        $success = 'Asistencia guardada correctamente.';
    }
    
    // Eliminar evento
    elseif ($_POST['accion'] === 'eliminar_evento') {
        $evento_id = intval($_POST['evento_id']);
        
        // Primero eliminar la asistencia relacionada
        supabaseRequest('DELETE', '/rest/v1/asistencia?evento_id=eq.' . $evento_id);
        
        // Eliminar ejercicios del entrenamiento
        supabaseRequest('DELETE', '/rest/v1/ejercicios_entrenamiento?evento_id=eq.' . $evento_id);
        
        // Finalmente eliminar el evento
        $result = supabaseRequest('DELETE', '/rest/v1/eventos?id=eq.' . $evento_id);
        
        if ($result['status'] >= 200 && $result['status'] < 300) {
            $success = 'Evento eliminado correctamente.';
            header('Location: entrenamientos.php?id=' . $equipo_id);
            exit;
        } else {
            $error = 'Error al eliminar el evento. Código: ' . $result['status'];
        }
    }
    
    // Crear ejercicio independiente (sin añadir a entrenamiento)
    elseif ($_POST['accion'] === 'crear_ejercicio') {
        $nombre = trim($_POST['nombre_ejercicio']);
        $categoria_id = intval($_POST['categoria_id_ejercicio']);
        
        if (empty($nombre)) {
            $error = 'El nombre del ejercicio es obligatorio.';
        } else {
            $result = supabaseRequest('POST', '/rest/v1/ejercicios', [
                'categoria_id' => $categoria_id,
                'nombre' => $nombre,
                'equipo_id' => $equipo_id
            ]);
            
            if ($result['status'] >= 200 && $result['status'] < 300) {
                $success = 'Ejercicio creado correctamente.';
            } else {
                $error = 'Error al crear el ejercicio. Código: ' . $result['status'];
            }
        }
    }
}

// Obtener evento específico si se solicita
$evento_actual = null;
$ejercicios_entrenamiento = [];
$asistencia_actual = [];

if (isset($_GET['evento'])) {
    $evento_id = intval($_GET['evento']);
    $eventoResponse = supabaseRequest('GET', '/rest/v1/eventos?id=eq.' . $evento_id);
    $evento_actual = ($eventoResponse['status'] === 200 && !empty($eventoResponse['data'])) ? $eventoResponse['data'][0] : null;
    
    if ($evento_actual) {
        // Obtener ejercicios del entrenamiento (incluyendo ejercicios temporales)
        $ejerciciosEntrenamientoResponse = supabaseRequest('GET', 
            '/rest/v1/ejercicios_entrenamiento?evento_id=eq.' . $evento_id . 
            '&select=*,ejercicios(*,categorias_ejercicios(nombre))&order=orden.asc'
        );
        $ejercicios_entrenamiento = ($ejerciciosEntrenamientoResponse['status'] === 200 && !empty($ejerciciosEntrenamientoResponse['data'])) ? 
            $ejerciciosEntrenamientoResponse['data'] : [];
        
        // Obtener asistencia
        $asistenciaResponse = supabaseRequest('GET', '/rest/v1/asistencia?evento_id=eq.' . $evento_id);
        $asistencia_actual = ($asistenciaResponse['status'] === 200 && !empty($asistenciaResponse['data'])) ? 
            $asistenciaResponse['data'] : [];
    }
}

// Preparar eventos para el calendario
$eventos_calendario = [];
foreach ($eventos as $evento) {
    $eventos_calendario[$evento['fecha']][] = $evento;
}

// Obtener mes y año actual
$mes_actual = date('m');
$ano_actual = date('Y');
if (isset($_GET['mes']) && isset($_GET['ano'])) {
    $mes_actual = intval($_GET['mes']);
    $ano_actual = intval($_GET['ano']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrenamientos - <?= htmlspecialchars($equipo['nombre']) ?></title>
    <link rel="stylesheet" href="entrenamientos.css">
</head>
<body>
    <header>
        <a href="equipo.php?id=<?= $equipo_id ?>" class="btn-volver">← Volver</a>
        <div class="equipo-header-center">
            <img src="<?= htmlspecialchars($equipo['escudo_url']) ?>" 
                 alt="Escudo de <?= htmlspecialchars($equipo['nombre']) ?>" 
                 class="escudo-equipo">
            <h1><?= htmlspecialchars($equipo['nombre']) ?></h1>
        </div>
        <div class="header-spacer"></div>
    </header>

    <main>
        <?php if ($error): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="mensaje success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="entrenamientos-container">
            <?php if (!$evento_actual): ?>
                <!-- Vista Calendario -->
                <section class="seccion">
                    <h2>Calendario de Entrenamientos y Partidos</h2>
                    
                    <!-- Botón para crear ejercicio independiente -->
                    <div class="acciones-superiores">
                        <button class="btn-crear-ejercicio" onclick="abrirModalCrearEjercicio()">
                            ➕ Crear Nuevo Ejercicio
                        </button>
                    </div>
                    
                    <!-- Navegación del calendario -->
                    <div class="calendario-nav">
                        <?php
                        $mes_anterior = $mes_actual - 1;
                        $ano_anterior = $ano_actual;
                        if ($mes_anterior < 1) {
                            $mes_anterior = 12;
                            $ano_anterior--;
                        }
                        
                        $mes_siguiente = $mes_actual + 1;
                        $ano_siguiente = $ano_actual;
                        if ($mes_siguiente > 12) {
                            $mes_siguiente = 1;
                            $ano_siguiente++;
                        }
                        ?>
                        
                        <a href="entrenamientos.php?id=<?= $equipo_id ?>&mes=<?= $mes_anterior ?>&ano=<?= $ano_anterior ?>" class="btn-nav">
                            ← Mes Anterior
                        </a>
                        
                        <h3><?= date('F Y', mktime(0, 0, 0, $mes_actual, 1, $ano_actual)) ?></h3>
                        
                        <a href="entrenamientos.php?id=<?= $equipo_id ?>&mes=<?= $mes_siguiente ?>&ano=<?= $ano_siguiente ?>" class="btn-nav">
                            Mes Siguiente →
                        </a>
                    </div>

                    <!-- Calendario -->
                    <div class="calendario">
                        <div class="dias-semana">
                            <div>Lun</div>
                            <div>Mar</div>
                            <div>Mié</div>
                            <div>Jue</div>
                            <div>Vie</div>
                            <div>Sáb</div>
                            <div>Dom</div>
                        </div>
                        
                        <div class="dias-mes">
                            <?php
                            $primer_dia = date('N', mktime(0, 0, 0, $mes_actual, 1, $ano_actual));
                            $dias_mes = date('t', mktime(0, 0, 0, $mes_actual, 1, $ano_actual));
                            
                            // Días vacíos al inicio
                            for ($i = 1; $i < $primer_dia; $i++) {
                                echo '<div class="dia vacio"></div>';
                            }
                            
                            // Días del mes
                            for ($dia = 1; $dia <= $dias_mes; $dia++) {
                                $fecha_actual = date('Y-m-d', mktime(0, 0, 0, $mes_actual, $dia, $ano_actual));
                                $tiene_eventos = isset($eventos_calendario[$fecha_actual]);
                                $es_hoy = $fecha_actual === date('Y-m-d');
                                $clases = 'dia';
                                if ($es_hoy) $clases .= ' hoy';
                                if ($tiene_eventos) $clases .= ' con-eventos';
                                
                                echo '<div class="' . $clases . '" data-fecha="' . $fecha_actual . '">';
                                echo '<span class="numero-dia">' . $dia . '</span>';
                                
                                if ($tiene_eventos) {
                                    echo '<div class="eventos-dia">';
                                    foreach ($eventos_calendario[$fecha_actual] as $evento) {
                                        $clase_evento = $evento['tipo'] === 'partido' ? 'partido' : 'entrenamiento';
                                        $evento_id = $evento['id'];
                                        echo '<div class="evento ' . $clase_evento . '" onclick="window.location.href=\'entrenamientos.php?id=' . $equipo_id . '&evento=' . $evento_id . '\'">';
                                        echo date('H:i', strtotime($evento['hora'])) . ' - ' . ucfirst($evento['tipo']);
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                                
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </section>

                <!-- Modal para crear evento -->
                <div id="modal-crear-evento" class="modal">
                    <div class="modal-contenido">
                        <span class="cerrar" onclick="cerrarModal('modal-crear-evento')">&times;</span>
                        <h3>Crear Nuevo Evento</h3>
                        
                        <form method="POST" class="form-evento">
                            <input type="hidden" name="accion" value="crear_evento">
                            <input type="hidden" name="fecha" id="fecha-evento">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="tipo">Tipo de Evento:</label>
                                    <select id="tipo" name="tipo" required>
                                        <option value="entrenamiento">Entrenamiento</option>
                                        <option value="partido">Partido</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="hora">Hora:</label>
                                    <input type="time" id="hora" name="hora" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="camiseta">Camiseta:</label>
                                    <input type="text" id="camiseta" name="camiseta" placeholder="Ej: Camiseta roja">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="descripcion">Descripción (opcional):</label>
                                <textarea id="descripcion" name="descripcion" rows="3" placeholder="Detalles adicionales..."></textarea>
                            </div>
                            
                            <div class="botones-form">
                                <button type="submit" class="btn-guardar">Crear Evento</button>
                                <button type="button" class="btn-cancelar" onclick="cerrarModal('modal-crear-evento')">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Modal para crear ejercicio independiente -->
                <div id="modal-crear-ejercicio" class="modal">
                    <div class="modal-contenido">
                        <span class="cerrar" onclick="cerrarModal('modal-crear-ejercicio')">&times;</span>
                        <h3>Crear Nuevo Ejercicio</h3>
                        
                        <form method="POST" class="form-ejercicio-independiente">
                            <input type="hidden" name="accion" value="crear_ejercicio">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="categoria_id_ejercicio">Categoría:</label>
                                    <select id="categoria_id_ejercicio" name="categoria_id_ejercicio" required>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="nombre_ejercicio">Nombre del Ejercicio:</label>
                                    <input type="text" id="nombre_ejercicio" name="nombre_ejercicio" required placeholder="Ej: Circuito de velocidad">
                                </div>
                            </div>
                            
                            <div class="botones-form">
                                <button type="submit" class="btn-guardar">Crear Ejercicio</button>
                                <button type="button" class="btn-cancelar" onclick="cerrarModal('modal-crear-ejercicio')">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php else: ?>
                <!-- Vista Detalle del Evento -->
                <section class="seccion">
                    <div class="evento-header">
                        <a href="entrenamientos.php?id=<?= $equipo_id ?>" class="btn-volver-evento">← Volver al calendario</a>
                        <h2>
                            <?= ucfirst($evento_actual['tipo']) ?> - 
                            <?= date('d/m/Y', strtotime($evento_actual['fecha'])) ?> 
                            a las <?= date('H:i', strtotime($evento_actual['hora'])) ?>
                        </h2>
                        <div class="acciones-evento">
                            <button class="btn-editar-evento" onclick="abrirModalEditarEvento()">
                                ✏️ Editar Evento
                            </button>
                            <button class="btn-eliminar-evento" onclick="confirmarEliminarEvento()">
                                🗑️ Eliminar Evento
                            </button>
                        </div>
                    </div>

                    <!-- Información básica del evento -->
                    <div class="info-evento">
                        <div class="info-item">
                            <strong>Camiseta:</strong>
                            <span><?= $evento_actual['camiseta'] ?: 'No especificada' ?></span>
                        </div>
                        <?php if ($evento_actual['descripcion']): ?>
                        <div class="info-item">
                            <strong>Descripción:</strong>
                            <span><?= htmlspecialchars($evento_actual['descripcion']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($evento_actual['tipo'] === 'entrenamiento'): ?>
                        <!-- Ejercicios del entrenamiento -->
                        <div class="ejercicios-seccion">
                            <h3>Ejercicios del Entrenamiento</h3>
                            
                            <?php if (empty($ejercicios_entrenamiento)): ?>
                                <p class="sin-ejercicios">No hay ejercicios añadidos aún.</p>
                            <?php else: ?>
                                <div class="lista-ejercicios">
                                    <?php foreach ($ejercicios_entrenamiento as $ejercicio_ent): ?>
                                        <div class="ejercicio-item">
                                            <div class="ejercicio-info">
                                                <?php if (isset($ejercicio_ent['ejercicios'])): ?>
                                                    <strong><?= htmlspecialchars($ejercicio_ent['ejercicios']['nombre']) ?></strong>
                                                    <span class="categoria"><?= $ejercicio_ent['ejercicios']['categorias_ejercicios']['nombre'] ?></span>
                                                <?php else: ?>
                                                    <strong><?= htmlspecialchars($ejercicio_ent['ejercicio_temporal']) ?></strong>
                                                    <span class="categoria temporal">Temporal</span>
                                                <?php endif; ?>
                                                <?php if ($ejercicio_ent['duracion']): ?>
                                                    <span class="duracion"><?= $ejercicio_ent['duracion'] ?> min</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Formulario para añadir ejercicio -->
                            <div class="añadir-ejercicio">
                                <h4>Añadir Ejercicio</h4>
                                <form method="POST" class="form-ejercicio">
                                    <input type="hidden" name="accion" value="añadir_ejercicio">
                                    <input type="hidden" name="evento_id" value="<?= $evento_actual['id'] ?>">
                                    
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="categoria_id">Categoría:</label>
                                            <select id="categoria_id" name="categoria_id" required>
                                                <?php foreach ($categorias as $categoria): ?>
                                                    <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="tipo_ejercicio">Tipo de Ejercicio:</label>
                                            <select id="tipo_ejercicio" name="tipo_ejercicio" required>
                                                <option value="nuevo">Nuevo Ejercicio (solo para este entrenamiento)</option>
                                                <option value="existente">Ejercicio Existente</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group" id="grupo-nuevo-ejercicio">
                                            <label for="nuevo_ejercicio">Nombre del Nuevo Ejercicio:</label>
                                            <input type="text" id="nuevo_ejercicio" name="nuevo_ejercicio" placeholder="Ej: Circuito de velocidad">
                                        </div>
                                        
                                        <div class="form-group" id="grupo-ejercicio-existente" style="display: none;">
                                            <label for="ejercicio_id">Seleccionar Ejercicio Existente:</label>
                                            <div class="desplegable-categorias">
                                                <?php foreach ($ejercicios_por_categoria as $categoria_id => $categoria_data): ?>
                                                    <div class="categoria-grupo">
                                                        <h5 class="categoria-titulo"><?= htmlspecialchars($categoria_data['categoria_nombre']) ?></h5>
                                                        <div class="ejercicios-lista">
                                                            <?php foreach ($categoria_data['ejercicios'] as $ejercicio): ?>
                                                                <label class="ejercicio-option">
                                                                    <input type="radio" name="ejercicio_id" value="<?= $ejercicio['id'] ?>">
                                                                    <span class="ejercicio-nombre"><?= htmlspecialchars($ejercicio['nombre']) ?></span>
                                                                </label>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="duracion">Duración (minutos):</label>
                                            <input type="number" id="duracion" name="duracion" min="0" placeholder="Opcional">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn-añadir">Añadir Ejercicio</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Lista de Asistencia -->
                    <div class="asistencia-seccion">
                        <h3>Lista de Asistencia</h3>
                        <form method="POST" class="form-asistencia">
                            <input type="hidden" name="accion" value="guardar_asistencia">
                            <input type="hidden" name="evento_id" value="<?= $evento_actual['id'] ?>">
                            
                            <div class="lista-asistencia">
                                <?php foreach ($jugadores as $jugador): 
                                    $asistio = false;
                                    foreach ($asistencia_actual as $asistencia) {
                                        if ($asistencia['jugador_id'] === $jugador['id']) {
                                            $asistio = $asistencia['asistio'];
                                            break;
                                        }
                                    }
                                ?>
                                    <div class="jugador-asistencia">
                                        <label class="checkbox-asistencia">
                                            <input type="checkbox" name="asistencias[<?= $jugador['id'] ?>]" 
                                                   <?= $asistio ? 'checked' : '' ?>>
                                            <span class="checkmark"></span>
                                        </label>
                                        <img src="<?= $jugador['foto_url'] ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjRjNFM0UzIi8+Cjx0ZXh0IHg9IjIwIiB5PSIyMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEwIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TaW4gRm90bzwvdGV4dD4KPC9zdmc+' ?>" 
                                             alt="<?= htmlspecialchars($jugador['nombre_completo']) ?>" 
                                             class="foto-jugador">
                                        <div class="jugador-info">
                                            <strong>#<?= $jugador['dorsal'] ?> <?= htmlspecialchars($jugador['nombre_completo']) ?></strong>
                                            <span><?= $jugador['posicion_ataque'] ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="submit" class="btn-guardar-asistencia">Guardar Asistencia</button>
                        </form>
                    </div>
                </section>

                <!-- Modal para editar evento -->
                <div id="modal-editar-evento" class="modal">
                    <div class="modal-contenido">
                        <span class="cerrar" onclick="cerrarModal('modal-editar-evento')">&times;</span>
                        <h3>Editar Evento</h3>
                        
                        <form method="POST" class="form-editar-evento">
                            <input type="hidden" name="accion" value="editar_evento">
                            <input type="hidden" name="evento_id" value="<?= $evento_actual['id'] ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="hora_editar">Hora:</label>
                                    <input type="time" id="hora_editar" name="hora" value="<?= date('H:i', strtotime($evento_actual['hora'])) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="camiseta_editar">Camiseta:</label>
                                    <input type="text" id="camiseta_editar" name="camiseta" value="<?= htmlspecialchars($evento_actual['camiseta'] ?? '') ?>" placeholder="Ej: Camiseta roja">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="descripcion_editar">Descripción (opcional):</label>
                                <textarea id="descripcion_editar" name="descripcion" rows="3" placeholder="Detalles adicionales..."><?= htmlspecialchars($evento_actual['descripcion'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="botones-form">
                                <button type="submit" class="btn-guardar">Guardar Cambios</button>
                                <button type="button" class="btn-cancelar" onclick="cerrarModal('modal-editar-evento')">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Modal para confirmar eliminación de evento -->
                <div id="modal-eliminar-evento" class="modal">
                    <div class="modal-contenido">
                        <h3>Confirmar Eliminación</h3>
                        <p>¿Estás seguro de que quieres eliminar este evento?</p>
                        <p><strong>Esta acción no se puede deshacer.</strong></p>
                        
                        <form method="POST" class="form-eliminar-evento">
                            <input type="hidden" name="accion" value="eliminar_evento">
                            <input type="hidden" name="evento_id" value="<?= $evento_actual['id'] ?>">
                            
                            <div class="botones-form">
                                <button type="submit" class="btn-confirmar-eliminar">Sí, eliminar</button>
                                <button type="button" class="btn-cancelar" onclick="cerrarModal('modal-eliminar-evento')">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Modal para crear evento
        function abrirModalCrearEvento(fecha) {
            // Verificar si ya hay eventos en esta fecha
            const diaElement = document.querySelector(`.dia[data-fecha="${fecha}"]`);
            if (diaElement && diaElement.classList.contains('con-eventos')) {
                // Si ya hay eventos, no abrir modal de creación
                return;
            }
            
            document.getElementById('fecha-evento').value = fecha;
            document.getElementById('modal-crear-evento').style.display = 'flex';
        }

        function abrirModalCrearEjercicio() {
            document.getElementById('modal-crear-ejercicio').style.display = 'flex';
        }

        function abrirModalEditarEvento() {
            document.getElementById('modal-editar-evento').style.display = 'flex';
        }

        function cerrarModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function confirmarEliminarEvento() {
            document.getElementById('modal-eliminar-evento').style.display = 'flex';
        }

        // Click en días del calendario
        document.querySelectorAll('.dia:not(.vacio)').forEach(dia => {
            dia.addEventListener('click', () => {
                const fecha = dia.dataset.fecha;
                
                // Si el día tiene eventos, ir al primer evento
                if (dia.classList.contains('con-eventos')) {
                    const primerEvento = dia.querySelector('.evento');
                    if (primerEvento) {
                        primerEvento.click();
                    }
                } else {
                    // Si no tiene eventos, abrir modal para crear uno
                    abrirModalCrearEvento(fecha);
                }
            });
        });

        // Cerrar modal al hacer clic fuera
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });

        // Mostrar/ocultar campos según tipo de ejercicio
        const tipoEjercicioSelect = document.getElementById('tipo_ejercicio');
        const grupoNuevo = document.getElementById('grupo-nuevo-ejercicio');
        const grupoExistente = document.getElementById('grupo-ejercicio-existente');

        if (tipoEjercicioSelect) {
            tipoEjercicioSelect.addEventListener('change', () => {
                if (tipoEjercicioSelect.value === 'nuevo') {
                    grupoNuevo.style.display = 'block';
                    grupoExistente.style.display = 'none';
                    document.getElementById('nuevo_ejercicio').required = true;
                    // Limpiar selección de ejercicios existentes
                    document.querySelectorAll('input[name="ejercicio_id"]').forEach(radio => {
                        radio.checked = false;
                    });
                } else {
                    grupoNuevo.style.display = 'none';
                    grupoExistente.style.display = 'block';
                    document.getElementById('nuevo_ejercicio').required = false;
                }
            });
        }

        // Establecer hora actual por defecto
        document.addEventListener('DOMContentLoaded', () => {
            const now = new Date();
            const hora = now.getHours().toString().padStart(2, '0') + ':' + 
                        now.getMinutes().toString().padStart(2, '0');
            const horaInput = document.getElementById('hora');
            if (horaInput) {
                horaInput.value = hora;
            }
        });

        // Manejar clic en opciones de ejercicios
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('ejercicio-nombre')) {
                const radio = e.target.previousElementSibling;
                radio.checked = true;
                
                // Resaltar la opción seleccionada
                document.querySelectorAll('.ejercicio-option').forEach(option => {
                    option.classList.remove('selected');
                });
                radio.parentElement.classList.add('selected');
            }
        });
    </script>
</body>
</html>