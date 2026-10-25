<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

/**
 * @extends AbstractManifestAssets<MergedAsset>
 */
class MergedAssets extends AbstractManifestAssets
{
    /**
     * MergedAsset constructor.
     *
     * @param ManifestAssets $manifests
     * @param MergedAsset ...$asset
     */
    public function __construct(
        ManifestAssets $manifests,
        MergedAsset ...$asset
    ) {
        parent::__construct($manifests);
        $this->add(...$asset);
    }

    /**
     * Add a merged asset to the collection
     *
     * @param MergedAsset ...$asset
     */
    public function add(MergedAsset ...$asset) : void
    {
        foreach ($asset as $a) {
            $this->addAsset($a);
        }
    }
}
