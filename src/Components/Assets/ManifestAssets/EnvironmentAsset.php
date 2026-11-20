<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

use TrayDigita\WP\Stack\Source\Enums\ManifestAssetType;
use function array_filter;

/**
 * @template T of string
 * @extends AbstractManifestAsset<EnvironmentAssets<T>>
 */
class EnvironmentAsset extends AbstractManifestAsset
{
    /**
     * The dependencies of the asset.
     *
     * @var AbstractManifestAsset<EnvironmentAssets<T>>[]
     */
    public readonly array $dependencies;

    /**
     * EnvironmentAsset constructor.
     *
     * @param EnvironmentAssets<T> $assets
     * @param string $id
     * @param string $name
     * @param string $file
     * @param string $src
     * @param bool $isEntry
     * @param AbstractManifestAsset<EnvironmentAssets<T>> ...$dependencies
     */
    public function __construct(
        EnvironmentAssets $assets,
        string $id,
        string $name,
        string $file,
        string $src,
        bool $isEntry,
        AbstractManifestAsset ...$dependencies
    ) {
        parent::__construct(
            $assets,
            ManifestAssetType::ENVIRONMENT,
            $id,
            $name,
            $file,
            $src,
            $isEntry
        );
        $dependencies = array_filter($dependencies, static fn ($dependency) => $dependency->id === $id);

        $this->dependencies = array_values($dependencies);
    }

    /**
     * Get the dependencies of the asset
     *
     * @return AbstractManifestAsset<EnvironmentAssets<T>>[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}
