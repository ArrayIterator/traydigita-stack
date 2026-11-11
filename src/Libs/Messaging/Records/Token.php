<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Records;

use DateTimeInterface;
use Throwable;
use TrayDigita\WP\Stack\Source\Components\Database\LazyFetchObject;
use TrayDigita\WP\Stack\Source\Libs\Helpers\DatetimeImmutableUnit;
use function absint;
use function is_string;

/**
 * @property-read int $id
 * @property-read ?string $token
 * @property-read ?string $topic
 * @property-read ?string $user_agent
 * @property-read ?string $ip_address
 * @property-read ?DatetimeImmutableUnit $created_at
 * @template-extends LazyFetchObject<array{
 *     id: int,
 *     token: string,
 *     topic: string,
 *     user_agent: string,
 *     ip_address: string,
 *     created_at: DatetimeImmutableUnit,
 * }>
 */
final class Token extends LazyFetchObject
{
    public function getId(): int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token ?? '';
    }

    public function getTopic(): string
    {
        return $this->topic ?? '';
    }

    public function getUserAgent(): string
    {
        return $this->user_agent ?? '';
    }

    public function getIpAddress(): string
    {
        return $this->ip_address ?? '';
    }

    public function getCreatedAt(): ?DatetimeImmutableUnit
    {
        return $this->created_at;
    }

    protected function convertValue(string $name, mixed $value, string $realName): mixed
    {
        return match ($name) {
            'id' => absint($value),
            'created_at' => DatetimeImmutableUnit::createFromFormat(
                'Y-m-d H:i:s',
                is_string($value) ? trim($value) : 'now',
                DatetimeImmutableUnit::utcTimeZone()
            ),
            default => is_string($value) ? $value : ''
        };
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param mixed $oldValue
     * @param bool|null $canBeSet
     * @return mixed
     */
    protected function setupNewValue(string $name, mixed $value, mixed $oldValue, ?bool &$canBeSet = null) : mixed
    {
        $canBeSet = $value !== $oldValue;
        if ($name === 'id') {
            $canBeSet = false;
            return $value;
        }
        if ($name !== 'created_at'
            || !$oldValue instanceof DatetimeImmutableUnit
            || $value instanceof DatetimeImmutableUnit
        ) {
            $canBeSet = $canBeSet && is_string($value);
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            $value = DatetimeImmutableUnit::createFromInterface($value)->setTimezone($value->getTimezone());
        } elseif (is_string($value)) {
            try {
                $hasTimezone = preg_match('/([Zz]|[+-]\d{2}:?\d{2})$/', trim($value));
                $value = new DatetimeImmutableUnit($value);
                if (!$hasTimezone) {
                    $value = $value->setTimezone(DatetimeImmutableUnit::utcTimeZone());
                }
            } catch (Throwable $e) {
                $canBeSet = false;
                return $value;
            }
        }
        if (!$value instanceof DateTimeInterface) {
            $canBeSet = false;
            return $value;
        }
        $canBeSet = $value->format('Y-m-d H:i:s') !== $oldValue->format('Y-m-d H:i:s');
        return $value;
    }
}
