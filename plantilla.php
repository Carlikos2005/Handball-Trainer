<?php
// plantilla.php
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

// Función para subir foto de jugador
function uploadFotoJugador($filePath, $fileName) {
    $fileContent = file_get_contents($filePath);
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    
    $ch = curl_init(SUPABASE_URL . '/storage/v1/object/fotos-jugadores/' . $fileName);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: image/' . $extension,
            'x-upsert: true'
        ],
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}

// Obtener información del equipo
$equipoResponse = supabaseRequest('GET', '/rest/v1/equipos?id=eq.' . $equipo_id);
$equipo = ($equipoResponse['status'] === 200 && !empty($equipoResponse['data'])) ? $equipoResponse['data'][0] : null;

if (!$equipo) {
    header('Location: index.php');
    exit;
}

// Procesar formularios
$error = '';
$success = '';

// Añadir jugador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'añadir') {
    $nombre_completo = trim($_POST['nombre_completo']);
    $dorsal = intval($_POST['dorsal']);
    $posicion_ataque = $_POST['posicion_ataque'];
    $posicion_defensa = $_POST['posicion_defensa'];
    $foto = $_FILES['foto'] ?? null;
    
    if (empty($nombre_completo)) {
        $error = 'El nombre completo es obligatorio.';
    } else {
        $foto_url = null;
        
        // Subir foto si se proporcionó
        if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
            $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
            $filename = uniqid('jugador_') . '.' . strtolower($extension);
            
            if (uploadFotoJugador($foto['tmp_name'], $filename)) {
                $foto_url = SUPABASE_URL . '/storage/v1/object/public/fotos-jugadores/' . $filename;
            }
        }
        
        // Crear jugador
        $result = supabaseRequest('POST', '/rest/v1/jugadores', [
            'equipo_id' => $equipo_id,
            'nombre_completo' => $nombre_completo,
            'dorsal' => $dorsal,
            'posicion_ataque' => $posicion_ataque,
            'posicion_defensa' => $posicion_defensa,
            'foto_url' => $foto_url
        ]);
        
        if ($result['status'] >= 200 && $result['status'] < 300) {
            $success = 'Jugador añadido correctamente.';
        } else {
            $error = 'Error al añadir el jugador. Código: ' . $result['status'];
        }
    }
}

// Editar jugador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $jugador_id = intval($_POST['jugador_id']);
    $nombre_completo = trim($_POST['nombre_completo']);
    $dorsal = intval($_POST['dorsal']);
    $posicion_ataque = $_POST['posicion_ataque'];
    $posicion_defensa = $_POST['posicion_defensa'];
    $foto = $_FILES['foto'] ?? null;
    
    $updateData = [
        'nombre_completo' => $nombre_completo,
        'dorsal' => $dorsal,
        'posicion_ataque' => $posicion_ataque,
        'posicion_defensa' => $posicion_defensa
    ];
    
    // Subir nueva foto si se proporcionó
    if ($foto && $foto['error'] === UPLOAD_ERR_OK) {
        $extension = pathinfo($foto['name'], PATHINFO_EXTENSION);
        $filename = uniqid('jugador_') . '.' . strtolower($extension);
        
        if (uploadFotoJugador($foto['tmp_name'], $filename)) {
            $updateData['foto_url'] = SUPABASE_URL . '/storage/v1/object/public/fotos-jugadores/' . $filename;
        }
    }
    
    $result = supabaseRequest('PATCH', '/rest/v1/jugadores?id=eq.' . $jugador_id, $updateData);
    
    if ($result['status'] >= 200 && $result['status'] < 300) {
        $success = 'Jugador actualizado correctamente.';
    } else {
        $error = 'Error al actualizar el jugador. Código: ' . $result['status'];
    }
}

// Eliminar jugador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $jugador_id = intval($_POST['jugador_id']);
    
    $result = supabaseRequest('DELETE', '/rest/v1/jugadores?id=eq.' . $jugador_id);
    
    if ($result['status'] >= 200 && $result['status'] < 300) {
        $success = 'Jugador eliminado correctamente.';
    } else {
        $error = 'Error al eliminar el jugador. Código: ' . $result['status'];
    }
}

// Obtener jugadores del equipo
$jugadoresResponse = supabaseRequest('GET', '/rest/v1/jugadores?equipo_id=eq.' . $equipo_id . '&order=dorsal.asc');
$jugadores = ($jugadoresResponse['status'] === 200 && !empty($jugadoresResponse['data'])) ? $jugadoresResponse['data'] : [];

// Agrupar jugadores por posición de ataque
$jugadores_por_posicion = [];
foreach ($jugadores as $jugador) {
    $posicion = $jugador['posicion_ataque'];
    if (!isset($jugadores_por_posicion[$posicion])) {
        $jugadores_por_posicion[$posicion] = [];
    }
    $jugadores_por_posicion[$posicion][] = $jugador;
}

// Posiciones ordenadas
$posiciones_orden = ['Portero', 'Extremo', 'Lateral', 'Central', 'Pivote'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantilla - <?= htmlspecialchars($equipo['nombre']) ?></title>
    <link rel="stylesheet" href="plantilla.css">
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

        <div class="plantilla-container">
            <!-- Sección Añadir Jugador -->
            <section class="seccion">
                <h2>Añadir Nuevo Jugador</h2>
                <form method="POST" enctype="multipart/form-data" class="form-jugador">
                    <input type="hidden" name="accion" value="añadir">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="foto">Foto:</label>
                            <input type="file" id="foto" name="foto" accept="image/*">
                            <img id="preview-foto" src="#" alt="Vista previa" style="display:none;">
                        </div>
                        
                        <div class="form-group">
                            <label for="nombre_completo">Nombre Completo:</label>
                            <input type="text" id="nombre_completo" name="nombre_completo" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="dorsal">Dorsal:</label>
                            <input type="number" id="dorsal" name="dorsal" min="1" max="99">
                        </div>
                        
                        <div class="form-group">
                            <label for="posicion_ataque">Posición Ataque:</label>
                            <select id="posicion_ataque" name="posicion_ataque" required>
                                <option value="Portero">Portero</option>
                                <option value="Extremo">Extremo</option>
                                <option value="Lateral">Lateral</option>
                                <option value="Central">Central</option>
                                <option value="Pivote">Pivote</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="posicion_defensa">Posición Defensa:</label>
                            <select id="posicion_defensa" name="posicion_defensa" required>
                                <option value="Portero">Portero</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-guardar">Añadir Jugador</button>
                </form>
            </section>

            <!-- Lista Completa por Dorsal -->
            <section class="seccion">
                <h2>Lista de Jugadores (por Dorsal)</h2>
                <div class="lista-jugadores">
                    <?php if (empty($jugadores)): ?>
                        <p class="sin-jugadores">No hay jugadores en la plantilla.</p>
                    <?php else: ?>
                        <?php foreach ($jugadores as $jugador): ?>
                            <div class="jugador-card" data-jugador-id="<?= $jugador['id'] ?>">
                                <div class="jugador-info">
                                    <img src="<?= $jugador['foto_url'] ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNFM0UzIi8+Cjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TaW4gRm90bzwvdGV4dD4KPC9zdmc+' ?>" 
                                         alt="<?= htmlspecialchars($jugador['nombre_completo']) ?>" 
                                         class="foto-jugador">
                                    <div class="jugador-datos">
                                        <h3>#<?= htmlspecialchars($jugador['dorsal']) ?> - <?= htmlspecialchars($jugador['nombre_completo']) ?></h3>
                                        <p>Ataque: <?= htmlspecialchars($jugador['posicion_ataque']) ?> | Defensa: <?= htmlspecialchars($jugador['posicion_defensa']) ?></p>
                                    </div>
                                </div>
                                <div class="jugador-acciones">
                                    <button class="btn-editar" onclick="editarJugador(<?= $jugador['id'] ?>)">✏️</button>
                                    <button class="btn-eliminar" onclick="eliminarJugador(<?= $jugador['id'] ?>, '<?= htmlspecialchars($jugador['nombre_completo']) ?>')">🗑️</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Listas por Posición -->
            <section class="seccion">
                <h2>Listas por Posición</h2>
                <div class="listas-posicion">
                    <?php foreach ($posiciones_orden as $posicion): ?>
                        <?php if (isset($jugadores_por_posicion[$posicion])): ?>
                            <div class="lista-posicion">
                                <h3><?= $posicion ?>s</h3>
                                <div class="jugadores-posicion">
                                    <?php foreach ($jugadores_por_posicion[$posicion] as $jugador): ?>
                                        <div class="jugador-posicion">
                                            <span class="dorsal">#<?= htmlspecialchars($jugador['dorsal']) ?></span>
                                            <span class="nombre"><?= htmlspecialchars($jugador['nombre_completo']) ?></span>
                                            <span class="defensa">(Def: <?= htmlspecialchars($jugador['posicion_defensa']) ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>

    <!-- Modal Editar Jugador -->
    <div id="modal-editar" class="modal">
        <div class="modal-contenido">
            <span class="cerrar" onclick="cerrarModal()">&times;</span>
            <h3>Editar Jugador</h3>
            <form id="form-editar" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="jugador_id" id="editar-jugador-id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="editar-foto">Foto:</label>
                        <input type="file" id="editar-foto" name="foto" accept="image/*">
                        <img id="preview-editar-foto" src="#" alt="Vista previa" style="display:none;">
                    </div>
                    
                    <div class="form-group">
                        <label for="editar-nombre_completo">Nombre Completo:</label>
                        <input type="text" id="editar-nombre_completo" name="nombre_completo" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar-dorsal">Dorsal:</label>
                        <input type="number" id="editar-dorsal" name="dorsal" min="1" max="99">
                    </div>
                    
                    <div class="form-group">
                        <label for="editar-posicion_ataque">Posición Ataque:</label>
                        <select id="editar-posicion_ataque" name="posicion_ataque" required>
                            <option value="Portero">Portero</option>
                            <option value="Extremo">Extremo</option>
                            <option value="Lateral">Lateral</option>
                            <option value="Central">Central</option>
                            <option value="Pivote">Pivote</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="editar-posicion_defensa">Posición Defensa:</label>
                        <select id="editar-posicion_defensa" name="posicion_defensa" required>
                            <option value="Portero">Portero</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                        </select>
                    </div>
                </div>
                
                <div class="botones-form">
                    <button type="submit" class="btn-guardar">Guardar Cambios</button>
                    <button type="button" class="btn-cancelar" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Confirmar Eliminación -->
    <div id="modal-eliminar" class="modal">
        <div class="modal-contenido">
            <h3>Confirmar Eliminación</h3>
            <p>¿Estás seguro de que quieres eliminar al jugador "<span id="nombre-jugador-eliminar"></span>"?</p>
            <form id="form-eliminar" method="POST">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="jugador_id" id="eliminar-jugador-id">
                <div class="botones-form">
                    <button type="submit" class="btn-confirmar">Sí, eliminar</button>
                    <button type="button" class="btn-cancelar" onclick="cerrarModalEliminar()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Vista previa de fotos
        document.getElementById('foto').addEventListener('change', function(e) {
            previewImage(e.target, 'preview-foto');
        });
        
        document.getElementById('editar-foto').addEventListener('change', function(e) {
            previewImage(e.target, 'preview-editar-foto');
        });
        
        function previewImage(input, previewId) {
            const file = input.files[0];
            const preview = document.getElementById(previewId);
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Modal Editar
        function editarJugador(jugadorId) {
            const jugador = <?= json_encode($jugadores) ?>.find(j => j.id === jugadorId);
            if (jugador) {
                document.getElementById('editar-jugador-id').value = jugador.id;
                document.getElementById('editar-nombre_completo').value = jugador.nombre_completo;
                document.getElementById('editar-dorsal').value = jugador.dorsal;
                document.getElementById('editar-posicion_ataque').value = jugador.posicion_ataque;
                document.getElementById('editar-posicion_defensa').value = jugador.posicion_defensa;
                
                const preview = document.getElementById('preview-editar-foto');
                if (jugador.foto_url) {
                    preview.src = jugador.foto_url;
                    preview.style.display = 'block';
                } else {
                    preview.style.display = 'none';
                }
                
                document.getElementById('modal-editar').style.display = 'flex';
            }
        }
        
        function cerrarModal() {
            document.getElementById('modal-editar').style.display = 'none';
        }
        
        // Modal Eliminar
        function eliminarJugador(jugadorId, nombre) {
            document.getElementById('eliminar-jugador-id').value = jugadorId;
            document.getElementById('nombre-jugador-eliminar').textContent = nombre;
            document.getElementById('modal-eliminar').style.display = 'flex';
        }
        
        function cerrarModalEliminar() {
            document.getElementById('modal-eliminar').style.display = 'none';
        }
        
        // Cerrar modales al hacer clic fuera
        window.addEventListener('click', function(e) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>