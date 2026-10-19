<?php
declare(strict_types=1);

function h(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function postTexto(string $campo): string
{
    $valor = $_POST[$campo] ?? '';
    return is_string($valor) ? trim($valor) : '';
}

$titulo = '';
$fecha = '';
$hora = '';
$categoria = '';
$descripcion = '';
$errores = [];
$errorGeneral = '';
$categoriasOK = ['trabajo', 'personal', 'estudio', 'ocio'];
$etiquetasCategoria = [
    'trabajo' => 'Trabajo',
    'personal' => 'Personal',
    'estudio' => 'Estudio',
    'ocio' => 'Ocio',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (!in_array($categoria, $categoriasOK, true)) {
        $errores['categoria'] = 'Elige una categoría válida.';
    }

    if (preg_match_all('/./us', $descripcion, $coincidencias) !== false && count($coincidencias[0]) > 500) {
        $errores['descripcion'] = 'La descripción no puede superar 500 caracteres.';
    }

    if (!$errores) {
        try {
            require __DIR__ . '/conexion.php';

            $horaGuardar = $hora === '' ? null : $hora . ':00';
            $sql = 'INSERT INTO eventos (titulo, fecha, hora, categoria, descripcion) VALUES (?, ?, ?, ?, ?)';
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param('sssss', $titulo, $fecha, $horaGuardar, $categoria, $descripcion);
            $stmt->execute();
            $stmt->close();
            $mysqli->close();

            header('Location: index.php?ok=1');
            exit;
        } catch (Throwable $e) {
            if (isset($mysqli) && $mysqli instanceof mysqli) {
                $mysqli->close();
            }
            $errorGeneral = 'No se pudo guardar el evento. Verifica la conexión con la base de datos e inténtalo de nuevo.';
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrar evento | AgendaWeb</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="index.php">AgendaWeb</a>
    <nav aria-label="Navegación principal"><a href="index.php">Ver eventos</a></nav>
</header>
<main class="container">
    <section class="card">
        <p class="eyebrow">Agenda personal</p>
        <h1>Registrar un evento</h1>
        <p class="intro">Completa los datos para agregar un evento a tu agenda.</p>

        <?php if ($errorGeneral !== ''): ?>
            <div class="alert alert-error" role="alert"><?= h($errorGeneral) ?></div>
        <?php endif; ?>

        <?php if ($errores): ?>
            <div class="alert alert-error" role="alert">
                <p><strong>Revisa los siguientes campos:</strong></p>
                <ul>
                    <?php foreach ($errores as $mensaje): ?>
                        <li><?= h($mensaje) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="" novalidate>
            <div class="field">
                <label for="titulo">Título <span aria-hidden="true">*</span></label>
                <input id="titulo" name="titulo" type="text" maxlength="120" required value="<?= h($titulo) ?>" aria-describedby="error-titulo">
                <?php if (isset($errores['titulo'])): ?><small class="field-error" id="error-titulo"><?= h($errores['titulo']) ?></small><?php endif; ?>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="fecha">Fecha <span aria-hidden="true">*</span></label>
                    <input id="fecha" name="fecha" type="date" required value="<?= h($fecha) ?>" aria-describedby="error-fecha">
                    <?php if (isset($errores['fecha'])): ?><small class="field-error" id="error-fecha"><?= h($errores['fecha']) ?></small><?php endif; ?>
                </div>
                <div class="field">
                    <label for="hora">Hora <span class="optional">(opcional)</span></label>
                    <input id="hora" name="hora" type="time" value="<?= h($hora) ?>" aria-describedby="error-hora">
                    <?php if (isset($errores['hora'])): ?><small class="field-error" id="error-hora"><?= h($errores['hora']) ?></small><?php endif; ?>
                </div>
            </div>

            <div class="field">
                <label for="categoria">Categoría <span aria-hidden="true">*</span></label>
                <select id="categoria" name="categoria" required aria-describedby="error-categoria">
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($etiquetasCategoria as $valor => $etiqueta): ?>
                        <option value="<?= h($valor) ?>" <?= $categoria === $valor ? 'selected' : '' ?>><?= h($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errores['categoria'])): ?><small class="field-error" id="error-categoria"><?= h($errores['categoria']) ?></small><?php endif; ?>
            </div>

            <div class="field">
                <label for="descripcion">Descripción <span class="optional">(opcional, máximo 500 caracteres)</span></label>
                <textarea id="descripcion" name="descripcion" rows="5" maxlength="500" aria-describedby="error-descripcion"><?= h($descripcion) ?></textarea>
                <?php if (isset($errores['descripcion'])): ?><small class="field-error" id="error-descripcion"><?= h($errores['descripcion']) ?></small><?php endif; ?>
            </div>

            <button class="button" type="submit">Guardar evento</button>
            <p class="help-text">Los campos marcados con * son obligatorios.</p>
        </form>
    </section>
</main>
<footer class="site-footer">AgendaWeb · Práctica P3</footer>
</body>
</html>
