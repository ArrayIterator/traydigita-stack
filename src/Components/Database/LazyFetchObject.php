<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Database;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use JsonSerializable;
use Serializable;
use Traversable;
use function array_key_exists;
use function gettype;
use function is_string;
use function serialize;
use function strtolower;
use function unserialize;

/**
 * @template TKey of non-empty-string
 * @template TNormalizedKey of TKey&lowercase-string
 * @template TValue of mixed
 * @template-implements IteratorAggregate<TKey, TValue>
 * @template-implements ArrayAccess<TKey, TValue>
 */
class LazyFetchObject implements IteratorAggregate, ArrayAccess, Serializable, JsonSerializable
{
    /**
     * @var array<TNormalizedKey, TValue> $data
     */
    private array $data;

    /**
     * @var array<TNormalizedKey, TValue> $new_data
     */
    private array $new_data;

    /**
     * @var TNormalizedKey[] $blacklisted_key
     */
    protected array $blacklisted_key;

    /**
     * @var bool $constructed
     */
    private bool $constructed;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->constructed = true;
    }

    /**
     * @param TNormalizedKey|TKey $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->all()[strtolower($name)]);
    }

    /**
     * Set the value for a given property.
     *
     * @param TKey|TNormalizedKey $name
     * @param TValue $value
     */
    public function set(string $name, mixed $value) : void
    {
        /**
         * @var TNormalizedKey $name
         */
        $name = strtolower($name);
        if (!isset($this->data) || !array_key_exists($name, $this->data)) {
            return;
        }
        $oldVal = $this->data[$name];
        /**
         * @var TValue $oldVal
         */
        $value = $this->setupNewValue($name, $value, $oldVal, $found);
        if (!$found) {
            return;
        }
        /**
         * @var TValue $value
         */
        $this->new_data ??= [];
        $this->new_data[$name] = $value;
    }

    /**
     * Get the value for a given property.
     *
     * @param TKey|TNormalizedKey $name
     * @return TValue
     */
    public function get(string $name) : mixed
    {
        return $this->all()[strtolower($name)];
    }

    /**
     * Get the blacklisted keys for this object.
     *
     * @return TNormalizedKey[]
     */
    protected function getBlackListedKeys() : array
    {
        return $this->blacklisted_key??[];
    }

    /**
     * Set the value for a given property.
     *
     * @param TKey|TNormalizedKey $name
     * @param TValue $value
     */
    final public function __set(string $name, mixed $value): void
    {
        if (!empty($this->constructed)) {
            $this->set($name, $value);
            return;
        }
        $this->data ??= [];
        /**
         * @var TNormalizedKey $lower
         */
        $lower = strtolower($name);
        $this->data[$lower] ??= $this->convertValue($lower, $value, $name);
    }

    /**
     * Convert the value for a given property.
     *
     * @param TNormalizedKey $name
     * @param TValue $value
     * @param string $realName
     * @return TValue
     */
    protected function convertValue(string $name, mixed $value, string $realName): mixed
    {
        return $value;
    }

    /**
     * Set up the new value for a given property.
     *
     * @param TNormalizedKey $name
     * @param TValue $value
     * @param TValue $oldValue
     * @param bool|null $canBeSet
     * @param-out bool $canBeSet
     * @return TValue
     */
    protected function setupNewValue(string $name, mixed $value, mixed $oldValue, bool &$canBeSet = null): mixed
    {
        $canBeSet = $value !== $oldValue && gettype($value) === gettype($oldValue);
        return $value;
    }

    /**
     * Get all the data for this object.
     *
     * @return array<TNormalizedKey, TValue>
     */
    public function all(): array
    {
        return $this->data ?? [];
    }

    /**
     * Get the new data for this object.
     *
     * @return array<TNormalizedKey, TValue>
     */
    public function getNewData(): array
    {
        return $this->new_data ?? [];
    }

    /**
     * Clear the new data for this object.
     */
    public function clearNewData(): void
    {
        $this->new_data = [];
    }

    /**
     * Check if a property is set.
     *
     * @param TNormalizedKey|TKey $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Unset a property.
     *
     * @param TNormalizedKey|TKey $name
     */
    public function __unset(string $name): void
    {
        if (!isset($this->new_data)) {
            return;
        }
        unset($this->new_data[$name]);
        // void
    }

    /**
     * Get a property.
     *
     * @param TNormalizedKey|TKey $name
     * @return mixed
     */
    public function __get(string $name) : mixed
    {
        return $this->get($name);
    }

    /**
     * Get the value for a given property.
     *
     * @param TNormalizedKey|TKey $name
     * @param array<array-key, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->get($name);
    }

    /**
     * Check if a property is set.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }
        /**
         * @var TNormalizedKey|TKey $offset
         */
        return $this->has($offset);
    }

    /**
     * Get a property.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset)) {
            return null;
        }
        /**
         * @var TNormalizedKey|TKey $offset
         */
        return $this->get($offset);
    }

    /**
     * Set a property.
     *
     * @param mixed|TNormalizedKey|TKey $offset
     * @param mixed|TValue $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset)) {
            return;
        }
        /**
         * @var TNormalizedKey|TKey $offset
         */
        $this->set($offset, $value);
    }

    /**
     * Unset a property.
     *
     * @param mixed|TNormalizedKey|TKey $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        /**
         * @var TNormalizedKey|TKey $offset
         */
        if (!is_string($offset)) {
            return;
        }
        $this->__unset($offset);
    }

    /**
     * Get an iterator for this object.
     *
     * @return Traversable<TNormalizedKey, TValue>
     */
    public function getIterator(): Traversable
    {
        /**
         * @var Traversable<TNormalizedKey, TValue> $result
         */
        $result = new ArrayIterator($this->all());
        return $result;
    }

    /**
     * @return array<TNormalizedKey, TValue>
     */
    public function toArray(): array
    {
        return $this->all();
    }

    /**
     * Serialize the object to a string.
     *
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this->toArray());
    }

    /**
     * Unserialize the object from a string.
     *
     * @param string $data
     */
    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data, ['allowed_classes' => false]));
    }

    /**
     * Serialize the object to an array.
     *
     * @return array<TNormalizedKey, TValue>
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * Unserialize the object from an array.
     *
     * @param array<TNormalizedKey, TValue> $data
     */
    public function __unserialize(array $data): void
    {
        $this->data = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                $key = (string) $key;
            }
            /**
             * @var TNormalizedKey $key
             */
            $key = strtolower($key);
            $this->data[$key] = $value;
        }
        $this->constructed = true;
    }

    /**
     * Serialize the object to a JSON-serializable value.
     *
     * @return array<TNormalizedKey, TValue>
     */
    public function jsonSerialize(): array
    {
        $result = $this->toArray();
        foreach ($this->getBlackListedKeys() as $item) {
            if (!is_string($item)) {
                continue;
            }
            if (array_key_exists($item, $result)) {
                unset($result[$item]);
            }
        }
        return $result;
    }
}
