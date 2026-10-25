<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Logger;

use ArrayAccess;
use DateTimeImmutable;
use Monolog\LogRecord;
use TrayDigita\WP\Stack\Source\Enums\LogLevel;

/**
 * @extends ArrayAccess<string, mixed>
 */
interface LogRecordInterface extends ArrayAccess
{
    /**
     * LogRecord constructor.
     *
     * @param DateTimeImmutable $datetime The date and time of the log record.
     * @param string $channel The channel name of the log record.
     * @param LogLevel $level The log level of the log record.
     * @param string $message The log message of the log record.
     * @param array<array-key, mixed> $context The context array of the log record. Defaults to an empty array.
     * @param array<array-key, mixed> $extra The extra array of the log record. Defaults to an empty array.
     * @param mixed|null $formatted The formatted log message of the log record. Defaults to null.
     */
    public function __construct(
        DateTimeImmutable $datetime,
        string $channel,
        LogLevel $level,
        string $message,
        array $context = [],
        array $extra = [],
        mixed $formatted = null,
    );

    /**
     * Gets the log level of the log record.
     *
     * @return LogLevel The log level of the log record.
     */
    public function getLevel(): LogLevel;

    /**
     * Creates a LogRecordInterface instance from a Monolog LogRecord instance.
     *
     * @param LogRecord $record The Monolog LogRecord instance.
     * @return self The created LogRecordInterface instance.
     */
    public static function fromMonologRecord(LogRecord $record): self;

    /**
     * @return array{
     *     message: string,
     *     context: array<array-key, mixed>,
     *     level: key-of<LogLevel::LEVEL_NUMBERS>,
     *     level_name: key-of<LogLevel::LEVEL_NAMES>,
     *     channel: string,
     *     datetime: DateTimeImmutable,
     *     extra: array<array-key, mixed>,
     * }
     */
    public function toArray(): array;

    /**
     * Converts the log record to a Monolog LogRecord instance.
     *
     * @return LogRecord The Monolog LogRecord instance.
     */
    public function toMonologRecord() : LogRecord;

    /**
     * Creates a new LogRecordInterface instance with the specified modifications.
     *
     * @param mixed ...$args The modifications to apply to the log record.
     * @return self A new LogRecordInterface instance with the specified modifications.
     */
    public function with(mixed ...$args): self;
}
