<?php
/**
 * AeroVista · Funciones de utilidad global
 */

require_once __DIR__ . '/../config/db.php';

// ── Seguridad ─────────────────────────────────────────────────

/** Escapa HTML para salida segura */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Genera un código PNR aleatorio de 6 caracteres alfanuméricos */
function generarPNR(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $pnr = '';
        for ($i = 0; $i < 6; $i++) {
            $pnr .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = getDB()->prepare('SELECT id FROM reservas WHERE codigo_pnr = ?');
        $stmt->execute([$pnr]);
    } while ($stmt->fetch());
    return $pnr;
}

/** Responde JSON y termina la ejecución */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Autenticación ─────────────────────────────────────────────

/** Devuelve true si hay un usuario de sesión activo */
function estaLogueado(): bool {
    return isset($_SESSION['usuario_id']);
}

/** Devuelve true si el usuario activo es administrador */
function esAdmin(): bool {
    return estaLogueado() && ($_SESSION['usuario_rol'] ?? '') === 'admin';
}

/** Redirige si el usuario NO está logueado */
function requiereLogin(string $redirect = '/login.php'): void {
    if (!estaLogueado()) {
        header('Location: ' . $redirect);
        exit;
    }
}

/** Redirige si el usuario NO es admin */
function requiereAdmin(): void {
    if (!esAdmin()) {
        header('Location: /');
        exit;
    }
}

/** Inicia la sesión del usuario tras login exitoso */
function iniciarSesion(array $usuario): void {
    session_regenerate_id(true);
    $_SESSION['usuario_id']     = $usuario['id'];
    $_SESSION['usuario_nombre'] = $usuario['nombre'];
    $_SESSION['usuario_email']  = $usuario['email'];
    $_SESSION['usuario_rol']    = $usuario['rol'];
}

// ── Formato ───────────────────────────────────────────────────

/** Formatea precio en USD */
function precio(float $amount): string {
    return '$' . number_format($amount, 2, '.', ',');
}

/** Formatea duración en minutos → "Xh Ym" */
function duracion(int $minutos): string {
    $h = intdiv($minutos, 60);
    $m = $minutos % 60;
    return ($h > 0 ? "{$h}h " : '') . ($m > 0 ? "{$m}m" : '');
}

/** Formatea fecha para mostrar al usuario: "15 May 2024" */
function fechaCorta(\DateTime|string $fecha): string {
    if (is_string($fecha)) $fecha = new \DateTime($fecha);
    return $fecha->format('d M Y');
}

/** Formatea hora: "08:30" */
function hora(string $datetime): string {
    return (new \DateTime($datetime))->format('H:i');
}

// ── Vuelos ────────────────────────────────────────────────────

/** Devuelve el badge HTML de estado de un vuelo */
function badgeEstado(string $estado): string {
    $map = [
        'programado' => ['bg-surface-container-high text-on-surface-variant', 'Programado'],
        'a_tiempo'   => ['bg-green-100 text-green-700',  'A Tiempo'],
        'retrasado'  => ['bg-orange-100 text-orange-700','Retrasado'],
        'cancelado'  => ['bg-red-100 text-red-700',      'Cancelado'],
        'completado' => ['bg-blue-100 text-blue-700',    'Completado'],
    ];
    [$css, $label] = $map[$estado] ?? ['bg-gray-100 text-gray-600', ucfirst($estado)];
    return "<span class=\"px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-tighter {$css}\">{$label}</span>";
}

// ── Validaciones ──────────────────────────────────────────────

/** Valida email */
function validarEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Valida que una cadena no esté vacía después de trim */
function noVacio(string $val): bool {
    return trim($val) !== '';
}

// ── Flash messages ────────────────────────────────────────────

function flashSet(string $tipo, string $mensaje): void {
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flashGet(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Renderiza un flash message si existe */
function flashRender(): void {
    $flash = flashGet();
    if (!$flash) return;
    $colorMap = [
        'success' => 'bg-green-100 border-green-400 text-green-800',
        'error'   => 'bg-red-100 border-red-400 text-red-800',
        'info'    => 'bg-blue-100 border-blue-400 text-blue-800',
        'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-800',
    ];
    $css = $colorMap[$flash['tipo']] ?? $colorMap['info'];
    echo "<div class=\"{$css} border px-4 py-3 rounded-lg mb-6 text-sm font-medium\">"
        . e($flash['mensaje'])
        . "</div>";
}

// ── CSRF ──────────────────────────────────────────────────────

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function csrfValidar(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals(csrfToken(), $token);
}
