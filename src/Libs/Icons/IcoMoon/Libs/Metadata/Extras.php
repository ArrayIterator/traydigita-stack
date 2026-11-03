<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata;

use JsonSerializable;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use function is_int;
use function is_string;
use function sprintf;
use function str_repeat;
use function var_export;

final class Extras implements JsonSerializable, ExportableInterface
{
    /**
     * @var non-empty-lowercase-string $name
     */
    public readonly string $name;

    /**
     * @var int $codePoint the code point
     */
    public readonly int $codePoint;

    /**
     * @param non-empty-lowercase-string $name
     * @param int $codepoint
     */
    public function __construct(string $name, int $codepoint)
    {
        $this->name = $name;
        $this->codePoint = $codepoint;
    }

    /**
     * @param array{
     *      name: non-empty-lowercase-string,
     *      codePoint: int
     * }|array<array-key, mixed> $extras
     * @return self
     */
    public static function fromArray(array $extras): self
    {
        $name = $extras['name'] ?? null;
        $codePoint = $extras['codePoint'] ?? null;
        if (!is_string($name)) {
            throw new RuntimeException(
                'Invalid extras name'
            );
        }
        if (!is_int($codePoint)) {
            throw new RuntimeException(
                'Invalid extras code point'
            );
        }
        if ($name === '') {
            throw new RuntimeException(
                'Invalid extras name'
            );
        }
        /**
         * @var non-empty-lowercase-string $name
         * @var int $codePoint
         */
        return new self($name, $codePoint);
    }

    /**
     * @return array{
     *     name: non-empty-lowercase-string,
     *     codePoint: int
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'codePoint' => $this->codePoint
        ];
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
        $name = var_export($this->name, true);
        $codePoint = var_export($this->codePoint, true);
        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);
        return "new $className(\n"
            . "{$pad}{$name},\n"
            . "{$pad}{$codePoint}\n"
            . "{$padEnd})";
    }
}
