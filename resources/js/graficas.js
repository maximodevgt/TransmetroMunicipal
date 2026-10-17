// Gráficas del panel con Chart.js (instalado por npm). Pide /panel/graficas una vez al abrir el panel;
// cada barra, punto o porción lleva al reporte filtrado. Sin datos, se muestra un mensaje en vez de la gráfica.
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

// Paleta celeste del sistema, de la más clara a la más oscura según haga falta contraste.
const CELESTES = ['#0ea5e9', '#0369a1', '#7dd3fc', '#075985', '#38bdf8', '#0c4a6e', '#bae6fd'];

Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
Chart.defaults.color = '#475569';
Chart.defaults.plugins.legend.labels.boxWidth = 12;

const contenedor = document.querySelector('[data-graficas]');

function opciones(grafica) {
    const apilada = grafica.series.length > 1 && grafica.tipo === 'bar';
    const base = {
        responsive: true,
        maintainAspectRatio: false,
        onHover: (evento, elementos) => {
            evento.native.target.style.cursor = elementos.length ? 'pointer' : 'default';
        },
        plugins: {
            legend: { display: grafica.series.length > 1 || grafica.tipo === 'doughnut', position: 'bottom' },
            tooltip: {
                callbacks: grafica.tipo === 'doughnut'
                    ? { label: (c) => ` ${c.label}: ${c.parsed} % de ocupación promedio` }
                    : {},
            },
        },
    };
    if (grafica.tipo === 'doughnut') return { ...base, cutout: '60%' };

    // En las líneas basta con acercarse a la hora o al día; en las barras, tocar la barra.
    // El clic usa las opciones de "hover", por eso van en los dos lugares.
    const interaccion = grafica.tipo === 'line' ? { mode: 'index', intersect: false } : { mode: 'nearest', intersect: true };

    return {
        ...base,
        interaction: interaccion,
        hover: interaccion,
        scales: {
            x: { stacked: apilada, grid: { display: false } },
            y: { stacked: apilada, beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e0f2fe' } },
        },
    };
}

function series(grafica) {
    return grafica.series.map((serie, i) => {
        const color = CELESTES[i % CELESTES.length];
        if (grafica.tipo === 'doughnut') {
            return { label: serie.nombre, data: serie.datos, backgroundColor: CELESTES, borderColor: '#fff', borderWidth: 2 };
        }
        if (grafica.tipo === 'line') {
            return {
                label: serie.nombre, data: serie.datos, borderColor: color, backgroundColor: `${color}22`,
                fill: grafica.series.length === 1, cubicInterpolationMode: 'monotone', pointRadius: grafica.etiquetas.length > 12 ? 0 : 3, pointHoverRadius: 6,
            };
        }
        return { label: serie.nombre, data: serie.datos, backgroundColor: color, borderRadius: 6, maxBarThickness: 36 };
    });
}

// Resumen en texto para lectores de pantalla.
function descripcion(grafica) {
    return grafica.series.map((serie) => `${serie.nombre}: ${grafica.etiquetas.map((e, i) => `${e} ${serie.datos[i]}`).join(', ')}`).join('. ');
}

function dibujar(tarjeta, grafica) {
    const lienzo = tarjeta.querySelector('canvas');
    const vacio = tarjeta.querySelector('[data-grafica-vacia]');
    tarjeta.querySelector('[data-grafica-cargando]')?.remove();

    if (!grafica.hay_datos) {
        lienzo.hidden = true;
        vacio.textContent = grafica.vacio;
        vacio.hidden = false;
        return;
    }

    lienzo.setAttribute('aria-label', descripcion(grafica));
    new Chart(lienzo, {
        type: grafica.tipo,
        data: { labels: grafica.etiquetas, datasets: series(grafica) },
        options: {
            ...opciones(grafica),
            onClick: (evento, elementos) => {
                const [elemento] = elementos;
                const url = elemento && grafica.series[elemento.datasetIndex]?.enlaces[elemento.index];
                if (url) window.location.href = url;
            },
        },
    });
}

async function cargar() {
    try {
        const respuesta = await fetch(contenedor.dataset.urlGraficas, { headers: { Accept: 'application/json' } });
        if (!respuesta.ok) throw new Error(respuesta.status);
        const { graficas } = await respuesta.json();
        contenedor.querySelectorAll('[data-grafica]').forEach((tarjeta) => {
            const grafica = graficas[tarjeta.dataset.grafica];
            if (grafica) dibujar(tarjeta, grafica);
        });
    } catch {
        contenedor.querySelectorAll('[data-grafica-cargando]').forEach((e) => (e.textContent = 'No se pudieron cargar las gráficas. Recargue la página.'));
    }
}

if (contenedor) cargar();
