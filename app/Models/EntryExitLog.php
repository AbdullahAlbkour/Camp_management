<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntryExitLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'refugee_id',
        'camp_id',
        'checkpoint_id',
        'movement_type',
        'movement_datetime',
        'reason',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return ['movement_datetime' => 'datetime'];
    }

    public function refugee(): BelongsTo
    {
        return $this->belongsTo(Refugee::class);
    }

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
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
