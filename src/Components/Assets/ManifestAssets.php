<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Assets;

use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets\AbstractManifestAsset;
use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets\EnvironmentAsset;
use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets\EnvironmentAssets;
use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets\MergedAsset;
use TrayDigita\WP\Stack\Source\Components\Assets\ManifestAssets\MergedAssets;
use TrayDigita\WP\Stack\Source\Components\Data\Site;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Callback;
use TrayDigita\WP\Stack\Source\Libs\Resolver\ThemeDirUrl;
use function array_all;
use function is_array;
use function is_string;
use function trailingslashit;

class ManifestAssets
{
    /**
     * @var int The maximum size of the JSON manifest file in bytes.
     */
    public const MAX_JSON_FILE_SIZE = 1024 * 768; // 768KB

    /**
     * @var string The name of the directory where the manifest files are located.
     */
    public const MANIFEST_DIR = 'manifest';

    /**
     * @var string The name of the JSON manifest file.
     */
    public const JSON_RELEASE_FILE_NAME = 'manifest.json';

    /**
     * @var string The name of the PHP manifest file.
     */
    public const RELEASE_FILE_NAME = 'manifest.php';

    /**
     * @var string The path to the PHP manifest file.
     */
    public readonly string $phpManifestFile;

    /**
     * @var string The path to the JSON manifest file.
     */
    public readonly string $jsonManifestFile;

    /**
     * @var string The directory path where the manifest files are located.
     */
    public readonly string $manifestDir;

    /**
     * @var array<string, array{json: string, php: string}>
     *     An associative array containing the paths to the environment-specific manifest files.
     */
    public readonly array $environmentFiles;

    /**
     * @var MergedAssets
     */
    private MergedAssets $mergedAssets;

    /**
     * @var EnvironmentAssets<"development">
     */
    private EnvironmentAssets $developmentAssets;

    /**
     * @var EnvironmentAssets<"production">
     */
    private EnvironmentAssets $productionAssets;

    /**
     * Create a new instance of the ManifestAssets class.
     *
     * @param Site $site The Site object that provides access to various site-related information and functionality.
     */
    public function __construct(public readonly Site $site)
    {
        $this->phpManifestFile = $this->site->getDistributionDir(self::RELEASE_FILE_NAME);
        $this->jsonManifestFile = $this->site->getDistributionDir(self::JSON_RELEASE_FILE_NAME);
        $this->manifestDir = trailingslashit($this->site->getDistributionDir(self::MANIFEST_DIR));
        $this->environmentFiles = [
            'production' => [
                'json' => ThemeDirUrl::asPath($this->manifestDir, 'production.json'),
                'php' => ThemeDirUrl::asPath($this->manifestDir, 'production.php')
            ],
            'development' => [
                'json' => ThemeDirUrl::asPath($this->manifestDir, 'development.json'),
                'php' => ThemeDirUrl::asPath($this->manifestDir, 'development.php')
            ]
        ];
    }

    /**
     * Parse the manifest file and return an array of assets.
     *
     * @return ?array<string, array{
     *     id: string,
     *     name: string,
     *     src: string,
     *     file: string,
     *     baseName?: string,
     *     files?: array{
     *         minified?: string,
     *         unminified?: string,
     *     },
     *     isEntry: bool
     * }>
     */
    private function parseFromFileManifest(string $file, string $jsonAsAlternate, bool $strict = false): ?array
    {
        $content = Callback::require($file);
        if (!is_array($content)) {
            $content = Callback::content(
                $jsonAsAlternate,
                self::MAX_JSON_FILE_SIZE,
                0,
                self::MAX_JSON_FILE_SIZE
            );
            if (!$content) {
                return null;
            }
            $content = Callback::apply('json_decode', $content, true);
            if (!is_array($content)) {
                return null;
            }
        }
        $result = [];
        foreach ($content as $id => $item) {
            if (!is_string($id) || !is_array($item)) {
                continue;
            }
            $data = [
                'id' => $id,
                'name' => $item['name'] ?? null,
                'src' => $item['src'] ?? null,
                'file' => $item['file'] ?? null,
            ];
            if (!array_all($data, static fn($v) => is_string($v) && (!$strict || $v !== ''))) {
                // early return null if strict mode is enabled & any of the required fields are missing or not strings
                if ($strict) {
                    return null;
                }
            }
            $IsEntry = $item['isEntry'];
            if ($strict) {
                if (!is_bool($IsEntry)) {
                    return null;
                }
            } else {
                $IsEntry = is_bool($IsEntry) ? $IsEntry : (bool)$IsEntry;
            }
            $data['isEntry'] = $IsEntry;
            $baseName = $item['baseName'] ?? null;
            $files = $item['files'] ?? null;
            if (is_string($baseName)) {
                if ($strict && $baseName === '') {
                    return null;
                }
                $data['baseName'] = $baseName;
            } elseif ($strict) {
                return null;
            }
            if (is_array($files)) {
                $minified = $files['minified'] ?? null;
                $unminified = $files['unminified'] ?? null;
                $data['files'] = [];
                if ($strict) {
                    if ($minified !== null && !is_string($minified)) {
                        return null;
                    }
                    if ($unminified !== null && !is_string($unminified)) {
                        return null;
                    }
                }
                if (is_string($minified)) {
                    $data['files']['minified'] = $minified;
                }
                if (is_string($unminified)) {
                    $data['files']['unminified'] = $unminified;
                }
            } elseif ($files !== null && $strict) {
                return null;
            }
            if ($strict) {
                // strict manifest validation: if either baseName or files is set, both must be set
                if (isset($data['baseName']) && !isset($data['files'])) {
                    return null;
                }
                if (isset($data['files']) && !isset($data['baseName'])) {
                    return null;
                }
            }
            $result[$id] = $data;
        }
        return $result;
    }

    /**
     * Create an EnvironmentAssets instance for the given environment and manifest files.
     *
     * @template T of string
     * @param T $environment
     * @param string $phpManifestFile
     * @param string $jsonManifestFile
     * @return EnvironmentAssets<T>
     */
    private function createEnvironmentAssets(
        string $environment,
        string $phpManifestFile,
        string $jsonManifestFile
    ): EnvironmentAssets {
        $assets = new EnvironmentAssets($this, $environment);
        $parse = $this->parseFromFileManifest($phpManifestFile, $jsonManifestFile, true);
        if (!is_array($parse)) {
            return $assets;
        }
        foreach ($parse as $item) {
            $asset = new EnvironmentAsset(
                $assets,
                $item['id'],
                $item['name'],
                $item['file'],
                $item['src'],
                $item['isEntry']
            );
            $assets->add($asset);
        }
        return $assets;
    }

    /**
     * Get the merged assets for the theme.
     *
     * @return MergedAssets
     */
    public function getMergedAssets(): MergedAssets
    {
        if (isset($this->mergedAssets)) {
            return $this->mergedAssets;
        }
        $this->mergedAssets = new MergedAssets($this);
        $parse = $this->parseFromFileManifest($this->phpManifestFile, $this->jsonManifestFile, true);
        if (!is_array($parse)) {
            return $this->mergedAssets;
        }
        foreach ($parse as $item) {
            $files = $item['files'] ?? [];
            $asset = new MergedAsset(
                $this->mergedAssets,
                $item['id'],
                $item['name'],
                $item['file'],
                $item['src'],
                $item['isEntry'],
                $item['baseName'] ?? '',
                $files['unminified'] ?? null,
                $files['minified'] ?? null
            );
            $this->mergedAssets->add($asset);
        }
        return $this->mergedAssets;
    }

    /**
     * Get the production assets for the theme.
     *
     * @return EnvironmentAssets<"production">
     */
    public function getProductionAssets(): EnvironmentAssets
    {
        return $this->productionAssets ??= $this->createEnvironmentAssets(
            'production',
            $this->environmentFiles['production']['php'],
            $this->environmentFiles['production']['json']
        );
    }

    /**
     * Get the development assets for the theme.
     *
     * @return EnvironmentAssets<"development">
     */
    public function getDevelopmentAssets(): EnvironmentAssets
    {
        return $this->developmentAssets ??= $this->createEnvironmentAssets(
            'development',
            $this->environmentFiles['development']['php'],
            $this->environmentFiles['development']['json']
        );
    }

    /**
     * Get an asset by its ID from the merged assets, development assets, or production assets.
     *
     * @param string $id The ID of the asset to retrieve.
     * @return ?AbstractManifestAsset The asset if found, or null if not found.
     */
    public function findAssetById(string $id): ?AbstractManifestAsset // @phpstan-ignore-line
    {
        if ($this->site->isDevelopment) {
            $dependency = $this->getDevelopmentAssets()->findAssetById($id);
            if ($dependency !== null) {
                return $dependency;
            }
        }
        $dependency = $this->getMergedAssets()->findAssetById($id);
        if ($dependency !== null) {
            return $dependency;
        }
        $dependency = $this->getProductionAssets()->findAssetById($id);
        if ($dependency !== null) {
            return $dependency;
        }
        if (!$this->site->isDevelopment) {
            // fallback to development assets if not found in production and merged assets
            return $this->getDevelopmentAssets()->findAssetById($id);
        }
        return null;
    }

    /**
     * Get an asset by its name from the merged assets, development assets, or production assets.
     *
     * @param string $name The name of the asset to retrieve.
     * @return ?AbstractManifestAsset The asset if found, or null if not found.
     */
    public function findAssetByName(string $name): ?AbstractManifestAsset // @phpstan-ignore-line
    {
        if ($this->site->isDevelopment) {
            $dependency = $this->getDevelopmentAssets()->findAssetByName($name);
            if ($dependency !== null) {
                return $dependency;
            }
        }
        $dependency = $this->getMergedAssets()->findAssetByName($name);
        if ($dependency !== null) {
            return $dependency;
        }
        $dependency = $this->getProductionAssets()->findAssetByName($name);
        if ($dependency !== null) {
            return $dependency;
        }
        if (!$this->site->isDevelopment) {
            // fallback to development assets if not found in production and merged assets
            return $this->getDevelopmentAssets()->findAssetByName($name);
        }
        return null;
    }

    /**
     * Find an asset by its ID or name from the merged assets, development assets, or production assets.
     *
     * @param string $idOrName The ID or name of the asset to retrieve.
     * @param bool $idAsPriority Whether to prioritize searching by ID first (default: true).
     * @return ?AbstractManifestAsset The asset if found, or null if not found.
     */
    public function find( // @phpstan-ignore-line
        string $idOrName,
        bool $idAsPriority = true
    ): ?AbstractManifestAsset {
        if ($idAsPriority) {
            return $this->findAssetById($idOrName) ?? $this->findAssetByName($idOrName);
        }
        return $this->findAssetByName($idOrName) ?? $this->findAssetById($idOrName);
    }
}
