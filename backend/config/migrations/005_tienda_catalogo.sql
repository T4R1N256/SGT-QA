-- Tienda en línea · T0: datos del catálogo que ve el cliente

-- Descripción del producto (página de detalle de la tienda). El admin la captura en Inventario
ALTER TABLE Producto ADD COLUMN IF NOT EXISTS Descripcion VARCHAR(500) NOT NULL DEFAULT '' AFTER Nombre_Producto;

-- Tasa de IVA en porcentaje. Los precios ya la incluyen (D2): la tienda solo la desglosa
ALTER TABLE Configuracion ADD COLUMN IF NOT EXISTS Tasa_IVA DECIMAL(5,2) NOT NULL DEFAULT 16.00 AFTER Mensaje_Ticket;
ALTER TABLE Configuracion DROP CONSTRAINT IF EXISTS chk_configuracion_iva;
ALTER TABLE Configuracion ADD CONSTRAINT chk_configuracion_iva CHECK (Tasa_IVA >= 0 AND Tasa_IVA <= 100);
