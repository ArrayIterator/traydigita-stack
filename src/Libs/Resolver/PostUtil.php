<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Resolver;

use TrayDigita\WP\Stack\Source\Libs\Helpers\Callback;
use TrayDigita\WP\Stack\Source\Libs\Helpers\Ctype;
use WP_Post;
use WP_Post_Type;
use function apply_filters;
use function get_permalink;
use function get_post;
use function get_post_format;
use function get_post_meta;
use function get_post_type_object;
use function in_array;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function method_exists;
use function preg_replace;

class PostUtil
{
    /**
     * Resolve mixed value to WP_Post object
     *
     * @param mixed|WP_Post|int $post
     * @param bool $preventZero If true, will return null if the post ID is 0 or less
     * @return WP_Post|null The resolved WP_Post object or null if not found
     */
    public static function resolve(mixed $post, bool $preventZero = true): ?WP_Post
    {
        if ($post instanceof WP_Post) {
            if ($preventZero && $post->ID <= 0) {
                return null;
            }
            return $post;
        }
        if (is_object($post)) {
            $tempPost = null;
            if (method_exists($post, 'getPost')) {
                $tempPost = Callback::apply([$post, 'getPost']);
            }
            if (!$tempPost && isset($post->post)) {
                $tempPost = Callback::apply(static fn ($p) => $p->post, $post);
            }
            if ($tempPost instanceof WP_Post) {
                if ($preventZero && $tempPost->ID <= 0) {
                    return null;
                }
                return $tempPost;
            }
            unset($tempPost);
            return null;
        }
        if (!is_int($post) && is_numeric($post)) {
            $post = (string)$post;
            if (str_contains($post, '.')) {
                $post = (string) preg_replace('~\.0+$~', '', $post);
            }
            if (!Ctype::digit($post)) {
                return null;
            }
            $post = (int)$post;
        }
        if (is_int($post)) {
            if ($post < 0) {
                return null;
            }
            if ($preventZero && $post === 0) {
                return null;
            }
            $post = get_post($post);
        }
        if (!$post instanceof WP_Post) {
            return null;
        }
        if ($preventZero && $post->ID <= 0) {
            return null;
        }
        return $post;
    }

    /**
     * Get the permalink of a post
     *
     * @param mixed|WP_Post|int $post
     * @return string|null
     */
    public static function permalink(mixed $post): ?string
    {
        $post = self::resolve($post);
        if (!$post) {
            return null;
        }
        $permalink = get_permalink($post);
        return is_string($permalink) ? $permalink : null;
    }

    /**
     * Get post-meta value
     *
     * @param mixed|WP_Post|int $post
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function meta(mixed $post, string $key, mixed $default = null) : mixed
    {
        $post = self::resolve($post);
        if (!$post) {
            return $default;
        }
        return get_post_meta($post->ID, $key, true) ?: $default;
    }

    /**
     * Get the WP_Post_Type object of a post
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return WP_Post_Type|null The WP_Post_Type object or null if not found
     */
    public static function postTypeObject(mixed $post): ?WP_Post_Type
    {
        $post = self::resolve($post);
        if (!$post) {
            return null;
        }
        return get_post_type_object($post->post_type) ?: null;
    }

    /**
     * Get the post format of a post
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return string|null The post format or null if not found
     */
    public static function postFormat(mixed $post): ?string
    {
        $post = self::resolve($post);
        $post_format = $post ? get_post_format($post) : null;
        return is_string($post_format) ? $post_format : null;
    }

    /**
     * Get the post type name of a post
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return string|null The post type name or null if not found
     */
    public static function postType(mixed $post): ?string
    {
        return self::postTypeObject($post)?->name;
    }

    /**
     * Check if the post type of post matches a given name
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @param string $name The post type name to check against
     * @return bool True if the post type matches, false otherwise
     */
    public static function postTypeIs(mixed $post, string $name): bool
    {
        return self::postTypeObject($post)?->name === $name;
    }

    /**
     * Check if the post is a page
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post is a page, false otherwise
     */
    public static function isPage(mixed $post): bool
    {
        return self::postTypeIs($post, 'page');
    }

    /**
     * Check if the post is an attachment
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post is an attachment, false otherwise
     */
    public static function isAttachment(mixed $post): bool
    {
        return self::postTypeIs($post, 'attachment');
    }

    /**
     * Check if the post is a standard post
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post is a standard post, false otherwise
     */
    public static function isPost(mixed $post): bool
    {
        return self::postTypeIs($post, 'post');
    }

    /**
     * Check if the post is a single post (not a page or attachment)
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post is a single post, false otherwise
     */
    public static function isSingle(mixed $post): bool
    {
        $post_type_obj = self::postTypeObject($post);
        if (!$post_type_obj) {
            return false;
        }
        return $post_type_obj->publicly_queryable && $post->post_type !== 'page' && $post->post_type !== 'attachment';
    }

    /**
     * Check if the post is singular.
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post is singular, false otherwise
     */
    public static function isSingular(mixed $post): bool
    {
        $post_type = self::postTypeObject($post);
        if (!$post_type) {
            return false;
        }
        return $post_type->publicly_queryable === true
            || $post_type->name === 'page'
            || $post_type->name === 'post'
            || $post_type->name === 'attachment';
    }

    /**
     * Check if the post is a custom post type.
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post is a custom post type, false otherwise
     */
    public static function isCustomPostType(mixed $post): bool
    {
        $post_type_obj = self::postTypeObject($post);
        if (!$post_type_obj) {
            return false;
        }
        return !$post_type_obj->_builtin && !in_array($post->post_type, ['post', 'page', 'attachment']);
    }

    /**
     * Check if the post is published.
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post status is publish, false otherwise
     */
    public static function isPublished(mixed $post): bool
    {
        return self::resolve($post)?->post_status === 'publish';
    }

    /**
     * Check if the post is a draft.
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post status is draft, false otherwise
     */
    public static function isDraft(mixed $post): bool
    {
        return self::resolve($post)?->post_status === 'draft';
    }

    /**
     * Check if the post is pending.
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post status is pending, false otherwise
     */
    public static function isPending(mixed $post): bool
    {
        return self::resolve($post)?->post_status === 'pending';
    }

    /**
     * Check if the post is a revision.
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @return bool True if the post is a revision, false otherwise
     */
    public static function isRevision(mixed $post): bool
    {
        return self::postTypeIs($post, 'revision');
    }

    /**
     * Get the filtered single post title with optional prefix and suffix.
     *
     * @param mixed|WP_Post|int $post The post object or ID
     * @param string $prefix Optional content to prepend to the title
     * @param string $suffix Optional content to append to the title
     * @return string|null The formatted title or null if the post cannot be resolved
     */
    public static function singlePostTitle(mixed $post, string $prefix = '', string $suffix = ''): ?string
    {
        $post = self::resolve($post);
        if (!$post) {
            return null;
        }
        $content = $prefix;
        $title = apply_filters('single_post_title', $post->post_title, $post);
        $content .= !is_string($title) ? $post->post_title : $title;
        $content .= $suffix;
        return $content;
    }
}
