<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'refugee_id',
        'medical_service_id',
        'camp_id',
        'record_date',
        'diagnosis',
        'notes',
        'needs_follow_up',
        'follow_up_date',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'record_date' => 'date',
            'follow_up_date' => 'date',
            'needs_follow_up' => 'boolean',
        ];
    }

    public function refugee(): BelongsTo
    {
        return $this->belongsTo(Refugee::class);
    }

    public function medicalService(): BelongsTo
    {
        return $this->belongsTo(MedicalService::class);
    }

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
