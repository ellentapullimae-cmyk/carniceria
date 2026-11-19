<?php
/** Conexion PDO unica y reutilizable (consultas preparadas, sin SQL dinamico). */

require_once __DIR__ . '/env.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'carniceria_db')
        );
        try {
            $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            error_log('[DB] ' . $e->getMessage());
            exit('No se pudo conectar con la base de datos. Verifique que XAMPP (MySQL) este iniciado '
                . 'y que exista la base de datos <b>carnicereria_db</b> (importe sql/carniceria_db.sql desde phpMyAdmin).');
        }
    }
    return $pdo;
}
