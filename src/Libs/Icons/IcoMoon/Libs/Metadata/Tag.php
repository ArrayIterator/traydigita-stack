<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata;

use JsonSerializable;
use Stringable;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\HTMLAttributes;
use TrayDigita\WP\Stack\Source\Libs\Helpers\PrettyExport;
use function array_map;
use function array_merge;
use function is_array;
use function is_string;
use function sprintf;
use function str_repeat;
use function strtolower;
use function var_export;

final class Tag implements JsonSerializable, Stringable, ExportableInterface
{
    /**
     * @var string $tagName
     */
    public readonly string $tagName;

    /**
     * @var array<string, Attribute> $attributes
     */
    public readonly array $attributes;

    /**
     * @var Node[] $children
     */
    public readonly array $children;

    /**
     * @param string $tagName
     * @param array<string, Attribute> $attributes
     * @param Node ...$children
     */
    public function __construct(string $tagName, array $attributes = [], Node ...$children)
    {
        $this->tagName = $tagName;
        foreach ($attributes as $name => $attribute) {
            if (!is_string($name)) {
                throw new RuntimeException('Invalid attribute name');
            }
            if (!$attribute instanceof Attribute) {
                throw new RuntimeException('Invalid attribute value');
            }
        }
        $this->attributes = $attributes;
        $this->children = $children;
    }

    /**
     * @param array{
     *     tagName: string,
     *     attributes: array<string, array<array-key, mixed>>,
     *     children: array<array-key, array<array-key, mixed>>
     * }|array<array-key, mixed> $args
     * @return Tag
     * @throws RuntimeException
     */
    public static function fromArray(array $args): Tag
    {
        $tagName = $args['tagName'] ?? null;
        if (!is_string($tagName)) {
            throw new RuntimeException('Invalid tag name');
        }
        $attributes = $args['attributes'] ?? [];
        $children = $args['children'] ?? [];
        if (!is_array($attributes)) {
            throw new RuntimeException('Invalid tag attributes');
        }
        if (!is_array($children)) {
            throw new RuntimeException('Invalid tag children');
        }
        $attr = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name)) {
                throw new RuntimeException('Invalid attribute name');
            }
            if (!is_array($value)) {
                throw new RuntimeException('Invalid attribute value');
            }
            $attr[$name] = Attribute::fromArray($value);
        }
        $child = [];
        foreach ($children as $c) {
            if (!is_array($c)) {
                throw new RuntimeException('Invalid child node');
            }
            $child[] = Node::fromArray($c);
        }
        return new self($tagName, $attr, ...$child);
    }

    /**
     * @return array{
     *     tagName: string,
     *     attributes: array<string, Attribute>,
     *     children: array<array-key, Node>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'tagName' => $this->tagName,
            'attributes' => $this->attributes,
            'children' => $this->children,
        ];
    }

    /**
     * @param string $tagName
     * @param array<string, scalar> $attributes
     * @param bool $cleanupXLMNS
     * @return string
     */
    public function toHTMLWithTagName(string $tagName, array $attributes = [], bool $cleanupXLMNS = false): string
    {
        $attributeList = array_map(function ($attr) {
            return $attr->toHTML();
        }, $this->attributes);
        $attributeList = array_merge($attributeList, $attributes);
        if ($cleanupXLMNS) {
            unset($attributeList['xmlns'], $attributeList['xmlns:xlink']);
        }
        $attributes = HTMLAttributes::buildAttributes($attributeList);
        $html = sprintf('<%s%s', $tagName, $attributes ? ' ' . $attributes : '');
        if (count($this->children) > 0) {
            $html .= '>';
            foreach ($this->children as $child) {
                $html .= $child->toHTML();
            }
            $html .= '</' . $tagName . '>';
        } else {
            $html .= '/>';
        }
        return $html;
    }

    /**
     * @param array<string, scalar> $attributes
     * @param bool $cleanupXLMNS
     * @return string
     */
    public function toHTML(array $attributes = [], bool $cleanupXLMNS = false): string
    {
        return $this->toHTMLWithTagName(strtolower($this->tagName), $attributes, $cleanupXLMNS);
    }

    /**
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
        $className = '\\' . self::class;
        $tagName = var_export($this->tagName, true);
        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);

        $attributes = PrettyExport::export(
            $this->attributes,
            $width,
            $indent,
            $maxDepth,
            $level + 1,
            $seen
        );

        if (empty($this->children)) {
            return "new {$className}(\n"
                . "{$pad}{$tagName},\n"
                . "{$pad}{$attributes}\n"
                . "{$padEnd})";
        }

        $children = PrettyExport::export(
            $this->children,
            $width,
            $indent,
            $maxDepth,
            $level + 1,
            $seen
        );

        return "new {$className}(\n"
            . "{$pad}{$tagName},\n"
            . "{$pad}{$attributes},\n"
            . "{$pad}...{$children}\n"
            . "{$padEnd})";
    }
}
