# Análisis del repositorio SGT

> Análisis de la rama `main` de [EduardGarrido/SGT](https://github.com/EduardGarrido/SGT) en el commit `c052740`.
> El fork [T4R1N256/SGT-QA](https://github.com/T4R1N256/SGT-QA) está en el mismo commit, así que este análisis también le aplica.

---

## 1. ¿Qué es?

**SGT** es una aplicación de escritorio para gestionar **inventario, ventas y caja** de un negocio pequeño, tipo punto de venta. Tiene unas 8,000 líneas de código propio, y la interfaz y los comentarios están en español.

### Módulos funcionales

| Módulo | Página (frontend) | Backend |
|---|---|---|
| Login / sesión | `Login.jsx` | `authRoute.php`, `models/login.php` |
| Dashboard | `Dashboard.jsx` | `getCashRegister`, `getAllVentas` |
| Ventas (POS) | `Sales.jsx` | `saleRoute.php`, `models/venta.php` |
| Caja (abrir y cerrar, cuadre) | `Caja.jsx` | `cashRegisterRoute.php`, `models/caja.php` |
| Inventario / productos | `Inventory.jsx` | `productRoute.php`, `models/producto.php` |
| Categorías | `Categorias.jsx` | `categoryRoute.php`, `models/categoria.php` |
| Proveedores | `Proveedores.jsx` | `supplierRoute.php`, `models/proveedor.php` |
| Usuarios / empleados (admin) | `Users.jsx`, `UserInfo.jsx` | `usuarioRoute.php`, `checkEmailRoute.php`, `models/usuario.php`, `empleado.php`, `contacto_empleado.php` |
| Historial y Reportes | solo un aviso de "Próximamente" | — |

---

## 2. Tecnologías

### Escritorio / frontend

- **Electron 41**, con `contextIsolation` activado y `nodeIntegration` desactivado.
- **electron-vite 5** y **Vite** para compilar el proceso principal, el preload y el renderer.
- **React 19** y **React Router 7** (usa `HashRouter`, necesario para cargar con `file://`).
- **Tailwind CSS 4** y **DaisyUI 5**, junto con `clsx`, `tailwind-merge` y **Heroicons**.
- **DOMPurify** para sanear entradas (`utils/sanitize.js`).
- Herramientas de calidad: **ESLint 9** (con plugins de React y hooks) y **Prettier**.

### Backend

- **PHP 8.5 sin framework**: servidor integrado `php -S` con un router en un único archivo, `index.php`.
- **PDO MySQL** con consultas preparadas.
- Composer:
  - `vlucas/phpdotenv` para cargar el `.env`.
  - `firebase/php-jwt`, que está **instalado pero no se usa**: la autenticación funciona con sesiones PHP.
- **MariaDB** (12.2.2 en Docker y CI).

### Infraestructura

- **Docker Compose** con dos servicios: `db` (MariaDB en el puerto 3307) y `backend` (PHP en el 8001). El esquema `database.sql` y el seeder se aplican automáticamente.
- **electron-builder** genera los instalables: NSIS y `.zip` en Windows, `.tar.gz` en Linux, `.dmg` en macOS.
- **GitHub Actions** (`.github/workflows/build.yml`): al crear una etiqueta `v*` compila Linux y Windows. Descarga los binarios de PHP y MariaDB de una release llamada `binaries`. macOS está desactivado.

---

## 3. Arquitectura

```
┌─────────────────────────── App Electron ───────────────────────────┐
│  Proceso principal (src/main/main.js)                               │
│   ├─ arranca mariadbd embebido  (solo en la app empaquetada, :3307) │
│   ├─ arranca php -S localhost:8000  (siempre)                       │
│   ├─ ejecuta seeder.php (crea el admin)                             │
│   └─ abre BrowserWindow                                             │
│                                                                     │
│  Renderer (React SPA)  ──fetch + cookie de sesión──►  API PHP       │
│   pages → hooks/useFetch → api/api.js        index.php (router)     │
│   context: Auth, Notification, Header          ├─ routes/*Route.php │
│                                                 └─ models/*.php     │
│                                                      │ PDO          │
│                                                      ▼              │
│                                                 MariaDB (sgtdb)     │
└─────────────────────────────────────────────────────────────────────┘
```

### Estilo general

Cliente-servidor local, de 3 capas (presentación → API → datos), empaquetado como una app de escritorio **autónoma**. El usuario final no necesita instalar PHP ni MariaDB porque ambos binarios van dentro de `resources/`. Los datos de MariaDB se guardan en `userData/mysql_data`, de modo que sobreviven a las actualizaciones de la app.

### Backend: MVC ligero sin vistas

- `index.php` es el **front controller**: maneja CORS y la sesión, y tiene un `switch` grande que asocia cada ruta a un archivo `routes/*.php` y aplica los guardias `requerirAutorizacion()` o `requerirAdmin()`.
- `routes/` funciona como capa de controladores: valida el método HTTP, lee el JSON de entrada y responde con `{ ok, mensaje, ... }`.
- `models/` contiene clases con **métodos estáticos**, una por tabla, que ejecutan las consultas PDO (patrón tipo *Table Gateway*).
- `config/database.php` define la clase `Conexion extends PDO` y carga el `.env`.
- La API es de estilo **RPC sobre HTTP** (`/api/createProduct`, `/api/modifyProduct`, ...), no REST basado en recursos.

### Frontend: SPA por capas

- `api/api.js` es la única capa de acceso HTTP. Si recibe un 401, emite el evento `sesion-expirada`.
- `hooks/useFetch.js` gestiona la carga, los errores y el refetch.
- `context/` maneja el estado global:
  - `AuthContext` guarda el usuario y el estado de la caja en `sessionStorage`.
  - `NotificationContext` muestra las alertas.
  - `HeaderContext` controla el encabezado de cada página.
- `App.jsx` define las rutas, con guardias `RutaAdmin` y `RutaPublica` y `AppLayout` como contenedor común.
- `components/` agrupa la interfaz reutilizable (modales, barras, botones) y `utils/` las funciones de formato, validación y saneado.

### Roles

`admin` y empleado. El rol se toma de `Empleado.Puesto` y se guarda en `$_SESSION`.

### Base de datos (11 tablas)

`Usuario`, `Empleado`, `Contacto_Empleado`, `Categoria`, `Proveedor`, `Producto`, `Caja`, `Venta`, `Detalle_Venta`, `Surtir`, `Detalle_Surtir`.

- El flujo central es **Caja → Venta → Detalle_Venta → Producto**: no se puede vender sin una caja abierta y cada venta descuenta inventario.
- `Surtir` y `Detalle_Surtir` (reabastecimiento) existen en el esquema, pero todavía no tienen rutas.

### Estructura de carpetas

```
src/
  main/         Proceso principal de Electron y preload
  renderer/     App React (Vite)
    api/        Wrapper de fetch
    components/ Componentes UI reutilizables
    context/    Estado global (Auth, Notification, Header)
    hooks/      useFetch
    pages/      Páginas por ruta
    utils/      Formato, validación y saneado
backend/        PHP 8 sin framework — router único en index.php
  config/       Conexión PDO, esquema database.sql y seeder
  routes/       Controladores por módulo
  models/       Clases de consultas PDO
.github/        Workflow de build multiplataforma
```

---

## 4. Problemas encontrados

1. **No hay pruebas automatizadas**, ni en el frontend ni en el backend, y el CI solo compila.
2. **La transacción de `completeSale` no es atómica.** `Venta::completeVenta()` y `Producto::readProducto()` abren su propia `new Conexion()` en vez de usar la conexión de la transacción. Si falla el descuento de inventario, el `rollBack` no revierte el cambio de la venta a "finalizada".
3. **Faltan rutas.** `api.js` llama a `deleteProduct`, pero `index.php` no tiene esa ruta, así que devuelve 404.
4. **Rutas sin autenticación:** `getCashRegister`, `getAllVentas`, `getProduct`, `getAllProducts`, `getCategory`, `getAllCategories`, `getSupplier` y `getAllSuppliers`.
5. **Credenciales inconsistentes:** el README dice que la contraseña del admin es `passadministrador`, pero `seeder.php` crea `passadmin`.
6. **Hay secretos en el código:** la contraseña de la base de datos empaquetada está escrita directamente en `src/main/main.js` (aunque esa base solo escucha en 127.0.0.1).
7. **El CORS acepta un `Origin` vacío** y responde `*` junto con credenciales. Lo hacen para que funcione con `file://`, pero es permisivo.
8. **`htmlspecialchars` se aplica a la entrada**, incluso a la contraseña en el login. Es un escape de salida usado en el lugar equivocado y puede romper contraseñas que contengan `&`, `<`, etc.
9. **Código muerto:** la dependencia `firebase/php-jwt` sin usar, rutas comentadas (`addProductSale` / `removeProductSale`) y un `console.log` en `useFetch`.
10. **Router monolítico:** `index.php` tiene unas 40 entradas `case` repetidas. Un mapa de rutas a manejadores con middleware lo simplificaría.

### Prioridad sugerida

Los más urgentes son el **2** (integridad de datos en ventas), el **3** (funcionalidad rota) y el **5** (no se puede entrar con las credenciales documentadas).
