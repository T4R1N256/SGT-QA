-- Fase 1 · Integridad de datos

-- B1: el dinero pasa de FLOAT a DECIMAL(10,2) para que los centavos sean exactos
-- (con FLOAT, $10.10 se guardaba como 10.100000381 y el cuadre de caja fallaba)
ALTER TABLE Producto MODIFY Precio DECIMAL(10,2) NOT NULL;
ALTER TABLE Venta MODIFY Monto DECIMAL(10,2) NOT NULL;
ALTER TABLE Detalle_Venta MODIFY Precio_Unitario DECIMAL(10,2) NOT NULL;
ALTER TABLE Caja MODIFY Monto_Inicial DECIMAL(10,2) NOT NULL;
ALTER TABLE Caja MODIFY Monto_Final DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE Caja MODIFY Monto_Final_Sistema DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE Surtir MODIFY Monto DECIMAL(10,2) NOT NULL;
ALTER TABLE Detalle_Surtir MODIFY Precio_Unitario DECIMAL(10,2) NOT NULL;

-- Regla 6: el inventario no puede ser negativo
-- Los productos que ya quedaron en negativo pasan a 0 (agotados) antes de agregar la restricción
UPDATE Producto SET Cantidad = 0, Estado = 'Sin stock' WHERE Cantidad < 0;
ALTER TABLE Producto DROP CONSTRAINT IF EXISTS chk_producto_cantidad;
ALTER TABLE Producto ADD CONSTRAINT chk_producto_cantidad CHECK (Cantidad >= 0 AND Cantidad_Minima >= 0 AND Precio >= 0);

-- Quién cerró la caja
ALTER TABLE Caja ADD COLUMN IF NOT EXISTS ID_Usuario_Cierre INT(11) DEFAULT NULL AFTER ID_Usuario;
ALTER TABLE Caja ADD CONSTRAINT fk_caja_usuario_cierre FOREIGN KEY IF NOT EXISTS (ID_Usuario_Cierre) REFERENCES Usuario (ID_Usuario);

-- Ventas que quedaron "enproceso" en cajas ya cerradas: nunca se cobraron
UPDATE Venta v INNER JOIN Caja c ON c.ID_Caja = v.ID_Caja SET v.Estado = 'cancelada' WHERE v.Estado = 'enproceso' AND c.Estado = 'cerrada';
