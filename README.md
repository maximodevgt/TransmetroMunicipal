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

## Despliegue en Railway

El repositorio trae `Dockerfile` y `railway.json`: Railway construye la imagen (PHP 8.3 con FrankenPHP, dependencias sin las de desarrollo y recursos compilados) y al iniciar genera las cachés con `php artisan optimize`. El HTTPS lo da Railway.

1. Crear un proyecto con un servicio **MySQL** y un servicio desde este repositorio (rama a desplegar).
2. En el servicio de MySQL, con los datos de *Public Networking*, conectarse desde MySQL Workbench y ejecutar `01_transmetro.sql` y, si se quieren datos de ejemplo, `07_datos_demo.sql`.
3. En el servicio de la aplicación, definir las variables y generar un dominio en *Networking*:

   | Variable | Valor |
   |---|---|
   | `APP_NAME` | `Transmetro` |
   | `APP_ENV` | `production` |
   | `APP_KEY` | resultado de `php artisan key:generate --show` |
   | `APP_DEBUG` | `false` |
   | `APP_URL` | `https://` + el dominio de Railway |
   | `APP_LOCALE` | `es` |
   | `LOG_CHANNEL` | `stderr` |
   | `DB_CONNECTION` | `mysql` |
   | `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
   | `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
   | `DB_DATABASE` | `transmetro` |
   | `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
   | `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
   | `SESSION_DRIVER` | `file` |
   | `SESSION_SECURE_COOKIE` | `true` |
   | `CACHE_STORE` | `file` |
   | `QUEUE_CONNECTION` | `sync` |

La conexión usa la hora de Guatemala (`-06:00`) aunque el servidor esté en UTC. En producción el simulador del mapa se usa con el botón **Iniciar simulación**.

## Autor

Maximiliano Ajca Us · Aseguramiento de la Calidad de Software · Universidad Mariano Gálvez de Guatemala · 2026
