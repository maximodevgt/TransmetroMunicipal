{{-- Ventana de confirmación para acciones delicadas. Ver resources/js/confirmacion.js. --}}
<dialog data-confirmacion aria-labelledby="confirmacion-titulo" aria-describedby="confirmacion-mensaje"
        class="confirmacion m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-celeste-900/40 backdrop:backdrop-blur-sm">
    <form method="dialog" class="p-6">
        <div class="flex items-start gap-4">
            <span data-confirmacion-icono class="flex size-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                <x-icono nombre="advertencia" class="size-6" />
            </span>
            <div class="min-w-0 pt-1">
                <h2 id="confirmacion-titulo" data-confirmacion-titulo class="text-lg font-semibold text-slate-900"></h2>
                <p id="confirmacion-mensaje" data-confirmacion-mensaje class="mt-1 text-sm text-slate-600"></p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button value="cancelar" autofocus class="btn-secundario">Cancelar</button>
            <button value="aceptar" data-confirmacion-aceptar class="btn bg-rose-600 text-white shadow-sm shadow-rose-200 hover:bg-rose-700 focus-visible:ring-rose-400"></button>
        </div>
    </form>
</dialog>
