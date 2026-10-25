<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

use JsonSerializable;
use TrayDigita\WP\Stack\Source\Enums\ManifestAssetFileType;
use TrayDigita\WP\Stack\Source\Enums\ManifestAssetType;

/**
 * @template T of AbstractManifestAssets
 */
abstract class AbstractManifestAsset implements JsonSerializable
{
    /**
     * @var ManifestAssetFileType
     */
    public readonly ManifestAssetFileType $fileType;

    /**
     * @param T $assets
     * @param ManifestAssetType $type
     * @param string $id
     * @param string $name
     * @param string $file
     * @param string $src
     * @param bool $isEntry
     */
    protected function __construct(
        public readonly AbstractManifestAssets $assets,
        public readonly ManifestAssetType $type,
        public readonly string $id,
        public readonly string $name,
        public readonly string $file,
        public readonly string $src,
        public readonly bool $isEntry
    ) {
        $this->fileType = ManifestAssetFileType::fromFileName($this->file);
    }

    /**
     * Get the distribution URL of the asset
     *
     * @return string
     */
    public function getUrl() : string
    {
        return $this->assets->manifests->site->getDistributionUrl($this->file);
    }

    /**
     * Get the value of assets
     *
     * @return T
     */
    public function getAssets(): AbstractManifestAssets
    {
        return $this->assets;
    }

    /**
     * Get the value of type
     *
     * @return ManifestAssetType
     */
    public function getType(): ManifestAssetType
    {
        return $this->type;
    }

    /**
     * Get the value of id
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the value of file
     *
     * @return string
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Get the value of fileType
     *
     * @return ManifestAssetFileType
     */
    public function getFileType(): ManifestAssetFileType
    {
        return $this->fileType;
    }

    /**
     * Get the value of src
     *
     * @return string
     */
    public function getSrc(): string
    {
        return $this->src;
    }

    /**
     * Get the value of isEntry
     *
     * @return bool
     */
    public function isEntry(): bool
    {
        return $this->isEntry;
    }

    /**
     * Convert the asset to an array representation
     *
     * @return array{
     *     'name': string,
     *     'file': string,
     *     'src': string,
     *     'isEntry': bool
     * }
     */
    public function toArray() : array
    {
        return [
            'name' => $this->name,
            'file' => $this->file,
            'src' => $this->src,
            'isEntry' => $this->isEntry,
        ];
    }

    /**
     * @return array{
     *     'name': string,
     *     'file': string,
     *     'src': string,
     *     'isEntry': bool
     * }
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
