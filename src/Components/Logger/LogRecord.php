<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger;

use DateTimeImmutable;
use RuntimeException;
use TrayDigita\WP\Stack\Source\Enums\LogLevel;
use TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException;
use TrayDigita\WP\Stack\Source\Exceptions\InvalidArgumentException;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogRecordInterface;
use function class_exists;
use function is_array;

class LogRecord implements LogRecordInterface
{
    /**
     * @var ?\Monolog\LogRecord The Monolog log record instance.
     */
    private ?\Monolog\LogRecord $monologRecord;

    /**
     * @var array<string, bool> List of modifiable properties
     */
    public const MODIFIABLE_FIELDS = [
        'extra' => true,
        'formatted' => true,
    ];

    /**
     * LogRecord constructor.
     *
     * @param DateTimeImmutable $datetime The date and time of the log record.
     * @param string $channel The channel name of the log record.
     * @param LogLevel $level The log level of the log record.
     * @param string $message The log message.
     * @param array<array-key, mixed> $context The context data for the log record.
     * @param array<array-key, mixed> $extra Additional data for the log record.
     * @param mixed|null $formatted The formatted log message, if any.
     */
    public function __construct(
        public readonly DateTimeImmutable $datetime,
        public readonly string $channel,
        public readonly LogLevel $level,
        public readonly string $message,
        public readonly array $context = [],
        public array $extra = [],
        public mixed $formatted = null
    ) {
    }

    /**
     * Get the log level of the record.
     *
     * @return LogLevel The log level of the record.
     */
    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    /**
     * Create a LogRecord instance from a Monolog\LogRecord instance.
     *
     * @param \Monolog\LogRecord $record The Monolog\LogRecord instance.
     * @return LogRecordInterface A new LogRecord instance.
     */
    public static function fromMonologRecord(\Monolog\LogRecord $record): LogRecordInterface
    {
        return new self(
            $record->datetime,
            $record->channel,
            LogLevel::fromLevel($record->level),
            $record->message,
            $record->context,
            $record->extra,
            $record->formatted
        );
    }

    /**
     * Convert this LogRecord to an associative array.
     *
     * @return array<string, mixed> An associative array representation of the LogRecord.
     */
    public function toArray(): array
    {
        return [
            'datetime' => $this->datetime,
            'channel' => $this->channel,
            'level' => $this->level->name,
            'message' => $this->message,
            'context' => $this->context,
            'extra' => $this->extra,
            'formatted' => $this->formatted,
        ];
    }

    /**
     * Convert this LogRecord to a Monolog\LogRecord instance.
     *
     * @return \Monolog\LogRecord
     * @throws RuntimeException if the Monolog\LogRecord class is not found
     */
    public function toMonologRecord(): \Monolog\LogRecord
    {
        if (isset($this->monologRecord)) {
            return $this->monologRecord;
        }
        if (!class_exists('\Monolog\LogRecord')) {
            throw new RuntimeException('Monolog\LogRecord class not found');
        }
        return $this->monologRecord = new \Monolog\LogRecord(
            $this->datetime,
            $this->channel,
            $this->level->toMonologLevel(),
            $this->message,
            $this->context,
            $this->extra,
            $this->formatted
        );
    }

    /**
     * Check if a property exists in the LogRecord.
     *
     * @param mixed $offset The property name to check.
     * @return bool True if the property exists, false otherwise.
     */
    public function offsetExists(mixed $offset): bool
    {
        if ($offset === 'level_name') {
            return true;
        }

        return isset($this->{$offset});
    }

    /**
     * @noinspection PhpUnnecessaryLocalVariableInspection
     */
    public function &offsetGet(mixed $offset): mixed
    {
        // handle special cases for the level enum
        if ($offset === 'level_name') {
            // avoid returning readonly props by ref as this is illegal
            $copy = $this->level->getName();
            return $copy;
        }
        if ($offset === 'level') {
            // avoid returning readonly props by ref as this is illegal
            $copy = $this->level->value;
            return $copy;
        }

        if (isset(self::MODIFIABLE_FIELDS[$offset])) {
            $this->monologRecord = null; // Invalidate the cached Monolog record if a modifiable field is accessed
            return $this->{$offset};
        }

        // avoid returning readonly props by ref as this is illegal
        $copy = $this->{$offset};
        $this->monologRecord = null; // Invalidate the cached Monolog record if a modifiable field is accessed
        return $copy;
    }

    /**
     * Set a property in the LogRecord.
     *
     * @param mixed $offset The property name to set.
     * @param mixed $value The value to set for the property.
     * @throws InvalidArgumentException if the value for 'extra' is not an array.
     * @throws IllegalOperationException if attempting to set an unsupported property.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === 'extra') {
            if (!is_array($value)) {
                throw new InvalidArgumentException('extra must be an array');
            }
            $this->extra = $value;
            if (isset($this->monologRecord)) {
                $this->monologRecord->extra = $value; // Update the cached Monolog record if it exists
            }
            return;
        }

        if ($offset === 'formatted') {
            $this->formatted = $value;
            if (isset($this->monologRecord)) {
                $this->monologRecord->formatted = $value; // Update the cached Monolog record if it exists
            }
            return;
        }
        throw new IllegalOperationException('Unsupported operation: setting ' . $offset);
    }

    /**
     * Unset a property in the LogRecord.
     *
     * @param mixed $offset The property name to unset.
     * @throws IllegalOperationException Always, as unsetting properties is not supported.
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new IllegalOperationException('Unsupported operation for unsetting properties');
    }

    /**
     * Create a new LogRecord instance with modified properties.
     *
     * @param mixed ...$args The properties to modify in the new LogRecord instance.
     * @return LogRecordInterface A new LogRecord instance with the specified modifications.
     */
    public function with(...$args): LogRecordInterface
    {
        foreach (['message', 'context', 'level', 'channel', 'datetime', 'extra'] as $prop) {
            $args[$prop] ??= $this->{$prop};
        }

        return new self(...$args);
    }
}
