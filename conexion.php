<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Sustituye estos valores por los datos de conexión de tu servidor.
// Este archivo contiene credenciales: NO lo subas a Git.
$mysqli = new mysqli('localhost', 'present-quiet-wez', 'W+A+GdI4s5te3Eo(36', 'present_quiet_wez_agendabd');
$mysqli->set_charset('utf8mb4');
