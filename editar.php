<?php
declare(strict_types=1);

session_start();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function postTexto(string $campo): string
{
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    header('Location: index.php?error=notfound');
    exit;
}

$categorias = [
    'trabajo' => 'Trabajo',
    'personal' => 'Personal',
    'estudio' => 'Estudio',
    'otro' => 'Otro',
];

$evento = null;
$errores = [];
$errorGeneral = '';
$titulo = '';
$fecha = '';
$hora = '';
$categoria = '';
$descripcion = '';

try {
    require __DIR__ . '/conexion.php';
    $stmt = $mysqli->prepare('SELECT titulo, fecha, hora, categoria, descripcion FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $evento = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($evento === null) {
        http_response_code(404);
    } else {
        $titulo = (string)$evento['titulo'];
        $fecha = (string)$evento['fecha'];
        $hora = $evento['hora'] === null ? '' : substr((string)$evento['hora'], 0, 5);
        $categoria = (string)$evento['categoria'];
        $descripcion = (string)($evento['descripcion'] ?? '');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tokenRecibido = $_POST['csrf_token'] ?? '';
            if (!is_string($tokenRecibido) || !hash_equals($csrfToken, $tokenRecibido)) {
                $errorGeneral = 'La solicitud expiró o no es válida. Regresa a la lista e inténtalo de nuevo.';
            } else {
                $titulo = postTexto('titulo');
                $fecha = postTexto('fecha');
                $hora = postTexto('hora');
                $categoria = postTexto('categoria');
                $descripcion = postTexto('descripcion');

                if ($titulo === '') {
                    $errores['titulo'] = 'El título es obligatorio.';
                } elseif (mb_strlen($titulo, 'UTF-8') > 120) {
                    $errores['titulo'] = 'El título no puede superar 120 caracteres.';
                }

                if ($fecha === '') {
                    $errores['fecha'] = 'La fecha es obligatoria.';
                } else {
                    $fechaObjeto = DateTime::createFromFormat('!Y-m-d', $fecha);
                    $erroresFecha = DateTime::getLastErrors();
                    $fechaValida = $fechaObjeto !== false
                        && $fechaObjeto->format('Y-m-d') === $fecha
                        && ($erroresFecha === false || ($erroresFecha['warning_count'] === 0 && $erroresFecha['error_count'] === 0));
                    if (!$fechaValida) {
                        $errores['fecha'] = 'Escribe una fecha válida.';
                    }
                }

                if ($hora !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora)) {
                    $errores['hora'] = 'Escribe una hora válida en formato HH:MM.';
                }

                if (!array_key_exists($categoria, $categorias)) {
                    $errores['categoria'] = 'Elige una categoría válida.';
                }

                $cantidadDescripcion = preg_match_all('/./us', $descripcion, $coincidencias);
                if ($cantidadDescripcion === false) {
                    $errores['descripcion'] = 'La descripción contiene caracteres no válidos.';
                } elseif ($cantidadDescripcion > 500) {
                    $errores['descripcion'] = 'La descripción no puede superar 500 caracteres.';
                }

                if (!$errores) {
                    $horaGuardar = $hora === '' ? null : $hora . ':00';
                    $stmtUpdate = $mysqli->prepare(
                        'UPDATE eventos SET titulo = ?, fecha = ?, hora = ?, categoria = ?, descripcion = ? WHERE id = ?'
                    );
                    $stmtUpdate->bind_param('sssssi', $titulo, $fecha, $horaGuardar, $categoria, $descripcion, $id);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                    $mysqli->close();
                    header('Location: index.php?updated=1');
                    exit;
                }
            }
        }
    }

    $mysqli->close();
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
    $errorGeneral = 'No se pudo cargar o actualizar el evento. Revisa la conexión con la base de datos.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar evento | AgendaWeb</title>
    <link rel="stylesheet" href="/css/estilos.css?v=4">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php">AgendaWeb</a>
    <nav aria-label="Navegación principal"><a href="index.php">Ver eventos</a></nav>
</header>
<main class="container">
    <section class="card">
        <p class="eyebrow">Agenda personal</p>
        <h1>Editar evento</h1>

        <?php if ($errorGeneral !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($errorGeneral) ?></div>
        <?php elseif ($evento === null): ?>
            <div class="alert alert-error" role="alert">No se encontró el evento solicitado.</div>
            <a class="button" href="index.php">Volver a mis eventos</a>
        <?php else: ?>
            <?php if ($errores): ?>
                <div class="alert alert-error" role="alert">
                    <p><strong>Revisa los siguientes campos:</strong></p>
                    <ul><?php foreach ($errores as $mensaje): ?><li><?= e($mensaje) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <form method="post" action="editar.php?id=<?= (int)$id ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                <div class="field">
                    <label for="titulo">Título *</label>
                    <input id="titulo" name="titulo" type="text" maxlength="120" required value="<?= e($titulo) ?>">
                    <?php if (isset($errores['titulo'])): ?><small class="field-error"><?= e($errores['titulo']) ?></small><?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="fecha">Fecha *</label>
                        <input id="fecha" name="fecha" type="date" required value="<?= e($fecha) ?>">
                        <?php if (isset($errores['fecha'])): ?><small class="field-error"><?= e($errores['fecha']) ?></small><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="hora">Hora (opcional)</label>
                        <input id="hora" name="hora" type="time" value="<?= e($hora) ?>">
                        <?php if (isset($errores['hora'])): ?><small class="field-error"><?= e($errores['hora']) ?></small><?php endif; ?>
                    </div>
                </div>

                <div class="field">
                    <label for="categoria">Categoría *</label>
                    <select id="categoria" name="categoria" required>
                        <option value="">Selecciona una categoría</option>
                        <?php foreach ($categorias as $valor => $etiqueta): ?>
                            <option value="<?= e($valor) ?>" <?= $categoria === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['categoria'])): ?><small class="field-error"><?= e($errores['categoria']) ?></small><?php endif; ?>
                </div>

                <div class="field">
                    <label for="descripcion">Descripción (opcional, máximo 500 caracteres)</label>
                    <textarea id="descripcion" name="descripcion" rows="5" maxlength="500"><?= e($descripcion) ?></textarea>
                    <?php if (isset($errores['descripcion'])): ?><small class="field-error"><?= e($errores['descripcion']) ?></small><?php endif; ?>
                </div>

                <button class="button" type="submit">Guardar cambios</button>
                <a href="index.php">Cancelar</a>
            </form>
        <?php endif; ?>
    </section>
</main>
<footer class="site-footer">AgendaWeb · Práctica P5</footer>
</body>
</html>
