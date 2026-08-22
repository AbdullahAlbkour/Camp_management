<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Checkpoint extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['camp_id', 'name', 'location', 'status'];

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class);
    }

    public function entryExitLogs(): HasMany
    {
        return $this->hasMany(EntryExitLog::class);
    }
}
