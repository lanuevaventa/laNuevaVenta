-- Tablas mínimas para métricas del panel ADMIN

CREATE TABLE IF NOT EXISTS Pedido (
  id_pedido SERIAL PRIMARY KEY,
  id_usuario INT NOT NULL REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
  fecha_creado TIMESTAMP NOT NULL DEFAULT NOW(),
  total NUMERIC(12,2) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS PedidoItem (
  id_item SERIAL PRIMARY KEY,
  id_pedido INT NOT NULL REFERENCES Pedido(id_pedido) ON DELETE CASCADE,
  id_producto INT NOT NULL REFERENCES Producto(id_producto) ON DELETE CASCADE,
  cantidad INT NOT NULL DEFAULT 1,
  precio_unitario NUMERIC(12,2) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS Opinion (
  id_opinion SERIAL PRIMARY KEY,
  id_usuario INT NOT NULL REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
  id_producto INT NOT NULL REFERENCES Producto(id_producto) ON DELETE CASCADE,
  calificacion INT NOT NULL CHECK (calificacion BETWEEN 1 AND 5),
  comentario TEXT,
  fecha TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS ActividadAdmin (
  id_actividad SERIAL PRIMARY KEY,
  tipo VARCHAR(40) NOT NULL,
  descripcion TEXT NOT NULL,
  fecha TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS Envio (
  id_envio SERIAL PRIMARY KEY,
  id_pedido INT NOT NULL REFERENCES Pedido(id_pedido) ON DELETE CASCADE,
  estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  fecha_creado TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pedido_fecha ON Pedido(fecha_creado);
CREATE INDEX IF NOT EXISTS idx_opinion_prod ON Opinion(id_producto);
CREATE INDEX IF NOT EXISTS idx_envio_estado ON Envio(estado);

INSERT INTO Pedido (id_usuario, fecha_creado, total) VALUES
 (2,NOW(),1999.90),
 (3,NOW(),14999.00),
 (4,NOW(),8999.50),
 (5,NOW(),2999.00),
 (6,NOW(),45999.99),
 (7,NOW(),4999.00),
 (8,NOW(),2499.00),
 (9,NOW(),12999.00),
 (10,NOW(),6999.00),
 (2,NOW(),3999.00);

INSERT INTO PedidoItem (id_pedido,id_producto,cantidad,precio_unitario) VALUES
 (1,1,1,1999.90),
 (2,2,1,14999.00),
 (3,3,1,8999.50),
 (4,4,2,2999.00),
 (5,5,1,45999.99),
 (6,6,1,4999.00),
 (7,7,2,2499.00),
 (8,8,1,12999.00),
 (9,9,1,6999.00),
 (10,10,1,3999.00);

INSERT INTO Opinion (id_usuario,id_producto,calificacion,comentario,fecha) VALUES
 (3,1,5,'Excelente audio',NOW()),
 (4,2,4,'Cómoda pero caliente',NOW()),
 (5,3,5,'Muy buenas',NOW()),
 (6,4,4,'Buena pelota',NOW()),
 (7,5,5,'Dron impresionante',NOW()),
 (8,6,4,'Set completo',NOW()),
 (9,7,5,'Libro recomendado',NOW()),
 (10,8,5,'Sabor genial',NOW()),
 (2,9,4,'Divertido',NOW()),
 (3,10,4,'Cumple su función',NOW());


INSERT INTO ActividadAdmin (tipo,descripcion,fecha) VALUES
 ('login','Admin inició sesión',NOW()),
 ('envio','Actualizado envío 1 a en_transito',NOW()),
 ('producto','Creado producto 11 (test)',NOW()),
 ('cupon','Desactivado cupón DRON500',NOW()),
 ('usuario','Eliminado usuario de pruebas id 11',NOW()),
 ('reporte','Procesada denuncia 3',NOW()),
 ('envio','Actualizado envío 2 a entregado',NOW()),
 ('pedido','Pedido 10 revisado',NOW()),
 ('seguridad','Intento acceso fallido admin',NOW()),
 ('backup','Respaldo nightly completado',NOW());

INSERT INTO Envio (id_pedido,estado,fecha_creado) VALUES
 (1,'pendiente',NOW()),
 (2,'procesando',NOW()),
 (3,'en_transito',NOW()),
 (4,'entregado',NOW()),
 (5,'pendiente',NOW()),
 (6,'cancelado',NOW()),
 (7,'en_transito',NOW()),
 (8,'procesando',NOW()),
 (9,'entregado',NOW()),
 (10,'pendiente',NOW());
