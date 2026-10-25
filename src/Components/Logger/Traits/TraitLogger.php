<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger\Traits;

use Closure;
use DateTimeInterface;
use DateTimeZone;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Processor\ProcessorInterface;
use Psr\Log\LoggerTrait;
use Stringable;
use Throwable;
use TrayDigita\WP\Stack\Source\Components\Defaults\DatetimeZoneDefault;
use TrayDigita\WP\Stack\Source\Components\Logger\DatetimeImmutableJsonSerializable;
use TrayDigita\WP\Stack\Source\Components\Logger\Logger;
use TrayDigita\WP\Stack\Source\Components\Logger\LogRecord;
use TrayDigita\WP\Stack\Source\Components\Logger\MonologHandlerAdapter;
use TrayDigita\WP\Stack\Source\Components\Logger\MonologProcessorAdapter;
use TrayDigita\WP\Stack\Source\Enums\LogLevel;
use TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException;
use TrayDigita\WP\Stack\Source\Exceptions\InvalidArgumentException;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ResettableInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogHandlerInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogProcessorInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogRecordInterface;
use function array_reverse;
use function array_shift;
use function array_unshift;
use function date_default_timezone_get;
use function is_array;
use function is_int;
use function max;
use function min;

trait TraitLogger
{
    use LoggerTrait;

    /**
     * The name of the logger.
     *
     * @var LogHandlerInterface[]
     */
    protected array $handlers = [];

    /**
     * The log processors for the logger.
     *
     * @var LogProcessorInterface[]
     */
    protected array $processors = [];

    /**
     * Whether to use microsecond timestamps for log records.
     *
     * @var bool
     */
    protected bool $useMicrosecondTimestamps = true;

    /**
     * The number of cycles for the logger.
     *
     * @var int
     */
    protected int $cycles = 0;

    /**
     * The maximum number of cycles for the logger.
     *
     * @var int
     */
    protected int $maxCycles = Logger::DEFAULT_CYCLES;

    /**
     * The custom exception handler for the logger.
     *
     * @var Closure|null
     */
    protected ?Closure $exceptionHandler = null;

    /**
     * The timezone for the logger.
     *
     * @var DateTimeZone
     */
    protected DateTimeZone $timezone;

    /**
     * The default logging level for the logger.
     *
     * @var LogLevel
     */
    protected LogLevel $defaultLevel = LogLevel::DEBUG;

    /**
     * @param string $name
     * @param array<LogHandlerInterface|HandlerInterface> $handlers
     * @param array<LogProcessorInterface|ProcessorInterface> $processors
     * @param ?DateTimeZone $timezone
     * @param LogLevel $defaultLevel
     */
    public function __construct(
        protected string $name = Logger::DEFAULT_NAME,
        array $handlers = [],
        array $processors = [],
        ?DateTimeZone $timezone = new DatetimeZoneDefault,
        LogLevel $defaultLevel = LogLevel::DEBUG
    ) {
        foreach ($handlers as $handler) {
            if ($handler instanceof HandlerInterface) {
                $handler = MonologHandlerAdapter::create($handler);
            }
            if (!$handler instanceof LogHandlerInterface) {
                throw new InvalidArgumentException(
                    'Handler must implement LogHandlerInterface or HandlerInterface'
                );
            }
            $this->pushHandler($handler);
        }
        foreach ($processors as $processor) {
            if ($processor instanceof ProcessorInterface) {
                $processor = MonologProcessorAdapter::create($processor);
            }
            if (!$processor instanceof LogProcessorInterface) {
                throw new InvalidArgumentException(
                    'Processor must implement LogProcessorInterface or ProcessorInterface'
                );
            }
            $this->pushProcessor($processor);
        }
        try {
            $this->timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
        } catch (Throwable) {
            $this->timezone = new DateTimeZone('UTC');
        }
        $this->setDefaultLevel($defaultLevel);
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getTimezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * @inheritdoc
     */
    public function setDefaultLevel(string|int|LogLevel|Level $level): self
    {
        $this->defaultLevel = LogLevel::fromLevel($level);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultLevel(): LogLevel
    {
        return $this->defaultLevel;
    }

    /**
     * @inheritdoc
     */
    public function withName(string $name): self
    {
        $new = clone $this;
        $new->name = $name;
        return $new;
    }

    /**
     * @inheritdoc
     */
    public function getExceptionHandler(): ?Closure
    {
        return $this->exceptionHandler;
    }

    /**
     * @inheritdoc
     */
    public function setTimezone(DateTimeZone $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setExceptionHandler(?Closure $exceptionHandler): self
    {
        $this->exceptionHandler = $exceptionHandler;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setMaxCycles(int $maxCycles): self
    {
        $this->maxCycles = min(max($maxCycles, Logger::MIN_CYCLES), Logger::MAX_CYCLES);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMaxCycles(): int
    {
        return $this->maxCycles;
    }

    /**
     * @inheritdoc
     */
    public function pushHandler(LogHandlerInterface|HandlerInterface $handler): self
    {
        if ($handler instanceof HandlerInterface) {
            $handler = MonologHandlerAdapter::create($handler);
        }
        $this->handlers[] = $handler;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function pushProcessor(LogProcessorInterface|ProcessorInterface $processor): self
    {
        if ($processor instanceof ProcessorInterface) {
            $processor = MonologProcessorAdapter::create($processor);
        }
        $this->processors[] = $processor;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function unshiftHandler(LogHandlerInterface|HandlerInterface $handler): self
    {
        if ($handler instanceof HandlerInterface) {
            $handler = MonologHandlerAdapter::create($handler);
        }
        array_unshift($this->handlers, $handler);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function unshiftProcessor(LogProcessorInterface|ProcessorInterface $processor): self
    {
        if ($processor instanceof ProcessorInterface) {
            $processor = MonologProcessorAdapter::create($processor);
        }
        array_unshift($this->processors, $processor);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function popHandler(): LogHandlerInterface
    {
        if (0 === count($this->handlers)) {
            throw new IllegalOperationException('You tried to pop from an empty handler stack.');
        }
        return array_shift($this->handlers);
    }

    /**
     * @inheritdoc
     */
    public function popProcessor(): LogProcessorInterface
    {
        if (0 === count($this->processors)) {
            throw new IllegalOperationException('You tried to pop from an empty processor stack.');
        }
        return array_shift($this->processors);
    }

    /**
     * @inheritdoc
     */
    public function shiftHandler(): LogHandlerInterface
    {
        if (0 === count($this->handlers)) {
            throw new IllegalOperationException('You tried to shift from an empty handler stack.');
        }
        return array_shift($this->handlers);
    }

    /**
     * @inheritdoc
     */
    public function shiftProcessor(): LogProcessorInterface
    {
        if (0 === count($this->processors)) {
            throw new IllegalOperationException('You tried to shift from an empty processor stack.');
        }
        return array_shift($this->processors);
    }

    /**
     * @inheritdoc
     */
    public function setHandlers(LogHandlerInterface|HandlerInterface ...$handlers): self
    {
        $this->handlers = [];
        foreach (array_reverse($handlers) as $handler) {
            $this->pushHandler($handler);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setProcessors(LogProcessorInterface|ProcessorInterface ...$processors): self
    {
        $this->processors = [];
        foreach (array_reverse($processors) as $processor) {
            $this->pushProcessor($processor);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * @inheritdoc
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }

    /**
     * @inheritdoc
     */
    public function useMicrosecondTimestamps(bool $micro): self
    {
        $this->useMicrosecondTimestamps = $micro;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isUseMicrosecondTimestamps(): bool
    {
        return $this->useMicrosecondTimestamps;
    }

    /**
     * Delegates exception management to the custom exception handler,
     * or throws the exception if no custom handler is set.
     * @throws Throwable
     */
    protected function handleException(Throwable $e, LogRecordInterface $record): void
    {
        if (null === $this->exceptionHandler) {
            throw $e;
        }
        ($this->exceptionHandler)($e, $record);
    }

    /**
     * @inheritdoc
     */
    public function addRecord(
        mixed $level,
        string $message,
        array $context = [],
        DateTimeInterface|null $datetime = null
    ): bool {
        $level = LogLevel::fromLevel($level, $this->getDefaultLevel());
        if (!$datetime instanceof DatetimeImmutableJsonSerializable) {
            $datetime = $datetime
                ? DatetimeImmutableJsonSerializable::createFromInterface($datetime, $this->isUseMicrosecondTimestamps())
                : new DatetimeImmutableJsonSerializable($this->isUseMicrosecondTimestamps(), $this->timezone);
        }
        $cycle = $this->cycles;
        if ($cycle >= $this->maxCycles) {
            return false;
        }
        $this->cycles++;
        try {
            $recordInitialized = count($this->processors) === 0;
            $record = new LogRecord(
                datetime: $datetime,
                channel: $this->name,
                level: $level,
                message: $message,
                context: $context,
                extra: [],
            );
            $handled = false;
            foreach ($this->getHandlers() as $handler) {
                if (false === $recordInitialized) {
                    // skip initializing the record as long as no handler is going to handle it
                    if (!$handler->isHandling($record)) {
                        continue;
                    }

                    try {
                        foreach ($this->getProcessors() as $processor) {
                            $record = $processor($record);
                        }
                        $recordInitialized = true;
                    } catch (Throwable $e) {
                        $this->handleException($e, $record);

                        return true;
                    }
                }

                // once the record is initialized,
                // send it to all handlers as long as the bubbling chain is not interrupted
                try {
                    $handled = true;
                    if (true === $handler->handle(clone $record)) {
                        break;
                    }
                } catch (Throwable $e) {
                    $this->handleException($e, $record);

                    return true;
                }
            }

            return $handled;
        } finally {
            $this->cycles--;
            if ($this->cycles < 0) {
                $this->cycles = 0;
            }
        }
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param Stringable|string $message
     * @param array<array-key, mixed> $context
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->addRecord($level, (string)$message, $context);
    }

    /**
     * @inheritdoc
     */
    public function close(): void
    {
        foreach ($this->getHandlers() as $handler) {
            $handler->close();
        }
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
    {
        foreach ($this->getHandlers() as $handler) {
            if ($handler instanceof ResettableInterface) {
                $handler->reset();
            }
        }
        foreach ($this->getProcessors() as $processor) {
            if ($processor instanceof ResettableInterface) {
                $processor->reset();
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'handlers' => $this->handlers,
            'processors' => $this->processors,
            'useMicrosecondTimestamps' => $this->useMicrosecondTimestamps,
            'cycles' => $this->cycles,
            'maxCycles' => $this->maxCycles,
            'timezone' => $this->timezone,
            'defaultLevel' => $this->defaultLevel,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $key => $value) {
            switch ($key) {
                case 'handlers':
                    if (!is_array($value)) {
                        continue 2;
                    }
                    foreach ($value as $handler) {
                        if ($handler instanceof HandlerInterface) {
                            $handler = new MonologHandlerAdapter($handler);
                        }
                        if (!$handler instanceof LogHandlerInterface) {
                            continue;
                        }
                        $this->pushHandler($handler);
                    }
                    break;
                case 'processors':
                    $this->processors = [];
                    foreach ($value as $processor) {
                        if ($processor instanceof ProcessorInterface) {
                            $processor = new MonologProcessorAdapter($processor);
                        }
                        if (!$processor instanceof LogProcessorInterface) {
                            continue;
                        }
                        $this->pushProcessor($processor);
                    }
                    break;
                case 'timezone':
                    if ($value instanceof DateTimeZone) {
                        $this->timezone = $value;
                    }
                    break;
                case 'defaultLevel':
                    if ($value instanceof LogLevel) {
                        $this->defaultLevel = $value;
                    }
                    break;
                case 'useMicrosecondTimestamps':
                    $this->useMicrosecondTimestamps = (bool)$value;
                    break;
                case 'maxCycles':
                    if (is_int($value)) {
                        $this->maxCycles = min(max($value, Logger::MIN_CYCLES), Logger::MAX_CYCLES);
                    } else {
                        $this->maxCycles = Logger::DEFAULT_CYCLES;
                    }
                    break;
            }
        }
        $this->cycles = 0;
    }
}
