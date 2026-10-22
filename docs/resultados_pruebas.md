# Resultados del plan de pruebas

Ejecución de los casos de prueba (sección 15.9) y de los criterios de aceptación (sección 16) del plan de pruebas (E7, 17/10/2026), con el formato de registro de la sección 15.11.

- **Fecha de ejecución:** 22/10/2026
- **Ejecutor:** Maximiliano Ajca Us
- **Ambiente:** Windows 11, PHP 8.3, MySQL 8 (base `transmetro`), Laravel 13, servidor `php artisan serve`.
- **Evidencia automatizada:** `tests/Feature/PlanDePruebasTest.php`, una prueba por caso (`test_cp_XX_...`) con los pasos y datos del plan. Se ejecuta con `php artisan test --filter=PlanDePruebasTest`; cada prueba corre dentro de una transacción que se revierte al terminar, así que no deja datos en la base.
- **Evidencia en pantalla:** los casos marcados "Pantalla" se repitieron además en el navegador con el usuario indicado.
- **Suite completa:** 263 pruebas aprobadas (18 384 verificaciones), sin fallas.

## Resumen

| Concepto | Total |
|---|---|
| Casos ejecutados | 50 |
| Aprobados | 49 |
| Fallidos | 0 |
| Pendientes | 1 (CP-48: falta Firefox en el equipo de pruebas) |
| Defectos encontrados | 6 (0 críticos, 1 alto, 3 medios, 2 bajos), todos cerrados |
| Criterios de aceptación cumplidos | 22 de 24 (CA-19 y CA-23 pendientes) |

Los datos de prueba son los iniciales del script. Cuando la base de desarrollo ya no los tenía (por ejemplo, un saldo que cambió con el uso), la prueba los preparó antes de ejecutar el caso; se indica en la columna "Resultado obtenido".

## Casos de prueba

### Autenticación y roles

| Código | Caso | Resultado esperado | Resultado obtenido | Estado | Evidencia | Fecha | Ejecutor |
|---|---|---|---|---|---|---|---|
| CP-01 | Inicio de sesión válido | Se muestra el panel principal con el nombre del usuario. | Con admin@transmetro.gt / Transmetro2026 entra al Panel con "Hola, Administrador Municipal". | Aprobado | `test_cp_01_inicio_de_sesion_valido` · Pantalla | 22/10/2026 | Maximiliano Ajca Us |
| CP-02 | Contraseña incorrecta | Mensaje de credenciales incorrectas; no se inicia sesión. | "Las credenciales no son válidas."; sigue sin sesión. | Aprobado | `test_cp_02_contrasena_incorrecta` | 22/10/2026 | Maximiliano Ajca Us |
| CP-03 | Usuario desactivado | Se rechaza el ingreso con el mensaje de usuario desactivado. | Se desactiva operador.trebol (antes se asigna otro operador a Trébol por RN-17); al ingresar: "Su usuario está desactivado. Comuníquese con el administrador." | Aprobado | `test_cp_03_usuario_desactivado` | 22/10/2026 | Maximiliano Ajca Us |
| CP-04 | Acceso sin sesión | Redirige a la pantalla de inicio de sesión. | `/buses` redirige a `/login`. | Aprobado | `test_cp_04_acceso_sin_sesion` | 22/10/2026 | Maximiliano Ajca Us |
| CP-05 | Permisos del operador | Se muestra la página de acceso restringido. | Pilotos y Registrar bus responden 403 "Acceso restringido"; el bus no se guarda. | Aprobado | `test_cp_05_permisos_del_operador` | 22/10/2026 | Maximiliano Ajca Us |
| CP-06 | Intentos fallidos repetidos | El sistema bloquea temporalmente nuevos intentos. | Los primeros 6 muestran el error; el 7.º responde 429 "Demasiados intentos". | Aprobado | `test_cp_06_intentos_fallidos_repetidos` | 22/10/2026 | Maximiliano Ajca Us |

### Líneas, estaciones y accesos

| Código | Caso | Resultado esperado | Resultado obtenido | Estado | Evidencia | Fecha | Ejecutor |
|---|---|---|---|---|---|---|---|
| CP-07 | Código de línea duplicado | Mensaje: ya existe un registro con ese valor; no se guarda. | "Ya existe un registro con ese valor de código."; el total de líneas no cambia. | Aprobado | `test_cp_07_codigo_de_linea_duplicado` | 22/10/2026 | Maximiliano Ajca Us |
| CP-08 | Agregar estación en medio de una línea | Las estaciones siguientes se reordenan y la distancia total se actualiza. | La estación nueva queda en la posición 3 de L2, las siguientes pasan a 4..6 y la distancia total sube 0.45 km. | Aprobado | `test_cp_08_y_cp_09_agregar_y_quitar_una_estacion_en_medio_de_l2` · Pantalla (formulario) | 22/10/2026 | Maximiliano Ajca Us |
| CP-09 | Quitar estación de una línea | El orden vuelve a ser consecutivo, sin posiciones vacías. | Como L2 es su única línea, quitarla de la línea se rechaza (RN-02) y se quita eliminándola; el orden vuelve a 1..5 sin huecos. | Aprobado | `test_cp_08_y_cp_09_agregar_y_quitar_una_estacion_en_medio_de_l2` | 22/10/2026 | Maximiliano Ajca Us |
| CP-10 | Campos obligatorios | Mensaje indicando que el nombre es obligatorio. | "El campo nombre es obligatorio." | Aprobado | `test_cp_10_campos_obligatorios` | 22/10/2026 | Maximiliano Ajca Us |
| CP-11 | Estación en varias líneas | Se muestra en las líneas L1 y L2. | San Sebastián aparece en L1 y L2. | Aprobado | `test_cp_11_estacion_en_varias_lineas` | 22/10/2026 | Maximiliano Ajca Us |
| CP-12 | Registrar acceso | El acceso aparece en la estación y en los accesos de la línea L12. | "Acceso Oriente" se registra en Trébol con su primer guardia (RN-11) y aparece en la estación y en la línea L12. | Aprobado | `test_cp_12_y_cp_13_registrar_acceso_y_guardia` | 22/10/2026 | Maximiliano Ajca Us |
| CP-13 | Registrar guardia | El guardia aparece con su turno en el acceso. | El guardia aparece en el acceso con turno "Nocturno". | Aprobado | `test_cp_12_y_cp_13_registrar_acceso_y_guardia` | 22/10/2026 | Maximiliano Ajca Us |
| CP-14 | Acceso sin guardia | Se rechaza: cada acceso debe tener al menos un guardia (RN-11). | "No se puede eliminar a …: es el último guardia del acceso … y todo acceso debe tener al menos uno (RN-11)." | Aprobado | `test_cp_14_acceso_sin_guardia` | 22/10/2026 | Maximiliano Ajca Us |

### Buses, parqueos y pilotos

| Código | Caso | Resultado esperado | Resultado obtenido | Estado | Evidencia | Fecha | Ejecutor |
|---|---|---|---|---|---|---|---|
| CP-15 | Superar el máximo de buses | Mensaje: la línea ya tiene el máximo de buses permitido; no se guarda. | Con L2 completada a 10 buses (los datos iniciales traen 5), el bus 11 se rechaza: "La línea ya tiene el máximo de buses permitido (estaciones × 2)."; L2 sigue con 10. | Aprobado | `test_cp_15_superar_el_maximo_de_buses` | 22/10/2026 | Maximiliano Ajca Us |
| CP-16 | Asignar bus a una línea | Se guarda; la línea L12 pasa a 17 buses. | BUS-132 queda en L12, que pasa de 16 a 17 buses. | Aprobado | `test_cp_16_asignar_bus_a_una_linea` | 22/10/2026 | Maximiliano Ajca Us |
| CP-17 | Bus sin parqueo | Mensaje indicando que el parqueo es obligatorio. | "El campo parqueo es obligatorio." | Aprobado | `test_cp_17_bus_sin_parqueo` | 22/10/2026 | Maximiliano Ajca Us |
| CP-18 | Cambiar de parqueo | El cambio se guarda y el bus nunca queda sin parqueo. | BUS-101 pasa al Predio Centro Histórico; dejarlo sin parqueo se rechaza. | Aprobado | `test_cp_18_cambiar_de_parqueo` | 22/10/2026 | Maximiliano Ajca Us |
| CP-19 | Menos buses que el mínimo | Se guarda y se genera una alerta de insuficiencia de unidades. | L1 queda con 9 buses y se genera la alerta activa de insuficiencia de unidades. | Aprobado | `test_cp_19_menos_buses_que_el_minimo` | 22/10/2026 | Maximiliano Ajca Us |
| CP-20 | Placa duplicada | Mensaje de valor duplicado; no se guarda. | "Ya existe un registro con ese valor de placa." | Aprobado | `test_cp_20_placa_duplicada` | 22/10/2026 | Maximiliano Ajca Us |
| CP-21 | Registrar parqueo | El parqueo aparece con su ocupación (buses asignados / capacidad). | El parqueo nuevo aparece con ocupación "0 / 12". | Aprobado | `test_cp_21_registrar_parqueo` | 22/10/2026 | Maximiliano Ajca Us |
| CP-22 | Registrar piloto | El piloto se guarda con su historial. | El piloto se guarda con residencia, teléfono y 2 registros de historial educativo visibles en su ficha. | Aprobado | `test_cp_22_registrar_piloto_con_historial` | 22/10/2026 | Maximiliano Ajca Us |
| CP-23 | DPI duplicado | Mensaje de valor duplicado; no se guarda. | "Ya existe un registro con ese valor de DPI." | Aprobado | `test_cp_23_dpi_duplicado` | 22/10/2026 | Maximiliano Ajca Us |

### Recorridos y seguimiento de unidades

| Código | Caso | Resultado esperado | Resultado obtenido | Estado | Evidencia | Fecha | Ejecutor |
|---|---|---|---|---|---|---|---|
| CP-24 | Iniciar recorrido | El recorrido queda en curso y el bus pasa a estado "en ruta". | Recorrido de L12 con BUS-103 y Julio Monzón Ramírez "En curso"; el bus queda "en ruta". | Aprobado | `test_cp_24_a_cp_28_recorrido_completo_de_bus_103` · Pantalla (recorrido #2491) | 22/10/2026 | Maximiliano Ajca Us |
| CP-25 | Registrar llegada a estación | Se guarda la visita con hora de llegada y pasajeros. | Llegada a Centra Sur a las 14:35 con 45 / 100 pasajeros (45 %, Normal). | Aprobado | `test_cp_24_a_cp_28_recorrido_completo_de_bus_103` · Pantalla | 22/10/2026 | Maximiliano Ajca Us |
| CP-26 | Seguimiento de la unidad | Se muestran el bus, la línea, la estación actual, la próxima estación, la ocupación, la hora y sus alertas. | El mapa y la lista muestran "BUS-103 · L12 · Centra Sur → Monte María · 45 %", con la hora de actualización y las alertas del bus. | Aprobado | `test_cp_24_a_cp_28_recorrido_completo_de_bus_103` · Pantalla (Seguimiento en vivo) | 22/10/2026 | Maximiliano Ajca Us |
| CP-27 | Finalizar recorrido | El recorrido queda finalizado y el bus vuelve a estado "disponible". | Recorrido "Finalizado" con hora de fin; BUS-103 vuelve a "disponible". | Aprobado | `test_cp_24_a_cp_28_recorrido_completo_de_bus_103` · Pantalla | 22/10/2026 | Maximiliano Ajca Us |
| CP-28 | Bus ya en ruta | Se rechaza con un mensaje claro. | "El bus BUS-103 ya está en ruta (recorrido #N en curso)." | Aprobado | `test_cp_24_a_cp_28_recorrido_completo_de_bus_103` | 22/10/2026 | Maximiliano Ajca Us |

### Capacidad de pasajeros y alertas

| Código | Caso | Resultado esperado | Resultado obtenido | Estado | Evidencia | Fecha | Ejecutor |
|---|---|---|---|---|---|---|---|
| CP-29 | Alta demanda | Se genera la alerta de alta demanda: enviar otra unidad. | Alerta activa "Estación Trébol con 160 pasajeros en espera (160 % de 100): enviar otro bus." Como los datos iniciales ya traen una alerta activa de Trébol, se actualiza esa en lugar de duplicarla. | Aprobado | `test_cp_29_y_cp_32_alta_demanda_y_atender_la_alerta` | 22/10/2026 | Maximiliano Ajca Us |
| CP-30 | Baja ocupación | Se genera la alerta de baja ocupación y se registran 5 minutos de espera adicional. | Con 20 a bordo de 100 se genera la alerta de baja ocupación y la visita registra 5 minutos de espera. | Aprobado | `test_cp_30_baja_ocupacion` | 22/10/2026 | Maximiliano Ajca Us |
| CP-31 | Ocupación normal | No se genera ninguna alerta. | Con 60 a bordo no se crea ninguna alerta y la espera es 0. | Aprobado | `test_cp_31_ocupacion_normal` | 22/10/2026 | Maximiliano Ajca Us |
| CP-32 | Atender alerta | La alerta cambia a "atendida" con su fecha y hora. | La alerta de CP-29 queda "atendida" con fecha y hora de atención. | Aprobado | `test_cp_29_y_cp_32_alta_demanda_y_atender_la_alerta` | 22/10/2026 | Maximiliano Ajca Us |
| CP-33 | Alertas por estación | Solo ve las alertas de su propia estación. | El operador de Centra Sur no ve la alerta de Trébol en la lista y abrirla responde 403. | Aprobado | `test_cp_33_alertas_por_estacion` | 22/10/2026 | Maximiliano Ajca Us |

### Tarjeta Transmetro

| Código | Caso | Resultado esperado | Resultado obtenido | Estado | Evidencia | Fecha | Ejecutor |
|---|---|---|---|---|---|---|---|
| CP-34 | Recarga | Saldo nuevo: Q14.00; el movimiento queda registrado. | Partiendo de Q4.00: "Recarga aprobada. Saldo nuevo: Q14.00." y la recarga queda en el historial. | Aprobado | `test_cp_34_recarga` | 22/10/2026 | Maximiliano Ajca Us |
| CP-35 | Pago con saldo | Pago aprobado; saldo nuevo: Q17.00. | TM-000001 llevada al saldo inicial de Q18.00; en Cobro de pasaje: "Bienvenido · Saldo: Q17.00". | Aprobado | `test_cp_35_pago_con_saldo` | 22/10/2026 | Maximiliano Ajca Us |
| CP-36 | Pago con tarjeta bloqueada | Pago rechazado con el motivo; el saldo no cambia y el intento queda en el historial. | "Tarjeta bloqueada"; el saldo no cambia y el intento queda rechazado con el motivo "La tarjeta no se encuentra activa." | Aprobado | `test_cp_36_pago_con_tarjeta_bloqueada` | 22/10/2026 | Maximiliano Ajca Us |
| CP-37 | Saldo insuficiente | Pago rechazado por saldo insuficiente; el saldo no cambia. | El pago de Q50.00 con Q9.00 lo rechaza la base de datos: "Saldo insuficiente en la tarjeta."; el saldo sigue en Q9.00. El pasaje de la aplicación es fijo de Q1.00, por eso el monto de Q50.00 se registró directo en la base. | Aprobado | `test_cp_37_saldo_insuficiente` | 22/10/2026 | Maximiliano Ajca Us |
| CP-38 | Cambiar estado de tarjeta | El administrador puede cambiar el estado; el operador no. | El administrador suspende TM-000003 ("estado cambiado a suspendida"); el operador recibe 403 y no ve el control. | Aprobado | `test_cp_38_cambiar_estado_de_tarjeta` · Pantalla | 22/10/2026 | Maximiliano Ajca Us |
| CP-39 | Historial de movimientos | Se listan fecha, hora, tipo, monto, saldo resultante y estado. | La ficha de TM-000001 lista Fecha (con hora), Movimiento, Monto, Saldo y Estado. | Aprobado | `test_cp_39_historial_de_movimientos` | 22/10/2026 | Maximiliano Ajca Us |
| CP-40 | Emitir tarjeta | La tarjeta se crea con código único, estado activa y saldo Q0.00. | Se crea con el siguiente código (único), estado activa y saldo Q0.00. | Aprobado | `test_cp_40_emitir_tarjeta` | 22/10/2026 | Maximiliano Ajca Us |

### Reportes, panel y requerimientos no funcionales

| Código | Caso | Resultado esperado | Resultado obtenido | Estado | Evidencia | Fecha | Ejecutor |
|---|---|---|---|---|---|---|---|
| CP-41 | Reporte de líneas | Muestra estaciones, distancia total, buses asignados, mínimo y máximo de cada línea. | Columnas Código, Nombre, Estaciones, Distancia, Buses asignados, Mínimo y Máximo; L12 con 9.40 km. | Aprobado | `test_cp_41_reporte_de_lineas` | 22/10/2026 | Maximiliano Ajca Us |
| CP-42 | Reporte de estaciones | Muestra municipalidad, líneas, accesos, guardias y operador de cada estación. | Columnas Estación, Municipalidad, Líneas, Orden, Accesos, Guardias y Operador. | Aprobado | `test_cp_42_reporte_de_estaciones` · Pantalla | 22/10/2026 | Maximiliano Ajca Us |
| CP-43 | Reporte de buses asignados | Muestra los buses agrupados por línea y los buses sin línea. | Los buses salen agrupados por línea y al final los 4 sin línea ("Sin línea"); total 35. | Aprobado | `test_cp_43_reporte_de_buses_asignados` | 22/10/2026 | Maximiliano Ajca Us |
| CP-44 | Imprimir reporte | La vista de impresión no muestra el menú y conserva las tablas completas. | La vista de impresión de Estaciones no tiene menú lateral y muestra las 28 filas sin paginar. | Aprobado | `test_cp_44_imprimir_reporte` · Pantalla | 22/10/2026 | Maximiliano Ajca Us |
| CP-45 | Indicadores del panel | Los valores coinciden. | Panel y conteo directo en MySQL coinciden: 3 líneas, 28 estaciones, 35 buses, 12 pilotos activos. | Aprobado | `test_cp_45_indicadores_del_panel` | 22/10/2026 | Maximiliano Ajca Us |
| CP-46 | Tiempo de respuesta | El promedio es de 2 segundos o menos. | 10 mediciones del listado de buses en el navegador: promedio 0.39 s (máximo 0.73 s). | Aprobado | `test_cp_46_tiempo_de_respuesta_del_listado_de_buses` · Pantalla | 22/10/2026 | Maximiliano Ajca Us |
| CP-47 | Bitácora | La operación aparece con usuario, fecha y hora. | En la actividad reciente aparece "Registro del bus …" con "Administrador Municipal · dd/mm/aaaa hh:mm". | Aprobado | `test_cp_47_bitacora` | 22/10/2026 | Maximiliano Ajca Us |
| CP-48 | Compatibilidad | Se ven y funcionan correctamente en los tres navegadores. | Chromium 152 (motor de Chrome): pantallas principales correctas. Edge 155: inicio de sesión correcto. Firefox no está instalado en el equipo de pruebas. | Pendiente | Pantalla | 22/10/2026 | Maximiliano Ajca Us |
| CP-49 | Inyección SQL | No se altera la consulta ni se muestran datos indebidos. | La búsqueda `' OR 1=1 --` responde "No se encontraron estaciones." y no cambia ningún dato. | Aprobado | `test_cp_49_inyeccion_sql` | 22/10/2026 | Maximiliano Ajca Us |
| CP-50 | Contraseñas cifradas | Todas las contraseñas están cifradas (bcrypt); ninguna es legible. | Las 29 contraseñas de `users` empiezan con `$2y$` (bcrypt); ninguna en texto plano. | Aprobado | `test_cp_50_contrasenas_cifradas` | 22/10/2026 | Maximiliano Ajca Us |

## Criterios de aceptación

| Código | Criterio | Resultado esperado | Resultado obtenido | Estado | Evidencia | Fecha | Ejecutor |
|---|---|---|---|---|---|---|---|
| CA-01 | RF-01 Gestión de líneas | Se registran, consultan y modifican líneas con su municipalidad, estaciones en orden, buses asignados y distancia total. | CP-07 y CP-41 aprobados. | Aprobado | CP-07, CP-41; `CatalogosTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-02 | RF-02 Gestión de estaciones | Se registran, consultan y modifican estaciones; una estación puede pertenecer a varias líneas. | CP-10 y CP-11 aprobados. | Aprobado | CP-10, CP-11 | 22/10/2026 | Maximiliano Ajca Us |
| CA-03 | RF-03 Orden de estaciones | Al agregar o quitar una estación el orden se actualiza sin posiciones repetidas ni vacías. | CP-08 y CP-09 aprobados. | Aprobado | CP-08, CP-09 | 22/10/2026 | Maximiliano Ajca Us |
| CA-04 | RF-04 Gestión de accesos | Se registran accesos por estación y se consultan los de una línea; un acceso pertenece a una sola estación. | CP-12 aprobado. | Aprobado | CP-12; `AccesosGuardiasTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-05 | RF-05 Gestión de buses | Se registran buses con su capacidad, se asignan a una sola línea y se consulta su estado. | CP-16 y CP-20 aprobados. | Aprobado | CP-16, CP-20; `AsignacionBusesTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-06 | RF-06 Cantidad de buses | Se impide superar el máximo (estaciones × 2) y se informa cuando una línea queda por debajo del mínimo. | CP-15 y CP-19 aprobados. | Aprobado | CP-15, CP-19 | 22/10/2026 | Maximiliano Ajca Us |
| CA-07 | RF-07 Gestión de parqueos | Se registran parqueos; todo bus tiene parqueo y puede cambiar de uno a otro. | CP-17, CP-18 y CP-21 aprobados. | Aprobado | CP-17, CP-18, CP-21; `ParqueosTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-08 | RF-08 Gestión de pilotos | Se registran pilotos con nombre, residencia, contacto e historial educativo. | CP-22 y CP-23 aprobados. | Aprobado | CP-22, CP-23 | 22/10/2026 | Maximiliano Ajca Us |
| CA-09 | RF-09 Guardias y operadores | Cada acceso tiene al menos un guardia y cada estación tiene un operador. | CP-13 y CP-14 aprobados; la estación no puede quedarse sin operador (CP-03, RN-17). | Aprobado | CP-13, CP-14; `OperadorEstacionTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-10 | RF-10 Recorridos y distancias | Se registran recorridos con línea, unidad, piloto y estaciones visitadas, y se consulta la distancia recorrida. | CP-24, CP-25, CP-27 y CP-28 aprobados. | Aprobado | CP-24 a CP-28; `RecorridosTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-11 | RF-11 Seguimiento operativo | Por unidad en ruta: línea, estado, estación actual, próxima estación, ocupación, hora y alertas. | CP-26 aprobado. | Aprobado | CP-26; `SeguimientoTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-12 | RF-12 Capacidad de pasajeros | Cada bus tiene capacidad registrada y la ocupación se calcula con ella. | CP-30 y CP-31 aprobados. | Aprobado | CP-30, CP-31 | 22/10/2026 | Maximiliano Ajca Us |
| CA-13 | RF-13 Alertas | Alerta con 150 % o más en espera y con ocupación menor al 25 % (5 minutos); se ven en la aplicación. | CP-29 a CP-33 aprobados. | Aprobado | CP-29 a CP-33; `AlertasAutomaticasTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-14 | RF-14 Tarjeta Transmetro | Código único, titular, saldo, estado y fecha; se verifica el estado antes de operar. | CP-36, CP-38 y CP-40 aprobados. | Aprobado | CP-36, CP-38, CP-40; `TarjetasPagosTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-15 | RF-15 Recargas y pagos | Recargas y pagos actualizan el saldo y quedan en el historial con fecha, hora, monto y tipo. | CP-34, CP-35, CP-37 y CP-39 aprobados. | Aprobado | CP-34, CP-35, CP-37, CP-39 | 22/10/2026 | Maximiliano Ajca Us |
| CA-16 | RF-16 Reportes | Se generan dentro de la aplicación los reportes de líneas, estaciones y buses asignados. | CP-41 a CP-44 aprobados. | Aprobado | CP-41 a CP-44; `ReportesTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-17 | Seguridad | 100 %: pantallas privadas con sesión y rol, contraseñas cifradas, formularios protegidos. | CP-01 a CP-06, CP-49 y CP-50 aprobados; los formularios sin token CSRF se rechazan. | Aprobado | CP-01 a CP-06, CP-49, CP-50; `SeguridadTest`, `PermisosOperadorTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-18 | Integridad de datos | 100 %: la aplicación valida y la base de datos rechaza lo que incumple sus reglas. | CP-07, CP-10, CP-15, CP-17, CP-20 y CP-23 aprobados; los 4 triggers rechazan los datos inválidos aunque no pasen por la aplicación (CP-37). | Aprobado | CP-07, CP-10, CP-15, CP-17, CP-20, CP-23, CP-37; `BaseDePruebasTest` | 22/10/2026 | Maximiliano Ajca Us |
| CA-19 | Usabilidad | Mensajes claros; un usuario completa 9 de cada 10 tareas principales sin ayuda. | Los mensajes son claros (CP-10, CP-17). Falta la prueba con un usuario que haga las tareas sin ayuda. | Pendiente | CP-10, CP-17 | 22/10/2026 | Maximiliano Ajca Us |
| CA-20 | Rendimiento | 2 segundos o menos. | Listado de buses: promedio 0.39 s; la suite de 263 pruebas corre en unos 23 s. | Aprobado | CP-46 | 22/10/2026 | Maximiliano Ajca Us |
| CA-21 | Fiabilidad | 95 % o más de casos aprobados. | 49 de 50 aprobados (98 %); 0 fallidos. | Aprobado | Esta tabla | 22/10/2026 | Maximiliano Ajca Us |
| CA-22 | Defectos | Ningún defecto crítico o alto sin corregir. | Los 6 defectos encontrados (1 alto) están cerrados. | Aprobado | Registro de defectos (abajo) | 22/10/2026 | Maximiliano Ajca Us |
| CA-23 | Compatibilidad | Funciona en Chrome, Edge y Firefox. | Chromium (motor de Chrome) y Edge correctos; falta Firefox (CP-48). | Pendiente | CP-48 | 22/10/2026 | Maximiliano Ajca Us |
| CA-24 | Mantenibilidad | Estructura MVC de Laravel y versionado en GitHub. | Modelos, controladores y vistas de Laravel, con servicios y reportes en módulos aparte; repositorio en GitHub con ramas `main` y `desarrollo`. | Aprobado | Repositorio | 22/10/2026 | Maximiliano Ajca Us |

Reglas de negocio (sección 16.2): las 18 se cumplen; las 5 críticas (RN-05, RN-08, RN-09/RN-10, RN-11 y RN-18) se verificaron con CP-16, CP-15, CP-17/CP-18, CP-14 y CP-35 a CP-37. El detalle de cada regla está en [`trazabilidad.md`](trazabilidad.md).

## Registro de defectos

Defectos encontrados al comparar el sistema con el plan antes de esta ejecución. Todos se corrigieron y se cerraron al volver a ejecutar su caso.

| ID | Caso | Descripción | Severidad | Estado |
|---|---|---|---|---|
| D-01 | CP-38 | El operador podía emitir tarjetas y bloquearlas; no se podía suspender una tarjeta. | Alta | Cerrado |
| D-02 | CP-42 | El reporte de estaciones no mostraba la municipalidad. | Media | Cerrado |
| D-03 | CP-43 | El reporte de buses asignados no incluía los buses sin línea. | Media | Cerrado |
| D-04 | CP-08 | Al crear una estación no se podía elegir su posición en la línea; entraba al final y había que moverla con ↑/↓. | Media | Cerrado |
| D-05 | CP-07, CP-20, CP-23 | El mensaje de valor duplicado no decía "ya existe un registro con ese valor". | Baja | Cerrado |
| D-06 | CP-03 | Un usuario desactivado veía el mensaje genérico de credenciales en lugar del de usuario desactivado. | Baja | Cerrado |

## Pendientes

- **CP-48 y CA-23:** abrir las pantallas principales en Firefox (y en Google Chrome) en un equipo que los tenga instalados.
- **CA-19:** prueba de usabilidad con un usuario que no conozca el sistema, contando cuántas de las tareas principales completa sin ayuda.
