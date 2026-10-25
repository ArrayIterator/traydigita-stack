<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonSerializable;
use Monolog\JsonSerializableDateTimeImmutable;
use RuntimeException;
use function class_exists;

class DatetimeImmutableJsonSerializable extends DateTimeImmutable implements JsonSerializable
{
    /**
     * Create a new instance.
     *
     * @param bool $useMicroseconds
     * @param DateTimeZone|null $timezone
     * @noinspection PhpUnhandledExceptionInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function __construct(protected bool $useMicroseconds = true, ?DateTimeZone $timezone = null)
    {
        parent::__construct('now', $timezone);
    }

    /**
     * Convert current instance to Monolog JsonSerializableDateTimeImmutable.
     *
     * @return JsonSerializableDateTimeImmutable
     * @noinspection PhpUnhandledExceptionInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function toMonologJsonSerializableDateTimeImmutable(): JsonSerializableDateTimeImmutable
    {
        if (!class_exists(JsonSerializableDateTimeImmutable::class)) {
            throw new RuntimeException('Monolog JsonSerializableDateTimeImmutable class not found');
        }
        return (new JsonSerializableDateTimeImmutable($this->useMicroseconds, $this->getTimezone()))
            ->setTime(
                (int)$this->format('H'),
                (int)$this->format('i'),
                (int)$this->format('s')
            )->setDate(
                (int)$this->format('Y'),
                (int)$this->format('m'),
                (int)$this->format('d')
            );
    }

    /**
     * Create an instance from DateTimeInterface.
     *
     * @param DateTimeInterface $object
     * @param bool $useMicroseconds
     * @return self
     * @noinspection PhpUnhandledExceptionInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function createFromInterface(DateTimeInterface $object, bool $useMicroseconds = true): self
    {
        return (new self($useMicroseconds, $object->getTimezone()))
            ->setTime(
                (int)$object->format('H'),
                (int)$object->format('i'),
                (int)$object->format('s')
            )->setDate(
                (int)$object->format('Y'),
                (int)$object->format('m'),
                (int)$object->format('d')
            );
    }

    /**
     * Serialize current instance.
     */
    public function jsonSerialize(): string
    {
        $format = $this->useMicroseconds ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        return $this->format($format);
    }

    /**
     * Cast current instance to string.
     */
    public function __toString(): string
    {
        return $this->jsonSerialize();
    }
}
