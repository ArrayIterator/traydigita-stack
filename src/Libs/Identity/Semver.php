<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Identity;

use JsonSerializable;
use Stringable;
use function preg_match;
use function trim;

/**
 * Semantic Versioning (Semver) class
 * Parse and validate semantic versioning strings
 */
class Semver implements JsonSerializable, Stringable
{
    /**
     * Regex pattern for semantic versioning
     * eg:
     * 1.2.3
     * v1.2.3
     * 1.2.3-beta
     * 1.2.3-beta.1
     * 1.2.3-beta.1+build.1
     * 1.2.3+build.1
     * 1.2.3+build.1.2
     *
     * contains array matched keys:
     * {
     *      "prefix"?: "[a-zA-Z]+",
     *      "major": "[0-9]+",
     *      "minor": "[0-9]+",
     *      "patch": "[0-9]+",
     *      "release_separator"?: "[+\-]",   // optional
     *      "release"?: "[a-zA-Z0-9]+([a-zA-Z0-9.+\-]*[a-zA-Z0-9])?",   // optional the release version
     * }
     */
    public const SEMANTIC_VERSION_REGEX = '~^
    (?<prefix>[a-zA-Z]+)? # optional prefix: eg : "v" in "v1.2.3"
    (?P<major>[0-9]+)     # major version
    \.(?P<minor>[0-9]+)   # minor version
    (?:
        \.(?P<patch>[0-9]+)   # patch version
        (?:
            (?P<release_separator>[+\-])
            (?P<release>
                [a-zA-Z0-9]+            # should start with a number or a letter
                (?:
                    [a-zA-Z0-9.+\-]*    # allow any number of letters, numbers, dots, plus and minus
                    [a-zA-Z0-9]         # should end with a number or a letter
                )?
            ) # release version
        )?
    )?
$~x';

    /**
     * The version string
     *
     * @var string
     */
    public readonly string $version;

    /**
     * Is the version string valid
     *
     * @var bool
     */
    public readonly bool $valid;

    /**
     * The major version
     *
     * @var int|null
     */
    public readonly ?int $major;

    /**
     * The minor version
     *
     * @var int|null
     */
    public readonly ?int $minor;

    /**
     * The patch version
     *
     * @var int|null
     */
    public readonly ?int $patch;

    /**
     * The release separator
     *
     * @var string|null
     */
    public readonly ?string $releaseSeparator;

    /**
     * The release version
     *
     * @var string|null
     */
    public readonly ?string $release;

    /**
     * Semver constructor.
     *
     * @param string $version
     */
    public function __construct(string $version)
    {
        $this->version = trim($version);
        preg_match(self::SEMANTIC_VERSION_REGEX, $this->version, $matches);
        $this->valid = !empty($matches);
        $this->major = isset($matches['major']) ? (int) $matches['major'] : null;
        $this->minor = isset($matches['minor']) ? (int) $matches['minor'] : null;
        $this->patch = isset($matches['patch']) ? (int) $matches['patch'] : null;
        $this->releaseSeparator = $matches['release_separator'] ?? null;
        $this->release = $matches['release'] ?? null;
    }

    /**
     * Get the version string
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get the major version
     *
     * @return int|null
     */
    public function getMajor(): ?int
    {
        return $this->major;
    }

    /**
     * Get the minor version
     *
     * @return int|null
     */
    public function getMinor(): ?int
    {
        return $this->minor;
    }

    /**
     * Get the patch version
     *
     * @return int|null
     */
    public function getPatch(): ?int
    {
        return $this->patch;
    }

    /**
     * Get the release separator
     *
     * @return string|null
     */
    public function getReleaseSeparator(): ?string
    {
        return $this->releaseSeparator;
    }

    /**
     * Get the release version
     *
     * @return string|null
     */
    public function getRelease(): ?string
    {
        return $this->release;
    }

    /**
     * Check if the version string is valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Get the version string as a string
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->version;
    }

    /**
     * Get the version information as an array
     *
     * @return array{
     *     version: string,
     *     valid: bool,
     *     major: ?int,
     *     minor: ?int,
     *     patch: ?int,
     *     release_separator: ?string,
     *     release: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'valid' => $this->valid,
            'major' => $this->major,
            'minor' => $this->minor,
            'patch' => $this->patch,
            'release_separator' => $this->releaseSeparator,
            'release' => $this->release,
        ];
    }

    /**
     * Get the version information as an array
     *
     * @return array{
     *     version: string,
     *     valid: bool,
     *     major: ?int,
     *     minor: ?int,
     *     patch: ?int,
     *     release_separator: ?string,
     *     release: ?string
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
