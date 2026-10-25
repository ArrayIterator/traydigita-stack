<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Enums;

enum PluginType: string
{
    case EXTERNAL = 'external';
    case INTERNAL = 'bundled';
    case WORDPRESS = 'wordpress';
}
