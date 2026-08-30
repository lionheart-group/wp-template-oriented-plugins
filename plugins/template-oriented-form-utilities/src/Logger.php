<?php

namespace TofuPlugin;

use TofuPlugin\Helpers\Directory;

/**
 * Debug logging, active only while WP_DEBUG is on.
 *
 * Writes to wp-content/uploads/tofu-logs/. Line format is kept
 * byte-compatible with the Monolog output this replaced —
 *
 *     [2026-01-22T07:21:30.184085+00:00] tofu.INFO: Message {"context":1} []
 *
 * — so log files written before and after the change stay greppable with the
 * same expressions, and existing files can be appended to rather than
 * orphaned. The trailing `[]` is Monolog's "extra" field, which this plugin
 * never populated; it is emitted so the column layout matches.
 *
 * Every method is a no-op unless WP_DEBUG is true, so calls can be left in
 * hot paths without a guard at the call site.
 */
class Logger
{
    /** @var bool */
    protected static $initialized = false;

    /** @var ?string */
    protected static $filePath = null;

    /**
     * Channel name, the `tofu` in `tofu.INFO`.
     */
    protected const CHANNEL = 'tofu';

    /**
     * Initialize the logger.
     *
     * @param string $file Log file name (without path)
     * @return void
     */
    public static function init(string $file): void
    {
        if (!WP_DEBUG) {
            return;
        }

        $logDir = Directory::createUploadSubDirectory(Consts::LOG_SUBFOLDER);
        self::$filePath = $logDir . \DIRECTORY_SEPARATOR . $file . '.log';
        self::$initialized = true;
    }

    /**
     * Get the log file path if logging is enabled.
     *
     * @return ?string
     * @throws \Exception
     */
    public static function getLogFilePath(): ?string
    {
        if (!WP_DEBUG) {
            return null;
        }

        self::assertInitialized();

        return self::$filePath;
    }

    /**
     * Write an info log.
     *
     * @param string $message
     * @param array $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * Write a warning log.
     *
     * @param string $message
     * @param array $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    /**
     * Write an error log.
     *
     * @param string $message
     * @param array $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * Write a critical log.
     *
     * @param string $message
     * @param array $context
     */
    public static function critical(string $message, array $context = []): void
    {
        self::write('CRITICAL', $message, $context);
    }

    /**
     * Write an alert log.
     *
     * @param string $message
     * @param array $context
     */
    public static function alert(string $message, array $context = []): void
    {
        self::write('ALERT', $message, $context);
    }

    /**
     * Write an emergency log.
     *
     * @param string $message
     * @param array $context
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::write('EMERGENCY', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     * @throws \Exception when called before init().
     */
    protected static function write(string $level, string $message, array $context): void
    {
        if (!WP_DEBUG) {
            return;
        }

        self::assertInitialized();

        $line = sprintf(
            "[%s] %s.%s: %s %s []\n",
            (new \DateTimeImmutable('now'))->format('Y-m-d\TH:i:s.uP'),
            self::CHANNEL,
            $level,
            $message,
            self::encodeContext($context)
        );

        // A full disk or an unwritable directory must never take down a form
        // submission: debug logging is a convenience, not part of the
        // contract with the visitor.
        try {
            @file_put_contents((string) self::$filePath, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Nothing useful to do — reporting the failure would need the
            // very channel that just failed.
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    protected static function encodeContext(array $context): string
    {
        if ($context === []) {
            return '[]';
        }

        $encoded = json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $encoded === false ? '[]' : $encoded;
    }

    /**
     * @throws \Exception
     */
    protected static function assertInitialized(): void
    {
        if (!self::$initialized) {
            throw new \Exception('Logger is not initialized.');
        }
    }
}
