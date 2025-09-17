CREATE TABLE Usuario (
    id_usuario 			SERIAL PRIMARY KEY,
    nombre				VARCHAR(50) NOT NULL,
    apellido 			VARCHAR(50),
    correo				VARCHAR(100) NOT NULL UNIQUE,
    contrasenia 		VARCHAR(255) NOT NULL,
    telefono 			VARCHAR(20),
    foto_perfil			VARCHAR(255), 
    fecha_registro		DATE NOT NULL,
    rol                 VARCHAR(20) DEFAULT 'usuario'
);

-- Crear usuario admin con contraseña hasheada correctamente
INSERT INTO Usuario (nombre, apellido, correo, contrasenia, telefono, fecha_registro, rol) 
VALUES ('Admin', 'Sistema', 'admin@lanuevaventa.com', '$2y$10$NGgLWSofyUNPX5mxj30Z1Ok5O6qIsSbiVenbMCC6Allvt3wBtHiMK', '123456789', CURRENT_DATE, 'admin');
-- Contraseña: admin123

CREATE TABLE Producto (
    id_producto 		SERIAL PRIMARY KEY,
    titulo				VARCHAR(100) NOT NULL,
    precio				NUMERIC(10,2) NOT NULL CHECK (precio >= 0),
    descripcion 		VARCHAR(500),
    stock				INT NOT NULL CHECK (stock >= 0),
    categoria			VARCHAR(50),
    fecha_publicacion	TIMESTAMP NOT NULL,
    imagen				VARCHAR(255)
);

CREATE TABLE Vende (
    id_producto INT PRIMARY KEY,
    id_usuario INT,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
);

CREATE TABLE enCarrito (
    id_producto INT,
    id_usuario INT,
    fecha_creacion TIMESTAMP,
    PRIMARY KEY (id_producto, id_usuario),
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_producto) REFERENCES Producto(id_producto)
);

CREATE TABLE Denuncia (
    id_denuncia SERIAL PRIMARY KEY,
    contenido VARCHAR(400) NOT NULL,
    fecha_creacion TIMESTAMP NOT NULL,
    id_usuario INT,
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Compra (
    id_compra SERIAL PRIMARY KEY,
    id_comprador INT,
    id_vendedor INT,
    fecha_compra TIMESTAMP,
    FOREIGN KEY (id_comprador) REFERENCES Usuario(id_usuario),
    FOREIGN KEY (id_vendedor) REFERENCES Usuario(id_usuario)
);

CREATE TABLE Metodo (
    id_metodo SERIAL PRIMARY KEY,
    numero_tarjeta VARCHAR(20) NOT NULL,
    anio_expiracion INT NOT NULL,
    mes_expiracion INT NOT NULL,
    emisor VARCHAR(30) NOT NULL,
    titular VARCHAR(40) NOT NULL
);

CREATE TABLE Pago (
    id_compra 		INT PRIMARY KEY,
    id_metodo 		INT,
    monto 			NUMERIC(8,2) NOT NULL,
    estado_pago 	BOOLEAN NOT NULL,
    cupones			VARCHAR(50),
    FOREIGN KEY (id_compra) REFERENCES Compra(id_compra),
    FOREIGN KEY (id_metodo) REFERENCES Metodo(id_metodo)
);