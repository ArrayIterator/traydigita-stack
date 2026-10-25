<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use TrayDigita\WP\Stack\Source\Components\Logger\Traits\PsrLogTraitChainLoader;
use TrayDigita\WP\Stack\Source\Exceptions\InvalidArgumentException;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryComponentInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;
use TrayDigita\WP\Stack\Source\Libs\Collections\RegistryChainingIdentity;

/**
 * @template T of LoggerInterface
 * @mixin LoggerInterface
 */
class LogAware extends AbstractLogger implements LoggerAwareInterface, RegistryComponentInterface
{
    use PsrLogTraitChainLoader;

    /**
     * The logger instance.
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * LogAware constructor.
     *
     * @param LoggerInterface $logger The logger instance to be used.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
    }

    /**
     * Sets a logger.
     * @param LoggerInterface $logger The logger instance to be used.
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        if ($logger === $this) {
            throw new InvalidArgumentException('Cannot set logger to self');
        }
        $this->logger = $logger;
    }

    /**
     * Gets the current logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Magic method to handle dynamic method calls.
     *
     * @param string $name The name of the method being called.
     * @param array<array-key, mixed> $arguments The arguments passed to the method.
     * @return mixed The result of the method call on the logger instance.
     */
    public function __call(string $name, array $arguments) : mixed
    {
        return $this->getLogger()->{$name}(...$arguments);
    }

    /**
     * Magic getter proxy to the wrapped logger.
     *
     * @param string $name The property name.
     * @return mixed The resolved property value.
     */
    public function __get(string $name) : mixed
    {
        return $this->getLogger()->{$name};
    }

    /**
     * Magic isset proxy to the wrapped logger.
     *
     * @param string $name The property name.
     * @return bool Whether the property is set.
     */
    public function __isset(string $name): bool
    {
        return isset($this->getLogger()->{$name});
    }

    /**
     * Magic unset proxy to the wrapped logger.
     *
     * @param string $name The property name.
     * @return void
     */
    public function __unset(string $name): void
    {
        unset($this->getLogger()->{$name});
    }

    /**
     * Serialize the object.
     *
     * @return array<string, mixed> The serialized data.
     */
    public function __serialize(): array
    {
        return [
            'logger' => $this->logger,
        ];
    }

    /**
     * Unserialize the object.
     *
     * @param array<string, mixed> $data The serialized data.
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->logger = $data['logger'] ?? null;
    }

    /**
     * @inheritdoc
     */
    public static function registerSelf(string $id, RegistryInterface $registry): ?RegistryChainingIdentity
    {
        return RegistryChainingIdentity::create(
            $id,
            [
                self::class,
                LoggerAwareInterface::class,
            ],
            $registry->locked($id),
            $registry->unlockable($id)
        );
    }
}
