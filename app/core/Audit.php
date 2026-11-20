<?php
declare(strict_types=1);

/** Append-only access log. The app never updates or deletes rows in audit_logs. */
final class Audit
{
    /**
     * @param string $action e.g. auth.login, auth.failed, report.view, report.status, access.denied
     */
    public static function log(string $action, ?string $objectType = null, ?string $objectRef = null, ?string $detail = null, ?array $actor = null, ?string $actorLabel = null): void
    {
        $actor ??= Auth::user();
        $label = $actorLabel ?? ($actor['name'] ?? 'Anonim');
        try {
            Database::run(
                'INSERT INTO audit_logs (actor_id, actor_label, action, object_type, object_ref, detail, ip, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $actor['id'] ?? null,
                    mb_substr($label, 0, 80),
                    $action,
                    $objectType,
                    $objectRef,
                    $detail === null ? null : mb_substr($detail, 0, 255),
                    client_ip(),
                    user_agent(),
                    now(),
                ]
            );
        } catch (Throwable $e) {
            // Auditing must not break the user's request, but a failure is itself worth knowing about.
            Logger::error('Audit write failed: ' . $e->getMessage());
        }
    }
}
