<?php
declare(strict_types=1);

session_start();

// No se permite borrar mediante GET; la acción debe venir del formulario POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($id === false || $id === null) {
    header('Location: index.php?error=notfound');
    exit;
}

$tokenRecibido = $_POST['csrf_token'] ?? '';
$tokenSesion = $_SESSION['csrf_token'] ?? '';
if (!is_string($tokenRecibido) || !is_string($tokenSesion) || $tokenSesion === '' || !hash_equals($tokenSesion, $tokenRecibido)) {
    header('Location: index.php?error=csrf');
    exit;
}

try {
    require __DIR__ . '/conexion.php';

    $stmt = $mysqli->prepare('DELETE FROM eventos WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $borrado = $stmt->affected_rows === 1;
    $stmt->close();
    $mysqli->close();

    if ($borrado) {
        header('Location: index.php?deleted=1');
    } else {
        header('Location: index.php?error=notfound');
    }
    exit;
} catch (Throwable $e) {
    if (isset($mysqli) && $mysqli instanceof mysqli) {
        $mysqli->close();
    }
    header('Location: index.php?error=db');
    exit;
}
