<?php
// resultados.php
define('SUPABASE_URL', 'https://tfeqqlechlakyqorlcga.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRmZXFxbGVjaGxha3lxb3JsY2dhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTk5NTU2MDAsImV4cCI6MjA3NTUzMTYwMH0.K9iVGTq3miOQ1VwDYRTRqUEwfnlq3UMWDv0K8AdTmxI');

session_start();

// Obtener ID del equipo desde la URL
$equipo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
        'status' => $httpCode,
        'response' => $response
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

// Procesar formularios
$error = '';
$success = '';

// Añadir partido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'añadir_partido') {
    $es_local = $_POST['es_local'] === 'local';
    $equipo_contrario = trim($_POST['equipo_contrario']);
    $fecha_hora = $_POST['fecha_hora'];
    
    if (empty($equipo_contrario) || empty($fecha_hora)) {
        $error = 'Todos los campos son obligatorios.';
    } else {
        $result = supabaseRequest('POST', '/rest/v1/partidos', [
            'equipo_id' => $equipo_id,
            'es_local' => $es_local,
            'equipo_contrario' => $equipo_contrario,
            'fecha_hora' => $fecha_hora
        ]);
        
        if ($result['status'] >= 200 && $result['status'] < 300) {
            $success = 'Partido añadido correctamente.';
        } else {
            $error = 'Error al añadir el partido. Código: ' . $result['status'];
        }
    }
}

// Añadir estadísticas básicas del partido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'añadir_estadisticas') {
    $partido_id = intval($_POST['partido_id']);
    $goles_favor = intval($_POST['goles_favor']);
    $goles_contra = intval($_POST['goles_contra']);
    
    // Actualizar goles del partido
    $result = supabaseRequest('PATCH', '/rest/v1/partidos?id=eq.' . $partido_id, [
        'goles_favor' => $goles_favor,
        'goles_contra' => $goles_contra
    ]);
    
    if ($result['status'] >= 200 && $result['status'] < 300) {
        // Procesar estadísticas de jugadores
        foreach ($_POST['jugadores'] as $jugador_id => $estadisticas) {
            $jugador_id = intval($jugador_id);
            
            // Para jugadores de campo: tiros acertados/fallados
            $tiros_acertados = intval($estadisticas['tiros_acertados'] ?? 0);
            $tiros_fallados = intval($estadisticas['tiros_fallados'] ?? 0);
            
            // Para porteros: paradas y goles recibidos
            $paradas = intval($estadisticas['paradas'] ?? 0);
            $goles_recibidos = intval($estadisticas['goles_recibidos'] ?? 0);
            
            // Determinar si es portero
            $jugador = array_filter($jugadores, function($j) use ($jugador_id) {
                return $j['id'] === $jugador_id;
            });
            $jugador = reset($jugador);
            $es_portero = $jugador && $jugador['posicion_ataque'] === 'Portero';
            
            $data = [
                'partido_id' => $partido_id,
                'jugador_id' => $jugador_id
            ];
            
            // Si es portero, usar campos específicos
            if ($es_portero) {
                $data['tiros_acertados'] = $paradas; // Paradas
                $data['tiros_fallados'] = $goles_recibidos; // Goles recibidos
            } else {
                $data['tiros_acertados'] = $tiros_acertados;
                $data['tiros_fallados'] = $tiros_fallados;
            }
            
            // Verificar si ya existen estadísticas
            $existeResponse = supabaseRequest('GET', '/rest/v1/estadisticas_partido?partido_id=eq.' . $partido_id . '&jugador_id=eq.' . $jugador_id);
            $existe = ($existeResponse['status'] === 200 && !empty($existeResponse['data']));
            
            if ($existe) {
                supabaseRequest('PATCH', '/rest/v1/estadisticas_partido?partido_id=eq.' . $partido_id . '&jugador_id=eq.' . $jugador_id, $data);
            } else {
                supabaseRequest('POST', '/rest/v1/estadisticas_partido', $data);
            }
        }
        
        $success = 'Estadísticas guardadas correctamente.';
    } else {
        $error = 'Error al guardar las estadísticas. Código: ' . $result['status'];
    }
}

// Eliminar partido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_partido') {
    $partido_id = intval($_POST['partido_id']);
    
    $result = supabaseRequest('DELETE', '/rest/v1/partidos?id=eq.' . $partido_id);
    
    if ($result['status'] >= 200 && $result['status'] < 300) {
        $success = 'Partido eliminado correctamente.';
    } else {
        $error = 'Error al eliminar el partido. Código: ' . $result['status'];
    }
}

// Obtener partidos del equipo
$partidosResponse = supabaseRequest('GET', '/rest/v1/partidos?equipo_id=eq.' . $equipo_id . '&order=fecha_hora.desc');
$partidos = ($partidosResponse['status'] === 200 && !empty($partidosResponse['data'])) ? $partidosResponse['data'] : [];

// Obtener estadísticas existentes para prellenar formularios
$estadisticasResponse = supabaseRequest('GET', '/rest/v1/estadisticas_partido?select=*');
$estadisticas = ($estadisticasResponse['status'] === 200 && !empty($estadisticasResponse['data'])) ? $estadisticasResponse['data'] : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados - <?= htmlspecialchars($equipo['nombre']) ?></title>
    <link rel="stylesheet" href="resultados.css">
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

        <div class="resultados-container">
            <!-- Sección Añadir Partido -->
            <section class="seccion">
                <h2>Añadir Nuevo Partido</h2>
                <form method="POST" class="form-partido">
                    <input type="hidden" name="accion" value="añadir_partido">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Tipo:</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="es_local" value="local" checked>
                                    <span>Local</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="es_local" value="visitante">
                                    <span>Visitante</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="equipo_contrario">Equipo Contrario:</label>
                            <input type="text" id="equipo_contrario" name="equipo_contrario" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_hora">Fecha y Hora:</label>
                            <input type="datetime-local" id="fecha_hora" name="fecha_hora" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-guardar">Añadir Partido</button>
                </form>
            </section>

            <!-- Lista de Partidos -->
            <section class="seccion">
                <h2>Partidos del Equipo</h2>
                <div class="lista-partidos">
                    <?php if (empty($partidos)): ?>
                        <p class="sin-partidos">No hay partidos registrados.</p>
                    <?php else: ?>
                        <?php foreach ($partidos as $partido): ?>
                            <div class="partido-card">
                                <div class="partido-info">
                                    <div class="partido-equipos">
                                        <?php if ($partido['es_local']): ?>
                                            <span class="equipo-local"><?= htmlspecialchars($equipo['nombre']) ?></span>
                                            <span class="vs">vs</span>
                                            <span class="equipo-visitante"><?= htmlspecialchars($partido['equipo_contrario']) ?></span>
                                        <?php else: ?>
                                            <span class="equipo-visitante"><?= htmlspecialchars($partido['equipo_contrario']) ?></span>
                                            <span class="vs">vs</span>
                                            <span class="equipo-local"><?= htmlspecialchars($equipo['nombre']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="partido-resultado">
                                        <?php if ($partido['goles_favor'] > 0 || $partido['goles_contra'] > 0): ?>
                                            <span class="marcador">
                                                <?= $partido['goles_favor'] ?> - <?= $partido['goles_contra'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="marcador pendiente">Pendiente</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="partido-fecha">
                                        <?= date('d/m/Y H:i', strtotime($partido['fecha_hora'])) ?>
                                        <?= $partido['es_local'] ? '🏠' : '✈️' ?>
                                    </div>
                                </div>
                                
                                <div class="partido-acciones">
                                    <button class="btn-estadisticas" onclick="mostrarEstadisticas(<?= $partido['id'] ?>)">
                                        📊 Añadir Estadísticas
                                    </button>
                                    <button class="btn-eliminar" 
                                            onclick="eliminarPartido(<?= $partido['id'] ?>, '<?= htmlspecialchars($partido['equipo_contrario']) ?>')">
                                        Eliminar
                                    </button>
                                </div>
                            </div>

                            <!-- Modal para Añadir Estadísticas -->
                            <div id="modal-estadisticas-<?= $partido['id'] ?>" class="modal">
                                <div class="modal-contenido modal-grande">
                                    <span class="cerrar" onclick="cerrarModal(<?= $partido['id'] ?>)">&times;</span>
                                    <h3>Añadir Estadísticas - <?= htmlspecialchars($partido['equipo_contrario']) ?></h3>
                                    
                                    <form method="POST" class="form-estadisticas">
                                        <input type="hidden" name="accion" value="añadir_estadisticas">
                                        <input type="hidden" name="partido_id" value="<?= $partido['id'] ?>">
                                        
                                        <!-- Goles del equipo -->
                                        <div class="goles-equipo">
                                            <h4>Goles del Partido</h4>
                                            <div class="form-grid">
                                                <div class="form-group">
                                                    <label for="goles_favor_<?= $partido['id'] ?>">Goles a Favor:</label>
                                                    <input type="number" id="goles_favor_<?= $partido['id'] ?>" 
                                                           name="goles_favor" value="<?= $partido['goles_favor'] ?>" min="0" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="goles_contra_<?= $partido['id'] ?>">Goles en Contra:</label>
                                                    <input type="number" id="goles_contra_<?= $partido['id'] ?>" 
                                                           name="goles_contra" value="<?= $partido['goles_contra'] ?>" min="0" required>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Estadísticas por jugador -->
                                        <div class="estadisticas-jugadores">
                                            <h4>Estadísticas por Jugador</h4>
                                            <div class="jugadores-grid">
                                                <?php foreach ($jugadores as $jugador): ?>
                                                    <?php 
                                                    $estadistica_jugador = array_filter($estadisticas, function($e) use ($jugador, $partido) {
                                                        return $e['jugador_id'] === $jugador['id'] && $e['partido_id'] === $partido['id'];
                                                    });
                                                    $estadistica = reset($estadistica_jugador);
                                                    $es_portero = $jugador['posicion_ataque'] === 'Portero';
                                                    ?>
                                                    
                                                    <div class="jugador-estadistica">
                                                        <div class="jugador-info">
                                                            <img src="<?= $jugador['foto_url'] ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjRjNFM0UzIi8+Cjx0ZXh0IHg9IjIwIiB5PSIyMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEwIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TaW4gRm90bzwvdGV4dD4KPC9zdmc+' ?>" 
                                                                 alt="<?= htmlspecialchars($jugador['nombre_completo']) ?>" 
                                                                 class="foto-jugador">
                                                            <div>
                                                                <strong>#<?= $jugador['dorsal'] ?> <?= htmlspecialchars($jugador['nombre_completo']) ?></strong>
                                                                <div class="posicion"><?= $jugador['posicion_ataque'] ?></div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="estadisticas-form">
                                                            <?php if ($es_portero): ?>
                                                                <!-- Campos para porteros -->
                                                                <div class="form-group-pequeno">
                                                                    <label>Paradas:</label>
                                                                    <input type="number" name="jugadores[<?= $jugador['id'] ?>][paradas]" 
                                                                           value="<?= $estadistica ? $estadistica['tiros_acertados'] : 0 ?>" min="0">
                                                                </div>
                                                                <div class="form-group-pequeno">
                                                                    <label>Goles Recibidos:</label>
                                                                    <input type="number" name="jugadores[<?= $jugador['id'] ?>][goles_recibidos]" 
                                                                           value="<?= $estadistica ? $estadistica['tiros_fallados'] : 0 ?>" min="0">
                                                                </div>
                                                            <?php else: ?>
                                                                <!-- Campos para jugadores de campo -->
                                                                <div class="form-group-pequeno">
                                                                    <label>Tiros Acertados:</label>
                                                                    <input type="number" name="jugadores[<?= $jugador['id'] ?>][tiros_acertados]" 
                                                                           value="<?= $estadistica ? $estadistica['tiros_acertados'] : 0 ?>" min="0">
                                                                </div>
                                                                <div class="form-group-pequeno">
                                                                    <label>Tiros Fallados:</label>
                                                                    <input type="number" name="jugadores[<?= $jugador['id'] ?>][tiros_fallados]" 
                                                                           value="<?= $estadistica ? $estadistica['tiros_fallados'] : 0 ?>" min="0">
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="botones-form">
                                            <button type="submit" class="btn-guardar">Guardar Estadísticas</button>
                                            <button type="button" class="btn-cancelar" onclick="cerrarModal(<?= $partido['id'] ?>)">Cancelar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <!-- Modal Confirmar Eliminación -->
    <div id="modal-eliminar" class="modal">
        <div class="modal-contenido">
            <h3>Confirmar Eliminación</h3>
            <p>¿Estás seguro de que quieres eliminar el partido contra "<span id="nombre-partido-eliminar"></span>"?</p>
            <form id="form-eliminar" method="POST">
                <input type="hidden" name="accion" value="eliminar_partido">
                <input type="hidden" name="partido_id" id="eliminar-partido-id">
                <div class="botones-form">
                    <button type="submit" class="btn-confirmar">Sí, eliminar</button>
                    <button type="button" class="btn-cancelar" onclick="cerrarModalEliminar()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mostrar modal de estadísticas
        function mostrarEstadisticas(partidoId) {
            document.getElementById('modal-estadisticas-' + partidoId).style.display = 'flex';
        }
        
        // Cerrar modal
        function cerrarModal(partidoId) {
            document.getElementById('modal-estadisticas-' + partidoId).style.display = 'none';
        }
        
        // Eliminar partido
        function eliminarPartido(partidoId, equipoContrario) {
            document.getElementById('eliminar-partido-id').value = partidoId;
            document.getElementById('nombre-partido-eliminar').textContent = equipoContrario;
            document.getElementById('modal-eliminar').style.display = 'flex';
        }
        
        function cerrarModalEliminar() {
            document.getElementById('modal-eliminar').style.display = 'none';
        }
        
        // Cerrar modales al hacer clic fuera
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
        
        // Establecer fecha y hora por defecto
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('fecha_hora').value = now.toISOString().slice(0, 16);
        });
    </script>
</body>
</html>