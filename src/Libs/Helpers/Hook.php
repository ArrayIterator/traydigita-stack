<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Helpers;

use function add_action;
use function apply_filters;
use function current_action;
use function did_action;
use function do_action;
use function doing_action;
use function has_action;
use function is_string;
use function remove_action;

final class Hook
{
    /**
     * Execute an action hook
     *
     * @param string $action The action name
     * @param mixed ...$args Additional arguments to pass to the action callback
     */
    public static function do(string $action, mixed ...$args) : void
    {
        do_action($action, ...$args);
    }

    /**
     * Apply a filter hook
     * @template T of mixed
     * @param string $action The action name
     * @param T $value The value to filter
     * @param mixed ...$args Additional arguments to pass to the filter callback
     * @return T The filtered value
     */
    public static function apply(string $action, mixed $value, mixed ...$args): mixed
    {
        return apply_filters($action, $value, ...$args);
    }

    /**
     * Remove an action hook
     *
     * @param string $action The action name
     * @param callable $callback The callback function to remove
     * @param int $priority The priority of the action (default: 10)
     * @return bool True if the action was removed, false otherwise
     */
    public static function remove(string $action, callable $callback, int $priority = 10): bool
    {
        return (bool) remove_action($action, $callback, $priority);
    }

    /**
     * Add an action hook
     *
     * @param string $action The action name
     * @param callable $callback The callback function to execute
     * @param int $priority The priority of the action (default: 10)
     * @param int $acceptedArgs The number of arguments the callback accepts (default: 1)
     * @return bool True if the action was added, false otherwise
     */
    public static function add(string $action, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        return (bool) add_action($action, $callback, $priority, $acceptedArgs);
    }

    /**
     * Get the current action name
     *
     * @return string|null The current action name, or null if no action is being executed
     */
    public static function current() : ?string
    {
        $current = current_action();
        return is_string($current) ? $current : null;
    }

    /**
     * Check if the action has been registered
     *
     * @param string $action The action name to check
     * @return bool True if the action has been registered, false otherwise
     */
    public static function has(string $action) : bool
    {
        return (bool) has_action($action);
    }

    /**
     * Check if the action has already been executed
     *
     * @param string $action The action name to check
     * @return int The number of times the action has been executed
     */
    public static function did(string $action) : int
    {
        return (int) did_action($action);
    }

    /**
     * Check if the action is currently being executed
     *
     * @param string $action The action name to check
     * @return bool True if the action is currently being executed, false otherwise
     */
    public static function doing(string $action): bool
    {
        return (bool) doing_action($action);
    }

    /**
     * Check if the action is runnable (either currently doing or already did)
     *
     * @param string $action The action name to check
     * @return bool True if the action is runnable, false otherwise
     */
    public static function runnable(string $action) : bool
    {
        return (self::doing($action) || self::did($action) > 0);
    }
}
