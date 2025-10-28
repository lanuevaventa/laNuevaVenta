-- ============================================
-- La Nueva Venta - Esquema completo
-- Idempotente: seguro para ejecutarse varias veces
-- ============================================

-- Usuarios
CREATE TABLE IF NOT EXISTS Usuario (
    id_usuario          SERIAL PRIMARY KEY,
    nombre              VARCHAR(50) NOT NULL,
    apellido            VARCHAR(50),
    correo              VARCHAR(100) NOT NULL UNIQUE,
    contrasenia         VARCHAR(255) NOT NULL,
    telefono            VARCHAR(20),
    foto_perfil         VARCHAR(255),
    fecha_registro      DATE NOT NULL,
    rol                 VARCHAR(20) DEFAULT 'usuario'
);

-- Usuario admin (evita duplicados)
INSERT INTO Usuario (nombre, apellido, correo, contrasenia, telefono, fecha_registro, rol)
VALUES ('Admin', 'Sistema', 'admin@lanuevaventa.com', '$2y$10$NGgLWSofyUNPX5mxj30Z1Ok5O6qIsSbiVenbMCC6Allvt3wBtHiMK', '123456789', CURRENT_DATE, 'admin')
ON CONFLICT (correo) DO NOTHING;
-- Contraseña: admin123

-- Productos (incluye columnas de oferta)
CREATE TABLE IF NOT EXISTS Producto (
    id_producto         SERIAL PRIMARY KEY,
    titulo              VARCHAR(100) NOT NULL,
    precio              NUMERIC(10,2) NOT NULL CHECK (precio >= 0),
    descripcion         VARCHAR(500),
    stock               INT NOT NULL CHECK (stock >= 0),
    categoria           VARCHAR(50),
    fecha_publicacion   TIMESTAMP NOT NULL,
    imagen              VARCHAR(255),
    oferta_activa       BOOLEAN DEFAULT FALSE,
    oferta_tipo         VARCHAR(12),
    oferta_valor        NUMERIC(10,2),
    oferta_desde        TIMESTAMP NULL,
    oferta_hasta        TIMESTAMP NULL
);

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM pg_constraint
    WHERE conname = 'producto_oferta_tipo_chk'
      AND conrelid = 'producto'::regclass
  ) THEN
    ALTER TABLE Producto
      ADD CONSTRAINT producto_oferta_tipo_chk
      CHECK (oferta_tipo IS NULL OR oferta_tipo IN ('porcentaje','fijo'));
  END IF;
END;
$$;

-- Para bases ya existentes sin columnas de oferta, agrégalas (idempotente)
ALTER TABLE Producto
  ADD COLUMN IF NOT EXISTS oferta_activa BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS oferta_tipo   VARCHAR(12),
  ADD COLUMN IF NOT EXISTS oferta_valor  NUMERIC(10,2),
  ADD COLUMN IF NOT EXISTS oferta_desde  TIMESTAMP NULL,
  ADD COLUMN IF NOT EXISTS oferta_hasta  TIMESTAMP NULL;

-- Relación vendedor-producto
CREATE TABLE IF NOT EXISTS Vende (
    id_producto INT PRIMARY KEY,
    id_usuario  INT,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
);

-- Carrito por usuario
CREATE TABLE IF NOT EXISTS enCarrito (
    id_producto     INT,
    id_usuario      INT,
    fecha_creacion  TIMESTAMP,
    PRIMARY KEY (id_producto, id_usuario),
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
);

-- Denuncias
CREATE TABLE IF NOT EXISTS Denuncia (
    id_denuncia     SERIAL PRIMARY KEY,
    contenido       VARCHAR(400) NOT NULL,
    fecha_creacion  TIMESTAMP NOT NULL,
    id_usuario      INT,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
);

-- Compras
CREATE TABLE IF NOT EXISTS Compra (
    id_compra       SERIAL PRIMARY KEY,
    id_comprador    INT,
    id_vendedor     INT,
    fecha_compra    TIMESTAMP,
    FOREIGN KEY (id_comprador) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_vendedor) REFERENCES Usuario(id_usuario)
);

-- Métodos de pago
CREATE TABLE IF NOT EXISTS Metodo (
    id_metodo           SERIAL PRIMARY KEY,
    numero_tarjeta      VARCHAR(20) NOT NULL,
    anio_expiracion     INT NOT NULL,
    mes_expiracion      INT NOT NULL,
    emisor              VARCHAR(30) NOT NULL,
    titular             VARCHAR(40) NOT NULL
);

-- Pagos
CREATE TABLE IF NOT EXISTS Pago (
    id_compra    INT PRIMARY KEY,
    id_metodo    INT,
    monto        NUMERIC(8,2) NOT NULL,
    estado_pago  BOOLEAN NOT NULL,
    cupones      VARCHAR(50),
    FOREIGN KEY (id_compra) REFERENCES Compra(id_compra),
    FOREIGN KEY (id_metodo) REFERENCES Metodo(id_metodo)
);

-- Comentarios (incluye respuestas anidadas)
CREATE TABLE IF NOT EXISTS Comentario (
    id_comentario       SERIAL PRIMARY KEY,
    id_producto         INT NOT NULL,
    id_usuario          INT NOT NULL,
    contenido           TEXT NOT NULL,
    fecha_comentario    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    id_comentario_padre INT NULL,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE
);

-- Para bases ya existentes, asegurar columna y FK de comentarios anidados
ALTER TABLE Comentario
    ADD COLUMN IF NOT EXISTS id_comentario_padre INT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.table_constraints
        WHERE constraint_name = 'fk_comentario_padre'
          AND table_name = 'comentario'
    ) THEN
        ALTER TABLE Comentario
            ADD CONSTRAINT fk_comentario_padre
            FOREIGN KEY (id_comentario_padre)
            REFERENCES Comentario(id_comentario)
            ON DELETE CASCADE;
    END IF;
END$$;

CREATE INDEX IF NOT EXISTS idx_comentario_padre
    ON Comentario(id_comentario_padre);

-- Imágenes de producto
CREATE TABLE IF NOT EXISTS ImagenProducto (
    id_imagen       SERIAL PRIMARY KEY,
    id_producto     INT NOT NULL,
    ruta_imagen     VARCHAR(255) NOT NULL,
    es_principal    BOOLEAN DEFAULT FALSE,
    orden_imagen    INT DEFAULT 1,
    fecha_subida    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto) ON DELETE CASCADE
);

-- Cupones por producto
CREATE TABLE IF NOT EXISTS Cupon (
  id_cupon     SERIAL PRIMARY KEY,
  id_producto  INT NOT NULL REFERENCES Producto(id_producto) ON DELETE CASCADE,
  codigo       VARCHAR(50) NOT NULL,
  tipo         VARCHAR(12) NOT NULL,
  valor        NUMERIC(10,2) NOT NULL,
  valido_desde TIMESTAMP NULL,
  valido_hasta TIMESTAMP NULL,
  activo       BOOLEAN DEFAULT TRUE
);

-- Constraints e índices de Cupon (idempotentes)
ALTER TABLE Cupon
    ADD CONSTRAINT IF NOT EXISTS cupon_tipo_chk
    CHECK (tipo IN ('porcentaje','fijo'));

ALTER TABLE Cupon
    ADD CONSTRAINT IF NOT EXISTS cupon_codigo_unico
    UNIQUE (id_producto, codigo);

CREATE INDEX IF NOT EXISTS idx_cupon_producto ON Cupon (id_producto);
CREATE INDEX IF NOT EXISTS idx_cupon_codigo   ON Cupon (codigo);