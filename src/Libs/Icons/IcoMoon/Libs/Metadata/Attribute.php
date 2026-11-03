<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata;

use JsonSerializable;
use Stringable;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\PrettyExport;
use function array_is_list;
use function array_key_exists;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function sprintf;
use function str_repeat;
use function strtolower;
use function var_export;

final class Attribute implements JsonSerializable, Stringable, ExportableInterface
{
    public const string VALUE = 'Value';

    public const string POINTS = 'Points';

    public const string CURRENT_COLOR = 'CurrentColor';

    public const string TRANSFORM = 'Transform';

    public const string VIEWBOX = 'ViewBox';

    public const string STRING_VALUE = 'StringValue';

    public const string PAINT = 'Paint';

    public const string LENGTH = 'Length';

    public const string PX = 'Px';

    public const string REM = 'Rem';

    public const string EM = 'Em';

    public const string PERCENT = 'Percent';

    public const string NUMBER = 'Number';

    public const string INTEGER = 'Integer';

    /**
     * A small epsilon value for floating-point comparisons.
     */
    public const float FLOAT_EPSILON = 1e-12;

    /**
     * @var array<string, string>
     */
    public const array AVAILABLE_TYPES = [
        'value' => self::VALUE,
        'stringvalue' => self::STRING_VALUE,
        'currentcolor' => self::CURRENT_COLOR,
        'points' => self::POINTS,
        'paint' => self::PAINT,
        'length' => self::LENGTH,
        'px' => self::PX,
        'em' => self::EM,
        'rem' => self::REM,
        'percent' => self::PERCENT,
        'number' => self::NUMBER,
        'integer' => self::INTEGER,
        'viewbox' => self::VIEWBOX,
        'transform' => self::TRANSFORM,
    ];

    public readonly string $tag;

    /**
     * @var string[]|int[]|float[]|bool[]|Attribute[]|array<array-key, mixed>[]
     */
    public readonly array $args;

    /**
     * @param string $tag
     * @param string|int|float|bool|Attribute|array<array-key, mixed> ...$args
     */
    public function __construct(
        string $tag,
        string|int|float|bool|array|Attribute ...$args
    ) {
        $this->tag = $tag;
        $this->args = $args;
    }

    /**
     * Normalize the attribute tag to a known type.
     *
     * @param string $tag The attribute tag to normalize.
     * @return string The normalized attribute tag.
     */
    public static function normalizeTag(string $tag): string
    {
        return self::AVAILABLE_TYPES[strtolower($tag)] ?? $tag;
    }

    /**
     * @param array{
     *     tag: string,
     *     args: array<array-key, mixed>
     * }|array<string, mixed> $args
     */
    public static function fromArray(array $args): self
    {
        $tag = $args['tag'] ?? null;

        if (!is_string($tag)) {
            throw new RuntimeException('Invalid attribute tag');
        }

        $tag = self::normalizeTag($tag);
        $arguments = $args['args'] ?? null;

        if (!is_array($arguments)) {
            throw new RuntimeException('Invalid attribute args');
        }

        $parsed = [];

        foreach ($arguments as $key => $value) {
            if (is_scalar($value)) {
                $parsed[] = $value;
                continue;
            }

            if (is_array($value)) {
                if (isset($value['tag'])) {
                    $parsed[] = self::fromArray($value);
                } else {
                    $parsed[] = $value;
                }

                continue;
            }

            throw new RuntimeException(
                'Invalid attribute value on index ' . $key
            );
        }

        return new self($tag, ...$parsed);
    }

    /**
     * @return array{
     *     tag: string,
     *     args: array<array-key, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'tag' => $this->tag,
            'args' => $this->args,
        ];
    }

    /**
     * Normalize a numeric value without losing small coordinates.
     *
     * @param int|float|string $value
     * @return string
     */
    private static function normalizeNumber(
        int|float|string $value
    ): string {
        if (!is_numeric($value)) {
            throw new RuntimeException(
                'Invalid numeric value: ' . $value
            );
        }

        $number = (float)$value;
        if (!is_finite($number)) {
            throw new RuntimeException(
                'Numeric value must be finite'
            );
        }
        // Normalize positive and negative zero only.
        if ($number === 0.0 || $number < self::FLOAT_EPSILON && $number > -self::FLOAT_EPSILON) {
            return '0';
        }

        return (string)$value;
    }

    private function firstArgument(): mixed
    {
        return $this->args[0] ?? null;
    }

    /**
     * Render SVG points.
     *
     * Supported formats:
     * - [x1, y1, x2, y2]
     * - [[x1, y1], [x2, y2]]
     * - ['x' => x, 'y' => y]
     * - [['x' => x1, 'y' => y1], ['x' => x2, 'y' => y2]]
     */
    private function renderPoints(): string
    {
        $points = $this->firstArgument();

        if ($points instanceof Attribute) {
            $points = $points->args;
        }

        if (!is_array($points)) {
            throw new RuntimeException(
                'Invalid points: expected array'
            );
        }

        if ($points === []) {
            return '';
        }

        // A single associative coordinate pair.
        if (array_key_exists('x', $points)
            || array_key_exists('y', $points)
        ) {
            return self::normalizePoint($points, null);
        }

        if (!array_is_list($points)) {
            throw new RuntimeException(
                'Invalid points: expected a list of coordinates'
            );
        }

        $first = $points[0];

        // Flat coordinate list: [x1, y1, x2, y2].
        if (!is_array($first) && !$first instanceof Attribute) {
            $count = count($points);

            if ($count % 2 !== 0) {
                throw new RuntimeException(
                    'Invalid points: expected coordinate pairs'
                );
            }

            $result = [];

            for ($i = 0; $i < $count; $i += 2) {
                $result[] = self::normalizePoint(
                    [$points[$i], $points[$i + 1]],
                    $i
                );
            }

            return implode(' ', $result);
        }

        // Nested coordinate pairs.
        $result = [];

        foreach ($points as $index => $point) {
            if ($point instanceof Attribute) {
                $point = $point->args;
            }

            $result[] = self::normalizePoint($point, $index);
        }

        return implode(' ', $result);
    }

    /**
     * Normalize one point into "x,y".
     *
     * @param mixed $point
     * @param int|string|null $index
     * @return string
     */
    private static function normalizePoint(
        mixed $point,
        int|string|null $index
    ): string {
        if (!is_array($point)) {
            throw new RuntimeException(
                'Invalid point at index ' . ($index ?? 0)
            );
        }

        if (array_key_exists('x', $point)
            || array_key_exists('y', $point)
        ) {
            if (count($point) !== 2
                || !array_key_exists('x', $point)
                || !array_key_exists('y', $point)
            ) {
                throw new RuntimeException(
                    'Invalid point coordinates at index ' . ($index ?? 0)
                );
            }

            $x = $point['x'];
            $y = $point['y'];
        } else {
            if (!array_is_list($point)
                || count($point) !== 2
            ) {
                throw new RuntimeException(
                    'Invalid point coordinates at index ' . ($index ?? 0)
                );
            }

            [$x, $y] = $point;
        }

        if ((!is_int($x) && !is_float($x) && !is_string($x))
            || (!is_int($y) && !is_float($y) && !is_string($y))
        ) {
            throw new RuntimeException(
                'Invalid point coordinates at index ' . ($index ?? 0)
            );
        }

        return self::normalizeNumber($x)
            . ','
            . self::normalizeNumber($y);
    }

    /**
     * Render SVG transform.
     *
     * Supported formats:
     * - [a, b, c, d, e, f]
     * - ['a' => a, 'b' => b, 'c' => c, 'd' => d, 'e' => e, 'f' => f]
     */
    private function renderTransform(): string
    {
        $matrix = $this->firstArgument();

        if ($matrix instanceof Attribute) {
            if ($matrix->tag !== self::TRANSFORM) {
                throw new RuntimeException(
                    'Invalid transform attribute'
                );
            }

            $matrix = $matrix->args;
        } elseif (is_array($matrix)) {
            // Support associative and indexed matrices.
            if (array_key_exists('a', $matrix)
                && array_key_exists('b', $matrix)
                && array_key_exists('c', $matrix)
                && array_key_exists('d', $matrix)
                && array_key_exists('e', $matrix)
                && array_key_exists('f', $matrix)
            ) {
                $matrix = [
                    $matrix['a'],
                    $matrix['b'],
                    $matrix['c'],
                    $matrix['d'],
                    $matrix['e'],
                    $matrix['f'],
                ];
            }
        } else {
            $matrix = $this->args;
        }

        if (count($matrix) !== 6) {
            throw new RuntimeException(
                'Invalid transform matrix: expected six values'
            );
        }

        $values = [];

        foreach ($matrix as $index => $value) {
            if (!is_int($value)
                && !is_float($value)
                && !is_string($value)
            ) {
                throw new RuntimeException(
                    'Invalid transform matrix value at index ' . $index
                );
            }

            $values[] = self::normalizeNumber($value);
        }

        return 'matrix(' . implode(' ', $values) . ')';
    }

    /**
     * Render SVG viewBox.
     *
     * Supported formats:
     * - [minX, minY, width, height]
     * - ['minX' => minX, 'minY' => minY, 'width' => width, 'height' => height]
     *
     * IcoMoon JSON uses the associative format.
     */
    private function renderViewBox(): string
    {
        $values = $this->firstArgument();

        if ($values instanceof Attribute) {
            $values = $values->args;
        }

        // Also support an Attribute wrapper containing one argument.
        if (is_array($values)
            && array_is_list($values)
            && count($values) === 1
            && is_array($values[0])
        ) {
            $values = $values[0];
        }

        if (!is_array($values) || count($values) !== 4) {
            throw new RuntimeException(
                'Invalid viewBox: expected four values'
            );
        }

        if (array_key_exists('minX', $values)
            || array_key_exists('minY', $values)
            || array_key_exists('width', $values)
            || array_key_exists('height', $values)
        ) {
            foreach (['minX', 'minY', 'width', 'height'] as $key) {
                if (!array_key_exists($key, $values)) {
                    throw new RuntimeException(
                        'Invalid viewBox: missing ' . $key
                    );
                }
            }

            $values = [
                $values['minX'],
                $values['minY'],
                $values['width'],
                $values['height'],
            ];
        } elseif (!array_is_list($values)) {
            throw new RuntimeException(
                'Invalid viewBox: expected indexed or named values'
            );
        }

        foreach ($values as $index => $value) {
            if (!is_int($value)
                && !is_float($value)
                && !is_string($value)
            ) {
                throw new RuntimeException(
                    'Invalid viewBox value at index ' . $index
                );
            }
        }

        $width = (float)$values[2];
        $height = (float)$values[3];

        if (!is_numeric($values[2])
            || !is_numeric($values[3])
            || !is_finite($width)
            || !is_finite($height)
            || $width < 0
            || $height < 0
        ) {
            throw new RuntimeException(
                'Invalid viewBox: width and height must be finite non-negative numbers'
            );
        }

        $result = [];

        foreach ($values as $value) {
            $result[] = self::normalizeNumber($value);
        }

        return implode(' ', $result);
    }

    /**
     * Convert the attribute to an HTML string representation.
     *
     * @return string
     */
    public function toHTML(): string
    {
        switch ($this->tag) {
            case self::CURRENT_COLOR:
                return 'currentColor';

            case self::STRING_VALUE:
            case self::INTEGER:
            case self::PAINT:
            case self::LENGTH:
            case self::PX:
            case self::NUMBER:
                return (string)($this->firstArgument() ?? '');

            case self::EM:
                $a = $this->firstArgument();

                if (is_numeric($a)) {
                    return self::normalizeNumber($a) . 'em';
                }

                return '';

            case self::PERCENT:
                $a = $this->firstArgument();

                if (is_numeric($a)) {
                    return self::normalizeNumber($a) . '%';
                }

                return '';

            case self::REM:
                $a = $this->firstArgument();

                if (is_numeric($a)) {
                    return self::normalizeNumber($a) . 'rem';
                }

                return '';

            case self::VIEWBOX:
                return $this->renderViewBox();

            case self::TRANSFORM:
                return $this->renderTransform();

            case self::POINTS:
                return $this->renderPoints();

            case self::VALUE:
                $html = '';

                foreach ($this->args as $arg) {
                    if ($arg instanceof Attribute) {
                        $html .= $arg->toHTML();
                    } elseif (is_scalar($arg)) {
                        $html .= $arg;
                    }
                }

                return $html;

            default:
                $html = [];

                foreach ($this->args as $arg) {
                    if ($arg instanceof Attribute) {
                        $html[] = $arg->toHTML();
                    } elseif (is_scalar($arg)) {
                        $html[] = is_bool($arg)
                            ? ($arg ? 'true' : 'false')
                            : (string)$arg;
                    }
                }

                return implode(' ', $html);
        }
    }

    /**
     * Convert the attribute to a string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toHTML();
    }

    /**
     * @inheritdoc
     */
    public function export(
        int $level = 0,
        int $width = 120,
        int $indent = 4,
        int $maxDepth = 10,
        array $seen = []
    ): string {
        $className = sprintf('\\%s', self::class);
        $tagStr = var_export($this->tag, true);
        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);
        if (empty($this->args)) {
            return "new $className({$tagStr})";
        }
        $argsArray = PrettyExport::export(
            array_values($this->args),
            $width,
            $indent,
            $maxDepth,
            $level + 1,
            $seen
        );
        return "new $className(\n"
            . "{$pad}{$tagStr},\n"
            . "{$pad}...{$argsArray}\n"
            . "{$padEnd})";
    }
}
