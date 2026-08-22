<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refugee extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'father_name',
        'last_name',
        'gender',
        'date_of_birth',
        'nationality',
        'document_number',
        'phone',
        'marital_status',
        'status',
        'current_camp_id',
        'current_shelter_id',
        'housing_status',
        'presence_status',
        'household_id',
        'relation_to_head',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->father_name.' '.$this->last_name);
    }

    public function currentCamp(): BelongsTo
    {
        return $this->belongsTo(Camp::class, 'current_camp_id');
    }

    public function currentShelter(): BelongsTo
    {
        return $this->belongsTo(Shelter::class, 'current_shelter_id');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function residencyTransfers(): HasMany
    {
        return $this->hasMany(ResidencyTransfer::class);
    }

    public function aidDistributions(): HasMany
    {
        return $this->hasMany(AidDistribution::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function entryExitLogs(): HasMany
    {
        return $this->hasMany(EntryExitLog::class);
    }

    public function securityReports(): HasMany
    {
        return $this->hasMany(SecurityReport::class);
    }
}
