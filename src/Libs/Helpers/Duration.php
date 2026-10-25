<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use ArrayAccess;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;
use Serializable;
use Stringable;
use function abs;
use function explode;
use function intdiv;
use function is_float;
use function is_int;
use function is_string;
use function round;
use function serialize;
use function str_pad;
use function strlen;
use function strtolower;
use function strtotime;
use function substr;
use function unserialize;

/**
 * @template-implements ArrayAccess<string, int>
 */
final class Duration implements JsonSerializable, Stringable, Serializable, ArrayAccess
{
    /**
     * Kept for backwards compatibility.
     *
     * This is not a limit for Duration::years because Duration is not
     * a calendar year.
     */
    public const MAX_YEARS = 9999;

    /**
     * Kept for backwards compatibility.
     *
     * This is not a limit for Duration::months because Duration is not
     * a calendar month.
     */
    public const MAX_MONTHS = 11;

    /**
     * @var int The number of microseconds in this Duration.  Can be negative.
     */
    private int $microseconds = 0;

    /**
     * @var int The number of milliseconds in this Duration.  Can be negative.
     */
    private int $milliseconds = 0;

    /**
     * @var int The number of seconds in this Duration.  Can be negative.
     */
    private int $seconds = 0;

    /**
     * @var int The number of minutes in this Duration.  Can be negative.
     */
    private int $minutes = 0;

    /**
     * @var int The number of hours in this Duration.  Can be negative.
     */
    private int $hours = 0;

    /**
     * @var int The number of days in this Duration.  Can be negative.
     */
    private int $days = 0;

    /**
     * @var int The number of months in this Duration.  Can be negative.
     */
    private int $months = 0;

    /**
     * @var int The number of years in this Duration.  Can be negative.
     */
    private int $years = 0;

    /**
     * @var bool Whether this Duration is immutable.  If true, any
     *             modification methods will return a new Duration
     *             instead of modifying this one.
     */
    private bool $immutable;

    /**
     * Create a Duration from its components.
     *
     * The components are added together to form the total duration.
     * Each component can be negative or positive.
     *
     * @param int $years The number of years in this Duration.  Can be negative.
     * @param int $months The number of months in this Duration.  Can be negative.
     * @param int $days The number of days in this Duration.  Can be negative.
     * @param int $hours The number of hours in this Duration.  Can be negative.
     * @param int $minutes The number of minutes in this Duration.  Can be negative.
     * @param int $seconds The number of seconds in this Duration.  Can be negative.
     * @param int $milliseconds The number of milliseconds in this Duration.  Can be negative.
     * @param int $microseconds The number of microseconds in this Duration.  Can be negative.
     * @param bool $immutable Whether this Duration is immutable.  If true, any
     *                       modification methods will return a new Duration
     *                       instead of modifying this one.
     */
    public function __construct(
        int $years = 0,
        int $months = 0,
        int $days = 0,
        int $hours = 0,
        int $minutes = 0,
        int $seconds = 0,
        int $milliseconds = 0,
        int $microseconds = 0,
        bool $immutable = false
    ) {
        /*
         * Keep the smallest unit first so every unit can carry into
         * the next unit.
         */
        $this
            ->addMicroseconds($microseconds)
            ->addMilliseconds($milliseconds)
            ->addSeconds($seconds)
            ->addMinutes($minutes)
            ->addHours($hours)
            ->addDays($days)
            ->addMonths($months)
            ->addYears($years);

        $this->immutable = $immutable;
    }

    /**
     * Whether this Duration is immutable.
     * @return bool True if this Duration is immutable, false otherwise.
     */
    public function isImmutable(): bool
    {
        return $this->immutable ?? false;
    }

    /**
     * Create a Duration from a millisecond timestamp.
     *
     * @param int $expiration Milliseconds
     * @return Duration The Duration representation of the millisecond timestamp.
     */
    public static function fromMilliseconds(int $expiration): Duration
    {
        $negative = $expiration < 0;
        /*
         * Millisecond timestamp.
         */
        $milliseconds = $expiration % 1000;
        $timestamp = intdiv($expiration, 1000);
        $values = self::decomposeSeconds(
            $timestamp,
            $milliseconds
        );
        if ($negative) {
            $values = self::negateParts($values);
        }
        return new self(
            $values['years'],
            $values['months'],
            $values['days'],
            $values['hours'],
            $values['minutes'],
            $values['seconds'],
            $values['milliseconds'],
            $values['microseconds']
        );
    }

    /**
     * Return a new Duration with the same values but immutable.
     * @return Duration A new Duration with the same values but immutable.
     */
    public function asImmutable(bool $immutable = true): self
    {
        return new self(
            $this->years,
            $this->months,
            $this->days,
            $this->hours,
            $this->minutes,
            $this->seconds,
            $this->milliseconds,
            $this->microseconds,
            $immutable
        );
    }

    /**
     * Create a Duration from the current time.
     * @return Duration A new Duration representing the current time.
     */
    public static function now(): self
    {
        return self::fromDateInterval(new DateInterval('PT0S'), true);
    }

    /**
     * Create a Duration from DateInterval.
     * @param DateInterval $interval The DateInterval to convert.
     */
    public static function fromDateInterval(
        DateInterval $interval,
        bool $immutable = false
    ): self {
        /*
         * DateInterval::$f is a fraction of one second.
         *
         * Example:
         *   0.123456
         *
         * becomes:
         *   123 milliseconds
         *   456 microseconds
         */
        $microseconds = (int)round($interval->f * 1_000_000);

        /*
         * Floating point rounding can theoretically produce exactly
         * 1,000,000.
         */
        if ($microseconds >= 1_000_000) {
            $microseconds = 999_999;
        }

        $milliseconds = intdiv($microseconds, 1000);
        $microseconds %= 1000;

        $duration = new self(
            $interval->y,
            $interval->m,
            $interval->d,
            $interval->h,
            $interval->i,
            $interval->s,
            $milliseconds,
            $microseconds,
            false
        );

        /*
         * DateInterval uses invert instead of negative components.
         *
         * Convert the already-normalized positive duration into its
         * canonical negative representation.
         */
        if ($interval->invert) {
            $duration = $duration->negated();
        }

        if ($immutable) {
            return $duration->asImmutable();
        }

        return $duration;
    }

    /**
     * Create a Duration from a DateTimeInterface.
     * @param DateTimeInterface $date The DateTimeInterface to convert.
     * @param bool $immutable Whether the resulting Duration should be immutable.
     * @return Duration A new Duration representing the difference between the given date and now.
     */
    public static function fromDate(
        DateTimeInterface $date,
        bool $immutable = false
    ): self {
        $now = new DateTimeImmutable();
        $interval = $now->diff($date);

        return self::fromDateInterval($interval, $immutable);
    }

    /**
     * Parse a numeric time representation.
     *
     * Integer:
     *   <= 10 digits  = seconds
     *   11-13 digits  = milliseconds
     *   > 13 digits   = microseconds
     *
     * Float:
     *   seconds + fractional seconds
     * @param int|float $time The numeric time representation to parse.
     * @return array<string, int> An associative array containing the parsed time components.
     */
    private static function parseTime(int|float $time): array
    {
        $negative = $time < 0;
        $absolute = (int) abs($time);
        if (is_float($time)) {
            $string = (string)$absolute;
            $parts = explode('.', $string, 2);
            $seconds = (int)$parts[0];
            $fraction = $parts[1] ?? '';
            $fraction = str_pad(substr($fraction, 0, 6), 6, '0');
            $microseconds = (int)$fraction;
            $milliseconds = intdiv($microseconds, 1000);
            $microseconds %= 1000;

            $values = self::decomposeSeconds(
                $seconds,
                $milliseconds,
                $microseconds
            );

            return $negative
                ? self::negateParts($values)
                : $values;
        }

        $length = strlen((string)$absolute);

        if ($length > 13) {
            /*
             * Microsecond timestamp.
             *
             * Example:
             * 123456789012345
             */
            $microseconds = $absolute % 1000;
            $milliseconds = intdiv($absolute, 1000) % 1000;
            $timestamp = intdiv($absolute, 1_000_000);

            $values = self::decomposeSeconds(
                $timestamp,
                $milliseconds,
                $microseconds
            );
        } elseif ($length > 10) {
            /*
             * Millisecond timestamp.
             */
            $milliseconds = $absolute % 1000;
            $timestamp = intdiv($absolute, 1000);

            $values = self::decomposeSeconds(
                $timestamp,
                $milliseconds
            );
        } else {
            /*
             * Second timestamp.
             */
            $values = self::decomposeSeconds($absolute);
        }

        return $negative
            ? self::negateParts($values)
            : $values;
    }

    /**
     * Decompose positive seconds/ms/us into Duration fields.
     *
     * The resulting fields are canonical and non-negative.
     * @param int $seconds The number of seconds to decompose.
     * @param int $milliseconds The number of milliseconds to decompose.
     * @param int $microseconds The number of microseconds to decompose.
     * @return array{
     *     microseconds: int,
     *     milliseconds: int,
     *     seconds: int,
     *     minutes: int,
     *     hours: int,
     *     days: int,
     *     months: int,
     *     years: int
     * } An associative array containing the decomposed time components.
     */
    private static function decomposeSeconds(
        int $seconds,
        int $milliseconds = 0,
        int $microseconds = 0
    ): array {
        $years = intdiv($seconds, 365 * 24 * 60 * 60);
        $seconds %= 365 * 24 * 60 * 60;

        $months = intdiv($seconds, 30 * 24 * 60 * 60);
        $seconds %= 30 * 24 * 60 * 60;

        $days = intdiv($seconds, 24 * 60 * 60);
        $seconds %= 24 * 60 * 60;

        $hours = intdiv($seconds, 60 * 60);
        $seconds %= 60 * 60;

        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        return [
            'microseconds' => $microseconds,
            'milliseconds' => $milliseconds,
            'seconds' => $seconds,
            'minutes' => $minutes,
            'hours' => $hours,
            'days' => $days,
            'months' => $months,
            'years' => $years,
        ];
    }

    /**
     * Convert canonical positive parts into canonical negative parts.
     *
     * Example:
     *   1 second
     * becomes:
     *   -1 minute + 59 seconds
     * @param array{
     *     microseconds: int,
     *     milliseconds: int,
     *     seconds: int,
     *     minutes: int,
     *     hours: int,
     *     days: int,
     *     months: int,
     *     years: int
     * } $parts The canonical positive parts to negate.
     * @return array{
     *     microseconds: int,
     *     milliseconds: int,
     *     seconds: int,
     *     minutes: int,
     *     hours: int,
     *     days: int,
     *     months: int,
     *     years: int
     * }
     */
    private static function negateParts(array $parts): array
    {
        $duration = new self(
            $parts['years'],
            $parts['months'],
            $parts['days'],
            $parts['hours'],
            $parts['minutes'],
            $parts['seconds'],
            $parts['milliseconds'],
            $parts['microseconds']
        );

        $negative = $duration->negated();

        return [
            'microseconds' => $negative->microseconds,
            'milliseconds' => $negative->milliseconds,
            'seconds' => $negative->seconds,
            'minutes' => $negative->minutes,
            'hours' => $negative->hours,
            'days' => $negative->days,
            'months' => $negative->months,
            'years' => $negative->years,
        ];
    }

    /**
     * Create a Duration from a numeric time representation.
     *
     * Integer:
     *   <= 10 digits  = seconds
     *   11-13 digits  = milliseconds
     *   > 13 digits   = microseconds
     *
     * Float:
     *   seconds + fractional seconds
     * @param int|float $timestamp The numeric time representation to convert.
     * @param bool $immutable Whether the resulting Duration should be immutable.
     * @return Duration A new Duration representing the given numeric time representation.
     */
    public static function fromUnit(
        int|float $timestamp,
        bool $immutable = false
    ): self {
        $values = self::parseTime($timestamp);

        return new self(
            $values['years'],
            $values['months'],
            $values['days'],
            $values['hours'],
            $values['minutes'],
            $values['seconds'],
            $values['milliseconds'],
            $values['microseconds'],
            $immutable
        );
    }

    /**
     * Create a Duration from a string representation.
     *
     * The string is parsed using strtotime().
     *
     * If the string cannot be parsed, a zero duration is returned.
     * @param string $durationString The string representation of the duration to convert.
     * @param bool $immutable Whether the resulting Duration should be immutable.
     * @return Duration A new Duration representing the given string representation.
     */
    public static function fromString(
        string $durationString,
        bool $immutable = false
    ): self {
        $time = strtotime($durationString);

        if ($time === false) {
            return new self(immutable: $immutable);
        }

        return self::fromUnit($time, $immutable);
    }

    /**
     * Create a Duration from a DateInterval, DateTimeInterface, numeric
     * time representation, or string representation.
     *
     * If the input cannot be parsed, a zero duration is returned.
     * @param DateInterval|DateTimeInterface|int|float|string $time The time representation to convert.
     * @param bool $immutable Whether the resulting Duration should be immutable.
     * @return Duration A new Duration representing the given time representation.
     */
    public static function from(
        DateInterval|DateTimeInterface|int|float|string $time,
        bool $immutable = false
    ): self {
        if ($time instanceof DateInterval) {
            return self::fromDateInterval($time, $immutable);
        }

        if ($time instanceof DateTimeInterface) {
            return self::fromDate($time, $immutable);
        }

        if (is_int($time) || is_float($time)) {
            /*
             * Do not cast float to int here. The fractional part is
             * intentionally handled by parseTime().
             */
            return self::fromUnit($time, $immutable);
        }

        return self::fromString($time, $immutable);
    }

    /**
     * Compare this Duration to another Duration, DateInterval,
     * DateTimeInterface, numeric time representation, or string
     * representation.
     *
     * Returns true if this Duration is greater than the other.
     * @param Duration|DateInterval|DateTimeInterface|int|float|string $other
     * The other time representation to compare against.
     * @return bool True if this Duration is greater than the other, false otherwise.
     */
    public function greaterThan(
        Duration|DateInterval|DateTimeInterface|int|float|string $other
    ): bool {
        $other = $other instanceof self
            ? $other
            : self::from($other);

        return $this->toMicrosecondsTime()
            > $other->toMicrosecondsTime();
    }

    /**
     * Compare this Duration to another Duration, DateInterval,
     * DateTimeInterface, numeric time representation, or string
     * representation.
     *
     * Returns true if this Duration is less than the other.
     * @param Duration|DateInterval|DateTimeInterface|int|float|string $other
     * The other time representation to compare against.
     * @return bool True if this Duration is less than the other, false otherwise.
     */
    public function lessThan(
        Duration|DateInterval|DateTimeInterface|int|float|string $other
    ): bool {
        $other = $other instanceof self
            ? $other
            : self::from($other);

        return $this->toMicrosecondsTime()
            < $other->toMicrosecondsTime();
    }

    /**
     * Compare this Duration to another Duration, DateInterval,
     * DateTimeInterface, numeric time representation, or string
     * representation.
     *
     * Returns true if this Duration is equal to the other.
     * @param Duration|DateInterval|DateTimeInterface|int|float|string $other
     * The other time representation to compare against.
     * @return bool True if this Duration is equal to the other, false otherwise.
     */
    public function equal(
        Duration|DateInterval|DateTimeInterface|int|float|string $other
    ): bool {
        $other = $other instanceof self
            ? $other
            : self::from($other);

        return $this->toMicrosecondsTime()
            === $other->toMicrosecondsTime();
    }

    /**
     * Convert this Duration to a microsecond timestamp.
     *
     * The result is a signed integer.
     * @return int The microsecond timestamp representation of this Duration.
     */
    public function toMicrosecondsTime(): int
    {
        return $this->microseconds
            + ($this->milliseconds * 1_000)
            + ($this->seconds * 1_000_000)
            + ($this->minutes * 60 * 1_000_000)
            + ($this->hours * 60 * 60 * 1_000_000)
            + ($this->days * 24 * 60 * 60 * 1_000_000)
            + ($this->months * 30 * 24 * 60 * 60 * 1_000_000)
            + ($this->years * 365 * 24 * 60 * 60 * 1_000_000);
    }

    /**
     * Convert this Duration to a millisecond timestamp.
     *
     * The result is a signed integer.
     * @return int The millisecond timestamp representation of this Duration.
     */
    public function toMillisecondsTime(): int
    {
        return $this->milliseconds
            + ($this->seconds * 1_000)
            + ($this->minutes * 60 * 1_000)
            + ($this->hours * 60 * 60 * 1_000)
            + ($this->days * 24 * 60 * 60 * 1_000)
            + ($this->months * 30 * 24 * 60 * 60 * 1_000)
            + ($this->years * 365 * 24 * 60 * 60 * 1_000);
    }

    /**
     * Convert this Duration to a second timestamp.
     *
     * The result is a signed integer.
     * @return int The second timestamp representation of this Duration.
     */
    public function toTime(): int
    {
        return $this->seconds
            + ($this->minutes * 60)
            + ($this->hours * 60 * 60)
            + ($this->days * 24 * 60 * 60)
            + ($this->months * 30 * 24 * 60 * 60)
            + ($this->years * 365 * 24 * 60 * 60);
    }

    /**
     * Convert to DateInterval while preserving the sign.
     * @return DateInterval The DateInterval representation of this Duration.
     */
    public function toDateInterval(): DateInterval
    {
        $negative = $this->toMicrosecondsTime() < 0;

        if (!$negative) {
            $dateIv = new DateInterval('P0Y0M0DT0H0M0S');

            $dateIv->y = $this->years;
            $dateIv->m = $this->months;
            $dateIv->d = $this->days;
            $dateIv->h = $this->hours;
            $dateIv->i = $this->minutes;
            $dateIv->s = $this->seconds;
            $dateIv->f = (
                    ($this->milliseconds * 1000) +
                    $this->microseconds
                ) / 1_000_000;

            return $dateIv;
        }

        /*
         * DateInterval represents negativity with invert=1.
         *
         * Convert the Duration into its canonical positive counterpart.
         */
        $positive = $this->negated();

        $dateIv = $positive->toDateInterval();
        $dateIv->invert = 1;

        return $dateIv;
    }

    /**
     * Convert to DateTimeImmutable while preserving the sign.
     * @return DatetimeImmutableUnit The DateTimeImmutable representation of this Duration.
     */
    public function toDate(): DatetimeImmutableUnit
    {
        return DatetimeImmutableUnit::now()
            ->add($this->toDateInterval());
    }

    /**
     * Convert to DateTimeImmutable while preserving the sign.
     * @return numeric-string The DateTimeImmutable representation of this Duration.
     */
    public function __toString(): string
    {
        return (string) $this->toMillisecondsTime();
    }

    /**
     * Add microseconds to this Duration.
     *
     * The result is canonicalized so that each unit is within its
     * normal range.
     */
    public function addMicroseconds(int $microseconds): self
    {
        if ($this->isImmutable()) {
            return $this;
        }

        $total = $this->microseconds + $microseconds;

        $milliseconds = intdiv($total, 1000);
        $remainder = $total % 1000;

        if ($remainder < 0) {
            --$milliseconds;
            $remainder += 1000;
        }

        $this->microseconds = $remainder;

        if ($milliseconds !== 0) {
            return $this->addMilliseconds($milliseconds);
        }

        return $this;
    }

    /**
     * Add milliseconds to this Duration.
     *
     * The result is canonicalized so that each unit is within its
     * normal range.
     * @param int $milliseconds The number of milliseconds to add.
     * @return Duration The Duration after adding the milliseconds.
     */
    public function addMilliseconds(int $milliseconds): self
    {
        if ($this->isImmutable()) {
            return $this;
        }

        $total = $this->milliseconds + $milliseconds;

        $carry = intdiv($total, 1000);
        $remainder = $total % 1000;

        if ($remainder < 0) {
            --$carry;
            $remainder += 1000;
        }

        $this->milliseconds = $remainder;

        if ($carry !== 0) {
            return $this->addSeconds($carry);
        }

        return $this;
    }

    /**
     * Add seconds to this Duration.
     *
     * The result is canonicalized so that each unit is within its
     * normal range.
     * @param int $seconds The number of seconds to add. Can be negative.
     * @return Duration The Duration after adding the seconds.
     */
    public function addSeconds(int $seconds): self
    {
        if ($this->isImmutable()) {
            return $this;
        }

        $total = $this->seconds + $seconds;

        $carry = intdiv($total, 60);
        $remainder = $total % 60;

        if ($remainder < 0) {
            --$carry;
            $remainder += 60;
        }

        $this->seconds = $remainder;

        if ($carry !== 0) {
            return $this->addMinutes($carry);
        }

        return $this;
    }

    /**
     * Add minutes to this Duration.
     *
     * The result is canonicalized so that each unit is within its
     * normal range.
     * @param int $minutes The number of minutes to add. Can be negative.
     * @return Duration The Duration after adding the minutes.
     */
    public function addMinutes(int $minutes): self
    {
        if ($this->isImmutable()) {
            return $this;
        }

        $total = $this->minutes + $minutes;

        $carry = intdiv($total, 60);
        $remainder = $total % 60;

        if ($remainder < 0) {
            --$carry;
            $remainder += 60;
        }

        $this->minutes = $remainder;

        if ($carry !== 0) {
            return $this->addHours($carry);
        }

        return $this;
    }

    /**
     * Add hours to this Duration.
     *
     * The result is canonicalized so that each unit is within its
     * normal range.
     * @param int $hours The number of hours to add. Can be negative.
     * @return Duration The Duration after adding the hours.
     */
    public function addHours(int $hours): self
    {
        if ($this->isImmutable()) {
            return $this;
        }

        $total = $this->hours + $hours;

        $carry = intdiv($total, 24);
        $remainder = $total % 24;

        if ($remainder < 0) {
            --$carry;
            $remainder += 24;
        }

        $this->hours = $remainder;

        if ($carry !== 0) {
            return $this->addDays($carry);
        }

        return $this;
    }

    /**
     * Add days to this Duration.
     *
     * The result is canonicalized so that each unit is within its
     * normal range.
     * @param int $days The number of days to add. Can be negative.
     * @return Duration The Duration after adding the days.
     */
    public function addDays(int $days): self
    {
        if ($this->isImmutable()) {
            return $this;
        }

        $total = $this->days + $days;

        $carry = intdiv($total, 30);
        $remainder = $total % 30;

        if ($remainder < 0) {
            --$carry;
            $remainder += 30;
        }

        $this->days = $remainder;

        if ($carry !== 0) {
            return $this->addMonths($carry);
        }

        return $this;
    }

    /**
     * Add months to this Duration.
     *
     * The result is canonicalized so that each unit is within its
     * normal range.
     * @param int $months The number of months to add. Can be negative.
     * @return Duration The Duration after adding the months.
     */
    public function addMonths(int $months): self
    {
        if ($this->isImmutable()) {
            return $this;
        }

        $total = $this->months + $months;

        $carry = intdiv($total, 12);
        $remainder = $total % 12;

        if ($remainder < 0) {
            --$carry;
            $remainder += 12;
        }

        $this->months = $remainder;

        if ($carry !== 0) {
            return $this->addYears($carry);
        }

        return $this;
    }

    /**
     * Add years to this Duration.
     *
     * The result is canonicalized so that each unit is within its
     * normal range.
     * @param int $years The number of years to add. Can be negative.
     * @return Duration The Duration after adding the years.
     */
    public function addYears(int $years): self
    {
        if ($this->isImmutable()) {
            return $this;
        }

        $this->years += $years;

        return $this;
    }

    /**
     * Return a new Duration with the opposite sign.
     * @return Duration A new Duration with the opposite sign.
     */
    public function negated(): self
    {
        /*
         * We intentionally construct this through add*() so the result
         * gets canonicalized correctly.
         */
        return (new self())
            ->addMicroseconds(-$this->microseconds)
            ->addMilliseconds(-$this->milliseconds)
            ->addSeconds(-$this->seconds)
            ->addMinutes(-$this->minutes)
            ->addHours(-$this->hours)
            ->addDays(-$this->days)
            ->addMonths(-$this->months)
            ->addYears(-$this->years);
    }

    /**
     * Magic method to set a property on the Duration object.
     *
     * @param string $name The name of the property to set.
     * @param mixed $value The value to set the property to.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->isImmutable()) {
            return;
        }

        if (!is_int($value)) {
            return;
        }

        /*
         * Preserve the current API semantics:
         *
         *   $duration->seconds = 10;
         *
         * means add 10 seconds.
         *
         * Negative values are intentionally rejected here because
         * callers wanting subtraction should use addSeconds(-10).
         */
        if ($value < 0) {
            return;
        }

        switch (strtolower($name)) {
            case 'microseconds':
                $this->addMicroseconds($value);
                break;

            case 'milliseconds':
                $this->addMilliseconds($value);
                break;

            case 'seconds':
                $this->addSeconds($value);
                break;

            case 'minutes':
                $this->addMinutes($value);
                break;

            case 'hours':
                $this->addHours($value);
                break;

            case 'days':
                $this->addDays($value);
                break;

            case 'months':
                $this->addMonths($value);
                break;

            case 'years':
                $this->addYears($value);
                break;
        }
    }

    /**
     * Magic method to get a property from the Duration object.
     *
     * @param string $name The name of the property to get.
     * @return int|bool|null The value of the property, or null if it does not exist.
     */
    public function __get(string $name): int|bool|null
    {
        return match (strtolower($name)) {
            'microseconds' => $this->microseconds,
            'milliseconds' => $this->milliseconds,
            'seconds' => $this->seconds,
            'minutes' => $this->minutes,
            'hours' => $this->hours,
            'days' => $this->days,
            'months' => $this->months,
            'years' => $this->years,
            'mutable' => !$this->isImmutable(),
            'immutable' => $this->isImmutable(),
            default => null,
        };
    }

    /**
     * Magic method to check if a property is set on the Duration object.
     *
     * @param string $name The name of the property to check.
     * @return bool True if the property is set, false otherwise.
     */
    public function __isset(string $name): bool
    {
        return match (strtolower($name)) {
            'microseconds', 'years', 'milliseconds', 'seconds', 'minutes',
            'hours', 'days', 'months', 'mutable', 'immutable' => true,
            default => false,
        };
    }

    /**
     * Magic method to unset a property on the Duration object.
     *
     * @param string $name The name of the property to unset.
     * @return void
     */
    public function __unset(string $name): void
    {
        if ($this->isImmutable()) {
            return;
        }

        switch (strtolower($name)) {
            case 'years':
                $this->years = 0;
                break;

            case 'months':
                $this->months = 0;
                break;

            case 'days':
                $this->days = 0;
                break;

            case 'hours':
                $this->hours = 0;
                break;

            case 'minutes':
                $this->minutes = 0;
                break;

            case 'seconds':
                $this->seconds = 0;
                break;

            case 'milliseconds':
                $this->milliseconds = 0;
                break;

            case 'microseconds':
                $this->microseconds = 0;
                break;
        }
    }

    /**
     * @return array{
     *     microseconds: int,
     *     milliseconds: int,
     *     seconds: int,
     *     minutes: int,
     *     hours: int,
     *     days: int,
     *     months: int,
     *     years: int,
     *     immutable: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'microseconds' => $this->microseconds,
            'milliseconds' => $this->milliseconds,
            'seconds' => $this->seconds,
            'minutes' => $this->minutes,
            'hours' => $this->hours,
            'days' => $this->days,
            'months' => $this->months,
            'years' => $this->years,
            'immutable' => $this->isImmutable(),
        ];
    }

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    /**
     * @return array{
     *      microseconds: int,
     *      milliseconds: int,
     *      seconds: int,
     *      minutes: int,
     *      hours: int,
     *      days: int,
     *      months: int,
     *      years: int,
     *      immutable: bool
     *  } An associative array containing the serialized data.
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array{
     *      microseconds: int,
     *      milliseconds: int,
     *      seconds: int,
     *      minutes: int,
     *      hours: int,
     *      days: int,
     *      months: int,
     *      years: int,
     *      immutable: bool
     *  } $data
     */
    public function __unserialize(array $data): void
    {
        /*
         * Do NOT use __set() here.
         *
         * __set() means "add", while unserialization needs "assign".
         */
        $this->microseconds = (int)($data['microseconds'] ?? 0);
        $this->milliseconds = (int)($data['milliseconds'] ?? 0);
        $this->seconds = (int)($data['seconds'] ?? 0);
        $this->minutes = (int)($data['minutes'] ?? 0);
        $this->hours = (int)($data['hours'] ?? 0);
        $this->days = (int)($data['days'] ?? 0);
        $this->months = (int)($data['months'] ?? 0);
        $this->years = (int)($data['years'] ?? 0);
        $this->immutable = (bool)($data['immutable'] ?? false);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, int|bool> Data which can be serialized by json_encode(), which is a value
     * of any type other than a resource.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && $this->__isset($offset);
    }

    public function offsetGet(mixed $offset): int|bool|null
    {
        if (is_string($offset)) {
            return $this->__get($offset);
        }

        return null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset)) {
            $this->__set($offset, $value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_string($offset)) {
            $this->__unset($offset);
        }
    }
}
