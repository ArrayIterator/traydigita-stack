<?php
declare(strict_types=1);

use TrayDigita\WP\Stack\Source\Libs\Helpers\PrettyExport;
use TrayDigita\WP\Stack\Source\Libs\Icons\IcoMoon\Libs\WebComponent;

if (PHP_SAPI !== 'cli') {
    return;
}

require_once __DIR__ . '/../../../../../../../wp-load.php';
require __DIR__ . '/../../../../vendor/autoload.php';

foreach (new DirectoryIterator(__DIR__ . '/data') as $file) {
    if ($file->isDot() || !$file->isFile()) {
        continue;
    }
    $filename = $file->getFilename();
    if (!str_ends_with($filename, '.json')) {
        continue;
    }
    $name = substr($filename, 0, -5);
    $web = WebComponent::parseJsonFile(__DIR__ . "/data/{$filename}");
    file_put_contents(__DIR__ . "/data/{$name}.php", PrettyExport::exportAsPhpReturnAbsPath($web));
    echo "Exported: {$name}.php\n";
}
