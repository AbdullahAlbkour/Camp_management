<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shelter extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['camp_id', 'code', 'type', 'capacity', 'status', 'notes'];

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class);
    }

    public function refugees(): HasMany
    {
        return $this->hasMany(Refugee::class, 'current_shelter_id');
    }

    public function occupiedCount(): int
    {
        return $this->refugees()->where('status', 'active')->count();
    }

    public function availableSpaces(): int
    {
        return max(0, (int) $this->capacity - $this->occupiedCount());
    }

    public function isFull(): bool
    {
        return $this->availableSpaces() <= 0;
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'tent' => 'خيمة',
            'room' => 'غرفة',
            'caravan' => 'كرفان',
            default => $this->type ?: 'سكن',
        };
    }

    public function getDisplayNameAttribute(): string
    {
        return trim($this->type_label.' '.$this->code);
    }
}
