import './notificaciones';
import './confirmacion';
import './atender';
import './alertas';
import './recargas';
import './torniquete';

// Menú lateral en pantallas pequeñas.
const menu = document.querySelector('[data-menu]');
const fondo = document.querySelector('[data-menu-fondo]');

function alternarMenu(abrir) {
    menu?.classList.toggle('hidden', !abrir);
    menu?.classList.toggle('flex', abrir);
    fondo?.classList.toggle('hidden', !abrir);
}

document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => alternarMenu(menu.classList.contains('hidden')));
fondo?.addEventListener('click', () => alternarMenu(false));

// Cuenta regresiva: <x data-cuenta-regresiva="ISO-8601"> muestra mm:ss hasta esa hora.
document.querySelectorAll('[data-cuenta-regresiva]').forEach((elemento) => {
    const fin = new Date(elemento.dataset.cuentaRegresiva).getTime();
    const actualizar = () => {
        const restante = Math.max(0, Math.round((fin - Date.now()) / 1000));
        elemento.textContent = restante === 0
            ? 'cumplida'
            : `${Math.floor(restante / 60)}:${String(restante % 60).padStart(2, '0')}`;
        if (restante === 0) {
            clearInterval(intervalo);
        }
    };
    const intervalo = setInterval(actualizar, 1000);
    actualizar();
});

// Select dependiente: <select data-filtra="bus_id"> deja ver en #campo_bus_id solo el <optgroup data-grupo> elegido.
document.querySelectorAll('select[data-filtra]').forEach((padre) => {
    const hijo = document.getElementById(`campo_${padre.dataset.filtra}`);
    const filtrar = () => {
        hijo?.querySelectorAll('optgroup[data-grupo]').forEach((grupo) => {
            const visible = !padre.value || grupo.dataset.grupo === padre.value;
            grupo.hidden = !visible;
            grupo.disabled = !visible;
        });
        if (hijo?.selectedOptions[0]?.parentElement?.disabled) {
            hijo.value = '';
        }
    };
    padre.addEventListener('change', filtrar);
    filtrar();
});
