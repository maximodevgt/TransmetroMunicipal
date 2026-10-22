# Sistema Web de Control y Administración de Transmetro

Aplicación web para administrar y supervisar la operación del **Transmetro de la Municipalidad de Guatemala**: líneas, estaciones, buses, pilotos, recorridos con seguimiento en vivo, alertas de demanda y la **Tarjeta Transmetro** con recargas y pasajes (pagos simulados).

Proyecto final del curso **Aseguramiento de la Calidad de Software** · Universidad Mariano Gálvez de Guatemala (UMG).
Autor: **Maximiliano Ajca Us**.

---

## Contenido

- [Módulos](#módulos)
- [Tecnología](#tecnología)
- [Requisitos](#requisitos)
- [Instalación en Windows](#instalación-en-windows)
- [Base de datos y scripts SQL](#base-de-datos-y-scripts-sql)
- [Usuarios y datos de prueba](#usuarios-y-datos-de-prueba)
- [Simulador del seguimiento en vivo](#simulador-del-seguimiento-en-vivo)
- [Pruebas](#pruebas)
- [Levantarlo para la demostración](#levantarlo-para-la-demostración)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Documentación](#documentación)

---

## Módulos

| Módulo | Qué hace | Rol |
|---|---|---|
| **Panel** | Indicadores, caja del día, cinco gráficas clicables (Chart.js), mini mapa, alertas activas y actividad reciente. | Ambos |
| **Seguimiento en vivo** | Mapa (Leaflet + OpenStreetMap) con los buses en ruta, su ocupación, estación actual y próxima, y las alertas. | Ambos |
| **Recorridos** | Iniciar y finalizar recorridos; registrar llegadas con pasajeros a bordo; distancia recorrida. | Administrador inicia y finaliza; el operador registra llegadas en su estación |
| **Alertas** | Alta demanda (≥ 150 % de la capacidad de referencia), baja ocupación (< 25 %: 5 minutos de espera), insuficiencia de unidades e incidencias; resolver o atender con nota. | Ambos (el operador, las de su estación) |
| **Mi estación** | Pasajeros en espera, buses que llegan y alertas de la estación del operador. | Operador |
| **Tarjetas** | Emisión, bloqueo y desbloqueo con motivo; detalle con movimientos. | Ambos |
| **Recargas** | En efectivo en ventanilla (operador) y en línea con pasarela **simulada** (marca, Luhn, tarjetas de prueba). | Ambos |
| **Cobro de pasaje** | Simula el paso del pasajero por el torniquete de un acceso. | Ambos (el operador, en su estación) |
| **Movimientos** | Recargas y pasajes con comprobante imprimible. | Ambos |
| **Reportes** | Líneas, estaciones, buses asignados, pilotos, recorridos, alertas, tarjetas y pagos, y bitácora; con filtros, totales, impresión carta y CSV. | Ambos (el operador, lo de su estación) |
| **Catálogos** | Líneas y orden de estaciones, estaciones con accesos y guardias, buses, parqueos, pilotos con historial educativo y usuarios. | Administrador (el operador consulta) |
| **Bitácora** | Registro de auditoría de todas las operaciones. | Administrador |
| **Consulta de saldo** | Página pública: código de tarjeta + últimos 4 dígitos del DPI. | Público |

Reglas de negocio importantes: mínimo de buses por línea = estaciones y máximo = estaciones × 2 (trigger de MySQL), todo bus con parqueo, cada acceso con al menos un guardia, cada estación en al menos una línea y con un operador, y **el saldo de las tarjetas lo calculan los triggers de MySQL** (la aplicación solo registra transacciones).

## Tecnología

- **Laravel 13** · **PHP 8.3**
- **MySQL 8.0** (triggers, vista `vw_resumen_lineas`, llaves foráneas y restricciones CHECK)
- **Blade + Tailwind CSS 4** · **Vite** · **Chart.js 4** · **Leaflet** (todo por npm, sin CDN)
- Pruebas con **PHPUnit** (Feature y Unit) sobre la base real dentro de transacciones

## Requisitos

- Windows 10/11
- PHP 8.3 con las extensiones `pdo_mysql`, `mbstring`, `openssl` y `fileinfo`
- Composer 2
- Node.js 20 o superior (con npm)
- MySQL Server 8.0 con el servicio **MySQL80** en el puerto **3307**, y MySQL Workbench para ejecutar los scripts

## Instalación en Windows

En PowerShell, dentro de la carpeta del proyecto:

1. **Dependencias de PHP**

   ```powershell
   composer install
   ```

2. **Configuración**: copie el archivo de ejemplo y genere la llave de la aplicación.

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

   Abra `.env` y escriba la contraseña de MySQL en `DB_PASSWORD`. Los demás valores ya están listos para este proyecto:

   ```dotenv
   APP_LOCALE=es
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3307
   DB_DATABASE=transmetro
   DB_USERNAME=root
   DB_PASSWORD=
   SESSION_DRIVER=file
   CACHE_STORE=file
   QUEUE_CONNECTION=sync
   ```

3. **Base de datos**: en MySQL Workbench ejecute los scripts en el orden de la sección siguiente. **No use `php artisan migrate`**: la base completa (tablas, vista, triggers y datos) la crean los scripts.

4. **Recursos de la interfaz**

   ```powershell
   npm install
   npm run build
   ```

5. **Iniciar**

   ```powershell
   php artisan serve
   ```

   Abra <http://localhost:8000> e ingrese con un usuario de prueba.

## Base de datos y scripts SQL

Los scripts están en `database/sql/` y se ejecutan en **MySQL Workbench** (pestaña de consultas, *Execute*).

| Script | Para qué | ¿Cuándo? |
|---|---|---|
| `01_transmetro.sql` | Crea la base `transmetro` completa: 19 tablas, la vista, los 4 triggers y los datos iniciales. Ya incluye todo lo de 03 a 06. | Siempre, primero. **Borra y vuelve a crear la base.** |
| `03_triggers_mensajes.sql`, `04_mapa.sql`, `05_pagos.sql`, `06_correcciones_datos.sql` | Actualizaciones para bases creadas con versiones anteriores de `01`. Son idempotentes. | Solo si **no** se vuelve a ejecutar `01`. |
| `07_datos_demo.sql` | Historial realista de los **últimos 7 días** para que el panel y los reportes tengan datos: recorridos en horas pico, llegadas, pasajeros en espera, alertas, 20 tarjetas (TM-000101 a TM-000120), recargas y pasajes. Respeta los triggers e idempotente (no se duplica). | Después de `01`, **el mismo día de la demostración** (las fechas son relativas al momento en que se ejecuta). |

**Orden para dejar la base lista para la demostración:**

```text
01_transmetro.sql  →  07_datos_demo.sql
```

Los scripts funcionan con el *modo de actualización segura* de Workbench activado.

## Usuarios y datos de prueba

Contraseña de todos los usuarios: **`Transmetro2026`** (solo para el entorno local).

| Usuario | Rol |
|---|---|
| `admin@transmetro.gt` | Administrador municipal |
| `operador.trebol@transmetro.gt` | Operador de la estación Trébol (L12) |
| `operador.centra.sur@transmetro.gt`, `operador.paseo.de.las.letras@transmetro.gt`, … | Un operador por estación: `operador.<estación>@transmetro.gt` |

**Tarjetas de prueba de la pasarela simulada** (cualquier vencimiento futuro, p. ej. `12/29`, y CVV de 3 dígitos):

| Número | Resultado |
|---|---|
| `4242 4242 4242 4242` | Aprobada |
| `4000 0000 0000 0002` | Rechazada: fondos insuficientes |
| `4000 0000 0000 0069` | Rechazada: tarjeta vencida |
| Cualquier otro número que pase la validación de Luhn (p. ej. `5555 5555 5555 4444`) | Aprobada |

No se guarda el número completo ni el CVV: solo la marca, los últimos 4 dígitos y una referencia inventada.

**Tarjetas Transmetro** (consulta pública de saldo: código + últimos 4 dígitos del DPI del titular):

| Tarjeta | Estado | Para probar | DPI termina en |
|---|---|---|---|
| `TM-000001` | Activa | Pasaje aprobado | `0101` |
| `TM-000004` | Activa, con poco saldo (Q4.00) | Pasaje aprobado hasta agotar el saldo; luego "Saldo insuficiente" | `0101` |
| `TM-000005` | **Bloqueada** | Pasaje y recarga rechazados | `0101` |
| `TM-000006` | Suspendida | Pasaje rechazado | `0101` |
| `TM-000101` a `TM-000120` | Activas (de `07_datos_demo.sql`) | Historial con varios movimientos | `TM-000101` → `0101`, `TM-000110` → `1001`, `TM-000120` → `2001` |

## Simulador del seguimiento en vivo

Pone buses en ruta con recorridos reales y, con el tiempo, registra las llegadas, los pasajeros en espera y las alertas.

- **Botón**: en *Seguimiento en vivo*, el administrador pulsa **Iniciar simulación** (y **Detener** al terminar; los buses quedan disponibles). Avanza mientras haya un mapa abierto.
- **Comando** (avanza aunque no haya ningún mapa abierto):

  ```powershell
  php artisan transmetro:simular --buses=6
  ```

  Opciones: `--buses` (cuántos buses en ruta, 6 por omisión), `--intervalo` (segundos entre avances, 3) y `--ciclos` (para detenerse solo). Se detiene con `Ctrl + C` y deja los buses disponibles.

## Pruebas

Las pruebas usan la misma base `transmetro` (con sus triggers y su vista) y **cada prueba corre dentro de una transacción que se revierte**: no dejan datos ni borran nada. Nunca se usan `RefreshDatabase`, `migrate` ni `db:wipe` (lo vigila `tests/Unit/PruebasSinBorrarDatosTest.php`).

```powershell
php artisan optimize:clear   # sin cachés: con la configuración en caché las pruebas no usan phpunit.xml
php artisan test
vendor/bin/pint --test       # estilo del código
```

Hay **pruebas Feature y Unit** de cada módulo: reglas de negocio, permisos por rol y por estación, seguridad (CSRF, XSS, inyección SQL, límite de intentos, sesión y "Recordarme"), reportes (totales, CSV, impresión, cantidad de consultas), gráficas, páginas de error y los scripts SQL 06 y 07 (que se ejecutan dentro de la transacción de la prueba con el modo de actualización segura). La matriz `docs/trazabilidad.md` relaciona cada requisito con su prueba, y `PlanDePruebasTest` ejecuta los 50 casos del plan de pruebas (CP-01 a CP-50) con sus datos.

## Levantarlo para la demostración

Con los recursos compilados y las cachés de Laravel (sin `npm run dev`):

```powershell
npm run build
php artisan optimize        # cachés de configuración, rutas, eventos y vistas
php artisan serve
```

Para que los errores muestren la página del sistema en vez del detalle técnico, en `.env` ponga `APP_DEBUG=false` durante la presentación.

Después de cambiar código o el `.env`, y **antes de correr las pruebas**, limpie las cachés:

```powershell
php artisan optimize:clear
```

## Estructura del proyecto

```text
app/
  Console/Commands/      transmetro:simular
  Exceptions/            ReglaNegocioException (mensajes de reglas de negocio)
  Http/Controllers/      un controlador por pantalla o recurso
  Http/Middleware/       rol (administrador/operador) y usuario activo
  Models/                modelos Eloquent (con alcance por estación: visiblesPara / puedeVerla)
  Reportes/              un reporte por clase (filtros, consulta agregada, totales, resumen)
  Services/              reglas: asignación de buses, recorridos, alertas, pagos, pasarela simulada, simulador, gráficas
  Support/               utilidades (interpolación del mapa)
config/transmetro.php    pasaje, montos de recarga y tarjetas de prueba de la pasarela
database/sql/            scripts de la base (01 a 07)
docs/                    trazabilidad, resultados de pruebas y guion de la demostración
lang/es/                 mensajes de validación y paginación en español
resources/views/         vistas Blade (componentes en components/, páginas de error en errors/)
resources/js/            mapa, gráficas, recargas, cobro de pasaje, notificaciones y alertas en vivo
routes/web.php           rutas (con su rol y su límite de intentos)
tests/Feature/           pruebas de cada módulo
tests/Unit/              pruebas unitarias
```

## Documentación

- [`docs/trazabilidad.md`](docs/trazabilidad.md): matriz de trazabilidad de RF-01 a RF-16, RNF-01 a RNF-07 y RN-01 a RN-18.
- [`docs/resultados_pruebas.md`](docs/resultados_pruebas.md): resultados de los casos CP-01 a CP-50 y de los criterios de aceptación CA-01 a CA-24, con el registro de defectos.
- [`docs/guion_demo.md`](docs/guion_demo.md): guion de la presentación (10-15 minutos) con plan B.

---

Maximiliano Ajca Us · Aseguramiento de la Calidad de Software · UMG · 2026
