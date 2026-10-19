<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Copia este archivo como conexion.php y completa los datos de tu hosting.
$mysqli = new mysqli('localhost', 'USUARIO_DE_BASE_DE_DATOS', 'CONTRASEÑA_DE_BASE_DE_DATOS', 'NOMBRE_DE_BASE_DE_DATOS');
$mysqli->set_charset('utf8mb4');
