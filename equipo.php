<?php
// equipo.php
define('SUPABASE_URL', 'https://tfeqqlechlakyqorlcga.supabase.co');
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRmZXFxbGVjaGxha3lxb3JsY2dhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTk5NTU2MDAsImV4cCI6MjA3NTUzMTYwMH0.K9iVGTq3miOQ1VwDYRTRqUEwfnlq3UMWDv0K8AdTmxI');

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

// Obtener información del equipo
$equipoResponse = supabaseRequest('GET', '/rest/v1/equipos?id=eq.' . $equipo_id);
$equipo = ($equipoResponse['status'] === 200 && !empty($equipoResponse['data'])) ? $equipoResponse['data'][0] : null;

if (!$equipo) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($equipo['nombre']) ?> - Handball Trainer</title>
    <link rel="stylesheet" href="equipo.css">
</head>
<body>
    <header>
        <a href="index.php" class="btn-volver">← Volver</a>
        <div class="equipo-header-center">
            <img src="<?= htmlspecialchars($equipo['escudo_url']) ?>" 
                 alt="Escudo de <?= htmlspecialchars($equipo['nombre']) ?>" 
                 class="escudo-equipo">
            <h1><?= htmlspecialchars($equipo['nombre']) ?></h1>
        </div>
        <div class="header-spacer"></div> <!-- Para mantener el balance -->
    </header>

    <main>
        <nav class="menu-equipo">
            <a href="plantilla.php?id=<?= $equipo_id ?>" class="menu-item">
                <span class="menu-icon">👥</span>
                <span class="menu-text">Plantilla</span>
            </a>
            
            <a href="alineacion.php?id=<?= $equipo_id ?>" class="menu-item">
                <span class="menu-icon">🤾🏼</span>
                <span class="menu-text">Alineación</span>
            </a>
            
            <a href="entrenamientos.php?id=<?= $equipo_id ?>" class="menu-item">
                <span class="menu-icon">🏋️</span>
                <span class="menu-text">Entrenamientos</span>
            </a>
            
            <a href="estadisticas.php?id=<?= $equipo_id ?>" class="menu-item">
                <span class="menu-icon">📊</span>
                <span class="menu-text">Estadísticas</span>
            </a>
            
            <a href="resultados.php?id=<?= $equipo_id ?>" class="menu-item">
                <span class="menu-icon">🏆</span>
                <span class="menu-text">Resultados</span>
            </a>
            
            <a href="jugadas.php?id=<?= $equipo_id ?>" class="menu-item">
                <span class="menu-icon">🎯</span>
                <span class="menu-text">Jugadas</span>
            </a>
        </nav>
    </main>

    <script>
        // Efecto hover suave para los items del menú
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateX(10px)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateX(0)';
            });
        });
    </script>
</body>
</html>