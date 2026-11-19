<?php
/** Cierra la sesion destruyendo la cookie y los datos. */

require_once __DIR__ . '/config/bootstrap.php';
arrancar_sesion();
cerrar_sesion();

// Nueva sesion limpia para mensajes flash
session_start();
flash('ok', 'Sesion cerrada correctamente.');
redirigir('login.php');
