<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Enums;

enum ManifestAssetType: string
{
    /**
     * Manifest type Merge
     */
    case MERGE = 'merge';

    /**
     * Manifest type Environment
     */
    case ENVIRONMENT = 'environment';
}
