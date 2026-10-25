<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Benchmarks;

use TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\TimeRecordInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\TimeRecordsInterface;
use function is_string;
use function serialize;
use function sprintf;
use function unserialize;

class TimeRecords implements TimeRecordsInterface
{
    /**
     * @var array<string, TimeRecordInterface> $records The array of time records
     */
    private array $records = [];

    /**
     * @var bool $locked Indicates whether the time record is locked or not
     */
    private bool $locked = false;

    /**
     * TimeRecords constructor.
     */
    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public static function createRecord(string $name): TimeRecordInterface
    {
        return new \TrayDigita\WP\Stack\Source\Components\Benchmarks\Records\TimeRecord($name);
    }

    /**
     * @inheritdoc
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * @inheritdoc
     */
    public function lock(): self
    {
        $this->locked = true;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @inheritdoc
     */
    public function attach(TimeRecordInterface $record): self
    {
        if ($this->isLocked()) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot attach time record "%s" because the collection is locked',
                    $record->getName()
                )
            );
        }
        $this->records[$record->getName()] = $record;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function remove(string|TimeRecordInterface $name): ?TimeRecordInterface
    {
        if ($this->isLocked()) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot remove time record "%s" because the collection is locked',
                    is_string($name) ? $name : $name->getName()
                )
            );
        }
        if (is_string($name)) {
            $record = $this->get($name);
            unset($this->records[$name]);
            if ($record) {
                unset($this->records[$record->getName()]);
            }
            return $record;
        }
        $record = $name;
        unset($this->records[$record->getName()]);
        return $record;
    }

    /**
     * @inheritdoc
     */
    public function add(string $name): TimeRecordInterface
    {
        if ($this->isLocked()) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot add time record "%s" because the collection is locked',
                    $name
                )
            );
        }
        if (isset($this->records[$name])) {
            return $this->records[$name];
        }
        $record = new \TrayDigita\WP\Stack\Source\Components\Benchmarks\Records\TimeRecord($name);
        $this->attach($record);
        return $record;
    }

    /**
     * @inheritdoc
     */
    public function begin(string|TimeRecordInterface $name): TimeRecordInterface
    {
        if ($name instanceof TimeRecordInterface) {
            $name->start();
            return $name;
        }
        $record = $this->add($name);
        $record->start();
        return $record;
    }

    /**
     * @inheritdoc
     */
    public function end(string|TimeRecordInterface $name): ?TimeRecordInterface
    {
        $record = $name instanceof TimeRecordInterface ? $name : $this->get($name);
        return $record?->end();
    }

    /**
     * @inheritdoc
     */
    public function __get(string $name) : ?TimeRecordInterface
    {
        return $this->get($name);
    }

    /**
     * @inheritdoc
     */
    public function __set(string $name, mixed $value): void
    {
        if ($value instanceof TimeRecordInterface) {
            $this->attach($value);
        }
    }

    /**
     * @inheritdoc
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * @inheritdoc
     */
    public function __unset(string $name): void
    {
        $this->remove($name);
    }

    /**
     * @inheritdoc
     */
    public function get(string $name): ?TimeRecordInterface
    {
        return $this->records[$name] ?? null;
    }

    /**
     * @inheritdoc
     */
    public function has(string|TimeRecordInterface $offset): bool
    {
        if (is_string($offset)) {
            return isset($this->records[$offset]);
        }
        $offset = $offset->getName();
        return isset($this->records[$offset]);
    }

    /**
     * @return string|null
     */
    public function serialize(): ?string
    {
        return serialize($this->__serialize());
    }

    /**
     * @param string $data
     */
    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    /**
     * @return array<string, TimeRecordInterface> Data which can be serialized by serialize
     */
    public function __serialize(): array
    {
        return $this->getRecords();
    }

    /**
     * @param array<string, TimeRecordInterface> $data Data which can be unserialized by unserialize
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $record) {
            if ($record instanceof TimeRecordInterface) {
                $this->attach($record);
            }
        }
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array<string, TimeRecordInterface> Data which can be serialized by json_encode
     */
    public function jsonSerialize(): array
    {
        return $this->getRecords();
    }

    /**
     * @inheritdoc
     */
    public function offsetExists(mixed $offset): bool
    {
        if (is_string($offset)) {
            return $this->has($offset);
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function offsetGet(mixed $offset): ?TimeRecordInterface
    {
        if (is_string($offset)) {
            return $this->get($offset);
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset) && $value instanceof TimeRecordInterface) {
            $this->attach($value);
        }
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset)) {
            $this->remove($offset);
        }
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return count($this->records);
    }
}
