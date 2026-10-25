# Plan: tienda en línea sobre SGT

> Basado en [`ANALISIS_TIENDA.md`](ANALISIS_TIENDA.md): requisitos R01–R20, criterios N1–N11 y P1–P15, y las decisiones D1–D9 del equipo.
> Mismo flujo que con SGT: cada fase va en su rama del fork, entra a `main` con un PR, y no se fusiona sin el CI en verde.

---

## 0. Decisiones y supuestos

### Decisiones del equipo

| # | Decisión |
|---|---|
| D1 | La tienda es un **módulo nuevo** dentro de SGT, con el mismo backend y la misma base de datos. |
| D2 | Los **precios ya incluyen IVA**; la tienda lo desglosa. |
| D3 | El **envío no se cobra**. |
| D4 | El pedido se **guarda como "pendiente de pago"**, con folio y sin apartar existencias. |
| D5 | **Compra con registro**: el cliente necesita una cuenta. |
| D6 | Pago: **solo los métodos y el botón "Pagar"**, que avisa que el pago no está disponible. |
| D7 | **Sin imágenes.** |
| D8 | Se evalúa con **`docker compose up` en un equipo**. |
| D9 | No se pide versión para **celular**. |

### Supuestos míos (corríjanme si alguno está mal)

| # | Supuesto | Alternativa si no aplica |
|---|---|---|
| T-S1 | Una sola tasa de **IVA del 16 %** para todos los productos, configurable. El desglose se calcula sobre el total, en centavos: subtotal = total ÷ 1.16 y el IVA es la diferencia. | Una tasa por producto (16 % o 0 %). |
| T-S2 | Se puede **explorar y armar el carrito sin cuenta**. Al continuar con la compra se pide iniciar sesión o registrarse, y el carrito se conserva. | Pedir cuenta desde que se agrega el primer producto. |
| T-S3 | El carrito vive **en el navegador** (`localStorage`). El servidor lo revisa en cada paso: existencias, precios vigentes y productos dados de baja. | Guardar el carrito en la base de datos para usarlo en varios equipos. |
| T-S4 | Al registrarse se pide **nombre, apellidos, correo (único), teléfono y contraseña** (8 caracteres o más, con letras y números). La **dirección de entrega** se captura en cada compra y se prellena con la última usada. | Guardar varias direcciones en la cuenta. |
| T-S5 | Al **confirmar el pedido** se vuelven a validar las existencias. Si ya no alcanzan, no se crea el pedido y se le dice al cliente qué ajustar. Los pedidos pendientes **no descuentan existencias** (D4). | Apartar existencias por un tiempo. |
| T-S6 | La cuenta del cliente tiene una página **"Mis pedidos"** con sus pedidos pendientes de pago. Desde ahí puede volver a la pantalla de pago. | Que solo se vea el folio al confirmar. |
| T-S7 | Los clientes son **independientes de los empleados**: tienen su propia tabla y su propia sesión, y nunca acceden a SGT. | — |
| T-S8 | El catálogo de la tienda son los **productos activos de SGT**. El admin les agrega una **descripción** desde Inventario. | — |
| T-S9 | El catálogo de prueba tiene unos **180 productos en 12 categorías**, con descripción y existencias variadas, incluidos agotados y con pocas piezas. Se carga solo con Docker. | — |
| T-S10 | Con Docker la tienda queda en **`http://localhost:8080`**, en el mismo origen que su API. SGT de escritorio sigue funcionando igual. | Otro puerto. |

---

## 1. Decisiones técnicas

### 1.1 Organización (R17)

```
src/
  tienda/                 App web de la tienda (React + Vite, independiente de Electron)
    api/                  Llamadas al API de la tienda
    carrito/              Estado del carrito (reducer puro + localStorage)
    compra/               Pasos: entrega, resumen, pago
    cuenta/               Registro, inicio de sesión, mis pedidos
    catalogo/             Inicio, categoría, búsqueda, detalle
    componentes/          Componentes de la tienda
  compartido/             Dinero, fechas, búsqueda sin acentos y validadores, usados por SGT y por la tienda
  renderer/               SGT de escritorio (sin cambios, salvo la descripción del producto)
backend/
  routes/tienda/          Rutas públicas y de cliente: /api/tienda/...
  models/                 Cliente, Pedido y Catálogo (nuevos), junto a los de SGT
  tests/Api/Tienda/       Pruebas del API de la tienda
```

### 1.2 API de la tienda

Todas las rutas van bajo `/api/tienda/`, con un router propio y con **sesión separada** de la de los empleados (otro nombre de cookie). Las rutas públicas nunca exponen costos, proveedores ni existencias mínimas.

| Ruta | Acceso | Para qué |
|---|---|---|
| `GET categorias` | Pública | Categorías activas, con cuántos productos tiene cada una |
| `GET productos?q&categoria&disponibles&orden&pagina` | Pública | Catálogo con búsqueda, filtros, orden y paginación |
| `GET producto?id` | Pública | Detalle de un producto |
| `POST carrito/revisar` | Pública | Recibe el carrito del navegador. Regresa las líneas con el precio vigente, los avisos (agotado, ajustado, dado de baja) y el desglose de subtotal, IVA y total |
| `POST registro`, `POST ingresar`, `POST salir`, `GET cuenta` | Pública o con sesión de cliente | Cuentas de cliente |
| `POST pedidos` | Cliente | Crea el pedido "pendiente de pago" a partir del carrito y los datos de entrega. Todo se valida en el servidor |
| `GET pedidos`, `GET pedido?folio` | Cliente | Mis pedidos y el detalle de uno |
| `POST pedido/pagar` | Cliente | Siempre responde que el pago **no está disponible**. No contacta a ningún proveedor |

### 1.3 Base de datos (migraciones 005 y 006)

- `Producto.Descripcion` (texto).
- `Configuracion.Tasa_IVA`, que vale 16.00.
- **`Cliente`**: nombre, apellidos, correo (único), teléfono, contraseña con hash, fecha de registro y activo.
- **`Pedido`**:
  - folio único, cliente, fecha y hora
  - subtotal, IVA y total
  - estado `pendiente_pago`
  - copia de los datos de entrega: quién recibe, teléfono, calle, número exterior e interior, colonia, código postal, ciudad, entidad y referencias
- **`Detalle_Pedido`**: producto, cantidad, precio unitario e importe. El precio se copia al confirmar.

### 1.4 Docker (D8)

`docker compose up --build` levanta tres contenedores:
- **db:** MariaDB.
- **backend:** PHP. Aplica las migraciones y carga el catálogo de la tienda.
- **web:** compila la tienda con Node y la sirve con nginx en `:8080`. Reenvía `/api/` al backend.

El equipo del profesor no necesita Node, PHP ni MariaDB: solo Docker.

### 1.5 Pruebas

| Tipo | Qué cubre |
|---|---|
| **PHPUnit** | Catálogo, revisión del carrito, impuestos, registro e inicio de sesión, pedidos, pago no disponible y todos los casos inválidos del API |
| **Vitest** | Reducer del carrito, cálculo del desglose, validadores del formulario y guardas de los pasos |
| **E2E en el navegador (Playwright, en el repo)** | Flujo completo N1–N11 y situaciones P1–P15 contra la tienda en Docker |
| **CI** | Lo anterior, más la compilación de la imagen web |

---

## 2. Fases

### Fase T0: Base del módulo · M
- [ ] Mover las utilidades compartidas a `src/compartido/` (SGT sigue igual).
- [ ] App `src/tienda/` con su configuración de Vite, su layout (encabezado con búsqueda, categorías, carrito y cuenta) y rutas vacías.
- [ ] Router `/api/tienda/` con sesión separada.
- [ ] Migraciones 005 y 006.
- [ ] Contenedor `web` en `docker-compose`.
- [ ] Catálogo de prueba amplio (T-S9).
- [ ] CI: compilar la tienda y la imagen.

**Listo cuando:** `docker compose up --build` muestra la tienda vacía en `:8080` y el API responde.

### Fase T1: Catálogo · M (R02–R04, R19)
- [ ] API: categorías, productos (búsqueda sin acentos, filtros, orden, paginación) y detalle.
- [ ] Páginas: inicio (categorías y algunos productos), categoría, resultados de búsqueda y detalle de producto. El detalle muestra nombre, descripción, categoría, precio con IVA incluido y disponibilidad: Disponible, Pocas piezas o Agotado.
- [ ] Producto inexistente (P14) y errores del API (P15).
- [ ] SGT: campo "Descripción" en Inventario.

**Listo cuando:** se cumplen N1–N4, P14 y P15.

### Fase T2: Carrito · M (R05–R07, R09, R10)
- [ ] Reducer del carrito con `localStorage`: agregar con cantidad, cambiar, quitar y vaciar.
- [ ] `carrito/revisar` en el servidor: precios vigentes, existencias, bajas y desglose de IVA (T-S1).
- [ ] Página del carrito con importes, subtotal, IVA, total y "Envío: gratis" (D3).
- [ ] Mensajes de existencia y cantidades inválidas (P1–P5).

**Listo cuando:** se cumplen N5–N8 y P1–P6.

### Fase T3: Cuentas de cliente · M (R11, R12, D5)
- [ ] Registro, inicio y cierre de sesión, con validación por campo en el navegador y en el servidor. El correo no se puede repetir.
- [ ] Al continuar la compra sin sesión, se pide ingresar o registrarse y se regresa al flujo con el carrito intacto (T-S2).
- [ ] Página "Mi cuenta".

**Listo cuando:** registrarse, entrar y salir funciona, con todos los datos inválidos rechazados (P8–P10).

### Fase T4: Compra y pago · L (R08, R11–R15)
- [ ] Paso de **datos de entrega**: formulario validado, prellenado con la última dirección usada; la entidad se elige de una lista de 32.
- [ ] **Resumen final** con productos, cantidades, importes, subtotal, IVA, total y datos de entrega. Permite corregir los datos o el carrito.
- [ ] **Confirmar**: crea el pedido "pendiente de pago" con folio, vuelve a validar existencias (T-S5) y evita el doble envío (P12).
- [ ] **Pantalla de pago**:
  - métodos de pago (tarjeta, transferencia, pago en tienda, etc.) sin campos de tarjeta
  - botón "Pagar" que muestra que el pago en línea todavía no está disponible
  - ninguna conexión externa
- [ ] **Guardas de los pasos** (P6, P7, P13) y **"Mis pedidos"** (T-S6).

**Listo cuando:** se cumplen N9–N11 y P6–P13.

### Fase T5: QA y entrega · M (R16, R17)
- [ ] E2E en el navegador de N1–N11 y P1–P15, en el repo y en el CI.
- [ ] `docs/QA_TIENDA.md`: cada requisito R01–R20 y cada criterio, con su evidencia y resultado.
- [ ] README de la tienda: cómo levantarla con Docker, cuentas de prueba y cómo correr las pruebas.
- [ ] Prueba desde cero en un equipo limpio: `git clone` y `docker compose up --build`.

**Listo cuando:** el checklist de la tienda está en verde para R01–R20.

---

## 3. Orden

T0 → T1 → T2 → T3 → T4 → T5. T3 (cuentas) puede hacerse en paralelo con T1 y T2, porque solo comparten la base del módulo.
