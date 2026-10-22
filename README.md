# Sistema Web de Control y Administración de Transmetro

Aplicación web para administrar la operación del Transmetro de la Municipalidad de Guatemala: líneas, estaciones con sus accesos y guardias, buses, parqueos, pilotos, recorridos con seguimiento en vivo, alertas de alta demanda y baja ocupación, la Tarjeta Transmetro con recargas y pasajes (pagos simulados), reportes y bitácora. Tiene dos roles: administrador municipal y operador de estación. Las reglas de cantidad de buses por línea y el saldo de las tarjetas se aplican con triggers de MySQL.

## Requisitos

- PHP 8.3 con `pdo_mysql`, `mbstring`, `openssl` y `fileinfo`
- Composer 2
- Node.js 20 o superior
- MySQL 8.0 (puerto 3307) y MySQL Workbench

## Instalación

1. Instalar las dependencias:

   ```powershell
   composer install
   npm install
   ```

2. Crear el archivo de configuración y la llave de la aplicación:

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

   En `.env`, escribir la contraseña de MySQL en `DB_PASSWORD`. La base es `transmetro` en `127.0.0.1:3307`.

3. Crear la base de datos en MySQL Workbench, ejecutando en este orden los scripts de `database/sql/`:

   1. `01_transmetro.sql`: tablas, vista, triggers y datos iniciales (borra y vuelve a crear la base).
   2. `07_datos_demo.sql` (opcional): historial de los últimos 7 días para el panel y los reportes.

   Los scripts `03` a `06` solo actualizan bases creadas con una versión anterior de `01`. No se usa `php artisan migrate`.

4. Compilar los recursos de la interfaz:

   ```powershell
   npm run build
   ```

## Usuarios de prueba

Contraseña de todos: `Transmetro2026`.

| Usuario | Rol |
|---|---|
| `admin@transmetro.gt` | Administrador municipal |
| `operador.trebol@transmetro.gt` | Operador de la estación Trébol |
| `operador.<estación>@transmetro.gt` | Operador de cada estación (por ejemplo `operador.centra.sur@transmetro.gt`) |

Para la recarga en línea (pasarela simulada) se usa la tarjeta `4242 4242 4242 4242`, con cualquier vencimiento futuro y CVV de 3 dígitos.

## Levantar el sistema

```powershell
php artisan optimize
php artisan serve
```

Abrir <http://localhost:8000>. Para el seguimiento en vivo hay un simulador: el botón **Iniciar simulación** en la pantalla de seguimiento, o el comando `php artisan transmetro:simular --buses=6`.

## Pruebas

Las pruebas usan la base `transmetro` y cada una corre dentro de una transacción que se revierte, así que no dejan datos.

```powershell
php artisan optimize:clear
php artisan test
vendor/bin/pint --test
```

## Autor

Maximiliano Ajca Us · Aseguramiento de la Calidad de Software · Universidad Mariano Gálvez de Guatemala · 2026
