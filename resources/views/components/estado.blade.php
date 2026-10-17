@props(['valor'])

@php
    $colores = [
        'activa' => 'bg-celeste-100 text-celeste-700',
        'activo' => 'bg-celeste-100 text-celeste-700',
        'disponible' => 'bg-celeste-100 text-celeste-700',
        'en_ruta' => 'bg-emerald-100 text-emerald-700',
        'mantenimiento' => 'bg-amber-100 text-amber-700',
        'inactiva' => 'bg-slate-100 text-slate-500',
        'inactivo' => 'bg-slate-100 text-slate-500',
        'en_curso' => 'bg-emerald-100 text-emerald-700',
        'finalizado' => 'bg-slate-100 text-slate-600',
        'cancelado' => 'bg-rose-100 text-rose-700',
        'atendida' => 'bg-slate-100 text-slate-600',
        'bloqueada' => 'bg-rose-100 text-rose-700',
        'suspendida' => 'bg-amber-100 text-amber-800',
        'vencida' => 'bg-slate-100 text-slate-500',
        'aprobada' => 'bg-emerald-100 text-emerald-700',
        'rechazada' => 'bg-rose-100 text-rose-700',
        'cumple' => 'bg-emerald-100 text-emerald-700',
        'faltan_buses' => 'bg-amber-100 text-amber-800',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'insignia '.($colores[$valor] ?? 'bg-slate-100 text-slate-600')]) }}>
    {{ ucfirst(str_replace('_', ' ', $valor)) }}
</span>
