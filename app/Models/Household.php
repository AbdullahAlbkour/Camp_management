<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['household_code', 'head_of_household_id', 'notes', 'status'];

    public function head(): BelongsTo
    {
        return $this->belongsTo(Refugee::class, 'head_of_household_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(Refugee::class);
    }

    public function aidDistributions(): HasMany
    {
        return $this->hasMany(AidDistribution::class);
    }
}
