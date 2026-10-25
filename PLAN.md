# Plan para completar el SGT

> Basado en el SRS v1.1 y en [`ANALISIS_SRS.md`](ANALISIS_SRS.md).
> Todo el trabajo va en el fork **T4R1N256/SGT-QA**. Nada se sube a `EduardGarrido/SGT`.

---

## 0. Decisiones

### Respuestas del equipo (25-oct-2026)

| Tema | Decisión |
|---|---|
| Ticket | Se genera en **PDF**. |
| Reportes | Cubren **ventas, surtidos, compras y stock**, y se **exportan a PDF**. |
| Cuadre de caja | Se cuenta **solo el efectivo**. |
| Reabastecimiento (Surtir) | **Entra en esta entrega**, y el **costo de compra se guarda aparte** del precio de venta. |
| Historial | Muestra **ventas pasadas** y **cajas** (cortes). |
| Eliminar producto | **Baja lógica**: el producto se marca como inactivo y no se borra. |
| Lector de código de barras | **No se usará.** |

### Supuestos míos (corríjanme si alguno está mal)

| # | Supuesto | Alternativa si no aplica |
|---|---|---|
| S1 | Los precios **ya incluyen impuestos**, así que el ticket no desglosa el IVA. Muestra la leyenda "Precios con impuestos incluidos". | Agregar una tasa de IVA por producto (0 % o 16 %) y desglosarla en el ticket. |
| S2 | El ticket mide **80 mm de ancho**, se guarda automáticamente en `Documentos/SGT/Tickets/` y se abre para imprimirlo. | Pedir dónde guardar cada vez, o usar 58 mm. |
| S3 | "Surtido" y "compra" son el **mismo registro**: una compra a un proveedor que se captura al recibir la mercancía. El reporte lo muestra de dos formas: unidades surtidas y dinero gastado. | Si se quiere un flujo **pedido → recepción** (pedir primero y surtir al llegar), se agrega en la Fase 3. |
| S4 | **Historial**, **Reportes**, **Surtir** y **Configuración** son **solo para el admin**. El empleado ve las ventas de su turno en Caja. | Darle al empleado acceso de solo lectura a su propio historial. |
| S5 | Se **quita la validación de correo por DNS** para cumplir RNF08 (funcionar sin internet). Solo se valida el formato. | — |
| S6 | La búsqueda en Ventas es por **nombre o categoría** (RF03). | — |
| S7 | No se agrega el estado de venta "Pagada": `finalizada` cubre "pagada y registrada". | — |
| S8 | **Fuera de alcance** mientras no se confirme: macOS, aviso de privacidad y respaldos de la base de datos (Fase 7, opcional). | — |
| S9 | El estado de producto "Sin stock" se renombra a **"Agotado"**, como dice el SRS. | — |

---

## 1. Decisiones técnicas transversales

### 1.1 Migraciones de la base de datos

**Problema.** `database.sql` solo se aplica en la primera instalación. Si se cambia el esquema, las instalaciones existentes no reciben el cambio.

**Solución.**
- Una carpeta `backend/config/migrations/NNN_descripcion.sql`.
- Una tabla `Migracion (Version, Aplicada_En)` que registra qué migraciones ya se aplicaron.
- `backend/config/migrate.php` aplica, en orden, las migraciones pendientes. MariaDB no permite deshacer cambios de esquema, así que una migración que falla no se registra y se reintenta en el siguiente arranque; por eso deben ser idempotentes.
- Se invoca desde `seeder.php`, que **ya corre en cada arranque** tanto en Docker (`entrypoint.sh`) como en Electron (`runSeeders()`).
- `database.sql` se queda como el esquema base (versión 0).

### 1.2 Generación de PDF (ticket, reportes y reimpresiones)

Se usa **Electron `webContents.printToPDF`**, que no necesita dependencias nuevas y funciona sin internet.

| Componente | Cambio |
|---|---|
| `src/main/main.js` | Un manejador `ipcMain.handle('pdf:generar', { html, carpeta, nombre, tamaño, abrir })` que crea una `BrowserWindow` oculta, carga el HTML, ejecuta `printToPDF`, escribe el archivo en `app.getPath('documents')/SGT/<carpeta>/` y lo abre con `shell.openPath`. |
| `src/main/preload.js` | Exponer `electronAPI.generarPDF(...)`. |
| `src/renderer/pdf/` | Plantillas `TicketPDF.jsx` y `ReportePDF.jsx` convertidas a HTML con `renderToStaticMarkup`. Usan **CSS en línea y fuentes del sistema** (sin Google Fonts, para que funcione sin conexión). |

### 1.3 Dinero

- Todas las columnas de dinero pasan a `DECIMAL(10,2)`.
- En PHP, los montos se comparan con `round($x, 2)`.
- En el frontend, los montos se calculan en centavos.

### 1.4 Validación en el backend

- Un helper `backend/config/validar.php` con funciones como `requerirNumero($v, min: 0)`, `requerirEntero` y `requerirTexto`.
- Reemplaza los `!$campo`, que rechazan el 0, y elimina `htmlspecialchars` de las **entradas**.
- **Ojo con las contraseñas existentes:** las que contienen `&`, `<` o `"` se guardaron escapadas. El login probará primero la contraseña tal cual y, si falla, la versión escapada. Si esa coincide, la vuelve a hashear correctamente.

### 1.5 Pruebas y CI

- **Backend:** PHPUnit en `backend/tests/` contra una base de datos de pruebas (MariaDB como servicio del CI).
- **Frontend:** Vitest para utilidades y cálculos (totales, cambio, validadores).
- **Workflow nuevo `ci.yml`** en cada push y PR: `eslint` sin `--fix`, PHPUnit y Vitest.
- `build.yml` se queda como está, para generar releases.

### 1.6 Ramas y PRs (en el fork)

- Una rama por fase, creada desde `main` del fork: `fase-0-base`, `fase-1-integridad`, etc.
- Cada fase se integra con un **PR a `main` del fork**, revisado por el equipo.
- El remoto `upstream` (`EduardGarrido/SGT`) tiene el push **deshabilitado** en esta sesión.

---

## 2. Fases

Tamaños: **S** ≈ 1–2 días · **M** ≈ 3–5 días · **L** ≈ 1–2 semanas, para una persona.

### Fase 0: Base de trabajo · S

- [x] Mecanismo de migraciones (§1.1).
- [x] Infraestructura de pruebas: PHPUnit, Vitest y `ci.yml` (§1.5).
- [x] Seeder de **datos de demostración** opcional (`SEED_DEMO=1`), con categorías, proveedores y unos 30 productos, para QA.
- [x] README: corregir la contraseña del admin (B10) y documentar el flujo de trabajo en el fork.

**Listo cuando:** el CI corre en verde con una prueba de ejemplo, y una base de datos existente recibe migraciones al arrancar sin perder datos.

### Fase 1: Integridad y correcciones · M

Corrige los bugs B1 a B11 de `ANALISIS_SRS.md`.

**Migraciones 001 y 002**
- [x] El dinero pasa a `DECIMAL(10,2)` en `Producto.Precio`, `Venta.Monto`, `Detalle_Venta.Precio_Unitario`, `Caja.Monto_*`, `Surtir.Monto` y `Detalle_Surtir.Precio_Unitario`.
- [x] `CHECK (Cantidad >= 0)` en `Producto` (regla 6).
- [x] `Caja.ID_Usuario_Cierre`.
- [x] 002: regresar a su texto original los datos que se guardaron con `htmlspecialchars` (`&amp;` → `&`).

**Ventas** (`saleRoute.php`, `venta.php`, `producto.php`)
- [x] `completeSale` en **una sola transacción y una sola conexión**, con `SELECT … FOR UPDATE` sobre los productos (B2).
- [x] Validar el stock en el servidor y responder 409 si no alcanza (reglas 1 y 6, B3).
- [x] **Recalcular el total** con los precios de la base de datos en vez de confiar en el `Monto` que manda el cliente.
- [x] Asignar `Fecha` y `Hora` al **finalizar** la venta (regla 9).
- [x] Cancelar las ventas `enproceso` huérfanas al abrir una venta nueva y al cerrar la caja.

**Caja** (`cashRegisterRoute.php`, `caja.php`, `Caja.jsx`)
- [x] Obtener la caja activa de la base de datos (`cajaAbierta()`) en vez de `$_SESSION` (B8).
- [x] Tomar `ID_Usuario` de la sesión, no del cuerpo de la petición.
- [x] Permitir $0 al abrir y al cerrar (B4).
- [x] **El cuadre suma solo efectivo** (B11) y compara redondeando (B1).
- [x] La pantalla de Caja y el resumen del corte muestran el **desglose por forma de pago**.
- [x] Arreglar la consulta de "Último corte" (B6): la caja **cerrada** más reciente.

**Productos**
- [x] Permitir crear productos con 0 existencias (B5).
- [x] Rechazar cantidades y precios negativos.

**Seguridad** (RNF03, RNF04, RL01)
- [x] Exigir sesión en los 8 endpoints de lectura que hoy están abiertos.
- [x] `getUserInfo?id=` solo para el admin; el empleado solo puede ver su propia información.
- [x] CORS: aceptar únicamente los orígenes conocidos. **Hallazgo:** con `file://`, la app compilada no podía iniciar sesión, porque Chromium rechaza `Access-Control-Allow-Origin: *` con credenciales. Ahora Electron sirve el renderer compilado por `http://localhost:<puerto libre>` y el backend solo acepta ese origen (`APP_ORIGIN`).
- [x] Quitar `htmlspecialchars` de las entradas (§1.4, B9).
- [ ] ~~Contraseña de la base de datos al azar en la app empaquetada~~ → **se movió a la fase 6**. El usuario `root` de la MariaDB local tampoco tiene contraseña, así que hay que resolver los dos juntos y probarlo con la app empaquetada.

**Otros**
- [x] **Regla 4:** el renderer avisa al proceso principal (por IPC) si hay caja abierta, y el evento `close` de la ventana se cancela con un diálogo de aviso.
- [x] **RNF08:** quitar `checkEmail` por DNS (S5).
- [x] **Pruebas:** stock insuficiente con rollback, cuadre con centavos, caja con $0, permisos por rol y ventas huérfanas.
- [x] Extra: `Conexion` lee las credenciales con `getenv()` cuando `$_ENV` viene vacío (`variables_order = "GPCS"`), y si no se puede conectar responde JSON con código 500.
- [x] Extra: `rowCount()` cuenta las filas encontradas, así que guardar un registro sin cambios ya no responde "no se encontró".

**Listo cuando:**
- Una venta de $10.10 en efectivo, contada exacta, deja la caja **cuadrada**.
- No se puede vender más que el stock, aunque se manipule la petición.
- Un empleado no puede leer los datos de otro empleado.
- Cerrar la ventana con la caja abierta muestra un aviso y no cierra la app.

### Fase 2: Venta completa y ticket PDF · M

**Migración 003**
- [x] `Venta.Pago_Recibido`, `Venta.Cambio` y `Venta.ID_Usuario`.
- [x] Tabla `Configuracion` con los datos de la tienda: nombre, dirección, teléfono, RFC y mensaje del ticket.
- [x] `Producto.Activo` para la baja lógica.
- [x] `'Sin stock'` → `'Agotado'` (S9).

**Backend**
- [x] `completeSale` guarda el pago recibido y el cambio (RF04), y devuelve el **folio** (`ID_Venta`) junto con el detalle.
- [x] Nuevo `getVenta?id=` que devuelve el detalle completo para reimprimir.
- [x] Nuevos `getConfig` y `updateConfig` (admin).
- [x] Nuevo `deleteProduct`: baja lógica, solo admin (RF06, B7).
- [x] Los productos inactivos no aparecen en Ventas pero se conservan en el historial.

**Frontend**
- [x] Plantilla **TicketPDF** con:
  - datos de la tienda;
  - folio, fecha, hora y cajero;
  - productos con cantidad, precio e importe;
  - total, forma de pago, pago recibido y cambio;
  - la leyenda del supuesto S1.
- [x] El PDF del ticket se **genera automáticamente** después de cada venta (regla 8).
- [x] Botón "Reimprimir último ticket" en Ventas.
- [x] Búsqueda por **nombre o categoría** (S6).
- [x] Página **Configuración** (admin) para los datos de la tienda, con vista previa del ticket.
- [x] Extra: con tarjeta o transferencia no se captura el pago (se cobra el total exacto); la búsqueda ignora acentos; el nombre de la tienda del login sale de la configuración; F9 reimprime.
- [x] Nota: si el sistema no tiene carpeta Documentos, Electron guarda en `<carpeta personal>/SGT/Tickets`.

**Listo cuando:** cada venta produce un PDF con folio y aparece en `Documentos/SGT/Tickets/`, y eliminar un producto lo oculta de Ventas sin romper el historial.

### Fase 3: Surtir (reabastecimiento) · M

**Migración 004**
- [x] `Surtir.ID_Usuario` y `Surtir.Notas`.
- [x] `Detalle_Surtir.Precio_Unitario` se usa como **costo**.
- [x] `Producto.Costo`, que guarda el último costo de compra para calcular márgenes.

**Backend:** `models/surtir.php` y `routes/restockRoute.php`, solo para el admin.
- [x] `createRestock`, en una transacción:
  - insertar `Surtir` y su `Detalle_Surtir`;
  - **sumar la cantidad al inventario**;
  - recalcular el estado del producto;
  - actualizar `Producto.Costo`.
- [x] `getAllRestocks?desde&hasta` y `getRestock?id`.

**Frontend:** página **Surtir**.
- [x] Elegir el proveedor y agregar productos. Por defecto se listan los de ese proveedor, con opción de ver todos.
- [x] Capturar cantidad y costo unitario por producto; el total se calcula solo.
- [x] Botón "**Sugerir productos con stock bajo**", que precarga los productos bajo el mínimo con una cantidad sugerida.
- [x] Lista de surtidos anteriores con su detalle.
- [x] Extra: se rechaza surtir de un proveedor o un producto dado de baja; la búsqueda de Surtir sí muestra los agotados; la cantidad sugerida llega al doble del mínimo.

**Listo cuando:** registrar un surtido sube la existencia, cambia el estado (por ejemplo, "Stock bajo" → "Disponible") y guarda el costo (RF05).

### Fase 4: Historial · M

**Backend**
- [x] `getSalesHistory?desde&hasta&forma_pago&estado&pagina`.
- [x] `getCashRegisters?desde&hasta`.
- [x] `getCashRegisterDetail?id`: ventas del corte, totales por forma de pago y diferencia.

**Frontend:** página **Historial** con dos pestañas.
- [x] **Ventas:** filtros por fecha, forma de pago y estado; tabla paginada; detalle en un modal; **reimprimir el ticket en PDF**.
- [x] **Cajas:** una fila por corte con fecha, cajero, horas de apertura y cierre, monto inicial, efectivo esperado, efectivo contado, diferencia y estado (cuadrada o descuadrada). Al abrir uno se ven sus ventas.

**Listo cuando:** se puede encontrar cualquier venta o corte pasado y reimprimir su ticket.

### Fase 5: Reportes con exportación a PDF · L

**Selector de periodo:** día (con fecha), semana (de lunes a domingo), mes o rango personalizado (RF11, diagramas SADT X3 e Y1). El reporte de stock es del momento y no usa periodo.

**Backend** (solo admin, regla 5): `getSalesReport`, `getRestockReport`, `getStockReport` y `getSummaryReport`, todos con `?desde&hasta` (por defecto, los últimos 30 días) salvo el de stock. Modelo `Reporte` y pruebas en `ReportesTest`.

| Reporte | Contenido |
|---|---|
| **Ventas** | [x] Total vendido, número de ventas, ticket promedio, artículos y ventas canceladas. Desglose por forma de pago (con % del total), por cajero y por día, y los 10 productos más vendidos (por unidades e importe). |
| **Surtidos y compras** | [x] Total gastado, número de surtidos, unidades y productos distintos. Desglose por proveedor, productos surtidos (unidades, costo promedio y total) y la lista de surtidos del periodo. |
| **Stock** | [x] Valor del inventario a costo y a precio de venta (el valor a costo avisa cuántos productos no tienen costo), existencias por categoría y la lista completa. **Productos en stock bajo y agotados** (regla 5) con la cantidad sugerida (la misma regla de Surtir) y su costo estimado. |
| **Resumen** | [x] Ventas menos compras del periodo y por día, utilidad bruta estimada con el último costo (margen y qué parte de lo vendido cubre) y los cortes de caja (descuadrados y diferencia neta). |

**Frontend**
- [x] Página **Reportes** con pestañas, indicadores y tablas, y un botón **Exportar PDF** (plantilla `ReportePDF`, tamaño carta, encabezados de tabla repetidos y número de página al pie). Se guarda en `Documentos/SGT/Reportes/reporte-<tipo>-<periodo>.pdf`.
- [x] La pantalla y el PDF salen del mismo armado (`utils/reportes.js`), así muestran las mismas cifras.
- [x] Ocultar "Reportes" del menú del empleado y proteger la ruta con `RutaAdmin` (regla 5).

**Listo cuando:** el admin genera los cuatro reportes para cualquier periodo, los exporta a PDF y las cifras coinciden con el Historial.

### Fase 6: QA y entrega · M

- [ ] `docs/QA_CHECKLIST.md`: casos manuales ligados a cada RF y regla, con el resultado de cada uno.
- [ ] Probar la **actualización** de una base de datos con datos de la versión actual: las migraciones se aplican sin pérdida.
- [ ] Probar la app **empaquetada** en Windows y Linux (CI con tag `v*`), incluyendo el primer arranque sin internet.
- [ ] Contraseñas al azar para el usuario de la app y para `root` en la MariaDB empaquetada (viene de la fase 1).
- [ ] Si la app se cierra de forma anormal, el proceso de PHP puede quedar vivo ocupando el puerto 8000 y el siguiente arranque no inicia sesión (visto en pruebas). Detectar y cerrar el PHP huérfano al arrancar, o usar un puerto libre y pasárselo al renderer (viene de la fase 2).
- [ ] Quitar código muerto: `firebase/php-jwt`, las rutas comentadas y los `console.log`.
- [ ] Actualizar el README, `ANALISIS_SRS.md` (matriz final) y la sección de sprints del SRS.

**Listo cuando:** el checklist QA está en verde para los 13 RF y las 9 reglas.

### Fase 7: Opcional · requiere confirmación

- Respaldo y restauración manual de la base de datos desde la app, con el `mariadb-dump` que ya va incluido (RNF02).
- Aviso de privacidad y consentimiento al registrar empleados (RL01, LFPDPPP).
- Build para macOS (RNF05).
- Convertir el router de `index.php` en una tabla de rutas con middleware (RNF10).
- Panel de inicio con un "Resumen del día" (ventas de hoy y alertas de stock bajo).
- Guardar el costo de cada producto al venderlo (en `Detalle_Venta`) para que la utilidad del reporte sea exacta y no una estimación con el último costo de compra.

---

## 3. Orden y dependencias

```
F0 Base ──► F1 Integridad ──► F2 Venta + Ticket PDF ──┬──► F3 Surtir ─────┐
                                                       │                  ├──► F5 Reportes ──► F6 QA y entrega
                                                       └──► F4 Historial ─┘
```

- F2 instala el mecanismo de PDF que usan F4 y F5.
- F3 y F4 pueden hacerse **en paralelo**: se pueden repartir entre integrantes.
- F5 necesita los datos de compras de F3.

---

## 4. Riesgos

| Riesgo | Mitigación |
|---|---|
| Al migrar de `FLOAT` a `DECIMAL`, los montos existentes se redondean | Se migra con `ROUND(x, 2)`. Antes se respalda la base de datos y se prueba sobre una copia (F6). |
| Contraseñas guardadas con `htmlspecialchars` | Login con respaldo y re-hash (§1.4). |
| PDF sin internet o con fuentes faltantes | CSS en línea y fuentes del sistema, sin recursos externos. |
| **No se puede hacer push al fork desde esta sesión** | La app de Claude no está instalada en la cuenta `T4R1N256`. Hay que instalarla (ver abajo). Mientras tanto, los commits se quedan en local. |

---

## 5. Trazabilidad SRS → fase

| Brecha | Fase |
|---|---|
| RF02, RF04, RF06, RF03 | F1 y F2 |
| RF05 (compras) | F3 |
| RF09, RF10 (bugs de caja) | F1 |
| RF11 | F5 |
| Regla 1 y regla 6 (stock) | F1 |
| Regla 4 (cerrar la app con la caja abierta) | F1 |
| Regla 5 (reportes de stock solo para el dueño) | F5 |
| Regla 8 (ticket) | F2 |
| Regla 9 (fecha y hora) | F1 |
| RNF02 (integridad) | F1 (+ F7 respaldos) |
| RNF03, RNF04, RL01 | F1 (+ F7 aviso de privacidad) |
| RNF05 (macOS) | F7 |
| RNF08 (sin internet) | F1 |
| Pruebas | F0 → todas |
| Historial (fuera del SRS) | F4 |
