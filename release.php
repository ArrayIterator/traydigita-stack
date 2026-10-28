<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);

// @phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols

(function () {
    $phpVersionMin = '8.3.0';
    $releaseDir = __DIR__ . DIRECTORY_SEPARATOR . 'releases';
    $dirPlugin = 'traydigita-stack';
    $includes = [
        'src',
        'vendor',
        '.htaccess',
        'index.php',
        'license.txt',
        'traydigita-stack.php',
    ];

    /**
     * @param string $text
     * @param 'green'|'red'|'yellow'|'blue'|'magenta'|'cyan'|'white'|'gray' $color
     * @return string
     */
    function color(string $text, string $color): string
    {
        $preColor = '';
        switch ($color) {
            case 'green':
                $preColor = "\033[0;32m";
                break;
            case 'red':
                $preColor = "\033[0;31m";
                break;
            case 'yellow':
                $preColor = "\033[0;33m";
                break;
            case 'blue':
                $preColor = "\033[0;34m";
                break;
            case 'magenta':
                $preColor = "\033[0;35m";
                break;
            case 'cyan':
                $preColor = "\033[0;36m";
                break;
            case 'white':
                $preColor = "\033[0;37m";
                break;
            case 'gray':
                $preColor = "\033[0;38m";
                break;
        }
        return $preColor . $text . "\033[0m";
    }

    /**
     * @param string $message
     * @return never-return
     */
    function error(string $message): void
    {
        $date = new DateTimeImmutable();
        // color date
        echo color($date->format('Y-m-d H:i:s'), 'gray') . ' ';
        echo color($message, 'red') . PHP_EOL;
        exit(1);
    }

    /**
     * @param string ...$message
     * @return void
     */
    function message(string ...$message): void
    {
        $date = new DateTimeImmutable();
        // color date
        echo color($date->format('Y-m-d H:i:s'), 'gray') . ' ';
        foreach ($message as $m) {
            echo $m;
        }
        echo PHP_EOL;
    }

    /**
     * @param string $message
     * @return void
     */
    function info(string $message): void
    {
        message(color($message, 'blue'));
    }

    function concatDir(string ...$args): string
    {
        $is_start = str_starts_with($args[0] ?? '', '/') || str_starts_with($args[0] ?? '', '\\');
        $args = array_map(static function ($arg) {
            return trim($arg, '\\\/');
        }, $args);
        $path = implode(DIRECTORY_SEPARATOR, $args);
        if ($is_start) {
            $path = DIRECTORY_SEPARATOR . $path;
        }
        return $path;
    }

    /**
     * Check if the file or directory should be excluded
     *
     * @param SplFileInfo $file
     * @return bool
     */
    function isExclude(SplFileInfo $file): bool
    {
        $baseName = $file->getBasename();
        // if is dot
        if (str_starts_with($baseName, '.')) {
            return $baseName !== '.htaccess'; // skip htaccess but not other dot files
        }
        $excludeDir = [
            concatDir('', 'vendor', 'bin')
        ];
        $excludeFiles = [
            'composer.json',
            '.md',
            '.MD',
        ];
        if ($file->isDir()) {
            foreach ($excludeDir as $ex) {
                $realPath = $file->getRealPath();
                if (!$realPath) {
                    continue;
                }
                if (str_ends_with($realPath, $ex)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($excludeFiles as $ex) {
            if (str_ends_with($file->getFilename(), $ex)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Recursively list all files in a directory, excluding certain files and directories
     *
     * @param string $path
     * @return array<string>
     */
    function listFiles(string $path): array
    {
        if (!file_exists($path)) {
            error("Path does not exist: $path");
        }
        $spl = new SplFileInfo($path);
        $realPath = $spl->getRealPath();
        if (!$realPath) {
            error("Failed to get real path for: $path");
        }
        if (isExclude($spl)) {
            return [];
        }
        if (!$spl->isDir()) {
            if (!$spl->isFile()) {
                error("Path is neither a file nor a directory: $path");
            }
            if (isExclude($spl)) { // @phpstan-ignore-line
                return [];
            }
            return [$realPath];
        }
        $files = [];
        foreach (new DirectoryIterator($realPath) as $file) {
            if ($file->isDot()) {
                continue;
            }
            if (isExclude($file)) {
                continue;
            }
            foreach (listFiles($file->getRealPath()) as $f) {
                $files[] = $f;
            }
        }
        return $files;
    }

    // CHECK IF CLI
    if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
        return; // Exit if not running in CLI mode
    }

    // check php version
    if (version_compare(PHP_VERSION, $phpVersionMin, '<')) {
        error("PHP version must be at least $phpVersionMin. Current version: " . PHP_VERSION);
    }
    info("Starting release process for plugin: $dirPlugin");
    // php version
    info("Current PHP version: " . PHP_VERSION);
    info("Minimum required PHP version: $phpVersionMin");
    info("Release directory: $releaseDir");

    // check ZIP extension
    if (!extension_loaded('zip')) {
        error("ZIP extension is not loaded. Please enable the ZIP extension in your PHP configuration.");
    }
    if (!class_exists('ZipArchive')) {
        error("ZipArchive class is not available. Please ensure the ZIP extension is installed and enabled.");
    }
    $composer_json_file = __DIR__ . DIRECTORY_SEPARATOR . 'composer.json';
    if (!is_file($composer_json_file)) {
        error("composer.json file does not exist in the current directory: $composer_json_file");
    }
    $json = @file_get_contents($composer_json_file);
    if ($json === false) {
        error("Failed to read composer.json file: $composer_json_file");
    }
    $composer = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error("Failed to parse composer.json file: " . json_last_error_msg());
    }
    $version = getenv('RELEASE_VERSION');
    if (!$version) {
        error("RELEASE_VERSION environment variable is not set. Please set it to the desired release version.");
    }
    // remove v prefix if exists
    $version = (string) preg_replace('/^v[-_]?/i', '', $version);
    // check if valid version of xx.xx.xx
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        error("Invalid version format: $version. Expected format: x.y.z (e.g., 1.0.0)");
    }
    info("Release version: $version");
    info("Release zip file: $dirPlugin-$version.zip");
    $zipFile = concatDir($releaseDir, "$dirPlugin-$version.zip");
    if (file_exists($zipFile)) {
        error("Release zip file already exists: $zipFile");
    }
    if (!isset($composer['require']) || !is_array($composer['require'])) {
        error("No 'require' section found in composer.json file.");
    }
    if (!isset($composer['require-dev']) || !is_array($composer['require-dev'])) {
        error("No 'require-dev' section found in composer.json file.");
    }
    $devDependencies = array_filter($composer['require-dev'], static function ($package) {
        return is_string($package) && str_contains($package, '/'); // check if contain slash
    }, ARRAY_FILTER_USE_KEY);
    $prodDependencies = array_filter($composer['require'], static function ($package) {
        return is_string($package) && str_contains($package, '/'); // check if contain slash
    }, ARRAY_FILTER_USE_KEY);

    if (!file_exists($releaseDir)) {
        if (!mkdir($releaseDir, 0755, true) && !is_dir($releaseDir)) {
            error("Failed to create release directory: $releaseDir");
        }
    }

    if (!is_dir($releaseDir)) {
        error("Release path is not a directory: $releaseDir");
    }

    if (!is_writable($releaseDir)) {
        error("Release path is not writable: $releaseDir");
    }

    $existsDev = [];
    $notExistsProd = [];
    $existsProd = [];
    $vendorDir = concatDir(__DIR__, 'vendor');
    // CHECK IF CONTAIN DEV
    foreach ($devDependencies as $package => $v) {
        // check if contain slash
        if (!str_contains($package, '/')) {
            // skip if not contain slash, because it's not a package
            continue;
        }
        $packagePath = concatDir($vendorDir, ...explode('/', $package));
        if (file_exists($packagePath)) {
            $existsDev[] = $package;
        }
    }

    if (!empty($existsDev)) {
        $message = "The following dev dependencies are present in the vendor directory:\n";
        foreach ($existsDev as $package) {
            $message .= " - $package\n";
        }
        $message .= "Please remove these dev dependencies before creating a release.\n";
        error($message);
    }

    foreach ($prodDependencies as $package => $v) {
        // check if contain slash
        if (!str_contains($package, '/')) {
            // skip if not contain slash, because it's not a package
            continue;
        }
        $packagePath = concatDir($vendorDir, ...explode('/', $package));
        if (!file_exists($packagePath)) {
            $notExistsProd[] = $package;
        } else {
            $existsProd[] = $package;
        }
    }
    if (count($existsProd) !== count($prodDependencies)) {
        $message = "The following prod dependencies are missing in the vendor directory:\n";
        foreach ($notExistsProd as $package) {
            $message .= " - $package\n";
        }
        $message .= "Please install these prod dependencies before creating a release.\n";
        error($message);
    }

    info("All prod dependencies are present in the vendor directory. Proceeding with release creation.");
    $files = [];
    foreach ($includes as $include) {
        $filePath = concatDir(__DIR__, $include);
        $lists = listFiles($filePath);
        foreach ($lists as $file) {
            $substring = substr($file, strlen(__DIR__) + 1);
            $files[$file] = $substring;
        }
    }
    $files = array_map(static function ($file) use ($dirPlugin) {
        return concatDir($dirPlugin, $file);
    }, $files);
    info("Total files to include in the release zip: " . count($files));
    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        error("Failed to create zip file: $zipFile");
    }
    foreach ($files as $file => $localName) {
        if (!$zip->addFile($file, $localName)) {
            error("Failed to add file to zip: $file");
        }
    }
    if (!$zip->close()) {
        error("Failed to close zip file: $zipFile");
    }
    message("Release zip file created successfully: " . color($zipFile, 'blue'));
})();
