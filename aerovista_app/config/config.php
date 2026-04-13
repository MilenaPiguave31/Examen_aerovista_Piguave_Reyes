<?php

define('APP_ENV',     'production');   // 'development' | 'production'
define('APP_NAME',    'AeroVista');
define('APP_VERSION', '1.0.0');
define('APP_URL',     'https://sourcecorpsa_aerovista.com'); // Sin barra final

// ── Base de datos ─────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_PORT',     '3306');
define('DB_NAME',     'sourcecorpsa_aerovista');
define('DB_USER',     'sourcecorpsa_aerovista');   // Cambia por tu usuario de MySQL
define('DB_PASS',     'P.tcscoreav.01@');   // Cambia por tu contraseña
define('DB_CHARSET',  'utf8mb4');

// ── Sesiones ──────────────────────────────────────────────────
define('SESSION_LIFETIME', 7200);          // 2 horas en segundos
define('SESSION_NAME',     'aerovista_session');

// ── Correo (para notificaciones de reserva) ───────────────────
// Solo usado si configuras el envío de emails con PHPMailer
define('MAIL_FROM',  'noreply@aerovista.com');
define('MAIL_NAME',  'AeroVista Booking');
define('MAIL_SMTP',  'smtp.tudominio.com');
define('MAIL_PORT',  587);
define('MAIL_USER',  'noreply@aerovista.com');
define('MAIL_PASS',  '');

// ── Reportes de error ─────────────────────────────────────────
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ── Zona horaria ──────────────────────────────────────────────
date_default_timezone_set('America/Guayaquil');

// ── Arrancar sesión ───────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_name(SESSION_NAME);
    session_start();
}
