<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Enums;

use Monolog\Level;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use function is_string;

/**
 * LogType Enum
 *
 * Maps application log types to official Monolog severity levels.
 * Refer to the Monolog documentation on the [Monolog GitHub Repository](https://github.com).
 */
enum LogLevel: int
{
    case DEBUG = 100;
    case INFO = 200;
    case NOTICE = 250;
    case WARNING = 300;
    case ERROR = 400;
    case CRITICAL = 500;
    case ALERT = 550;
    case EMERGENCY = 600;

    /**
     * Mapping between levels numbers defined in RFC 5424 and Monolog ones
     *
     * @phpstan-var array<int, self> $rfc_5424_levels
     */
    public const RFC_5424_LEVELS = [
        7 => self::DEBUG,
        6 => self::INFO,
        5 => self::NOTICE,
        4 => self::WARNING,
        3 => self::ERROR,
        2 => self::CRITICAL,
        1 => self::ALERT,
        0 => self::EMERGENCY,
    ];

    /**
     * Mapping between Monolog levels and their string representations
     *
     * @phpstan-var array<string, self> $level_names
     */
    public const LEVEL_NAMES = [
        'DEBUG' => self::DEBUG,
        'INFO' => self::INFO,
        'NOTICE' => self::NOTICE,
        'WARNING' => self::WARNING,
        'ERROR' => self::ERROR,
        'CRITICAL' => self::CRITICAL,
        'ALERT' => self::ALERT,
        'EMERGENCY' => self::EMERGENCY,
    ];

    /**
     * Mapping between Monolog levels and their string representations
     *
     * @phpstan-var array<int, string> $level_numbers
     */
    public const LEVEL_NUMBERS = [
        self::DEBUG->value => 'DEBUG',
        self::INFO->value => 'INFO',
        self::NOTICE->value => 'NOTICE',
        self::WARNING->value => 'WARNING',
        self::ERROR->value => 'ERROR',
        self::CRITICAL->value => 'CRITICAL',
        self::ALERT->value => 'ALERT',
        self::EMERGENCY->value => 'EMERGENCY',
    ];

    /**
     * Mapping between Monolog levels and their PSR-3 string representations
     *
     * @phpstan-var array<int, string> $psr_log_levels
     */
    public const PSR_LOG_LEVELS = [
        self::DEBUG->value => \Psr\Log\LogLevel::DEBUG,
        self::INFO->value => \Psr\Log\LogLevel::INFO,
        self::NOTICE->value => \Psr\Log\LogLevel::NOTICE,
        self::WARNING->value => \Psr\Log\LogLevel::WARNING,
        self::ERROR->value => \Psr\Log\LogLevel::ERROR,
        self::CRITICAL->value => \Psr\Log\LogLevel::CRITICAL,
        self::ALERT->value => \Psr\Log\LogLevel::ALERT,
        self::EMERGENCY->value => \Psr\Log\LogLevel::EMERGENCY,
    ];

    /**
     * Get a human-readable label for the log level.
     */
    public function label(): string
    {
        return match ($this) {
            self::DEBUG => 'Debug',
            self::INFO => 'Info',
            self::NOTICE => 'Notice',
            self::WARNING => 'Warning',
            self::ERROR => 'Error',
            self::CRITICAL => 'Critical',
            self::ALERT => 'Alert',
            self::EMERGENCY => 'Emergency',
        };
    }

    /**
     * @return value-of<self::LEVEL_NUMBERS> The Monolog level name corresponding to the log level.
     */
    public function getName(): string
    {
        return self::LEVEL_NUMBERS[$this->value];
    }

    /**
     * Get the RFC 5424 level number corresponding to the log level.
     *
     * @return key-of<self::RFC_5424_LEVELS> The RFC 5424 level number (0-7).
     */
    public function toRFC5424(): int
    {
        $result = array_search($this, self::RFC_5424_LEVELS, true);
        return $result !== false ? $result : 7; // Default to DEBUG if not found
    }

    /**
     * Get the Monolog level corresponding to the log level.
     *
     * @return Level The Monolog level.
     * @throws RuntimeException If the Monolog Level class is not found.
     */
    public function toMonologLevel(): Level
    {
        if (!class_exists(Level::class)) {
            throw new RuntimeException(
                'Monolog Level class not found. Please ensure Monolog is installed.'
            );
        }
        return Level::from($this->value);
    }

    /**
     * Get the PSR-3 log level string corresponding to the log level.
     *
     * @phpstan-return value-of<self::PSR_LOG_LEVELS> The PSR-3 log level string (e.g., 'debug', 'info', etc.)..
     */
    public function toPsrLevel() : string
    {
        return self::PSR_LOG_LEVELS[$this->value];
    }

    /**
     * Get the LogType enum case from a string name.
     *
     * @param string $name The name of the log level (case-insensitive).
     * @return LogLevel|null The corresponding LogType enum case, or null if not found.
     */
    public static function fromName(string $name): ?self
    {

        return self::LEVEL_NAMES[strtoupper($name)] ?? null;
    }

    /**
     * Get the LogType enum case from a Monolog level number.
     *
     * @param int $level The Monolog level number (100-600).
     * @return LogLevel|null The corresponding LogType enum case, or null if not found.
     */
    public static function fromLevelNumber(int $level): ?self
    {
        $level = self::LEVEL_NUMBERS[$level] ?? null;
        if ($level === null) {
            return null;
        }
        return self::fromName($level);
    }

    /**
     * Get the LogType enum case from an RFC 5424 level number.
     *
     * @param int $level The RFC 5424 level number (0-7).
     * @return LogLevel|null The corresponding LogType enum case, or null if not found.
     */
    public static function fromRFC5424(int $level): ?self
    {
        return self::RFC_5424_LEVELS[$level] ?? null;
    }

    /**
     * Get the LogType enum case from a string name, Monolog level number, or RFC 5424 level number.
     *
     * @param string|int|LogLevel|Level $level The log level
     *  (string name, Monolog level number, or RFC 5424 level number).
     * @return LogLevel|null The corresponding LogType enum case, or null if not found.
     */
    public static function levelOf(string|int|LogLevel|Level $level): ?self
    {
        return match (true) {
            $level instanceof LogLevel => $level,
            $level instanceof Level => self::fromLevelNumber($level->value),
            is_string($level) => self::fromName($level),
            default => self::fromRFC5424($level) ?? self::fromLevelNumber($level),
        };
    }

    /**
     * Get the LogType enum case from a string name, Monolog level number, or RFC 5424 level number.
     *
     * @param string|int|LogLevel|Level $level Log level (string name, Monolog level number, or RFC 5424 level number).
     * @param LogLevel $default The default LogType enum case to return if the input is invalid.
     * @return LogLevel The corresponding LogType enum case, or the default if not found.
     */
    public static function fromLevel(mixed $level, LogLevel $default = LogLevel::WARNING): self
    {
        if (is_string($level) || is_int($level) || $level instanceof LogLevel || $level instanceof Level) {
            return self::levelOf($level) ?? $default;
        }
        return $default;
    }

    /**
     * Returns true if the passed $level is higher or equal to $this
     * @param LogLevel|Level $level The log level to compare against.
     * @return bool True if the passed $level is higher or equal to $this,
     */
    public function includes(LogLevel|Level $level): bool
    {
        return $this->lessOrEqual($level);
    }

    /**
     * Returns true if the passed $level is lower or equal to $this
     * @param LogLevel|Level $level The log level to compare against.
     * @return bool True if the passed $level is lower or equal to $this, false otherwise
     */
    public function greaterThan(LogLevel|Level $level): bool
    {
        return $this->value > $level->value;
    }

    /**
     * Returns true if the passed $level is lower than $this
     * @param LogLevel|Level $level The log level to compare against.
     * @return bool True if the passed $level is lower than $this, false otherwise
     */
    public function lessThan(LogLevel|Level $level): bool
    {
        return $this->value < $level->value;
    }

    /**
     * Returns true if the passed $level is higher or equal to $this
     * @param LogLevel|Level $level The log level to compare against.
     * @return bool True if the passed $level is higher or equal to $this,
     */
    public function greaterOrEqual(LogLevel|Level $level): bool
    {
        return $this->value >= $level->value;
    }

    /**
     * Returns true if the passed $level is lower or equal to $this
     * @param LogLevel|Level $level The log level to compare against.
     * @return bool True if the passed $level is lower or equal to $this,
     */
    public function lessOrEqual(LogLevel|Level $level): bool
    {
        return $this->value <= $level->value;
    }
}
