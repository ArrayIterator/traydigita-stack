<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Database;

/**
 * @mixin \wpdb
 * @property-read \wpdb $wpdb
 */
final class WPDB
{
//    /**
//     * @var \wpdb $static_wpdb Cached static instance of the WordPress database object
//     */
//    private static \wpdb $static_wpdb;

    /**
     * @var \wpdb $wpdb The WordPress database object
     */
    private \wpdb $wpdb;

    /**
     * WPDB constructor.
     *
     * @param \wpdb $db WordPress database object.
     */
    public function __construct(\wpdb $db)
    {
        $this->wpdb = $db;
        // self::$static_wpdb = $db;
    }

    /**
     * Get the WordPress database object.
     *
     * @return \wpdb The WordPress database object
     */
    public function wpdb(): \wpdb
    {
//        if (!isset($this->wpdb)) {
//            if (isset(self::$static_wpdb)) {
//                $this->wpdb = self::$static_wpdb;
//            } else {
//                global $wpdb;
//                if ($wpdb instanceof \wpdb) {
//                    $new_wpdb = $wpdb;
//                } else {
//                    global $table_prefix;
//                    $dbuser = defined('DB_USER') ? \DB_USER : '';
//                    $dbpassword = defined('DB_PASSWORD') ? \DB_PASSWORD : '';
//                    $dbname = defined('DB_NAME') ? \DB_NAME : '';
//                    $dbhost = defined('DB_HOST') ? \DB_HOST : '';
//                    $new_wpdb = new \wpdb($dbuser, $dbpassword, $dbname, $dbhost);
//                    $pref = null;
//                    if (is_string($table_prefix)) {
//                        $pref = $new_wpdb->set_prefix($table_prefix);
//                        if (is_wp_error($pref)) {
//                            $pref = null;
//                        }
//                    }
//                    if (!$pref) {
//                        if (defined('DB_PREFIX')) {
//                            $new_wpdb->set_prefix(\DB_PREFIX);
//                        }
//                    }
//                }
//                self::$static_wpdb = $this->wpdb = $new_wpdb;
//            }
//        }
        return $this->wpdb;
    }

    /**
     * Magic method to call methods on the underlying \wpdb instance.
     *
     * @param string $name
     * @param array<array-key, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->wpdb()->$name(...$arguments);
    }

    /**
     * Magic method to set properties on the underlying \wpdb instance.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, mixed $value): void
    {
        $this->wpdb()->$name = $value;
    }

    /**
     * Magic method to check if a property is set on the underlying \wpdb instance.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if ($name === 'wpdb') {
            return true;
        }
        return isset($this->wpdb()->$name);
    }

    /**
     * Magic method to get properties from the underlying \wpdb instance.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if ($name === 'wpdb') {
            return $this->wpdb();
        }
        return $this->wpdb()->$name;
    }
}
