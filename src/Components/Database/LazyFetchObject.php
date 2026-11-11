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
 * A class that provides lazy fetching of data from an array.
 * @template TVal of array<non-empty-lowercase-string, mixed>
 * @template-implements IteratorAggregate<key-of<TVal>, value-of<TVal>>
 * @template-implements ArrayAccess<key-of<TVal>, value-of<TVal>>
 */
class LazyFetchObject implements IteratorAggregate, ArrayAccess, Serializable, JsonSerializable
{
    /**
     * @var array<key-of<TVal>&lowercase-string, value-of<TVal>> $data
     */
    private array $data;

    /**
     * @var array<key-of<TVal>&lowercase-string, value-of<TVal>> $new_data
     */
    private array $new_data;

    /**
     * The blacklisted keys for this object.
     * @var (key-of<TVal>&lowercase-string)[] $blacklisted_key
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
     * @param key-of<TVal>|non-empty-string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->all()[strtolower($name)]);
    }

    /**
     * Set the value for a given property.
     *
     * @param key-of<TVal>|non-empty-string $name
     * @param value-of<TVal> $value
     */
    public function set(string $name, mixed $value): void
    {
        $name = strtolower($name);
        if (!isset($this->data) || !array_key_exists($name, $this->data)) {
            return;
        }
        /**
         * @var lowercase-string&key-of<TVal> $name
         */
        $oldVal = $this->data[$name];
        $value = $this->setupNewValue($name, $value, $oldVal, $found);
        if (!$found) {
            return;
        }
        if (!isset($this->new_data)) {
            $this->new_data = [];
        }
        $this->new_data[$name] = $value;
    }

    /**
     * Get the value for a given property.
     *
     * @param key-of<TVal>|non-empty-string $name
     * @return value-of<TVal>
     */
    public function get(string $name): mixed
    {
        return $this->all()[strtolower($name)];
    }

    /**
     * Get the blacklisted keys for this object.
     *
     * @return (key-of<TVal>&lowercase-string)[]
     */
    protected function getBlackListedKeys(): array
    {
        return $this->blacklisted_key ?? [];
    }

    /**
     * Set the value for a given property.
     *
     * @param key-of<TVal>|non-empty-string $name
     * @param value-of<TVal> $value
     */
    final public function __set(string $name, mixed $value): void
    {
        if (!empty($this->constructed)) {
            $this->set($name, $value);
            return;
        }
        if (!isset($this->data)) {
            /**
             * @var array<key-of<TVal>&lowercase-string, value-of<TVal>> $val
             */
            $val = [];
            $this->data = $val;
        }
        /**
         * @var lowercase-string&key-of<TVal> $lower
         */
        $lower = strtolower($name);
        $this->data[$lower] ??= $this->convertValue($lower, $value, $name);
    }

    /**
     * Convert the value for a given property.
     *
     * @param key-of<TVal>|non-empty-string $name
     * @param value-of<TVal> $value
     * @param string $realName
     * @return value-of<TVal>
     */
    protected function convertValue(string $name, mixed $value, string $realName): mixed
    {
        return $value;
    }

    /**
     * Set up the new value for a given property.
     *
     * @param key-of<TVal>|non-empty-string $name
     * @param value-of<TVal> $value
     * @param value-of<TVal> $oldValue
     * @param bool|null $canBeSet
     * @param-out bool $canBeSet
     * @return value-of<TVal>
     */
    protected function setupNewValue(string $name, mixed $value, mixed $oldValue, ?bool &$canBeSet = null): mixed
    {
        $canBeSet = $value !== $oldValue && gettype($value) === gettype($oldValue);
        return $value;
    }

    /**
     * Get all the data for this object.
     *
     * @phpstan-return array<key-of<TVal>&lowercase-string, value-of<TVal>>
     */
    public function all(): array
    {
        return $this->data ?? [];
    }

    /**
     * Get the new data for this object.
     *
     * @phpstan-return array<key-of<TVal>&lowercase-string, value-of<TVal>>
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
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        /**
         * @var key-of<TVal> $name
         */
        return $this->has($name);
    }

    /**
     * Unset a property.
     *
     * @param string $name
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
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        /**
         * @var key-of<TVal> $name
         */
        return $this->get($name);
    }

    /**
     * Get the value for a given property.
     *
     * @param string $name
     * @param array<array-key, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        /**
         * @var key-of<TVal> $name
         */
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
         * @var key-of<TVal> $offset
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
         * @var key-of<TVal> $offset
         */
        return $this->get($offset);
    }

    /**
     * Set a property.
     *
     * @param mixed|key-of<TVal> $offset
     * @param mixed|value-of<TVal> $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_string($offset)) {
            return;
        }
        /**
         * @var key-of<TVal> $offset
         */
        $this->set($offset, $value);
    }

    /**
     * Unset a property.
     *
     * @param mixed|key-of<TVal> $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!is_string($offset)) {
            return;
        }
        /**
         * @var key-of<TVal> $offset
         */
        $this->__unset($offset);
    }

    /**
     * Get an iterator for this object.
     *
     * @return Traversable<key-of<TVal>, value-of<TVal>>
     */
    public function getIterator(): Traversable
    {
        /**
         * @var Traversable<key-of<TVal>, value-of<TVal>> $result
         */
        $result = new ArrayIterator($this->all());
        return $result;
    }

    /**
     * Convert the object to an array.
     * @phpstan-return array<key-of<TVal>&lowercase-string, value-of<TVal>>
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
     * @phpstan-return array<key-of<TVal>&lowercase-string, value-of<TVal>>
     * @return TVal
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * Unserialize the object from an array.
     *
     * @phpstan-param array<key-of<TVal>&lowercase-string, value-of<TVal>> $data
     * @param TVal $data
     */
    public function __unserialize(array $data): void
    {
        $this->data = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                $key = (string)$key;
            }
            /**
             * @var key-of<TVal> $key
             */
            $key = strtolower($key);
            $this->data[$key] = $value;
        }
        $this->constructed = true;
    }

    /**
     * Serialize the object to a JSON-serializable value.
     *
     * @phpstan-return array<key-of<TVal>&lowercase-string, value-of<TVal>>
     * @return TVal
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
