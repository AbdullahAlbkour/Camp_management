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
}
