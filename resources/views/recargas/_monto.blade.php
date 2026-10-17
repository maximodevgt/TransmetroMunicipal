{{-- Monto de la recarga: botones rápidos y monto libre --}}
<fieldset>
    <legend class="etiqueta">Monto</legend>
    <div class="flex flex-wrap gap-2">
        @foreach ($rapidos as $rapido)
            <button type="button" data-monto-rapido="{{ $rapido }}"
                    class="btn-secundario min-w-16 tabular-nums aria-pressed:bg-celeste-500 aria-pressed:text-white" aria-pressed="false">Q{{ $rapido }}</button>
        @endforeach
    </div>
    <div class="mt-3 max-w-xs">
        <label for="campo_monto" class="etiqueta">Otro monto (Q{{ $minimo }} a Q{{ $maximo }})</label>
        <div class="flex items-center gap-2">
            <span class="text-slate-500">Q</span>
            <input id="campo_monto" type="number" name="monto" min="{{ $minimo }}" max="{{ $maximo }}" step="0.01" required data-monto
                   value="{{ old('monto') }}" class="campo tabular-nums">
        </div>
        @error('monto') <p class="campo-error">{{ $message }}</p> @enderror
    </div>
</fieldset>
