// Recargas: montos rápidos, confirmación del efectivo y checkout de la pasarela SIMULADA
// (marca, formato mientras se escribe, Luhn y "Procesando pago..."). El servidor vuelve a validar todo.

export function marca(numero) {
    if (/^4/.test(numero)) return { nombre: 'VISA', clase: 'bg-blue-600' };
    if (/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/.test(numero)) return { nombre: 'MASTERCARD', clase: 'bg-orange-500' };
    if (/^3[47]/.test(numero)) return { nombre: 'AMEX', clase: 'bg-sky-600' };
    return { nombre: 'TARJETA', clase: 'bg-white/10' };
}

export function luhn(numero) {
    if (numero.length < 13 || numero.length > 19) return false;
    let suma = 0;
    [...numero].reverse().forEach((digito, i) => {
        let valor = Number(digito);
        if (i % 2 === 1) {
            valor *= 2;
            if (valor > 9) valor -= 9;
        }
        suma += valor;
    });
    return suma % 10 === 0;
}

// American Express se agrupa 4-6-5; las demás de 4 en 4.
function formatear(numero) {
    if (/^3[47]/.test(numero)) {
        return [numero.slice(0, 4), numero.slice(4, 10), numero.slice(10, 15)].filter(Boolean).join(' ');
    }
    return numero.slice(0, 19).replace(/(\d{4})(?=\d)/g, '$1 ');
}

document.querySelectorAll('[data-recarga]').forEach((formulario) => {
    const monto = formulario.querySelector('[data-monto]');
    const rapidos = formulario.querySelectorAll('[data-monto-rapido]');
    const marcarRapido = () => rapidos.forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.montoRapido) === Number(monto.value))));

    rapidos.forEach((boton) => boton.addEventListener('click', () => {
        monto.value = boton.dataset.montoRapido;
        marcarRapido();
    }));
    monto.addEventListener('input', marcarRapido);
    marcarRapido();

    // Efectivo: el título de la ventana de confirmación lleva el monto.
    if (formulario.dataset.tarjeta) {
        formulario.addEventListener('submit', () => {
            formulario.dataset.confirmarTitulo = `¿Recargar Q${Number(monto.value || 0).toFixed(2)} en efectivo a ${formulario.dataset.tarjeta}?`;
        });
    }

    if (!formulario.hasAttribute('data-pasarela')) return;

    const numero = formulario.querySelector('[data-numero]');
    const vencimiento = formulario.querySelector('[data-vencimiento]');
    const cvv = formulario.querySelector('[data-cvv]');
    const errorNumero = formulario.querySelector('[data-error-numero]');
    const insignia = formulario.querySelector('[data-marca]');
    const procesando = document.querySelector('[data-procesando]');

    const soloDigitos = (texto) => texto.replace(/\D/g, '');
    const pintarMarca = () => {
        const datos = marca(soloDigitos(numero.value));
        insignia.textContent = datos.nombre;
        insignia.className = `rounded-md px-2 py-1 text-xs font-bold tracking-wider ${datos.clase}`;
        cvv.maxLength = datos.nombre === 'AMEX' ? 4 : 3;
    };

    numero.addEventListener('input', () => {
        numero.value = formatear(soloDigitos(numero.value));
        errorNumero.textContent = '';
        pintarMarca();
    });
    vencimiento.addEventListener('input', () => {
        const digitos = soloDigitos(vencimiento.value).slice(0, 4);
        vencimiento.value = digitos.length > 2 ? `${digitos.slice(0, 2)}/${digitos.slice(2)}` : digitos;
    });
    cvv.addEventListener('input', () => (cvv.value = soloDigitos(cvv.value)));

    // Los botones del recuadro "Modo prueba" están junto al formulario, no dentro.
    document.querySelectorAll('[data-tarjeta-prueba]').forEach((boton) => boton.addEventListener('click', () => {
        numero.value = formatear(boton.dataset.tarjetaPrueba);
        const futuro = new Date(new Date().getFullYear() + 3, 0);
        vencimiento.value = `12/${String(futuro.getFullYear()).slice(2)}`;
        cvv.value = '123';
        formulario.querySelector('[name="nombre"]').value ||= 'PRUEBA TRANSMETRO';
        pintarMarca();
        numero.focus();
    }));

    // Al pagar: validación de Luhn y pantalla "Procesando pago..." de 1 a 2 segundos.
    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        if (!luhn(soloDigitos(numero.value))) {
            errorNumero.textContent = 'El número de tarjeta no es válido.';
            numero.focus();
            return;
        }
        if (!formulario.checkValidity()) {
            formulario.reportValidity();
            return;
        }
        formulario.querySelector('[data-pagar]').disabled = true;
        procesando.hidden = false;
        setTimeout(() => formulario.submit(), 1000 + Math.random() * 800);
    });
    pintarMarca();
});
