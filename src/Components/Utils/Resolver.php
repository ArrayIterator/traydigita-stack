<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Utils;

use TrayDigita\WP\Stack\Source\Libs\Data\FeatureImage;
use TrayDigita\WP\Stack\Source\Libs\Data\User;
use TrayDigita\WP\Stack\Source\Libs\Resolver\PostUtil;
use TrayDigita\WP\Stack\Source\Libs\Resolver\ThemeDirUrl;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_User;
use function apply_filters;
use function get_term;
use function get_term_link;
use function is_int;
use function is_numeric;
use function is_string;

/**
 * Component Resolver for resolving WP_Post objects from various inputs.
 */
class Resolver
{
    /**
     * Resolve mixed value to User object
     *
     * @param mixed|WP_User|int|string|User|null $user
     * @return User|null The resolved User object or null if not found
     */
    public function user(mixed $user): ?User
    {
        if ($user instanceof User) {
            return $user;
        }
        if (is_string($user) || is_int($user) || $user instanceof WP_User) {
            return new User($user);
        }
        return null;
    }

    /**
     * Resolve mixed value to FeatureImage object
     *
     * @param mixed|WP_Post|int $post
     * @return FeatureImage|null The resolved FeatureImage object or null if not found
     */
    public function featureImage(mixed $post): ?FeatureImage
    {
        $post = $this->post($post);
        return $post ? new FeatureImage($post) : null;
    }

    /**
     * Resolve mixed value to WP_Post object
     *
     * @param mixed|WP_Post|int $post
     * @param bool $preventZero If true, will return null if the post ID is 0 or less
     * @return WP_Post|null The resolved WP_Post object or null if not found
     */
    public function post(mixed $post, bool $preventZero = true): ?WP_Post
    {
        return PostUtil::resolve($post, $preventZero);
    }

    /**
     * Get the title of a post
     *
     * @param mixed|WP_Post|int $post
     * @param string $prefix Optional prefix to prepend to the title
     * @param string $suffix Optional suffix to append to the title
     * @return string|null The title of the post or null if not found
     */
    public function postTitle(mixed $post, string $prefix = '', string $suffix = ''): ?string
    {
        return PostUtil::singlePostTitle($post, $prefix, $suffix);
    }

    /**
     * Resolve mixed value to WP_Term object
     *
     * @param mixed|WP_Term|int $term
     * @return WP_Term|null The resolved WP_Term object or null if not found
     */
    public function term(mixed $term): ?WP_Term
    {
        /** @var WP_Term|null $term */
        if ($term instanceof WP_Term && is_int($term->term_id)) {
            if ($term->term_id <= 0) {
                return null;
            }
            return $term;
        }
        if (is_int($term) || is_numeric($term)) {
            $termId = (int)$term;
            if ($termId <= 0) { // @phpstan-ignore-line
                return null;
            }
            $term = get_term($termId, '', 'OBJECT', 'raw');
            if ($term instanceof WP_Term) {
                return $term;
            }
        }
        return null;
    }

    /**
     * Get the title of a term
     *
     * @param mixed|WP_Term|int $term
     * @param string $prefix Optional prefix to prepend to the title
     * @param string $suffix Optional suffix to append to the title
     * @return string|null The title of the term or null if not found
     */
    public function termTitle(mixed $term, string $prefix = '', string $suffix = ''): ?string
    {
        $term = $this->term($term);
        if (!$term) {
            return null;
        }
        $title = apply_filters('single_term_title', $term->name, $term);
        $title = is_string($title) ? $title : $term->name;
        return $prefix . $title . $suffix;
    }

    /**
     * Get the link of a term
     *
     * @param mixed|WP_Term|int $term
     * @return string|null The link of the term or null if not found
     */
    public function termLink(mixed $term): ?string
    {
        $term = $this->term($term);
        $link = $term ? get_term_link($term) : null;
        return is_string($link) ? $link : null;
    }

    /**
     * Get the permalink of a post
     *
     * @param mixed|WP_Post|int $post
     * @return string|null The permalink of the post or null if not found
     */
    public function permalink(mixed $post): ?string
    {
        return PostUtil::permalink($post);
    }

    /**
     * Get the post type object of a post
     *
     * @param mixed|WP_Post|int $post
     * @return WP_Post_Type|null The post type object or null if not found
     */
    public function postTypeObject(mixed $post): ?WP_Post_Type
    {
        return PostUtil::postTypeObject($post);
    }

    /**
     * Get the post format of a post
     *
     * @param mixed|WP_Post|int $post
     * @return string|null The post format or null if not found
     */
    public function postFormat(mixed $post): ?string
    {
        return PostUtil::postFormat($post);
    }

    /**
     * Get the post meta value of a post
     *
     * @param mixed|WP_Post|int $post
     * @param string $key The meta key to retrieve
     * @param bool $single Whether to return a single value or an array of values
     * @return mixed The meta value(s) or null if not found
     */
    public function postMeta(mixed $post, string $key, bool $single = true): mixed
    {
        return PostUtil::meta($post, $key, $single);
    }

    /**
     * Get the post type of a post
     *
     * @param mixed|WP_Post|int $post
     * @return string|null The post type or null if not found
     */
    public function postType(mixed $post): ?string
    {
        return $this->postTypeObject($post)?->name;
    }

    /**
     * Check if the post type of a post matches the given type
     *
     * @param mixed|WP_Post|int $post
     * @param string $type The post type to check against
     * @return bool True if the post type matches, false otherwise
     */
    public function postTypeIs(mixed $post, string $type): bool
    {
        return $this->postType($post) === $type;
    }

    /**
     * Check if the post is a page
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is a page, false otherwise
     */
    public function isPage(mixed $post): bool
    {
        return $this->postTypeIs($post, 'page');
    }

    /**
     * Check if the post is a post (not a page or custom post type)
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is a post, false otherwise
     */
    public function isPost(mixed $post): bool
    {
        return $this->postTypeIs($post, 'post');
    }

    /**
     * Check if the post is an attachment
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is an attachment, false otherwise
     */
    public function isAttachment(mixed $post): bool
    {
        return $this->postTypeIs($post, 'attachment');
    }

    /**
     * Check if the post is a single post (not a page or attachment)
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is a single post, false otherwise
     */
    public function isSingle(mixed $post): bool
    {
        return PostUtil::isSingle($post);
    }

    /**
     * Check if the post is a singular post (not a page or attachment)
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is a singular post, false otherwise
     */
    public function isSingular(mixed $post): bool
    {
        return PostUtil::isSingular($post);
    }

    /**
     * Check if the post is a revision
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is a revision, false otherwise
     */
    public function isRevision(mixed $post): bool
    {
        return PostUtil::isRevision($post);
    }

    /**
     * Check if the post is a custom post type
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is a custom post type, false otherwise
     */
    public function isCustomPostType(mixed $post): bool
    {
        return PostUtil::isCustomPostType($post);
    }

    /**
     * Check if the post is published
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is published, false otherwise
     */
    public function isPublishedPost(mixed $post): bool
    {
        return PostUtil::isPublished($post);
    }

    /**
     * Check if the post is a draft
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is a draft, false otherwise
     */
    public function isDraftPost(mixed $post): bool
    {
        return PostUtil::isDraft($post);
    }

    /**
     * Check if the post is pending
     *
     * @param mixed|WP_Post|int $post
     * @return bool True if the post is pending, false otherwise
     */
    public function isPendingPost(mixed $post): bool
    {
        return PostUtil::isPending($post);
    }

    /**
     * Get the theme path for a given URI and path
     *
     * @param string $uri The URI to resolve
     * @param string $path The path to append
     * @param bool $trailingSlash Whether to add a trailing slash
     * @param bool $normalizedPath Whether to normalize the path
     * @return string The resolved theme path
     */
    public function themePath(
        string $uri,
        string $path,
        bool $trailingSlash = true,
        bool $normalizedPath = true
    ): string {
        return ThemeDirUrl::asPath($uri, $path, $trailingSlash, $normalizedPath);
    }

    /**
     * Get the template directory URL for a given path
     *
     * @param string $path The path to append
     * @param bool $normalizedPath Whether to normalize the path
     * @return string The resolved template directory URL
     */
    public function templateDirectory(string $path = '', bool $normalizedPath = true): string
    {
        return ThemeDirUrl::templateDirectory($path, $normalizedPath);
    }

    /**
     * Get the stylesheet directory URL for a given path
     *
     * @param string $path The path to append
     * @param bool $normalizedPath Whether to normalize the path
     * @return string The resolved stylesheet directory URL
     */
    public function styleSheetDirectory(string $path = '', bool $normalizedPath = true): string
    {
        return ThemeDirUrl::styleSheetDirectory($path, $normalizedPath);
    }

    /**
     * Get the stylesheet URI for a given path
     *
     * @param string $path The path to append
     * @param bool $normalizedPath Whether to normalize the path
     * @return string The resolved stylesheet URI
     */
    public function styleSheetUri(string $path = '', bool $normalizedPath = true): string
    {
        return ThemeDirUrl::styleSheetUri($path, $normalizedPath);
    }

    /**
     * Get the template URI for a given path
     *
     * @param string $path The path to append
     * @param bool $normalizedPath Whether to normalize the path
     * @return string The resolved template URI
     */
    public function templateUri(string $path = '', bool $normalizedPath = true): string
    {
        return ThemeDirUrl::templateUri($path, $normalizedPath);
    }
}
