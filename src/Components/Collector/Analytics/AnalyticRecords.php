<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Collector\Analytics;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Serializable;
use Traversable;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function serialize;
use function time;
use function uasort;
use function unserialize;

/**
 * @template-implements IteratorAggregate<string, AnalyticRecord>
 * @template-implements ArrayAccess<string, AnalyticRecord>
 */
final class AnalyticRecords implements
    IteratorAggregate,
    ArrayAccess,
    Countable,
    Serializable,
    JsonSerializable
{
    /**
     * Timestamp for January 1, 1990, 01:01:01.
     * @var int
     */
    public const int TIME_1990 = 631155661; // 1990-01-01 01:01:01
    /**
     * @var int
     */
    protected int $timestamp;

    /**
     * @var array<string, AnalyticRecord>
     */
    protected array $data;

    /**
     * @var string
     */
    protected string $property_id;

    public function __construct(string $property_id, int $timestamp, AnalyticRecord ...$records)
    {
        $data = [];
        foreach ($records as $record) {
            $data[$record->getSlug()] = $record;
        }
        // sort by count
        uasort($data, function (AnalyticRecord $a, AnalyticRecord $b) {
            return $b->count() <=> $a->count();
        });
        $this->property_id = $property_id;
        $this->timestamp = $timestamp;
        $this->data = $data;
    }

    /**
     * Filters and validates the provided data array.
     *
     * @param mixed $data The data to filter and validate.
     * @return array<string, AnalyticRecord>|null Returns the filtered data array or null if invalid.
     */
    public static function filterMap(mixed $data): ?array
    {
        if (!is_array($data) || !is_int($data['timestamp']) || $data['timestamp'] < self::TIME_1990) {
            return null;
        }
        $propertyId = $data['property_id'] ?? null;
        if (!is_string($propertyId)) {
            return null;
        }
        $data = $data['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }
        foreach ($data as $key => $i) {
            if (!$i instanceof AnalyticRecord) {
                return null;
            }
            if (!is_string($key)) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * @return array{
     *     property_id: string,
     *     timestamp: int,
     *     data: array<string, AnalyticRecord>
     * }
     */
    public function toArray(): array
    {
        return [
            'property_id' => $this->getPropertyId(),
            'timestamp' => $this->getTimestamp(),
            'data' => $this->getData()
        ];
    }

    /**
     * Checks if the records have expired based on the provided hour limit.
     *
     * @param int $hour The number of hours to consider for expiration (default is 24).
     * @return bool Returns true if the records have expired, false otherwise.
     */
    public function isExpire(int $hour = 24): bool
    {
        $cTime = time();
        $expireTime = $cTime - ($hour * 3600);
        return ($this->timestamp - $expireTime) < 0
            // futures
            || $cTime < $this->timestamp;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Gets the data array.
     *
     * @return array<string, AnalyticRecord> The data array.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Gets the property ID.
     *
     * @return string The property ID.
     */
    public function getPropertyId(): string
    {
        return $this->property_id;
    }

    /**
     * Gets an iterator for the data array.
     *
     * @return Traversable<string, AnalyticRecord> The iterator for the data array.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Checks if the specified offset exists in the data array.
     *
     * @param mixed $offset The offset to check.
     * @return bool Returns true if the offset exists, false otherwise.
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }
        return isset($this->data[$offset]);
    }

    /**
     * Gets the value at the specified offset.
     *
     * @param mixed $offset The offset to retrieve.
     * @return mixed The value at the specified offset, or null if not found.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return !is_string($offset) ? null : ($this->data[$offset] ?? null);
    }

    /**
     * Sets the value at the specified offset.
     *
     * @param mixed $offset The offset to set.
     * @param mixed $value The value to set at the specified offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        // void
    }

    /**
     * Unsets the value at the specified offset.
     *
     * @param mixed $offset The offset to unset.
     */
    public function offsetUnset(mixed $offset): void
    {
        // void
    }

    /**
     * Counts the number of records in the data array.
     *
     * @return int The number of records in the data array.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * Unserializes the object from a serialized string representation.
     *
     * @param string $data The serialized string representation of the object.
     */
    public function unserialize(string $data): void
    {
        $data = unserialize($data);
        if (!is_array($data)) {
            $data = [
                'property_id' => '',
                'timestamp' => 0,
                'data' => []
            ];
        }
        $data['property_id'] = $data['property_id'] ?? '';
        $data['timestamp'] = $data['timestamp'] ?? 0;
        $data['data'] = $data['data'] ?? [];
        $data['data'] = is_array($data['data']) ? $data['data'] : [];
        $dataData = self::filterMap($data);
        $data['data'] = $dataData ?? [];
        $this->__unserialize($data);
    }

    /**
     * Unserializes the object from an array representation.
     *
     * @param array{
     *      property_id: string,
     *      timestamp: int,
     *      data: array<string, AnalyticRecord>
     *  } $data The array representation of the object.
     */
    public function __unserialize(array $data): void
    {
        $this->data = $data['data'];
        $this->timestamp = $data['timestamp'];
        $this->property_id = $data['property_id'];
    }

    /**
     * Serializes the object to an array representation.
     *
     * @return array{
     *      property_id: string,
     *      timestamp: int,
     *      data: array<string, AnalyticRecord>
     *  }
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * Merges the current records with another set of records, up to a specified limit.
     *
     * @param self $records The records to merge with.
     * @param int $limitUntil The maximum number of records to include in the merged result (default is 50).
     * @return self Returns a new instance of AnalyticRecords containing the merged data.
     */
    public function mergeWith(self $records, int $limitUntil = 50): self
    {
        $obj = clone $this;
        if ($records === $this) {
            return $obj;
        }
        $count = count($this->data);
        if ($count >= $limitUntil) {
            return $obj;
        }
        foreach ($records->data as $key => $item) {
            if (isset($obj->data[$key])) {
                continue;
            }
            if ($count++ >= $limitUntil) {
                break;
            }
            $obj->data[$key] = $item;
        }
        // sort by count
        uasort($obj->data, function (AnalyticRecord $a, AnalyticRecord $b) {
            return $b->getCount() <=> $a->getCount();
        });
        return $obj;
    }

    /**
     * @return array{
     *     property_id: string,
     *     timestamp: int,
     *     data: array<string, AnalyticRecord>
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
