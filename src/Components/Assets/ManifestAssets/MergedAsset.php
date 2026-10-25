<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

use TrayDigita\WP\Stack\Source\Enums\ManifestAssetType;

/**
 * @extends AbstractManifestAsset<MergedAssets>
 */
class MergedAsset extends AbstractManifestAsset
{
    /**
     * MergedAsset constructor.
     *
     * @param MergedAssets $assets
     * @param string $id
     * @param string $name
     * @param string $file
     * @param string $src
     * @param bool $isEntry
     * @param string $baseName
     * @param string|null $unminifiedFile
     * @param string|null $minifiedFile
     */
    public function __construct(
        MergedAssets $assets,
        string $id,
        string $name,
        string $file,
        string $src,
        bool $isEntry,
        public readonly string $baseName,
        public readonly ?string $unminifiedFile = null,
        public readonly ?string $minifiedFile = null
    ) {
        parent::__construct($assets, ManifestAssetType::MERGE, $id, $name, $file, $src, $isEntry);
    }

    /**
     * Convert the MergedAsset to an array representation
     *
     * @return array<string, mixed>
     */
    public function toArray() : array
    {
        $files = [];
        if ($this->unminifiedFile !== null) {
            $files['unminified'] = $this->unminifiedFile;
        }
        if ($this->minifiedFile !== null) {
            $files['minified'] = $this->minifiedFile;
        }
        return [
            'name' => $this->name,
            'file' => $this->file,
            'src' => $this->src,
            'isEntry' => $this->isEntry,
            'baseName' => $this->baseName,
            'files' => $files,
        ];
    }
}
