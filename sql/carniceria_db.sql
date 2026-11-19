-- ============================================================
-- LA TRADICION CARNICERIA - Base de datos carniceria_db
-- Importar desde phpMyAdmin > Importar > elegir este archivo
-- Compatible con MySQL 8 / MariaDB 10.4 (XAMPP)
-- ============================================================

DROP DATABASE IF EXISTS carniceria_db;
CREATE DATABASE carniceria_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE carniceria_db;

-- ------------------------------------------------------------
-- ROLES
-- ------------------------------------------------------------
CREATE TABLE roles (
  id_rol       INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(30)  NOT NULL UNIQUE,
  descripcion  VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

INSERT INTO roles (nombre, descripcion) VALUES
('administrador', 'Acceso completo al sistema'),
('empleado',      'Ventas, pedidos e inventario'),
('cliente',       'Compras y seguimiento de pedidos');

-- ------------------------------------------------------------
-- USUARIOS (todos los roles: admin, empleado y cliente)
-- ------------------------------------------------------------
CREATE TABLE usuarios (
  id_usuario    INT AUTO_INCREMENT PRIMARY KEY,
  id_rol        INT NOT NULL,
  usuario       VARCHAR(50)  NOT NULL UNIQUE,
  correo        VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nombre        VARCHAR(100) NOT NULL,
  activo        TINYINT(1)   NOT NULL DEFAULT 1,
  creado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso DATETIME     NULL,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- RECUPERACION DE CONTRASENAS (tokens de un solo uso)
-- ------------------------------------------------------------
CREATE TABLE password_resets (
  id_reset     INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario   INT NOT NULL,
  token_hash   CHAR(64) NOT NULL,
  expira_en    DATETIME NOT NULL,
  usado        TINYINT(1) NOT NULL DEFAULT 0,
  creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resets_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
  INDEX idx_token (token_hash)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- CATEGORIAS
-- ------------------------------------------------------------
CREATE TABLE categorias (
  id_categoria INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(60) NOT NULL UNIQUE,
  activo       TINYINT(1)  NOT NULL DEFAULT 1
) ENGINE=InnoDB;

INSERT INTO categorias (nombre) VALUES
('Res'), ('Cerdo'), ('Pollo'), ('Cordero'), ('Embutidos'), ('Pescados');

-- ------------------------------------------------------------
-- PRODUCTOS
-- ------------------------------------------------------------
CREATE TABLE productos (
  id_producto   INT AUTO_INCREMENT PRIMARY KEY,
  id_categoria  INT NOT NULL,
  nombre        VARCHAR(100)   NOT NULL,
  descripcion   TEXT           NULL,
  precio        DECIMAL(10,2)  NOT NULL CHECK (precio >= 0),
  stock         INT            NOT NULL DEFAULT 0 CHECK (stock >= 0),
  stock_minimo  INT            NOT NULL DEFAULT 5,
  imagen        VARCHAR(255)   NULL,
  activo        TINYINT(1)     NOT NULL DEFAULT 1,
  creado_en     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_productos_categoria FOREIGN KEY (id_categoria) REFERENCES categorias(id_categoria),
  INDEX idx_stock (stock)
) ENGINE=InnoDB;

INSERT INTO productos (id_categoria, nombre, descripcion, precio, stock, stock_minimo) VALUES
(1, 'Bife ancho de res',      'Corte premium para asar, marmoleado equilibrado.',        38.00, 40, 8),
(1, 'Lomo de res',            'Corte tierno, ideal para salteados y parrilla.',           52.00, 25, 8),
(1, 'Falda de res',           'Excelente para guisos y seco de carne.',                   32.00, 30, 8),
(2, 'Chuleta de cerdo',       'Chuleta fresca con hueso, cortada al gusto.',              24.00, 35, 8),
(2, 'Costilla de cerdo',      'Para horno o parrilla, gran sabor.',                       26.00, 20, 6),
(3, 'Pollo entero',           'Pollo fresco del dia, limpio y listo.',                    14.00, 50, 10),
(3, 'Pechuga de pollo',       'Sin hueso, ideal para dietas y salteados.',                19.00, 28, 8),
(4, 'Pierna de cordero',      'Para asar al horno con hierbas.',                          45.00, 12, 5),
(5, 'Chorizo criollo',        'Elaboracion casera, receta tradicional.',                  18.00, 22, 6),
(5, 'Longaniza extra',        'Suave y aromatica, lista para freir.',                     19.00, 18, 6),
(6, 'Trucha fresca',          'Trucha de rio, entera o en filetes.',                      22.00, 10, 5),
(6, 'Corvina del dia',        'Pescado blanco fresco, corte limpio.',                     20.00, 3, 5);  -- stock bajo para alertas

-- ------------------------------------------------------------
-- CLIENTES (vinculados a un usuario con rol cliente)
-- ------------------------------------------------------------
CREATE TABLE clientes (
  id_cliente  INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario  INT NULL UNIQUE,
  nombre      VARCHAR(100) NOT NULL,
  dni         VARCHAR(12)  NULL UNIQUE,
  telefono    VARCHAR(20)  NULL,
  correo      VARCHAR(120) NULL,
  direccion   VARCHAR(150) NULL,
  creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_clientes_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PEDIDOS (realizados desde la tienda por clientes)
-- ------------------------------------------------------------
CREATE TABLE pedidos (
  id_pedido    INT AUTO_INCREMENT PRIMARY KEY,
  id_cliente   INT NOT NULL,
  id_usuario   INT NULL COMMENT 'Usuario del staff que gestiono el pedido',
  estado       ENUM('pendiente','confirmado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  metodo_pago  ENUM('efectivo','tarjeta','transferencia') NOT NULL DEFAULT 'efectivo',
  total        DECIMAL(10,2) NOT NULL DEFAULT 0,
  notas        VARCHAR(255) NULL,
  creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pedidos_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente),
  CONSTRAINT fk_pedidos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
  INDEX idx_estado (estado), INDEX idx_fecha (creado_en)
) ENGINE=InnoDB;

CREATE TABLE detalle_pedidos (
  id_detalle      INT AUTO_INCREMENT PRIMARY KEY,
  id_pedido       INT NOT NULL,
  id_producto     INT NOT NULL,
  cantidad        INT NOT NULL CHECK (cantidad > 0),
  precio_unitario DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_dp_pedido   FOREIGN KEY (id_pedido)   REFERENCES pedidos(id_pedido) ON DELETE CASCADE,
  CONSTRAINT fk_dp_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- VENTAS (registra el staff en el POS o al entregar un pedido)
-- ------------------------------------------------------------
CREATE TABLE ventas (
  id_venta    INT AUTO_INCREMENT PRIMARY KEY,
  id_pedido   INT NULL UNIQUE COMMENT 'Si la venta proviene de un pedido',
  id_cliente  INT NULL COMMENT 'Venta directa de mostrador (sin pedido)',
  id_usuario  INT NOT NULL COMMENT 'Empleado que registro la venta',
  metodo_pago ENUM('efectivo','tarjeta','transferencia') NOT NULL DEFAULT 'efectivo',
  total       DECIMAL(10,2) NOT NULL,
  creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ventas_pedido  FOREIGN KEY (id_pedido)  REFERENCES pedidos(id_pedido),
  CONSTRAINT fk_ventas_cliente FOREIGN KEY (id_cliente) REFERENCES clientes(id_cliente),
  CONSTRAINT fk_ventas_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  INDEX idx_fecha (creado_en)
) ENGINE=InnoDB;

CREATE TABLE detalle_ventas (
  id_detalle      INT AUTO_INCREMENT PRIMARY KEY,
  id_venta        INT NOT NULL,
  id_producto     INT NOT NULL,
  cantidad        INT NOT NULL CHECK (cantidad > 0),
  precio_unitario DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_dv_venta   FOREIGN KEY (id_venta)   REFERENCES ventas(id_venta) ON DELETE CASCADE,
  CONSTRAINT fk_dv_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- MOVIMIENTOS DE INVENTARIO (entradas y salidas)
-- ------------------------------------------------------------
CREATE TABLE movimientos_inventario (
  id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
  id_producto   INT NOT NULL,
  tipo          ENUM('entrada','salida') NOT NULL,
  cantidad      INT NOT NULL CHECK (cantidad > 0),
  motivo        VARCHAR(100) NOT NULL,
  referencia    VARCHAR(100) NULL,
  id_usuario    INT NULL,
  creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mi_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto),
  CONSTRAINT fk_mi_usuario  FOREIGN KEY (id_usuario)  REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
  INDEX idx_producto_fecha (id_producto, creado_en)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- OFERTAS
-- ------------------------------------------------------------
CREATE TABLE ofertas (
  id_oferta    INT AUTO_INCREMENT PRIMARY KEY,
  id_producto  INT NOT NULL,
  titulo       VARCHAR(100) NOT NULL,
  precio_oferta DECIMAL(10,2) NOT NULL CHECK (precio_oferta >= 0),
  fecha_inicio DATE NOT NULL,
  fecha_fin    DATE NOT NULL,
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ofertas_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON DELETE CASCADE,
  CHECK (fecha_fin >= fecha_inicio)
) ENGINE=InnoDB;

INSERT INTO ofertas (id_producto, titulo, precio_oferta, fecha_inicio, fecha_fin) VALUES
(1, 'Bife ancho -20%', 33.60, '2026-11-16', '2026-11-30'),
(4, 'Chuleta de cerdo -15%', 22.10, '2026-11-16', '2026-11-23'),
(6, 'Pollo entero -10%', 12.60, '2026-11-18', '2026-11-25');

-- ============================================================
-- USUARIOS INICIALES (contrasenas cifradas con password_hash)
#   admin     / Admin123!     -> administrador
#   empleado  / Empleado123!  -> empleado
#   cliente   / Cliente123!   -> cliente (demo)
#   IMPORTANTE: cambie estas contrasenas despues del primer inicio.
-- ============================================================
INSERT INTO usuarios (id_rol, usuario, correo, password_hash, nombre) VALUES
(1, 'admin',    'admin@carniceria.com',    '$2y$10$egjASO6s34g9H3pWmkzToOXim0ZnT5SeDRTzKcgSXmHAqjIQgzmw2', 'Administrador General'),
(2, 'empleado', 'empleado@carniceria.com', '$2y$10$cJEN4L97f8v9waxZZsq.p.vA6aefWdlhx9pM1xziy1AbAaXE.gxOu', 'Empleado de Mostrador'),
(3, 'cliente',  'cliente@ejemplo.com',     '$2y$10$EGppHjrQSPEKnfMT9mqEp.nMewHfYYfPlSBKCy1VXhX75uWGV0mb2', 'Cliente Demo');

INSERT INTO clientes (id_usuario, nombre, dni, telefono, correo, direccion) VALUES
(3, 'Cliente Demo', '12345678', '987654321', 'cliente@ejemplo.com', 'Av. Los Olivos 456');

-- ------------------------------------------------------------
-- DATOS DE EJEMPLO: ventas de los ultimos 7 dias (para graficos)
-- y su correspondiente salida de inventario.
-- ------------------------------------------------------------
INSERT INTO pedidos (id_cliente, id_usuario, estado, metodo_pago, total, creado_en) VALUES
(1, 2, 'entregado', 'efectivo',   76.00, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(1, 2, 'entregado', 'tarjeta',    52.00, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, 2, 'entregado', 'efectivo',   68.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 2, 'confirmado','efectivo',   45.00, DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad, precio_unitario) VALUES
(1, 1, 2, 38.00),
(2, 2, 1, 52.00),
(3, 4, 1, 24.00), (3, 7, 1, 19.00), (3, 9, 1, 18.00), (3, 5, 1, 7.00),
(4, 8, 1, 45.00);

INSERT INTO ventas (id_pedido, id_cliente, id_usuario, metodo_pago, total, creado_en) VALUES
(1, 1, 2, 'efectivo',  76.00, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 1, 2, 'tarjeta',   52.00, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(3, 1, 2, 'efectivo',  68.00, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(NULL, 1, 2, 'efectivo', 38.00, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(NULL, 1, 2, 'efectivo', 47.00, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(NULL, 1, 2, 'efectivo', 24.00, DATE_SUB(NOW(), INTERVAL 0 DAY));

INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario) VALUES
(1, 1, 2, 38.00),
(2, 2, 1, 52.00),
(3, 4, 1, 24.00), (3, 7, 1, 19.00), (3, 9, 1, 18.00), (3, 5, 1, 7.00),
(4, 1, 1, 38.00),
(5, 4, 1, 24.00), (5, 7, 1, 23.00),
(6, 4, 1, 24.00);

INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, referencia, id_usuario, creado_en) VALUES
(1, 'entrada', 60, 'Compra a proveedor', 'OC-001', 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(6, 'entrada', 70, 'Compra a proveedor', 'OC-002', 1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(1, 'salida',   2, 'Venta', 'Venta #1', 2, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(2, 'salida',   1, 'Venta', 'Venta #2', 2, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(4, 'salida',   2, 'Venta', 'Venta #3 / #6', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, 'salida',   1, 'Venta', 'Venta #3', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(9, 'salida',   1, 'Venta', 'Venta #3', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 'salida',   1, 'Venta directa', 'Venta #4', 2, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(7, 'salida',   1, 'Venta directa', 'Venta #5', 2, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Vista util: stock bajo (alertas)
CREATE OR REPLACE VIEW v_stock_bajo AS
SELECT id_producto, nombre, stock, stock_minimo
FROM productos
WHERE activo = 1 AND stock <= stock_minimo;
