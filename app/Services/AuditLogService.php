<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogService
{
    public function log(
        string $action,
        string $module,
        ?Model $auditable,
        string $description,
        string $sensitivity = 'medium',
        array $metadata = []
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'sensitivity' => $sensitivity,
            'metadata' => $this->sanitizeMetadata($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function sanitizeMetadata(array $metadata): array
    {
        return Arr::except($metadata, [
            'password',
            'password_confirmation',
            'diagnosis',
            'description',
            'security_description',
        ]);
    }
}
