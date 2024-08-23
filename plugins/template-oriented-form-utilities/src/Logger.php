<?php

namespace TofuPlugin;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;

class Logger
{
    /** @var Logger */
    protected static $logger;

    /** @var string */
    protected static $filePath;

    public static function init($file)
    {
        if (WP_DEBUG) {
            self::$filePath = WP_CONTENT_DIR . '/tofu-logs/' . $file . '.log';
            $handler = new StreamHandler(self::$filePath);
            self::$logger = new MonologLogger('tofu', [$handler]);
        }
    }

    public static function getLogFilePath(): string
    {
        if (WP_DEBUG) {
            if (!self::$logger) {
                throw new \Exception('Logger is not initialized.');
            }

            return self::$filePath;
        } else {
            return null;
        }
    }

    /**
     * Write an info log.
     *
     * @param string $message
     * @param array $context
     */
    public static function info(string $message, array $context = []): void
    {
        if (WP_DEBUG) {
            if (!self::$logger) {
                throw new \Exception('Logger is not initialized.');
            }

            self::$logger->info($message, $context);
        }
    }

    /**
     * Write a warning log.
     *
     * @param string $message
     * @param array $context
     */
    public static function warning(string $message, array $context = []): void
    {
        if (WP_DEBUG) {
            if (!self::$logger) {
                throw new \Exception('Logger is not initialized.');
            }

            self::$logger->warning($message, $context);
        }
    }

    /**
     * Write an error log.
     *
     * @param string $message
     * @param array $context
     */
    public static function error(string $message, array $context = []): void
    {
        if (WP_DEBUG) {
            if (!self::$logger) {
                throw new \Exception('Logger is not initialized.');
            }

            self::$logger->error($message, $context);
        }
    }

    /**
     * Write a critical log.
     *
     * @param string $message
     * @param array $context
     */
    public static function critical(string $message, array $context = []): void
    {
        if (WP_DEBUG) {
            if (!self::$logger) {
                throw new \Exception('Logger is not initialized.');
            }

            self::$logger->critical($message, $context);
        }
    }

    /**
     * Write an alert log.
     *
     * @param string $message
     * @param array $context
     */
    public static function alert(string $message, array $context = []): void
    {
        if (WP_DEBUG) {
            if (!self::$logger) {
                throw new \Exception('Logger is not initialized.');
            }

            self::$logger->alert($message, $context);
        }
    }

    /**
     * Write an emergency log.
     *
     * @param string $message
     * @param array $context
     */
    public static function emergency(string $message, array $context = []): void
    {
        if (WP_DEBUG) {
            if (!self::$logger) {
                throw new \Exception('Logger is not initialized.');
            }

            self::$logger->emergency($message, $context);
        }
    }
}
