<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

/**
 * @template T of string
 * @extends AbstractManifestAssets<EnvironmentAsset>
 */
class EnvironmentAssets extends AbstractManifestAssets
{
    /**
     * EnvironmentAsset constructor.
     *
     * @param T $environment
     * @param ManifestAssets $manifests
     * @param EnvironmentAsset<T> ...$asset
     */
    public function __construct(
        ManifestAssets $manifests,
        public readonly string $environment,
        EnvironmentAsset ...$asset
    ) {
        parent::__construct($manifests);
        $this->add(...$asset);
    }

    /**
     * Add an environment asset to the collection
     *
     * @param EnvironmentAsset<T> ...$asset
     */
    public function add(EnvironmentAsset ...$asset) : void
    {
        foreach ($asset as $a) {
            $this->addAsset($a);
        }
    }
}
