-- Los formularios de SGT pasaban el texto por DOMPurify antes de guardarlo. Si el texto tenía un "<",
-- lo devolvía como HTML: "Pan & Cía <grande>" se guardaba como "Pan &amp; Cía " y se veía así en SGT,
-- en los tickets, en los reportes y en la tienda en línea. Desde ahora el texto se guarda tal cual.
-- Esta migración regresa las entidades a su texto original. Lo que DOMPurify borró (por ejemplo, "<grande>")
-- ya no se puede recuperar. &amp; se reemplaza al final para no generar entidades nuevas.
-- Son las columnas que llenan los formularios de Inventario, Categorías, Proveedores y Usuarios.

UPDATE Producto SET Nombre_Producto = REPLACE(REPLACE(REPLACE(REPLACE(Nombre_Producto, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Nombre_Producto LIKE '%&%;%';
UPDATE Categoria SET Nombre_Categoria = REPLACE(REPLACE(REPLACE(REPLACE(Nombre_Categoria, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Nombre_Categoria LIKE '%&%;%';
UPDATE Proveedor SET Nombre_Proveedor = REPLACE(REPLACE(REPLACE(REPLACE(Nombre_Proveedor, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Nombre_Proveedor LIKE '%&%;%';
UPDATE Proveedor SET Telefono = REPLACE(REPLACE(REPLACE(REPLACE(Telefono, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Telefono LIKE '%&%;%';
UPDATE Empleado SET Nombre = REPLACE(REPLACE(REPLACE(REPLACE(Nombre, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Nombre LIKE '%&%;%';
UPDATE Contacto_Empleado SET Telefono = REPLACE(REPLACE(REPLACE(REPLACE(Telefono, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Telefono LIKE '%&%;%';
UPDATE Contacto_Empleado SET Correo = REPLACE(REPLACE(REPLACE(REPLACE(Correo, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Correo LIKE '%&%;%';
UPDATE Contacto_Empleado SET Calle = REPLACE(REPLACE(REPLACE(REPLACE(Calle, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Calle LIKE '%&%;%';
UPDATE Contacto_Empleado SET Colonia = REPLACE(REPLACE(REPLACE(REPLACE(Colonia, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Colonia LIKE '%&%;%';
UPDATE Contacto_Empleado SET Codigo_Postal = REPLACE(REPLACE(REPLACE(REPLACE(Codigo_Postal, '&lt;', '<'), '&gt;', '>'), '&nbsp;', _utf8mb4 X'C2A0'), '&amp;', '&') WHERE Codigo_Postal LIKE '%&%;%';
