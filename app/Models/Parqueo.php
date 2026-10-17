<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('parqueos')]
#[Fillable(['nombre', 'direccion', 'capacidad'])]
class Parqueo extends Model
{
    public function buses(): HasMany
    {
        return $this->hasMany(Bus::class, 'parqueo_id');
    }

    /**
     * Porcentaje de ocupación (buses asignados / capacidad). Usa buses_count si se cargó con withCount.
     */
    public function ocupacion(): int
    {
        $buses = $this->buses_count ?? $this->buses()->count();

        return $this->capacidad > 0 ? (int) round($buses / $this->capacidad * 100) : 0;
    }
}
