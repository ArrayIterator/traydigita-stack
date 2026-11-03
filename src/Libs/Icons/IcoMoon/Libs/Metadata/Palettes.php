<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata;

use JsonSerializable;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\PrettyExport;
use function is_array;
use function is_int;
use function str_repeat;
use function var_export;

final class Palettes implements JsonSerializable, ExportableInterface
{
    /**
     * @var int $index The palette index
     */
    public readonly int $index;

    /**
     * @var array<array-key, mixed> $table icomoon palette tables
     */
    public readonly array $table;

    /**
     * @param int $index
     * @param array<array-key, mixed> $table
     */
    public function __construct(int $index, array $table)
    {
        $this->index = $index;
        $this->table = $table;
    }

    /**
     * @param array{
     *     index: mixed,
     *     table: mixed,
     * }|array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $index = $data['index'] ?? 0;
        if (!is_int($index)) {
            $index = 0;
        }
        $table = $data['table'] ?? [];
        if (!is_array($table)) {
            $table = [];
        }
        return new self(
            $index,
            $table
        );
    }

    /**
     * @return array{
     *     index: int,
     *     table: array<array-key, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'index' => $this->index,
            'table' => $this->table,
        ];
    }

    public function export(
        int $level = 0,
        int $width = 120,
        int $indent = 4,
        int $maxDepth = 10,
        array $seen = []
    ): string {
        $className = '\\' . self::class;
        $indexStr = var_export($this->index, true);

        if (empty($this->table)) {
            return "new {$className}({$indexStr})";
        }
        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);
        $tableStr = PrettyExport::export(
            $this->table,
            $width,
            $indent,
            $maxDepth,
            $level + 1,
            $seen
        );

        return "new {$className}(\n"
            . "{$pad}{$indexStr},\n"
            . "{$pad}{$tableStr}\n"
            . "{$padEnd})";
    }
}
