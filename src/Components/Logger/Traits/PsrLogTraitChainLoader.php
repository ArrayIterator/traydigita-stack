<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Logger\Traits;

use Psr\Log\LoggerInterface;
use Stringable;
use function method_exists;

trait PsrLogTraitChainLoader
{
    /**
     * @return LoggerInterface
     */
    abstract public function getLogger(): LoggerInterface;

    /**
     * Closes the logger and releases any resources.
     */
    public function close(): void
    {
        $logger = $this->getLogger();
        if (method_exists($logger, 'close')) {
            $logger->close();
        }
    }

    /**
     * Resets the logger to its initial state.
     */
    public function reset(): void
    {
        $logger = $this->getLogger();
        if (method_exists($logger, 'reset')) {
            $logger->reset();
        }
    }

    /**
     * @inheritdoc
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->getLogger()->log($level, $message, $context);
    }
}
