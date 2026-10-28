<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Data;

use ArrayAccess;
use Serializable;
use Stringable;
use TrayDigita\WP\Stack\Source\Libs\Resolver\PostUtil;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Callback;
use TrayDigita\WP\Stack\Source\Libs\Helpers\DataFilter;
use WP_Post;
use function _wp_get_attachment_relative_path;
use function absint;
use function array_keys;
use function array_values;
use function count;
use function explode;
use function file_exists;
use function get_post_thumbnail_id;
use function in_array;
use function intval;
use function is_array;
use function is_int;
use function is_string;
use function pathinfo;
use function preg_match;
use function preg_match_all;
use function preg_split;
use function serialize;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;
use function unserialize;
use function wp_get_additional_image_sizes;
use function wp_get_attachment_image_src;
use function wp_get_mime_types;
use function wp_get_registered_image_subsizes;
use function wp_get_upload_dir;
use const PATHINFO_EXTENSION;
use const PREG_SET_ORDER;

/**
 * @property-read ?WP_Post $post
 * @property-read ?int $thumbnail_id
 * @property-read ?array{
 *      id: int,
 *      is_svg: bool,
 *      path: string,
 *      url: string,
 *  } $attachment_details
 * @property-read ?int $feature_image_id
 * @property-read ?string $feature_image_url
 * @property-read ?string $feature_image_ext
 * @property-read ?string $feature_image_path
 * @property-read ?array{
 *      url: string,
 *      width: int,
 *      height: int,
 *      resized: bool,
 *      thumbnail_size: string,
 *      details: array{
 *       id: int,
 *       is_svg: bool,
 *       ext: string,
 *       path: string,
 *       url: string,
 *   }
 *  } $thumbnail
 * @property-read bool $found
 * @template-implements ArrayAccess<string, mixed>
 */
final class FeatureImage implements Stringable, ArrayAccess, Serializable
{
    /**
     * @var WP_Post|null
     */
    private ?WP_Post $post;

    /**
     * @var int|false $thumbnail_id
     */
    private int|false $thumbnail_id;

    /**
     * @var array<int, array{
     *     id: int,
     *     tag: string,
     *     url: ?string,
     * }>
     */
    protected array $image_lists;

    /**
     * @var array{
     *     id: int,
     *     is_svg: bool,
     *     ext: string,
     *     path: string,
     *     url: string,
     *     alt: ?string,
     * }|false
     */
    protected array|false $attachment_details;

    /**
     * @var array<string, false|array{
     *      url:string,
     *      thumbnail_size: string,
     *      width: int,
     *      height: int,
     *      resized: bool,
     * }> $attachment_size
     */
    protected array $attachment_size = [];

    /**
     * @var array<string> $SUPPORTED_IMAGE_EXTENSIONS
     */
    public const SUPPORTED_IMAGE_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'avif',
        'heic'
    ];

    /**
     * @var array<string, false|array{
     *     width: int,
     *     height: int,
     * }>
     */
    private static array $recordsSVGDimensions = [];

    /**
     * @var array<string, array{
     *      width: int,
     *      height: int,
     * }>
     */
    private array $image_sub_sizes;

    /**
     * Image constructor.
     *
     * @param mixed|int|WP_Post $post
     */
    public function __construct(mixed $post)
    {
        if ($post instanceof FeatureImage) {
            if (isset($post->thumbnail_id)) {
                $this->thumbnail_id = $post->thumbnail_id;
            }
            if (isset($post->image_lists)) {
                $this->image_lists = $post->image_lists;
            }
            if (isset($post->attachment_details)) {
                $this->attachment_details = $post->attachment_details;
            }
            $this->attachment_size = $post->attachment_size;
            $post = $post->post;
        }
        $this->post = PostUtil::resolve($post); // default
        if (!$this->post || $this->post->ID <= 0 || !PostUtil::isSingular($this->post)) { // must singular
            $this->post = null;
            $this->attachment_details = false;
            $this->thumbnail_id = false;
            $this->image_lists = [];
            $this->attachment_size = [];
        }
    }

    /**
     * Create a new instance of the Image class with a different post.
     *
     * @param mixed $post The new post to associate with the Image instance
     * @return self A new instance of the Image class
     */
    public function withPost(mixed $post): self
    {
        return new self($post);
    }

    /**
     * Get the post object.
     *
     * @return WP_Post|null
     */
    public function getPost(): ?WP_Post
    {
        return $this->post ?? null;
    }

    /**
     * Check if the feature image is found.
     *
     * @return bool returns true if the feature image is found, false otherwise
     */
    public function isFound(): bool
    {
        return $this->getFeatureImageId() !== null;
    }

    /**
     * Get the list of images in the post content.
     *
     * @return array<int, array{
     *     id: int,
     *     tag: string,
     *     url: ?string,
     * }>
     */
    public function getImageLists(): array
    {
        if (isset($this->image_lists)) {
            return $this->image_lists;
        }
        $this->image_lists = [];
        $post = $this->getPost();
        if (!$post) {
            return [];
        }
        preg_match_all(
            '/(?P<img><img\b[^>]*?\bwp-image-(?P<image_id>[0-9]+)\b[^>]*>)/i',
            $post->post_content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $id = (int)$match['image_id'];
            $img = $match['img'];
            if ($id && $id > 0) {
                preg_match('/src=(?:([\'"])(?P<url>.*?)\1|(?P<url_unquoted>[^\s>]+))/i', $img, $m);
                $image = !empty($m['url']) ? $m['url'] : ($m['url_unquoted'] ?? null);
                if (is_string($image)) {
                    $image = str_contains($image, '?') ? explode('?', $image)[0] : $image;
                    $image = str_contains($image, '#') ? explode('#', $image)[0] : $image;
                }
                $this->image_lists[$id] = [
                    'id' => $id,
                    'tag' => $img,
                    'url' => $image,
                ];
            }
        }
        return $this->image_lists;
    }

    /**
     * Parse the attachment details of the post.
     *
     * @param mixed|WP_Post|int $post
     * @return ?array{
     *     id: int,
     *     is_svg: bool,
     *     ext: string,
     *     path: string,
     *     url: string,
     * }
     */
    private function parseAttachmentDetails(mixed $post): ?array
    {
        $post = PostUtil::resolve($post);
        if (!$post || $post->ID <= 0) {
            return null;
        }
        $mime = $post->post_mime_type;
        if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
            return null;
        }
        $found = false;
        foreach (wp_get_mime_types() as $mime_type) {
            if ($mime_type === $mime) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return null;
        }
        $id = absint($post->ID);
        if ($id <= 0) {
            return null;
        }
        $file = PostUtil::meta($post, '_wp_attached_file');
        if (!$file) {
            return null;
        }
        // Get upload directory.
        $uploads = wp_get_upload_dir();
        if (!$uploads || ($uploads['error'] ?? null) !== false) {
            return null;
        }
        // Check that the upload base exists in the file location.
        if (str_starts_with($file, $uploads['basedir'])) {
            $path = $file;
            // Replace file location with url location.
            $url = str_replace($uploads['basedir'], $uploads['baseurl'], $file);
        } elseif (str_contains($file, 'wp-content/uploads')) {
            $trail = _wp_get_attachment_relative_path($file);
            $base = DataFilter::baseName($file);
            $path = DataFilter::trailingSlash($uploads['basedir'] . '/' . $trail) . $base;
            // Get the directory name relative to the basedir (back compat for pre-2.7 uploads).
            $url = DataFilter::trailingSlash($uploads['baseurl'] . '/' . $trail) . $base;
        } else {
            // It's a newly-uploaded file, therefore $file is relative to the basedir.
            $url = $uploads['baseurl'] . "/$file";
            $path = $uploads['basedir'] . "/$file";
        }
        $ext = strtolower((string)(pathinfo($file, PATHINFO_EXTENSION) ?: ''));
        $is_svg = $ext === 'svg';
        return [
            'id' => $id,
            'ext' => $ext,
            'is_svg' => $is_svg,
            'url' => $url,
            'path' => $path,
        ];
    }

    /**
     * Get the feature image details of the post.
     *
     * @return ?array{
     *      id: int,
     *      is_svg: bool,
     *      ext: string,
     *      path: string,
     *      url: string,
     *  } returns the feature image details or null if not found
     */
    public function getFeatureImageDetails(): ?array
    {
        if (!isset($this->attachment_details)) {
            $this->getImageId();
        }
        return $this->attachment_details ?: null;
    }

    /**
     * Get the feature image URL of the post.
     *
     * @return ?string returns the feature image URL or null if not found
     */
    public function getFeatureImageUrl(): ?string
    {
        $feature = $this->getFeatureImageDetails();
        return $feature ? $feature['url'] : null;
    }

    /**
     * Get the feature image ID of the post.
     *
     * @return ?int returns the feature image ID or null if not found
     */
    public function getFeatureImageId(): ?int
    {
        $feature = $this->getFeatureImageDetails();
        return $feature ? $feature['id'] : null;
    }

    /**
     * Get the feature image path of the post.
     *
     * @return ?string returns the feature image path or null if not found
     */
    public function getFeatureImagePath(): ?string
    {
        $feature = $this->getFeatureImageDetails();
        return $feature ? $feature['path'] : null;
    }

    /**
     * Get the feature image extension of the post.
     *
     * @return ?string returns the feature image extension or null if not found
     */
    public function getFeatureImageExtension(): ?string
    {
        $feature = $this->getFeatureImageDetails();
        return $feature ? $feature['ext'] : null;
    }

    /**
     * Check if the post has a thumbnail ID.
     *
     * @return bool returns true if the post has a thumbnail ID, false otherwise
     */
    public function hasThumbnailId(): bool
    {
        $thumb = $this->getThumbnailId(false);
        return is_int($thumb);
    }

    /**
     * Get the thumbnail ID of the post.
     *
     * @param bool $useImageIdIfEmpty Whether to use the feature image ID if the thumbnail ID is empty
     * @return ?int returns the thumbnail ID or null if not found
     */
    public function getThumbnailId(bool $useImageIdIfEmpty = true): ?int
    {
        if (!isset($this->thumbnail_id)) {
            $this->getImageId();
        }
        if (($this->thumbnail_id ?? null)) {
            return $this->thumbnail_id ?: null;
        }
        if (!$useImageIdIfEmpty) {
            return null;
        }
        return $this->getFeatureImageId();
    }

    /**
     * Get the attachment details of the post.
     *
     * @return ?array{
     *      id: int,
     *      is_svg: bool,
     *      ext: string,
     *      path: string,
     *      url: string,
     *      alt: ?string
     *  } returns the attachment details or null if not found
     */
    public function getAttachmentDetails(): ?array
    {
        if (!isset($this->attachment_details)) {
            $this->getImageId();
        }
        return ($this->attachment_details ?? null) ?: null;
    }

    /**
     * Get the dimensions of an SVG from its content string.
     *
     * @param string $content The SVG content as a string
     * @return ?array{
     *      width: int,
     *      height: int,
     *  } returns the dimensions or null if not found
     */
    public function getSvgDimensionFromString(string $content): ?array
    {
        $content = trim($content);
        if (!$content || str_contains('<svg', $content)) {
            return null;
        }
        $width = null;
        $height = null;
        if (preg_match('/<svg[^>]*\s+width=["\']([\d.]+(?:px|em|rem|%)?)["\']/i', $content, $match)) {
            $width = $match[1];
        }

        if (preg_match('/<svg[^>]*\s+height=["\']([\d.]+(?:px|em|rem|%)?)["\']/i', $content, $match)) {
            $height = $match[1];
        }

        if ((empty($width) || str_contains($width, '%')) && preg_match(
            '/<svg[^>]*\s+viewBox=["\']([\d.\s-]+)["\']/i',
            $content,
            $match
        )
        ) {
            $viewBox = preg_split('/\s+/', trim($match[1]));
            if (is_array($viewBox) && count($viewBox) === 4) {
                $width = $viewBox[2];
                $height = $viewBox[3];
            }
        }

        if ($width === null || $height === null) {
            return null;
        }

        return [
            'width' => intval($width),
            'height' => intval($height)
        ];
    }

    /**
     * Get the dimensions of an SVG file.
     *
     * @return ?array{
     *      width: int,
     *      height: int,
     *  } returns the dimensions or null if not found
     */
    private function getSvgDimensionsFromFile(string $filePath): ?array
    {
        if (isset(self::$recordsSVGDimensions[$filePath])) {
            return self::$recordsSVGDimensions[$filePath] ?: null;
        }

        self::$recordsSVGDimensions[$filePath] = false;
        if (!file_exists($filePath)) {
            return null;
        }
        $content = Callback::apply('file_get_contents', $filePath, false, null, 0, 4096);

        self::$recordsSVGDimensions[$filePath] = is_string($content)
            ? ($this->getSvgDimensionFromString($content) ?: false)
            : false;
        return self::$recordsSVGDimensions[$filePath] ?: null;
    }

    /**
     * Get the thumbnail details of the post for a specific size.
     *
     * @param string $size
     * @return ?array{
     *     id: int,
     *     url: string,
     *     width: int,
     *     height: int,
     *     resized: bool,
     *     thumbnail_size: string,
     *     alt: ?string,
     *     details: array{
     *      id: int,
     *      is_svg: bool,
     *      ext: string,
     *      path: string,
     *      url: string,
     *  }
     * } returns the thumbnail details or null if not found
     */
    public function getThumbnail(string $size = 'thumbnail'): ?array
    {
        $details = $this->getAttachmentDetails();
        if (!$details) {
            return null;
        }
        if (!isset($this->image_sub_sizes)) {
            $registeredSubSized = wp_get_registered_image_subsizes();
            $this->image_sub_sizes = [];
            foreach ($registeredSubSized as $key => $item) {
                if (!is_string($key) || !is_array($item)) {
                    continue;
                }
                $width = $item['width'] ?? null;
                $height = $item['height'] ?? null;
                $crop = (bool)($item['crop'] ?? false);
                if (!is_int($width) || !is_int($height)) {
                    continue;
                }
                $this->image_sub_sizes[$key] = [
                    'width' => $width,
                    'height' => $height,
                    'crop' => $crop
                ];
            }
        }
        if (strtolower($size) === 'full') {
            $size = 'full';
        }
        $image_size = $size;
        if ($size !== 'full' && !isset($this->image_sub_sizes[$size])) {
            $new_size = strtolower($size);
            if ($new_size !== $size) {
                $image_size = $new_size;
            }
            $additional = wp_get_additional_image_sizes();
            $image_size = is_array($additional) && isset($additional[$image_size])
                ? $image_size
                : (
                isset($this->image_sub_sizes['thumbnail']) ? 'thumbnail' : (
                    array_keys($this->image_sub_sizes)[0] ?? 'thumbnail'
                )
                );
        }
        if (isset($this->attachment_size[$image_size])) {
            if (!is_array($this->attachment_size[$image_size])) {
                return null;
            }
            $data = $this->attachment_size[$image_size];
            $data['id'] = $details['id'];
            $data['details'] = $details;
            $data['alt'] = $details['alt'] ?? null;
            return $data;
        }
        $this->attachment_size[$image_size] = false;
        $image_id = $details['id'];
        $src = wp_get_attachment_image_src($image_id, $image_size);
        if (!is_array($src)) {
            return null;
        }
        $src = array_values($src);
        if (count($src) < 4) {
            return null;
        }
        [$url, $width, $height, $resized] = $src;
        if ($details['is_svg']) {
            $size = self::getSvgDimensionsFromFile($details['path']);
            if ($size) {
                ['width' => $width, 'height' => $height] = $size;
            }
        }
        $data = [
            'id' => $details['id'],
            'url' => $url,
            'thumbnail_size' => $image_size,
            'width' => (int)$width,
            'height' => (int)$height,
            'resized' => $resized,
            'alt' => $details['alt'] ?? null
        ];
        $this->attachment_size[$image_size] = $data;
        $data['details'] = $details;
        return $data;
    }

    /**
     * Get the image ID of the post.
     *
     * @return ?int returns the image ID or null if not found
     */
    public function getImageId(): ?int
    {
        if (isset($this->thumbnail_id)) {
            return $this->thumbnail_id ?: null;
        }
        if (isset($this->attachment_details)) {
            return is_array($this->attachment_details) ? $this->attachment_details['id'] : null;
        }
        $post = $this->getPost();
        if (!$post) {
            return null;
        }
        $this->thumbnail_id = false; // reset
        $this->attachment_details = false;
        $thumbnailId = get_post_thumbnail_id($post);
        if ($thumbnailId && is_int($thumbnailId)) {
            $attachment_details = $this->parseAttachmentDetails($thumbnailId) ?: false;
            if (is_array($attachment_details)) {
                $this->thumbnail_id = $thumbnailId;
                $alt = PostUtil::meta($attachment_details['id'], '_wp_attachment_image_alt');
                $alt = is_string($alt) ? $alt : null;
                $attachment_details['alt'] = $alt;
                $this->attachment_details = $attachment_details;
                return $thumbnailId;
            } else {
                $this->thumbnail_id = false;
                $this->attachment_details = false;
            }
        }
        if (!is_string($post->post_content)) {
            return null;
        }
        $svg = null;
        foreach ($this->getImageLists() as $data) {
            $attachment = $this->parseAttachmentDetails($data['id']);
            if ($attachment) {
                if ($attachment['is_svg']) {
                    if ($svg !== null) {
                        continue;
                    }
                    $svg = $attachment;
                    continue;
                }
                if (!in_array($attachment['ext'], self::SUPPORTED_IMAGE_EXTENSIONS)) {
                    continue;
                }
                $alt = PostUtil::meta($attachment['id'], '_wp_attachment_image_alt');
                $alt = is_string($alt) ? $alt : null;
                $attachment['alt'] = $alt;
                $this->attachment_details = $attachment;
                return $attachment['id'];
            }
        }
        if (is_array($svg)) {
            $alt = PostUtil::meta($svg['id'], '_wp_attachment_image_alt');
            $alt = is_string($alt) ? $alt : null;
            $svg['alt'] = $alt;
            $this->attachment_details = $svg;
        }
        return null;
    }

    /**
     * Magic method to get properties dynamically.
     *
     * @param string $name The name of the property to get
     * @return mixed The value of the property or null if not found
     */
    public function __get(string $name)
    {
        return match ($name) {
            'post' => $this->getPost(),
            'thumbnail_id' => $this->getThumbnailId(false),
            'attachment_details' => $this->getAttachmentDetails(),
            'feature_image_id' => $this->getFeatureImageId(),
            'feature_image_path' => $this->getFeatureImagePath(),
            'feature_image_url' => $this->getFeatureImageUrl(),
            'feature_image_ext' => $this->getFeatureImageExtension(),
            'thumbnail' => $this->getThumbnail(),
            'found' => $this->isFound(),
            default => $this->$name ?? null,
        };
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    public function __set(string $name, mixed $value): void
    {
        // void
    }

    public function __unset(string $name): void
    {
        // void
    }

    public function __toString(): string
    {
        return $this->getFeatureImageUrl() ?: '';
    }

    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }
        return $this->__isset($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset)) {
            return null;
        }
        return $this->__get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // void
    }

    public function offsetUnset(mixed $offset): void
    {
        // void
    }

    /**
     * @return array{
     *      post: int|null,
     *      thumbnail_id: int|false|null,
     *      attachment_details: ?array{
     *          id: int,
     *          is_svg: bool,
     *          ext: string,
     *          path: string,
     *          url: string,
     *          alt: ?string,
     *      },
     *     image_lists: ?array<int, array{
     *         id: int,
     *         tag: string,
     *         url: ?string,
     *     }>,
     *     attachment_size : array<string, false|array{
     *          url:string,
     *          thumbnail_size: string,
     *          width: int,
     *          height: int,
     *          resized: bool,
     *      }>
     * }
     */
    public function __serialize(): array
    {
        return [
            'post' => $this->post?->ID,
            'thumbnail_id' => $this->thumbnail_id ?? null,
            'attachment_details' => ($this->attachment_details ?? null) ?: null,
            'image_lists' => $this->image_lists ?? null,
            'attachment_size' => $this->attachment_size
        ];
    }

    /**
     * Unserializes the given data and populates the properties of the FeatureImage object.
     *
     * @param array{
     *       post: int|null,
     *       thumbnail_id: int|false|null,
     *           attachment_details: ?array{
     *           id: int,
     *           is_svg: bool,
     *           ext: string,
     *           path: string,
     *           url: string,
     *           alt: ?string,
     *       },
     *      image_lists: array<int, array{
     *          id: int,
     *          tag: string,
     *          url: ?string,
     *      }>,
     *      attachment_size : array<string, false|array{
     *           url:string,
     *           thumbnail_size: string,
     *           width: int,
     *           height: int,
     *           resized: bool,
     *       }>
     *  } $data The serialized data to unserialize
     */
    public function __unserialize(array $data): void
    {
        $this->post = PostUtil::resolve($data['post'] ?? null);
        if (!$this->post || $this->post->ID <= 0) {
            $this->post = null;
            $this->attachment_details = false;
            $this->thumbnail_id = false;
            $this->image_lists = [];
            $this->attachment_size = [];
            return;
        }
        if (is_array($data['image_lists'] ?? null)) {
            $this->image_lists = $data['image_lists'];
        }
        if (is_array($data['attachment_details'] ?? null) || ($data['attachment_details'] ?? null) === false) {
            $this->attachment_details = $data['attachment_details'];
        }
        if (is_int($data['thumbnail_id'] ?? null) || ($data['thumbnail_id'] ?? null) === false) {
            $this->thumbnail_id = $data['thumbnail_id'];
        }
        if (is_array($data['attachment_size'] ?? null)) {
            $this->attachment_size = $data['attachment_size'];
        }
    }

    /**
     * Serializes the FeatureImage object into a string representation.
     *
     * @return string The serialized string representation of the FeatureImage object
     */
    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * Unserializes the given data and populates the properties of the FeatureImage object.
     *
     * @param string $data The serialized data to unserialize
     */
    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }
}
