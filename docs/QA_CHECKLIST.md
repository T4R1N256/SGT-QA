# Checklist QA de SGT

Resultado de la verificación final (fase 6, 25-oct-2026) contra el **SRS v1.1**: los 13 requisitos funcionales, las 9 reglas de negocio y los requisitos no funcionales.

Leyenda: ✅ verificado · ⏳ pendiente de verificar en el entorno indicado · — fuera de alcance acordado

## Cómo se verificó

| Tipo | Qué es | Cómo se corre |
|---|---|---|
| **PHPUnit** | 159 pruebas del API contra MariaDB real (`backend/tests`). La base de datos se recrea en cada prueba. | `cd backend && vendor/bin/phpunit` (también en el CI) |
| **Vitest** | 60 pruebas del frontend: dinero, fechas, búsqueda, armado del ticket y de los reportes, PDF. | `npm test` (también en el CI) |
| **E2E** | 75 verificaciones en la app de Electron compilada, manejada con Playwright: ventas, caja, ticket, surtir, historial y reportes, incluido el texto de los PDF generados. | Scripts de QA de cada fase (no están en el repo) |
| **Empaquetada** | 34 verificaciones de la app de Linux empaquetada con los binarios reales de PHP y MariaDB, **sin red** y como usuario normal. | Script de QA de la fase 6 (no está en el repo) |
| **CI de instaladores** | `build.yml` compila los instaladores de Linux y Windows y arranca la app de Linux (smoke test). | En los PRs que cambian el empaquetado, con un tag `v*` o a mano |

---

## Requisitos funcionales

| ID | Requisito | Casos verificados | Evidencia | Resultado |
|---|---|---|---|---|
| RF01 | Iniciar sesión | Credenciales correctas e incorrectas, usuario inexistente, ID no numérico, cerrar sesión, rutas sin sesión responden 401 | PHPUnit `AutenticacionTest`, `SeguridadTest`; E2E en todas las fases | ✅ |
| RF02 | Registrar ventas | Productos, cantidades, fecha y hora al cobrar; total recalculado con los precios de la BD; venta atómica (si un producto falla no se guarda nada); productos repetidos se suman | PHPUnit `VentasTest` (14); E2E fases 2, 4 y 5 | ✅ |
| RF03 | Buscar productos | Por nombre o categoría, sin distinguir acentos ni mayúsculas; los agotados no se ofrecen para vender | Vitest `productos.test.js`; E2E fase 2 | ✅ |
| RF04 | Registrar pago | Pago recibido y cambio en efectivo; pago insuficiente se rechaza; con tarjeta o transferencia se cobra el total exacto | PHPUnit `TicketTest`; Vitest `money.test.js`; E2E fase 2 | ✅ |
| RF05 | Actualizar inventario tras compra y venta | La venta descuenta y el surtido suma; el estado (Disponible, Stock bajo, Agotado) se recalcula; se guarda el costo de compra | PHPUnit `VentasTest`, `SurtidoTest`; E2E fase 3 | ✅ |
| RF06 | Registrar y eliminar productos | Alta con 0 existencias; texto guardado tal cual; eliminar es baja lógica y el producto ya no se vende, pero su historial y ticket se conservan | PHPUnit `ProductosTest`, `TicketTest`; E2E fase 2 | ✅ |
| RF07 | Modificar productos | Recalcula el estado; rechaza negativos; 404 si no existe | PHPUnit `ProductosTest` | ✅ |
| RF08 | Consultar productos | Lista y detalle con sesión; sin sesión, 401 | PHPUnit `SeguridadTest`; E2E fases 2 y 3 | ✅ |
| RF09 | Abrir caja | Se abre con $0; no se abren dos cajas; quien abre es el usuario de la sesión; la caja abierta sigue disponible en otra sesión | PHPUnit `CajaTest`; E2E fases 4 y 5 | ✅ |
| RF10 | Cerrar caja | Cuadra con centavos; solo cuenta el efectivo; descuadrada con su diferencia; registra quién cerró; cancela la venta abandonada | PHPUnit `CajaTest`; E2E fases 4 y 5 | ✅ |
| RF11 | Generar reportes (día, semana, mes) | Ventas, surtidos y compras, stock y resumen para cualquier periodo; exportación a PDF tamaño carta | PHPUnit `ReportesTest` (17); Vitest `reportes.test.js`, `ReportePDF.test.jsx`, `fechas.test.js`; E2E fase 5 | ✅ |
| RF12 | Cancelar venta | Vaciar el carrito cancela la venta; una venta abandonada se cancela al abrir otra o al cerrar la caja; las canceladas no tienen ticket ni suman | PHPUnit `VentasTest`, `CajaTest`, `TicketTest`; E2E fase 4 | ✅ |
| RF13 | Gestión de usuarios | Alta (inicia sesión sin permisos de admin), modificación de datos y contraseña, baja lógica que impide iniciar sesión, reactivación; el empleado no gestiona usuarios ni ve datos de otros | PHPUnit `UsuariosTest`, `SeguridadTest` | ✅ |

## Reglas de negocio

| # | Regla | Casos verificados | Evidencia | Resultado |
|---|---|---|---|---|
| 1 | No vender sin stock suficiente | El servidor rechaza vender más que la existencia, con bloqueo de filas (`FOR UPDATE`) | PHPUnit `VentasTest::testNoSeVendeMasQueLaExistencia` | ✅ |
| 2 | Sin caja abierta no hay ventas | Responde error y la pantalla de Ventas se bloquea | PHPUnit `VentasTest::testSinCajaAbiertaNoHayVentas` | ✅ |
| 3 | El empleado no modifica productos ni inventario | Crear, modificar, eliminar y surtir responden 403 al empleado; Inventario y Surtir no aparecen en su menú | PHPUnit `ProductosTest`, `SurtidoTest`, `AutenticacionTest` | ✅ |
| 4 | El sistema no se cierra con la caja abierta | La ventana de Electron no se cierra con la caja abierta y sí con la caja cerrada; cerrar sesión también se bloquea | E2E regla 4 | ✅ |
| 5 | Solo el dueño hace reportes de stock bajo o agotado | Los 4 reportes responden 403 al empleado y 401 sin sesión; Reportes no aparece en su menú y la ruta lo regresa al inicio | PHPUnit `ReportesTest`; E2E fase 5 | ✅ |
| 6 | El inventario no puede ser negativo | Restricción `CHECK` en la BD; la API rechaza negativos; las existencias negativas de versiones anteriores pasan a 0 | PHPUnit `SeederTest`, `ProductosTest`, `ActualizacionTest` | ✅ |
| 7 | Productos bien definidos | Campos obligatorios, tipos y rangos validados en el backend | PHPUnit `ProductosTest::testDatosInvalidosResponden400` | ✅ |
| 8 | Ticket después de cada venta | PDF de 80 mm con tienda, folio, cajero, productos, total, pago y cambio; reimpresión desde Ventas (F9) y desde Historial | PHPUnit `TicketTest`; Vitest `TicketPDF.test.jsx`; E2E fases 2 y 4 | ✅ |
| 9 | Fecha y hora en todas las ventas | Fecha y hora se guardan juntas al cobrar | PHPUnit `VentasTest::testFechaYHoraSeGuardanAlCompletar` | ✅ |

## Requisitos no funcionales, legales y técnicos

| ID | Requisito | Casos verificados | Evidencia | Resultado |
|---|---|---|---|---|
| RNF01 | Fácil de configurar | Primer arranque de la app empaquetada sin pasos manuales: inicializa MariaDB, crea la BD y el admin | Empaquetada; smoke test del CI | ✅ |
| RNF02 | Integridad de los datos | Dinero en `DECIMAL`; ventas y surtidos atómicos; migraciones; **actualización desde la versión anterior sin perder registros**; apagado ordenado de MariaDB; recuperación tras un cierre forzado | PHPUnit `ActualizacionTest`, `SeederTest`, `MigracionesTest`; Empaquetada | ✅ |
| RNF03 | Acceso con credenciales | Todas las rutas de lectura exigen sesión; las de admin responden 403 al empleado | PHPUnit `SeguridadTest`, `AutenticacionTest` | ✅ |
| RNF04 / RL01 | Seguridad de datos personales | El empleado no ve datos de otros; CORS solo para los orígenes de la app; **MariaDB con contraseñas al azar**, sin usuarios anónimos, solo en 127.0.0.1 y sin contraseñas en la línea de comandos | PHPUnit `SeguridadTest`; Empaquetada | ✅ (el aviso de privacidad queda en la fase 7) |
| RNF05 | Cualquier sistema operativo | Linux: instalador compilado y app probada. Windows: instalador compilado en el CI | Empaquetada; CI de instaladores | ✅ Linux · ⏳ Windows: probar el instalador en un equipo Windows real · — macOS (fase 7) |
| RNF06 / RNF09 | Usabilidad | Atajos de teclado en Ventas; mensajes de error en español; avisos claros si la app no puede arrancar | E2E | ✅ |
| RNF07 | Disponibilidad | Todo corre en el equipo, sin servidores externos | Empaquetada | ✅ |
| RNF08 | Funciona sin internet | La app empaquetada arranca, vende y reporta **sin red**; ya no se valida el correo por DNS | Empaquetada (`unshare -n`); PHPUnit `SeguridadTest::testLaValidacionDeCorreoPorInternetYaNoExiste` | ✅ |
| RNF10 | Escalabilidad | Migraciones versionadas, pruebas automatizadas y CI | CI | ✅ (el router como tabla de rutas queda en la fase 7) |
| RT01–RT05 | MySQL/MariaDB, PHP, Electron + React, Tailwind + Vite, Git | — | — | ✅ |
| — | Pruebas y QA | Pruebas automatizadas en el CI (lint, Vitest, PHPUnit) y este checklist | CI | ✅ |

## App empaquetada (Linux, sin red, usuario normal)

| Caso | Resultado |
|---|---|
| Primer arranque: contraseñas al azar para root y la app (archivo con permisos 600), MariaDB y PHP en puertos libres, sin usuarios anónimos ni base `test`, ninguna contraseña en la lista de procesos | ✅ |
| Funciona aunque el equipo tenga otro MySQL o MariaDB instalado con su propio `my.cnf` (antes no arrancaba) | ✅ |
| Cierre normal: PHP y MariaDB se apagan; el siguiente arranque conserva los datos y las contraseñas | ✅ |
| Cierre forzado (`kill -9`): el siguiente arranque cierra el PHP y la MariaDB que quedaron vivos y los datos siguen | ✅ |
| Se borra `credenciales-bd.json`: se restablece root y los datos siguen | ✅ |
| Instalación de la versión anterior (root sin contraseña, puerto 3307, datos viejos): se apaga la MariaDB anterior, se aseguran las cuentas, se aplican las migraciones y los usuarios anteriores inician sesión | ✅ |

## Corregido después de la fase 6

| Hallazgo | Corrección | Evidencia |
|---|---|---|
| Los formularios de SGT pasaban el texto por DOMPurify (`sanitize`). Con un `<` en el texto lo devolvía como HTML y borraba lo que pareciera etiqueta: "Pan & Cía <grande>" se guardaba como "Pan &amp; Cía ". La pantalla de Login también lo aplicaba a la contraseña, así que un usuario con `<` en su contraseña no podía entrar. | El texto solo se recorta (`utils/texto.js`) y se guarda tal cual; React lo escapa al mostrarlo. Se quitó DOMPurify. La migración 007 regresa `&amp;`, `&lt;`, `&gt;` y `&nbsp;` a su texto original en las columnas que llenan esos formularios (lo que DOMPurify borró no se puede recuperar). | PHPUnit `TextoEscapadoTest`; Vitest `texto.test.js`; E2E en la app compilada: entrar con la contraseña `Clave&2025<x>` y guardar "Pan & Cía <grande>" y "Frutas & Verduras <3" |

### Categorías y proveedores (QA de SGT, 26-oct-2026)

Las rutas de Categorías y Proveedores no tenían pruebas. Al revisarlas por el API y en la app se encontraron estos problemas; las decisiones son del equipo.

| Hallazgo | Corrección | Evidencia |
|---|---|---|
| Se aceptaban categorías y proveedores con un nombre que ya existía ("Abarrotes" dos veces) | Se rechazan con 409 y el mensaje "Ya existe una categoría llamada «…»", sin distinguir mayúsculas ni acentos, también contra las inactivas (el mensaje sugiere volver a activarla). Un repetido de antes no impide cambiar el estado o el teléfono. | PHPUnit `CategoriasTest`, `ProveedoresTest`; E2E en la app compilada |
| El teléfono del proveedor aceptaba cualquier texto (`hola`) | Opcional y, si se escribe, de 10 o 12 dígitos: la misma regla que Usuarios, en la pantalla y en el API | PHPUnit `ProveedoresTest`; Vitest `catalogos.test.js`; E2E |
| Desactivar una categoría con productos activos no avisaba: la tienda en línea los ocultaba y SGT los seguía vendiendo | El API la bloquea (409, con cuántos productos activos tiene) hasta que el admin confirma. La pantalla avisa que la tienda en línea dejará de mostrarlos y pide "Desactivar de todas formas", desde el botón y desde Editar | PHPUnit `CategoriasTest`; Vitest `catalogos.test.js`; E2E |
| Las pantallas de Categorías y Proveedores mostraban "—" en Estado y Productos, el estado elegido en Editar no se guardaba y una categoría o proveedor desactivado desaparecía sin forma de volver a activarlo | El API manda el estado y los productos activos, y con `?todas=1` / `?todos=1` también los inactivos. Editar guarda el estado, así que se pueden volver a activar. Inventario y Surtir siguen ofreciendo solo los activos. | PHPUnit `CategoriasTest`, `ProveedoresTest`; E2E |

## Pendiente

| Qué | Dónde | Cómo |
|---|---|---|
| Instalar y abrir el instalador de Windows (`SGT-1.0.0-win.exe`) del CI | Un equipo con Windows 10 u 11, de preferencia sin internet | Instalar, abrir, iniciar sesión como admin, abrir caja, vender, imprimir el ticket, cerrar caja y exportar un reporte. Cerrar y volver a abrir: los datos siguen. |
