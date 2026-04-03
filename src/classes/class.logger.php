<?php

/**
 * Logger
 * Simple file-based logger for process tracking.
 *
 * @version 1.0.0
 *
 * @changelog
 *   1.0.0 - 2026-04-03
 *     - Add: Initial versioned release
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * $logger = new Logger();
 * $logger->logAction('update_post', 'Post #123 updated');
 * $logger->logAction('delete_cache', 'Cache cleared for page 45');
 *
 * // Log file: wp-content/log-process.log
 * // Only writes when ENABLE_LOGS constant is true
 * // Auto-rotates when file exceeds 5MB
 *
 * ──────────────────────────────────────────────────────────
 */
class Logger {
    private $logFile;
    private $enabled;
    private const MAX_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct() {
        $this->enabled = defined('ENABLE_LOGS') && ENABLE_LOGS;
        $this->logFile = WP_CONTENT_DIR . '/log-process.log';
    }

    public function logAction(string $functionName, string $description): void {
        if (!$this->enabled) return;

        // Auto-rotate if too large
        if (file_exists($this->logFile) && filesize($this->logFile) > self::MAX_SIZE) {
            $backup = $this->logFile . '.' . gmdate('Ymd-His') . '.bak';
            @rename($this->logFile, $backup);
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $line = "[{$timestamp}] {$functionName} — {$description}\n";
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
