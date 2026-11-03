<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata;

use JsonSerializable;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use function implode;
use function is_array;
use function str_repeat;

final class Formats implements JsonSerializable, ExportableInterface
{
    /**
     * @var Format[] $formats The formats data
     */
    public readonly array $formats;

    /**
     * @param Format ...$formats
     */
    public function __construct(Format ...$formats)
    {
        $this->formats = $formats;
    }

    /**
     * @param array<array-key, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $formats = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $formats[] = Format::fromArray($item);
        }
        return new self(...$formats);
    }

    /**
     * @return Format[]
     */
    public function jsonSerialize(): array
    {
        return $this->formats;
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
        $className = '\\' . self::class;

        if (empty($this->formats)) {
            return "new {$className}()";
        }

        $pad = str_repeat(' ', ($level + 1) * $indent);
        $childPad = str_repeat(' ', ($level + 2) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);

        $lines = [];
        foreach ($this->formats as $format) {
            $lines[] = $childPad
                . $format->export($level + 2, $width, $indent, $maxDepth, $seen)
                . ',';
        }

        return "new {$className}(\n"
            . "{$pad}...[\n"
            . implode("\n", $lines) . "\n"
            . "{$pad}],\n"
            . "{$padEnd})";
    }
}
