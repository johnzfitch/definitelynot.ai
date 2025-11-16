<?php

declare(strict_types=1);

/**
 * Logging Helper for Cosmic Text Linter
 *
 * Provides structured, privacy-aware logging with configurable levels.
 * Designed for shared hosting environments with minimal dependencies.
 *
 * Configuration via environment variables:
 * - LINTER_LOG_LEVEL: off|error|warn|info|debug (default: error)
 * - LINTER_LOG_FILE: custom log file path (default: PHP error_log)
 */
class Logger
{
    private const LEVEL_OFF = 0;
    private const LEVEL_ERROR = 1;
    private const LEVEL_WARN = 2;
    private const LEVEL_INFO = 3;
    private const LEVEL_DEBUG = 4;

    private static $level = self::LEVEL_ERROR;
    private static $logFile = null;
    private static $initialized = false;

    /**
     * Initialize logger from environment
     */
    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $levelStr = strtolower(getenv('LINTER_LOG_LEVEL') ?: 'error');
        $levelMap = [
            'off' => self::LEVEL_OFF,
            'error' => self::LEVEL_ERROR,
            'warn' => self::LEVEL_WARN,
            'info' => self::LEVEL_INFO,
            'debug' => self::LEVEL_DEBUG,
        ];

        self::$level = $levelMap[$levelStr] ?? self::LEVEL_ERROR;
        self::$logFile = getenv('LINTER_LOG_FILE') ?: null;
        self::$initialized = true;
    }

    /**
     * Set log level programmatically (for testing)
     */
    public static function setLevel(string $level): void
    {
        self::init();
        $levelMap = [
            'off' => self::LEVEL_OFF,
            'error' => self::LEVEL_ERROR,
            'warn' => self::LEVEL_WARN,
            'info' => self::LEVEL_INFO,
            'debug' => self::LEVEL_DEBUG,
        ];
        if (isset($levelMap[$level])) {
            self::$level = $levelMap[$level];
        }
    }

    /**
     * Log an error message
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, 'ERROR', $message, $context);
    }

    /**
     * Log a warning message
     */
    public static function warn(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARN, 'WARN', $message, $context);
    }

    /**
     * Log an info message
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, 'INFO', $message, $context);
    }

    /**
     * Log a debug message
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(self::LEVEL_DEBUG, 'DEBUG', $message, $context);
    }

    /**
     * Log a request with sanitization stats (privacy-safe)
     */
    public static function logRequest(string $mode, array $stats, float $duration): void
    {
        self::init();
        if (self::$level < self::LEVEL_INFO) {
            return;
        }

        $context = [
            'mode' => $mode,
            'input_length' => $stats['original_length'] ?? 0,
            'output_length' => $stats['final_length'] ?? 0,
            'chars_removed' => $stats['characters_removed'] ?? 0,
            'invisibles_removed' => $stats['invisibles_removed'] ?? 0,
            'homoglyphs_normalized' => $stats['homoglyphs_normalized'] ?? 0,
            'digits_normalized' => $stats['digits_normalized'] ?? 0,
            'duration_ms' => round($duration * 1000, 2),
        ];

        // Log advisories that were triggered
        $advisories = [];
        if (isset($stats['advisories']) && is_array($stats['advisories'])) {
            foreach ($stats['advisories'] as $key => $value) {
                if ($value === true) {
                    $advisories[] = $key;
                }
            }
        }
        if (!empty($advisories)) {
            $context['advisories'] = implode(',', $advisories);
        }

        self::info('Request processed', $context);
    }

    /**
     * Log a security event (high priority)
     */
    public static function security(string $event, string $message, array $context = []): void
    {
        self::init();
        $context['security_event'] = $event;
        self::warn($message, $context);
    }

    /**
     * Core logging implementation
     */
    private static function log(int $requiredLevel, string $levelStr, string $message, array $context): void
    {
        self::init();

        if (self::$level < $requiredLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s T');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[{$timestamp}] [{$levelStr}] CosmicTextLinter: {$message}{$contextStr}";

        if (self::$logFile) {
            error_log($logLine . PHP_EOL, 3, self::$logFile);
        } else {
            error_log($logLine);
        }
    }

    /**
     * Get a unique request ID for correlation
     */
    public static function getRequestId(): string
    {
        static $requestId = null;
        if ($requestId === null) {
            $requestId = bin2hex(random_bytes(8));
        }
        return $requestId;
    }
}
