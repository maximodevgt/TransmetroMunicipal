// Alertas del Transmetro en vivo: campana con contador, panel con las últimas 5 y aviso al llegar una nueva.
import { notificar } from './notificaciones';

const INTERVALO_MS = 15000;
const CLAVE_ULTIMO_ID = 'transmetro.alertas.ultimoId';
const CLAVE_SONIDO = 'transmetro.alertas.sonido';

const campanas = [...document.querySelectorAll('[data-campana]')];

// El almacenamiento del navegador puede no estar disponible (modo privado o bloqueado).
function leer(almacen, clave) {
    try {
        return window[almacen].getItem(clave);
    } catch {
        return null;
    }
}

function guardar(almacen, clave, valor) {
    try {
        window[almacen].setItem(clave, valor);
    } catch {
        // Sin almacenamiento solo se pierde la preferencia o el último ID visto.
    }
}

// Sonido corto generado con Web Audio (sin archivos). Desactivado por defecto.
let audio = null;

function sonidoActivo() {
    return leer('localStorage', CLAVE_SONIDO) === 'si';
}

function prepararAudio() {
    if (!audio && window.AudioContext) {
        audio = new AudioContext();
    }
    audio?.resume();
}

function sonar() {
    if (!audio || audio.state !== 'running') {
        return;
    }
    const oscilador = audio.createOscillator();
    const volumen = audio.createGain();
    const ahora = audio.currentTime;
    oscilador.frequency.value = 880;
    volumen.gain.setValueAtTime(0.0001, ahora);
    volumen.gain.exponentialRampToValueAtTime(0.2, ahora + 0.02);
    volumen.gain.exponentialRampToValueAtTime(0.0001, ahora + 0.35);
    oscilador.connect(volumen).connect(audio.destination);
    oscilador.start(ahora);
    oscilador.stop(ahora + 0.4);
}

function pintarCampana(campana, datos) {
    const contador = campana.querySelector('[data-campana-contador]');
    contador.textContent = datos.activas > 99 ? '99+' : String(datos.activas);
    contador.hidden = datos.activas === 0;
    campana.querySelector('[data-campana-boton]').setAttribute(
        'aria-label',
        datos.activas === 1 ? 'Alertas activas: 1' : `Alertas activas: ${datos.activas}`,
    );

    const lista = campana.querySelector('[data-campana-lista]');
    const plantilla = campana.querySelector('template[data-plantilla-alerta]');
    lista.replaceChildren();

    if (datos.recientes.length === 0) {
        const vacio = document.createElement('li');
        vacio.className = 'px-4 py-6 text-center text-sm text-slate-500';
        vacio.textContent = 'No hay alertas activas.';
        lista.append(vacio);
        return;
    }

    datos.recientes.forEach((alerta) => {
        const elemento = plantilla.content.firstElementChild.cloneNode(true);
        elemento.querySelector('[data-alerta-ver]').href = alerta.url;
        const resolver = elemento.querySelector('[data-alerta-resolver]');
        if (alerta.resolver) {
            resolver.href = alerta.resolver.url;
            resolver.textContent = alerta.resolver.texto;
            resolver.hidden = false;
        }
        elemento.querySelector('[data-alerta-tipo]').textContent = alerta.tipo_texto;
        elemento.querySelector('[data-alerta-lugar]').textContent = [alerta.lugar, alerta.fecha].filter(Boolean).join(' · ');
        elemento.querySelector('[data-alerta-mensaje]').textContent = alerta.mensaje;
        lista.append(elemento);
    });
}

let intervalo = null;

async function consultar() {
    const url = new URL(campanas[0].dataset.url, window.location.origin);
    const ultimoId = leer('sessionStorage', CLAVE_ULTIMO_ID);
    if (ultimoId !== null) {
        url.searchParams.set('desde', ultimoId);
    }

    let datos;
    try {
        const respuesta = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        // Sesión vencida o usuario desactivado: se deja de consultar.
        if ([401, 403, 419].includes(respuesta.status) || respuesta.redirected) {
            clearInterval(intervalo);
            return;
        }
        if (!respuesta.ok) {
            return;
        }
        datos = await respuesta.json();
    } catch {
        return; // Sin red: se reintenta en la siguiente vuelta.
    }

    campanas.forEach((campana) => pintarCampana(campana, datos));

    datos.nuevas.forEach((alerta) => {
        notificar('advertencia', `Alerta: ${alerta.tipo_texto}`, [alerta.lugar, alerta.mensaje].filter(Boolean).join(' — '), {
            enlaces: [alerta.resolver, { url: alerta.url, texto: 'Ver' }],
        });
    });
    if (datos.nuevas.length > 0 && sonidoActivo()) {
        sonar();
    }

    guardar('sessionStorage', CLAVE_ULTIMO_ID, String(datos.ultimo_id));
}

function iniciarPanel(campana) {
    const boton = campana.querySelector('[data-campana-boton]');
    const panel = campana.querySelector('[data-campana-panel]');

    const alternar = (abrir) => {
        panel.hidden = !abrir;
        boton.setAttribute('aria-expanded', String(abrir));
    };

    boton.addEventListener('click', () => alternar(panel.hidden));
    document.addEventListener('click', (evento) => {
        if (!campana.contains(evento.target)) {
            alternar(false);
        }
    });
    campana.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && !panel.hidden) {
            alternar(false);
            boton.focus();
        }
    });
}

if (campanas.length > 0) {
    campanas.forEach(iniciarPanel);

    const interruptores = document.querySelectorAll('[data-sonido-alertas]');
    interruptores.forEach((interruptor) => {
        interruptor.checked = sonidoActivo();
        interruptor.addEventListener('change', () => {
            guardar('localStorage', CLAVE_SONIDO, interruptor.checked ? 'si' : 'no');
            interruptores.forEach((otro) => (otro.checked = interruptor.checked));
            if (interruptor.checked) {
                prepararAudio();
                sonar();
            }
        });
    });

    // El navegador solo permite sonido después de una interacción del usuario en la página.
    ['pointerdown', 'keydown'].forEach((tipo) => {
        document.addEventListener(tipo, () => sonidoActivo() && prepararAudio(), { once: true });
    });

    consultar();
    intervalo = setInterval(() => {
        if (!document.hidden) {
            consultar();
        }
    }, INTERVALO_MS);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            consultar();
        }
    });
}
