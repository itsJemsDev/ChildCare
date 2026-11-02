<?php

declare(strict_types=1);

namespace App\Services;

final class AuditLogger
{
    public function log(string $action, string $entityType, int|string|null $entityId = null, ?array $old = null, ?array $new = null, ?int $userId = null): void
    {
        db_connect()->table('audit_logs')->insert([
            'user_id' => $userId ?? (function_exists('auth') && auth()->loggedIn() ? auth()->id() : null),
            'action' => $action, 'entity_type' => $entityType, 'entity_id' => $entityId === null ? null : (string) $entityId,
            'old_values' => $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR), 'new_values' => $new === null ? null : json_encode($new, JSON_THROW_ON_ERROR),
            'ip_address' => service('request')->getIPAddress(), 'user_agent' => substr((string) service('request')->getUserAgent(), 0, 255), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
