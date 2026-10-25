<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Meta;

use JsonSerializable;
use function preg_match;
use function str_contains;
use function strtolower;

/**
 * The screenshot object represents a screenshot of the web application,
 * which can be used to provide a visual preview of the app in various contexts,
 * such as app stores or promotional materials.
 * @link https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/screenshots
 */
class ManifestScreenshot implements JsonSerializable
{
    /**
     * The form factor of the screenshot, which can be either "wide" or "narrow".
     */
    public const FORM_FACTOR_WIDE = 'wide';

    /**
     * The form factor of the screenshot, which can be either "wide" or "narrow".
     */
    public const FORM_FACTOR_NARROW = 'narrow';

    /**
     * List of available form factors for the screenshot.
     */
    public const AVAILABLE_FORM_FACTORS = [
        self::FORM_FACTOR_WIDE => true,
        self::FORM_FACTOR_NARROW => true
    ];

    /**
     * Platform for the screenshot, which can be one of the following values:
     * - android
     * - chromeos
     * - ios
     * - ipados
     * - kaios
     * - macos
     * - windows
     * - xbox
     * - chrome_web_store
     * - itunes
     * - microsoft-inbox
     * - microsoft-store
     * - play
     */
    public const PLATFORM_ANDROID = 'android';

    public const PLATFORM_CHROMEOS = 'chromeos';

    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_IPADOS = 'ipados';

    public const PLATFORM_KAIOS = 'kaios';

    public const PLATFORM_MACOS = 'macos';

    public const PLATFORM_WINDOWS = 'windows';

    public const PLATFORM_XBOX = 'xbox';

    public const PLATFORM_CHROME_WEB_STORE = 'chrome_web_store';

    public const PLATFORM_ITUNES = 'itunes';

    public const PLATFORM_MICROSOFT_INBOX = 'microsoft-inbox';

    public const PLATFORM_MICROSOFT_STORE = 'microsoft-store';

    public const PLATFORM_PLAY = 'play';

    /**
     * List of available platforms for the screenshot.
     * @link https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/screenshots#platform
     */
    public const AVAILABLE_PLATFORMS = [
        self::PLATFORM_ANDROID => true,
        self::PLATFORM_CHROMEOS => true,
        self::PLATFORM_IOS => true,
        self::PLATFORM_IPADOS => true,
        self::PLATFORM_KAIOS => true,
        self::PLATFORM_MACOS => true,
        self::PLATFORM_WINDOWS => true,
        self::PLATFORM_XBOX => true,
        self::PLATFORM_CHROME_WEB_STORE => true,
        self::PLATFORM_ITUNES => true,
        self::PLATFORM_MICROSOFT_INBOX => true,
        self::PLATFORM_MICROSOFT_STORE => true,
        self::PLATFORM_PLAY => true
    ];

    /**
     * The URL of the screenshot image.
     * @link https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/screenshots#src
     */
    protected string $src;

    /**
     * The sizes of the screenshot image, which can be one or more of the following values:
     * - 640x480
     * - 800x600
     * - 1024x768
     * - 1280x720
     * - 1920x1080
     * @link https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/screenshots#sizes
     * @var array{
     *     0: int,
     *     1: int
     * }
     */
    protected array $sizes = [0, 0];

    /**
     * The form factor of the screenshot, which can be either "wide" or "narrow".
     * @link https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/screenshots#form_factor
     * @var null|'wide'|'narrow'
     */
    protected ?string $formFactor = null;

    /**
     * The platform for which the screenshot is intended, which can be one of the available platforms.
     * @link https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/screenshots#platform
     */
    protected ?string $platform = null;

    /**
     * The MIME type of the screenshot image, which can be one of the following values:
     * - image/png
     * - image/jpeg
     * - image/webp
     * @link https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/screenshots#type
     */
    protected ?string $mimeType = null;

    /**
     * The label for the screenshot, which can be used to provide a brief description of the image.
     * @link https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/screenshots#label
     */
    protected ?string $label = null;

    /**
     * ManifestScreenshot constructor.
     *
     * @param string $src The URL of the screenshot image.
     * @param int $width The width of the screenshot image.
     * @param int $height The height of the screenshot image.
     * @param string|null $formFactor The form factor of the screenshot, which can be either "wide" or "narrow".
     * @param string|null $platform The platform for which the screenshot is intended,
     * which can be one of the available platforms.
     */
    public function __construct(
        string $src,
        int $width,
        int $height,
        ?string $label = null,
        ?string $formFactor = null,
        ?string $platform = null
    ) {
        $this->setImage($src, $width, $height);
        if ($formFactor) {
            $this->setFormFactor($formFactor);
        }
        $this->setPlatform($platform);
        $this->setLabel($label);
    }

    /**
     * Filter and validate the form factor value.
     *
     * @param mixed $formFactor The form factor value to filter.
     * @return null|'wide'|'narrow' The filtered form factor value if valid, or null if invalid.
     */
    public static function filterFormFactor(mixed $formFactor): ?string
    {
        if (!is_string($formFactor)) {
            return null;
        }
        $formFactor = strtolower($formFactor);
        if (!isset(self::AVAILABLE_FORM_FACTORS[$formFactor])) {
            return null;
        }
        return $formFactor;
    }

    /**
     * Filter and validate the platform value.
     *
     * @param mixed $platform The platform value to filter.
     * @return null|string The filtered platform value if valid, or null if invalid.
     */
    public static function filterPlatform(mixed $platform): ?string
    {
        if (!is_string($platform)) {
            return null;
        }
        $platform = strtolower($platform);
        if (!isset(self::AVAILABLE_PLATFORMS[$platform])) {
            return null;
        }
        $lower = strtolower($platform);
        if (str_contains($lower, 'android')) {
            return 'android';
        }
        if (str_contains($lower, 'ios')) {
            return 'ios';
        }
        if (str_contains($lower, 'windows')) {
            return 'windows';
        }
        if (str_contains($lower, 'macos')) {
            return 'macos';
        }
        if (str_contains($lower, 'chrome')) {
            return 'chromeos';
        }
        if (str_contains($lower, 'kai')) {
            return 'kaios';
        }
        if (str_contains($lower, 'xbox')) {
            return 'xbox';
        }
        if (str_contains($lower, 'itune')) {
            return 'itunes';
        }
        if (str_contains($lower, 'mac')) {
            return 'macos';
        }
        if (str_contains($lower, 'inbox')) {
            return 'microsoft-inbox';
        }
        if (str_contains($lower, 'microsoft')) {
            return 'microsoft-store';
        }
        if (str_contains($lower, 'play')) {
            return 'play';
        }
        return $platform;
    }

    /**
     * Filter and validate the form factor based on the image size.
     *
     * @param int $width The width of the image.
     * @param int $height The height of the image.
     * @return null|'wide'|'narrow' The form factor based on the image size, or null if invalid.
     */
    public static function filterFormFactorBySize(int $width, int $height): ?string
    {
        if ($width > $height) {
            return 'wide';
        } elseif ($width < $height) {
            return 'narrow';
        }
        return null;
    }

    /**
     * Set the form factor of the screenshot.
     *
     * @param string|null $formFactor The form factor value to set.
     * @return self
     */
    public function setFormFactor(?string $formFactor): self
    {
        $this->formFactor = self::filterFormFactor($formFactor);
        return $this;
    }

    /**
     * Set the platform for which the screenshot is intended.
     *
     * @param string|null $label
     * @return self
     */
    public function setLabel(?string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Set the image URL, size, and optional MIME type.
     *
     * @param string $src The URL of the image
     * @param int $width The width of the image
     * @param int $height The height of the image
     * @param string|null $mime_type Optional MIME type of the image.
     * @param bool $autoFormFactor Whether to automatically determine
     * the form factor based on the image dimensions (default: true)
     * @return self
     */
    public function setImage(
        string $src,
        int $width,
        int $height,
        ?string $mime_type = null,
        bool $autoFormFactor = true
    ): self {
        $this->sizes = [$width, $height];
        if ($autoFormFactor) {
            $this->formFactor = self::filterFormFactorBySize($width, $height);
        } else {
            $this->formFactor = null;
        }
        if (!$mime_type) {
            $mime_type = null;
            if (preg_match('~\.(pe?g|heic|avif|webp|png|gif|svg)(?:[#?].*)?$~', $src, $match)) {
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
            $this->src = $src;
        }
        $this->setMimeType($mime_type);
        return $this;
    }

    public function getSrc(): string
    {
        return $this->src;
    }

    /**
     * Get the sizes of the screenshot image.
     *
     * @return array{0: int, 1: int} An array containing the width and height of the screenshot image.
     * The first element (index 0) represents the width, and the second element (index 1) represents the height.
     * Example: [width, height] => [1280, 720]
     * Valid sizes include:
     * - 640x480
     * - 800x600
     * - 1024x768
     * - 1280x720
     * - 1920x1080
     * @link https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/screenshots#sizes
     */
    public function getSizes(): array
    {
        return $this->sizes;
    }

    /**
     * Get the form factor of the screenshot.
     *
     * @return null|'narrow'|'wide' The form factor of the screenshot, or null if not set.
     */
    public function getFormFactor(): ?string
    {
        return $this->formFactor;
    }

    /**
     * Get the platform for which the screenshot is intended.
     *
     * @return string|null The platform for which the screenshot is intended, or null if not set.
     */
    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    /**
     * Set the MIME type of the screenshot image.
     *
     * @param string|null $mimeType The MIME type to set.
     * @return self
     */
    public function setMimeType(?string $mimeType): self
    {
        if ($mimeType && preg_match('~^image/.*$~', strtolower($mimeType))) {
            $this->mimeType = strtolower($mimeType);
        } else {
            $this->mimeType = null;
        }
        return $this;
    }

    /**
     * Set the platform for which the screenshot is intended.
     *
     * @param string|null $platform The platform value to set.
     * @return self
     */
    public function setPlatform(?string $platform): self
    {
        $this->platform = self::filterPlatform($platform);
        return $this;
    }

    /**
     * Get the MIME type of the screenshot image.
     *
     * @return string|null The MIME type of the screenshot image, or null if not set.
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * Get the label for the screenshot.
     *
     * @return string|null The label for the screenshot, or null if not set.
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * Get the screenshot data as an associative array.
     *
     * @return array<string, mixed> An associative array containing the screenshot data.
     */
    public function toArray(): array
    {
        [$width, $height] = $this->getSizes();
        $formFactor = $this->getFormFactor();
        $platform = $this->getPlatform();
        $mimeType = $this->getMimeType();
        $label = $this->getLabel();
        return [
            'src' => $this->getSrc(),
            'sizes' => sprintf('%1$dx%2$d', $width, $height),
            'form_factor' => $formFactor,
            'platform' => $platform,
            'type' => $mimeType,
            'label' => $label,
        ];
    }

    /**
     * Check if the screenshot is valid.
     *
     * @return bool True if the screenshot is valid, false otherwise.
     */
    public function isValid(): bool
    {
        return $this->getMimeType() && $this->sizes[0] > 0 && $this->sizes[1] > 0;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed> The data to be serialized.
     */
    public function jsonSerialize(): array
    {
        [$width, $height] = $this->getSizes();
        $data = [
            'src' => $this->getSrc(),
            'sizes' => sprintf('%1$dx%2$d', $width, $height),
        ];
        $formFactor = $this->getFormFactor();
        if ($formFactor) {
            $data['form_factor'] = $formFactor;
        }
        $platform = $this->getPlatform();
        if ($platform) {
            $data['platform'] = $platform;
        }
        $mimeType = $this->getMimeType();
        if ($mimeType) {
            $data['type'] = $mimeType;
        }
        $label = $this->getLabel();
        if ($label) {
            $data['label'] = $label;
        }
        return $data;
    }
}
