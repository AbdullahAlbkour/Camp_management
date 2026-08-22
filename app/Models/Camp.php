<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Camp extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'location', 'capacity', 'status', 'notes'];

    public function shelters(): HasMany
    {
        return $this->hasMany(Shelter::class);
    }

    public function refugees(): HasMany
    {
        return $this->hasMany(Refugee::class, 'current_camp_id');
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(Checkpoint::class);
    }
}
