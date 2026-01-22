<?php

namespace TofuPlugin;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use TofuPlugin\Helpers\Directory;

class Logger
{
    /** @var MonologLogger */
    protected static $logger;

    /** @var string */
    protected static $filePath;

    /**
     * Initialize the logger.
     *
     * @param string $file Log file name (without path)
     * @return void
     */
    public static function init(string $file): void
    {
        if (WP_DEBUG) {
            $logDir = Directory::createUploadSubDirectory(Consts::LOG_SUBFOLDER);
            self::$filePath = $logDir . \DIRECTORY_SEPARATOR . $file . '.log';
            $handler = new StreamHandler(self::$filePath);
            self::$logger = new MonologLogger('tofu', [$handler]);
        }
    }

    /**
     * Get the log file path if logging is enabled.
     *
     * @return ?string
     * @throws \Exception
     */
    public static function getLogFilePath(): ?string
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
