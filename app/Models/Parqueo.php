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
}
