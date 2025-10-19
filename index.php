<?php
// Configuración
define('SUPABASE_URL', 'https://tfeqqlechlakyqorlcga.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRmZXFxbGVjaGxha3lxb3JsY2dhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTk5NTU2MDAsImV4cCI6MjA3NTUzMTYwMH0.K9iVGTq3miOQ1VwDYRTRqUEwfnlq3UMWDv0K8AdTmxI');

session_start();
$error = '';

// Función para peticiones a Supabase REST API
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

// Función para subir archivos a Supabase Storage
function uploadToSupabaseStorage($filePath, $fileName) {
    $fileContent = file_get_contents($filePath);
    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    
    $ch = curl_init(SUPABASE_URL . '/storage/v1/object/escudos/' . $fileName);
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
    
    return [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'code' => $httpCode,
        'response' => $response
    ];
}

// Función alternativa para validar tipo de imagen
function validarImagen($archivo_tmp) {
    $bytes = file_get_contents($archivo_tmp, false, null, 0, 4);
    if ($bytes === false) return false;
    
    $hex = strtoupper(bin2hex($bytes));
    
    $signatures = [
        'FFD8FF' => 'image/jpeg',
        '89504E' => 'image/png', 
        '474946' => 'image/gif',
    ];
    
    foreach ($signatures as $signature => $mime) {
        if (strpos($hex, $signature) === 0) {
            return $mime;
        }
    }
    
    return false;
}

// Crear equipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre_equipo'])) {
    $nombre = trim($_POST['nombre_equipo']);
    $archivo_escudo = $_FILES['escudo'] ?? null;
    
    if (empty($nombre)) {
        $error = 'El nombre del equipo es obligatorio.';
    } elseif (!$archivo_escudo || $archivo_escudo['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error al subir la imagen. Código: ' . ($archivo_escudo['error'] ?? 'desconocido');
    } else {
        // Validar tipo de archivo
        $tipo_valido = validarImagen($archivo_escudo['tmp_name']);
        
        if (!$tipo_valido) {
            $error = 'Solo se permiten imágenes JPEG, PNG o GIF.';
        } else {
            // Primero subir la imagen
            $extension = pathinfo($archivo_escudo['name'], PATHINFO_EXTENSION);
            $filename = uniqid('escudo_') . '.' . strtolower($extension);
            
            $uploadResult = uploadToSupabaseStorage($archivo_escudo['tmp_name'], $filename);
            
            if ($uploadResult['success']) {
                $escudo_url = SUPABASE_URL . '/storage/v1/object/public/escudos/' . $filename;
                
                // Crear el equipo con la URL de la imagen
                $result = supabaseRequest('POST', '/rest/v1/equipos', [
                    'nombre' => $nombre,
                    'escudo_url' => $escudo_url
                ]);
                
                if ($result['status'] >= 200 && $result['status'] < 300) {
                    header('Location: index.php?success=1');
                    exit;
                } else {
                    $error = 'Error al crear el equipo. Código: ' . $result['status'];
                    if ($result['response']) {
                        $errorData = json_decode($result['response'], true);
                        $error .= ' - ' . ($errorData['message'] ?? $result['response']);
                    }
                    
                    // Si falla crear el equipo, borrar la imagen subida
                    $ch = curl_init(SUPABASE_URL . '/storage/v1/object/escudos/' . $filename);
                    curl_setopt_array($ch, [
                        CURLOPT_CUSTOMREQUEST => 'DELETE',
                        CURLOPT_HTTPHEADER => [
                            'apikey: ' . SUPABASE_KEY,
                            'Authorization: Bearer ' . SUPABASE_KEY
                        ],
                        CURLOPT_RETURNTRANSFER => true
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            } else {
                $error = 'Error al subir la imagen. Código: ' . $uploadResult['code'];
                if ($uploadResult['response']) {
                    $errorData = json_decode($uploadResult['response'], true);
                    $error .= ' - ' . ($errorData['message'] ?? $uploadResult['response']);
                }
            }
        }
    }
}

// Eliminar equipo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar_id'])) {
    $id = intval($_POST['borrar_id']);
    
    // Obtener información del equipo
    $equipo = supabaseRequest('GET', '/rest/v1/equipos?id=eq.' . $id);
    
    if ($equipo['status'] === 200 && !empty($equipo['data'])) {
        $escudo_url = $equipo['data'][0]['escudo_url'];
        
        // Borrar imagen del storage
        if (strpos($escudo_url, 'escudos/') !== false) {
            $filename = basename($escudo_url);
            
            $ch = curl_init(SUPABASE_URL . '/storage/v1/object/escudos/' . $filename);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . SUPABASE_KEY,
                    'Authorization: Bearer ' . SUPABASE_KEY
                ],
                CURLOPT_RETURNTRANSFER => true
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
        
        // Borrar de la base de datos
        supabaseRequest('DELETE', '/rest/v1/equipos?id=eq.' . $id);
    }
    
    header('Location: index.php');
    exit;
}

// Cargar equipos
$equiposResponse = supabaseRequest('GET', '/rest/v1/equipos?select=*&order=id.desc');
$equipos = ($equiposResponse['status'] === 200 && !empty($equiposResponse['data'])) ? $equiposResponse['data'] : [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Handball Trainer</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
    <header>
        <img src="logo.png" alt="Logo Handball Trainer" class="logo">
        <h1>Handball Trainer</h1>
    </header>

    <main>
        <h2>Selecciona tu equipo</h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">
                Equipo creado exitosamente.
            </div>
        <?php endif; ?>

        <div class="equipos">
            <?php if (!empty($equipos)): ?>
                <?php foreach ($equipos as $equipo): ?>
                    <div class="equipo">
                        <a href="equipo.php?id=<?= htmlspecialchars($equipo['id']) ?>" class="equipo-link">
                            <img src="<?= htmlspecialchars($equipo['escudo_url']) ?>" 
                                 alt="Escudo de <?= htmlspecialchars($equipo['nombre']) ?>"
                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjNFM0UzIi8+Cjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TaW4gRXNjdWRvPC90ZXh0Pgo8L3N2Zz4K'">
                            <span><?= htmlspecialchars($equipo['nombre']) ?></span>
                        </a>
                        <button class="btn-borrar" 
                                onclick="confirmarBorrado(<?= htmlspecialchars($equipo['id']) ?>, '<?= htmlspecialchars($equipo['nombre']) ?>')">
                            🗑️
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-equipos">No hay equipos creados aún.</p>
            <?php endif; ?>
        </div>

        <button id="btn-crear" onclick="mostrarFormulario()">+ Crear nuevo equipo</button>

        <!-- Modal crear equipo -->
        <div id="modal-crear" class="modal">
            <div class="modal-contenido">
                <span class="cerrar" onclick="ocultarFormulario()">&times;</span>
                <h3>Crear nuevo equipo</h3>

                <?php if ($error): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="form-crear">
                    <label for="nombre_equipo">Nombre del equipo:</label>
                    <input type="text" id="nombre_equipo" name="nombre_equipo" required 
                           value="<?= htmlspecialchars($_POST['nombre_equipo'] ?? '') ?>"
                           maxlength="50">

                    <label for="escudo" class="file-label">
                        <span>Seleccionar imagen del escudo</span>
                    </label>
                    <input type="file" id="escudo" name="escudo" accept=".jpg,.jpeg,.png,.gif" required>

                    <img id="preview" src="#" alt="Vista previa del escudo">

                    <div class="botones-form">
                        <button type="submit">Guardar equipo</button>
                        <button type="button" onclick="ocultarFormulario()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal confirmar borrado -->
        <div id="modal-borrar" class="modal">
            <div class="modal-contenido">
                <h3>¿Estás seguro de que quieres eliminar este equipo?</h3>
                <p>Esta acción no se puede deshacer.</p>
                <div class="botones-form">
                    <form id="form-borrar" method="POST">
                        <input type="hidden" name="borrar_id" id="borrar_id">
                        <button type="submit" class="btn-confirmar">Sí, eliminar</button>
                    </form>
                    <button type="button" class="btn-cancelar" onclick="ocultarConfirmacion()">Cancelar</button>
                </div>
            </div>
        </div>
    </main>

    <script>
        function mostrarFormulario() {
            document.getElementById('modal-crear').style.display = 'flex';
        }

        function ocultarFormulario() {
            document.getElementById('modal-crear').style.display = 'none';
            document.getElementById('form-crear').reset();
            document.getElementById('preview').style.display = 'none';
        }

        function confirmarBorrado(id, nombre) {
            document.getElementById('borrar_id').value = id;
            document.getElementById('modal-borrar').style.display = 'flex';
        }

        function ocultarConfirmacion() {
            document.getElementById('modal-borrar').style.display = 'none';
        }

        // Vista previa de imagen
        document.getElementById('escudo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('preview');
            
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    alert('La imagen debe ser menor a 2MB');
                    this.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });

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