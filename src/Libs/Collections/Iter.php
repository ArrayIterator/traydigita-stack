<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections;

use AppendIterator;
use ArrayIterator;
use Countable;
use Generator;
use Iterator;
use JsonSerializable;
use SeekableIterator;
use SplDoublyLinkedList;
use function array_filter;
use function array_key_exists;
use function array_merge;
use function array_pop;
use function array_reverse;
use function array_shift;
use function array_slice;
use function array_splice;
use function array_unshift;
use function arsort;
use function asort;
use function current;
use function end;
use function get_class;
use function in_array;
use function is_array;
use function is_int;
use function iterator_count;
use function iterator_to_array;
use function key;
use function krsort;
use function ksort;
use function next;
use function prev;
use function reset;
use function rsort;
use function sprintf;
use function uasort;
use function uksort;
use const ARRAY_FILTER_USE_BOTH;
use const SORT_REGULAR;

/**
 * @template TKey
 * @template TValue
 * @implements SeekableIterator<TKey, TValue>
 */
final class Iter implements SeekableIterator, Countable, JsonSerializable
{
    /**
     * Slice an array while preserving keys.
     *
     * @param array<array-key, mixed> $array The input array to slice.
     * @param int $offset The starting index to slice from.
     * @param int|null $length The length of the slice. If null, slices until the end of the array.
     * @return array<array-key, mixed> The sliced array with preserved keys.
     */
    public static function sliceWith(array $array, int $offset, ?int $length = null): array
    {
        return array_slice($array, $offset, $length, true);
    }

    /**
     * Slice an array into a specified total number of elements.
     *
     * @param array<array-key, mixed> $array The input array to slice.
     * @param int $total The total number of elements to include in the slice.
     * @param bool $fifo If true, slices from the end (FIFO), otherwise slices from the beginning (LIFO).
     * FIFO is removing the first elements, LIFO is removing the last elements.
     * @return array<array-key, mixed> The sliced array with preserved keys.
     */
    public static function sliceInto(array $array, int $total, bool $fifo = true): array
    {
        if ($total <= 0) {
            return [];
        }
        $count = count($array);
        if ($count <= $total) {
            return $array;
        }
        return $fifo
            ? self::sliceWith($array, $count - $total)
            : self::sliceWith($array, 0, $total);
    }

    /**
     * Slice an array into a specified total number of elements
     * if the array has more than a specified number of elements.
     *
     * @param array<array-key, mixed> $array The input array to slice.
     * @param int $largerOrEqualThan The minimum number of elements required to perform the slice.
     * @param int $total The total number of elements to include in the slice.
     * @param bool $fifo If true, slices from the end (FIFO), otherwise slices from the beginning (LIFO).
     * FIFO is removing the first elements, LIFO is removing the last elements.
     * @return array<array-key, mixed> The sliced array with preserved keys.
     */
    public static function sliceIf(array $array, int $largerOrEqualThan, int $total, bool $fifo = true): array
    {
        if ($total <= 0) {
            return [];
        }
        if ($largerOrEqualThan <= 0 || empty($array)) {
            return $array;
        }
        $count = count($array);
        if ($count < $largerOrEqualThan || $count < $total) {
            return $array;
        }
        return $fifo
            ? self::sliceWith($array, $count - $total)
            : self::sliceWith($array, 0, $total);
    }

    /**
     * @var array<TKey, TValue>|Iterator<TKey, TValue>
     */
    private array|Iterator $iterable;

    /**
     * @var ?array<TKey, TValue>
     */
    private ?array $bufferTemp = null;

    /**
     * Iter constructor.
     *
     * @param iterable<TKey, TValue> $source The source iterable to wrap.
     */
    public function __construct(iterable $source = [])
    {
        if (is_array($source) || $source instanceof Iterator) {
            $this->iterable = $source;
        } else {
            $this->iterable = new ArrayIterator(iterator_to_array($source));
        }
    }

    /**
     * Create an instance of Iter from an array.
     * @template K of array-key
     * @template V of mixed
     * @param array<K, V> $array The input array to wrap in an Iter instance.
     * @return self<K, V> A new instance of Iter wrapping the provided array.
     */
    public static function fromArray(array $array): self
    {
        return new self($array);
    }

    /**
     * @inheritdoc
     */
    public function current(): mixed
    {
        if (is_array($this->iterable)) {
            /**
             * @var TValue
             */
            return current($this->iterable);
        }
        return $this->iterable->current();
    }

    /**
     * Push a value to the end of the iterable.
     *
     * This method adds a value to the end of the internal iterable. If the iterable is an array,
     * it appends the value directly. If it's an `Iter`, `SplDoublyLinkedList`, or `ArrayIterator`,
     * it uses their respective methods to add the value. If the iterable is a `Generator` or other
     * types, it converts it to an array and then appends the value.
     *
     * @param TValue $value The value to be added to the iterable.
     * @return void
     */
    public function push(mixed $value): void
    {
        if (is_array($this->iterable)) {
            $this->iterable[] = $value;
            return;
        }
        if ($this->iterable instanceof Iter) {
            $this->iterable->push($value);
            return;
        }
        if ($this->iterable instanceof SplDoublyLinkedList) {
            $this->iterable->push($value);
            return;
        }
        if ($this->iterable instanceof ArrayIterator
            || $this->iterable instanceof AppendIterator
        ) {
            $this->iterable->append($value);
            return;
        }
        if ($this->iterable instanceof Generator) {
            $iterable = $this->iterable;
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($iterable as $key => $item) {
                if (is_int($key) && array_key_exists($key, $this->iterable)) {
                    $this->iterable[] = $item;
                    continue;
                }
                $this->iterable[$key] = $item;
            }
            $this->iterable[] = $value;
            return;
        }
        $this->bufferTemp = null;
        $this->iterable = iterator_to_array($this->iterable);
        $this->iterable[] = $value;
    }

    /**
     * Unshift a value to the beginning of the iterable.
     *
     * This method adds a value to the beginning of the internal iterable. If the iterable is an array,
     * it uses the `array_unshift()` function to add the value. If it's an `Iter`, `SplDoublyLinkedList`,
     * or `ArrayIterator`, it uses their respective methods to add the value. If the iterable is a
     * `Generator` or other types, it converts it to an array and then unshifts the value.
     *
     * @param TValue $value The value to be added to the beginning of the iterable.
     * @return void
     */
    public function unshift(mixed $value): void
    {
        if (is_array($this->iterable)) {
            array_unshift($this->iterable, $value);
            return;
        }
        if (is_array($this->bufferTemp)) {
            array_unshift($this->bufferTemp, $value);
            return;
        }
        if ($this->iterable instanceof Iter) {
            $this->iterable->unshift($value);
            return;
        }
        if ($this->iterable instanceof SplDoublyLinkedList) {
            $this->iterable->unshift($value);
            return;
        }
        if ($this->iterable instanceof Generator) {
            /**
             * @var TValue $value
             */
            $this->bufferTemp = [];
            array_unshift($this->bufferTemp, $value); // @phpstan-ignore-line
            return;
        }

        $this->bufferTemp = null;
        $this->iterable = iterator_to_array($this->iterable);
        array_unshift($this->iterable, $value);
    }

    /**
     * Pop a value from the end of the iterable.
     *
     * This method removes and returns the last value from the internal iterable. If the iterable is an array,
     * it uses the `array_pop()` function to remove and return the last element.
     * If it's an `Iter`, `SplDoublyLinkedList`,
     * or `ArrayIterator`, it uses their respective methods to remove and return the last value. If the iterable is a
     * `Generator` or other types, it converts it to an array and then pops the last value.
     *
     * @return TValue|null The popped value, or false if the iterable is empty.
     */
    public function pop(): mixed
    {
        if (is_array($this->iterable)) {
            return array_pop($this->iterable);
        }
        if ($this->iterable instanceof Iter) {
            return $this->iterable->pop();
        }
        if ($this->iterable instanceof SplDoublyLinkedList) {
            return $this->iterable->pop();
        }
        if ($this->iterable instanceof Generator) {
            $iterable = $this->iterable;
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($iterable as $key => $item) {
                if (is_int($key) && array_key_exists($key, $this->iterable)) {
                    $this->iterable[] = $item;
                    continue;
                }
                $this->iterable[$key] = $item;
            }
            return array_pop($this->iterable);
        }
        if ($this->iterable instanceof ArrayIterator
            || $this->iterable instanceof AppendIterator
        ) {
            $this->bufferTemp = null;
            $this->iterable = iterator_to_array($this->iterable);
            return array_pop($this->iterable);
        }
        if (!is_array($this->iterable)) {
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($this->iterable as $item) {
                $this->iterable[] = $item;
            }
        }
        return array_pop($this->iterable);
    }

    /**
     * Shift a value from the beginning of the iterable.
     *
     * This method removes and returns the first value from the internal iterable. If the iterable is an array,
     * it uses the `array_shift()` function to remove and return the first element.
     * If it's an `Iter`, `SplDoublyLinkedList`,
     * or `ArrayIterator`, it uses their respective methods to remove and return the first value. If the iterable is a
     * `Generator` or other types, it converts it to an array and then shifts the first value.
     *
     * @return TValue|null The shifted value, or null if the iterable is empty.
     */
    public function shift(): mixed
    {
        if (is_array($this->iterable)) {
            return array_shift($this->iterable);
        }
        if ($this->iterable instanceof Iter) {
            return $this->iterable->shift();
        }
        if ($this->iterable instanceof SplDoublyLinkedList) {
            return $this->iterable->shift();
        }
        if ($this->iterable instanceof Generator) {
            if (!empty($this->bufferTemp)) {
                return array_shift($this->bufferTemp);
            }
            $iterable = $this->iterable;
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($iterable as $key => $item) {
                if (is_int($key) && array_key_exists($key, $this->iterable)) {
                    $this->iterable[] = $item;
                    continue;
                }
                $this->iterable[$key] = $item;
            }
            return array_shift($this->iterable);
        }

        if ($this->iterable instanceof ArrayIterator
            || $this->iterable instanceof AppendIterator
        ) {
            $this->bufferTemp = null;
            $this->iterable = iterator_to_array($this->iterable);
            return array_shift($this->iterable);
        }
        if (!is_array($this->iterable)) {
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($this->iterable as $item) {
                $this->iterable[] = $item;
            }
        }
        return array_shift($this->iterable);
    }

    /**
     * Convert the iterable to an array.
     *
     * This method converts the internal iterable to an array. If the iterable is already an array,
     * it returns it directly. If it's a `Generator`, it iterates through it and stores the values
     * in a temporary buffer before returning the array. If it's an `Iterator`, it also iterates
     * through it and stores the values in a temporary buffer before returning the array.
     *
     * @return array<TKey, TValue> The converted array.
     */
    public function toArray(): array
    {
        if (is_array($this->iterable)) {
            return $this->iterable;
        }
        if ($this->iterable instanceof Generator) {
            $iterable = $this->iterable;
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($iterable as $key => $item) {
                if (is_int($key) && array_key_exists($key, $this->iterable)) {
                    $this->iterable[] = $item;
                    continue;
                }
                $this->iterable[$key] = $item;
            }
            return $this->iterable;
        }
        $this->bufferTemp = null;
        return $this->iterable = iterator_to_array($this->iterable);
    }

    /**
     * Move the internal pointer to the next element.
     *
     * This method is used to move the internal pointer of the iterable to the next element.
     * If the iterable is an array, it uses the `next()` function to move the pointer.
     * If the iterable is a `Generator`, it checks if it's closed or running and handles accordingly.
     * If the iterable is an `Iterator`, it stores the current key-value pair
     * in a buffer and moves to the next element.
     *
     * @return void
     */
    public function next(): void
    {
        if (is_array($this->iterable)) {
            next($this->iterable);
            return;
        }
        $key = $this->iterable->key();
        if ($key !== null) {
            $this->bufferTemp[$key] = $this->iterable->current();
        }
        $this->iterable->next();
    }

    /**
     * Move the internal pointer to the previous element.
     *
     * This method is used to move the internal pointer of the iterable to the previous element.
     * If the iterable is an array, it uses the `prev()` function to move the pointer.
     * If the iterable is a `Generator`, it checks if it's closed or running and handles accordingly.
     * If the iterable is a `SeekableIterator`, it seeks to the previous element.
     * If the iterable is an `Iterator`, it stores the current key-value pair
     * in a buffer and moves to the previous element.
     *
     * @return void
     */
    public function prev(): void
    {
        if (is_array($this->iterable)) {
            prev($this->iterable);
            return;
        }
        if ($this->iterable instanceof Generator) {
            $iterable = $this->iterable;
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($iterable as $key => $item) {
                if (is_int($key) && array_key_exists($key, $this->iterable)) {
                    $this->iterable[] = $item;
                    continue;
                }
                $this->iterable[$key] = $item;
            }
            prev($this->iterable);
            return;
        }

        if (is_array($this->bufferTemp)) {
            prev($this->bufferTemp);
            return;
        }
        $key = $this->iterable->key();
        if ($key !== null) {
            $this->bufferTemp[$key] = $this->iterable->current();
        }
    }

    /**
     * @inheritdoc
     * @return TKey|null
     */
    public function key(): mixed
    {
        if (is_array($this->iterable)) {
            return key($this->iterable);
        }
        return $this->iterable->key();
    }

    /**
     * @inheritdoc
     */
    public function valid(): bool
    {
        if (is_array($this->iterable)) {
            return key($this->iterable) !== null;
        }
        return $this->iterable->valid();
    }

    /**
     * Move the internal pointer to the beginning of the iterable.
     *
     * This method is used to move the internal pointer of the iterable to the first element.
     * If the iterable is an array, it uses the `reset()` function to move the pointer.
     * If the iterable is a `Generator`, it checks if it's closed or running and handles accordingly.
     * If the iterable is an `Iterator`, it stores the current key-value pair
     * in a buffer and moves to the first element.
     *
     * @return TValue|false The value at the beginning of the iterable, or false if the iterable is empty.
     */
    public function reset(): mixed
    {
        if (is_array($this->iterable)) {
            return reset($this->iterable);
        }
        if (is_array($this->bufferTemp)) {
            return reset($this->bufferTemp);
        }
        if ($this->iterable instanceof Generator) {
            $this->bufferTemp = [];
            foreach ($this->iterable as $key => $item) {
                $this->iterable[$key] = $item; // @phpstan-ignore-line
                break; // only get the first item
            }
            return reset($this->iterable);
        }
        if (is_array($this->bufferTemp)) {
            return reset($this->bufferTemp);
        }
        $this->iterable->rewind();
        return $this->iterable->current();
    }

    /**
     * Move the internal pointer to the end of the iterable.
     *
     * This method is used to move the internal pointer of the iterable to the last element.
     * If the iterable is an array, it uses the `end()` function to move the pointer.
     * If the iterable is a `Generator`, it checks if it's closed or running and handles accordingly.
     * If the iterable is an `Iterator`, it stores the current key-value pair
     * in a buffer and moves to the last element.
     *
     * @return TValue|false The value at the end of the iterable, or false if the iterable is empty.
     */
    public function end(): mixed
    {
        if (is_array($this->iterable)) {
            return end($this->iterable);
        }
        if ($this->iterable instanceof Generator) {
            $iterable = $this->iterable;
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($iterable as $key => $item) {
                if (is_int($key) && array_key_exists($key, $this->iterable)) {
                    $this->iterable[] = $item;
                    continue;
                }
                $this->iterable[$key] = $item;
            }
            return end($this->iterable);
        }
        $this->bufferTemp = null;
        $this->iterable = iterator_to_array($this->iterable);
        return end($this->iterable);
    }

    /**
     * @inheritdoc
     */
    public function rewind(): void
    {
        if (is_array($this->iterable)) {
            reset($this->iterable);
            return;
        }
        if (!empty($this->bufferTemp)) {
            reset($this->bufferTemp);
            return;
        }
        if ($this->iterable instanceof Generator) {
            $this->bufferTemp = [];
            foreach ($this->iterable as $key => $item) {
                $this->bufferTemp[$key] = $item;
                break; // only get the first item
            }
            return;
        }
        if (is_array($this->bufferTemp)) {
            reset($this->bufferTemp);
            return;
        }
        $this->iterable->rewind();
    }

    /**
     * @inheritdoc
     */
    public function seek(mixed $offset): void
    {
        if (is_array($this->iterable)) {
            // move the internal pointer to the specified offset
            if (array_key_exists($offset, $this->iterable)) {
                reset($this->iterable);
                while (key($this->iterable) !== $offset) {
                    next($this->iterable);
                }
            }
            return;
        }
        if (is_array($this->bufferTemp)) {
            // move the internal pointer to the specified offset
            if (array_key_exists($offset, $this->bufferTemp)) {
                reset($this->bufferTemp);
                while (key($this->bufferTemp) !== $offset) {
                    next($this->bufferTemp);
                }
                return;
            }
        }
        $is_buffer_array = is_array($this->bufferTemp);
        if ($this->iterable instanceof Generator) {
            $iterable = $this->iterable;
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($iterable as $key => $item) {
                if (is_int($key) && array_key_exists($key, $this->iterable)) {
                    $this->iterable[] = $item;
                    continue;
                }
                $this->iterable[$key] = $item;
            }
            if (array_key_exists($offset, $this->iterable)) {
                reset($this->iterable);
                while (key($this->iterable) !== $offset) {
                    next($this->iterable);
                }
            }
            return;
        }
        if (!$is_buffer_array && $this->iterable instanceof SeekableIterator) {
            $this->iterable->seek($offset);
            return;
        }

        $this->bufferTemp = null;
        $this->iterable = iterator_to_array($this->iterable);
        if (array_key_exists($offset, $this->iterable)) {
            reset($this->iterable);
            while (key($this->iterable) !== $offset) {
                next($this->iterable);
            }
        }
    }

    /**
     * Map the values of the iterable using a callback function.
     *
     * @template RetVal of mixed
     * @param callable(TValue, TKey, array<TKey, TValue>) : RetVal $callback
     * @return self<TKey, RetVal>
     */
    public function map(callable $callback): self
    {
        $result = [];
        $array = $this->toArray();
        foreach ($array as $key => $value) {
            $result[$key] = $callback($value, $key, $array);
        }
        /**
         * @var self<TKey, RetVal>
         */
        return new self($result);
    }

    /**
     * Filter the values of the iterable using a callback function.
     *
     * @param callable(TValue, TKey, array<TKey, TValue>) : bool $callback
     * @return self<TKey, TValue>
     */
    public function filter(callable $callback): self
    {
        $result = array_filter(
            $this->toArray(),
            fn($value, $key) => $callback($value, $key, $this->toArray()),
            ARRAY_FILTER_USE_BOTH
        );
        /**
         * @var self<TKey, TValue>
         */
        return new self($result);
    }

    /**
     * Check if the iterable contains a specific value.
     *
     * @param mixed $value The value to check for in the iterable.
     * @return bool True if the value is found, false otherwise.
     */
    public function some(mixed $value): bool
    {
        if (is_array($this->iterable)) {
            return in_array($value, $this->iterable, true);
        }
        if (in_array($value, $this->bufferTemp ?? [], true)) {
            return true;
        }
        if ($this->iterable instanceof Generator) {
            $this->bufferTemp ??= [];
            foreach ($this->iterable as $key => $v) {
                $this->bufferTemp[$key] = $v;
                if ($v === $value) {
                    return true;
                }
            }
            $this->iterable = $this->bufferTemp;
            $this->bufferTemp = null;
            return false;
        }
        $this->bufferTemp = null;
        $this->iterable = iterator_to_array($this->iterable);
        return in_array($value, $this->iterable, true);
    }

    /**
     * Check if all values in the iterable satisfy a specific condition.
     *
     * @param callable(TValue, TKey, array<TKey, TValue>) : bool $callback The callback function to test each value.
     * @return bool True if all values satisfy the condition, false otherwise.
     */
    public function every(callable $callback): bool
    {
        $array = $this->toArray();
        foreach ($array as $key => $value) {
            if (!$callback($value, $key, $array)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Execute a callback function for each value in the iterable.
     *
     * @param callable(TValue, TKey, array<TKey, TValue>) : void $callback
     * The callback function to execute for each value.
     * @return void
     */
    public function forEach(callable $callback): void
    {
        $array = $this->toArray();
        foreach ($array as $key => $value) {
            $callback($value, $key, $array);
        }
    }

    /**
     * Sort the values of the iterable and return a new instance of Iter.
     *
     * @param int $sort_flags Optional sorting flags (default: SORT_REGULAR).
     * @return self<TKey, TValue> A new instance of Iter with the sorted values.
     */
    public function toSorted(int $sort_flags = SORT_REGULAR): self
    {
        $array = $this->toArray();
        sort($array, $sort_flags);
        /**
         * @var self<TKey, TValue>
         */
        return new self($array);
    }

    /**
     * Splice the iterable by removing a portion and optionally replacing it with new values,
     * and return a new instance of Iter.
     *
     * @template K of array-key
     * @template V of mixed
     * @param int $offset The starting index to remove elements from.
     * @param int|null $length The number of elements to remove. If null, removes all elements from the offset.
     * @phpstan-param ?iterable<K, V> $replacement An optional iterable to replace the removed elements.
     * @return self<TKey|K, TValue|V> A new instance of Iter with the spliced values.
     */
    public function toSpliced(int $offset, ?int $length = null, ?iterable $replacement = null): self
    {
        $array = $this->toArray();
        if ($replacement !== null) {
            $replacementArray = is_array($replacement) ? $replacement : iterator_to_array($replacement);
            array_splice($array, $offset, $length ?? count($array), $replacementArray);
        } else {
            array_splice($array, $offset, $length ?? count($array));
        }
        /**
         * @var self<TKey|K, TValue|V>
         */
        return new self($array);
    }

    /**
     * Sort the values of the iterable in reverse order and return a new instance of Iter.
     *
     * @param int $sort_flags Optional sorting flags (default: SORT_REGULAR).
     * @return self<TKey, TValue> A new instance of Iter with the sorted values in reverse order.
     */
    public function toRsort(int $sort_flags = SORT_REGULAR): self
    {
        $array = $this->toArray();
        rsort($array, $sort_flags);
        /**
         * @var self<TKey, TValue>
         */
        return new self($array);
    }

    /**
     * Sort the values of the iterable by key and return a new instance of Iter.
     *
     * @param int $sort_flags Optional sorting flags (default: SORT_REGULAR).
     * @return self<TKey, TValue> A new instance of Iter with the values sorted by key.
     */
    public function toKsort(int $sort_flags = SORT_REGULAR): self
    {
        $array = $this->toArray();
        ksort($array, $sort_flags);
        /**
         * @var self<TKey, TValue>
         */
        return new self($array);
    }

    /**
     * Sort the values of the iterable by key in reverse order and return a new instance of Iter.
     *
     * @param int $sort_flags Optional sorting flags (default: SORT_REGULAR).
     * @return self<TKey, TValue> A new instance of Iter with the values sorted by key in reverse order.
     */
    public function toKrsort(int $sort_flags = SORT_REGULAR): self
    {
        $array = $this->toArray();
        krsort($array, $sort_flags);
        /**
         * @var self<TKey, TValue>
         */
        return new self($array);
    }

    /**
     * Sort the values of the iterable in reverse order while maintaining key association
     * and return a new instance of Iter.
     *
     * @param int $sort_flags Optional sorting flags (default: SORT_REGULAR).
     * @return self<TKey, TValue> A new instance of Iter with the values sorted
     * in reverse order while maintaining key association.
     */
    public function toAsort(int $sort_flags = SORT_REGULAR): self
    {
        $array = $this->toArray();
        asort($array, $sort_flags);
        /**
         * @var self<TKey, TValue>
         */
        return new self($array);
    }

    /**
     * Sort the values of the iterable in reverse order while maintaining key association
     * and return a new instance of Iter.
     *
     * @param int $sort_flags Optional sorting flags (default: SORT_REGULAR).

     * @return self<TKey, TValue> A new instance of
     * Iter with the values sorted in reverse order while maintaining key association.
     */
    public function toArsort(int $sort_flags = SORT_REGULAR): self
    {
        $array = $this->toArray();
        arsort($array, $sort_flags);
        /**
         * @var self<TKey, TValue>
         */
        return new self($array);
    }

    /**
     * Sort the values of the iterable using a user-defined comparison function.
     *
     * @param callable(TValue, TValue) : int $callback The comparison function to use for sorting values.
     * @return void
     */
    public function uasort(callable $callback): void
    {
        $this->iterable = $this->toArray();
        uasort($this->iterable, $callback);
    }

    /**
     * Sort the values of the iterable using a user-defined comparison function
     * and return a new instance of Iter.
     *
     * @param callable(TValue, TValue) : int $callback The comparison function to use for sorting values.
     * @return self<TKey, TValue> A new instance of
     * Iter with the values sorted using the user-defined comparison function.
     */
    public function toUasort(callable $callback): self
    {
        $array = $this->toArray();
        uasort($array, $callback);
        /**
         * @var self<TKey, TValue>
         */
        return new self($array);
    }

    /**
     * Sort the values of the iterable by key using a user-defined comparison function.
     *
     * @param callable(TKey, TKey) : int $callback The comparison function to use for sorting keys.
     * @return void
     */
    public function uksort(callable $callback): void
    {
        $this->iterable = $this->toArray();
        uksort($this->iterable, $callback);
    }

    /**
     * Sort the values of the iterable by key using a user-defined comparison function
     * and return a new instance of Iter.
     *
     * @param callable(TKey, TKey) : int $callback The comparison function to use for sorting keys.
     * @return self<TKey, TValue> A new instance of Iter
     * with the values sorted by key using the user-defined comparison function.
     */
    public function toUksort(callable $callback): self
    {
        $array = $this->toArray();
        uksort($array, $callback);
        /**
         * @var self<TKey, TValue>
         */
        return new self($array);
    }

    /**
     * Reverse the order of the values in the iterable.
     *
     * @return self<TKey, TValue> A new instance of Iter with the values reversed.
     */
    public function reverse(): self
    {
        $reversed = array_reverse($this->toArray(), true);
        /**
         * @var self<TKey, TValue>
         */
        return new self($reversed);
    }

    /**
     * Merge the current iterable with one or more additional iterables.
     * @template K of array-key
     * @template V
     * @param iterable<K&TKey, V|TValue> ...$iterables One or more iterables to merge with the current iterable.
     * @return self<K&TKey, V|TValue> A new instance of Iter containing the merged values.
     */
    public function merge(iterable ...$iterables): self
    {
        $array = $this->toArray();
        foreach ($iterables as $iterable) {
            $array = array_merge($array, is_array($iterable) ? $iterable : iterator_to_array($iterable));
        }
        /**
         * @var array<K&TKey, V|TValue> $array
         */
        return new self($array);
    }

    /**
     * Get the count of values in the iterable.
     *
     * @return int The number of values in the iterable.
     */
    public function count(): int
    {
        if (is_array($this->iterable)) {
            return count($this->iterable);
        }
        if (is_array($this->bufferTemp)) {
            return count($this->bufferTemp);
        }
        if ($this->iterable instanceof Generator) {
            $iterable = $this->iterable;
            $this->iterable = $this->bufferTemp ?? [];
            $this->bufferTemp = null;
            foreach ($iterable as $key => $item) {
                if (is_int($key) && array_key_exists($key, $this->iterable)) {
                    $this->iterable[] = $item;
                    continue;
                }
                $this->iterable[$key] = $item;
            }
            return count($this->iterable);
        }
        return iterator_count($this->iterable);
    }

    /**
     * Slice the iterable into a new instance of Iter.
     *
     * @param int $offset The starting index to slice from.
     * @param int|null $length The length of the slice. If null, slices until the end of the iterable.
     * @return self<TKey, TValue> A new instance of Iter containing the sliced values.
     */
    public function slice(int $offset, ?int $length = null): self
    {
        /**
         * @var array<TKey, TValue> $slicedArray
         */
        $slicedArray = array_slice($this->toArray(), $offset, $length, true);
        /**
         * @var self<TKey, TValue> $result
         */
        $result = new self($slicedArray);
        return $result;
    }

    /**
     * @return string A string representation of the Iter object, including its class name and count of values.
     */
    public function __toString(): string
    {
        return sprintf('%s(%d)', get_class($this), count($this));
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<TKey, TValue> Data for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        if ($this->iterable instanceof JsonSerializable) {
            return $this->iterable->jsonSerialize();
        }
        return $this->toArray();
    }
}
