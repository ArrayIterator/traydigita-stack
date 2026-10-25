<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Extensions\Abstracts;

use TrayDigita\WP\Stack\Source\Interfaces\Collections\ExtensionCoreInterface;

abstract class AbstractCoreExtension extends AbstractExtension implements ExtensionCoreInterface
{
    /**
     * @var string $logo The logo of the core extension
     */
    protected string $logo;

    /**
     * @var int $priority The priority of the core extension
     */
    protected int $priority = 100;

    /**
     * @var ?string $homepage The homepage of the core extension
     */
    protected ?string $homepage = 'https://traydigita.com';

    /**
     * @var string $version The version of the core extension
     */
    protected string $version = '1.0.0';

    /**
     * @var bool $shouldBeActive Whether the core extension should be active
     */
    protected bool $shouldBeActive = false;

    /**
     * @inheritdoc
     */
    protected function coreShouldBeActive(): bool
    {
        return $this->shouldBeActive;
    }

    /**
     * @inheritdoc
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * @inheritdoc
     */
    public function getLogo(): ?string
    {
        return $this->logo ?? null;
    }
}
