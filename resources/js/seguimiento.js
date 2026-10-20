// Seguimiento en vivo con Leaflet y OpenStreetMap (sin claves de API).
// Pide /seguimiento/datos cada 3 s (solo con la pestaña visible) y mueve cada bus en cada cuadro
// interpolando entre estaciones con el mismo horario que calcula el servidor: sin saltos.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { notificar } from './notificaciones';

const INTERVALO_MS = 3000;
const CENTRO = [14.615, -90.53];
const ICONO_BUS = 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12';

const escapar = (texto) => String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function colorOcupacion(porcentaje) {
    if (porcentaje >= 85) return { barra: 'bg-rose-500', texto: 'text-rose-700' };
    if (porcentaje >= 60) return { barra: 'bg-amber-400', texto: 'text-amber-700' };
    return { barra: 'bg-emerald-500', texto: 'text-emerald-700' };
}

function barraOcupacion(bus) {
    const color = colorOcupacion(bus.ocupacion);
    return `<div class="mt-1 flex items-center gap-2">
        <div class="h-2 flex-1 rounded-full bg-slate-200" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${bus.ocupacion}" aria-label="Ocupación ${bus.ocupacion} %">
            <div class="h-2 rounded-full ${color.barra}" style="width:${Math.min(100, bus.ocupacion)}%"></div>
        </div>
        <span class="text-xs font-semibold ${color.texto}">${bus.ocupacion} %</span>
    </div>`;
}

function listaAlertas(alertas) {
    if (!alertas?.length) return '';
    return `<ul class="mt-2 space-y-1.5">${alertas.map((a) => `
        <li class="rounded-lg bg-amber-50 p-2 text-xs ring-1 ring-amber-200">
            <p class="font-semibold text-amber-800">${escapar(a.tipo_texto)}</p>
            <p class="text-amber-700">${escapar(a.mensaje)}</p>
            <div class="mt-1 flex gap-2">
                ${a.resolver ? `<a href="${escapar(a.resolver.url)}" class="rounded bg-celeste-500 px-2 py-0.5 font-semibold text-white hover:bg-celeste-600">${escapar(a.resolver.texto)}</a>` : ''}
                <a href="${escapar(a.url)}" class="rounded px-2 py-0.5 font-semibold text-celeste-700 ring-1 ring-celeste-200 hover:bg-celeste-50">Ver</a>
            </div>
        </li>`).join('')}</ul>`;
}

function popupBus(bus, ahora) {
    const ubicacion = bus.en_estacion
        ? `En ${escapar(bus.estacion_actual ?? 'la primera estación')}`
        : `Entre ${escapar(bus.estacion_actual)} y ${escapar(bus.proxima_estacion)}`;
    return `<div class="w-64 text-sm">
        <p class="flex items-center gap-2 text-base font-bold text-slate-900">
            ${escapar(bus.codigo)}
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-white" style="background:${escapar(bus.color)}">${escapar(bus.linea)}</span>
        </p>
        <dl class="mt-2 space-y-1 text-slate-700">
            <div><dt class="inline text-slate-500">Piloto:</dt> <dd class="inline">${escapar(bus.piloto)}</dd></div>
            <div><dt class="inline text-slate-500">Ubicación:</dt> <dd class="inline">${ubicacion}</dd></div>
            <div><dt class="inline text-slate-500">Próxima estación:</dt> <dd class="inline">${escapar(bus.proxima_estacion ?? 'fin del recorrido')}</dd></div>
            <div><dt class="text-slate-500">Ocupación: ${bus.pasajeros} de ${bus.capacidad} pasajeros</dt><dd>${barraOcupacion(bus)}</dd></div>
            <div class="text-xs text-slate-400">Actualizado ${new Date(ahora * 1000).toLocaleTimeString('es-GT')}${bus.actualizado ? ` · última llegada ${escapar(bus.actualizado)}` : ''}</div>
        </dl>
        ${listaAlertas(bus.alertas)}
        ${bus.url ? `<a href="${escapar(bus.url)}" class="mt-2 inline-block text-xs font-semibold text-celeste-700 hover:underline">Ver recorrido #${bus.recorrido_id}</a>` : ''}
    </div>`;
}

function popupEstacion(estacion) {
    const espera = estacion.en_espera
        ? `${estacion.en_espera.cantidad} personas esperando (registro de las ${escapar(estacion.en_espera.hora)})`
        : 'Sin registro de pasajeros en espera';
    return `<div class="w-60 text-sm">
        <p class="text-base font-bold text-slate-900">${escapar(estacion.nombre)}</p>
        <p class="text-xs text-slate-500">Líneas ${escapar(estacion.lineas.join(', '))}</p>
        <p class="mt-2 text-slate-700">${espera}</p>
        ${listaAlertas(estacion.alertas)}
        ${estacion.url ? `<a href="${escapar(estacion.url)}" class="mt-2 inline-block text-xs font-semibold text-celeste-700 hover:underline">Ver estación</a>` : ''}
    </div>`;
}

function iconoEstacion(estacion, miEstacion) {
    const pulso = estacion.alertas.length ? (estacion.alertas.some((a) => a.grave) ? 'pulso-rojo' : 'pulso-ambar') : '';
    return L.divIcon({
        className: '',
        html: `<span class="estacion-marcador ${pulso} ${miEstacion ? 'mi-estacion' : ''}" style="--color:${escapar(estacion.color)}"></span>`,
        iconSize: miEstacion ? [22, 22] : [14, 14],
        iconAnchor: miEstacion ? [11, 11] : [7, 7],
    });
}

function iconoBus(bus) {
    const pulso = bus.alertas.length ? (bus.alertas.some((a) => a.grave) ? 'pulso-rojo' : 'pulso-ambar') : '';
    return L.divIcon({
        className: '',
        html: `<span class="bus-marcador ${pulso}" style="--color:${escapar(bus.color)}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="${ICONO_BUS}"/></svg>
            <span>${escapar(bus.codigo)}</span>
        </span>`,
        iconSize: [86, 26],
        iconAnchor: [13, 13],
        popupAnchor: [30, -10],
    });
}

function iniciarMapa(elemento) {
    const mini = elemento.dataset.mapaSeguimiento === 'mini';
    const urls = { datos: elemento.dataset.urlDatos, avanzar: elemento.dataset.urlAvanzar, simulacion: elemento.dataset.urlSimulacion };
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    const mapa = L.map(elemento, { zoomControl: !mini, scrollWheelZoom: !mini }).setView(CENTRO, mini ? 11 : 13);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(mapa);

    const estado = { datos: null, desfase: 0, sinSesion: false, lineasDibujadas: false, estaciones: new Map(), buses: new Map(), filtro: '', temporizador: null };

    // --- Dibujo ---
    function dibujarLineas(lineas) {
        const puntos = [];
        lineas.forEach((linea) => {
            if (linea.trazo.length < 2) return;
            L.polyline(linea.trazo.map((p) => [p.lat, p.lng]), { color: linea.color, weight: mini ? 4 : 6, opacity: 0.8 }).addTo(mapa)
                .bindTooltip(`${linea.codigo} · ${linea.nombre}`, { sticky: true });
            puntos.push(...linea.trazo.map((p) => [p.lat, p.lng]));
        });
        if (puntos.length) mapa.fitBounds(puntos, { padding: [24, 24] });

        if (!mini) {
            const leyenda = L.control({ position: 'bottomleft' });
            leyenda.onAdd = () => {
                const caja = L.DomUtil.create('div', 'rounded-xl bg-white/95 p-3 text-xs shadow ring-1 ring-celeste-100');
                caja.innerHTML = `<p class="mb-1 font-semibold text-slate-700">Líneas</p>${lineas.map((l) => `
                    <p class="flex items-center gap-2"><span class="inline-block h-1.5 w-6 rounded-full" style="background:${escapar(l.color)}"></span>${escapar(l.codigo)} · ${escapar(l.nombre)}</p>`).join('')}
                    <p class="mt-2 flex items-center gap-2"><span class="estacion-marcador pulso-ambar" style="--color:#f59e0b"></span>Con alerta</p>`;
                return caja;
            };
            leyenda.addTo(mapa);
        }
        estado.lineasDibujadas = true;
    }

    function actualizarEstaciones(estaciones, miEstacionId) {
        estaciones.forEach((estacion) => {
            const miEstacion = estacion.id === miEstacionId;
            let marcador = estado.estaciones.get(estacion.id);
            if (!marcador) {
                marcador = L.marker([estacion.lat, estacion.lng], { icon: iconoEstacion(estacion, miEstacion), keyboard: true, title: estacion.nombre })
                    .addTo(mapa)
                    .bindTooltip(miEstacion ? `${estacion.nombre} · Mi estación` : estacion.nombre, { direction: 'top', offset: [0, -8], permanent: miEstacion });
                if (!mini) marcador.bindPopup(() => popupEstacion(estado.estaciones.get(estacion.id).datos));
                estado.estaciones.set(estacion.id, marcador);
            } else {
                marcador.setIcon(iconoEstacion(estacion, miEstacion));
            }
            marcador.datos = estacion;
            if (marcador.isPopupOpen()) marcador.setPopupContent(popupEstacion(estacion));
        });
    }

    function actualizarBuses(buses) {
        const vigentes = new Set();
        buses.forEach((bus) => {
            vigentes.add(bus.recorrido_id);
            let marcador = estado.buses.get(bus.recorrido_id);
            if (!marcador) {
                marcador = L.marker([bus.posicion.lat, bus.posicion.lng], { icon: iconoBus(bus), zIndexOffset: 1000, keyboard: true, title: bus.codigo }).addTo(mapa);
                if (!mini) marcador.bindPopup(() => popupBus(estado.buses.get(bus.recorrido_id).datos, estado.datos.ahora));
                estado.buses.set(bus.recorrido_id, marcador);
            } else if ((marcador.datos.alertas.length > 0) !== (bus.alertas.length > 0)) {
                marcador.setIcon(iconoBus(bus));
            }
            marcador.datos = bus;
            // También al recibir datos, por si el navegador pausó la animación.
            ubicar(marcador);
            if (marcador.isPopupOpen()) marcador.setPopupContent(popupBus(bus, estado.datos.ahora));
        });
        estado.buses.forEach((marcador, id) => {
            if (!vigentes.has(id)) {
                marcador.remove();
                estado.buses.delete(id);
            }
        });
    }

    // Posición del bus en este instante con el horario del tramo y el reloj del servidor (la misma fórmula del servidor).
    function ubicar(marcador) {
        const { tramo, posicion } = marcador.datos;
        if (!tramo) {
            marcador.setLatLng([posicion.lat, posicion.lng]);
            return;
        }
        const ahora = Date.now() / 1000 + estado.desfase;
        const total = tramo.llegada - tramo.salida;
        const fraccion = total > 0 ? Math.min(1, Math.max(0, (ahora - tramo.salida) / total)) : 1;
        marcador.setLatLng([
            tramo.desde.lat + (tramo.hacia.lat - tramo.desde.lat) * fraccion,
            tramo.desde.lng + (tramo.hacia.lng - tramo.desde.lng) * fraccion,
        ]);
    }

    // Movimiento suave: se reubica cada bus en cada cuadro de animación.
    function animar() {
        estado.buses.forEach(ubicar);
        requestAnimationFrame(animar);
    }

    // --- Panel lateral ---
    function pintarPanel(datos) {
        const lista = document.querySelector('[data-lista-buses]');
        if (lista) {
            const buses = datos.buses.filter((b) => !estado.filtro || String(b.linea_id) === estado.filtro);
            lista.innerHTML = buses.length ? buses.map((bus) => `
                <li>
                    <button type="button" data-volar="${bus.recorrido_id}" class="clicable block w-full rounded-xl px-3 py-2 text-left hover:bg-celeste-50 hover:shadow-none">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">${escapar(bus.codigo)}${bus.alertas.length ? ' <span class="text-amber-600" title="Con alerta">●</span>' : ''}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold text-white" style="background:${escapar(bus.color)}">${escapar(bus.linea)}</span>
                        </span>
                        <span class="block text-xs text-slate-500">${escapar(bus.estacion_actual ?? 'Saliendo')} → ${escapar(bus.proxima_estacion ?? 'fin')}</span>
                        ${barraOcupacion(bus)}
                    </button>
                </li>`).join('') : '<li class="px-3 py-4 text-sm text-slate-500">No hay buses en ruta.</li>';
        }
        const contador = document.querySelector('[data-contador-buses]');
        if (contador) contador.textContent = datos.buses.length;

        const alertas = document.querySelector('[data-lista-alertas]');
        if (alertas) {
            alertas.innerHTML = datos.alertas.length
                ? datos.alertas.map((a) => `<li class="text-sm">${listaAlertas([a])}</li>`).join('')
                : '<li class="px-1 py-2 text-sm text-slate-500">Sin alertas activas.</li>';
        }
        pintarSimulacion(datos.simulacion);
    }

    function pintarSimulacion(simulacion) {
        document.querySelectorAll('[data-estado-simulacion]').forEach((chip) => {
            chip.textContent = simulacion.activa ? `Simulación activa · ${simulacion.propios} buses` : 'Simulación detenida';
            chip.classList.toggle('bg-emerald-100', simulacion.activa);
            chip.classList.toggle('text-emerald-700', simulacion.activa);
            chip.classList.toggle('bg-slate-100', !simulacion.activa);
            chip.classList.toggle('text-slate-600', !simulacion.activa);
        });
        const boton = document.querySelector('[data-boton-simulacion]');
        if (boton) {
            boton.textContent = simulacion.activa ? 'Detener simulación' : 'Iniciar simulación';
            boton.dataset.accion = simulacion.activa ? 'detener' : 'iniciar';
        }
    }

    function volarA(recorridoId) {
        const marcador = estado.buses.get(Number(recorridoId));
        if (!marcador) return;
        mapa.flyTo(marcador.getLatLng(), 16, { duration: 1 });
        mapa.once('moveend', () => marcador.openPopup());
        document.querySelector('[data-panel-seguimiento]')?.removeAttribute('data-abierto');
    }

    // --- Datos ---
    async function consultar() {
        try {
            if (estado.datos?.simulacion.activa) {
                await fetch(urls.avanzar, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } });
            }
            const respuesta = await fetch(urls.datos, { headers: { Accept: 'application/json' } });
            if (respuesta.status === 401 || respuesta.redirected) {
                // Sesión vencida o usuario desactivado: deja de consultar.
                estado.sinSesion = true;
                return;
            }
            if (!respuesta.ok) return;
            const datos = await respuesta.json();
            estado.desfase = datos.ahora - Date.now() / 1000;
            estado.datos = datos;

            if (!estado.lineasDibujadas) dibujarLineas(datos.lineas);
            actualizarEstaciones(datos.estaciones, datos.usuario.estacion_id);
            actualizarBuses(datos.buses);
            if (!mini) pintarPanel(datos);
        } catch {
            // Sin conexión: se reintenta en la siguiente vuelta.
        } finally {
            programar();
        }
    }

    function programar() {
        clearTimeout(estado.temporizador);
        if (!document.hidden && !estado.sinSesion) estado.temporizador = setTimeout(consultar, INTERVALO_MS);
    }

    function detener() {
        clearTimeout(estado.temporizador);
        estado.temporizador = null;
    }

    // Con la pestaña oculta no se consulta; al volver se actualiza de inmediato.
    document.addEventListener('visibilitychange', () => (document.hidden || estado.sinSesion ? detener() : consultar()));

    if (!mini) {
        document.querySelector('[data-filtro-linea]')?.addEventListener('change', (evento) => {
            estado.filtro = evento.target.value;
            if (estado.datos) pintarPanel(estado.datos);
        });
        document.querySelector('[data-lista-buses]')?.addEventListener('click', (evento) => {
            const boton = evento.target.closest('[data-volar]');
            if (boton) volarA(boton.dataset.volar);
        });
        document.querySelector('[data-alternar-panel]')?.addEventListener('click', () => {
            const panel = document.querySelector('[data-panel-seguimiento]');
            panel.toggleAttribute('data-abierto');
        });
        document.querySelector('[data-boton-simulacion]')?.addEventListener('click', async (evento) => {
            const boton = evento.currentTarget;
            boton.disabled = true;
            try {
                const respuesta = await fetch(urls.simulacion, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ accion: boton.dataset.accion, buses: Number(document.querySelector('[data-buses-simulacion]')?.value || 6) }),
                });
                const datos = await respuesta.json();
                notificar(respuesta.ok ? 'exito' : 'error', '', datos.mensaje ?? datos.message);
                await consultar();
            } finally {
                boton.disabled = false;
            }
        });
    }

    requestAnimationFrame(animar);
    consultar();
}

document.querySelectorAll('[data-mapa-seguimiento]').forEach(iniciarMapa);
