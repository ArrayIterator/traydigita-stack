<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Collector\Analytics;

use ArrayAccess;
use Countable;
use JsonSerializable;
use Serializable;
use Stringable;
use TrayDigita\WP\Stack\Source\Libs\Data\FeatureImage;
use WP_Post;
use function get_post;
use function is_string;
use function serialize;
use function trim;
use function unserialize;

/**
 * @property-read int $count
 * @property-read FeatureImage $featureImage
 * @property-read ?WP_Post $post
 * @template-implements ArrayAccess<string, mixed>
 */
final class AnalyticRecord implements Serializable, JsonSerializable, Countable, Stringable, ArrayAccess
{
    /**
     * @var string
     */
    public readonly string $slug;

    /**
     * @var int
     */
    public readonly int $id;

    /**
     * @var int
     */
    private int $count;

    /**
     * @var WP_Post|false
     */
    private WP_Post|false $post;

    /**
     * @var bool
     */
    private bool $mismatched_slug = false;

    /**
     * @var FeatureImage
     */
    private FeatureImage $featureImage;

    /**
     * AnalyticRecord constructor.
     *
     * @param string $slug The slug of the record.
     * @param int $id The ID of the record.
     * @param int $count The count associated with the record.
     */
    public function __construct(
        string $slug,
        int $id,
        int $count
    ) {
        $this->slug = $slug;
        $this->id = $id;
        $this->count = $count;
    }

    /**
     * @param string $slug
     * @param int $id
     * @param int $count
     * @return self
     */
    public static function create(string $slug, int $id, int $count): self
    {
        return new self($slug, $id, $count);
    }

    /**
     * Create a new instance of AnalyticRecord from a WP_Post object.
     *
     * @param WP_Post $post The WP_Post object to create the record from.
     * @param int $count The count value to set for the new instance.
     * @return self A new instance of AnalyticRecord created from the WP_Post object.
     */
    public static function fromPost(WP_Post $post, int $count): self
    {
        $record = new self($post->post_name, $post->ID, $count);
        $record->post = $post;
        return $record;
    }

    /**
     * Create a new instance of AnalyticRecord with the specified count.
     *
     * @param int $count The count value to set for the new instance.
     * @return self A new instance of AnalyticRecord with the specified count.
     */
    public function withCount(int $count): self
    {
        $new = clone $this;
        $new->{'count'} = $count;
        return $new;
    }

    /**
     * @return FeatureImage
     */
    public function getFeatureImage(): FeatureImage
    {
        if (!isset($this->featureImage)) {
            $this->featureImage = new FeatureImage($this->getPost());
        }
        return $this->featureImage;
    }

    /**
     * Get the feature image ID of the post.
     *
     * @return ?int returns the feature image ID or null if not found
     */
    public function getFeatureImageId(): ?int
    {
        if ($this->valid()) {
            return $this->getFeatureImage()->getFeatureImageId();
        }
        return null;
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
        $id = $this->getFeatureImageId();
        if (!$id) {
            return null;
        }
        return $this->getFeatureImage()->getThumbnail($size);
    }

    /**
     * Get the slug of the post.
     *
     * @return string returns the slug of the post
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the ID of the post.
     *
     * @return int returns the ID of the post
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get the WP_Post object associated with the record.
     *
     * @return ?WP_Post returns the WP_Post object or null if not found
     */
    public function getPost(): ?WP_Post
    {
        if (isset($this->post)) {
            return $this->post ?: null;
        }
        if ($this->id <= 0) {
            return null;
        }
        $this->mismatched_slug = true;
        $post = get_post($this->id);
        $this->post = false;
        if ($post instanceof WP_Post) {
            $this->post = $post;
            if ($this->slug === $this->post->post_name) {
                $this->mismatched_slug = false;
            } elseif (trim($this->slug, '/') === $this->post->post_name) {
                $this->mismatched_slug = false;
            } else {
                $this->mismatched_slug = true;
            }
        }
        return $this->post?:null;
    }

    /**
     * Get the count associated with the record.
     *
     * @return int returns the count value
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Check if the record is valid.
     *
     * @return bool returns true if the record is valid, false otherwise
     */
    public function valid(): bool
    {
        if ($this->count < 0 || $this->id <= 0) {
            return false;
        }
        $post = $this->getPost();
        return $post && $post->ID === $this->id && !$this->mismatched_slug;
    }

    /**
     * @return array{
     *     id: int,
     *     slug: string,
     *     count: int
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'count' => $this->count
        ];
    }

    /**
     * Serialize the record to a string.
     *
     * @return string returns the serialized string representation of the record
     */
    public function serialize(): string
    {
        return serialize($this->toArray());
    }

    /**
     * Unserialize the record from a string.
     *
     * @param string $data the serialized string representation of the record
     * @return void
     */
    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    /**
     * Serialize the record to an array.
     *
     * @return array{
     *     id: int,
     *     slug: string,
     *     count: int
     * } returns the array representation of the record
     */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array{
     *     id: int,
     *     slug: string,
     *     count: int
     * } $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $this->id = $data['id'];
        $this->slug = $data['slug'];
        $this->count = $data['count'];
    }

    /**
     * Serialize the record to a JSON-serializable array.
     *
     * @return array{
     *      id: int,
     *      slug: string,
     *      count: int
     *  } returns the JSON-serializable array representation of the record
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the count associated with the record.
     *
     * @return int returns the count value
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Get the string representation of the record.
     *
     * @return string returns the slug of the record
     */
    public function __toString(): string
    {
        return $this->slug;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'slug' => $this->getSlug(),
            'id' => $this->getId(),
            'count' => $this->getCount(),
            'post' => $this->getPost(),
            'featureImage' => $this->getFeatureImage(),
            default => null,
        };
    }

    /**
     * Prevent setting properties directly.
     *
     * @param string $name the name of the property
     * @param mixed $value the value to set
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        // void
        if ($name === 'count' && is_int($value)) {
            $this->count = $value;
        }
    }

    /**
     * Prevent unsetting properties directly.
     *
     * @param string $name the name of the property
     * @return void
     */
    public function __unset(string $name): void
    {
        // void
    }

    /**
     * Check if an offset exists in the record.
     *
     * @param mixed $offset the offset to check
     * @return bool returns true if the offset exists, false otherwise
     */

    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }
        return match ($offset) {
            'id', 'post', 'featureImage', 'count', 'slug' => true,
            default => false
        };
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_string($offset) ? $this->__get($offset) : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset)) {
            $this->__set($offset, $value);
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        // void
    }
}
