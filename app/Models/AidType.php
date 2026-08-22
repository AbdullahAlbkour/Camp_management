<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AidType extends Model
{
    use HasFactory;
    use SoftDeletes;

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
