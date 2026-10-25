-- Tienda en línea · T0: cuentas de cliente y pedidos
-- Los clientes son independientes de los empleados (Usuario/Empleado): nunca entran a SGT

CREATE TABLE IF NOT EXISTS Cliente (
  ID_Cliente INT(11) NOT NULL AUTO_INCREMENT,
  Nombre VARCHAR(100) NOT NULL,
  Apellidos VARCHAR(100) NOT NULL,
  Correo VARCHAR(255) NOT NULL,
  Telefono VARCHAR(20) NOT NULL,
  Password VARCHAR(255) NOT NULL,
  Fecha_Registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  Activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (ID_Cliente),
  UNIQUE KEY uq_cliente_correo (Correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pedido "pendiente de pago" (D4): no descuenta existencias; los datos de entrega se copian al confirmar
-- Clave_Envio la genera el navegador por cada confirmación: el mismo envío repetido no crea dos pedidos
CREATE TABLE IF NOT EXISTS Pedido (
  ID_Pedido INT(11) NOT NULL AUTO_INCREMENT,
  Folio VARCHAR(20) NOT NULL,
  ID_Cliente INT(11) NOT NULL,
  Clave_Envio VARCHAR(64) NOT NULL,
  Fecha DATE NOT NULL,
  Hora TIME NOT NULL,
  Subtotal DECIMAL(10,2) NOT NULL,
  IVA DECIMAL(10,2) NOT NULL,
  Total DECIMAL(10,2) NOT NULL,
  Estado VARCHAR(20) NOT NULL DEFAULT 'pendiente_pago',
  Recibe VARCHAR(150) NOT NULL,
  Telefono VARCHAR(20) NOT NULL,
  Calle VARCHAR(100) NOT NULL,
  Numero_Exterior VARCHAR(10) NOT NULL,
  Numero_Interior VARCHAR(10) NOT NULL DEFAULT '',
  Colonia VARCHAR(100) NOT NULL,
  Codigo_Postal CHAR(5) NOT NULL,
  Ciudad VARCHAR(100) NOT NULL,
  Entidad VARCHAR(40) NOT NULL,
  Referencias VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (ID_Pedido),
  UNIQUE KEY uq_pedido_folio (Folio),
  UNIQUE KEY uq_pedido_envio (ID_Cliente, Clave_Envio),
  CONSTRAINT fk_pedido_cliente FOREIGN KEY (ID_Cliente) REFERENCES Cliente (ID_Cliente),
  CONSTRAINT chk_pedido_montos CHECK (Subtotal >= 0 AND IVA >= 0 AND Total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Productos del pedido con el precio vigente al confirmar
CREATE TABLE IF NOT EXISTS Detalle_Pedido (
  ID_Detalle_Pedido INT(11) NOT NULL AUTO_INCREMENT,
  ID_Pedido INT(11) NOT NULL,
  ID_Producto INT(11) NOT NULL,
  Cantidad INT(11) NOT NULL,
  Precio_Unitario DECIMAL(10,2) NOT NULL,
  Importe DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (ID_Detalle_Pedido),
  KEY ID_Pedido (ID_Pedido),
  CONSTRAINT fk_detalle_pedido_pedido FOREIGN KEY (ID_Pedido) REFERENCES Pedido (ID_Pedido),
  CONSTRAINT fk_detalle_pedido_producto FOREIGN KEY (ID_Producto) REFERENCES Producto (ID_Producto),
  CONSTRAINT chk_detalle_pedido CHECK (Cantidad > 0 AND Precio_Unitario >= 0 AND Importe >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
