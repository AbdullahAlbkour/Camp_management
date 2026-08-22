<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AidDistribution extends Model
{
    protected $fillable = [
        'aid_type_id',
        'refugee_id',
        'household_id',
        'camp_id',
        'quantity',
        'distribution_date',
        'distributed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'distribution_date' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    public function aidType(): BelongsTo
    {
        return $this->belongsTo(AidType::class);
    }

    public function refugee(): BelongsTo
    {
        return $this->belongsTo(Refugee::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class);
    }
}
