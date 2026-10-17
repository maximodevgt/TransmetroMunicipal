// Notificaciones flotantes (toasts). Las plantillas están en resources/views/components/notificaciones.blade.php.
const DURACION_MS = 5000;
const MAXIMO_VISIBLES = 5;
const TITULOS = {
    exito: 'Operación exitosa',
    error: 'No se pudo completar',
    advertencia: 'Atención',
    info: 'Información',
};

const contenedor = document.querySelector('[data-notificaciones]');

function cerrar(toast) {
    if (!toast.isConnected || toast.dataset.estado === 'saliendo') {
        return;
    }
    toast.dataset.estado = 'saliendo';
    toast.addEventListener('transitionend', () => toast.remove(), { once: true });
    // Respaldo si no hay transición (movimiento reducido o pestaña en segundo plano).
    setTimeout(() => toast.remove(), 400);
}

// Cierre automático que se pausa mientras el puntero o el foco están sobre la notificación.
function programarCierre(toast) {
    let restante = DURACION_MS;
    let inicio = 0;
    let temporizador = null;
    const motivosPausa = new Set();

    const reanudar = (motivo) => {
        motivosPausa.delete(motivo);
        if (motivosPausa.size > 0 || temporizador) {
            return;
        }
        delete toast.dataset.pausado;
        inicio = Date.now();
        temporizador = setTimeout(() => cerrar(toast), restante);
    };
    const pausar = (motivo) => {
        motivosPausa.add(motivo);
        if (!temporizador) {
            return;
        }
        clearTimeout(temporizador);
        temporizador = null;
        restante -= Date.now() - inicio;
        toast.dataset.pausado = '';
    };

    toast.addEventListener('mouseenter', () => pausar('puntero'));
    toast.addEventListener('mouseleave', () => reanudar('puntero'));
    toast.addEventListener('focusin', () => pausar('foco'));
    toast.addEventListener('focusout', (evento) => {
        if (!toast.contains(evento.relatedTarget)) {
            reanudar('foco');
        }
    });
    reanudar();
}

/**
 * Muestra una notificación flotante.
 * tipo: 'exito' | 'error' | 'advertencia' | 'info'. opciones.enlace: { url, texto } agrega un botón.
 */
export function notificar(tipo, titulo, mensaje, opciones = {}) {
    const plantilla = document.querySelector(`template[data-plantilla-toast="${tipo}"]`)
        ?? document.querySelector('template[data-plantilla-toast="info"]');
    if (!contenedor || !plantilla) {
        return null;
    }

    const toast = plantilla.content.firstElementChild.cloneNode(true);
    toast.querySelector('[data-toast-titulo]').textContent = titulo || TITULOS[tipo] || TITULOS.info;

    const parrafo = toast.querySelector('[data-toast-mensaje]');
    parrafo.textContent = mensaje ?? '';
    parrafo.hidden = !mensaje;

    if (opciones.enlace?.url) {
        const enlace = toast.querySelector('[data-toast-enlace]');
        enlace.href = opciones.enlace.url;
        enlace.textContent = opciones.enlace.texto ?? 'Ver';
        enlace.hidden = false;
    }

    toast.style.setProperty('--duracion-toast', `${DURACION_MS}ms`);
    toast.querySelector('[data-toast-cerrar]').addEventListener('click', () => cerrar(toast));
    toast.dataset.estado = 'entrando';
    contenedor.prepend(toast);

    // La más antigua se va si ya hay demasiadas en pantalla.
    const visibles = contenedor.querySelectorAll('[data-toast]:not([data-estado="saliendo"])');
    [...visibles].slice(MAXIMO_VISIBLES).forEach(cerrar);

    // Forzar el cálculo de estilos para que la transición de entrada arranque desde fuera de la pantalla.
    toast.getBoundingClientRect();
    delete toast.dataset.estado;
    programarCierre(toast);

    return toast;
}

window.notificar = notificar;

// Mensajes de sesión que dejó el servidor (session('ok'), session('error'), etc.).
const iniciales = document.querySelector('[data-notificaciones-iniciales]');
if (iniciales) {
    try {
        JSON.parse(iniciales.textContent).forEach(({ tipo, titulo, mensaje }) => notificar(tipo, titulo, mensaje));
    } catch {
        // JSON inválido: no se muestran avisos, la página sigue funcionando.
    }
}
