<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger;

use Monolog\Handler\HandlerInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ClosableInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ResettableInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogHandlerInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogRecordInterface;
use function array_map;

/**
 * This class adapts a Monolog handler to the LogHandlerInterface.
 *
 * @template T of HandlerInterface
 * @mixin HandlerInterface
 */
readonly class MonologHandlerAdapter implements LogHandlerInterface, ResettableInterface, ClosableInterface
{
    /**
     * MonologHandlerAdapter constructor.
     *
     * @param T $handler The Monolog handler to adapt.
     */
    public function __construct(public HandlerInterface $handler)
    {
    }

    /**
     * Creates a new instance of MonologHandlerAdapter.
     * @template S of HandlerInterface
     * @param S $handler The Monolog handler to adapt.
     * @return self<S> A new instance of MonologHandlerAdapter.
     */
    public static function create(HandlerInterface $handler): self
    {
        return new self($handler);
    }

    /**
     * Get the underlying Monolog handler.
     *
     * @return T The Monolog handler.
     */
    public function getHandler(): HandlerInterface
    {
        return $this->handler;
    }

    /**
     * @inheritdoc
     */
    public function handle(LogRecordInterface $record): bool
    {
        return $this->handler->handle($record->toMonologRecord());
    }

    /**
     * @inheritdoc
     */
    public function isHandling(LogRecordInterface $record): bool
    {
        return $this->handler->isHandling($record->toMonologRecord());
    }

    /**
     * @inheritdoc
     */
    public function handleBatch(LogRecordInterface ...$records): void
    {
        $monologRecords = array_map(fn(LogRecordInterface $record) => $record->toMonologRecord(), $records);
        $this->handler->handleBatch($monologRecords);
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
    {
        if ($this->handler instanceof \Monolog\ResettableInterface) {
            $this->handler->reset();
        }
    }

    /**
     * @inheritdoc
     */
    public function close(): void
    {
        $this->handler->close();
    }

    /**
     * Magic method to access properties of the underlying Monolog handler.
     *
     * @param string $name The property name.
     * @return mixed The property value.
     */
    public function __get(string $name) : mixed
    {
        return $this->handler->{$name};
    }

    /**
     * Magic method to set properties of the underlying Monolog handler.
     *
     * @param string $name The property name.
     * @param mixed $value The property value.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->handler->{$name} = $value;
    }

    /**
     * Magic method to call methods of the underlying Monolog handler.
     *
     * @param string $name The method name.
     * @param array<array-key, mixed> $arguments The method arguments.
     * @return mixed The method return value.
     */
    public function __call(string $name, array $arguments) : mixed
    {
        return $this->handler->{$name}(...$arguments);
    }

    /**
     * Magic method to unset properties of the underlying Monolog handler.
     *
     * @param string $name The property name.
     */
    public function __unset(string $name): void
    {
        unset($this->handler->{$name});
    }

    /**
     * Magic method to check if a property of the underlying Monolog handler is set.
     *
     * @param string $name The property name.
     * @return bool True if the property is set, false otherwise.
     */
    public function __isset(string $name): bool
    {
        return isset($this->handler->{$name});
    }
}
