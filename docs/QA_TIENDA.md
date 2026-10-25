# Checklist QA de la tienda en línea

Verificación final (fase T5, 25-oct-2026) de la tienda contra los requisitos **R01–R20**, las condiciones normales **N1–N11** y las situaciones problemáticas **P1–P15** de [`ANALISIS_TIENDA.md`](../ANALISIS_TIENDA.md), con las decisiones **D1–D9** del equipo.

Leyenda: ✅ verificado · ➖ no aplica por una decisión del equipo (se explica en la fila)

## Cómo se verificó

| Tipo | Qué es | Cómo se corre |
|---|---|---|
| **PHPUnit** | 109 pruebas del API de la tienda contra MariaDB real (`backend/tests/Api/Tienda`), dentro de las 272 del backend. La base de datos se recrea en cada prueba. | `cd backend && vendor/bin/phpunit` (también en el CI) |
| **Vitest** | 65 pruebas de la tienda y de las utilidades compartidas: filtros de la dirección, paginación, disponibilidad, carrito, validaciones, desglose del IVA y cliente del API. | `npm test` (también en el CI) |
| **Playwright** | 18 pruebas en Chromium (`e2e/tienda`) contra la tienda levantada con Docker. Cada una falla si la página tiene errores de JavaScript **o si el navegador se conecta a cualquier sitio fuera de la tienda**. Los cambios de existencias, precios y bajas los hace un admin de SGT con su propio API, con productos creados para la prueba. | `npm run test:e2e:tienda` con la tienda en `:8080` (también en el CI) |
| **CI con Docker** | El job *Tienda (Docker)* hace `cp .env.example .env && docker compose up --build`, revisa con `curl` el API (catálogo, carrito, registro, entrega, pedido y pago) y corre las pruebas de Playwright. | En cada PR y en cada push a `main` |
| **Desde cero** | Clon nuevo del repositorio, `cp .env.example .env` y la pila de Docker: migraciones, catálogo de 180 productos, cliente de prueba, revisiones con `curl` y las 18 pruebas de Playwright en verde. | Ver *Prueba desde cero* al final |

| Prueba de Playwright | Archivo |
|---|---|
| Catálogo: N1–N4, P13 en la lista, P14 y P15 | `e2e/tienda/catalogo.spec.js` |
| Carrito: N5–N8, P1–P6, P11 y pestañas | `e2e/tienda/carrito.spec.js` |
| Cuentas: P8–P10, sesión y T-S2 | `e2e/tienda/cuentas.spec.js` |
| Compra: N9–N11, P6, P7, P8/P9 en la entrega, P12, P13, T-S4, T-S5 y T-S6 | `e2e/tienda/compra.spec.js` |

---

## Requisitos funcionales

| ID | Requisito | Cómo se cumple | Evidencia | Resultado |
|---|---|---|---|---|
| R01 | Se usa desde un navegador, sin instalar nada y sin cuenta de empleado | Con Docker la tienda queda en `http://localhost:8080` (nginx sirve la app y reenvía solo `/api/tienda/`). El catálogo es público. Los clientes tienen su propia tabla y su propia sesión (`SGT_TIENDA`), y no entran a SGT. | PHPUnit `RouterTiendaTest`, `CuentasApiTest` (sesiones separadas); CI *Tienda (Docker)*; Playwright N1 | ✅ |
| R02 | Explorar por categorías | Inicio con las 12 categorías y su número de productos, barra de categorías en todas las páginas y página de cada categoría. Las categorías desactivadas no se muestran. | PHPUnit `CatalogoApiTest`; Playwright N1, N2 | ✅ |
| R03 | Localizar productos: buscar; filtrar y ordenar | Búsqueda por palabras en nombre, descripción y categoría, sin distinguir acentos ni mayúsculas. Filtros por categoría y "Solo disponibles", orden por nombre o precio, paginación de 12. Todo vive en la dirección. | PHPUnit `CatalogoApiTest`; Vitest `filtros.test.js`; Playwright N2, N3 | ✅ |
| R04 | Página de información del producto | Nombre, descripción, categoría con enlace, precio por unidad con IVA incluido y su monto, disponibilidad y existencia. **Imagen:** ➖ D7, "sin imágenes". La descripción se captura en SGT (Inventario). | PHPUnit `CatalogoApiTest`, `ProductosTest`; Playwright N4 | ✅ |
| R05 | Elegir cuántas unidades | Selector de cantidad con −/+ en el detalle y en el carrito. | Vitest `carrito.test.js`; Playwright N5 | ✅ |
| R06 | Administrar la selección y conservarla al recargar | Carrito con cambiar, quitar y vaciar (con confirmación). Se guarda en el navegador, se conserva al recargar y se mantiene igual entre pestañas. Un carrito guardado inválido se descarta. | Vitest `carrito.test.js`; Playwright N6, N7, pestañas | ✅ |
| R07 | Existencia limitada y revalidada | No se acepta más que la existencia, contando lo que ya está en el carrito. Los agotados no se pueden agregar. El servidor revisa el carrito en cada cambio, al continuar y al confirmar; lo que cambió se ajusta y se avisa. | PHPUnit `CarritoApiTest`, `PedidosApiTest`; Playwright P1, P3, P4, T-S5 | ✅ |
| R08 | Acciones sin sentido con mensajes claros | Cantidades inválidas (P2), carrito vacío (P6), saltarse pasos (P7), doble envío (P12), producto inexistente o dado de baja (P5, P14) y precios alterados (P11). Ver la tabla de situaciones problemáticas. | Ver P2, P5–P7, P11, P12, P14 | ✅ |
| R09 | Revisión de la compra | Carrito y resumen con importe por producto, subtotal, IVA, envío gratis y total. | PHPUnit `CarritoApiTest`; Playwright N8, N9 | ✅ |
| R10 | Total con impuestos, calculado en el servidor sin errores de redondeo | El servidor calcula todo en centavos con sus precios. Los precios incluyen IVA (D2): subtotal = total ÷ 1.16 y el IVA es la diferencia, así que siempre suman el total. La tasa es configurable (`Configuracion.Tasa_IVA`). | PHPUnit `CarritoApiTest` (desglose y tasa); Vitest `money.test.js`; Playwright N8 ($110.34 + $17.66 = $128.00) y N9 | ✅ |
| R11 | Datos del cliente para identificarlo y entregar | Cuenta: nombre, apellidos, correo y teléfono (D5). Entrega: quién recibe, teléfono, calle, número exterior e interior, colonia, código postal, ciudad, entidad (lista de 32) y referencias opcionales. | PHPUnit `CuentasApiTest`, `PedidosApiTest`; Playwright N9 | ✅ |
| R12 | Validación en navegador y servidor, con un mensaje por campo | Las mismas reglas en `cuenta/validaciones.js`, `compra/validaciones.js`, `models/cliente.php` y `models/entrega.php`. El API responde `errores: {campo: mensaje}` y la tienda lo muestra en cada campo. | PHPUnit `CuentasApiTest`, `PedidosApiTest`; Vitest `validaciones.test.js` (cuenta y compra); Playwright P8–P10 | ✅ |
| R13 | Resumen final antes de pagar, con opción de corregir | Productos, cantidades, importes, IVA, total y datos de entrega. **Corregir datos** y **Modificar carrito**. | Playwright N9, N10 | ✅ |
| R14 | Pantalla de pago que avisa que el pago no está disponible | Métodos (tarjeta, SPEI, efectivo en tienda) sin campos de tarjeta. **Pagar** responde "El pago en línea todavía no está disponible. No se hizo ningún cargo…". El servidor no contacta a nadie (D6). | PHPUnit `PedidosApiTest`; Playwright N11 (sin campos de tarjeta y **sin conexiones fuera de la tienda en todas las pruebas**) | ✅ |
| R15 | Todo el flujo anterior al pago funciona | Explorar, carrito, cuenta, entrega, resumen y confirmación, que crea el pedido "pendiente de pago" con folio sin descontar existencias (D4). "Mis pedidos" permite regresar al pago. | Playwright: flujo completo en `compra.spec.js`; CI con `curl` | ✅ |

## Requisitos de desarrollo

| ID | Requisito | Cómo se cumple | Evidencia | Resultado |
|---|---|---|---|---|
| R16 | Análisis antes de programar | Análisis de SGT contra el enunciado, requisitos, condiciones y decisiones del equipo antes de la primera línea de la tienda. El plan por fases tiene criterios de "listo". | [`ANALISIS_TIENDA.md`](../ANALISIS_TIENDA.md), [`PLAN_TIENDA.md`](../PLAN_TIENDA.md) | ✅ |
| R17 | Organización interna en partes evaluables por separado | **Tienda:** `src/tienda/` (catálogo, carrito, cuenta, compra, componentes, api y hooks). **Compartido:** `src/compartido/`. **Backend:** router `backend/routes/tienda/` con un archivo por ruta y modelos `Catalogo`, `Carrito`, `Cliente`, `Entrega` y `Pedido`. Cada parte tiene sus pruebas. | Estructura del repo; README, *Estructura del proyecto* | ✅ |
| R18 | Persistencia | MariaDB con migraciones versionadas (005 y 006 para la tienda). Productos, clientes y pedidos se guardan en la base de datos y Docker conserva el volumen. El carrito vive en el navegador (T-S3) y el servidor lo revisa. | PHPUnit `ActualizacionTest`, `SeederTest` | ✅ |
| R19 | Conjunto de datos amplio | 180 productos en 12 categorías, con descripción, precios y existencias variadas: cada categoría tiene agotados y productos con pocas piezas. Hay un cliente de prueba. | PHPUnit `CatalogoTiendaTest` | ✅ |
| R20 | Alcance pequeño | Sin facturación, seguimiento de envíos, IA ni pagos reales. Los proveedores siguen en SGT y no se muestran en la tienda. | Revisión del código; el API público no expone costos, proveedores ni mínimos (`CatalogoApiTest`) | ✅ |

---

## Condiciones normales

| # | Situación | Resultado | Evidencia |
|---|---|---|---|
| N1 | Entra a la tienda | Ve 12 categorías y 12 destacados (ninguno agotado), sin iniciar sesión | `catalogo.spec.js` |
| N2 | Elige una categoría | 12 productos por página (15 en Abarrotes: 12 + 3), con precio y disponibilidad | `catalogo.spec.js` |
| N3 | Busca "azucar" | Encuentra "Azúcar estándar 1 kg" | `catalogo.spec.js`, `CatalogoApiTest` |
| N4 | Abre un producto | Nombre, descripción, categoría, $34.00 con IVA incluido ($4.69) y disponibilidad. Sin imagen por D7 | `catalogo.spec.js` |
| N5 | Agrega 3 piezas de un producto con 10 | "Agregaste 3 piezas al carrito" y el encabezado dice 3 piezas | `carrito.spec.js` |
| N6 | Cambia a 5 y quita otro producto | Importes y totales se actualizan | `carrito.spec.js` |
| N7 | Recarga | El carrito sigue igual | `carrito.spec.js` |
| N8 | Revisa la compra | Importe por producto, subtotal, IVA (16 %), envío gratis y total | `carrito.spec.js` |
| N9 | Llena la entrega | Pasa al resumen con productos, totales y datos de entrega | `compra.spec.js` |
| N10 | "Corregir datos" | Regresa al formulario con sus datos y vuelve al resumen corregido | `compra.spec.js` |
| N11 | Pulsa "Pagar" | "El pago en línea todavía no está disponible. No se hizo ningún cargo…"; el pedido sigue pendiente | `compra.spec.js`, `PedidosApiTest` |

## Situaciones problemáticas

| # | Situación | Resultado | Evidencia |
|---|---|---|---|
| P1 | Más piezas que la existencia | "Solo hay 10 disponibles" (y "…ya tienes 3 en tu carrito" al agregar más); en el carrito no se aplica | `carrito.spec.js`, `CarritoApiTest` |
| P2 | Cantidad 0, negativa, decimal o texto | Mensaje específico; no se aplica y al salir del campo regresa a la cantidad anterior | `carrito.spec.js`, `carrito.test.js`, `CarritoApiTest` |
| P3 | Producto agotado | Botón "Agotado" deshabilitado, sin selector de cantidad | `carrito.spec.js` |
| P4 | La existencia baja con el producto en el carrito | Aviso y cantidad ajustada al revisar; al confirmar, no se crea el pedido y se avisa (T-S5) | `carrito.spec.js`, `compra.spec.js`, `PedidosApiTest` |
| P5 | Baja o cambio de precio después de agregarlo | Aviso "ya no está a la venta" o "el precio cambió de… a…"; el total usa el precio vigente | `carrito.spec.js`, `CarritoApiTest` |
| P6 | Continuar con el carrito vacío | No hay botón para continuar; se muestra un mensaje con enlace a la tienda; los pasos de la compra regresan al carrito; el API responde 409 | `carrito.spec.js`, `compra.spec.js`, `PedidosApiTest` |
| P7 | Entrar directo a entrega, resumen o pago | Sin sesión → Ingresar (y regresa); sin productos → carrito; resumen sin entrega → entrega; el API responde 401 o 409 | `compra.spec.js`, `PedidosApiTest` |
| P8 | Campos vacíos o solo con espacios | Mensaje en cada campo y se enfoca el primero; no avanza | `cuentas.spec.js`, `compra.spec.js`, `CuentasApiTest`, `PedidosApiTest` |
| P9 | Correo, teléfono o código postal con formato incorrecto | Mensaje específico por campo | `cuentas.spec.js`, `compra.spec.js`, `CuentasApiTest`, `PedidosApiTest` |
| P10 | Textos largos, con HTML o comillas | Nombres y números con HTML o comillas dobles se rechazan; la calle con comillas o `<>` se guarda y se muestra como texto; los largos se rechazan | `cuentas.spec.js`, `CuentasApiTest`, `PedidosApiTest` |
| P11 | Precio o cantidad alterados en el navegador | El servidor usa sus precios, avisa el cambio y no confirma sin que el cliente lo vea; las cantidades inválidas responden 400 | `carrito.spec.js`, `CarritoApiTest`, `PedidosApiTest` |
| P12 | Doble clic en "Confirmar" o "Pagar" | Un solo pedido: la clave de envío se repite y el servidor regresa el mismo folio; pagar no cambia nada | `compra.spec.js`, `PedidosApiTest` |
| P13 | Atrás o Adelante en medio del flujo | Lista y búsqueda en la dirección; la entrega conserva los datos; Atrás desde el pago lleva al carrito vacío sin repetir el pedido | `catalogo.spec.js`, `compra.spec.js` |
| P14 | `/producto/99999` | "Producto no encontrado" (también `/producto/abc` y categorías inexistentes) | `catalogo.spec.js`, `CatalogoApiTest` |
| P15 | El API no responde | Cada sección avisa y tiene un botón **Reintentar**; el resto de la página sigue visible | `catalogo.spec.js`, `tienda.test.js` |

---

## Decisiones del equipo

| # | Decisión | Cómo quedó |
|---|---|---|
| D1 | Módulo nuevo dentro de SGT | Mismo backend y base de datos. La tienda es una app aparte (`src/tienda`) |
| D2 | Precios con IVA incluido | Se desglosa en el carrito, el resumen y el pago |
| D3 | Envío sin costo | "Envío: Gratis" en todos los totales |
| D4 | Pedido "pendiente de pago" con folio, sin apartar existencias | `Pedido.Estado = pendiente_pago`; las existencias no cambian |
| D5 | Compra con registro | Explorar y armar el carrito sin cuenta; para continuar se pide ingresar o registrarse |
| D6 | Pago: solo métodos y botón "Pagar" con aviso | Sin campos de tarjeta ni conexión externa |
| D7 | Sin imágenes | El detalle no muestra imagen (R04 se cumple sin ella por esta decisión) |
| D8 | Se evalúa con `docker compose up` en un equipo | Ver *Prueba desde cero* |
| D9 | Sin versión para celular | El diseño es de escritorio |

## Hallazgos durante la verificación

| Hallazgo | Estado |
|---|---|
| Al confirmar, vaciar el carrito hacía que la guarda del resumen llevara al carrito en lugar del pago | Corregido en T4 |
| Concordancia de los mensajes en plural ("Los apellidos solo *pueden*…", "Las referencias no *pueden*…") | Corregido en T3 y T4 |
| SGT (no la tienda): `sanitize` del renderer (DOMPurify) convertía en HTML el texto que tuviera un `<`: "Pan & Cía <grande>" se guardaba como "Pan &amp; Cía " y se veía así en SGT y en la tienda, y un usuario con `<` en su contraseña no podía iniciar sesión | Corregido después de T5: el texto se guarda tal cual y la migración 007 regresa los datos ya guardados a su texto original |

## Prueba desde cero

En una carpeta nueva, con Docker como único requisito:

```bash
git clone https://github.com/T4R1N256/SGT-QA.git sgt-qa && cd sgt-qa
cp .env.example .env
docker compose up --build -d
# la tienda: http://localhost:8080   (cliente de prueba: cliente@ejemplo.mx / prueba123)
npm ci && npx playwright install chromium && npm run test:e2e:tienda   # opcional: las 18 pruebas
```

Se hizo de dos formas:

- **En el CI, en cada PR:** el job *Tienda (Docker)* parte de un runner limpio y hace exactamente lo anterior, con los `Dockerfile` reales: `docker compose up --build`, revisiones con `curl` y las pruebas de Playwright.
- **En local, el 25-oct-2026:** clon nuevo del commit de esta fase en una carpeta vacía, `cp .env.example .env` y `docker compose up` con un proyecto nuevo (volumen de base de datos vacío). Las compilaciones de Docker de ese entorno no tienen red, así que las imágenes se armaron con los mismos pasos de los `Dockerfile`: `composer install --no-dev` y `vite build` hechos afuera, y las mismas imágenes base (`php:8.5.5-cli`, `nginx:1.27-alpine`) con el mismo `nginx.conf`.

| Verificación (local, clon nuevo) | Resultado |
|---|---|
| El backend aplica las migraciones 001–006, crea el admin y carga 12 categorías, 180 productos y el cliente de prueba | ✅ |
| Las revisiones con `curl` del CI (catálogo, carrito, registro, entrega, pedido y pago; el API de SGT responde 404 por el `8080`) | ✅ |
| Las 18 pruebas de Playwright contra `:8080` (nginx) y `:8001` (backend) | ✅ |
| Sin errores fatales en el registro del backend | ✅ |
