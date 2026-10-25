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
- `backend/config/migrate.php` aplica, en orden y dentro de una transacción, las migraciones pendientes.
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

- [ ] Mecanismo de migraciones (§1.1).
- [ ] Infraestructura de pruebas: PHPUnit, Vitest y `ci.yml` (§1.5).
- [ ] Seeder de **datos de demostración** opcional (`SEED_DEMO=1`), con categorías, proveedores y unos 30 productos, para QA.
- [ ] README: corregir la contraseña del admin (B10) y documentar el flujo de trabajo en el fork.

**Listo cuando:** el CI corre en verde con una prueba de ejemplo, y una base de datos existente recibe migraciones al arrancar sin perder datos.

### Fase 1: Integridad y correcciones · M

Corrige los bugs B1 a B11 de `ANALISIS_SRS.md`.

**Migración M001**
- [ ] El dinero pasa a `DECIMAL(10,2)` en `Producto.Precio`, `Venta.Monto`, `Detalle_Venta.Precio_Unitario`, `Caja.Monto_*`, `Surtir.Monto` y `Detalle_Surtir.Precio_Unitario`.
- [ ] `CHECK (Cantidad >= 0)` en `Producto` (regla 6).
- [ ] `Caja.ID_Usuario_Cierre`.

**Ventas** (`saleRoute.php`, `venta.php`, `producto.php`)
- [ ] `completeSale` en **una sola transacción y una sola conexión**, con `SELECT … FOR UPDATE` sobre los productos (B2).
- [ ] Validar el stock en el servidor y responder 409 si no alcanza (reglas 1 y 6, B3).
- [ ] **Recalcular el total** con los precios de la base de datos en vez de confiar en el `Monto` que manda el cliente.
- [ ] Asignar `Fecha` y `Hora` al **finalizar** la venta (regla 9).
- [ ] Cancelar las ventas `enproceso` huérfanas al abrir una venta nueva y al cerrar la caja.

**Caja** (`cashRegisterRoute.php`, `caja.php`, `Caja.jsx`)
- [ ] Obtener la caja activa de la base de datos (`cajaAbierta()`) en vez de `$_SESSION` (B8).
- [ ] Tomar `ID_Usuario` de la sesión, no del cuerpo de la petición.
- [ ] Permitir $0 al abrir y al cerrar (B4).
- [ ] **El cuadre suma solo efectivo** (B11) y compara redondeando (B1).
- [ ] La pantalla de Caja y el resumen del corte muestran el **desglose por forma de pago**.
- [ ] Arreglar la consulta de "Último corte" (B6): la caja **cerrada** más reciente.

**Productos**
- [ ] Permitir crear productos con 0 existencias (B5).
- [ ] Rechazar cantidades y precios negativos.

**Seguridad** (RNF03, RNF04, RL01)
- [ ] Exigir sesión en los 8 endpoints de lectura que hoy están abiertos.
- [ ] `getUserInfo?id=` solo para el admin; el empleado solo puede ver su propia información.
- [ ] CORS: aceptar únicamente los orígenes conocidos y `file://`.
- [ ] Quitar `htmlspecialchars` de las entradas (§1.4, B9).
- [ ] En la app empaquetada, **generar la contraseña de la base de datos al azar** en el primer arranque y guardarla en `userData`, en lugar de dejarla escrita en `main.js`.

**Otros**
- [ ] **Regla 4:** el renderer avisa al proceso principal (por IPC) si hay caja abierta, y el evento `close` de la ventana se cancela con un diálogo de aviso.
- [ ] **RNF08:** quitar `checkEmail` por DNS (S5).
- [ ] **Pruebas:** stock insuficiente con rollback, cuadre con centavos, caja con $0, permisos por rol y ventas huérfanas.

**Listo cuando:**
- Una venta de $10.10 en efectivo, contada exacta, deja la caja **cuadrada**.
- No se puede vender más que el stock, aunque se manipule la petición.
- Un empleado no puede leer los datos de otro empleado.
- Cerrar la ventana con la caja abierta muestra un aviso y no cierra la app.

### Fase 2: Venta completa y ticket PDF · M

**Migración M002**
- [ ] `Venta.Pago_Recibido`, `Venta.Cambio` y `Venta.ID_Usuario`.
- [ ] Tabla `Configuracion` con los datos de la tienda: nombre, dirección, teléfono, RFC y mensaje del ticket.
- [ ] `Producto.Activo` para la baja lógica.
- [ ] `'Sin stock'` → `'Agotado'` (S9).

**Backend**
- [ ] `completeSale` guarda el pago recibido y el cambio (RF04), y devuelve el **folio** (`ID_Venta`) junto con el detalle.
- [ ] Nuevo `getVenta?id=` que devuelve el detalle completo para reimprimir.
- [ ] Nuevos `getConfig` y `updateConfig` (admin).
- [ ] Nuevo `deleteProduct`: baja lógica, solo admin (RF06, B7).
- [ ] Los productos inactivos no aparecen en Ventas pero se conservan en el historial.

**Frontend**
- [ ] Plantilla **TicketPDF** con:
  - datos de la tienda;
  - folio, fecha, hora y cajero;
  - productos con cantidad, precio e importe;
  - total, forma de pago, pago recibido y cambio;
  - la leyenda del supuesto S1.
- [ ] El PDF del ticket se **genera automáticamente** después de cada venta (regla 8).
- [ ] Botón "Reimprimir último ticket" en Ventas.
- [ ] Búsqueda por **nombre o categoría** (S6).
- [ ] Página **Configuración** (admin) para los datos de la tienda.

**Listo cuando:** cada venta produce un PDF con folio y aparece en `Documentos/SGT/Tickets/`, y eliminar un producto lo oculta de Ventas sin romper el historial.

### Fase 3: Surtir (reabastecimiento) · M

**Migración M003**
- [ ] `Surtir.ID_Usuario` y `Surtir.Notas`.
- [ ] `Detalle_Surtir.Precio_Unitario` se usa como **costo**.
- [ ] `Producto.Costo`, que guarda el último costo de compra para calcular márgenes.

**Backend:** `models/surtir.php` y `routes/restockRoute.php`, solo para el admin.
- [ ] `createRestock`, en una transacción:
  - insertar `Surtir` y su `Detalle_Surtir`;
  - **sumar la cantidad al inventario**;
  - recalcular el estado del producto;
  - actualizar `Producto.Costo`.
- [ ] `getAllRestocks?desde&hasta` y `getRestock?id`.

**Frontend:** página **Surtir**.
- [ ] Elegir el proveedor y agregar productos. Por defecto se listan los de ese proveedor, con opción de ver todos.
- [ ] Capturar cantidad y costo unitario por producto; el total se calcula solo.
- [ ] Botón "**Sugerir productos con stock bajo**", que precarga los productos bajo el mínimo con una cantidad sugerida.
- [ ] Lista de surtidos anteriores con su detalle.

**Listo cuando:** registrar un surtido sube la existencia, cambia el estado (por ejemplo, "Stock bajo" → "Disponible") y guarda el costo (RF05).

### Fase 4: Historial · M

**Backend**
- [ ] `getSalesHistory?desde&hasta&forma_pago&estado&pagina`.
- [ ] `getCashRegisters?desde&hasta`.
- [ ] `getCashRegisterDetail?id`: ventas del corte, totales por forma de pago y diferencia.

**Frontend:** página **Historial** con dos pestañas.
- [ ] **Ventas:** filtros por fecha, forma de pago y estado; tabla paginada; detalle en un modal; **reimprimir el ticket en PDF**.
- [ ] **Cajas:** una fila por corte con fecha, cajero, horas de apertura y cierre, monto inicial, efectivo esperado, efectivo contado, diferencia y estado (cuadrada o descuadrada). Al abrir uno se ven sus ventas.

**Listo cuando:** se puede encontrar cualquier venta o corte pasado y reimprimir su ticket.

### Fase 5: Reportes con exportación a PDF · L

**Selector de periodo:** día (con fecha), semana, mes o rango personalizado (RF11, diagramas SADT X3 e Y1).

**Backend:** `/api/reports/sales`, `/api/reports/restocks` y `/api/reports/stock`, solo para el admin.

| Reporte | Contenido |
|---|---|
| **Ventas** | Total vendido, número de ventas y ticket promedio. Desglose por forma de pago, ventas por día, top de productos (por unidades e importe), ventas canceladas y ventas por cajero. |
| **Surtidos y compras** | Total gastado y número de surtidos. Desglose por proveedor y productos surtidos (unidades y costo). |
| **Stock** | Existencias por categoría y valor del inventario (a costo y a precio de venta). **Productos en stock bajo y agotados** (regla 5) con una cantidad de pedido sugerida. |
| **Resumen** | Ventas menos compras del periodo, y utilidad bruta estimada para los productos que tienen costo. |

**Frontend**
- [ ] Página **Reportes** con pestañas y un botón **Exportar PDF** (plantilla `ReportePDF`, tamaño carta).
- [ ] Ocultar "Reporte" del menú del empleado y proteger la ruta con `RutaAdmin` (regla 5).

**Listo cuando:** el admin genera los cuatro reportes para cualquier periodo, los exporta a PDF y las cifras coinciden con el Historial.

### Fase 6: QA y entrega · M

- [ ] `docs/QA_CHECKLIST.md`: casos manuales ligados a cada RF y regla, con el resultado de cada uno.
- [ ] Probar la **actualización** de una base de datos con datos de la versión actual: las migraciones se aplican sin pérdida.
- [ ] Probar la app **empaquetada** en Windows y Linux (CI con tag `v*`), incluyendo el primer arranque sin internet.
- [ ] Quitar código muerto: `firebase/php-jwt`, las rutas comentadas y los `console.log`.
- [ ] Actualizar el README, `ANALISIS_SRS.md` (matriz final) y la sección de sprints del SRS.

**Listo cuando:** el checklist QA está en verde para los 13 RF y las 9 reglas.

### Fase 7: Opcional · requiere confirmación

- Respaldo y restauración manual de la base de datos desde la app, con el `mariadb-dump` que ya va incluido (RNF02).
- Aviso de privacidad y consentimiento al registrar empleados (RL01, LFPDPPP).
- Build para macOS (RNF05).
- Convertir el router de `index.php` en una tabla de rutas con middleware (RNF10).
- Panel de inicio con un "Resumen del día" (ventas de hoy y alertas de stock bajo).

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
