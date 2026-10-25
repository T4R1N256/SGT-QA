-- Fase 2 · Venta completa y ticket

-- RF04: se guarda cuánto pagó el cliente y el cambio; y quién hizo la venta (cajero del ticket)
ALTER TABLE Venta ADD COLUMN IF NOT EXISTS Pago_Recibido DECIMAL(10,2) DEFAULT NULL AFTER Monto;
ALTER TABLE Venta ADD COLUMN IF NOT EXISTS Cambio DECIMAL(10,2) DEFAULT NULL AFTER Pago_Recibido;
ALTER TABLE Venta ADD COLUMN IF NOT EXISTS ID_Usuario INT(11) DEFAULT NULL AFTER ID_Caja;
ALTER TABLE Venta ADD CONSTRAINT fk_venta_usuario FOREIGN KEY IF NOT EXISTS (ID_Usuario) REFERENCES Usuario (ID_Usuario);

-- Ventas anteriores: el cajero es quien abrió la caja
UPDATE Venta v INNER JOIN Caja c ON c.ID_Caja = v.ID_Caja SET v.ID_Usuario = c.ID_Usuario WHERE v.ID_Usuario IS NULL;

-- Datos de la tienda que aparecen en el ticket (una sola fila)
CREATE TABLE IF NOT EXISTS Configuracion (
  ID_Configuracion INT(11) NOT NULL,
  Nombre_Tienda VARCHAR(100) NOT NULL,
  Direccion VARCHAR(255) NOT NULL DEFAULT '',
  Telefono VARCHAR(20) NOT NULL DEFAULT '',
  RFC VARCHAR(13) NOT NULL DEFAULT '',
  Mensaje_Ticket VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (ID_Configuracion),
  CONSTRAINT chk_configuracion_unica CHECK (ID_Configuracion = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO Configuracion (ID_Configuracion, Nombre_Tienda, Mensaje_Ticket) VALUES (1, 'Abarrotes "La bamba"', '¡Gracias por su compra!');

-- RF06: baja lógica de productos; un producto eliminado se conserva para el historial de ventas
ALTER TABLE Producto ADD COLUMN IF NOT EXISTS Activo TINYINT(1) NOT NULL DEFAULT 1 AFTER Estado;

-- El SRS llama "Agotado" al producto sin existencias
UPDATE Producto SET Estado = 'Agotado' WHERE Estado = 'Sin stock';
