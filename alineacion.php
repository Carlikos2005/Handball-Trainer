<?php
// alineacion.php
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
        'Content-Type: ' . ($method === 'POST' ? 'application/json' : 'application/json'),
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

// Formaciones disponibles
$formaciones_ataque = [
    '3-3' => [
        'nombre' => '3:3',
        'posiciones' => [
            'Lateral izquierdo' => ['x' => 20, 'y' => 75],
            'Central' => ['x' => 50, 'y' => 80],
            'Lateral derecho' => ['x' => 80, 'y' => 75],
            'Extremo izquierdo' => ['x' => 15, 'y' => 20],
            'Pivote' => ['x' => 49, 'y' => 45],
            'Extremo derecho' => ['x' => 85, 'y' => 20],
            'Portero' => ['x' => 50, 'y' => 12]
        ]
    ],
    '4-2' => [
        'nombre' => '4:2',
        'posiciones' => [
            'Lateral izquierdo' => ['x' => 25, 'y' => 75],
            'Extremo izquierdo' => ['x' => 15, 'y' => 20],
            'Extremo derecho' => ['x' => 85, 'y' => 20],
            'Lateral derecho' => ['x' => 75, 'y' => 75],
            'Pivote1' => ['x' => 35, 'y' => 45],
            'Pivote2' => ['x' => 65, 'y' => 45],
            'Portero' => ['x' => 50, 'y' => 12]
        ]
    ]
];

$formaciones_defensa = [
    '6-0' => [
        'nombre' => '6:0',
        'posiciones' => [
            'Portero' => ['x' => 50, 'y' => 12],
            '1 derecha' => ['x' => 15, 'y' => 20],
            '3 derecha' => ['x' => 42, 'y' => 45],
            '2 derecha' => ['x' => 30, 'y' => 35],
            '2 izquierda' => ['x' => 70, 'y' => 35],
            '1 izquierda' => ['x' => 85, 'y' => 20],
            '3 izquierda' => ['x' => 55, 'y' => 45]
        ]
    ],
    '5-1' => [
        'nombre' => '5:1',
        'posiciones' => [
            'Portero' => ['x' => 50, 'y' => 12],
            '2 derecha' => ['x' => 30, 'y' => 35],
            '2 izquierda' => ['x' => 70, 'y' => 35],
            '1 derecha' => ['x' => 15, 'y' => 20],
            '1 izquierda' => ['x' => 85, 'y' => 20],
            'Avanzado' => ['x' => 50, 'y' => 70],
            '3 central' => ['x' => 50, 'y' => 45]
        ]
    ],
    '4-2-defensa' => [
        'nombre' => '4:2',
        'posiciones' => [
            'Portero' => ['x' => 50, 'y' => 12],
            '1 derecha' => ['x' => 20, 'y' => 35],
            '3 derecha' => ['x' => 40, 'y' => 45],
            '3 izquierda' => ['x' => 60, 'y' => 45],
            '1 izquierda' => ['x' => 80, 'y' => 35],
            'Avanzado derecho' => ['x' => 30, 'y' => 60],
            'Avanzado izquierdo' => ['x' => 70, 'y' => 60]
        ]
    ],
    '3-3-defensa' => [
        'nombre' => '3:3',
        'posiciones' => [
            'Avanzado derecho' => ['x' => 20, 'y' => 75],
            'Avanzado central' => ['x' => 50, 'y' => 80],
            'Avanzado izquierdo' => ['x' => 80, 'y' => 75],
            'Apoyo derecho' => ['x' => 25, 'y' => 35],
            'Apoyo central' => ['x' => 49, 'y' => 45],
            'Apoyo izquierdo' => ['x' => 75, 'y' => 35],
            'Portero' => ['x' => 50, 'y' => 12]
        ]
    ],
    '3-2-1' => [
        'nombre' => '3:2:1',
        'posiciones' => [
            'Portero' => ['x' => 50, 'y' => 12],
            'Apoyo derecho' => ['x' => 25, 'y' => 35],
            'Apoyo central' => ['x' => 49, 'y' => 45],
            'Apoyo izquierdoo' => ['x' => 75, 'y' => 35],
            'Avanzado derecho' => ['x' => 35, 'y' => 55],
            'Avanzado izquierdo' => ['x' => 65, 'y' => 55],
            'Avanzado central' => ['x' => 50, 'y' => 70]
        ]
    ]
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alineación - <?= htmlspecialchars($equipo['nombre']) ?></title>
    <link rel="stylesheet" href="alineacion.css">
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
        <div class="alineacion-container">
            <!-- Selector de Tipo y Formación -->
            <section class="seccion">
                <h2>Crear Alineación</h2>
                
                <div class="selectores">
                    <div class="selector-group">
                        <label for="tipo">Tipo:</label>
                        <select id="tipo" name="tipo">
                            <option value="ataque">Ataque</option>
                            <option value="defensa">Defensa</option>
                        </select>
                    </div>
                    
                    <div class="selector-group">
                        <label for="formacion">Formación:</label>
                        <select id="formacion" name="formacion">
                            <!-- Las opciones se cargarán con JavaScript -->
                        </select>
                    </div>
                    
                    <button id="btn-cargar-formacion" class="btn-cargar">Cargar Formación</button>
                    <button id="btn-limpiar" class="btn-limpiar">Limpiar Campo</button>
                </div>
            </section>

            <!-- Área de Trabajo -->
            <section class="seccion area-trabajo">
                <div class="campo-container">
                    <!-- Lista de Jugadores -->
                    <div class="lista-jugadores">
                        <h3>Jugadores Disponibles</h3>
                        <div class="jugadores-lista" id="lista-jugadores">
                            <?php foreach ($jugadores as $jugador): ?>
                                <div class="jugador-item" 
                                     draggable="true" 
                                     data-jugador-id="<?= $jugador['id'] ?>"
                                     data-dorsal="<?= $jugador['dorsal'] ?>"
                                     data-nombre="<?= htmlspecialchars($jugador['nombre_completo']) ?>"
                                     data-posicion="<?= $jugador['posicion_ataque'] ?>"
                                     data-foto="<?= $jugador['foto_url'] ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjRjNFM0UzIi8+Cjx0ZXh0IHg9IjIwIiB5PSIyMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEwIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TaW4gRm90bzwvdGV4dD4KPC9zdmc+' ?>">
                                    <img src="<?= $jugador['foto_url'] ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjRjNFM0UzIi8+Cjx0ZXh0IHg9IjIwIiB5PSIyMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEwIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TaW4gRm90bzwvdGV4dD4KPC9zdmc+' ?>" 
                                         alt="<?= htmlspecialchars($jugador['nombre_completo']) ?>" 
                                         class="foto-jugador">
                                    <div class="jugador-info">
                                        <strong>#<?= $jugador['dorsal'] ?></strong>
                                        <span><?= htmlspecialchars($jugador['nombre_completo']) ?></span>
                                        <small><?= $jugador['posicion_ataque'] ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Campo de Balonmano -->
                    <div class="campo-balonmano">
                        <img src="campo.png" alt="Campo de Balonmano" class="campo-img">
                        <div class="posiciones-container" id="posiciones-container">
                            <!-- Las posiciones se cargarán dinámicamente con JavaScript -->
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        // Datos de formaciones
        const formacionesAtaque = <?= json_encode($formaciones_ataque) ?>;
        const formacionesDefensa = <?= json_encode($formaciones_defensa) ?>;
        const jugadores = <?= json_encode($jugadores) ?>;

        // Elementos del DOM
        const tipoSelect = document.getElementById('tipo');
        const formacionSelect = document.getElementById('formacion');
        const btnCargar = document.getElementById('btn-cargar-formacion');
        const btnLimpiar = document.getElementById('btn-limpiar');
        const posicionesContainer = document.getElementById('posiciones-container');
        const listaJugadores = document.getElementById('lista-jugadores');

        // Estado
        let alineacionActual = {};
        let posicionSeleccionada = null;

        // Cargar opciones de formación según el tipo
        function cargarFormaciones() {
            const tipo = tipoSelect.value;
            const formaciones = tipo === 'ataque' ? formacionesAtaque : formacionesDefensa;
            
            formacionSelect.innerHTML = '';
            Object.keys(formaciones).forEach(key => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = formaciones[key].nombre;
                formacionSelect.appendChild(option);
            });
        }

        // Cargar formación en el campo
        function cargarFormacionEnCampo() {
            const tipo = tipoSelect.value;
            const formacionKey = formacionSelect.value;
            const formaciones = tipo === 'ataque' ? formacionesAtaque : formacionesDefensa;
            const formacion = formaciones[formacionKey];
            
            if (!formacion) return;

            posicionesContainer.innerHTML = '';
            alineacionActual = { tipo, formacion: formacionKey, posiciones: {} };
            posicionSeleccionada = null;

            Object.keys(formacion.posiciones).forEach(posKey => {
                const posicion = formacion.posiciones[posKey];
                const posicionElement = document.createElement('div');
                posicionElement.className = 'posicion';
                posicionElement.style.left = `${posicion.x}%`;
                posicionElement.style.top = `${posicion.y}%`;
                posicionElement.dataset.posicion = posKey;
                
                posicionElement.innerHTML = `
                    <div class="circulo-posicion">
                        <img src="" alt="" class="foto-posicion" style="display: none;">
                    </div>
                    <div class="info-posicion">
                        <span class="nombre-posicion">${posKey}</span>
                        <div class="jugador-asignado"></div>
                    </div>
                `;

                // Eventos de drag and drop
                posicionElement.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    posicionElement.classList.add('drag-over');
                });

                posicionElement.addEventListener('dragleave', () => {
                    posicionElement.classList.remove('drag-over');
                });

                posicionElement.addEventListener('drop', (e) => {
                    e.preventDefault();
                    posicionElement.classList.remove('drag-over');
                    
                    const jugadorId = e.dataTransfer.getData('text/plain');
                    const jugador = jugadores.find(j => j.id == jugadorId);
                    
                    if (jugador) {
                        asignarJugadorAPosicion(posKey, jugador, posicionElement);
                    }
                });

                // Evento de click para selección
                posicionElement.addEventListener('click', (e) => {
                    e.stopPropagation();
                    
                    // Quitar selección anterior
                    document.querySelectorAll('.posicion').forEach(p => {
                        p.classList.remove('seleccionada');
                    });
                    
                    // Seleccionar esta posición
                    posicionElement.classList.add('seleccionada');
                    posicionSeleccionada = { elemento: posicionElement, key: posKey };
                    
                    // Resaltar jugadores disponibles
                    document.querySelectorAll('.jugador-item').forEach(j => {
                        j.classList.add('disponible');
                    });
                });

                posicionesContainer.appendChild(posicionElement);
            });
        }

        // Asignar jugador a posición
        function asignarJugadorAPosicion(posKey, jugador, posicionElement) {
            const circulo = posicionElement.querySelector('.circulo-posicion');
            const foto = posicionElement.querySelector('.foto-posicion');
            const jugadorAsignado = posicionElement.querySelector('.jugador-asignado');
            
            // Mostrar foto en el círculo
            foto.src = jugador.foto_url || 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjRjNFM0UzIi8+Cjx0ZXh0IHg9IjIwIiB5PSIyMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEwIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TaW4gRm90bzwvdGV4dD4KPC9zdmc+';
            foto.style.display = 'block';
            circulo.style.background = 'transparent';
            circulo.style.border = '3px solid #ffd166';
            
            jugadorAsignado.innerHTML = `
                <strong>#${jugador.dorsal}</strong>
                <span>${jugador.nombre_completo}</span>
            `;
            
            alineacionActual.posiciones[posKey] = {
                jugador_id: jugador.id,
                dorsal: jugador.dorsal,
                nombre: jugador.nombre_completo,
                posicion: jugador.posicion_ataque,
                foto_url: jugador.foto_url
            };

            // Quitar selección
            posicionElement.classList.remove('seleccionada');
            posicionSeleccionada = null;
            document.querySelectorAll('.jugador-item').forEach(j => {
                j.classList.remove('disponible');
            });
        }

        // Limpiar campo
        function limpiarCampo() {
            posicionesContainer.innerHTML = '';
            alineacionActual = {};
            posicionSeleccionada = null;
            document.querySelectorAll('.jugador-item').forEach(j => {
                j.classList.remove('disponible');
            });
        }

        // Asignar jugador por click
        function asignarJugadorClick(jugador) {
            if (posicionSeleccionada) {
                asignarJugadorAPosicion(
                    posicionSeleccionada.key, 
                    jugador, 
                    posicionSeleccionada.elemento
                );
            }
        }

        // Event Listeners
        tipoSelect.addEventListener('change', cargarFormaciones);
        btnCargar.addEventListener('click', cargarFormacionEnCampo);
        btnLimpiar.addEventListener('click', limpiarCampo);

        // Drag and Drop para jugadores
        document.querySelectorAll('.jugador-item').forEach(jugador => {
            jugador.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', jugador.dataset.jugadorId);
                jugador.classList.add('dragging');
            });

            jugador.addEventListener('dragend', () => {
                jugador.classList.remove('dragging');
            });

            // Click para asignar a posición seleccionada
            jugador.addEventListener('click', () => {
                if (posicionSeleccionada) {
                    const jugadorData = {
                        id: parseInt(jugador.dataset.jugadorId),
                        dorsal: parseInt(jugador.dataset.dorsal),
                        nombre_completo: jugador.dataset.nombre,
                        posicion_ataque: jugador.dataset.posicion,
                        foto_url: jugador.dataset.foto
                    };
                    asignarJugadorClick(jugadorData);
                }
            });
        });

        // Click fuera para deseleccionar
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.posicion') && !e.target.closest('.jugador-item')) {
                document.querySelectorAll('.posicion').forEach(p => {
                    p.classList.remove('seleccionada');
                });
                document.querySelectorAll('.jugador-item').forEach(j => {
                    j.classList.remove('disponible');
                });
                posicionSeleccionada = null;
            }
        });

        // Inicializar
        cargarFormaciones();
    </script>
</body>
</html>