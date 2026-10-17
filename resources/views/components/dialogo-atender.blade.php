{{-- Ventana para atender una alerta escribiendo qué se hizo. La abre cualquier botón con data-atender
     dentro de un formulario PATCH a alertas.atender (ver resources/js/atender.js); sin JavaScript, el formulario se envía directo. --}}
<dialog data-dialogo-atender aria-labelledby="atender-titulo"
        class="confirmacion m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-celeste-900/40 backdrop:backdrop-blur-sm">
    <form method="POST" class="space-y-4 p-6" data-dialogo-atender-formulario>
        @csrf
        @method('PATCH')
        <div class="flex items-start gap-4">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <x-icono nombre="exito" class="size-6" />
            </span>
            <div class="min-w-0 pt-1">
                <h2 id="atender-titulo" class="text-lg font-semibold text-slate-900">Atender alerta</h2>
                <p data-dialogo-atender-alerta class="mt-0.5 text-sm text-slate-600"></p>
            </div>
        </div>
        <div>
            <label for="atender_nota" class="etiqueta">¿Qué se hizo?</label>
            <textarea id="atender_nota" name="nota" rows="3" maxlength="200" class="campo"
                      placeholder="Por ejemplo: se envió otro bus a la estación."></textarea>
            <p class="mt-1 text-xs text-slate-500">Queda en la bitácora con su nombre y la hora.</p>
        </div>
        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" data-dialogo-atender-cancelar class="btn-secundario">Cancelar</button>
            <button class="btn bg-emerald-600 text-white shadow-sm shadow-emerald-200 hover:bg-emerald-700 focus-visible:ring-emerald-400">Atender</button>
        </div>
    </form>
</dialog>
