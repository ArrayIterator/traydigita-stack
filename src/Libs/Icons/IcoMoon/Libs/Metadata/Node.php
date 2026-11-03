<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata;

use JsonSerializable;
use Stringable;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\PrettyExport;
use function array_values;
use function is_array;
use function is_string;
use function sprintf;
use function str_repeat;
use function trim;
use function var_export;

final class Node implements JsonSerializable, Stringable, ExportableInterface
{
    /**
     * @var string $tag eg: Element
     */
    public readonly string $tag;

    /**
     * @var Tag[]|string[] $args
     */
    public readonly array $args;

    /**
     * @param string $tag
     * @param Tag|string ...$args
     */
    public function __construct(string $tag, Tag|string ...$args)
    {
        $this->tag = $tag;
        $this->args = $args;
    }

    /**
     * @param array{
     *     tag: string,
     *     args: mixed
     * }|array<string, mixed> $data
     * @return self
     * @throws RuntimeException
     */
    public static function fromArray(array $data): self
    {
        $tag = $data['tag'] ?? null;
        if (!is_string($tag)) {
            throw new RuntimeException('Invalid node tag');
        }
        $args = $data['args'] ?? [];
        if (!is_array($args)) {
            throw new RuntimeException('Invalid node args');
        }
        $tags = [];
        foreach ($args as $key => $arg) {
            if (is_string($arg)) {
                $tags[] = $arg;
                continue;
            }
            if (!is_array($arg)) {
                throw new RuntimeException('Invalid node arg on index ' . $key);
            }
            $tags[] = Tag::fromArray($arg);
        }
        return new self($tag, ...$tags);
    }

    /**
     * @return array{
     *     tag: string,
     *     args: Tag[]|string[]
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
     * To HTML
     * @return string
     */
    public function toHTML(): string
    {
        $html = '';
        foreach ($this->args as $tag) {
            $tag = trim((string)$tag);
            if ($tag === '') {
                continue;
            }
            $html .= $tag;
        }
        return $html;
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
        $tagStr = var_export($this->tag, true);
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
        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);
        return "new $className(\n"
            . "{$pad}{$tagStr},\n"
            . "{$pad}...{$argsArray}\n"
            . "{$padEnd})";
    }
}
