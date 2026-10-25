-- Fase 1 · B9: hasta ahora el backend aplicaba htmlspecialchars a las entradas,
-- así que "Pan & Cía" se guardaba como "Pan &amp; Cía" y se veía así en pantalla.
-- Desde esta fase el texto se guarda tal cual; esta migración regresa los datos existentes a su texto original.
-- &amp; se reemplaza al final para no generar entidades nuevas.

UPDATE Producto SET Nombre_Producto = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Nombre_Producto, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Nombre_Producto LIKE '%&%;%';
UPDATE Producto SET Unidad_Medida = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Unidad_Medida, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Unidad_Medida LIKE '%&%;%';
UPDATE Categoria SET Nombre_Categoria = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Nombre_Categoria, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Nombre_Categoria LIKE '%&%;%';
UPDATE Proveedor SET Nombre_Proveedor = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Nombre_Proveedor, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Nombre_Proveedor LIKE '%&%;%';
UPDATE Proveedor SET Telefono = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Telefono, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Telefono LIKE '%&%;%';
UPDATE Empleado SET Nombre = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Nombre, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Nombre LIKE '%&%;%';
UPDATE Contacto_Empleado SET Telefono = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Telefono, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Telefono LIKE '%&%;%';
UPDATE Contacto_Empleado SET Correo = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Correo, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Correo LIKE '%&%;%';
UPDATE Contacto_Empleado SET Calle = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Calle, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Calle LIKE '%&%;%';
UPDATE Contacto_Empleado SET Colonia = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Colonia, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Colonia LIKE '%&%;%';
UPDATE Contacto_Empleado SET Codigo_Postal = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(Codigo_Postal, '&lt;', '<'), '&gt;', '>'), '&quot;', '"'), '&#039;', ''''), '&amp;', '&') WHERE Codigo_Postal LIKE '%&%;%';
