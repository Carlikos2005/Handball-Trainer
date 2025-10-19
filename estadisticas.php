<?php
// estadisticas.php - SOLO PARA VER ESTADÍSTICAS
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

// Obtener partidos del equipo
$partidosResponse = supabaseRequest('GET', '/rest/v1/partidos?equipo_id=eq.' . $equipo_id . '&order=fecha_hora.desc');
$partidos = ($partidosResponse['status'] === 200 && !empty($partidosResponse['data'])) ? $partidosResponse['data'] : [];

// Obtener estadísticas
$estadisticasResponse = supabaseRequest('GET', '/rest/v1/estadisticas_partido?select=*');
$estadisticas = ($estadisticasResponse['status'] === 200 && !empty($estadisticasResponse['data'])) ? $estadisticasResponse['data'] : [];

// Calcular estadísticas de equipo
$total_goles_favor = 0;
$total_goles_contra = 0;
$partidos_jugados = 0;

foreach ($partidos as $p) {
    if ($p['goles_favor'] > 0 || $p['goles_contra'] > 0) {
        $total_goles_favor += $p['goles_favor'];
        $total_goles_contra += $p['goles_contra'];
        $partidos_jugados++;
    }
}

// Calcular estadísticas individuales
$estadisticas_individuales = [];
foreach ($jugadores as $jugador) {
    $estadisticas_jugador = array_filter($estadisticas, function($e) use ($jugador) {
        return $e['jugador_id'] === $jugador['id'];
    });
    
    $total_tiros_acertados = 0;
    $total_tiros_fallados = 0;
    $partidos_con_estadisticas = 0;
    
    foreach ($estadisticas_jugador as $est) {
        $total_tiros_acertados += $est['tiros_acertados'];
        $total_tiros_fallados += $est['tiros_fallados'];
        $partidos_con_estadisticas++;
    }
    
    $total_tiros = $total_tiros_acertados + $total_tiros_fallados;
    $eficiencia = $total_tiros > 0 ? round(($total_tiros_acertados / $total_tiros) * 100, 1) : 0;
    
    $estadisticas_individuales[$jugador['id']] = [
        'jugador' => $jugador,
        'tiros_acertados' => $total_tiros_acertados,
        'tiros_fallados' => $total_tiros_fallados,
        'eficiencia' => $eficiencia,
        'partidos' => $partidos_con_estadisticas
    ];
}

// Ordenar por eficiencia (descendente)
uasort($estadisticas_individuales, function($a, $b) {
    return $b['eficiencia'] - $a['eficiencia'];
});
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - <?= htmlspecialchars($equipo['nombre']) ?></title>
    <link rel="stylesheet" href="estadisticas.css">
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
        <div class="estadisticas-container">
            <!-- Estadísticas de Equipo -->
            <section class="seccion">
                <h2>Estadísticas de Equipo</h2>
                
                <div class="resumen-equipo">
                    <div class="estadistica-card">
                        <h3>Partidos Jugados</h3>
                        <div class="valor"><?= $partidos_jugados ?></div>
                    </div>
                    <div class="estadistica-card">
                        <h3>Goles a Favor</h3>
                        <div class="valor"><?= $total_goles_favor ?></div>
                    </div>
                    <div class="estadistica-card">
                        <h3>Goles en Contra</h3>
                        <div class="valor"><?= $total_goles_contra ?></div>
                    </div>
                    <div class="estadistica-card">
                        <h3>Diferencia</h3>
                        <div class="valor <?= ($total_goles_favor - $total_goles_contra) >= 0 ? 'positivo' : 'negativo' ?>">
                            <?= $total_goles_favor - $total_goles_contra ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Estadísticas Individuales -->
            <section class="seccion">
                <h2>Estadísticas Individuales</h2>

                <!-- Ranking por Eficiencia -->
                <div class="ranking">
                    <h3>Ranking por Eficiencia</h3>
                    <?php if (empty($estadisticas_individuales)): ?>
                        <p class="sin-datos">No hay estadísticas registradas.</p>
                    <?php else: ?>
                        <?php $posicion = 1; ?>
                        <?php foreach ($estadisticas_individuales as $est): ?>
                            <?php if ($est['tiros_acertados'] > 0 || $est['tiros_fallados'] > 0): ?>
                                <div class="ranking-item">
                                    <span class="posicion">#<?= $posicion ?></span>
                                    <img src="<?= $est['jugador']['foto_url'] ?: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjQwIiBoZWlnaHQ9IjQwIiBmaWxsPSIjRjNFM0UzIi8+Cjx0ZXh0IHg9IjIwIiB5PSIyMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEwIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TaW4gRm90bzwvdGV4dD4KPC9zdmc+' ?>" 
                                         alt="<?= htmlspecialchars($est['jugador']['nombre_completo']) ?>" 
                                         class="foto-ranking">
                                    <div class="jugador-info">
                                        <span class="nombre">#<?= $est['jugador']['dorsal'] ?> - <?= htmlspecialchars($est['jugador']['nombre_completo']) ?></span>
                                        <span class="posicion"><?= $est['jugador']['posicion_ataque'] ?></span>
                                    </div>
                                    <div class="estadisticas">
                                        <?php if ($est['jugador']['posicion_ataque'] === 'Portero'): ?>
                                            <span class="dato"><?= $est['tiros_acertados'] ?> paradas</span>
                                            <span class="dato"><?= $est['tiros_fallados'] ?> goles recibidos</span>
                                            <span class="eficiencia <?= $est['eficiencia'] >= 70 ? 'alta' : ($est['eficiencia'] >= 50 ? 'media' : 'baja') ?>">
                                                <?= $est['eficiencia'] ?>% eficiencia
                                            </span>
                                        <?php else: ?>
                                            <span class="dato"><?= $est['tiros_acertados'] ?> tiros acertados</span>
                                            <span class="dato"><?= $est['tiros_fallados'] ?> tiros fallados</span>
                                            <span class="eficiencia <?= $est['eficiencia'] >= 70 ? 'alta' : ($est['eficiencia'] >= 50 ? 'media' : 'baja') ?>">
                                                <?= $est['eficiencia'] ?>% eficiencia
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php $posicion++; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</body>
</html>