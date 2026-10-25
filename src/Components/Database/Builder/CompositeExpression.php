<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Database\Builder;

use Countable;
use Stringable;
use TrayDigita\WP\Stack\Source\Exceptions\InvalidArgumentException;
use function sprintf;

/**
 * Class CompositeExpression
 * Take from Doctrine Composite Expression
 */
class CompositeExpression implements Countable, Stringable
{
    /**
     * Constant for an AND composite expression.
     */
    public const TYPE_AND = 'AND';

    /**
     * Constant for an OR composite expression.
     */
    public const TYPE_OR = 'OR';

    /**
     * @var self::TYPE_AND|self::TYPE_OR $type
     */
    private string $type;

    /**
     * @var array<Stringable|string|int|float> parts
     */
    private array $parts = [];

    /**
     * CompositeExpression constructor.
     *
     * @param self::TYPE_AND|self::TYPE_OR $type
     * @param Stringable|string|int|float ...$parts
     */
    public function __construct(string $type, Stringable|string|int|float ...$parts)
    {
        if ($type !== self::TYPE_AND && $type !== self::TYPE_OR) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid type "%s" for CompositeExpression',
                    $type
                )
            );
        }

        $this->type = $type;
        $this->addMultiple(...$parts);
    }

    /**
     * Create a new AND composite expression
     *
     * @param Stringable|string|int|float ...$parts
     * @return CompositeExpression
     */
    public static function createAnd(Stringable|string|int|float ...$parts): CompositeExpression
    {
        return new self(self::TYPE_AND, ...$parts);
    }

    /**
     * Create a new OR composite expression
     *
     * @param Stringable|string|int|float ...$parts
     * @return CompositeExpression
     */
    public static function createOr(Stringable|string|int|float ...$parts): CompositeExpression
    {
        return new self(self::TYPE_OR, ...$parts);
    }

    /**
     * Count the number of parts in the composite expression
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->parts);
    }

    /**
     * Add part
     *
     * @param Stringable|string|int|float $part
     *
     * @return $this
     */
    public function add(Stringable|string|int|float $part): self
    {
        if (!empty($part)) {
            $this->parts[] = $part;
        }

        return $this;
    }

    /**
     * Add multiple parts
     *
     * @param Stringable|string|int|float ...$parts
     */
    public function addMultiple(Stringable|string|int|float  ...$parts): void
    {
        foreach ($parts as $part) {
            $this->add($part);
        }
    }

    /**
     * Get the string representation of the composite expression
     *
     * @return string
     */
    public function __toString(): string
    {
        if (count($this->parts) === 0) {
            return '';
        }
        if (count($this->parts) === 1) {
            return (string) $this->parts[0];
        }
        return '(' . implode(') ' . $this->type . ' (', $this->parts) . ')';
    }

    /**
     * Get the type of the composite expression
     *
     * @return "AND"|"OR" type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get the parts of the composite expression
     *
     * @return array<Stringable|string|int|float> parts
     */
    public function getParts(): array
    {
        return $this->parts;
    }
}
