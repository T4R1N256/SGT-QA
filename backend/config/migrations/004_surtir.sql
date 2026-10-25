-- Fase 3 · Surtir (reabastecimiento con costo de compra, RF05)

-- Quién registró el surtido y notas libres (factura, observaciones)
ALTER TABLE Surtir ADD COLUMN IF NOT EXISTS ID_Usuario INT(11) DEFAULT NULL AFTER ID_Proveedor;
ALTER TABLE Surtir ADD COLUMN IF NOT EXISTS Notas VARCHAR(255) NOT NULL DEFAULT '' AFTER ID_Usuario;
ALTER TABLE Surtir ADD CONSTRAINT fk_surtir_usuario FOREIGN KEY IF NOT EXISTS (ID_Usuario) REFERENCES Usuario (ID_Usuario);

-- Detalle_Surtir.Precio_Unitario es el costo de compra por unidad (aparte del precio de venta)
-- Producto.Costo guarda el último costo de compra, para estimar márgenes en los reportes
ALTER TABLE Producto ADD COLUMN IF NOT EXISTS Costo DECIMAL(10,2) DEFAULT NULL AFTER Precio;
ALTER TABLE Producto DROP CONSTRAINT IF EXISTS chk_producto_costo;
ALTER TABLE Producto ADD CONSTRAINT chk_producto_costo CHECK (Costo IS NULL OR Costo >= 0);
