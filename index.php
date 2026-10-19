<?php
declare(strict_types=1);

session_start();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatearFecha(string $fecha): string
{
    $marcaTiempo = strtotime($fecha);
    return $marcaTiempo === false ? $fecha : date('d/m/Y', $marcaTiempo);
}

function nombreCategoria(string $clave): string
{
    $nombres = [
        'trabajo' => 'Trabajo',
        'personal' => 'Personal',
        'estudio' => 'Estudio',
        'ocio' => 'Ocio',
        'otro' => 'Otro',
    ];
    return $nombres[$clave] ?? $clave;
}

function mostrarEvento(array $ev, string $csrfToken): string
{
    $html = '<article class="card event-card">';
    $html .= '<div class="event-meta">';
    $html .= '<span class="badge">' . e(nombreCategoria((string)($ev['categoria'] ?? ''))) . '</span>';
    $fecha = (string)($ev['fecha'] ?? '');
    $html .= '<time datetime="' . e($fecha) . '">' . e(formatearFecha($fecha)) . '</time>';

    $hora = $ev['hora'] ?? null;
    if ($hora !== null && $hora !== '') {
        $html .= '<span>' . e(substr((string)$hora, 0, 5)) . ' h</span>';
    }
    $html .= '</div>';
    $html .= '<h2>' . e((string)($ev['titulo'] ?? '')) . '</h2>';

    $descripcion = $ev['descripcion'] ?? null;
    if ($descripcion !== null && $descripcion !== '') {
        $html .= '<p class="event-description">' . nl2br(e((string)$descripcion)) . '</p>';
    }

    $id = (int)($ev['id'] ?? 0);
    $html .= '<div class="card__actions">';
    $html .= '<a href="editar.php?id=' . $id . '" class="btn-secondary btn-sm">Editar</a>';
    $html .= '<form method="post" action="borrar.php" class="form-inline" onsubmit="return confirm(\'¿Seguro que quieres borrar este evento?\');">';
    $html .= '<input type="hidden" name="id" value="' . $id . '">';
    $html .= '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">';
    $html .= '<button type="submit" class="btn-danger btn-sm">Borrar</button>';
    $html .= '</form></div>';

    return $html . '</article>';
}

$eventos = [];
$errorGeneral = '';

try {
    require __DIR__ . '/conexion.php';
    $resultado = $mysqli->query(
        'SELECT id, titulo, fecha, hora, categoria, descripcion
           FROM eventos
          ORDER BY fecha ASC, hora ASC, id DESC'
    );
    $eventos = $resultado->fetch_all(MYSQLI_ASSOC);
    $resultado->free();
    $mysqli->close();
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
    $errorGeneral = 'No se pudieron cargar los eventos. Revisa la conexión y que exista la tabla eventos.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AgendaWeb | Mis eventos</title>
    <link rel="stylesheet" href="/css/estilos.css?v=4">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php">AgendaWeb</a>
    <nav aria-label="Navegación principal"><a href="registrar.php">Registrar evento</a></nav>
</header>
<main class="container">
    <div class="page-heading">
        <div>
            <p class="eyebrow">Tu espacio organizado</p>
            <h1>Mis eventos</h1>
            <p class="intro"><?= count($eventos) ?> eventos registrados</p>
        </div>
        <a class="button" href="registrar.php">+ Nuevo evento</a>
    </div>

    <?php if (isset($_GET['ok']) && $_GET['ok'] === '1'): ?>
        <div class="alert alert-success" role="status">Evento guardado correctamente.</div>
    <?php elseif (isset($_GET['updated']) && $_GET['updated'] === '1'): ?>
        <div class="alert alert-success" role="status">Evento actualizado correctamente.</div>
    <?php elseif (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
        <div class="alert alert-success" role="status">Evento eliminado correctamente.</div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'notfound'): ?>
        <div class="alert alert-error" role="alert">No se encontró ese evento.</div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'csrf'): ?>
        <div class="alert alert-error" role="alert">La solicitud expiró o no es válida. Vuelve a intentarlo.</div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'db'): ?>
        <div class="alert alert-error" role="alert">No se pudo completar la operación en la base de datos.</div>
    <?php endif; ?>

    <?php if ($errorGeneral !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($errorGeneral) ?></div>
    <?php elseif (empty($eventos)): ?>
        <section class="card empty-state">
            <h2>Aún no hay eventos</h2>
            <p>Agrega tu primer evento para empezar a organizarte.</p>
            <a class="button" href="registrar.php">Registrar evento</a>
        </section>
    <?php else: ?>
        <section class="event-list" aria-label="Lista de eventos">
            <?php foreach ($eventos as $ev): ?>
                <?= mostrarEvento($ev, $csrfToken) ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<footer class="site-footer">AgendaWeb · Práctica P4</footer>
</body>
</html>
