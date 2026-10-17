// Ventana de confirmación propia (reemplaza window.confirm). Marcado en resources/views/components/confirmacion.blade.php.
const dialogo = document.querySelector('[data-confirmacion]');

/**
 * Abre la ventana y resuelve true si el usuario acepta.
 */
export function confirmar({ titulo = '¿Está seguro?', mensaje = '', boton = 'Sí, eliminar', tipo = 'peligro' } = {}) {
    if (!dialogo) {
        return Promise.resolve(window.confirm([titulo, mensaje].filter(Boolean).join('\n')));
    }

    dialogo.querySelector('[data-confirmacion-titulo]').textContent = titulo;
    dialogo.querySelector('[data-confirmacion-mensaje]').textContent = mensaje;
    const aceptar = dialogo.querySelector('[data-confirmacion-aceptar]');
    aceptar.textContent = boton;
    // 'peligro' (rojo) para eliminar; 'normal' (celeste) para confirmar operaciones como una recarga.
    aceptar.className = tipo === 'normal'
        ? 'btn-primario'
        : 'btn bg-rose-600 text-white shadow-sm shadow-rose-200 hover:bg-rose-700 focus-visible:ring-rose-400';
    const icono = dialogo.querySelector('[data-confirmacion-icono]');
    icono?.classList.toggle('bg-rose-100', tipo !== 'normal');
    icono?.classList.toggle('text-rose-600', tipo !== 'normal');
    icono?.classList.toggle('bg-celeste-100', tipo === 'normal');
    icono?.classList.toggle('text-celeste-600', tipo === 'normal');
    dialogo.returnValue = '';
    dialogo.showModal();

    return new Promise((resolver) => {
        dialogo.addEventListener('close', () => resolver(dialogo.returnValue === 'aceptar'), { once: true });
    });
}

window.confirmar = confirmar;

if (dialogo) {
    // Clic en el fondo oscurecido: el evento llega al <dialog> y no a su contenido. Esc lo cierra el navegador.
    dialogo.addEventListener('click', (evento) => {
        if (evento.target === dialogo) {
            dialogo.close('cancelar');
        }
    });

    atraparFoco(dialogo);
}

/**
 * Mantiene el foco dentro de una ventana <dialog> mientras está abierta.
 */
export function atraparFoco(ventana) {
    ventana.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Tab') {
            return;
        }
        const enfocables = [...ventana.querySelectorAll('button:not([disabled]), a[href], input, select, textarea')];
        const primero = enfocables[0];
        const ultimo = enfocables.at(-1);

        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    });
}

// Formularios con data-confirmar (x-eliminar y acciones delicadas). Opcionales: data-confirmar-titulo y data-confirmar-boton.
document.addEventListener('submit', async (evento) => {
    const formulario = evento.target;
    const mensaje = formulario.dataset?.confirmar;
    if (!mensaje) {
        return;
    }
    if (formulario.dataset.confirmado === 'si') {
        delete formulario.dataset.confirmado;
        return;
    }

    evento.preventDefault();
    const boton = evento.submitter;
    const aceptado = await confirmar({
        titulo: formulario.dataset.confirmarTitulo,
        mensaje,
        boton: formulario.dataset.confirmarBoton,
        tipo: formulario.dataset.confirmarTipo,
    });

    if (aceptado) {
        formulario.dataset.confirmado = 'si';
        boton?.setAttribute('disabled', '');
        formulario.requestSubmit();
    }
});
