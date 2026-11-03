<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs;

use Countable;
use JsonSerializable;
use Stringable;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\HTMLAttributes;
use TrayDigita\WP\Stack\Source\Libs\Helpers\PrettyExport;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata\Extras;
use function array_values;
use function is_array;
use function rtrim;
use function sprintf;
use function str_repeat;
use function strtolower;
use function trim;

final class Glyphs implements JsonSerializable, Stringable, ExportableInterface, Countable
{
    /**
     * @var string $prefixId The prefix ID for the glyphs
     */
    public const string DEFAULT_PREFIX_ID = 'icomoon';

    /**
     * @var array<string, Glyph> $glyphs The glyphs data
     */
    public readonly array $glyphs;

    /**
     * @var string $defaultPrefixId The default prefix ID for the glyphs
     */
    protected string $defaultPrefixId = self::DEFAULT_PREFIX_ID;

    /**
     * @param Glyph ...$glyphs
     */
    public function __construct(Glyph ...$glyphs)
    {
        $data = [];
        foreach ($glyphs as $glyph) {
            $data[$glyph->extras->name] = $glyph;
        }
        $this->glyphs = $data;
    }

    /**
     * Check if a glyph exists by name.
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->glyphs[$this->normalizeName($name)]);
    }

    /**
     * Get a glyph by name.
     * @param string $name
     * @return Glyph|null
     */
    public function get(string $name): ?Glyph
    {
        return $this->glyphs[$this->normalizeName($name)] ?? null;
    }

    /**
     * Normalize the glyph name to a consistent format.
     * @param string|Glyph|Extras $glyph
     * @return string
     */
    public function normalizeName(string|Glyph|Extras $glyph): string
    {
        if ($glyph instanceof Glyph) {
            $glyph = $glyph->extras->name;
        }
        if ($glyph instanceof Extras) {
            $glyph = $glyph->name;
        }
        return strtolower(trim($glyph));
    }

    /**
     * Create a new Glyphs instance from an array of glyph definitions.
     * @param array<array-key, array{extras: mixed, node: mixed, palettes: mixed}> $definitions
     * @return self
     * @throws RuntimeException
     */
    public static function fromArray(array $definitions): self
    {
        $data = [];
        foreach ($definitions as $k => $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException('Invalid glyph definition on index ' . $k);
            }
            $data[$k] = Glyph::fromArray($definition);
        }
        return new self(...array_values($data));
    }

    /**
     * @return Glyph[]
     */
    public function jsonSerialize(): array
    {
        return array_values($this->glyphs);
    }

    /**
     * Convert the glyphs to an SVG sprite.
     * @param string $prefixId
     * @param array<string, string> $attributes
     * @return string
     */
    public function toSvgSprite(string $prefixId, array $attributes = []): string
    {
        $html = '';
        $prefixId = rtrim($prefixId, '-');
        if ($prefixId !== '') {
            $prefixId .= '-';
        }
        foreach ($this->glyphs as $glyph) {
            $html .= $glyph->toSymbol(['id' => sprintf('%s%s', $prefixId, $glyph->extras->name)]);
        }
        if (!isset($attributes['style'])) {
            $attributes['style'] = 'display:none';
        }
        $attributes['xmlns'] = 'http://www.w3.org/2000/svg';
        $attributesString = HTMLAttributes::buildAttributes($attributes);
        return "<svg {$attributesString}><defs>{$html}</defs></svg>";
    }

    /**
     * Get a list of all the SVG sprite data.
     * @param string $prefixId
     * @return array<string, array{id: string, name: string}>
     */
    public function svgSpriteDataList(string $prefixId): array
    {
        $prefixId = rtrim($prefixId, '-');
        if ($prefixId !== '') {
            $prefixId .= '-';
        }
        $keys = [];
        foreach ($this->glyphs as $glyph) {
            $id = sprintf('%s%s', $prefixId, $glyph->extras->name);
            $keys[$id] = [
                'id' => $id,
                'name' => $glyph->extras->name,
            ];
        }
        return $keys;
    }

    /**
     * Convert the glyphs to a string representation.
     * @return string
     */
    public function __toString(): string
    {
        return $this->toSvgSprite(
            $this->defaultPrefixId,
            [
                'aria-hidden' => 'true',
                'version' => '1.1',
            ]
        );
    }

    /**
     * Magic method to handle dynamic property access.
     *
     * @param string $name The name of the property being accessed.
     * @return ?Glyph
     */
    public function __get(string $name): ?Glyph
    {
        return $this->get($name);
    }

    /**
     * Magic method to handle dynamic property setting.
     *
     * @param string $name The name of the property being set.
     * @param mixed $value The value to set the property to.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        // void
    }

    /**
     * Magic method to handle dynamic property access.
     *
     * @param string $name The name of the property being accessed.
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Magic method to handle dynamic property unsetting.
     *
     * @param string $name The name of the property being unset.
     * @return void
     */
    public function __unset(string $name): void
    {
        // void
    }

    public function export(
        int $level = 0,
        int $width = 120,
        int $indent = 4,
        int $maxDepth = 10,
        array $seen = []
    ): string {
        $className = sprintf('\\%s', self::class);

        if (empty($this->glyphs)) {
            return "new $className()";
        }
        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);
        $glyphsString = PrettyExport::export(
            array_values($this->glyphs),
            $width,
            $indent,
            $maxDepth,
            $level + 1,
            $seen
        );
        return "new $className(\n"
            . "{$pad}...{$glyphsString}\n"
            . "{$padEnd})";
    }

    /**
     * Get the number of glyphs in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->glyphs);
    }

    /**
     * Find a glyph by its name
     *
     * @param string $search The name or tag to search for
     * @return ?Glyph
     */
    public function findOne(string $search): ?Glyph
    {
        $search = strtolower(trim($search));
        if ($search === '') {
            return null;
        }
        $findOne = null;
        foreach ($this->glyphs as $glyph) {
            $name = $glyph->extras->name;
            if ($name === $search) {
                return $glyph;
            }
            if (!$findOne) {
                if (str_contains($name, $search)) {
                    $findOne = $glyph;
                }
            }
        }
        return $findOne;
    }

    /**
     * Find glyphs by name
     *
     * @param string $search The name to search for
     * @param int $limit The maximum number of results to return
     * @return array<non-empty-lowercase-string, Glyph> An array of matching glyphs
     */
    public function find(string $search, int $limit = 10): array
    {
        $search = strtolower(trim($search));
        if ($search === '') {
            return [];
        }
        $results = [];
        $namedResults = [];
        foreach ($this->glyphs as $glyph) {
            $name = $glyph->extras->name;
            if ($name === $search) {
                $results[$name] = $glyph;
                if (count($results) >= $limit) {
                    return $results;
                }
            }
            if (str_contains($name, $search)) {
                $namedResults[$name] = $glyph;
            }
        }
        if (count($results) < $limit) {
            foreach ($namedResults as $name => $icon) {
                if (isset($results[$name])) {
                    continue;
                }
                $results[$name] = $icon;
                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }
        return $results;
    }
}
