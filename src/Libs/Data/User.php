<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Data;

use DateTimeInterface;
use SensitiveParameter;
use stdClass;
use Throwable;
use TrayDigita\WP\Stack\Source\Interfaces\Data\UserInterface;
use TrayDigita\WP\Stack\Source\Libs\Helpers\DatetimeImmutableUnit;
use WP_User;
use function absint;
use function get_current_user_id;
use function is_int;
use function is_numeric;
use function is_string;
use function strtolower;
use function trim;
use function user_can;
use function wp_check_password;

/**
 * Property back compatibility for WP_User
 *
 * @property string $nickname
 * @property string $description
 * @property string $user_description
 * @property string $first_name
 * @property string $user_firstname
 * @property string $last_name
 * @property string $user_lastname
 * @property string $user_login
 * @property string $user_pass
 * @property string $user_nicename
 * @property string $user_email
 * @property string $user_url
 * @property string $user_registered
 * @property string $user_activation_key
 * @property string $user_status
 * @property int $user_level
 * @property string $display_name
 * @property string $spam
 * @property string $deleted
 * @property string $locale
 * @property string $rich_editing
 * @property string $syntax_highlighting
 * @property string $use_ssl
 */
final class User implements UserInterface
{
    /**
     * @var WP_User $user WordPress user object
     */
    public readonly WP_User $user;

    /**
     * @var int $id User ID
     */
    public readonly int $id;

    /**
     * @var string $username User login name (lowercase)
     */
    public readonly string $username;

    /**
     * @var string $password User password hash
     */
    public readonly string $password;

    /**
     * @var string $nickname User nickname
     */
    public readonly string $nickname;

    /**
     * @var string $email User email address
     */
    public readonly string $email;

    /**
     * @var string $url User website URL
     */
    public readonly string $url;

    /**
     * @var ?DatetimeImmutableUnit $registered User registration date and time
     */
    public readonly ?DatetimeImmutableUnit $registered;

    /**
     * @var string $activationKey User activation key
     */
    public readonly string $activationKey;

    /**
     * @var string $displayName User display name
     */
    public readonly string $displayName;

    /**
     * @var bool $exists Whether the user exists in the database
     */
    public readonly bool $exists;

    /**
     * @param WP_User|int|string|User|null $user WordPress user object
     */
    public function __construct(
        #[SensitiveParameter]
        null|WP_User|int|string|User $user = null
    ) {
        if ($user instanceof User) {
            $this->user = $user->user;
            $this->url = $user->url;
            $this->id = $user->id;
            $this->username = $user->username;
            $this->password = $user->password;
            $this->nickname = $user->nickname;
            $this->email = $user->email;
            $this->registered = $user->registered;
            $this->activationKey = $user->activationKey;
            $this->displayName = $user->displayName;
            $this->exists = $user->exists;
            return;
        }
        if (!$user instanceof WP_User) {
            $userdata = new stdClass();
            if ($user === null) {
                $id = get_current_user_id();
                if ($id > 0) {
                    $userdata = WP_User::get_data_by('id', $id)?:$userdata;
                }
            } elseif (is_int($user) && $user > 0) {
                $userdata = WP_User::get_data_by('id', $user)?:$userdata;
            } elseif (is_string($user) && trim($user) !== '') {
                $userdata = WP_User::get_data_by('login', strtolower(trim($user)))?:$userdata;
            }
            $user = new WP_User();
            $user->init($userdata);
        }
        $this->id = $this->makeInt($user->ID);
        $this->username = strtolower($this->makeString($user->user_login));
        $this->password = $this->makeString($user->user_pass);
        $this->nickname = $this->makeString($user->nickname);
        $this->email = $this->makeString($user->user_email);
        $this->url = $this->makeString($user->user_url);
        $this->registered = $this->makeDatetime($user->user_registered);
        $this->activationKey = $this->makeString($user->user_activation_key);
        $this->displayName = $this->makeString($user->display_name);
        $this->exists = $user->exists() && $this->id > 0;
        $this->user = $user;
    }

    /**
     * @inheritdoc
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Check if the user has a specific capability
     *
     * @param string $cap Capability to check
     * @return bool True if the user has the capability, false otherwise
     */
    public function can(string $cap): bool
    {
        if (!$this->exists) {
            return false;
        }
        return user_can($this->user, $cap);
    }

    /**
     * Convert a value to an integer
     *
     * @param mixed $value The value to convert
     * @return int The converted integer value, or 0 if the value is not numeric
     */
    private function makeInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return absint($value);
        }
        return 0;
    }

    /**
     * Convert a value to a string
     *
     * @param mixed $value The value to convert
     * @return string The converted string value, or an empty string if the value is not a string
     */
    private function makeString(
        #[SensitiveParameter]
        mixed $value
    ): string {
        if (is_string($value)) {
            return trim($value);
        }
        return '';
    }

    /**
     * Convert a value to a DatetimeImmutableStringable instance
     *
     * @param mixed $value The value to convert
     * @return ?DatetimeImmutableUnit
     * The converted DatetimeImmutableStringable instance, or null if the value is invalid
     */
    private function makeDatetime(mixed $value): ?DatetimeImmutableUnit
    {
        if ($value instanceof DatetimeImmutableUnit) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return DatetimeImmutableUnit::createFromInterface($value);
        }
        if (is_string($value)) {
            try {
                return DatetimeImmutableUnit::createFromWordPressDatabase($value);
            } catch (Throwable) {
                return null;
            }
        }
        return null;
    }

    /**
     * Check if the provided password matches the user's password
     *
     * @param string $password The password to check
     * @return bool True if the password matches, false otherwise
     */
    public function isPasswordMatch(#[SensitiveParameter] string $password): bool
    {
        return wp_check_password($password, $this->user->user_pass);
    }

    /**
     * Magic method to access properties of the underlying WP_User object
     *
     * @param string $name The name of the property to access
     * @return mixed The value of the property, or null if the property does not exist
     */
    public function __get(string $name)
    {
        return $this->user->$name ?? null;
    }

    /**
     * Magic method to prevent setting properties on the User object
     *
     * @param string $name The name of the property to set
     * @param mixed $value The value to set (sensitive parameter)
     */
    public function __set(
        string $name,
        #[SensitiveParameter]
        mixed $value
    ): void {
        // Prevent setting properties on the User object
    }

    /**
     * Magic method to check if a property exists on the underlying WP_User object
     *
     * @param string $name The name of the property to check
     * @return bool True if the property exists, false otherwise
     */
    public function __isset(string $name): bool
    {
        return isset($this->user->$name);
    }
}
