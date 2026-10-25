<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Logger;

use Closure;
use DateTimeInterface;
use DateTimeZone;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Processor\ProcessorInterface;
use TrayDigita\WP\Stack\Source\Enums\LogLevel;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ClosableInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ResettableInterface;

interface LoggerInterface extends \Psr\Log\LoggerInterface, ClosableInterface, ResettableInterface
{
    /**
     * Get the default log level for the logger.
     *
     * @return LogLevel The default log level.
     */
    public function getDefaultLevel(): LogLevel;

    /**
     * Set the default log level for the logger.
     *
     * @param string|int|LogLevel|Level $level The log level to set as default.
     * @return self The logger instance for method chaining.
     */
    public function setDefaultLevel(string|int|LogLevel|Level $level): self;

    /**
     * Create a new instance of the logger with a different name.
     *
     * @param string $name The new name for the logger.
     * @return self A new instance of the logger with the specified name.
     */
    public function withName(string $name): self;

    /**
     * Get the name of the logger.
     *
     * @return string The name of the logger.
     */
    public function getName(): string;

    /**
     * Get the timezone for the logger.
     *
     * @return DateTimeZone The timezone for the logger.
     */
    public function getTimezone(): DateTimeZone;

    /**
     * Get the custom exception handler for the logger.
     *
     * @return Closure|null A callable that takes a Throwable
     *  and a LogRecordInterface as parameters, or null if no custom handler is set.
     */
    public function getExceptionHandler(): ?Closure;

    /**
     * Set the timezone for the logger.
     *
     * @param DateTimeZone $timezone The timezone to set for the logger.
     */
    public function setTimezone(DateTimeZone $timezone): self;

    /**
     * Set a custom exception handler for the logger.
     *
     * @param Closure|null $exceptionHandler A closure that takes a Throwable and a LogRecordInterface as parameters.
     */
    public function setExceptionHandler(?Closure $exceptionHandler): self;

    /**
     * Set the maximum number of cycles for the logger.
     *
     * @param int $maxCycles The maximum number of cycles for the logger.
     * @return self The logger instance for method chaining.
     */
    public function setMaxCycles(int $maxCycles): self;

    /**
     * Get the maximum number of cycles for the logger.
     *
     * @return int The maximum number of cycles for the logger.
     */
    public function getMaxCycles(): int;

    /**
     * Add a log handler to the logger.
     *
     * @param LogHandlerInterface|HandlerInterface $handler The log handler to add.
     */
    public function pushHandler(LogHandlerInterface|HandlerInterface $handler): self;

    /**
     * Add a log processor to the logger.
     *
     * @param LogProcessorInterface|ProcessorInterface $processor The log processor to add.
     */
    public function pushProcessor(LogProcessorInterface|ProcessorInterface $processor): self;

    /**
     * Add a log handler to the beginning of the logger's handler stack.
     *
     * @param LogHandlerInterface|HandlerInterface $handler The log handler to add.
     */
    public function unshiftHandler(LogHandlerInterface|HandlerInterface $handler): self;

    /**
     * Add a log processor to the beginning of the logger's processor stack.
     *
     * @param LogProcessorInterface|ProcessorInterface $processor The log processor to add.
     */
    public function unshiftProcessor(LogProcessorInterface|ProcessorInterface $processor): self;

    /**
     * Remove and return the most recently added log handler from the logger.
     *
     * @return LogHandlerInterface The removed log handler.
     * @throws \TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException If there are no handlers to pop.
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function popHandler(): LogHandlerInterface;

    /**
     * Remove and return the most recently added log processor from the logger.
     *
     * @return LogProcessorInterface The removed log processor.
     * @throws \TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException If there are no processors to pop.
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function popProcessor(): LogProcessorInterface;

    /**
     * Remove and return the least recently added log handler from the logger.
     *
     * @return LogHandlerInterface The removed log handler.
     * @throws \TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException If there are no handlers to shift.
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function shiftHandler(): LogHandlerInterface;

    /**
     * Remove and return the least recently added log processor from the logger.
     *
     * @return LogProcessorInterface The removed log processor.
     * @throws \TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException If there are no processors to shift.
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function shiftProcessor(): LogProcessorInterface;

    /**
     * Set the log handlers for the logger, replacing any existing handlers.
     *
     * @param LogHandlerInterface|HandlerInterface ...$handlers The log handlers to set.
     */
    public function setHandlers(LogHandlerInterface|HandlerInterface ...$handlers): self;

    /**
     * Set the log processors for the logger, replacing any existing processors.
     *
     * @param LogProcessorInterface|ProcessorInterface ...$processors The log processors to set.
     */
    public function setProcessors(LogProcessorInterface|ProcessorInterface ...$processors): self;

    /**
     * Get the log handlers for the logger.
     *
     * @return array<LogHandlerInterface> The log handlers for the logger.
     */
    public function getHandlers(): array;

    /**
     * Get the log processors for the logger.
     *
     * @return array<LogProcessorInterface> The log processors for the logger.
     */
    public function getProcessors(): array;

    /**
     * Set whether the logger should use microsecond timestamps.
     *
     * @param bool $micro True to use microsecond timestamps, false otherwise.
     * @return self The logger instance for method chaining.
     */
    public function useMicrosecondTimestamps(bool $micro): self;

    /**
     * Check if the logger is configured to use microsecond timestamps.
     *
     * @return bool
     */
    public function isUseMicrosecondTimestamps(): bool;

    /**
     * Adds a log record to the logger.
     *
     * @param string|int|LogLevel|Level $level The log level for the record.
     * @param string $message The log message.
     * @param array<array-key, mixed> $context The context for the log record.
     * @param DateTimeInterface|null $datetime The datetime for the log record.
     *  If null, the current datetime will be used.
     * @return bool True if the record was handled by at least one handler, false otherwise.
     */
    public function addRecord(
        string|int|LogLevel|Level $level,
        string $message,
        array $context = [],
        DateTimeInterface|null $datetime = null
    ): bool;
}
