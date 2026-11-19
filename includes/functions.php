<?php
/** Funciones generales de utilidad. */

require_once __DIR__ . '/../config/env.php';

/** Escapa salida HTML (proteccion XSS). */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** Ruta base del proyecto ("/carniceria" en XAMPP, "" si esta en la raiz). */
function base_path(): string
{
    static $ruta = null;
    if ($ruta === null) {
        $url   = env('APP_URL', '');
        $parte = parse_url($url, PHP_URL_PATH);
        $ruta  = ($parte !== null && $parte !== false) ? rtrim($parte, '/') : '';
    }
    return $ruta;
}

/** Construye una URL interna: url('login.php') -> /carniceria/login.php */
function url(string $ruta = ''): string
{
    return base_path() . '/' . ltrim($ruta, '/');
}

/** Redireccion y detiene el script. */
function redirigir(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

/** Mensaje flash (se muestra una sola vez). */
function flash(string $tipo, ?string $mensaje = null): ?string
{
    if ($mensaje !== null) {
        $_SESSION['flash'][$tipo] = $mensaje;
        return null;
    }
    $valor = $_SESSION['flash'][$tipo] ?? null;
    unset($_SESSION['flash'][$tipo]);
    return $valor;
}
