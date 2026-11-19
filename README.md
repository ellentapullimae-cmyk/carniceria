# La Tradición Carnicería — Sistema de Comercio Electrónico

Sistema profesional de gestión para carnicería: tienda en línea con autenticación,
base de datos MySQL y paneles por roles (administrador, empleado y cliente).

## Tecnologías

| Capa | Tecnología |
|---|---|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP 8.1+ (PDO) |
| Base de datos | MySQL / MariaDB (utf8mb4) |
| Entorno local | XAMPP (Apache + MySQL + phpMyAdmin) |

## Requisitos

- XAMPP con PHP 8.1 o superior y MySQL en ejecución
- Navegador web actualizado

## Instalación con XAMPP

1. **Inicie XAMPP** (Apache y MySQL en verde).
2. **Copie el proyecto** a `C:\xampp\htdocs\carniceria`
   (si ya está allí, omite este paso).
3. **Cree la base de datos**: abra `http://localhost/phpmyadmin` →
   pestaña *Importar* → seleccione `sql/carniceria_db.sql` → *Continuar*.
   (El archivo crea la BD `carniceria_db`, sus tablas, relaciones y datos iniciales.)
4. **Configuración**: revise el archivo `.env` en la raíz del proyecto.
   Para XAMPP los valores por defecto ya funcionan:
   ```
   APP_ENV=development
   APP_URL=http://localhost/carniceria
   DB_HOST=localhost
   DB_NAME=carniceria_db
   DB_USER=root
   DB_PASS=
   ```
5. **Abra el sistema**: `http://localhost/carniceria/login.php`

## Cuentas de prueba

| Usuario | Contraseña | Rol |
|---|---|---|
| `admin` | `Admin123!` | administrador (acceso completo) |
| `empleado` | `Empleado123!` | empleado (ventas, pedidos, inventario) |
| `cliente` | `Cliente123!` | cliente (compras y seguimiento) |

> Cambie estas contraseñas después del primer inicio (o regístrelas desde el módulo
> de usuarios en la siguiente fase).

## Seguridad implementada

- Contraseñas cifradas con `password_hash()` (bcrypt) y verificadas con `password_verify()`
- Consultas preparadas PDO (`ATTR_EMULATE_PREPARES = false`) — sin SQL Injection
- Tokens CSRF en todos los formularios (`hash_equals`)
- Salida escapada con `htmlspecialchars()` — sin XSS
- Sesiones con cookies `httponly` + `samesite=Lax`, `session_regenerate_id()` al entrar
- Bloqueo tras 5 intentos fallidos de login (15 minutos)
- Recuperación de contraseñas con token aleatorio de un solo uso (30 minutos),
  guardado solo como hash SHA-256
- Protección de rutas por rol (`requiere_rol()` → 403 si no corresponde)
- `.env` fuera de Git y bloqueado por Apache (403)
- Archivo `config/.htaccess` con acceso denegado

## Estructura

```
carniceria/
├── index.html / style.css / script.js   ← diseño original de la tienda (se conserva)
├── login.php / registro.php             ← autenticación (clientes y staff)
├── recuperar.php / restablecer.php      ← recuperación de contraseñas
├── logout.php
├── config/
│   ├── env.php          (carga .env: desarrollo/producción)
│   ├── database.php     (conexión PDO única)
│   ├── auth.php         (sesiones, CSRF, roles, resets)
│   └── bootstrap.php    (carga completa)
├── includes/functions.php               (escape XSS, urls, flashes)
├── panel/index.php      ← panel de administrador/empleado (protegido)
├── cliente/index.php    ← panel del cliente (protegido)
├── assets/css/          (auth.css, panel.css)
├── sql/carniceria_db.sql                ← importar en phpMyAdmin
├── .env / .env.example                  ← configuración por entorno
└── .htaccess                             ← DirectoryIndex + seguridad
```

## Despliegue en hosting (producción)

1. Suba todos los archivos (excepto `.env` de desarrollo) por FTP o Git.
2. Cree la base de datos en el panel del hosting e importe `sql/carniceria_db.sql`.
3. Cree un `.env` **nuevo** con las credenciales del hosting:
   ```
   APP_ENV=production
   APP_URL=https://www.tudominio.com
   DB_HOST=localhost
   DB_NAME=u12345_carniceria
   DB_USER=u12345_usuario
   DB_PASS=clave_del_hosting
   ```
4. Active **HTTPS** (el flag `secure` de las cookies se activa automáticamente
   cuando `APP_ENV=production`).
5. Configure SMTP en el hosting para el envío de correos de recuperación
   (en `recuperar.php` se usa `mail()`; en desarrollo el enlace se muestra en pantalla).

## Fases del proyecto

- **Fase 1 (implementada y probada):** base de datos, autenticación de los 3 roles,
  registro de clientes, recuperación de contraseñas, CSRF, protección de rutas.
- **Fase 2:** dashboard completo con gráficos, productos y categorías (CRUD + fotos).
- **Fase 3:** inventario (entradas/salidas, alertas) y ofertas.
- **Fase 4:** ventas (POS), pedidos y permisos de empleado.
- **Fase 5:** carrito, checkout e historial del cliente.
- **Fase 6:** reportes y configuración.
