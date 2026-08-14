<?php
require_once(__DIR__ . '/../includes/db.php');
require_once(__DIR__ . '/../includes/tallyman_auto_cierre.php');

try {
    $pdo = new PDO(
        'mysql:host=' . OPER_DB_HOST . ';port=3306;dbname=' . OPER_DB_NAME . ';charset=utf8mb4',
        OPER_DB_USER,
        OPER_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $cerradas = tm_auto_cerrar_naves_inactivas($pdo);
    echo "Actividades cerradas automáticamente: $cerradas\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error en cierre automático: ' . $e->getMessage() . "\n");
    exit(1);
}
