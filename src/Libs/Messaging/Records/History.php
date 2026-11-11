<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging\Records;

use DateTimeInterface;
use Throwable;
use TrayDigita\WP\Stack\Source\Components\Database\LazyFetchObject;
use TrayDigita\WP\Stack\Source\Libs\Helpers\DatetimeImmutableUnit;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;
use function absint;
use function is_string;
use function preg_match;
use function trim;

/**
 * @property-read int $id
 * @property-read ?string $uuid
 * @property-read ?string $title
 * @property-read ?string $messenger_id
 * @property-read ?string $message
 * @property-read ?string $target_url
 * @property-read ?boolean $status
 * @property-read ?string $response_data
 * @property-read ?DatetimeImmutableUnit $created_at
 * @template-extends LazyFetchObject<array{
 *     id: int,
 *     uuid: string,
 *     title: string,
 *     messenger_id: string,
 *     message: string,
 *     target_url: string,
 *     status: boolean,
 *     response_data: ?string,
 *     created_at: DatetimeImmutableUnit,
 * }>
 */
final class History extends LazyFetchObject
{
    public function getId(): int
    {
        return $this->id ?? 0;
    }

    public function getUUID(): string
    {
        return $this->uuid ?? '';
    }

    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    public function getMessengerId(): string
    {
        return $this->messenger_id ?? '';
    }

    public function getMessage(): string
    {
        return $this->message ?? '';
    }

    public function getTargetUrl(): string
    {
        return $this->target_url ?? '';
    }

    public function getResponseData(): ?string
    {
        return $this->response_data;
    }

    public function getCreatedAt(): ?DatetimeImmutableUnit
    {
        return $this->created_at;
    }

    protected function convertValue(string $name, mixed $value, string $realName): mixed
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return match ($name) {
            'id' => absint($value),
            'status' => Is::true($value),
            'created_at' => DatetimeImmutableUnit::createFromFormat(
                'Y-m-d H:i:s',
                is_string($value) ? trim($value) : 'now',
                DatetimeImmutableUnit::utcTimeZone()
            ),
            default => is_string($value) ? $value : ($value === null ? null : $value)
        };
    }

    protected function setupNewValue(string $name, mixed $value, mixed $oldValue, ?bool &$canBeSet = null): mixed
    {
        $canBeSet = $value !== $oldValue;
        if ($name === 'id') {
            $canBeSet = false;
            return $value;
        }
        if ($name === 'status') {
            $canBeSet = true;
            return Is::true($value);
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
