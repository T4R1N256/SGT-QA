# SGT

Aplicación de escritorio para gestión de inventario y ventas, construida con **Electron + React** (frontend) y **PHP + MySQL** (backend).

Incluye una **tienda en línea** para clientes que se abre en el navegador (ver *Tienda en línea*). El plan está en [`PLAN_TIENDA.md`](PLAN_TIENDA.md).

---

## Requisitos previos

| Herramienta | Versión |
|-------------|---------|
| Node.js | 18 + |
| npm | 9 + |
| Docker + Docker Compose | cualquier versión reciente |
| PHP (solo sin Docker o para pruebas) | 8.3 + con `pdo_mysql`, `pdo_sqlite` y `curl` |
| Composer (solo sin Docker o para pruebas) | 2 + |

Docker es la forma más sencilla de levantar el backend. También se puede usar PHP instalado localmente.

---

## Ejecución en desarrollo

### 1. Instalar dependencias del frontend

```bash
npm install
```

### 2. Configurar el `.env`

```bash
cp .env.example .env
```

Editar `.env` según el modo elegido:

**Con Docker (recomendado):** los valores por defecto de `.env.example` funcionan sin cambios. Levantar los contenedores:

```bash
docker-compose up
```

Esto inicia MariaDB en el puerto `3307` y el servidor PHP en el puerto `8001`. El esquema y el seeder se aplican automáticamente.

**Sin Docker (requiere MariaDB instalado localmente):**

1. Crear la base de datos y el usuario en MySQL:

```sql
CREATE DATABASE sgtdb;
CREATE USER 'sgt_root'@'localhost' IDENTIFIED BY 'tu_contraseña';
GRANT ALL PRIVILEGES ON sgtdb.* TO 'sgt_root'@'localhost';
FLUSH PRIVILEGES;
```

2. Importar el esquema base e instalar las dependencias de PHP:

```bash
mysql -u sgt_root -p sgtdb < backend/config/database.sql
composer install --working-dir=backend
```

3. Ajustar el `.env`:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=sgt_root
DB_PASS=tu_contraseña
VITE_API_URL=http://localhost:8000/api
```

### 3. Iniciar la aplicación Electron

```bash
npm run dev
```

Electron arranca un servidor PHP en el puerto `8000` con el PHP del sistema, ejecuta el seeder y abre la ventana. Los cambios en el renderer se recargan en caliente.

- **API:** en desarrollo la app usa la URL de `VITE_API_URL`. Con Docker es el backend del contenedor (`8001`); sin Docker, el PHP local (`8000`).
- **Con Docker no hace falta PHP en el equipo:** si no está instalado, la app abre igual y usa el backend del contenedor.

### Credenciales por defecto

| Campo | Valor |
|-------|-------|
| ID de usuario | `1` |
| Contraseña | `passadmin` |

El seeder crea esta cuenta automáticamente en el primer arranque si no existe.

### Tickets de venta

Cada venta genera su ticket en PDF (80 mm) en `Documentos/SGT/Tickets/ticket-<folio>.pdf` y lo abre con el visor del sistema para imprimirlo. Si el sistema no tiene carpeta Documentos, se guarda en `<carpeta personal>/SGT/Tickets`. Los datos de la tienda que aparecen en el ticket se editan en **Catálogo → Configuración** (solo admin). En el navegador, sin Electron, el ticket se abre en una ventana nueva.

### Reportes

En **Reportes** (solo admin) se consultan las ventas, los surtidos y compras, el stock y un resumen del periodo (día, semana, mes o rango). **Exportar PDF** guarda el reporte en tamaño carta en `Documentos/SGT/Reportes/reporte-<tipo>-<periodo>.pdf` y lo abre. La utilidad del resumen se estima con el último costo de compra de cada producto, que se registra en **Surtir**.

### Datos de demostración

Con `SEED_DEMO=1` en el `.env`, el seeder carga 7 categorías, 6 proveedores y 32 productos (en los estados disponible, stock bajo y sin stock) y un usuario empleado. Solo se cargan si la tabla de productos está vacía.

| Usuario | ID | Contraseña |
|---|---|---|
| Empleado Demo | `2` | `empleado123` |

El ID del empleado es `2` en una base de datos nueva. Si ya había usuarios, el seeder imprime el ID que le tocó.

---

## Tienda en línea

Módulo web para clientes, con el mismo backend y la misma base de datos que SGT. Con Docker no hace falta Node, PHP ni MariaDB en el equipo:

```bash
cp .env.example .env
docker compose up --build
```

La tienda queda en **http://localhost:8080**. La primera vez el backend aplica las migraciones y, con `SEED_TIENDA=1` (valor por defecto de `.env.example`), carga un catálogo de prueba de 12 categorías y 180 productos. El catálogo solo se carga si la tabla de productos está vacía.

| Contenedor | Qué hace |
|---|---|
| `db` | MariaDB |
| `backend` | PHP: el API de SGT y el de la tienda (`/api/tienda/...`), en el `8001` |
| `web` | Compila la tienda y la sirve con nginx en el `8080`. Reenvía `/api/tienda/` al backend; el resto del API de SGT no se expone |

Para trabajar en la tienda con recarga en caliente, con el backend de Docker levantado:

```bash
npm run dev:tienda       # http://localhost:5174, reenvía /api al backend del 8001
```

Para usar otro backend: `TIENDA_API=http://localhost:8000 npm run dev:tienda`.

---

## Probar la app instalada (sin compilar)

Las versiones publicadas, como `v1.0.0`, tienen sus instaladores en la página **Releases** del repositorio. Además, cada ejecución del workflow **Build** deja los instaladores como artefactos durante 14 días: *Actions → Build →* la ejecución más reciente en verde *→ Artifacts*.

| Sistema | Artefacto | Cómo abrirla |
|---|---|---|
| Windows 10/11 | `sgt-win` | Ejecutar `SGT-1.0.0-win.exe` (instalador) o descomprimir `SGT-1.0.0-win.zip` y abrir `SGT.exe` |
| Linux x64 | `sgt-linux` | Descomprimir `SGT-1.0.0-linux.tar.gz` y ejecutar `./sgt` dentro de la carpeta |

No necesita PHP, MariaDB ni internet. El primer arranque tarda unos segundos más mientras prepara la base de datos. Las credenciales iniciales están en *Credenciales por defecto*. La base empieza vacía, sin datos de demostración.

- **Windows:** el instalador no está firmado, así que SmartScreen puede mostrar "Windows protegió tu PC". Elegir *Más información → Ejecutar de todas formas*.
- **Linux:** si no abre y en la terminal aparece un error del *sandbox* (pasa en Ubuntu 24.04 y otras distribuciones que restringen los espacios de nombres), ejecutar `./sgt --no-sandbox`.

---

## Base de datos y migraciones

`backend/config/database.sql` es el **esquema base**. Cada cambio posterior al esquema es un archivo en `backend/config/migrations/` y se aplica solo en el siguiente arranque, tanto en Docker como en Electron. Las reglas están en [`backend/config/migrations/README.md`](backend/config/migrations/README.md).

---

## Compilar una versión distribuible

Los binarios de PHP y MariaDB deben colocarse en `resources/` antes de compilar (están en el `.gitignore`). El CI los descarga del release `binaries` del repositorio original (`EduardGarrido/SGT`); para compilar en local, descargar los mismos archivos de ese release:

- **PHP 8.5** (`php-linux.tar.gz`, `php-win.zip`) → extraer en `resources/php/{linux,win}/`
- **MariaDB 12.2** (`mysql-linux.tar.gz`, `mysql-win.zip`) → extraer en `resources/mysql/{linux,win}/`

Las dependencias de PHP se instalan sin las de desarrollo: `composer install --no-dev --working-dir=backend`. El instalador no incluye las partes de MariaDB que la app no usa (pruebas, motores RocksDB/Mroonga/Spider, Galera, mariabackup); los filtros están en `extraResources` de `package.json`.

Luego compilar para la plataforma deseada:

```bash
npm run dist:win    # Windows — instalador NSIS + .zip (ejecutar en Windows)
npm run dist:linux  # Linux   — .tar.gz (ejecutar en Linux)
npm run dist:mac    # macOS   — .dmg (ejecutar en macOS)
```

El resultado se genera en `release/`. La app empaquetada es completamente autónoma: no requiere PHP ni MariaDB en la máquina del usuario final, y funciona sin internet.

### Cómo arranca la app empaquetada

- **Base de datos:** MariaDB se inicializa en la carpeta de datos del usuario (`~/.config/sgt/mysql_data` en Linux, `%APPDATA%\sgt\mysql_data` en Windows). Escucha solo en `127.0.0.1`, en un puerto libre.
- **Contraseñas:** se generan al azar en el primer arranque para `root` y para el usuario de la app (`sgt_root`). Se guardan en `credenciales-bd.json`, en la misma carpeta, solo legible por el usuario. No hay contraseñas escritas en el código.
- **Actualización desde una versión anterior:** se le pone contraseña a root, se quitan los usuarios anónimos y la base `test`, y `seeder.php` aplica las migraciones pendientes. Los datos se conservan.
- **Otro MySQL o MariaDB en el equipo:** no afecta, porque la app no lee el `my.cnf` / `my.ini` del sistema.
- **PHP:** corre en un puerto libre, que el preload le pasa al renderer (`window.electronAPI.apiURL`).
- **Cierre:** al cerrar la app, MariaDB se apaga de forma ordenada.

**Si algo falla:**
- **Procesos que quedaron vivos.** Si la app se cerró de forma forzada, el siguiente arranque cierra el PHP y la MariaDB que hayan quedado vivos.
- **Credenciales borradas.** Si se borra `credenciales-bd.json`, la app restablece la contraseña de root al arrancar.
- **La app no puede arrancar.** Muestra un aviso con el motivo y se cierra.

### Instaladores en el CI

`build.yml` compila los instaladores de Linux (`.tar.gz`) y Windows (`.exe` y `.zip`) y los deja como artefactos del workflow. Al terminar arranca la app de Linux como prueba (smoke test). Corre en tres casos:
- al crear una etiqueta `v*`. En este caso, además, publica un **release de GitHub** con los instaladores, que no caducan; los artefactos del workflow se borran a los 14 días
- a mano, desde *Actions → Build → Run workflow*
- en los PRs que cambian el empaquetado o el proceso principal

---

## Estructura del proyecto

```
src/
  main/         Proceso principal de Electron: arranca PHP siempre y MariaDB en la app empaquetada (mariadb.js)
  renderer/     App React (Vite)
    api/        Wrappers de fetch
    components/ Componentes UI reutilizables
    pages/      Páginas por ruta
  tienda/       Tienda en línea: app React independiente de Electron (vite.tienda.config.mjs)
  compartido/   Dinero y formatos, usados por SGT y por la tienda
backend/        PHP 8 sin framework — router único en index.php
  config/       Esquema base database.sql, migraciones, seeder, datos demo y catálogo de la tienda
  models/       Clases de consultas PDO
  routes/       Controladores por módulo (routes/tienda/: API de la tienda)
  tests/        Pruebas PHPUnit (Unit y Api)
docker/web/     Imagen de la tienda (Node para compilar, nginx para servir)
resources/      Binarios empaquetados (en .gitignore — descargar por separado)
```

---

## Pruebas

### Frontend (Vitest)

```bash
npm test                 # una vez
npm run test:watch       # en modo observación
```

Las pruebas van junto al código que prueban, con el nombre `*.test.js` o `*.test.jsx`.

### Backend (PHPUnit)

Las pruebas `Unit` no necesitan base de datos. Las pruebas `Api` levantan el backend con `php -S` y usan una base de datos de pruebas que **se borra y se vuelve a crear en cada ejecución**. Nunca apunten las pruebas a la base de datos real.

Usuario de pruebas en MariaDB (una sola vez):

```sql
CREATE USER 'sgt_test'@'127.0.0.1' IDENTIFIED BY 'sgt_test';
GRANT ALL PRIVILEGES ON `sgtdb_test`.* TO 'sgt_test'@'127.0.0.1';
```

Ejecutar:

```bash
cd backend
composer install
composer test
```

Con la base de datos de Docker Compose, en lugar de crear el usuario:

```bash
DB_PORT=3307 DB_USER=root DB_PASS=root composer test
```

Los valores por defecto (`127.0.0.1:3306`, base `sgtdb_test`, usuario `sgt_test`) están en `backend/phpunit.xml` y se pueden cambiar con variables de entorno.

### CI

`.github/workflows/ci.yml` corre el lint, las dos suites de pruebas y la compilación de la tienda en cada PR y en cada push a `main`. El job **Tienda (Docker)** levanta todo con `docker compose up --build` y revisa que la tienda y su API respondan en el `8080`. `build.yml` genera los instaladores (ver *Instaladores en el CI*).

### QA

El resultado de la verificación final contra el SRS (requisitos funcionales, reglas de negocio y no funcionales) está en [`docs/QA_CHECKLIST.md`](docs/QA_CHECKLIST.md).

---

## Flujo de trabajo

El desarrollo se hace en el fork `T4R1N256/SGT-QA`, siguiendo [`PLAN.md`](PLAN.md):

- Una rama por fase, creada desde `main`: `fase-0-base`, `fase-1-integridad`, etc.
- Cada fase entra a `main` con un PR revisado y el CI en verde.

---

## Comandos útiles

```bash
npm run lint             # ESLint con auto-fix
npm run lint:check       # ESLint sin modificar archivos (lo que corre el CI)
npm run format           # Prettier
npm run dev:tienda       # tienda en línea en modo desarrollo (5174)
npm run build:tienda     # compila la tienda en dist-tienda/
docker-compose down      # detener contenedores (los datos persisten)
docker-compose down -v   # detener y borrar el volumen de base de datos
```
