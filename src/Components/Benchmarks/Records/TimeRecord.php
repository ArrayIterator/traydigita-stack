<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Benchmarks\Records;

use TrayDigita\WP\Stack\Source\Interfaces\Collections\TimeRecordInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Time;
use function serialize;
use function unserialize;

/**
 * @property-read string $name The name of the time record
 * @property-read int $start The start time in nanoseconds
 * @property-read ?int $end The end time in nanoseconds or null if not finalized
 * @property-read int $elapsed The elapsed time in nanoseconds
 */
class TimeRecord implements TimeRecordInterface
{
    /**
     * @var ?int $start The start time in nanoseconds
     */
    private ?int $start;

    /**
     * @var ?int $end The end time in nanoseconds or null if not finalized
     */
    private ?int $end;

    /**
     * TimeRecord constructor.
     * Initializes the start time to the current time in nanoseconds
     * @param string $name The name of the time record
     */
    public function __construct(private string $name = '')
    {
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
    public function getStartTime(): ?int
    {
        return $this->start??null;
    }

    /**
     * @inheritdoc
     */
    public function getEndTime(): ?int
    {
        return $this->end??null;
    }

    /**
     * @inheritdoc
     */
    public function end(): self
    {
        $this->end ??= Time::nano();
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function start(): self
    {
        $this->start ??= Time::nano();
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function elapsed(): int
    {
        $start = $this->start ?? Time::nano();
        $end = $this->end ?? Time::nano();
        return $end - $start;
    }

    /**
     * Magic method to get the value of a property.
     *
     * @param string $name
     * @return string|int|null
     */
    public function __get(string $name) : string|int|null
    {
        return match ($name) {
            'name' => $this->getName(),
            'start' => $this->getStartTime(),
            'end' => $this->getEndTime(),
            'elapsed' => $this->elapsed(),
            default => null,
        };
    }

    public function __set(string $name, mixed $value): void
    {
        // none
    }

    public function __isset(string $name): bool
    {
        return match ($name) {
            'name', 'elapsed' => true,
            'start' => $this->getStartTime() !== null,
            'end' => $this->getEndTime() !== null,
            default => false,
        };
    }

    public function __unset(string $name): void
    {
        // none
    }

    /**
     * @inheritdoc
     */
    public function serialize(): ?string
    {
        return serialize($this->__serialize());
    }

    /**
     * @inheritdoc
     */
    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    /**
     * @inheritdoc
     */
    public function __serialize(): array
    {
        return [
            'name' => $this->getName(),
            'start' => $this->getStartTime(),
            'end' => $this->getEndTime(),
        ];
    }

    /**
     * @inheritdoc
     */
    public function __unserialize(array $data): void
    {
        $this->name = $data['name'] ?? '';
        $this->start = $data['start'] ?? null;
        $this->end = $data['end'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->getName(),
            'start' => $this->getStartTime(),
            'end' => $this->getEndTime(),
        ];
    }
}
