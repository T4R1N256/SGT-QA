-- Datos como los guardaba la versión original de SGT (antes de las migraciones de este fork)
-- Se cargan sobre database.sql sin migraciones para probar que una instalación existente se actualiza sin perder nada:
--  - Textos escapados con htmlspecialchars (&amp;, &quot;, &#039;)
--  - Dinero en FLOAT (10.10 se guardaba como 10.1000003814...)
--  - Producto con existencia negativa y productos en 'Sin stock'
--  - Una venta 'enproceso' que quedó en una caja cerrada
-- Contraseñas: admin (1) passadmin, cajera (2) cajera2024

INSERT INTO Usuario (ID_Usuario, Password, Estado) VALUES
(1, '$2y$10$wmhTzj/Kf3xZz/7aEvK1AeUxMv0aCk9PAf7T/YoIgYVdVWvmLUJUK', 'autorizado'),
(2, '$2y$10$9nm5TRYYb.vFcoeENb5ei.JC7x0OTDtUD52QsZ5Pxx1u4iYThaWr.', 'autorizado');

INSERT INTO Contacto_Empleado (ID_Contacto_Empleado, Telefono, Correo, Calle, Colonia, Codigo_Postal) VALUES
(1, '0000000000', 'correoadmin@gmail.com', 'Calle', 'Colonia', '00000'),
(2, '6561112233', 'cajera@correo.com', 'Av. Juárez &amp; 16 de Septiembre', 'Centro', '32000');

INSERT INTO Empleado (ID_Empleado, Nombre, Puesto, Estado, ID_Contacto_Empleado, ID_Usuario) VALUES
(1, 'Usuario Administrador', 'admin', 'activo', 1, 1),
(2, 'María O&#039;Connor', 'empleado', 'activo', 2, 2);

INSERT INTO Categoria (ID_Categoria, Nombre_Categoria, Estado) VALUES
(1, 'Abarrotes', 'activo'),
(2, 'Lácteos &amp; huevo', 'activo');

INSERT INTO Proveedor (ID_Proveedor, Nombre_Proveedor, Telefono, Estado) VALUES
(1, 'Pan &amp; Cía', '6560000001', 'activo'),
(2, 'Distribuidora del Norte', '6560000002', 'activo');

INSERT INTO Producto (ID_Producto, Nombre_Producto, Precio, Cantidad, Unidad_Medida, Estado, Cantidad_Minima, ID_Categoria, ID_Proveedor) VALUES
(1, 'Galletas &quot;Marías&quot;', 10.10, 30, 'pieza', 'Disponible', 5, 1, 1),
(2, 'Leche entera 1 L', 28.50, 4, 'litro', 'Stock bajo', 6, 2, 2),
(3, 'Huevo 12 piezas', 42.00, 0, 'pieza', 'Sin stock', 3, 2, 2),
(4, 'Azúcar 1 kg', 33.30, -2, 'pieza', 'Sin stock', 5, 1, 2);

INSERT INTO Caja (ID_Caja, Fecha, Hora, Hora_Final, Monto_Inicial, Monto_Final, Monto_Final_Sistema, Estado, Estado_Final, ID_Usuario) VALUES
(1, '2026-03-02', '08:00:00', '20:15:00', 100.00, 191.80, 191.80, 'cerrada', 'cuadrada', 2);

INSERT INTO Venta (ID_Venta, Fecha, Hora, Monto, Estado, Forma_Pago, ID_Caja) VALUES
(1, '2026-03-02', '09:30:00', 58.80, 'finalizada', 'efectivo', 1),
(2, '2026-03-02', '12:10:00', 42.00, 'finalizada', 'tarjeta', 1),
(3, '2026-03-02', '18:45:00', 33.00, 'finalizada', 'efectivo', 1),
(4, '2026-03-02', '19:50:00', 0, 'enproceso', NULL, 1);

INSERT INTO Detalle_Venta (ID_Detalle_Venta, Cantidad, Precio_Unitario, ID_Venta, ID_Producto) VALUES
(1, 3, 10.10, 1, 1),
(2, 1, 28.50, 1, 2),
(3, 1, 42.00, 2, 3),
(4, 1, 33.00, 3, 4),
(5, 2, 10.10, 4, 1);

INSERT INTO Surtir (ID_Surtir, Fecha, Hora, Monto, ID_Proveedor) VALUES
(1, '2026-03-01', '10:00:00', 252.50, 1);

INSERT INTO Detalle_Surtir (ID_Detalle_Surtir, Cantidad, Precio_Unitario, ID_Surtir, ID_Producto) VALUES
(1, 25, 10.10, 1, 1);
