# Migraciones

`../database.sql` es el **esquema base**. Todo cambio posterior al esquema va aquí, como un archivo nuevo.
Las migraciones se aplican solas en cada arranque (`seeder.php`), tanto en Docker como en Electron.

## Reglas

- **Nombre:** `NNN_descripcion.sql`, con tres dígitos y en orden. Por ejemplo, `001_dinero_decimal.sql`.
- **No se edita** una migración que ya está en `main`. Para corregirla, se crea otra.
- **Una sentencia por bloque**, terminada en `;` al final de la línea. Los comentarios van con `--` al inicio de la línea.
- **Idempotentes cuando se pueda:** MariaDB no permite deshacer DDL, así que si una migración falla a la mitad, se reintenta completa en el siguiente arranque. Usen `IF NOT EXISTS`, `IF EXISTS`, `ADD COLUMN IF NOT EXISTS`, etc.
- **Ramas en paralelo:** si dos ramas crean el mismo número (por ejemplo, `003_surtir.sql` y `003_historial.sql`), no hay conflicto. Las dos se aplican, ordenadas por nombre completo. Aun así, conviene acordar el número antes de empezar.

## Aplicar a mano

```bash
cd backend
php config/migrate.php
```

La tabla `Migracion` registra qué versiones ya se aplicaron y cuándo.
