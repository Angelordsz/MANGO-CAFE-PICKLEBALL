<?php
namespace App\Core;

/**
 * Two sinks:
 *   activity() -> system_logs table  (use case A15, View System Logs)
 *   error()    -> storage/logs/app.log
 */
final class Logger
{
    public static function activity(
        string $action,
        string $description,
        ?string $entityType = null,
        ?int $entityId = null
    ): void {
        try {
            $request = new Request();
            Database::insert('system_logs', [
                'user_id'     => Auth::id(),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'description' => mb_substr($description, 0, 255),
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Never let audit logging break the request it is auditing.
            self::error('Failed to write system log: ' . $e->getMessage());
        }
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = sprintf(
            "[%s] %s: %s%s%s",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? ' ' . json_encode($context) : '',
            PHP_EOL
        );

        @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
