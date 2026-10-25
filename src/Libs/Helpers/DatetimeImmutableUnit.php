<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Stringable;
use function function_exists;
use function method_exists;

class DatetimeImmutableUnit extends DateTimeImmutable implements Stringable
{
    /**
     * @var DateTimeZone
     */
    private static DateTimeZone $utcTimeZone;

    /**
     * @var DateTimeZone
     */
    private static DateTimeZone $wpTimeZone;

    /**
     * Get cached UTC timezone instance.
     *
     * @return DateTimeZone
     */
    public static function utcTimeZone(): DateTimeZone
    {
        return self::$utcTimeZone ??= new DateTimeZone('UTC');
    }

    /**
     * Converts the DateTimeImmutable object to milliseconds since the Unix epoch.
     *
     * @return int
     */
    public function toMilliSeconds(): int
    {
        return (int)($this->format('Uv'));
    }

    /**
     * Converts the DateTimeImmutable object to microseconds since the Unix epoch.
     *
     * @return int
     */
    public function toMicroseconds(): int
    {
        return (int)($this->format('Uu'));
    }

    /**
     * @return DateTimeZone
     */
    public static function wpTimezone(): DateTimeZone
    {
        /** @noinspection PhpFullyQualifiedNameUsageInspection */
        return self::$wpTimeZone ??= function_exists('wp_timezone') ? \wp_timezone() : self::utcTimeZone();
    }

    /**
     * Wraps a DateTimeImmutable object into a DatetimeImmutableUnit object.
     *
     * @param DateTimeInterface $datetime
     * @return self
     * @throws Exception
     */
    public static function wrap(DateTimeInterface $datetime): self
    {
        return new self($datetime->format('c'), $datetime->getTimezone());
    }

    /**
     * @return self
     */
    public static function now(): self
    {
        return new self();
    }

    /**
     * @param $format
     * @param $datetime
     * @param $timezone
     * @return self
     * @throws Exception
     */
    public static function createFromFormat($format, $datetime, $timezone = null): self
    {
        $datetime = parent::createFromFormat($format, $datetime, $timezone);
        if (!$datetime) {
            throw new Exception('Failed to create DateTimeImmutable from format: ' . $format);
        }
        return static::wrap($datetime);
    }

    /**
     * Creates a new instance of the class from a mutable DateTime object.
     *
     * @param DateTime $object
     * @return self
     * @throws Exception
     */
    public static function createFromMutable(DateTime $object): self
    {
        return static::wrap(parent::createFromMutable($object));
    }

    /**
     * Creates a new instance of the class from a DateTimeInterface object.
     *
     * @param DateTimeInterface $object
     * @return self
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function createFromInterface(DateTimeInterface $object): self
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return self::wrap(parent::createFromInterface($object));
    }

    /**
     * Creates a new instance of the class from a database datetime string.
     *
     * @param string $datetime
     * @param DateTimeZone|null $timezone
     * @return self
     * @throws Exception
     */
    public static function createFromDatabase(string $datetime, ?DateTimeZone $timezone = null): self
    {
        return self::createFromFormat('Y-m-d H:i:s', $datetime, $timezone);
    }

    /**
     * Creates a new instance of the class from a WordPress database datetime string.
     *
     * @param string $datetime
     * @return self
     * @throws Exception
     */
    public static function createFromWordPressDatabase(string $datetime): self
    {
        return self::createFromFormat('Y-m-d H:i:s', $datetime, self::wpTimezone());
    }

    /**
     * Creates a new instance of the class from a timestamp.
     *
     * @param float|int $timestamp
     * @return self
     * @throws Exception
     */
    public static function createFromTimestamp(float|int $timestamp): self
    {
        if (method_exists(parent::class, 'createFromTimestamp')) {
            return self::wrap(parent::createFromTimestamp($timestamp));
        }
        return new self('@' . (int)$timestamp, self::utcTimeZone());
    }

    /**
     * Implements the Stringable interface to provide a string representation of the object.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->format('Y-m-d H:i:s');
    }
}
