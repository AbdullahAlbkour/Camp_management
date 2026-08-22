<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_role',
        'user_id',
        'type',
        'title',
        'body',
        'status',
        'related_type',
        'related_id',
        'created_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleFor(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('admin')) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($user): void {
            $inner->where('user_id', $user->id)
                ->orWhere('target_role', $user->role?->name);
        });
    }

    public function scopeEquivalentTo(Builder $query, self $notification): Builder
    {
        foreach (['type', 'title', 'body', 'related_type', 'related_id'] as $field) {
            $value = $notification->{$field};

            $value === null
                ? $query->whereNull($field)
                : $query->where($field, $value);
        }

        return $query;
    }

    public function getDedupeKeyAttribute(): string
    {
        return collect([
            $this->type,
            $this->title,
            $this->body,
            $this->related_type,
            $this->related_id,
        ])->map(fn ($value) => $value === null ? 'NULL' : (string) $value)->implode('|');
    }
}
