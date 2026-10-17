import './notificaciones';
import './confirmacion';
import './alertas';

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
