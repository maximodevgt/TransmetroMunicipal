import './notificaciones';
import './confirmacion';
import './atender';
import './alertas';
import './recargas';
import './torniquete';

// Mostrar u ocultar la contraseña: <button data-ver-clave="id-del-campo"> (type="button": no envía el formulario).
document.querySelectorAll('[data-ver-clave]').forEach((boton) => {
    const campo = document.getElementById(boton.dataset.verClave);
    boton.addEventListener('click', () => {
        const mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        boton.setAttribute('aria-pressed', String(mostrar));
        boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
        boton.querySelector('[data-icono-mostrar]').classList.toggle('hidden', mostrar);
        boton.querySelector('[data-icono-ocultar]').classList.toggle('hidden', !mostrar);
        campo.focus();
    });
});

// Menú lateral en pantallas pequeñas.
const menu = document.querySelector('[data-menu]');
const fondo = document.querySelector('[data-menu-fondo]');

function alternarMenu(abrir) {
    menu?.classList.toggle('hidden', !abrir);
    menu?.classList.toggle('flex', abrir);
    fondo?.classList.toggle('hidden', !abrir);
}

// Si el enlace activo queda fuera de la parte visible del menú (pantallas bajas o con zoom), se desplaza hasta él.
function mostrarEnlaceActivo() {
    const lista = menu?.querySelector('[data-menu-enlaces]');
    const activo = lista?.querySelector('[aria-current="page"]');
    if (!activo || lista.clientHeight === 0) return;
    const fuera = activo.offsetTop < lista.scrollTop || activo.offsetTop + activo.offsetHeight > lista.scrollTop + lista.clientHeight;
    if (fuera) activo.scrollIntoView({ block: 'center', inline: 'nearest' });
}
mostrarEnlaceActivo();

document.querySelector('[data-menu-toggle]')?.addEventListener('click', () => {
    alternarMenu(menu.classList.contains('hidden'));
    mostrarEnlaceActivo();
});
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
