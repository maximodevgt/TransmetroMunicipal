// Validador de torniquete: se escribe o "pasa" el código y responde en grande (verde o rojo).
const panel = document.querySelector('[data-torniquete]');

if (panel) {
    const formulario = panel.querySelector('form');
    const codigo = formulario.querySelector('[name="codigo"]');
    const resultado = panel.querySelector('[data-resultado]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let reinicio = null;

    const mostrar = ({ aprobado, titulo, mensaje }) => {
        resultado.hidden = false;
        resultado.dataset.aprobado = String(aprobado);
        resultado.className = `rounded-3xl p-10 text-center text-white shadow-xl transition ${aprobado ? 'bg-emerald-500' : 'bg-rose-600'}`;
        resultado.querySelector('[data-resultado-titulo]').textContent = titulo;
        resultado.querySelector('[data-resultado-mensaje]').textContent = mensaje;
        resultado.querySelector('[data-resultado-icono]').textContent = aprobado ? '✓' : '✕';
        clearTimeout(reinicio);
        reinicio = setTimeout(() => {
            resultado.hidden = true;
            codigo.value = '';
            codigo.focus();
        }, 4000);
    };

    const pasar = async () => {
        if (!codigo.value.trim()) {
            codigo.focus();
            return;
        }
        try {
            const respuesta = await fetch(formulario.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ codigo: codigo.value }),
            });
            const datos = await respuesta.json();
            mostrar(respuesta.ok ? datos : { aprobado: false, titulo: 'Error', mensaje: datos.message ?? 'No se pudo validar.' });
        } catch {
            mostrar({ aprobado: false, titulo: 'Sin conexión', mensaje: 'Intente de nuevo.' });
        }
    };

    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        pasar();
    });
    panel.querySelectorAll('[data-pasar]').forEach((boton) => boton.addEventListener('click', () => {
        codigo.value = boton.dataset.pasar;
        pasar();
    }));
}
