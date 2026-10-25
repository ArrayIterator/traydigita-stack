<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Extensions\Abstracts;

use ReflectionClass;
use Throwable;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\ExtensionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\ExtensionsInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Hook;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Is;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Reflections;
use function array_filter;
use function debug_backtrace;
use function determine_locale;
use function dirname;
use function end;
use function explode;
use function file_exists;
use function get_class;
use function load_textdomain;
use function ltrim;
use function plugin_basename;
use function sprintf;
use function trim;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * Extension abstract class. This class provides a base implementation for extensions.
 */
abstract class AbstractExtension implements ExtensionInterface
{
    /**
     * @var string $name The extension name.
     */
    protected string $name;

    /**
     * @var string $description The extension description.
     */
    protected string $description;

    /**
     * @var string $version The extension version.
     */
    protected string $version;

    /**
     * @var string|null $homepage The extension homepage URL,
     * if any. This property can be null if the extension does not have a homepage.
     */
    protected ?string $homepage;

    /**
     * @var string|null $supportUrl The extension support URL,
     * if any. This property can be null if the extension does not have a support URL.
     */
    protected ?string $supportUrl;

    /**
     * @var string|null $author The extension author name,
     * if any. This property can be null if the extension does not have an author.
     */
    protected ?string $author;

    /**
     * @var array<string>
     */
    protected array $keywords;

    /**
     * @var string|null $textDomain The extension text domain,
     * if any. This property can be null if the extension does not have a text domain.
     */
    protected ?string $textDomain;

    /**
     * @var string $domainPath The extension domain path.
     */
    protected string $domainPath;

    /**
     * @var bool $booted Whether the extension has been loaded or not.
     */
    private bool $booted;

    /**
     * @var bool $prepared Whether the extension has been prepared or not.
     */
    private bool $prepared;

    /**
     * @var bool $initialized Whether the extension has been initialized or not.
     */
    private bool $initialized = false;

    /**
     * @var bool $isCore Whether the extension is a core extension or not.
     */
    private bool $isCore;

    /**
     * @var string $basename The extension basename.
     */
    public readonly string $basename;

    /**
     * @var ReflectionClass<$this> $reflection The reflection class of the extension.
     */
    public readonly ReflectionClass $reflection;

    /**
     * @var string $hookName The hook name for the extension.
     */
    public readonly string $hookName;

    /**
     * @inheritdoc
     */
    final public function __construct(public readonly ExtensionsInterface $extensions)
    {
        $this->reflection = Reflections::objectClass($this);
        $file = $this->reflection->getFileName() ?: '';
        $this->basename = plugin_basename($file);
        $this->hookName = $this->extensions->isRunningAsPlugin()
            ? 'plugins_loaded'
            : 'after_setup_theme';
        if (Hook::runnable($this->hookName)) {
            $this->initialize();
        } else {
            Hook::add($this->hookName, [$this, 'initialize'], 0);
        }
    }

    /**
     * @inheritdoc
     * @return array{
     *     name: string,
     *     class_name: string,
     *     description: string,
     *     version: string,
     *     homepage: string|null,
     *     author: string|null,
     *     keywords: string[],
     *     support_url: string|null,
     *     active: bool,
     *     text_domain: string|null,
     *     is_core: bool,
     *     logo: string|null
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->getName(),
            'class_name' => get_class($this),
            'description' => $this->getDescription(),
            'version' => $this->getVersion(),
            'homepage' => $this->getHomepage(),
            'author' => $this->getAuthor(),
            'keywords' => $this->getKeyWords(),
            'support_url' => $this->getSupportUrl(),
            'active' => $this->booted ?? false,
            'text_domain' => $this->getTextDomain(),
            'is_core' => $this->isCore(),
            'logo' => $this->getLogo(),
        ];
    }

    /**
     * Should the extension be active. This method checks if the extension
     * is a core extension and if it should be active.
     *
     * @return bool
     */
    final public function shouldBeActive(): bool
    {
        return !$this->isCore() || $this->coreShouldBeActive();
    }

    /**
     * Get the priority of the core extension. This method is only called if the extension
     * is a core extension and should be active.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * Check if core extension should be active. This method is only called if the extension
     * is a core extension and should be active.
     *
     * @return bool
     */
    protected function coreShouldBeActive(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    final public function isCore(): bool
    {
        return $this->isCore ??= $this->extensions->isCore($this);
    }

    /**
     * @inheritdoc
     */
    public function getExtensions(): ExtensionsInterface
    {
        return $this->extensions;
    }

    /**
     * Initialize the extension. This method is called after the '$this->hookName' action is fired.
     * @see self::$hookName
     */
    final public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        if (!Hook::runnable($this->hookName)) {
            return;
        }
        $this->initialized = true;
        $domain = $this->getTextDomain();
        $directory = $this->getDomainPath();
        $domain = $domain ? trim($domain) : null;
        $directory = $directory ? trim($directory) : null;
        if ($directory && $domain) {
            try {
                $fileName = $this->reflection->getFileName();
                if (!$fileName || !file_exists($fileName)) {
                    return;
                }
                $directory = Is::absolutePath($directory)
                    ? ltrim($directory, '/') . '/'
                    : dirname($fileName) . '/' . trim($directory, '/\\') . '/';
                $locale = determine_locale();
                $fileName = sprintf('%s-%s.mo', $domain, $locale);
                $filePath = $directory . $fileName;
                if (file_exists($filePath)) {
                    load_textdomain($domain, $filePath);
                }
            } catch (Throwable) {
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getTextDomain(): ?string
    {
        return $this->textDomain ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getDomainPath(): ?string
    {
        return $this->domainPath ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getKeyWords(): array
    {
        if (!isset($this->keywords)) {
            return [];
        }
        return array_filter($this->keywords, 'is_string');
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        if (isset($this->name)) {
            return $this->name;
        }
        $name = get_class($this);
        $name = explode('\\', $name);
        $name = end($name);
        $this->name = $name;
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    /**
     * @inheritdoc
     */
    public function getHomepage(): ?string
    {
        return $this->homepage ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getVersion(): string
    {
        return $this->version ?? self::UNKNOWN_VERSION;
    }

    /**
     * @inheritdoc
     */
    public function getSupportUrl(): ?string
    {
        return $this->supportUrl ?? null;
    }

    /**
     * @inheritdoc
     */
    public function getAuthor(): ?string
    {
        return $this->author ?? null;
    }

    /**
     * @inheritdoc
     */
    final public function boot(ExtensionsInterface $extensions): void
    {
        if (!empty($this->booted)
            // strict comparison to ensure the same instance of ExtensionsInterface is used
            || $extensions !== $this->extensions
        ) {
            return;
        }

        // skip if the extension is not registered in the collection
        if (!$extensions->has($this, true)) {
            return;
        }
        $debugBoot = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
        $className = $debugBoot['class'] ?? null;
        if ($className !== get_class($extensions)) {
            return; // skip if the boot method is not called from the Extensions class
        }
        $this->booted = true;
        if ($extensions->booted($this, true)) {
            return;
        }
        if (empty($this->prepared)) {
            $this->prepare($extensions);
        }
        $this->doLoad();
    }

    /**
     * @inheritdoc
     */
    final public function prepare(ExtensionsInterface $extensions): void
    {
        if (!empty($this->prepared)
            || !empty($this->booted)
            // strict comparison to ensure the same instance of ExtensionsInterface is used
            || $extensions !== $this->extensions
        ) {
            return;
        }
        // skip if the extension is not registered in the collection
        if (!$extensions->has($this, true) || $extensions->booted($this, true)) {
            return;
        }
        $debugBoot = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
        $className = $debugBoot['class'] ?? null;
        if (!$className || $className !== get_class($extensions) && $className !== __CLASS__) {
            return; // skip if the boot method is not called from the Extensions class
        }
        $this->prepared = true;
        $this->doPrepare();
    }

    /**
     * @inheritdoc
     */
    public function getLogo(): ?string
    {
        return null;
    }

    /**
     * Load the extension
     *
     * @abstract
     */
    protected function doLoad() // @phpstan-ignore missingType.return
    {
    }

    /**
     * Prepare the extension
     *
     * @abstract
     */
    protected function doPrepare() // @phpstan-ignore missingType.return
    {
    }
}
