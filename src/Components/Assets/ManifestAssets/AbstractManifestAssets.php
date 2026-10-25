<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets;

/**
 * @template T of AbstractManifestAsset
 * @implements IteratorAggregate<string, T>
 */
abstract class AbstractManifestAssets implements IteratorAggregate, Countable, JsonSerializable
{
    /**
     * @var array<string, T>
     */
    protected array $collections = [];

    /**
     * AbstractManifestAssets constructor.
     *
     * @param ManifestAssets $manifests
     */
    public function __construct(public readonly ManifestAssets $manifests)
    {
    }

    /**
     * Add an asset to the collection
     * @param T $asset
     */
    protected function addAsset(AbstractManifestAsset $asset): void
    {
        $this->collections[$asset->getId()] = $asset;
    }

    /**
     * Get the collections of assets
     *
     * @return array<string, T>
     */
    public function getCollections(): array
    {
        return $this->collections;
    }

    /**
     * Find an asset by its ID
     *
     * @param string $id
     * @return T|null
     */
    public function findAssetById(string $id): ?AbstractManifestAsset
    {
        return $this->collections[$id] ?? null;
    }

    /**
     * Find an asset by its name
     *
     * @param string $name
     * @return T|null
     */
    public function findAssetByName(string $name): ?AbstractManifestAsset
    {
        foreach ($this->collections as $asset) {
            if ($asset->name === $name) {
                return $asset;
            }
        }
        return null;
    }

    /**
     * Count the number of assets in the collection
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->collections);
    }

    /**
     * Get an iterator for the collection
     *
     * @return Traversable<string, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->collections);
    }

    /**
     * Serialize the collection to JSON
     *
     * @return array<string, T>
     */
    public function jsonSerialize(): array
    {
        return $this->collections;
    }
}
