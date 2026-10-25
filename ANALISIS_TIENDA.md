# SGT frente al enunciado "Tienda a realizar"

> Comparación del enunciado del profesor (tienda en línea para clientes) contra el código de `main` (commit `88146cd`).
> Leyenda: ✅ cumple · ⚠️ parcial (hay algo reutilizable, pero no cumple del todo) · ❌ no existe

---

## 1. Resumen

SGT es un **punto de venta de escritorio** (Electron) para el personal de una tienda física. El empleado inicia sesión, abre caja, busca productos y cobra. El enunciado pide algo distinto: una **tienda en línea para clientes**, usada desde un navegador, sin personal de por medio. El flujo esperado es:

> Explorar la tienda → seleccionar productos → administrar la compra → datos del cliente → revisar la compra → llegar al pago (que no funciona)

**Lo que sí se puede reutilizar de SGT:**
- El catálogo con categorías y existencias.
- La validación de datos en el servidor.
- El cálculo de dinero en centavos.
- La base de datos con migraciones.
- La organización por capas.
- Las pruebas automatizadas.

**Lo que no existe:** toda la parte pública. Falta:
- Catálogo, búsqueda y detalle de producto para clientes.
- Carrito del cliente.
- Desglose de impuestos.
- Datos de entrega y su validación.
- Resumen final.
- Pantalla de pago deshabilitada.
- Forma de usar la tienda como sitio web.

| Bloque | Cumple | Parcial | Falta |
|---|---|---|---|
| Requisitos funcionales (R01–R15) | 0 | 7 | 8 |
| Requisitos de desarrollo (R16–R20) | 3 | 2 | 0 |

---

## 2. Qué debe hacer la tienda (análisis del enunciado)

El enunciado no trae una especificación formal. Estos son los requisitos que se derivan de él y contra los que se evalúa SGT.

### Funcionales

| ID | Requisito | De dónde sale |
|---|---|---|
| R01 | Se usa desde un **navegador**, sin instalar nada y **sin cuenta de empleado**. | "a través de un navegador" |
| R02 | El catálogo se **explora por categorías**. | "organizados por categorías", "explorar la tienda" |
| R03 | El cliente puede **localizar productos**: buscar por texto. Filtrar y ordenar es recomendable. | "localizar productos que les interesen" |
| R04 | Cada producto tiene una **página o vista de información**: nombre, descripción, precio, categoría, disponibilidad e imagen. | "consultar información sobre ellos" |
| R05 | El cliente **selecciona productos y elige cuántas unidades**. | "decidir cuántas unidades desean adquirir" |
| R06 | El cliente **administra su selección**: ver el carrito, cambiar cantidades, quitar productos y vaciarlo. La selección se conserva si recarga la página. | "administrar los productos seleccionados" |
| R07 | **Existencia limitada**: no se aceptan cantidades mayores a la existencia y se avisa claramente. Los agotados no se pueden agregar. Las existencias se vuelven a validar antes de continuar, porque pueden cambiar. | "existencia limitada… comportamiento coherente" |
| R08 | **Acciones sin sentido** se manejan con mensajes claros: <ul><li>cantidad 0, negativa, decimal o texto</li><li>continuar con el carrito vacío</li><li>saltarse pasos</li><li>enviar dos veces</li><li>producto inexistente o dado de baja</li><li>precios alterados desde el navegador</li></ul> | "acciones que no tengan sentido dentro del proceso de compra" |
| R09 | **Revisión de la compra**: importe por producto, subtotal, impuestos y total. | "revisar su compra… cuánto está comprando y cuánto deberá pagar" |
| R10 | El total **considera los impuestos** (IVA) de forma explícita y se calcula en el servidor, sin errores de redondeo. | "el cálculo deberá considerar el precio de los productos y los impuestos" |
| R11 | **Datos del cliente** para identificarlo y entregarle el pedido: <ul><li>nombre</li><li>correo</li><li>teléfono</li><li>dirección: calle, número, colonia, código postal, ciudad y estado</li><li>referencias (opcional)</li></ul> | "información necesaria para identificarlo y realizar posteriormente la entrega" |
| R12 | **Validación** de esos datos: campos obligatorios, formato (correo, teléfono de 10 dígitos, código postal de 5 dígitos) y longitudes. Hay un mensaje por campo y la validación se hace en el navegador y en el servidor. | "información incorrecta, insuficiente o que no tenga el formato esperado" |
| R13 | **Resumen final antes de pagar**: productos, cantidades, importes, impuestos, total y datos de entrega. Se puede regresar a corregir. | "resumen final de su compra…" |
| R14 | **Pantalla de pago** que se puede alcanzar. Al intentar pagar, la aplicación indica claramente que el pago **no está disponible**. No hay conexión con bancos, tarjetas ni pasarelas, y no se capturan ni guardan datos reales de tarjeta. | "El módulo de pago" |
| R15 | **Todo el flujo anterior al pago funciona**. | "Todo el proceso anterior al pago sí deberá funcionar" |

### De desarrollo

| ID | Requisito | De dónde sale |
|---|---|---|
| R16 | **Análisis antes de programar**: qué se espera del sistema, comportamiento normal y situaciones problemáticas. | "Antes de comenzar a programar deberán establecer…" |
| R17 | **Organización interna** en partes que se puedan analizar y evaluar por separado; nada de un solo archivo o componente. | "suficiente organización interna…" |
| R18 | **Persistencia** de la información de los productos. | "algún mecanismo para conservar la información" |
| R19 | Un **conjunto de datos amplio**, que permita usar la tienda de forma realista. | "conjunto de datos suficientemente amplio" |
| R20 | **Alcance pequeño**. Quedan fuera la facturación, el seguimiento de envíos, la administración de proveedores, la IA y los pagos reales. | "Alcance esperado" |

---

## 3. Estado de SGT frente a cada requisito

| ID | Estado | Qué hay hoy / qué falta |
|---|---|---|
| R01 | ❌ | SGT es una app de escritorio. Su interfaz solo corre en un navegador en modo desarrollo (Vite) y **todas las pantallas exigen iniciar sesión como empleado o admin**. El API solo acepta los orígenes `localhost:5173`, `localhost:8000` y el de la app de Electron (CORS). `docker-compose` levanta la base de datos y el API, pero no publica ninguna interfaz web. |
| R02 | ⚠️ | Hay categorías (7 en los datos de demostración) y cada producto tiene la suya. No existe una vista para que el cliente navegue por categoría, y `getAllCategories` / `getAllProducts` **exigen sesión**. |
| R03 | ⚠️ | Hay búsqueda por nombre o categoría, sin distinguir acentos (`utils/productos.js`), pero solo en la pantalla de Ventas del cajero y con un máximo de 8 resultados. No hay búsqueda para clientes, ni filtros (precio, disponibilidad), ni orden, ni paginación. |
| R04 | ❌ | `Producto` solo guarda nombre, precio, costo, existencia, unidad, estado, mínimo, categoría y proveedor. **No hay descripción, imagen ni marca**, ni página de detalle. |
| R05 | ⚠️ | El carrito del punto de venta (`Sales.jsx`) agrega de uno en uno y permite escribir la cantidad. Pero es del cajero, no del cliente, y no hay forma de elegir la cantidad antes de agregar. |
| R06 | ⚠️ | En el punto de venta se pueden cambiar cantidades, quitar productos y limpiar el carrito. Pero el carrito vive en memoria (**se pierde al recargar**) y no existe un carrito por cliente. |
| R07 | ⚠️ | **Servidor:** valida la existencia al cobrar, con bloqueo de filas (regla 1), y la base rechaza existencias negativas. **Punto de venta:** no deja pasar de la existencia, pero **lo ignora en silencio**, sin avisar. **Falta:** <ul><li>mensaje claro al cliente</li><li>validar existencias otra vez en la revisión y antes del pago</li><li>avisar si un producto del carrito se agotó o cambió de precio</li></ul> |
| R08 | ⚠️ | **Ya hay:** el API rechaza cantidades inválidas (`campoEntero`), recalcula el total con los precios de la base de datos (no confía en el cliente) y rechaza productos dados de baja. **Falta, porque no existe el flujo por pasos:** <ul><li>impedir continuar con el carrito vacío</li><li>impedir entrar a la revisión o al pago sin haber pasado por los pasos anteriores</li><li>evitar el doble envío</li><li>manejar el botón Atrás y la recarga</li></ul> |
| R09 | ❌ | No hay revisión de compra para el cliente. El punto de venta solo muestra el total al cajero. |
| R10 | ❌ | Los precios **ya incluyen impuestos** y no se desglosan (supuesto S1 de `PLAN.md`; el ticket dice "Precios con impuestos incluidos"). No hay tasa de IVA ni cálculo de impuestos en ninguna parte. Lo que sí hay: dinero en `DECIMAL` y suma en centavos. |
| R11 | ❌ | No existen las entidades **Cliente** ni **Pedido**, ni datos de entrega. `Venta` está ligada a una caja y a un empleado, no a un cliente. |
| R12 | ⚠️ | Hay una capa de validación reutilizable en el servidor (`config/validar.php`: obligatorios, longitudes, enteros, dinero, fechas y opciones) y validadores en el frontend (`utils/validators.js`). Faltan las reglas del cliente (correo, teléfono, código postal, nombre, dirección), el formulario y los mensajes por campo. |
| R13 | ❌ | No existe un resumen final de la compra. |
| R14 | ❌ | No hay pantalla de pago ni el mensaje de "pago no disponible". SGT no se conecta con ningún banco ni pasarela; las formas de pago del punto de venta (efectivo, tarjeta, transferencia) solo se registran como un dato. Eso cumple el "sin conexión real", pero faltan la interfaz y el aviso. |
| R15 | ❌ | El flujo de la tienda no existe. |
| R16 | ⚠️ | Hay análisis de SGT contra su propio SRS (`ANALISIS_SRS.md`, `PLAN.md`, `docs/QA_CHECKLIST.md`), pero no contra este enunciado. Este documento es el punto de partida; faltan los criterios de aceptación de la tienda (sección 5). |
| R17 | ✅ | El backend está por capas (`routes/`, `models/`, `config/`) y el frontend por páginas, componentes y utilidades. Hay pruebas por módulo: 159 de PHPUnit y 60 de Vitest. La tienda debe seguir esa organización, en módulos propios. |
| R18 | ✅ | MariaDB con esquema y migraciones versionadas. |
| R19 | ⚠️ | Hay **32 productos** de demostración en 7 categorías, y solo se cargan con `SEED_DEMO=1`. No tienen descripciones ni imágenes. Para usar la tienda "de forma realista" conviene tener 150–200 productos en 10–12 categorías, con descripción, imagen y existencias variadas: algunos agotados y algunos con pocas piezas. |
| R20 | ✅ | El alcance de la tienda es pequeño. SGT trae funciones que el enunciado no pide (caja, surtir, proveedores, reportes); no estorban si la tienda es un módulo aparte, pero tampoco cuentan para la evaluación. |

---

## 4. Lo que falta (lista accionable)

**Acceso web (R01)**
1. Una interfaz pública de la tienda que funcione en el navegador sin iniciar sesión.
2. Rutas públicas de solo lectura en el API: catálogo, categorías y detalle de producto. No deben exponer costos, proveedores ni existencias mínimas.
3. Una forma de publicar la tienda para las pruebas: un contenedor web en `docker-compose` que sirva la interfaz compilada en el mismo origen que el API, sin depender de Electron.
4. Que la tienda se vea bien en pantallas de distintos tamaños. Hoy la interfaz de SGT es casi solo de escritorio.

**Catálogo (R02–R04, R19)**

5. Navegación por categorías.
6. Búsqueda por texto, con filtros (categoría, solo disponibles) y orden (nombre, precio), con paginación.
7. Página de detalle del producto.
8. Una migración que agregue `Descripcion` e `Imagen` al producto.
9. Un catálogo de datos amplio y realista (150–200 productos), cargado por el seeder.

**Carrito (R05–R07)**

10. Agregar con cantidad, cambiar cantidades, quitar, vaciar y ver el total en todo momento.
11. Que el carrito se conserve al recargar o cerrar la pestaña (en el navegador), siempre validado contra el servidor.
12. Mensajes claros cuando se pide más de lo disponible, el producto está agotado o el producto cambió (precio, existencia o baja) desde que se agregó.

**Flujo de compra y casos inválidos (R08, R15)**

13. Pasos de la compra: carrito → datos de entrega → resumen → pago. No se puede entrar a un paso sin haber completado el anterior.
14. Bloquear continuar con el carrito vacío y evitar el doble envío.
15. Validar en el servidor todo el pedido antes de crearlo: productos, cantidades, existencias, precios vigentes e impuestos.

**Impuestos y totales (R09, R10)**

16. Una tasa de IVA configurable (global o por producto) y el desglose de subtotal, IVA y total, calculado en el servidor en centavos.
17. Costo de envío, si se decide cobrarlo.

**Datos del cliente (R11, R12)**

18. Un formulario de datos de entrega con validación por campo, en el navegador y en el servidor.
19. Reglas de formato: correo, teléfono de 10 dígitos, código postal de 5 dígitos, longitudes y campos con solo espacios.

**Resumen y pago (R13, R14)**

20. Un resumen final con productos, cantidades, importes, impuestos, total y datos de entrega, con opción de regresar a corregir.
21. Una pantalla de pago con métodos de pago (sin capturar datos reales de tarjeta). Al pulsar "Pagar" aparece un mensaje claro: *"El pago en línea todavía no está disponible"*. No hay ninguna conexión con proveedores de pago.
22. Opcional: guardar el pedido como "pendiente de pago", con folio, para poder revisarlo después.

**Pruebas y documentación (R16, R17)**

23. Los criterios de aceptación de la tienda: la sección 5 de este documento, convertida en casos de prueba.
24. Pruebas automatizadas de cada parte: cálculo de impuestos, validación de datos, existencias, API de la tienda y flujo completo en el navegador.

---

## 5. Comportamiento esperado y situaciones problemáticas

Esta es la propuesta de criterios de aceptación de la tienda. Cada fila se puede convertir en una prueba.

### Condiciones normales

| # | Situación | Resultado esperado |
|---|---|---|
| N1 | El cliente entra a la tienda | Ve las categorías y productos destacados sin iniciar sesión |
| N2 | Elige una categoría | Ve sus productos, paginados, con precio y disponibilidad |
| N3 | Busca "azucar" (sin acento) | Encuentra "Azúcar estándar 1 kg" |
| N4 | Abre un producto | Ve nombre, imagen, descripción, precio, categoría y disponibilidad |
| N5 | Agrega 3 piezas de un producto con 10 en existencia | El carrito muestra 3 piezas y el total se actualiza |
| N6 | Cambia la cantidad a 5 y quita otro producto | El carrito y los totales se actualizan |
| N7 | Recarga la página | El carrito sigue igual |
| N8 | Continúa a la revisión | Ve el importe por producto, el subtotal, el IVA y el total |
| N9 | Llena sus datos de entrega correctamente | Pasa al resumen final con productos, totales y datos de entrega |
| N10 | En el resumen elige "Corregir datos" | Regresa al formulario con sus datos y vuelve al resumen |
| N11 | Llega al pago y pulsa "Pagar" | Ve el aviso de que el pago en línea todavía no está disponible. No se cobra nada |

### Situaciones problemáticas

| # | Situación | Resultado esperado |
|---|---|---|
| P1 | Pide más piezas que la existencia | No se acepta. Mensaje: "Solo hay N disponibles" |
| P2 | Cantidad 0, negativa, decimal o texto | No se acepta. Se conserva la cantidad válida anterior con un mensaje |
| P3 | Intenta agregar un producto agotado | El botón está deshabilitado y la etiqueta dice "Agotado" |
| P4 | La existencia baja mientras el producto está en su carrito | Al revisar o confirmar se le avisa y se ajusta o se le pide corregir |
| P5 | El producto se da de baja o cambia de precio después de agregarlo | Se le avisa en la revisión. El total usa el precio vigente |
| P6 | Intenta continuar con el carrito vacío | No puede. Mensaje y enlace a la tienda |
| P7 | Entra directo a la dirección de datos, resumen o pago sin pasos previos | Se le regresa al paso que le falta |
| P8 | Deja campos obligatorios vacíos o solo con espacios | Mensaje en cada campo. No avanza |
| P9 | Correo, teléfono o código postal con formato incorrecto | Mensaje específico por campo |
| P10 | Textos demasiado largos o con HTML o comillas | Se rechazan por longitud o se guardan y muestran como texto, sin ejecutarse |
| P11 | Altera el precio o la cantidad desde las herramientas del navegador | El servidor recalcula con sus datos y rechaza lo inválido |
| P12 | Hace doble clic en "Confirmar" o "Pagar" | No se duplica nada |
| P13 | Usa Atrás o Adelante del navegador en medio del flujo | El flujo sigue siendo coherente |
| P14 | Producto inexistente en la dirección (`/producto/99999`) | Página de "Producto no encontrado" |
| P15 | El API no responde | Mensaje de error y opción de reintentar, sin pantalla en blanco |

---

## 6. Decisiones que necesitamos antes de programar

| # | Pregunta | Recomendación |
|---|---|---|
| **D1** | ¿La tienda es un **módulo nuevo dentro de SGT** (mismo backend y base de datos, con SGT como back office para el catálogo) o un proyecto aparte? | Módulo nuevo. Se reutiliza el catálogo, la validación, la base de datos y las pruebas, y SGT sirve para administrar productos. |
| **D2** | **IVA**: ¿los precios ya incluyen IVA y se desglosa, o el precio es sin IVA y se le suma el 16 %? ¿Hay productos con tasa 0 % (alimentos básicos)? | Precio sin IVA más tasa por producto (16 % o 0 %), con desglose. Es lo realista en México y prueba bien el cálculo. |
| **D3** | **Envío**: ¿se cobra? ¿Tarifa fija, gratis desde cierto monto o no aplica? | Tarifa fija (por ejemplo $99) y gratis desde $999, como dato configurable. |
| **D4** | **Pedido**: ¿se guarda en la base de datos como "pendiente de pago"? ¿Se apartan existencias? | Guardarlo con folio, sin descontar existencias, porque el pago nunca se completa. |
| **D5** | **Cuenta de cliente**: ¿registro e inicio de sesión, o compra como invitado? | Como invitado. El enunciado solo pide los datos para identificar y entregar. |
| **D6** | **Pantalla de pago**: ¿mostrar un formulario de tarjeta simulado o solo los métodos y el botón "Pagar"? | Métodos de pago y botón "Pagar", sin campos de tarjeta, para no manejar datos sensibles. |
| **D7** | **Imágenes**: ¿fotos reales o ilustraciones generadas? | Ilustraciones generadas por categoría (SVG locales), sin depender de internet ni de derechos de imagen. |
| **D8** | ¿**Cómo la va a probar el profesor**: URL pública, `docker compose up` en su equipo o el instalador? | `docker compose up` con un contenedor web y la tienda en `http://localhost:8080`. |
| **D9** | ¿Se pide que funcione en **celular**? | Que sea adaptable (responsive) aunque no se pida explícitamente. |
