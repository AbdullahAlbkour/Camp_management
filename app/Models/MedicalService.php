<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalService extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'status'];

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }
}
