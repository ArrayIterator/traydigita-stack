<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source;

use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;
use TrayDigita\WP\Stack\Source\Components\Auth\StatelessAuthenticator;
use TrayDigita\WP\Stack\Source\Components\Auth\Totp;
use TrayDigita\WP\Stack\Source\Components\Benchmarks\TimeRecords;
use TrayDigita\WP\Stack\Source\Components\Data\Site;
use TrayDigita\WP\Stack\Source\Components\Data\SiteOptions;
use TrayDigita\WP\Stack\Source\Components\Data\SiteTransients;
use TrayDigita\WP\Stack\Source\Components\Database\Database;
use TrayDigita\WP\Stack\Source\Components\Database\WordPressDatabase;
use TrayDigita\WP\Stack\Source\Components\Database\WPDB;
use TrayDigita\WP\Stack\Source\Components\Defaults\DatetimeZoneDefault;
use TrayDigita\WP\Stack\Source\Components\Extensions\Extensions;
use TrayDigita\WP\Stack\Source\Components\Factory\UserFactory;
use TrayDigita\WP\Stack\Source\Components\Logger\LogAware;
use TrayDigita\WP\Stack\Source\Components\Logger\Logger;
use TrayDigita\WP\Stack\Source\Components\Networks\ClientBrowserType;
use TrayDigita\WP\Stack\Source\Components\Networks\Cloudflare;
use TrayDigita\WP\Stack\Source\Components\Networks\Ip;
use TrayDigita\WP\Stack\Source\Components\Networks\UserAgent;
use TrayDigita\WP\Stack\Source\Components\Networks\UserAgentGenerator;
use TrayDigita\WP\Stack\Source\Components\Provider\JetpackPhoton;
use TrayDigita\WP\Stack\Source\Components\Security\Hash;
use TrayDigita\WP\Stack\Source\Components\Utils\Filter;
use TrayDigita\WP\Stack\Source\Components\Utils\Resolver;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\SingletonInterface;

/**
 * The main stack class for managing components.
 *
 * @mixin Components
 */
final class Stack implements SingletonInterface
{
    /**
     * The version of the TrayDigita Stack.
     */
    public const string VERSION = '1.0.0';

    /**
     * Plugin name constant for TrayDigita Stack.
     */
    public const string PLUGIN_NAME = 'TrayDigita Stack';

    /**
     * @var self
     */
    private static self $instance;

    /**
     * @var Components
     */
    public readonly Components $components;

    /**
     * Stack constructor.
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        self::$instance = $this;
        $this->components = new Components();
        $this->components->set(__CLASS__, $this);
    }

    /**
     * @inheritdoc
     * @return self
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Handle dynamic method calls to the components instance
     *
     * @param string $name The name of the method being called
     * @param array<array-key, mixed> $arguments The arguments passed to the method
     * @return mixed The result of the method call on the components instance
     */
    public function __call(string $name, array $arguments)
    {
        return $this->components->{$name}(...$arguments);
    }

    /**
     * Handle static method calls to the singleton instance
     *
     * @param string $name The name of the method being called
     * @param array<array-key, mixed> $arguments The arguments passed to the method
     * @return mixed The result of the method call on the singleton instance
     */
    public static function __callStatic(string $name, array $arguments)
    {
        return self::getInstance()->components->{$name}(...$arguments);
    }

    /**
     * Handle dynamic property access on the components instance
     *
     * @param string $name The name of the property being accessed
     * @return mixed The value of the property from the components instance
     */
    public function __get(string $name)
    {
        return $this->components->{$name};
    }

    /**
     * Handle dynamic property setting on the components instance
     *
     * @param string $name The name of the property being set
     * @param mixed $value The value to set for the property
     */
    public function __set(string $name, mixed $value): void
    {
        $this->components->{$name} = $value;
    }

    /**
     * Get the components instance
     *
     * @return Components
     */
    public function getComponents(): Components
    {
        return $this->components;
    }

    /**
     * Get an instance of a component class.
     * @template T of object
     * @param class-string<T> $class The class name of the component to retrieve.
     * @return T|null The instance of the requested component class.
     */
    private function getOfInstance(string $class): ?object
    {
        $obj = $this->components->get($class);
        return $obj instanceof $class ? $obj : null;
    }

    /**
     * Get the manifest assets component
     *
     * @return ManifestAssets|null
     */
    public function getManifestAssets(): ?ManifestAssets
    {
        return $this->getOfInstance(ManifestAssets::class);
    }

    /**
     * Get the stateless authenticator component
     *
     * @return StatelessAuthenticator|null
     */
    public function getStatelessAuthenticator(): ?StatelessAuthenticator
    {
        return $this->getOfInstance(StatelessAuthenticator::class);
    }

    /**
     * Get the TOTP component
     *
     * @return Totp|null
     */
    public function getTotp(): ?Totp
    {
        return $this->getOfInstance(Totp::class);
    }

    /**
     * Get the time records component
     *
     * @return TimeRecords|null
     */
    public function getTimeRecords(): ?TimeRecords
    {
        return $this->getOfInstance(TimeRecords::class);
    }

    /**
     * Get the site options component
     *
     * @return SiteOptions|null
     */
    public function getSiteOptions(): ?SiteOptions
    {
        return $this->getOfInstance(SiteOptions::class);
    }

    /**
     * Get the site transients component
     *
     * @return SiteTransients|null
     */
    public function getSiteTransients(): ?SiteTransients
    {
        return $this->getOfInstance(SiteTransients::class);
    }

    /**
     * Get the site component
     *
     * @return Site|null
     */
    public function getSite(): ?Site
    {
        return $this->getOfInstance(Site::class);
    }

    /**
     * Get the WPDB component
     *
     * @return WPDB|null
     */
    public function getWPDB(): ?WPDB
    {
        return $this->getOfInstance(WPDB::class);
    }

    /**
     * Get the WordPress database component
     *
     * @return WordPressDatabase|null
     */
    public function getWordPressDatabase(): ?WordPressDatabase
    {
        return $this->getOfInstance(WordPressDatabase::class);
    }

    /**
     * Get the database component
     *
     * @return Database|null
     */
    public function getDatabase(): ?Database
    {
        return $this->getOfInstance(Database::class);
    }

    /**
     * Get the default datetime zone component
     *
     * @return DatetimeZoneDefault|null
     */
    public function getDatetimeZoneDefault(): ?DatetimeZoneDefault
    {
        return $this->getOfInstance(DatetimeZoneDefault::class);
    }

    /**
     * Get the extensions component
     *
     * @return Extensions<\TrayDigita\WP\Stack\Source\Components\Extensions\Abstracts\AbstractExtension>|null
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function getExtensions(): ?Extensions
    {
        return $this->getOfInstance(Extensions::class);
    }

    /**
     * Get the user factory component
     *
     * @return UserFactory|null
     */
    public function getUserFactory(): ?UserFactory
    {
        return $this->getOfInstance(UserFactory::class);
    }

    /**
     * Get the logger component
     *
     * @return Logger|null
     */
    public function getLogger(): ?Logger
    {
        return $this->getOfInstance(Logger::class);
    }

    /**
     * Get the log aware component
     *
     * @return LogAware<\Psr\Log\LoggerInterface>|null
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function getLogAware(): ?LogAware
    {
        return $this->getOfInstance(LogAware::class);
    }

    /**
     * Get the client browser type component
     *
     * @return ClientBrowserType|null
     */
    public function getClientBrowserType(): ?ClientBrowserType
    {
        return $this->getOfInstance(ClientBrowserType::class);
    }

    /**
     * Get the Cloudflare component
     *
     * @return Cloudflare|null
     */
    public function getCloudflare(): ?Cloudflare
    {
        return $this->getOfInstance(Cloudflare::class);
    }

    /**
     * Get the IP component
     *
     * @return Ip|null
     */
    public function getIp(): ?Ip
    {
        return $this->getOfInstance(Ip::class);
    }

    /**
     * Get the user agent component
     *
     * @return UserAgent|null
     */
    public function getUserAgent(): ?UserAgent
    {
        return $this->getOfInstance(UserAgent::class);
    }

    /**
     * Get the user agent generator component
     *
     * @return UserAgentGenerator|null
     */
    public function getUserAgentGenerator(): ?UserAgentGenerator
    {
        return $this->getOfInstance(UserAgentGenerator::class);
    }

    /**
     * Get the Jetpack Photon provider component
     *
     * @return JetpackPhoton|null
     */
    public function getJetpackPhoton(): ?JetpackPhoton
    {
        return $this->getOfInstance(JetpackPhoton::class);
    }

    /**
     * Get the hash component
     *
     * @return Hash|null
     */
    public function getHash(): ?Hash
    {
        return $this->getOfInstance(Hash::class);
    }

    /**
     * Get the resolver component
     *
     * @return Resolver|null
     */
    public function getResolver(): ?Resolver
    {
        return $this->getOfInstance(Resolver::class);
    }

    /**
     * Get the filter component
     *
     * @return Filter|null
     */
    public function getFilter(): ?Filter
    {
        return $this->getOfInstance(Filter::class);
    }
}
