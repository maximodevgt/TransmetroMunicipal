# Guion de la demostración (10 a 15 minutos)

Sistema Web de Control y Administración de Transmetro · Presentación del 23/10/2026.

## Antes de empezar (30 minutos antes)

1. **Base de datos** en MySQL Workbench: ejecutar `01_transmetro.sql` y luego `07_datos_demo.sql` (el 07 genera los últimos 7 días a partir de ese momento; por eso se corre el mismo día). El 07 termina mostrando "Datos de demostración cargados" y el conteo de filas.
2. **Aplicación** en PowerShell, en la carpeta del proyecto:

   ```powershell
   npm run build
   php artisan optimize
   php artisan serve
   ```

   En `.env`, `APP_DEBUG=false` para que cualquier error muestre la página del sistema.
3. **Dos ventanas del navegador**:
   - Ventana normal: `admin@transmetro.gt`.
   - Ventana privada (incógnito): `operador.trebol@transmetro.gt`.
   - Contraseña de ambos: `Transmetro2026`. Zoom al 100 %; ponga las dos ventanas lado a lado si la pantalla lo permite.
4. Tener a mano: tarjeta de prueba `4242 4242 4242 4242` (vence `12/29`, CVV `123`) y `4000 0000 0000 0002`; tarjetas Transmetro `TM-000001` y `TM-000005`.
5. Abrir Excel (para el CSV) y comprobar que la impresora predeterminada sea "Microsoft Print to PDF".

## Recorrido

| # | Tiempo | Pantalla | Qué hacer | Qué demuestra | Datos |
|---|---|---|---|---|---|
| 1 | 0:00 – 1:00 | **Inicio de sesión** | Mostrar el botón del ojo para ver la contraseña y la casilla "Recordarme". Ingresar como administrador. Mencionar el enlace público "Consulte su saldo". | RNF-01 (autenticación y roles), RNF-03 | `admin@transmetro.gt` / `Transmetro2026` |
| 2 | 1:00 – 2:30 | **Panel** | Recorrer los indicadores y la caja del día. Bajar a "Indicadores": las cinco gráficas. Hacer clic en una barra de "Alertas por tipo" (o en un punto de "Recaudación"): abre el reporte ya filtrado. Volver. | Aportación (dashboard), RF-16, RNF-05 | Historial de 7 días del script 07 |
| 3 | 2:30 – 4:00 | **Seguimiento en vivo** | Pulsar **Iniciar simulación**. Mostrar los buses moviéndose entre estaciones, el color por línea y el panel con ocupación, estación actual y próxima. Abrir el globo de un bus. Dejar la simulación corriendo. | RF-11, RF-12, RNF-05 | 6 buses simulados |
| 4 | 4:00 – 5:30 | **Mi estación** (ventana del operador) | En la ventana del operador de Trébol, registrar **160 pasajeros en espera**. Se genera la alerta de alta demanda; en la ventana del administrador, en unos segundos, aparece el aviso en vivo y la campana sube. | RF-13, RN-15, RF-09 (operador por estación), RNF-01 (el operador solo ve su estación) | Trébol: 160 ≥ 150 % de 100 |
| 5 | 5:30 – 7:00 | **Resolver la alerta** (administrador) | En el aviso o en Alertas, pulsar **Resolver**: abre "Iniciar recorrido" con la línea L12 y Trébol resaltada. Elegir bus y piloto y confirmar. La alerta queda atendida y el bus en ruta. En Reportes → Alertas, atajo Hoy: el tiempo de atención de cada alerta y el promedio. | RF-10, RF-13, RN-07/RN-08 (solo buses de la línea), bitácora | Línea L12 |
| 6 | 7:00 – 9:00 | **Tarjetas → Recargar en línea** | Abrir `TM-000001` → Recargar → En línea. Usar el botón de prueba **4242**: "Procesando pago…" y comprobante con "Visa •••• 4242" y el saldo nuevo. Repetir con **0002**: "Recarga rechazada: Fondos insuficientes" y el saldo no cambia. Mencionar que nunca se guarda el número completo ni el CVV y que el saldo lo calcula el trigger. | RF-14, RF-15, RN-18, RNF-02 | `TM-000001`, Q20.00 |
| 7 | 9:00 – 10:00 | **Cobro de pasaje** (operador) | En la ventana del operador: Cobro de pasaje → Acceso Norte de Trébol. Pasar `TM-000001`: verde "Bienvenido" con el saldo. Pasar `TM-000005`: rojo "Tarjeta bloqueada". | RF-15, RF-14 (verificar estado), RNF-02 | `TM-000001`, `TM-000005` |
| 8 | 10:00 – 12:00 | **Reportes** | Abrir "Tarjetas y pagos", atajo **7 días**, agrupar por día y estación: totales arriba y rechazos por motivo abajo. **Imprimir**: vista carta con encabezado, filtros, usuario y número de página (imprimir a PDF). **Exportar CSV** y abrirlo en Excel: columnas separadas y tildes correctas. Abrir también "Líneas" (Cumple / Faltan buses). | RF-16, RNF-05 | Últimos 7 días |
| 9 | 12:00 – 13:00 | **Bitácora** | Mostrar las operaciones de la demostración: recorrido iniciado, recargas, alerta atendida, reportes consultados. Filtrar por usuario. | RNF-01, RNF-07 (auditoría) | — |
| 10 | 13:00 – 14:00 | **Cierre** | Detener la simulación (los buses vuelven a disponibles). Mencionar las pruebas automatizadas (`php artisan test`), la matriz `docs/trazabilidad.md` y los resultados del plan de pruebas (`docs/resultados_pruebas.md`). Cerrar sesión. | Calidad: pruebas y trazabilidad | — |

Si sobra tiempo: consulta pública de saldo (`TM-000001`, DPI que termina en `0101`); un operador que escribe la URL de otra estación y recibe la página 403 en español; el menú en una ventana pequeña (la lista de enlaces se desplaza y "Cerrar sesión" sigue visible).

## Plan B

| Si pasa esto | Hacer esto |
|---|---|
| Las gráficas o los reportes salen vacíos | No se corrió el script 07 hoy. Ejecutar `07_datos_demo.sql` en Workbench y recargar (si ya se había corrido antes, ejecutar `01` y luego `07`). |
| El simulador no mueve los buses | Avanza solo mientras haya un mapa abierto. Abrir una terminal y ejecutar `php artisan transmetro:simular --buses=6`; detenerlo con `Ctrl + C`. |
| El mapa no carga las calles (sin internet) | Las calles vienen de OpenStreetMap. Los buses y estaciones se siguen mostrando y la lista lateral funciona; explicar con la lista y el mini mapa del panel. |
| No aparece la alerta de alta demanda | Puede haber ya una activa en Trébol (no se duplican). Abrir Alertas, filtrar por alta demanda y resolver esa; o usar otra estación con su operador (`operador.<estación>@transmetro.gt`). |
| "Demasiados intentos" al iniciar sesión | El inicio de sesión se bloquea un minuto tras 6 intentos fallidos. Esperar el minuto o entrar con otro usuario de prueba. |
| "La sesión expiró" (419) al enviar un formulario | Recargar la página e intentar de nuevo. |
| La recarga en línea no responde | Revisar que el monto esté entre Q1 y Q500 y usar los botones de tarjetas de prueba (llenan número, vencimiento y CVV). |
| Excel muestra todo en una columna | En Excel: Datos → Desde texto/CSV, origen UTF-8 y delimitador punto y coma. |
| No hay impresora | Elegir "Microsoft Print to PDF" o mostrar la vista de impresión en pantalla. |
| El servidor se cae o no responde | En la terminal: `Ctrl + C`, `php artisan optimize:clear`, `php artisan optimize`, `php artisan serve`. |
| Se ve un error técnico en vez de la página del sistema | `APP_DEBUG=true` en `.env`: cambiarlo a `false` y ejecutar `php artisan optimize`. |
| La base quedó en mal estado | Ejecutar `01_transmetro.sql` y `07_datos_demo.sql` (unos segundos) y volver a iniciar sesión. |
