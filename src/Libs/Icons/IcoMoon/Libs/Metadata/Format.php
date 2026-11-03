<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata;

use JsonSerializable;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use function is_array;
use function is_bool;
use function is_int;
use function str_repeat;
use function var_export;

final class Format implements JsonSerializable, ExportableInterface
{
    /**
     * @var int $order The order of the format
     */
    public readonly int $order;

    /**
     * @var bool $mOpen Whether the format is open
     */
    public readonly bool $mOpen;

    /**
     * @var Attribute $item The format item
     */
    public readonly Attribute $item;

    /**
     * @param int $order
     * @param bool $mOpen
     * @param Attribute $item
     */
    public function __construct(int $order, bool $mOpen, Attribute $item)
    {
        $this->order = $order;
        $this->mOpen = $mOpen;
        $this->item = $item;
    }

    /**
     * @param array{
     *     order: mixed,
     *     mOpen: mixed,
     *     item: mixed
     * }|array<string, mixed> $data
     * @return self
     * @throws RuntimeException
     */
    public static function fromArray(array $data): self
    {
        $order = $data['order'] ?? 0;
        if (!is_int($order)) {
            $order = 0;
        }
        $mOpen = $data['mOpen'] ?? false;
        if (!is_bool($mOpen)) {
            $mOpen = false;
        }
        $item = $data['item'] ?? [];
        if (!is_array($item)) {
            throw new RuntimeException('Invalid format item data');
        }
        $item = Attribute::fromArray($item);
        return new self(
            $order,
            $mOpen,
            $item
        );
    }

    /**
     * @return array{
     *     order: int,
     *     mOpen: bool,
     *     item: Attribute
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'order' => $this->order,
            'mOpen' => $this->mOpen,
            'item' => $this->item,
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
        $className = '\\' . self::class;
        $order = var_export($this->order, true);
        $mOpen = var_export($this->mOpen, true);

        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);
        $itemExport = $this->item->export(
            $level + 1,
            $width,
            $indent,
            $maxDepth,
            $seen
        );

        return "new {$className}(\n"
            . "{$pad}{$order},\n"
            . "{$pad}{$mOpen},\n"
            . "{$pad}{$itemExport}\n"
            . "{$padEnd})";
    }
}
