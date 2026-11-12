<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private Request $request) {}

    /** @param array<string, mixed>|null $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(string $action, ?object $subject = null, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create([
            'user_id' => $this->request->user()?->id,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => isset($subject->id) ? (string) $subject->id : null,
            'before' => $before,
            'after' => $after,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
