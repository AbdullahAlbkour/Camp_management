<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'refugee_id',
        'camp_id',
        'incident_type',
        'severity',
        'report_date',
        'description',
        'action_taken',
        'reported_by',
    ];

    protected function casts(): array
    {
        return ['report_date' => 'date'];
    }

    public function refugee(): BelongsTo
    {
        return $this->belongsTo(Refugee::class);
    }

    public function camp(): BelongsTo
    {
        return $this->belongsTo(Camp::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
