// "Atender" abre una ventana con el campo "¿Qué se hizo?" antes de enviar.
// Los botones llevan data-atender y están dentro de un formulario PATCH a alertas.atender (sin JS se envía directo).
import { atraparFoco } from './confirmacion';

const dialogo = document.querySelector('[data-dialogo-atender]');

if (dialogo) {
    const formulario = dialogo.querySelector('[data-dialogo-atender-formulario]');
    const nota = formulario.querySelector('textarea[name="nota"]');

    document.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-atender]');
        if (!boton?.form) {
            return;
        }
        evento.preventDefault();
        formulario.action = boton.form.action;
        dialogo.querySelector('[data-dialogo-atender-alerta]').textContent = boton.dataset.atender || '';
        nota.value = '';
        dialogo.showModal();
        nota.focus();
    });

    dialogo.querySelector('[data-dialogo-atender-cancelar]').addEventListener('click', () => dialogo.close());
    // Clic en el fondo oscurecido: cierra sin atender. Esc lo cierra el navegador.
    dialogo.addEventListener('click', (evento) => {
        if (evento.target === dialogo) {
            dialogo.close();
        }
    });
    formulario.addEventListener('submit', () => formulario.querySelector('button:not([type="button"])').setAttribute('disabled', ''));
    atraparFoco(dialogo);
}
