<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Collections;

use ArrayAccess;
use Countable;
use JsonSerializable;
use Serializable;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\LockableInterface;

/**
 * @extends ArrayAccess<string, TimeRecordInterface>
 */
interface TimeRecordsInterface extends LockableInterface, Serializable, JsonSerializable, ArrayAccess, Countable
{
    /**
     * Create a new time record with the given name
     *
     * @param string $name The name of the time record
     * @return TimeRecordInterface The newly created time record
     */
    public static function createRecord(string $name) : TimeRecordInterface;

    /**
     * Check if the time record collection is locked
     *
     * @return bool
     */
    public function isLocked(): bool;

    /**
     * Lock the time record to prevent further modifications
     *
     * @return self The current instance of TimeRecords
     */
    public function lock() : self;

    /**
     * Get the array of time records
     *
     * @return array<string, TimeRecordInterface> The array of time records
     */
    public function getRecords(): array;

    /**
     * Attach an existing time record to the collection
     *
     * @param TimeRecordInterface $record The time record to attach
     */
    public function attach(TimeRecordInterface $record): self;

    /**
     * Remove a time record by name or by passing the time record object
     *
     * @param string|TimeRecordInterface $name The name of the time record or the time record object
     * @return ?TimeRecordInterface The removed time record if found, null otherwise
     */
    public function remove(string|TimeRecordInterface $name): ?TimeRecordInterface;

    /**
     * Add a new time record to the collection
     *
     * @param string $name The name of the time record
     * @return TimeRecordInterface The newly created time record
     */
    public function add(string $name): TimeRecordInterface;

    /**
     * Begin a new time record with the given name
     * @template T of TimeRecordInterface
     * @param string|T $name The name of the time record
     * @return TimeRecordInterface|T The newly created time record
     */
    public function begin(string|TimeRecordInterface $name): TimeRecordInterface;

    /**
     * End a time record by name or by passing the time record object
     *
     * @param string|TimeRecordInterface $name The name of the time record or the time record object
     * @return ?TimeRecordInterface The finalized time record if found, null otherwise
     */
    public function end(string|TimeRecordInterface $name): ?TimeRecordInterface;

    /**
     * Magic method to get a time record by name or the array of time records
     *
     * @param string $name The name of the time record or 'records' to get all records
     * @return ?TimeRecordInterface
     * The time record if found, the array of time records if 'records' is requested, null otherwise
     */
    public function __get(string $name) : ?TimeRecordInterface;

    /**
     * Set a time record by name
     *
     * @param string $name The name of the time record
     * @param TimeRecordInterface|mixed $value The time record to set, if not a time record, it will be ignored
     */
    public function __set(string $name, mixed $value): void;

    /**
     * Check if a time record exists by name
     *
     * @param string $name The name of the time record
     * @return bool True if the time record exists, false otherwise
     */
    public function __isset(string $name): bool;

    /**
     * Unset a time record by name
     *
     * @param string $name The name of the time record
     */
    public function __unset(string $name): void;

    /**
     * Get a time record by name
     *
     * @param string $name The name of the time record
     * @return ?TimeRecordInterface The time record if found, null otherwise
     */
    public function get(string $name): ?TimeRecordInterface;

    /**
     * Check if a time record exists by name or by passing the time record object
     *
     * @param string|TimeRecordInterface $offset The name of the time record or the time record object
     * @return bool True if the time record exists, false otherwise
     */
    public function has(string|TimeRecordInterface $offset) : bool;
}
