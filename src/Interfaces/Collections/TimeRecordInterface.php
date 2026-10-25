<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Interfaces\Collections;

use JsonSerializable;
use Serializable;

interface TimeRecordInterface extends Serializable, JsonSerializable
{
    /**
     * TimeRecord constructor.
     * Initializes the start time to the current time in nanoseconds
     */
    public function __construct(string $name = '');

    /**
     * Get the name of the time record
     *
     * @return string The name of the time record
     */
    public function getName(): string;

    /**
     * Get the start time in nanoseconds
     *
     * @return ?int The start time in nanoseconds or null if not started
     */
    public function getStartTime(): ?int;

    /**
     * Get the end time in nanoseconds
     *
     * @return ?int The end time in nanoseconds or null if not finalized
     */
    public function getEndTime(): ?int;

    /**
     * Finalize the time record by setting the end time
     *
     * @return self The current instance of TimeRecord
     */
    public function end(): self;

    /**
     * Get the start time in nanoseconds
     *
     * @return self The current instance of TimeRecord
     */
    public function start(): self;

    /**
     * Get the elapsed time in nanoseconds
     *
     * @return int The elapsed time in nanoseconds
     */
    public function elapsed(): int;

    /**
     * @return array{
     *     name: string,
     *     start: ?int,
     *     end: ?int,
     * }
     */
    public function __serialize(): array;

    /**
     * @param array{
     *     name: string,
     *     start: ?int,
     *     end: ?int,
     * } $data
     */
    public function __unserialize(array $data): void;

    /**
     * @return array{
     *     name: string,
     *     start: ?int,
     *     end: ?int,
     * }
     */
    public function jsonSerialize(): array;
}
