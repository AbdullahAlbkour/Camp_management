<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'contact_name', 'phone', 'email', 'notes', 'status'];

    public function aidTypes(): HasMany
    {
        return $this->hasMany(AidType::class);
    }
}
