<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

use TrayDigita\WP\Stack\Source\Enums\ManifestAssetType;

/**
 * @template T of string
 * @extends AbstractManifestAsset<EnvironmentAssets>
 */
class EnvironmentAsset extends AbstractManifestAsset
{
    /**
     * EnvironmentAsset constructor.
     *
     * @param EnvironmentAssets<T> $assets
     * @param string $id
     * @param string $name
     * @param string $file
     * @param string $src
     * @param bool $isEntry
     */
    public function __construct(
        EnvironmentAssets $assets,
        string $id,
        string $name,
        string $file,
        string $src,
        bool $isEntry
    ) {
        parent::__construct($assets, ManifestAssetType::ENVIRONMENT, $id, $name, $file, $src, $isEntry);
    }
}
