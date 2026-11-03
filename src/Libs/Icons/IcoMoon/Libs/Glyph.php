<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs;

use JsonSerializable;
use Stringable;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata\Extras;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata\Node;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata\Palettes;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata\Tag;
use function is_array;
use function sprintf;
use function str_repeat;
use function strtolower;

final class Glyph implements JsonSerializable, Stringable, ExportableInterface
{
    /**
     * @var Extras $extras The extras data
     */
    public readonly Extras $extras;

    /**
     * @var Node $node The Tag Node
     */
    public readonly Node $node;

    /**
     * @var Palettes $palettes The palettes data
     */
    public readonly Palettes $palettes;

    /**
     * @param Extras $extras
     * @param Node $node
     * @param Palettes $palettes
     */
    public function __construct(Extras $extras, Node $node, Palettes $palettes)
    {
        $this->extras = $extras;
        $this->node = $node;
        $this->palettes = $palettes;
    }

    /**
     * @param array{
     *     extras: mixed,
     *     node: mixed,
     *     palettes: mixed
     * } $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $extras = $data['extras'] ?? null;
        $node = $data['node'] ?? null;
        if (!is_array($extras)) {
            throw new RuntimeException('Invalid extras data');
        }
        if (!is_array($node)) {
            throw new RuntimeException('Invalid node data');
        }
        $extras = Extras::fromArray($extras);
        $node = Node::fromArray($node);
        $palettes = $data['palettes'] ?? [];
        $palettes = is_array($palettes) ? Palettes::fromArray($palettes) : new Palettes(0, []);
        return new self($extras, $node, $palettes);
    }

    /**
     * @return array{
     *     extras: Extras,
     *     node: Node,
     *     palettes: Palettes
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'extras' => $this->extras,
            'node' => $this->node,
            'palettes' => $this->palettes
        ];
    }

    /**
     * @param array<string, scalar> $attributes the override attributes
     * @param bool $cleanupXLMNS
     * @return string
     * @see self::toSvg()
     */
    public function toHTML(array $attributes = [], bool $cleanupXLMNS = false): string
    {
        return $this->toSvg($attributes, $cleanupXLMNS);
    }

    /**
     * To SVG
     * @param array<string, scalar> $attributes
     * @param bool $cleanupXLMNS
     * @return string
     */
    public function toSvg(array $attributes = [], bool $cleanupXLMNS = false): string
    {
        foreach ($this->node->args as $tag) {
            if ($tag instanceof Tag && strtolower($tag->tagName) === 'svg') {
                return $tag->toHTML($attributes, $cleanupXLMNS);
            }
        }
        throw new RuntimeException('SVG tag not found in glyph node');
    }

    /**
     * To Symbol (used for svg sprite)
     * @param array<string, scalar> $attributes
     * @param bool $cleanupXLMNS
     * @return string
     */
    public function toSymbol(array $attributes = [], bool $cleanupXLMNS = false): string
    {
        foreach ($this->node->args as $tag) {
            if ($tag instanceof Tag && strtolower($tag->tagName) === 'svg') {
                return $tag->toHTMLWithTagName('symbol', $attributes, $cleanupXLMNS);
            }
        }
        throw new RuntimeException('Symbol tag not found in glyph node');
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toHTML();
    }

    public function export(
        int $level = 0,
        int $width = 120,
        int $indent = 4,
        int $maxDepth = 10,
        array $seen = []
    ): string {
        $className = sprintf('\\%s', self::class);
        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);
        $extrasExport = $this->extras->export($level + 1, $width, $indent, $maxDepth, $seen);
        $nodeExport = $this->node->export($level + 1, $width, $indent, $maxDepth, $seen);
        $palettesExport = $this->palettes->export($level + 1, $width, $indent, $maxDepth, $seen);
        return "new $className(\n"
            . "{$pad}{$extrasExport},\n"
            . "{$pad}{$nodeExport},\n"
            . "{$pad}{$palettesExport}\n"
            . "{$padEnd})";
    }
}
