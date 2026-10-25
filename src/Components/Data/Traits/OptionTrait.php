<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Data\Traits;

use TrayDigita\WP\Stack\Source\Interfaces\Data\OptionInterface;
use function add_action;
use function apply_filters;
use function array_key_exists;
use function delete_option;
use function delete_site_option;
use function delete_site_transient;
use function delete_transient;
use function do_action;
use function doing_action;
use function get_object_vars;
use function get_option;
use function get_site_option;
use function get_site_transient;
use function get_transient;
use function is_array;
use function max;
use function min;
use function set_site_transient;
use function set_transient;
use function update_option;
use function update_site_option;

/**
 * @mixin OptionInterface
 */
trait OptionTrait
{
    /**
     * The array of options.
     * @var ?array<string, mixed>
     */
    private ?array $options;

    /**
     * The array of deferred updates.
     * @var array<string, mixed>
     */
    private array $deferred_update;

    /**
     * The array of deferred deletes.
     * @var array<string, bool>
     */
    private array $deferred_delete;

    /**
     * The expiration time for the option.
     * @var int
     */
    private int $expiration;

    /**
     * Indicates if the options have been updated from the database.
     * @var bool
     */
    private bool $updated;

    /**
     * Indicates if the options have been changed.
     * @var bool
     */
    private bool $changed;

    /**
     * The hook name for updating the option.
     * @var string
     */
    protected string $hookUpdate;

    /**
     * The hook name for deleting the option.
     * @var string
     */
    protected string $hookDelete;

    /**
     * @inheritdoc
     */
    abstract public function getOptionName(): string;

    /**
     * OptionTrait constructor.
     */
    public function __construct()
    {
        $hook_update = match ($this->getType()) {
            OptionInterface::TYPE_OPTION => 'pre_update_option',
            OptionInterface::TYPE_SITE_TRANSIENT => 'pre_set_site_transient',
            OptionInterface::TYPE_TRANSIENT => 'pre_set_transient',
            default => 'pre_update_site_option',
        };
        $hook_delete = match ($this->getType()) {
            OptionInterface::TYPE_OPTION => 'delete_option',
            OptionInterface::TYPE_SITE_TRANSIENT => 'delete_site_transient',
            OptionInterface::TYPE_TRANSIENT => 'delete_transient',
            default => 'delete_site_option',
        };
        $option_name = $this->getOptionName();
        $this->hookUpdate = "{$hook_update}_$option_name";
        $this->hookDelete = "{$hook_delete}_$option_name";
        add_action($this->hookUpdate, [$this, 'hookUpdate']);
        add_action($this->hookDelete, [$this, 'hookDelete']);
    }

    /**
     * Hook for updating the option.
     *
     * @param mixed $value The new value of the option.
     * @return mixed The original value of the option.
     */
    public function hookUpdate(mixed $value): mixed
    {
        if (!doing_action($this->hookUpdate)) {
            return $value;
        }
        if (is_array($value)) {
            $this->options = $value;
        } else {
            $this->options = null;
        }
        $this->updated = true;
        $this->deferred_delete = [];
        $this->deferred_update = [];
        $this->changed = false;
        return $this->options;
    }

    /**
     * Hook for deleting the option.
     *
     * @param string|mixed $optionName The name of the option being deleted.
     * @return mixed The original option name.
     */
    public function hookDelete(mixed $optionName): mixed
    {
        if (!doing_action($this->hookDelete)) {
            return $optionName;
        }
        if ($optionName !== $this->getOptionName()) {
            return $optionName;
        }
        $this->options = [];
        $this->deferred_delete = [];
        $this->deferred_update = [];
        $this->updated = false;
        $this->changed = false;
        return $optionName;
    }

    /**
     * Set the expiration time for the option.
     *
     * @param int $expiration The expiration time in seconds.
     */
    public function setExpiration(int $expiration): void
    {
        $expiration = max($expiration, OptionInterface::MIN_EXPIRATION);
        $this->expiration = min($expiration, OptionInterface::MAX_EXPIRATION);
    }

    /**
     * Get the expiration time for the option.
     *
     * @return int The expiration time in seconds.
     */
    public function getExpiration(): int
    {
        return $this->expiration??OptionInterface::DEFAULT_EXPIRATION;
    }

    /**
     * Get the type of the option.
     *
     * @return OptionInterface::TYPE_SITE_OPTION|OptionInterface::TYPE_OPTION|OptionInterface::TYPE_TRANSIENT
     */
    public function getType(): string
    {
        return OptionInterface::TYPE_SITE_OPTION;
    }

    /**
     * Check if the options have been changed.
     * @return bool
     */
    final public function isChanged() : bool
    {
        return $this->changed??false;
    }

    /**
     * Check if the options have been updated from the database.
     * @return bool
     */
    final public function isUpdated() : bool
    {
        return $this->updated??false;
    }

    /**
     * Refresh the options from the database.
     * @return array<string, mixed>
     */
    final public function refresh(): array
    {
        $changed = false;
        $option_name = $this->getOptionName();
        if ($this->isUpdated() && is_array($this->options)) {
            $options = $this->rebuildOptions($this->options, $changed);
        } else {
            // this object as placeholder
            $options = match ($this->getType()) {
                OptionInterface::TYPE_OPTION => get_option($option_name, $this),
                OptionInterface::TYPE_SITE_TRANSIENT => get_site_transient($option_name) ?? $this,
                OptionInterface::TYPE_TRANSIENT => get_transient($option_name) ?? $this,
                default => get_site_option($option_name, $this),
            };
            if ($options === $this) {
                return $this->options = $this->convertRefresh([]);
            }
            if (!is_array($options)) {
                $options = [];
                foreach (($this->deferred_update??[]) as $name => $value) {
                    $changed = true;
                    $options[$name] = $value;
                }
                if (!$changed) {
                    match ($this->getType()) {
                        OptionInterface::TYPE_OPTION => delete_option($option_name),
                        OptionInterface::TYPE_SITE_TRANSIENT => delete_site_transient($option_name),
                        OptionInterface::TYPE_TRANSIENT => delete_transient($option_name),
                        default => delete_site_option($option_name),
                    };
                    return $this->options = $this->convertRefresh($options);
                }
            } elseif ($this->isChanged()) {
                $options = $this->rebuildOptions($options, $changed);
            }
        }
        $this->updated = false;
        $this->deferred_delete = [];
        $this->deferred_update = [];
        $this->changed = false;
        if ($changed) {
            $transient_timeout = $this->getExpiration();
            match ($this->getType()) {
                OptionInterface::TYPE_OPTION => update_option($option_name, $options, false),
                OptionInterface::TYPE_SITE_TRANSIENT => set_site_transient($option_name, $options, $transient_timeout),
                OptionInterface::TYPE_TRANSIENT => set_transient($option_name, $options, $transient_timeout),
                default => update_site_option($option_name, $options),
            };
        }
        /**
         * @var array<string, mixed> $options
         */
        return $this->options = $this->convertRefresh($options);
    }

    /**
     * Convert the refreshed options.
     *
     * @template T of array<string, mixed>
     * @param T $options
     * @return T
     */
    protected function convertRefresh(array $options): array
    {
        return $options;
    }

    /**
     * Rebuild the options array with deferred updates and deletes.
     *
     * @param array<string, mixed> $options The original options array.
     * @param ?bool &$changed A reference to a boolean indicating if changes were made.
     * @return array<string, mixed> The rebuilt options array.
     */
    private function rebuildOptions( // @phpstan-ignore-line
        array $options,
        ?bool &$changed = null
    ): array {
        $changed = $changed?:false;
        foreach (($this->deferred_update??[]) as $name => $data) {
            $changed = true;
            $options[$name] = $data;
        }
        foreach (($this->deferred_delete??[]) as $name => $true) {
            $changed = true;
            unset($options[$name]);
        }
        return $options;
    }

    /**
     * Get the internal options, refreshing them if necessary.
     *
     * @return array<string, mixed> The internal options.
     */
    final protected function internalOptions(): array
    {
        return $this->options ??= $this->refresh();
    }

    /**
     * Get all options.
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->internalOptions();
    }

    /**
     * Get an option value.
     *
     * @param string $name The name of the option.
     * @param mixed $default The default value to return if the option does not exist.
     * @return mixed The option value or the default value.
     */
    public function get(string $name, mixed $default = null): mixed
    {
        if (isset($this->deferred_update[$name])) {
            $value = $this->deferred_update[$name];
        } elseif (isset($this->deferred_delete[$name])) {
            $value = $default;
        } else {
            $value = $this->internalOptions()[$name] ?? $default;
        }
        return apply_filters("traydigita_option_$name", $value, $default);
    }

    /**
     * Check if an option exists.
     *
     * @param string $data The name of the option.
     * @return bool True if the option exists, false otherwise.
     */
    public function has(string $data): bool
    {
        return isset($this->internalOptions()[$data]);
    }

    /**
     * Set an option value.
     *
     * @param string $name The name of the option.
     * @param mixed $value The value to set for the option.
     */
    public function set(string $name, mixed $value): void
    {
        $this->options = $this->internalOptions();
        $changed = isset($this->deferred_delete[$name])
            || isset($this->options[$name])
            || !isset($this->deferred_update[$name])
            || $this->deferred_update[$name] !== $value;
        if ($changed) {
            unset($this->deferred_delete[$name], $this->options[$name]);
            $this->deferred_update[$name] = $value;
            $this->changed = true;
        }
        do_action("traydigita_update_" . $this->getType(), $name, $value);
    }

    /**
     * Delete an option.
     *
     * @param string $name The name of the option to delete.
     */
    public function delete(string $name): void
    {
        $this->options = $this->internalOptions();
        if (array_key_exists($name, $this->options)) {
            $this->deferred_delete[$name] = true;
            $this->changed = true;
        }
        unset($this->options[$name], $this->deferred_update[$name]);
        do_action("traydigita_delete_" . $this->getType(), $name);
    }

    /**
     * Magic method to set an option value.
     *
     * @param string $name The name of the option.
     * @param mixed $value The value to set for the option.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    /**
     * Magic method to check if an option exists.
     *
     * @param string $name The name of the option.
     * @return bool True if the option exists, false otherwise.
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Magic method to check if an option exists.
     *
     * @param string $name The name of the option.
     * @return bool True if the option exists, false otherwise.
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Magic method to delete an option.
     *
     * @param string $name The name of the option.
     */
    public function __unset(string $name): void
    {
        $this->delete($name);
    }

    /**
     * Destructor to save changes to the options if they have been modified.
     */
    public function __destruct()
    {
        if ($this->isChanged()) {
            $options = $this->options ?? $this->internalOptions();
            $this->changed = false;
            $options = $this->rebuildOptions($options, $changed);
            if (!$changed) {
                return;
            }
            $option_name = $this->getOptionName();
            $transient_timeout = $this->getExpiration();
            match ($this->getType()) {
                OptionInterface::TYPE_OPTION => update_option($option_name, $options),
                OptionInterface::TYPE_SITE_TRANSIENT => set_site_transient($option_name, $options, $transient_timeout),
                OptionInterface::TYPE_TRANSIENT => set_transient($option_name, $options, $transient_timeout),
                default => update_site_option($option_name, $options),
            };
        }
    }

    /**
     * Magic method to provide debug information for the object.
     *
     * @return array<string, mixed> An array of object properties, with sensitive data redacted.
     */
    public function __debugInfo(): array
    {
        $var = get_object_vars($this);
        if (isset($var['options'])) {
            $var['options'] = '<redacted>';
        }
        if (isset($var['deferred_update'])) {
            $var['deferred_update'] = '<redacted>';
        }
        if (isset($var['deferred_delete'])) {
            $var['deferred_delete'] = '<redacted>';
        }
        return $var;
    }
}
