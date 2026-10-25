<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Enums;

/**
 * Enum representing different types of manifest asset files.
 */
enum ManifestAssetFileType: string
{
    /**
     * JavaScript file type
     */
    case JS = 'js';

    /**
     * CSS file type
     */
    case CSS = 'css';

    /**
     * SCSS file type
     */
    case SCSS = 'scss';

    /**
     * LESS file type
     */
    case LESS = 'less';

    /**
     * Source map file type
     */
    case MAP = 'map';

    /**
     * Image file type
     */
    case IMAGE = 'image';

    /**
     * Font file type
     */
    case FONT = 'font';

    /**
     * Video file type
     */
    case VIDEO = 'video';

    /**
     * Audio file type
     */
    case AUDIO = 'audio';

    /**
     * HTML file type
     */
    case HTML = 'html';

    /**
     * JSON file type
     */
    case JSON = 'json';

    /**
     * XML file type
     */
    case XML = 'xml';

    /**
     * Configuration file type
     */
    case CONFIG = 'config';

    /**
     * Archive file type
     */
    case ARCHIVE = 'archive';

    /**
     * Application file type
     */
    case APPLICATION = 'application';

    /**
     * Script file type
     */
    case SCRIPT = 'script';

    /**
     * Other file type
     */
    case OTHER = 'other';

    /**
     * Get the file type from the file name
     *
     * @param string $fileName
     * @return ManifestAssetFileType
     */
    public static function fromFileName(string $fileName): self
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION) ?: '');
        $result = match ($ext) {
            'js', 'ts', 'tsx', 'jsx' => self::JS,
            'css' => self::CSS,
            'scss', 'sass' => self::SCSS,
            'less' => self::LESS,
            'map' => self::MAP,
            'woff', 'woff2', 'ttf', 'otf', 'eot' => self::FONT,
            'mp4', 'webm', 'weba', 'flv', 'avi', 'mov', 'mkv' => self::VIDEO,
            'mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a' => self::AUDIO,
            'html', 'htm', 'xhtml' => self::HTML,
            'json', 'jsonp' => self::JSON,
            'xml' => self::XML,
            'jpg', 'jpeg', 'avif', 'png', 'gif', 'heic', 'svg', 'webp', 'bmp', 'tiff' => self::IMAGE,
            'xz', 'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'gzip', 'jar', 'lz4' => self::ARCHIVE,
            'exe', 'msi', 'apk', 'app', 'bin', 'deb', 'rpm' => self::APPLICATION,
            'config', 'conf', 'cfg', 'ini', 'env' => self::CONFIG,
            'sh', 'bash', 'zsh', 'py', 'java', 'php', 'pl',
            'rb', 'bat', 'ps1', 'inc', 'cmd', 'vbs', 'jscript',
            'coffee', 'lua', 'r', 'sql'
            => self::SCRIPT,
            default => null,
        };
        if ($result === null) {
            $result = self::OTHER;
        }
        return $result;
    }
}
