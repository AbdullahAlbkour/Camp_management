<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidencyTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'refugee_id',
        'from_camp_id',
        'to_camp_id',
        'from_shelter_id',
        'to_shelter_id',
        'transfer_type',
        'reason',
        'transferred_by',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return ['transferred_at' => 'datetime'];
    }

    public function refugee(): BelongsTo
    {
        return $this->belongsTo(Refugee::class);
    }

    public function fromCamp(): BelongsTo
    {
        return $this->belongsTo(Camp::class, 'from_camp_id');
    }

    public function toCamp(): BelongsTo
    {
        return $this->belongsTo(Camp::class, 'to_camp_id');
    }

    public function fromShelter(): BelongsTo
    {
        return $this->belongsTo(Shelter::class, 'from_shelter_id');
    }

    public function toShelter(): BelongsTo
    {
        return $this->belongsTo(Shelter::class, 'to_shelter_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
