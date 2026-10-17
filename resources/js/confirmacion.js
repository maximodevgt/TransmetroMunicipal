// Ventana de confirmación propia (reemplaza window.confirm). Marcado en resources/views/components/confirmacion.blade.php.
const dialogo = document.querySelector('[data-confirmacion]');

/**
 * Abre la ventana y resuelve true si el usuario acepta.
 */
export function confirmar({ titulo = '¿Está seguro?', mensaje = '', boton = 'Sí, eliminar' } = {}) {
    if (!dialogo) {
        return Promise.resolve(window.confirm([titulo, mensaje].filter(Boolean).join('\n')));
    }

    dialogo.querySelector('[data-confirmacion-titulo]').textContent = titulo;
    dialogo.querySelector('[data-confirmacion-mensaje]').textContent = mensaje;
    dialogo.querySelector('[data-confirmacion-aceptar]').textContent = boton;
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
    });

    if (aceptado) {
        formulario.dataset.confirmado = 'si';
        boton?.setAttribute('disabled', '');
        formulario.requestSubmit();
    }
});
