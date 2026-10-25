<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Meta;

use JsonSerializable;
use function array_keys;
use function implode;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;

final class ManifestIcon implements JsonSerializable
{
    /**
     * @var string The purpose of Maskable icon, which is used for maskable icons.
     */
    public const MASKABLE = 'maskable';

    /**
     * @var string The purpose of Any icon, which can be used for any purpose.
     */

    public const ANY = 'any';

    /**
     * @var string The purpose of Monochrome icon, which is used for monochrome icons.
     */
    public const MONOCHROME = 'monochrome';

    /**
     * @var array<string, bool> The available purposes for the icon.
     */
    public const PURPOSE_AVAILABLE = [
        self::MASKABLE => true,
        self::MONOCHROME => true,
        self::ANY => true,
    ];

    /**
     * @var array<string, bool> The purposes of the icon.
     */
    protected array $purposes = [];

    /**
     * @var string
     */
    protected string $src;

    /**
     * @var string|null
     */
    protected ?string $mimeType = null;

    /**
     * @var int
     */
    protected int $size;

    /**
     * ManifestIcon constructor.
     *
     * @param string $image The URL of the image.
     * @param int $size The size of the image (width and height).
     */
    public function __construct(string $image, int $size)
    {
        $this->setImage($image, $size);
    }

    /**
     * Set the image URL, size, and optional MIME type.
     *
     * @param string $url The URL of the image.
     * @param int $size The size of the image (width and height).
     * @param string|null $mime_type Optional MIME type of the image.
     * @return self
     */
    public function setImage(string $url, int $size, ?string $mime_type = null): self
    {
        $this->size = $size;
        if (!$mime_type) {
            $mime_type = null;
            if (preg_match('~\.(pe?g|heic|avif|webp|png|gif|svg)(?:[#?].*)?$~', $url, $match)) {
                $mime_type = match (strtolower($match[1])) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'heic' => 'image/heic',
                    'avif' => 'image/avif',
                    'webp' => 'image/webp',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    default => null,
                };
            }
            $this->src = $url;
        }
        if ($mime_type && preg_match('~^image/.*$~', $mime_type)) {
            $this->mimeType = $mime_type;
        } else {
            $this->mimeType = null;
        }
        return $this;
    }

    /**
     * Create a new ManifestIcon instance.
     *
     * @param string $url
     * @param int $size
     * @return ManifestIcon
     */
    public static function create(string $url, int $size): self
    {
        return new self($url, $size);
    }

    /**
     * Set the purpose of the icon.
     *
     * @param string $purposes The purpose to set.
     * @return self
     */
    public function setPurposes(
        string $purposes
    ): self {
        $purposes = strtolower(trim($purposes));
        if (!isset(self::PURPOSE_AVAILABLE[$purposes])) {
            return $this;
        }
        $this->purposes = [$purposes => true];
        return $this;
    }

    /**
     * Add a purpose to the icon.
     *
     * @param string $purpose The purpose to add.
     * @return self
     */
    public function addPurpose(string $purpose): self
    {
        $purpose = strtolower(trim($purpose));
        if (!isset(self::PURPOSE_AVAILABLE[$purpose])) {
            return $this;
        }
        $this->purposes[$purpose] = true;
        return $this;
    }

    /**
     * Add multiple purposes to the icon.
     *
     * @param string ...$purposes The purposes to add.
     * @return self
     */
    public function addPurposes(string ...$purposes): self
    {
        foreach ($purposes as $purpose) {
            $this->addPurpose($purpose);
        }
        return $this;
    }

    /**
     * Remove a purpose from the icon.
     *
     * @param string $purpose The purpose to remove.
     * @return self
     */
    public function removePurpose(
        string $purpose
    ): self {
        $purpose = strtolower(trim($purpose));
        unset($this->purposes[$purpose]);
        return $this;
    }

    /**
     * Get the purposes of the icon.
     *
     * @return array<array-key, string> The purposes of the icon.
     */
    public function getPurposes(): array
    {
        return array_keys($this->purposes);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<array-key, mixed> The data to be serialized.
     */
    public function jsonSerialize(): array
    {
        $purposes = $this->getPurposes();
        $purpose = null;
        if (!empty($purposes)) {
            $purpose = implode(' ', $purposes);
        }
        $data = [
            'src' => $this->getSrc(),
            'sizes' => sprintf('%1$dx%1$d', $this->getSize()),
        ];
        $mimeType = $this->getMimeType();
        if ($mimeType) {
            $data['type'] = $mimeType;
        }
        if ($purpose) {
            $data['purpose'] = $purpose;
        }
        return $data;
    }

    /**
     * Get the URL of the icon.
     *
     * @return string The URL of the icon.
     */
    public function getSrc(): string
    {
        return $this->src;
    }
    /**
     * Get the size of the icon.
     *
     * @return int The size of the icon (width and height).
     */

    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * Get the MIME type of the icon.
     *
     * @return string|null The MIME type of the icon, or null if not set.
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType??null;
    }

    /**
     * Check if the icon is valid (has a MIME type and a size greater than 0).
     *
     * @return bool True if the icon is valid, false otherwise.
     */
    public function isValid(): bool
    {
        return $this->getSize() > 0;
    }
}
