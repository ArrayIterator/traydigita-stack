<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs;

use Countable;
use JsonSerializable;
use SplFileInfo;
use Stringable;
use TrayDigita\WP\Stack\Source\Exceptions\InvalidArgumentException;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use TrayDigita\WP\Stack\Source\Interfaces\Actions\ExportableInterface;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata\Formats;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\Metadata\Palettes;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;
use function str_repeat;

/**
 * @template Id of non-empty-lowercase-string
 * @mixin Glyphs
 */
abstract class WebComponent implements JsonSerializable, Stringable, ExportableInterface, Countable
{
    /**
     * Get the ID of the WebComponent.
     *
     * @return Id
     */
    abstract public static function id() : string;

    /**
     * @var Palettes $palettes The palettes data
     */
    public readonly Palettes $palettes;

    /**
     * @var Formats $formats The formats data
     */
    public readonly Formats $formats;

    /**
     * @var Glyphs $glyphs The glyphs data
     */
    public readonly Glyphs $glyphs;

    /**
     * @param Palettes $palettes
     * @param Formats $formats
     * @param Glyphs $glyphs
     */
    protected function __construct(Palettes $palettes, Formats $formats, Glyphs $glyphs)
    {
        $this->palettes = $palettes;
        $this->formats = $formats;
        $this->glyphs = $glyphs;
    }

    /**
     * Create a new instance from a JSON file.
     *
     * @param SplFileInfo|string $info
     * @return array{
     *     palettes: Palettes,
     *     formats: Formats,
     *     glyphs: Glyphs
     * }
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function parseJsonFile(SplFileInfo|string $info): array
    {
        if ($info instanceof SplFileInfo) {
            $info = $info->getRealPath();
        }
        if (!is_string($info) || !file_exists($info)) {
            throw new InvalidArgumentException('Invalid file path');
        }
        $json = file_get_contents($info);
        if ($json === false) {
            throw new RuntimeException('Failed to read file');
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid JSON data');
        }
        if (!is_array($data['glyphs'] ?? null)) {
            throw new RuntimeException('Invalid glyphs data');
        }
        $glyphs = Glyphs::fromArray($data['glyphs']);
        /**
         * @var array{
         *     index: int,
         *     table: mixed,
         * } $palettes
         */
        $palettes = $data['palettes'] ?? null;
        $palettes = is_array($palettes) ? Palettes::fromArray($palettes) : new Palettes(0, []);
        $formats = $data['formats'] ?? null;
        $formats = is_array($formats) ? Formats::fromArray($formats) : new Formats();
        return [
            'palettes' => $palettes,
            'formats' => $formats,
            'glyphs' => $glyphs
        ];
    }

    /**
     * Convert the glyphs to an SVG sprite.
     * @param array<string, string> $attributes
     * @return string
     */
    public function toSvgSprite(array $attributes = []): string
    {
        return $this->glyphs->toSvgSprite(static::id(), $attributes);
    }

    /**
     * Get a list of all the SVG sprite data.
     * @return array<string, array{id: string, name: string}>
     */
    public function svgSpriteDataList(): array
    {
        return $this->glyphs->svgSpriteDataList(static::id());
    }

    /**
     * @return array{
     *     palettes: Palettes,
     *     formats: Formats,
     *     glyphs: Glyphs
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'palettes' => $this->palettes,
            'formats' => $this->formats,
            'glyphs' => $this->glyphs
        ];
    }

    /**
     * Magic method to handle dynamic method calls.
     *
     * @param string $name The name of the method being called.
     * @param array<array-key, mixed> $arguments The arguments passed to the method.
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return $this->glyphs->{$name}(...$arguments);
    }

    /**
     * Magic method to handle dynamic property access.
     *
     * @param string $name The name of the property being accessed.
     * @param mixed $value The value to set the property to.
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        // void
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $this->glyphs->has($name);
    }

    /**
     * @param string $name
     * @return Glyph|null
     */
    public function __get(string $name): ?Glyph
    {
        return $this->glyphs->get($name);
    }

    /**
     * @param string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        // void
    }

    /**
     * Convert the WebComponent to a string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->glyphs;
    }

    /**
     * Get the number of glyphs in the WebComponent.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->glyphs->count();
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
        $palettesString = $this->palettes->export($level + 1, $width, $indent, $maxDepth, $seen);
        $formatsString = $this->formats->export($level + 1, $width, $indent, $maxDepth, $seen);
        $glyphsString = $this->glyphs->export($level + 1, $width, $indent, $maxDepth, $seen);
        $pad = str_repeat(' ', ($level + 1) * $indent);
        $padEnd = str_repeat(' ', $level * $indent);
        return "new $className(\n"
            . "{$pad}{$palettesString},\n"
            . "{$pad}{$formatsString},\n"
            . "{$pad}{$glyphsString}\n"
            . "{$padEnd})";
    }
}
