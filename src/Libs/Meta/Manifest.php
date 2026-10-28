<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Meta;

use JsonSerializable;
use TrayDigita\WP\Stack\Source\Libs\Helpers\DataFilter;
use function array_values;
use function get_bloginfo;
use function is_string;
use function ltrim;
use function preg_match;
use function strtolower;
use function trim;

/**
 * @property string $name
 * @property string|null $short_name
 * @property string|null $description
 * @property string $start_url
 * @property string|null $id
 * @property string|null $scope
 * @property string $display
 * @property string $orientation
 * @property string|null $background_color
 * @property string|null $theme_color
 * @property string|null $lang
 * @property ManifestIcon[] $icons
 * @property ManifestScreenshot[] $screenshots
 */
final class Manifest implements JsonSerializable
{
    /**
     * Recommended icon sizes for the manifest.
     */
    public const RECOMMENDED_MANIFEST_SIZE = [
        512,
        192,
        180
    ];

    private string $name;

    /**
     * @var string|null The short name of the manifest. If null, it will be unset.
     */
    private ?string $short_name = null;

    /**
     * @var string The start URL of the manifest. Defaults to '/'.
     */
    private string $start_url = '/';

    /**
     * @var string|null The description of the manifest. If null, it will be unset.
     */
    private ?string $description = null;

    /**
     * @var ManifestIcon[]
     */
    private array $icons = [];

    /**
     * @var ManifestScreenshot[]
     */
    private array $screenshots = [];

    /**
     * @var string The display mode of the manifest (e.g., 'standalone', 'fullscreen', etc.).
     */
    private string $display = self::STANDALONE;

    /**
     * @var string|null The scope of the manifest. If null, it will be unset.
     */
    private ?string $scope = null;

    /**
     * @var string|null The ID of the manifest. If null, it will be unset.
     */
    private ?string $id = null;

    /**
     * @var string The orientation of the manifest (e.g., 'any', 'landscape', etc.).
     */
    private string $orientation = self::ORIENTATION_ANY;

    /**
     * @var string|null The background color of the manifest in hex format (e.g., '#FFFFFF') or null if not set.
     */
    private ?string $background_color = null;

    /**
     * @var string|null The theme color of the manifest in hex format (e.g., '#FFFFFF') or null if not set.
     */
    private ?string $theme_color = null;

    /**
     * @var string|null The language of the manifest (e.g., 'en', 'fr', etc.) or null if not set.
     */
    private ?string $lang = null;

    /**
     * The display mode of the manifest, which can be one of the following:
     * - 'standalone': The application will be displayed in a standalone window.
     * - 'fullscreen': The application will be displayed in fullscreen mode.
     * - 'minimal-ui': The application will be displayed with minimal UI elements.
     */
    public const STANDALONE = 'standalone';

    public const FULLSCREEN = 'fullscreen';

    public const MINIMAL_UI = 'minimal-ui';

    public const ORIENTATION_LANDSCAPE = 'landscape';

    public const ORIENTATION_LANDSCAPE_PRIMARY = 'landscape-primary';

    public const ORIENTATION_LANDSCAPE_SECONDARY = 'landscape-secondary';

    public const ORIENTATION_LANDSCAPE_NATURAL = 'landscape-natural';

    public const ORIENTATION_PORTRAIT = 'portrait';

    public const ORIENTATION_PORTRAIT_PRIMARY = 'portrait-primary';

    public const ORIENTATION_PORTRAIT_SECONDARY = 'portrait-secondary';

    public const ORIENTATION_PORTRAIT_NATURAL = 'portrait-natural';

    public const ORIENTATION_ANY = 'any';

    public const ORIENTATION_NATURAL = 'natural';

    public const ALLOWED_DISPLAY = [
        self::STANDALONE => self::STANDALONE,
        self::MINIMAL_UI => self::MINIMAL_UI,
        self::FULLSCREEN => self::FULLSCREEN,
    ];

    public const ALLOWED_ORIENTATION = [
        self::ORIENTATION_ANY => self::ORIENTATION_ANY,
        self::ORIENTATION_LANDSCAPE => self::ORIENTATION_LANDSCAPE,
        self::ORIENTATION_PORTRAIT => self::ORIENTATION_PORTRAIT,
        self::ORIENTATION_NATURAL => self::ORIENTATION_NATURAL,
        self::ORIENTATION_LANDSCAPE_PRIMARY => self::ORIENTATION_LANDSCAPE_PRIMARY,
        self::ORIENTATION_LANDSCAPE_SECONDARY => self::ORIENTATION_LANDSCAPE_SECONDARY,
        self::ORIENTATION_PORTRAIT_PRIMARY => self::ORIENTATION_PORTRAIT_PRIMARY,
        self::ORIENTATION_PORTRAIT_SECONDARY => self::ORIENTATION_PORTRAIT_SECONDARY,
        self::ORIENTATION_PORTRAIT_NATURAL => self::ORIENTATION_PORTRAIT_NATURAL,
        self::ORIENTATION_LANDSCAPE_NATURAL => self::ORIENTATION_LANDSCAPE_NATURAL,
    ];

    /**
     * Manifest constructor.
     *
     * @param string|null $name The name of the manifest. If null, it will default to the blog name.
     * @param string|null $short_name The short name of the manifest. If null, it will be unset.
     * @param string $description The description of the manifest. Defaults to an empty string.
     * @param string|null $id The ID of the manifest. If null, it will be unset.
     * @param string $start_url The start URL of the manifest. Defaults to '/'.
     * @param string|null $scope The scope of the manifest. If null, it will be unset.
     */
    public function __construct(
        ?string $name = null,
        ?string $short_name = null,
        string $description = '',
        ?string $id = null,
        string $start_url = '/',
        ?string $scope = null,
    ) {
        $name ??= get_bloginfo('name');
        $this->setName($name);
        $this->setShortName($short_name);
        $this->setDescription($description);
        $this->setStartUrl($start_url);
        $this->setScope($scope);
        $this->setId($id);
    }

    /**
     * Get the name of the manifest.
     *
     * @return string The name of the manifest.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the name of the manifest.
     *
     * @param string $name The name to set.
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = trim($name);
        return $this;
    }

    /**
     * Get the short name of the manifest.
     *
     * @return string|null The short name of the manifest or null if not set.
     */
    public function getShortName(): ?string
    {
        return $this->short_name;
    }

    /**
     * Get the description of the manifest.
     *
     * @return string|null The description of the manifest or null if not set.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set the description of the manifest.
     *
     * @param string $description The description to set.
     * @return self
     */
    public function setDescription(string $description): self
    {
        $this->description = trim($description) ?: null;
        return $this;
    }

    /**
     * Set the short name of the manifest.
     *
     * @param string|null $short_name The short name to set or null to unset.
     * @return self
     */
    public function setShortName(?string $short_name): self
    {
        $short_name = is_string($short_name) ? trim($short_name) : $short_name;
        $this->short_name = $short_name ?: null;
        return $this;
    }

    /**
     * Get the start URL of the manifest.
     *
     * @return string The start URL.
     */
    public function getStartUrl(): string
    {
        return $this->start_url;
    }

    /**
     * Set the start URL of the manifest.
     *
     * @param string $start_url The start URL to set.
     * @return self
     */
    public function setStartUrl(string $start_url): self
    {
        if (preg_match('~^(?:https?:)?//[^/]+(/.*)?$~i', $start_url, $match)) {
            $start_url = $match[1] ?? '';
        }
        $start_url = '/' . ltrim($start_url, '/');
        $this->start_url = $start_url;
        return $this;
    }

    /**
     * Add multiple icons to the manifest.
     *
     * @param ManifestIcon ...$icons The icons to add.
     * @return self
     */
    public function addIcons(ManifestIcon ...$icons): self
    {
        foreach ($icons as $icon) {
            $this->addIcon($icon);
        }
        return $this;
    }

    /**
     * Add an icon to the manifest.
     *
     * @param ManifestIcon $icon The icon to add.
     * @return self
     */
    public function addIcon(ManifestIcon $icon): self
    {
        $this->icons[] = $icon;
        return $this;
    }

    /**
     * Add multiple screenshots to the manifest.
     *
     * @param ManifestScreenshot ...$screenshots The screenshots to add.
     * @return self
     */
    public function addScreenshots(ManifestScreenshot ...$screenshots): self
    {
        foreach ($screenshots as $screenshot) {
            $this->addScreenshot($screenshot);
        }
        return $this;
    }

    /**
     * Add a screenshot to the manifest.
     *
     * @param ManifestScreenshot $screenshot The screenshot to add.
     * @return self
     */
    public function addScreenshot(ManifestScreenshot $screenshot): self
    {
        $this->screenshots[] = $screenshot;
        return $this;
    }

    /**
     * Get the list of icons in the manifest.
     *
     * @return ManifestIcon[] The list of icons.
     */
    public function getIcons(): array
    {
        return $this->icons;
    }

    /**
     * Get the list of screenshots in the manifest.
     *
     * @return ManifestScreenshot[] The list of screenshots.
     */
    public function getScreenshots(): array
    {
        return $this->screenshots;
    }

    /**
     * Get the display mode of the manifest.
     *
     * @return string The display mode (e.g., 'standalone', 'fullscreen', etc.).
     */
    public function getDisplay(): string
    {
        return $this->display;
    }

    /**
     * Set the display mode of the manifest.
     *
     * @param string $display The display mode to set (e.g., 'standalone', 'fullscreen', etc.).
     * @return self
     */
    public function setDisplay(string $display): self
    {
        $display = strtolower(trim($display));
        $display = self::ALLOWED_DISPLAY[$display] ?? null;
        if ($display) {
            $this->display = $display;
        }
        return $this;
    }

    /**
     * Get the scope of the manifest.
     *
     * @return string|null The scope of the manifest or null if not set.
     */
    public function getScope(): ?string
    {
        return $this->scope;
    }

    /**
     * Clean the scope string by removing the domain and ensuring it starts with a slash.
     *
     * @param string|null $scope The scope string to clean.
     * @return string|null The cleaned scope string or null if not set.
     */
    private function cleanScope(?string $scope): ?string
    {
        if (is_string($scope)) {
            $scope = trim($scope);
            if (preg_match('~^(?:https?:)?//[^/]+(/.*)?$~i', $scope, $match)) {
                $scope = $match[1] ?? '';
                if ($scope !== '') {
                    $scope = '/' . ltrim($scope, '/');
                } else {
                    $scope = null;
                }
            }
        }
        return $scope;
    }

    /**
     * Set the scope of the manifest.
     *
     * @param string|null $scope The scope string to set or null to unset.
     * @return self
     */
    public function setScope(?string $scope): self
    {
        $this->scope = $this->cleanScope($scope);
        return $this;
    }

    /**
     * Get the ID of the manifest.
     *
     * @return string|null The ID of the manifest or null if not set.
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set the ID of the manifest.
     *
     * @param string|null $id The ID string to set or null to unset.
     * @return self
     */
    public function setId(?string $id): self
    {
        $this->id = $this->cleanScope($id);
        return $this;
    }

    /**
     * Get the orientation of the manifest.
     *
     * @return string The orientation of the manifest (e.g., 'any', 'landscape', etc.).
     */
    public function getOrientation(): string
    {
        return $this->orientation;
    }

    /**
     * Set the orientation of the manifest.
     *
     * @param string $orientation The orientation to set (e.g., 'any', 'landscape', etc.).
     * @return self
     */
    public function setOrientation(string $orientation): self
    {
        $orientation = strtolower(trim($orientation));
        $orientation = self::ALLOWED_ORIENTATION[$orientation] ?? $this->orientation;
        $this->orientation = $orientation;
        return $this;
    }

    /**
     * Get the background color of the manifest.
     *
     * @return string|null The background color in hex format (e.g., '#FFFFFF') or null if not set.
     */
    public function getBackgroundColor(): ?string
    {
        return $this->background_color;
    }

    /**
     * Set the background color of the manifest.
     *
     * @param string|null $background_color The background color in hex format (e.g., '#FFFFFF') or null to unset.
     * @return self
     */
    public function setBackgroundColor(?string $background_color): self
    {
        if (is_string($background_color)) {
            $hex = DataFilter::colorHex($background_color);
            if (!$hex) {
                return $this;
            }
            $background_color = $hex;
        }
        $this->background_color = $background_color;
        return $this;
    }

    /**
     * Get the theme color of the manifest.
     *
     * @return string|null The theme color in hex format (e.g., '#FFFFFF') or null if not set.
     */
    public function getThemeColor(): ?string
    {
        return $this->theme_color;
    }

    /**
     * Set the theme color of the manifest.
     *
     * @param string|null $theme_color The theme color in hex format (e.g., '#FFFFFF') or null to unset.
     * @return self
     */
    public function setThemeColor(?string $theme_color): self
    {
        if (is_string($theme_color)) {
            $hex = DataFilter::colorHex($theme_color);
            if (!$hex) {
                return $this;
            }
            $theme_color = $hex;
        }
        $this->theme_color = $theme_color;
        return $this;
    }

    /**
     * Get the language of the manifest.
     *
     * @return string|null The language code (e.g., 'en', 'fr', etc.) or null if not set.
     */
    public function getLang(): ?string
    {
        return $this->lang;
    }

    /**
     * Set the list of icons for the manifest.
     *
     * @param ManifestIcon ...$icons The icons to set.
     * @return self
     */
    public function setIconsList(ManifestIcon ...$icons): self
    {
        $this->icons = array_values($icons);
        return $this;
    }

    /**
     * Set the list of screenshots for the manifest.
     *
     * @param ManifestScreenshot ...$screenshots The screenshots to set.
     * @return self
     */
    public function setScreenshotsList(ManifestScreenshot ...$screenshots): self
    {
        $this->screenshots = array_values($screenshots);
        return $this;
    }

    /**
     * Clear all icons from the manifest.
     *
     * @return self
     */
    public function clearIcons(): self
    {
        $this->icons = [];
        return $this;
    }

    /**
     * Clear all screenshots from the manifest.
     *
     * @return self
     */
    public function clearScreenshots(): self
    {
        $this->screenshots = [];
        return $this;
    }

    /**
     * Set the language of the manifest.
     *
     * @param string|null $lang The language code (e.g., 'en', 'fr', etc.) or null to unset.
     * @return self
     */
    public function setLang(?string $lang): self
    {
        if (!$lang) {
            $this->lang = null;
            return $this;
        }
        $this->lang = trim($lang) ?: null;
        return $this;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link https://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array<string, mixed> data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize(): array
    {
        $data = [
            'name' => $this->getName(),
            "short_name" => $this->getShortName(),
            "description" => $this->getDescription(),
            "start_url" => $this->getStartUrl(),
            "id" => $this->getId(),
            "scope" => $this->getScope(),
            "display" => $this->getDisplay(),
            "orientation" => $this->getOrientation(),
            "background_color" => $this->getBackgroundColor(),
            "theme_color" => $this->getThemeColor(),
            "lang" => $this->getLang(),
            'icons' => [],
            'screenshots' => [],
        ];
        foreach ($data as $key => $item) {
            if ($item === null) {
                unset($data[$key]);
            }
        }
        foreach ($this->getIcons() as $icon) {
            if (!$icon->isValid()) {
                continue;
            }
            $data['icons'][] = $icon;
        }
        foreach ($this->getScreenshots() as $screenshot) {
            if (!$screenshot->isValid()) {
                continue;
            }
            $data['screenshots'][] = $screenshot;
        }
        // Remove empty icons and screenshots arrays if they are empty
        if (empty($data['icons'])) {
            unset($data['icons']);
        }
        // Remove empty screenshots array if it is empty
        if (empty($data['screenshots'])) {
            unset($data['screenshots']);
        }
        return $data;
    }

    /**
     * Magic method to handle property access on the Manifest class.
     * It returns the value of the property based on the property name.
     *
     * @param string $name The name of the property being accessed
     * @return string|array<ManifestIcon|ManifestScreenshot>|null The value of the property or null if not found
     */
    public function __get(string $name) : string|array|null
    {
        return match ($name) {
            'name' => $this->getName(),
            'short_name' => $this->getShortName(),
            'description' => $this->getDescription(),
            'start_url' => $this->getStartUrl(),
            'id' => $this->getId(),
            'scope' => $this->getScope(),
            'display' => $this->getDisplay(),
            'orientation' => $this->getOrientation(),
            'background_color' => $this->getBackgroundColor(),
            'theme_color' => $this->getThemeColor(),
            'lang' => $this->getLang(),
            'icons' => $this->getIcons(),
            'screenshots' => $this->getScreenshots(),
            default => null,
        };
    }

    /**
     * Magic method to handle property setting on the Manifest class.
     * It sets the value of the property based on the property name.
     *
     * @param string $name The name of the property being set
     * @param mixed $value The value to set for the property
     */
    public function __set(string $name, mixed $value): void
    {
        switch ($name) {
            case 'name':
                if (!is_string($value)) {
                    return;
                }
                $this->setName($value);
                break;
            case 'short_name':
                if (!is_string($value) && $value !== null) {
                    return;
                }
                $this->setShortName($value);
                break;
            case 'description':
                if (!is_string($value)) {
                    return;
                }
                $this->setDescription($value);
                break;
            case 'start_url':
                if (!is_string($value)) {
                    return;
                }
                $this->setStartUrl($value);
                break;
            case 'id':
                if (!is_string($value) && $value !== null) {
                    return;
                }
                $this->setId($value);
                break;
            case 'scope':
                if (!is_string($value) && $value !== null) {
                    return;
                }
                $this->setScope($value);
                break;
            case 'display':
                if (!is_string($value)) {
                    return;
                }
                $this->setDisplay($value);
                break;
            case 'orientation':
                if (!is_string($value)) {
                    return;
                }
                $this->setOrientation($value);
                break;
            case 'background_color':
                if (!is_string($value) && $value !== null) {
                    return;
                }
                $this->setBackgroundColor($value);
                break;
            case 'theme_color':
                if (!is_string($value) && $value !== null) {
                    return;
                }
                $this->setThemeColor($value);
                break;
            case 'lang':
                if (!is_string($value) && $value !== null) {
                    return;
                }
                $this->setLang($value);
                break;
            case 'icons':
                if (!is_array($value)) {
                    return;
                }
                $icons = [];
                foreach ($value as $icon) {
                    if ($icon instanceof ManifestIcon) {
                        $icons[] = $icon;
                    }
                }
                $this->setIconsList(...$icons);
                break;
            case 'screenshots':
                if (!is_array($value)) {
                    return;
                }
                $screenshots = [];
                foreach ($value as $screenshot) {
                    if ($screenshot instanceof ManifestScreenshot) {
                        $screenshots[] = $screenshot;
                    }
                }
                $this->setScreenshotsList(...$screenshots);
                break;
            default:
        }
    }
}
