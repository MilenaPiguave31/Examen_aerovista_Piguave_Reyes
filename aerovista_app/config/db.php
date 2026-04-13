<?php
/**
 * AeroVista · Conexión PDO a la base de datos
 * Devuelve un singleton de PDO.
 */

require_once __DIR__ . '/config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción no mostrar detalles de la excepción
            if (APP_ENV === 'development') {
                die('<pre>Error de conexión a la base de datos: ' . $e->getMessage() . '</pre>');
            } else {
                die('<p style="font-family:sans-serif;color:#c00">Error al conectar con la base de datos. Por favor intenta más tarde.</p>');
            }
        }
    }
    return $pdo;
}
