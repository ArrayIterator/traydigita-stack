<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger;

use Monolog\Level;
use Throwable;
use TrayDigita\WP\Stack\Source\Enums\LogLevel;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ClosableInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ResettableInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogHandlerInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Logger\LogRecordInterface;

abstract class AbstractHandler implements LogHandlerInterface, ResettableInterface, ClosableInterface
{
    /**
     * The minimum logging level at which this handler will be triggered.
     *
     * @var LogLevel
     */
    protected LogLevel $level = LogLevel::DEBUG;

    /**
     * Whether the messages that are handled can bubble up the stack or not.
     *
     * @var bool
     */
    protected bool $bubble = true;

    /**
     * AbstractHandler constructor.
     *
     * @param string|int|LogLevel|Level $level The minimum logging level at which this handler will be triggered.
     * @param bool $bubble Whether the messages that are handled can bubble up the stack or not.
     */
    public function __construct(string|int|LogLevel|Level $level = LogLevel::DEBUG, bool $bubble = true)
    {
        $this->setLevel($level);
        $this->setBubble($bubble);
    }

    /**
     * Gets the minimum logging level at which this handler will be triggered.
     *
     * @return LogLevel The minimum logging level.
     */
    public function getLevel(): LogLevel
    {
        return $this->level;
    }

    /**
     * Sets the minimum logging level at which this handler will be triggered.
     *
     * @param string|int|LogLevel|Level $level The minimum logging level.
     */
    public function setLevel(string|int|LogLevel|Level $level): void
    {
        $this->level = LogLevel::fromLevel($level);
    }

    /**
     * Sets the bubbling behavior.
     *
     * @param  bool  $bubble true means that this handler allows bubbling.
     *                       false means that bubbling is not permitted.
     * @return $this
     */
    public function setBubble(bool $bubble): self
    {
        $this->bubble = $bubble;

        return $this;
    }

    /**
     * Gets the bubbling behavior.
     *
     * @return bool true means that this handler allows bubbling.
     *              false means that bubbling is not permitted.
     */
    public function getBubble(): bool
    {
        return $this->bubble;
    }

    /**
     * @inheritdoc
     */
    abstract public function handle(LogRecordInterface $record): bool;

    /**
     * @inheritdoc
     */
    public function isHandling(LogRecordInterface $record): bool
    {
        return $this->level->includes($record->getLevel());
    }

    /**
     * @inheritdoc
     */
    public function handleBatch(LogRecordInterface ...$records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    /**
     * @inheritdoc
     */
    public function close(): void
    {
    }

    /**
     * @inheritdoc
     */
    public function reset() // @phpstan-ignore missingType.return
    {
    }

    /**
     * Destructor to ensure the handler is closed properly.
     */
    public function __destruct()
    {
        try {
            $this->close();
        } catch (Throwable) {
            // do nothing
        }
    }

    /**
     * Serialize the handler, ensuring it is closed before serialization.
     *
     * @return array<string, mixed> The serialized representation of the handler.
     */
    public function __serialize(): array
    {
        $this->close();
        return (array) $this;
    }
}
