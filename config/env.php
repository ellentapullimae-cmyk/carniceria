<?php
/**
 * Carga variables de entorno desde .env (desarrollo y produccion).
 * Las variables ya definidas en el servidor tienen prioridad sobre .env.
 */

define('BASE_PATH', dirname(__DIR__));

$envFile = BASE_PATH . '/.env';
if (is_readable($envFile)) {
    $lineas = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim($valor);
        if (getenv($clave) === false) {
            putenv($clave . '=' . $valor);
            $_ENV[$clave] = $valor;
        }
    }
}

function env(string $clave, ?string $defecto = null): ?string
{
    $valor = getenv($clave);
    return $valor === false ? $defecto : $valor;
}
