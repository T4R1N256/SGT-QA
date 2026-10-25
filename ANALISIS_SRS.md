# SGT: brechas contra el SRS

> Comparación de **SRS - Sistema de Gestión de Tienda v1.1** (20 de mayo de 2026) contra el código de `main` (commit `c052740`).
> Leyenda: ✅ cumple · ⚠️ parcial o con defectos · ❌ no implementado

---

## 1. Resumen

El sistema tiene cubierto el **flujo operativo básico**: iniciar sesión, abrir caja, vender, cerrar caja y administrar el catálogo (productos, categorías, proveedores y usuarios). Para ser **completamente funcional** según el SRS le falta:

1. **Ticket de venta.** La regla 8 lo hace obligatorio y no existe.
2. **Reportes diarios, semanales y mensuales (RF11)** y la página **Historial**. Las dos son pantallas de "Próximamente".
3. **Reabastecimiento (Surtir).** Las tablas existen, pero no hay backend ni interfaz. RF05 pide actualizar el inventario "después de cada compra".
4. **Eliminar producto (RF06).** El botón existe, pero la ruta del backend no.
5. **Bugs en caja y ventas** que producen datos incorrectos:
   - La caja se marca "descuadrada" aunque el efectivo coincida.
   - El inventario puede quedar en negativo.
   - No se puede abrir la caja con $0.
   - La tarjeta "Último corte" del Dashboard casi nunca aparece.
6. **Requisitos no funcionales** de seguridad, operación sin internet y compatibilidad.

| Bloque | Cumple | Parcial | Falta |
|---|---|---|---|
| Requisitos funcionales (13) | 5 | 7 | 1 |
| Reglas de negocio (9) | 3 | 3 | 3 |
| RNF, legales y técnicos (11 filas de la tabla §4) | 4 | 6 | 1 |

---

## 2. Requisitos funcionales

| ID | Requisito | Estado | Evidencia o qué falta |
|---|---|---|---|
| RF01 | Iniciar sesión | ✅ | `authRoute.php` usa `password_verify` y valida el estado del usuario. Detalle menor: `htmlspecialchars` se aplica a la contraseña antes de verificarla (§5, B9). |
| RF02 | Registrar ventas | ⚠️ | `Sales.jsx` y `saleRoute.php` ya registran productos, cantidades, fecha, hora y monto. Faltan dos cosas: el **monto lo envía el cliente** y no se recalcula en el servidor, y la transacción no es atómica (B2). |
| RF03 | Buscar productos | ⚠️ | Solo busca por **nombre**, y del lado del cliente. El léxico (LEL) del SRS dice "por nombre **o categoría**". No hay código de barras (ver P7). |
| RF04 | Registrar pago | ⚠️ | Se guardan `Forma_Pago` y `Monto`, pero **no el monto recibido ni el cambio**. El Alcance menciona "la cantidad total de dinero recibido". |
| RF05 | Actualizar inventario tras compra y venta | ⚠️ | El descuento por **venta** sí está (`descontarInventario`). La entrada por **compra** (Surtir) no existe: `Surtir` y `Detalle_Surtir` no tienen modelo, ruta ni página. Hoy solo se puede editar la cantidad a mano. |
| RF06 | Registrar y **eliminar** productos | ⚠️ | Dar de alta funciona. Eliminar está roto: `api.js` llama a `deleteProduct`, que no existe en `index.php` y devuelve 404, y `deleteProducto()` del modelo está comentada. Además, no se puede crear un producto con 0 existencias (B5). |
| RF07 | Modificar productos | ✅ | `modifyProduct` (solo admin) recalcula el estado. |
| RF08 | Consultar productos | ✅ | `Inventory.jsx` (solo admin). |
| RF09 | Abrir caja | ⚠️ | Funciona, pero **rechaza un saldo inicial de $0** (B4). El `ID_Usuario` lo envía el cliente en vez de tomarlo de la sesión. |
| RF10 | Cerrar caja | ⚠️ | Funciona, pero el cuadre falla por usar `FLOAT` y comparar con `==` (B1). También rechaza un monto final de $0, y no genera el "reporte del día" ni la "nota de estado final" del diagrama SADT de cierre. |
| RF11 | Generar reportes (día, semana, mes) | ❌ | `/report` muestra "Próximamente". No hay endpoints de reportes. |
| RF12 | Cancelar venta | ✅ | Vaciar el carrito llama a `cancelSale`. |
| RF13 | Gestión de usuarios | ✅ | Alta, modificación y baja lógica (`no autorizado`). |

Hay además una página **fuera del SRS**: `Historial` (`/historial`) también está en "Próximamente" y no se sabe qué debe mostrar (P3).

---

## 3. Reglas de negocio (LEL)

| # | Regla | Estado | Comentario |
|---|---|---|---|
| 1 | No vender sin stock suficiente | ⚠️ | Solo lo valida el **frontend**, con una lista de productos que puede estar desactualizada. El backend descuenta sin revisar. |
| 2 | Sin caja abierta no hay ventas | ✅ | `openSale` exige `$_SESSION['ID_Caja']` y `Sales.jsx` se bloquea. |
| 3 | El empleado no modifica productos ni inventario | ✅ | `requerirAdmin()` en `create` y `modifyProduct`. |
| 4 | El sistema no se cierra con la caja abierta | ⚠️ | Solo se bloquea **cerrar sesión** (`LogoutButton.jsx`). Cerrar la ventana de Electron sí se permite: `main.js` no intercepta el evento `close`. |
| 5 | Solo el dueño hace reportes de stock bajo o agotado | ❌ | No existen esos reportes. Además, el menú muestra **Reporte** también a los empleados (`SideNav.jsx`). |
| 6 | El inventario no puede ser negativo | ❌ | `descontarInventario` hace `Cantidad - :Cantidad` sin validar. `create` y `modifyProduct` aceptan negativos. La columna no es `UNSIGNED` y no tiene `CHECK`. |
| 7 | Productos bien definidos | ✅ | Hay validación de campos obligatorios. |
| 8 | **Ticket después de cada venta** | ❌ | Solo aparece un aviso flotante con el total y el cambio. No hay ticket en pantalla, impreso ni en PDF. |
| 9 | Fecha y hora en todas las ventas | ⚠️ | La `Fecha` se guarda al **abrir** la venta y la `Hora` al **cerrarla**. Una venta que cruza la medianoche queda inconsistente, y las ventas canceladas quedan con hora `00:00:00`. |

Hay **diferencias de nomenclatura** entre el SRS y el código:
- Producto: el SRS dice "Agotado" y el código usa "Sin stock".
- Venta: el SRS define los estados "En proceso → Pagada → Finalizada", pero el código solo usa `enproceso → finalizada`.
- Empleado: los estados "Activo / No activo" ligados a la sesión no se registran en ningún lado.

---

## 4. Requisitos no funcionales, legales y técnicos

| ID | Requisito | Estado | Comentario |
|---|---|---|---|
| RNF01 | Fácil de configurar | ✅ | La app empaquetada incluye PHP y MariaDB y arranca sola. |
| RNF02 | Integridad de los datos | ⚠️ | Falla por varias causas: la transacción de venta no es atómica (B2), el dinero se guarda como `FLOAT` (B1), quedan ventas `enproceso` huérfanas si la app se cierra a mitad de venta, y **no hay respaldos ni exportación** de la base de datos. |
| RNF03 | Acceso con credenciales | ⚠️ | Hay login, pero 8 endpoints de lectura **no exigen sesión**: `getProduct`, `getAllProducts`, `getCategory`, `getAllCategories`, `getSupplier`, `getAllSuppliers`, `getCashRegister` y `getAllVentas`. |
| RNF04 / RL01 | Seguridad de datos personales (LFPDPPP) | ⚠️ | Hay cuatro problemas: (1) `getUserInfo?id=X` deja a **cualquier empleado** leer el teléfono, el correo y el domicilio de otro empleado; (2) no hay aviso de privacidad; (3) la contraseña de la base de datos está escrita en `main.js`; (4) el CORS es permisivo. |
| RNF05 | Cualquier sistema operativo | ⚠️ | El CI solo compila Linux y Windows. macOS está comentado en `build.yml`. |
| RNF06 / RNF09 | Usabilidad e interfaz intuitiva | ✅ | Interfaz consistente, con atajos F1 a F8 en Ventas (adelanta el "requisito futuro" del SRS). |
| RNF07 | Disponibilidad | ✅ | Funciona en local. |
| RNF08 | **Funciona sin internet** | ⚠️ | Al crear un usuario con correo se llama a `checkEmail`, que hace una consulta DNS de registros MX (`checkdnsrr`). **Sin internet no se puede crear un usuario con correo.** |
| RNF10 | Escalabilidad | ⚠️ | Funciona, pero el router es un `switch` de unas 40 entradas, no hay pruebas y los modelos son estáticos. |
| RT01–RT05 | MySQL, PHP, Electron+React, Tailwind+Vite, Git | ✅ | — |
| — | Pruebas y QA | ❌ | No hay **pruebas automatizadas**. El SRS menciona un "QA Test", pero el CI solo compila. |

---

## 5. Bugs que impiden considerarlo "funcional"

Ordenados por impacto. Todos se verificaron leyendo el código. B4 y B5 además se reprodujeron con PHP.

| # | Bug | Dónde | Efecto |
|---|---|---|---|
| **B1** | El dinero se guarda como `FLOAT` (precisión simple) y el cuadre compara con `==` | `database.sql` (`Monto`, `Precio`, `Monto_*`), `cashRegisterRoute.php:98` | Montos con centavos como $10.10 se guardan como 10.100000381… El corte sale **"descuadrada" aunque el efectivo coincida**. Hay que usar `DECIMAL(10,2)` y comparar redondeando o con una tolerancia. |
| **B2** | La transacción de venta no es atómica | `saleRoute.php:70-77`. `completeVenta()` y `readProducto()` abren su propia `new Conexion()`. | Si falla el descuento de inventario, la venta **queda "finalizada" de todos modos** y con un monto cobrado. |
| **B3** | No se valida el stock en el servidor | `producto.php` → `descontarInventario` | Hay inventario negativo (reglas 1 y 6). El `Monto` llega del cliente y no se recalcula con los precios de la base de datos. |
| **B4** | No se puede abrir ni cerrar caja con $0 | `cashRegisterRoute.php:22, 68` | `!"0"` es `true` en PHP, así que responde 400 "Faltan campos obligatorios". **Reproducido.** |
| **B5** | No se puede crear un producto con 0 existencias | `productRoute.php:22` | Por la misma razón. **Reproducido.** Si la cantidad es negativa, `$Estado` queda sin definir. |
| **B6** | "Último corte" del Dashboard | `caja.php:149` | Filtra con `Fecha = MAX(Fecha) AND Hora = MIN(Hora)` de **toda la tabla**, así que casi nunca encuentra un registro. Tampoco filtra `Estado = 'cerrada'`. |
| **B7** | Eliminar producto devuelve 404 | `index.php` (falta la ruta) y `producto.php` (método comentado) | El botón "Eliminar seleccionado" de Inventario nunca funciona. |
| **B8** | La caja activa depende de la sesión PHP | `cashRegisterRoute.php`, `saleRoute.php` | `$_SESSION['ID_Caja']` solo se asigna al **abrir** la caja. Si se pierde la sesión PHP con la caja abierta, el frontend la muestra abierta, pero `openSale` y `getAllVentas` fallan. Debería obtenerse de la base de datos (`Caja::cajaAbierta()`). |
| **B9** | `htmlspecialchars` se aplica a la entrada | Todas las rutas | Altera las contraseñas y los nombres que contienen `&`, `<` o `"`. Es un escape de salida usado en la entrada. |
| **B10** | Credencial del README | `README.md` vs `seeder.php` | El README dice `passadministrador` y el seeder crea `passadmin`. |
| **B11** | El cuadre mezcla formas de pago | `caja.php::getMontoFinal` | El "saldo esperado en efectivo" suma también las ventas con tarjeta y transferencia (ver P4). |

---

## 6. Huecos del SRS: preguntas para el equipo o el cliente

El SRS no define lo suficiente para implementar estos puntos sin suponer cosas.

> **Actualización 25-oct-2026:** el equipo respondió P1–P7. Las respuestas y los supuestos para las preguntas restantes están en [`PLAN.md` §0](PLAN.md).

| # | Pregunta | Por qué importa |
|---|---|---|
| **P1** | **Ticket:** ¿en pantalla, en PDF o en una **impresora térmica** (58 u 80 mm)? ¿Qué datos lleva: nombre de la tienda, RFC, folio, cajero, **IVA desglosado**? ¿Los precios ya incluyen IVA? | La regla 8 es obligatoria y el LEL menciona "impuestos pagados como el IVA". Si los precios no incluyen IVA, cambia el cálculo del total. |
| **P2** | **Reportes:** ¿qué deben contener? Por ejemplo: total vendido, número de ventas, ventas por forma de pago, productos más vendidos, ventas por empleado, cortes de caja, stock bajo o agotado, inicios de sesión. ¿Se ven en pantalla o también se **exportan** a PDF o Excel? | RF11 solo dice "día, semana o mes". El LEL menciona "inicios de sesión o detalles de jornadas", y hoy **no se registran los inicios de sesión**. |
| **P3** | **Historial:** ¿qué debe mostrar? ¿Ventas pasadas con su detalle y reimpresión del ticket? ¿Cortes de caja anteriores? | Está en el menú y en el router, pero no en el SRS. |
| **P4** | **Cuadre:** ¿el saldo esperado de la caja debe contar **solo efectivo**? ¿Hay una tolerancia aceptable, por ejemplo ±$1? | Hoy suma también tarjeta y transferencia, así que cualquier venta con tarjeta descuadra la caja. |
| **P5** | **Reabastecimiento (Surtir):** ¿entra en esta entrega? ¿Quién lo registra (solo el admin)? ¿Se guarda el **costo** de compra (`Precio_Unitario`) por separado del precio de venta? ¿Se generan "pedidos" a partir del stock bajo? | Las tablas existen y RF05 lo menciona, pero no hay interfaz ni flujo definido. |
| **P6** | **Eliminar producto:** ¿baja lógica (`inactivo`) o borrado físico? | Un borrado físico rompe las llaves foráneas de `Detalle_Venta` y el historial. Recomendamos la baja lógica, igual que con categorías y proveedores. |
| **P7** | ¿Se usará un **lector de código de barras**? ¿La búsqueda debe ser también por categoría? | Si hay lector, hay que añadir el campo `Codigo_Barras` a `Producto`. RNF05 habla de periféricos. |
| **P8** | **Validación de correo por DNS:** ¿la quitamos? | Contradice RNF08 (funcionar sin internet). La propuesta es validar solo el formato. |
| **P9** | **Protección de datos:** ¿hace falta una pantalla de **aviso de privacidad**? ¿Un empleado puede ver los datos de otro? ¿Se necesitan **respaldos** automáticos o manuales de la base de datos? | RL01 y RNF02. |
| **P10** | **Caja:** ¿cualquier empleado puede cerrar una caja que abrió otro? ¿Se registra quién la cerró? Si la PC se apaga con la caja abierta, ¿cómo se recupera? | El código no guarda quién cierra la caja, y la regla 4 no contempla un apagado inesperado. |
| **P11** | ¿**macOS** es requisito real, o bastan Windows y Linux? | RNF05 dice "cualquier sistema operativo", pero el CI no compila macOS. |
| **P12** | ¿El estado de venta **"Pagada"** debe existir por separado de "Finalizada"? | El SRS los define como dos estados distintos y el código los junta en uno. |

---

## 7. Plan sugerido (sprints 8 y 9)

**Sprint 8: corrección e integridad** (no depende de las respuestas)
1. B1: migrar el dinero a `DECIMAL(10,2)` y hacer el cuadre con redondeo.
2. B2 y B3: transacción única en `completeSale`, con `SELECT … FOR UPDATE`, validación de stock y monto recalculado en el servidor.
3. B4 y B5: validar con `isset` o `is_numeric` en lugar de `!`, y rechazar negativos (regla 6).
4. B6, B7 y B8: corregir la consulta del último corte, añadir la ruta y la baja lógica de producto, y obtener la caja activa de la base de datos.
5. Seguridad: proteger los 8 endpoints sin autenticación, restringir `getUserInfo?id=` a admin y quitar `htmlspecialchars` de las entradas.
6. Regla 4: interceptar el cierre de la ventana en `main.js` cuando haya caja abierta.
7. Pruebas mínimas: PHPUnit para venta y caja, y que el CI ejecute el lint.

**Sprint 9: funcionalidad faltante** (depende de P1 a P5)
1. Ticket de venta (P1).
2. Reportes día, semana y mes, más stock bajo y agotado, solo para admin (P2 y regla 5).
3. Historial (P3).
4. Surtir y reabastecimiento (P5).
5. Registro de inicios de sesión si los reportes lo requieren (P2).
6. Respaldos y aviso de privacidad (P9).
