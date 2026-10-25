<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Extensions;

use ReflectionClass;
use Throwable;
use TrayDigita\WP\Stack\Source\Components\Benchmarks\TimeRecords;
use TrayDigita\WP\Stack\Source\Components\Data\SiteOptions;
use TrayDigita\WP\Stack\Source\Components\Extensions\Abstracts\AbstractCoreExtension;
use TrayDigita\WP\Stack\Source\Components\Extensions\Abstracts\AbstractExtension;
use TrayDigita\WP\Stack\Source\Exceptions\IllegalOperationException;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\ExtensionCoreInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\ExtensionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\ExtensionsInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryComponentInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryChainingIdentity;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Hook;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Reflections;
use function array_reverse;
use function count;
use function get_class;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function sprintf;
use function time;
use function uasort;

/**
 * @template T of AbstractExtension
 * @implements ExtensionsInterface<T>
 */
class Extensions implements ExtensionsInterface, RegistryComponentInterface
{
    /**
     * Option name for storing active extensions
     */
    private const OPTION_NAME = 'active_extensions';

    /**
     * List of registered extensions
     * @var array<(class-string<T>&lowercase-string), T> $extensions
     */
    private array $extensions;

    /**
     * List of lazy deactivated extensions
     * @var array<(class-string<T>&lowercase-string), T> $lazyDeactivatedExtensions
     */
    private array $lazyDeactivatedExtensions;

    /**
     * List of active extensions
     *
     * @var array<(class-string<T>&lowercase-string), T>
     */
    private array $activeExtensions;

    /**
     * @var SiteOptions $option The site options instance
     */
    private SiteOptions $option;

    /**
     * List of loaded extensions
     *
     * @var array<(class-string<T>&lowercase-string), class-string<T>>
     */
    private array $loadedExtensions;

    /**
     * @var array<(class-string<T>&lowercase-string), int> $activeExtensionsData
     * Integer as timestamp of when the extension was activated
     */
    private array $activeExtensionsData;

    /**
     * @var bool $loaded Whether the extension collection has been loaded or not.
     */
    private bool $booted;

    /**
     * @var TimeRecords[] $bootRecords The time records for the boot process.
     */
    private array $bootRecords;

    /**
     * @var bool $booting Whether the extension collection is currently booting or not.
     */
    private bool $booting;

    /**
     * @var array<(class-string<T>&lowercase-string), Throwable> $errors
     *      The errors that occurred during the boot process.
     */
    private array $errors;

    /**
     * @var string $optionName The name of the option used to store active extensions.
     */
    public readonly string $optionName;

    /**
     * @var bool $runningAsPlugin Whether the extension collection is running as a plugin or not.
     */
    public readonly bool $runningAsPlugin;

    /**
     * Extensions constructor.
     * @inheritdoc
     */
    public function __construct(
        public readonly RegistryInterface $registry,
        public readonly string $name = self::DEFAULT_NAME,
        ?bool $runningAsPlugin = null
    ) {
        $this->optionName = sprintf(
            '%s_%s',
            self::OPTION_NAME,
            $this->name
        );
        $this->runningAsPlugin = $runningAsPlugin??!Hook::did('setup_theme');
    }

    /**
     * @inheritdoc
     */
    public static function registerSelf(string $id, RegistryInterface $registry) : ?RegistryChainingIdentity
    {
        return RegistryChainingIdentity::create(
            $id,
            [
                self::class,
                ExtensionsInterface::class,
            ],
            $registry->locked($id),
            $registry->unlockable($id)
        );
    }

    /**
     * @inheritdoc
     */
    public function isRunningAsPlugin(): bool
    {
        return $this->runningAsPlugin;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getRegistry(): RegistryInterface
    {
        return $this->registry;
    }

    /**
     * @inheritdoc
     */
    public function isCore(ExtensionInterface|string $extension): bool
    {
        $ref = $this->getReflection($extension);
        if (!$ref) {
            return false;
        }
        return $ref->isSubclassOf(AbstractCoreExtension::class);
    }

    /**
     * Compare two values for strict equality
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool
     */
    private function sameValue(mixed $a, mixed $b): bool
    {
        return $a === $b;
    }

    /**
     * Get the reflection class for an extension
     *
     * @param class-string<T>|T $class
     * @return ?ReflectionClass<T>
     */
    public function getReflection(string|ExtensionInterface $class): ?ReflectionClass
    {
        $reflection = Reflections::class($class);
        if (!$reflection?->isSubclassOf(AbstractExtension::class)) {
            return null;
        }
        return $reflection;
    }

    /**
     * Check if an extension satisfies the requirements
     *
     * @param T|class-string<T> $extension
     * @return class-string<T>|null
     */
    private function satisfyClassName(ExtensionInterface|string $extension): ?string
    {
        $reflection = $this->getReflection($extension);
        // does not satisfy the requirements
        if (!$reflection || $reflection->isAnonymous()) {
            return null;
        }
        return $reflection->getName() && $reflection->isSubclassOf(AbstractExtension::class)
            ? $reflection->getName()
            : null;
    }

    /**
     * @inheritdoc
     */
    public function booted(string|ExtensionInterface $extension, bool $strict = false): bool
    {
        $key = Reflections::lowercase($extension);
        if (!isset($this->extensions[$key])) {
            return false;
        }
        if (!isset($this->loadedExtensions[$key])) {
            return false;
        }
        if ($strict && is_object($extension) && !is_string($this->loadedExtensions[$key])) {
            return $this->sameValue($this->loadedExtensions[$key], $extension);
        }
        return true;
    }

    /**
     * @param T|class-string<T> $extension
     * @param bool $override
     * @return bool
     * @inheritdoc
     */
    public function register(ExtensionInterface|string $extension, bool $override = true): bool
    {
        if ($this->isBooted()) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot register extension %s after the collection has been booted.',
                    is_string($extension) ? $extension : get_class($extension)
                )
            );
        }
        $className = $this->satisfyClassName($extension);
        if (!$className) {
            return false;
        }
        /**
         * @var class-string<T>&lowercase-string $key
         */
        $key = Reflections::lowercase($extension);
        if (isset($this->loadedExtensions[$key])) {
            return false;
        }
        $exists = isset($this->extensions[$key]);
        if ($override || !$exists) {
            $this->extensions[$key] = new $className($this);
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     * @return T|null
     * @throws IllegalOperationException if the extension is loaded and strict is true
     */
    public function remove(string|ExtensionInterface $extension, bool $strict = false): ?ExtensionInterface
    {
        if ($this->isBooted()) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot remove extension %s after the collection has been booted.',
                    is_string($extension) ? $extension : get_class($extension)
                )
            );
        }
        $key = Reflections::lowercase($extension);
        if (!isset($this->extensions[$key])) {
            return null;
        }
        if (isset($this->loadedExtensions[$key])) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot remove extension %s after the collection has been booted.',
                    is_string($extension) ? $extension : get_class($extension)
                )
            );
        }
        $removedExtension = $this->extensions[$key];
        if (is_object($removedExtension)) {
            if ($removedExtension->isCore() && $removedExtension instanceof ExtensionCoreInterface) {
                if ($removedExtension->shouldBeActive()) {
                    throw new IllegalOperationException(
                        sprintf(
                            'Cannot remove core extension %s.',
                            is_string($extension) ? $extension : get_class($extension)
                        )
                    );
                }
            }
        }
        if ($strict && is_object($extension) && !is_string($removedExtension)) {
            if (!$this->sameValue($removedExtension, $extension)) {
                return null;
            }
        }
        unset($this->extensions[$key], $this->lazyDeactivatedExtensions[$key]);
        return $removedExtension;
    }

    /**
     * @inheritdoc
     */
    public function has(string|ExtensionInterface $extension, bool $strict = false): bool
    {
        $key = Reflections::lowercase($extension);
        if (!isset($this->extensions[$key])) {
            return false;
        }
        if ($strict && is_object($extension) && !is_string($this->extensions[$key])) {
            return $this->sameValue($this->extensions[$key], $extension);
        }
        return true;
    }

    /**
     * @param string|T $name
     * @param bool $strict
     * @inheritdoc
     */
    public function get(string|ExtensionInterface $name, bool $strict = false): ?ExtensionInterface
    {
        $key = Reflections::lowercase($name);
        if (!isset($this->extensions[$key])) {
            return null;
        }
        if ($strict && is_object($name) && !is_string($this->extensions[$key])) {
            if (!$this->sameValue($this->extensions[$key], $name)) {
                return null;
            }
        }
        return $this->extensions[$key];
    }

    /**
     * @inheritdoc
     */
    public function getExtensions(): array
    {
        return $this->extensions ?? [];
    }

    /**
     * @return SiteOptions|null
     * @private
     */
    public function getSiteOption(): ?SiteOptions
    {
        if (!isset($this->option)) {
            try {
                $option = $this->registry->get(SiteOptions::class);
                if ($option instanceof SiteOptions) {
                    $this->option = $option;
                    return $this->option;
                }
                return null;
            } catch (Throwable) {
                return null;
            }
        } else {
            return $this->option;
        }
    }

    /**
     * Set an option value
     *
     * @param string $name
     * @param mixed $value
     * @private
     */
    public function setOption(string $name, mixed $value): void
    {
        $this->getSiteOption()?->set($name, $value);
    }

    /**
     * Get an option value
     *
     * @param string $name
     * @return mixed
     * @private
     */
    public function getOption(string $name): mixed
    {
        return $this->getSiteOption()?->get($name);
    }

    /**
     * @inheritdoc
     */
    public function activate(string|ExtensionInterface $name, bool $strict = false): ?int
    {
        if ($this->isBooted()) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot activate extension %s after the collection has been booted.',
                    is_string($name) ? $name : get_class($name)
                )
            );
        }
        $extension = $this->get($name, $strict);
        if (!$extension) {
            return null;
        }
        $activeExtensionsData = $this->getActiveExtensionData();
        $activeExtensions = $this->getActiveExtensions();
        $key = Reflections::lowercase($name);
        if (!$key) {
            return null;
        }
        if (is_int($activeExtensionsData[$key]??null)) {
            if (!isset($activeExtensions[$key])) {
                $this->activeExtensions[$key] = $extension;
            }
            return $activeExtensionsData[$key];
        }
        $activeExtensionsData[$key] = time();
        $this->setOption($this->optionName, $activeExtensionsData);
        return $activeExtensionsData[$key];
    }

    /**
     * @inheritdoc
     */
    public function deactivate(string|ExtensionInterface $name, bool $strict = false): ?int
    {
        if ($this->isBooted()) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot deactivate extension %s after the collection has been booted.',
                    is_string($name) ? $name : get_class($name)
                )
            );
        }
        $extension = $this->get($name, $strict);
        if (!$extension) {
            return null;
        }
        if ($extension instanceof ExtensionCoreInterface && $extension->isCore()) {
            // core can not be deactivated, but if it should be active, we can ignore it
            if ($extension->shouldBeActive()) {
                return null;
            }
        }
        $activeExtensions = $this->getActiveExtensionData();
        $key = Reflections::lowercase($name);
        if (!$key || !isset($activeExtensions[$key])) {
            return null;
        }
        $timestamp = $activeExtensions[$key];
        unset($activeExtensions[$key]);
        $this->activeExtensionsData = $activeExtensions;
        if (isset($this->activeExtensions[$key])) {
            unset($this->activeExtensions[$key]);
            unset($this->lazyDeactivatedExtensions[$key]);
        } elseif (!isset($this->lazyDeactivatedExtensions[$key])) {
            $this->lazyDeactivatedExtensions[$key] = $extension;
        }
        $this->setOption($this->optionName, $activeExtensions);
        return $timestamp;
    }

    /**
     * Get the active extension data from the options
     *
     * @return array<lowercase-string<class-string<ExtensionInterface>>, int>
     */
    private function getActiveExtensionData(): array
    {
        if (isset($this->activeExtensionsData)) {
            return $this->activeExtensionsData;
        }
        $activeExtensions = $this->getOption($this->optionName);
        $update = false;
        if (!is_array($activeExtensions)) {
            $activeExtensions = [];
            $update = true;
        }
        foreach ($activeExtensions as $key => $value) {
            if (!is_string($key) || !is_int($value)) {
                unset($activeExtensions[$key]);
                $update = true;
            }
        }
        if ($update) {
            $this->setOption($this->optionName, $activeExtensions);
        }
        // sort by timestamp
        uasort($activeExtensions, static fn($a, $b) => $a <=> $b);
        $this->activeExtensionsData = $activeExtensions;
        return $this->activeExtensionsData;
    }

    /**
     * @inheritdoc
     */
    public function getActiveExtensions(): array
    {
        if (isset($this->activeExtensions)) {
            return $this->activeExtensions;
        }
        $this->activeExtensions = [];
        foreach ($this->getActiveExtensionData() as $key => $timestamp) {
            $extension = $this->get($key);
            if (!$extension) {
                unset($this->activeExtensionsData[$key]);
                continue;
            }
            if (($this->lazyDeactivatedExtensions[$key] ?? null) !== $extension) {
                $this->activeExtensions[$key] = $extension;
            }
        }
        $this->lazyDeactivatedExtensions = [];
        return $this->activeExtensions;
    }

    /**
     * @inheritdoc
     */
    public function getBootErrors(): array
    {
        return $this->errors ?? [];
    }

    /**
     * @inheritdoc
     */
    public function isBooted(): bool
    {
        return !empty($this->booted);
    }

    /**
     * @inheritdoc
     */
    public function isBooting(): bool
    {
        return !empty($this->booting);
    }

    /**
     * @inheritdoc
     */
    public function getBootTimes(): ?array
    {
        return $this->bootRecords ?? null;
    }

    /**
     * @inheritdoc
     * @return $this
     */
    public function shutdown(): self
    {
        $this->booted = false;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function bootCount(): int
    {
        return count($this->getBootTimes() ?? []);
    }

    /**
     * @inheritdoc
     * @return $this
     * @throws IllegalOperationException if the extension collection is already booting
     */
    public function reboot(): self
    {
        if ($this->isBooting()) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot reboot extension collection %s while it is already booting.',
                    $this->name
                )
            );
        }
        return $this->shutdown()->boot();
    }

    /**
     * @inheritdoc
     * @return $this
     * @throws IllegalOperationException if the extension collection is already booting
     */
    public function boot(): self
    {
        if ($this->isBooted()) {
            return $this;
        }
        if ($this->isBooting()) {
            throw new IllegalOperationException(
                sprintf(
                    'Cannot boot extension collection %s while it is already booting.',
                    $this->name
                )
            );
        }
        try {
            $this->booting = true;
            if (!isset($this->bootRecords)) {
                $this->bootRecords = [];
            }
            $increment = count($this->bootRecords);
            $timeRecord = TimeRecords::createRecord('time');
            $prepareRecord = TimeRecords::createRecord('prepare');
            $bootRecord = TimeRecords::createRecord('boot');
            $this->bootRecords[$increment] = (new TimeRecords())
                ->attach($bootRecord)
                ->attach($prepareRecord)
                ->attach($timeRecord)
                ->lock(); // lock the records to prevent further modifications;
            $timeRecord->start();
            $extensions = [];
            $cores = [];
            $activeExtensions = $this->getActiveExtensions();
            foreach ($this->extensions as $key => $extension) {
                $extension = is_object($extension) ? $extension : $this->get($key);
                if (!$extension) {
                    continue;
                }
                if ($extension->isCore()) {
                    if ($extension->shouldBeActive()) {
                        $extensions[$key] = $extension;
                    } elseif (isset($activeExtensions[$key])) {
                        $cores[$key] = $extension;
                    }
                }
            }
            $update = false;
            // sort by highest priority first
            uasort($extensions, static fn($a, $b) => $b->getPriority() <=> $a->getPriority());
            // cores should be sorted by highest priority first, but they should be added to the end of the list
            uasort($cores, static fn($a, $b) => $b->getPriority() <=> $a->getPriority());

            $prepareRecord->start();
            // execute cores after the extensions, but they should be added to the end of the list
            foreach ($extensions as $key => $extension) {
                unset($this->extensions[$key]);
                try {
                    $extension->prepare($this);
                    $this->activeExtensions[$key] = $extension;
                    if (!isset($this->activeExtensionsData[$key])) {
                        $this->activeExtensionsData[$key] = time();
                        $update = true;
                    }
                } catch (Throwable $e) {
                    $update = $update || isset($this->activeExtensionsData[$key]);
                    unset($this->activeExtensionsData[$key], $extensions[$key]);
                    $this->errors[$key] = $e;
                }
            }
            foreach (array_reverse($cores) as $key => $extension) {
                unset($this->extensions[$key], $cores[$key]);
                $this->extensions = [$key => $extension] + $this->extensions;
            }
            foreach (array_reverse($extensions) as $key => $extension) {
                unset($this->extensions[$key]);
                $this->extensions = [$key => $extension] + $this->extensions;
            }
            $this->lazyDeactivatedExtensions = [];
            foreach ($activeExtensions as $key => $extension) {
                if (isset($extensions[$key])) {
                    continue;
                }
                try {
                    $extension->prepare($this);
                    $this->activeExtensions[$key] = $extension;
                    $extensions[$key] = $extension;
                } catch (Throwable $e) {
                    $update = true;
                    unset(
                        $this->activeExtensionsData[$key],
                        $this->activeExtensions[$key]
                    );
                    $this->errors[$key] = $e;
                }
            }
            $this->activeExtensions = $extensions;
            $activeExtensions = $this->activeExtensionsData;
            foreach ($activeExtensions as $key => $timestamp) {
                if (isset($extensions[$key])) {
                    continue;
                }
                $extension = $this->get($key);
                if (!$extension) {
                    unset($activeExtensions[$key]);
                    continue;
                }
                $update = true;
                try {
                    $this->activeExtensionsData = $activeExtensions;
                    $extension->prepare($this);
                    $this->activeExtensionsData = $activeExtensions;
                    $extensions[$key] = $extension;
                    $this->activeExtensions[$key] = $extension;
                } catch (Throwable $e) {
                    unset($activeExtensions[$key]);
                    $this->errors[$key] = $e;
                }
            }
            $prepareRecord->end();
            $bootRecord->start();
            foreach ($extensions as $key => $extension) {
                try {
                    $this->activeExtensionsData = $activeExtensions;
                    $extension->boot($this);
                    $this->activeExtensionsData = $activeExtensions;
                    $this->loadedExtensions[$key] = get_class($extension);
                    $this->activeExtensions[$key] = $extension;
                } catch (Throwable $e) {
                    $update = true;
                    unset($extensions[$key], $activeExtensions[$key], $this->activeExtensions[$key]);
                    $this->errors[$key] = $e;
                }
            }
            $bootRecord->end();
            $this->activeExtensionsData = $activeExtensions;
            $this->activeExtensions = $extensions; // re-set active extensions to the final list
            if ($update) {
                $this->setOption($this->optionName, $activeExtensions);
            }
            $timeRecord->end();
        } finally {
            $this->booted = true;
            $this->booting = false;
        }
        return $this;
    }
}
