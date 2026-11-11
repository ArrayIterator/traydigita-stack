<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Messaging;

use function in_array;
use function is_array;
use function strtolower;
use function strtoupper;

class MessagingPriority
{
    public const string PRIORITY_HIGH = 'high';

    public const string PRIORITY_NORMAL = 'normal';

    public const string PRIORITY_LOW = 'low';

    public const string PRIORITY_VERY_LOW = 'very_low';

    public const string TYPE_APPLE = 'apns';

    public const string TYPE_ANDROID = 'android';

    public const string TYPE_WEB = 'webpush';

    public const array AVAILABLE_PRIORITIES = [
        self::TYPE_APPLE => [
            self::PRIORITY_HIGH,
            self::PRIORITY_NORMAL,
            self::PRIORITY_LOW,
        ],
        self::TYPE_ANDROID => [
            self::PRIORITY_HIGH,
            self::PRIORITY_NORMAL,
            self::PRIORITY_LOW,
        ],
        self::TYPE_WEB => [
            self::PRIORITY_HIGH,
            self::PRIORITY_NORMAL,
            self::PRIORITY_LOW,
            self::PRIORITY_VERY_LOW,
        ],
    ];

    private string $type;

    private string $priority;

    public function __construct(string $type, string $priority)
    {
        $this->type = self::normalizeType($type)??strtolower(trim($type));
        $this->priority = strtolower(trim($priority));
        if (($this->type === self::TYPE_APPLE || $this->type === self::TYPE_ANDROID)
            && $this->priority === self::PRIORITY_VERY_LOW
        ) {
            $this->priority = self::PRIORITY_LOW;
        } elseif ($this->type === self::TYPE_ANDROID && $this->priority === self::PRIORITY_LOW) {
            $this->priority = self::PRIORITY_NORMAL;
        }
    }

    public static function normalizeType(string $type) : ?string
    {
        return match (strtolower(trim($type))) {
            'apple', self::TYPE_APPLE, 'mac' => self::TYPE_APPLE,
            'web', self::TYPE_WEB, 'internet', 'webpush' => self::TYPE_WEB,
            'android', self::TYPE_ANDROID, 'google', 'mobile' => self::TYPE_ANDROID,
            default => null
        };
    }

    public static function create(string $type, string $priority) : self
    {
        return new self($type, $priority);
    }

    public static function isValidPriority(string $type, string $priority): bool
    {
        $priorities = self::AVAILABLE_PRIORITIES[$type] ?? null;
        if (!is_array($priorities)) {
            return false;
        }
        return in_array($priority, $priorities);
    }

    public static function android(string $priority = self::PRIORITY_NORMAL): self
    {
        return new self(self::TYPE_ANDROID, $priority);
    }

    public static function apple(string $priority = self::PRIORITY_NORMAL): self
    {
        return new self(self::TYPE_APPLE, $priority);
    }

    public static function web(string $priority = self::PRIORITY_NORMAL): self
    {
        return new self(self::TYPE_WEB, $priority);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    /**
     * @return string[]|null
     */
    public function getPriorities(): ?array
    {
        return self::AVAILABLE_PRIORITIES[$this->type] ?? null;
    }

    public function isValid(): bool
    {
        $priorities = $this->getPriorities();
        return $priorities !== null && in_array($this->priority, $priorities);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toParams(): ?array
    {
        $priorities = $this->getPriorities();
        if (!is_array($priorities) || !in_array($this->priority, $priorities, true)) {
            return null;
        }

        return match ($this->getType()) {
            self::TYPE_APPLE => [
                'apns' => [
                    'headers' => [
                        'apns-priority' => match ($this->priority) {
                            self::PRIORITY_HIGH => '10',
                            self::PRIORITY_LOW => '1',
                            default => '5',
                        }
                    ]
                ]
            ],
            self::TYPE_ANDROID => [
                'android' => [
                    'priority' => strtolower($this->priority) === self::PRIORITY_HIGH
                        ? 'HIGH'
                        : 'NORMAL'
                ]
            ],
            self::TYPE_WEB => [
                'webpush' => [
                    'headers' => [
                        'Urgency' => $this->priority
                    ]
                ]
            ],
            default => null,
        };
    }
}
