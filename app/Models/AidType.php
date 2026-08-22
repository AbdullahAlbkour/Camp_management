<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AidType extends Model
{
    protected $fillable = ['organization_id', 'name', 'unit', 'description', 'status'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(AidDistribution::class);
    }
}
