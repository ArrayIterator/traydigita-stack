<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Collector\Analytics\Providers;

use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\AbstractsAnalytics;
use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\AnalyticRecord;
use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\AnalyticRecords;
use TrayDigita\WP\Stack\Source\Libs\Helpers\OutputBuffer;
use TrayDigita\WP\Stack\Source\Libs\Helpers\RawParams;
use TrayDigita\WP\Stack\Source\Libs\Resolver\PostUtil;
use WP_Post;
use function add_action;
use function add_rewrite_rule;
use function add_rewrite_tag;
use function array_all;
use function array_keys;
use function current_user_can;
use function date;
use function did_action;
use function doing_action;
use function explode;
use function flush_rewrite_rules;
use function get_post;
use function get_query_var;
use function hash_hmac;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function min;
use function parse_url;
use function sprintf;
use function str_contains;
use function time;
use function wp_json_encode;
use function wp_send_json;
use const JSON_UNESCAPED_SLASHES;
use const PHP_URL_HOST;

class SiteAnalytics extends AbstractsAnalytics
{
    public const string VERSION = '1.0.0';

    /**
     * The header name for tracking post views.
     * @var string
     */
    public const string HEADER_X_REQUESTED_FOR = 'tracker';

    /**
     * The cookie name for tracking post views.
     * @var string
     */
    public const string COOKIE_NAME = 'traydigita_track_views';

    public const string TABLE_NAME = 'traydigita_views_counter';

    public const string OPTION_NAME = 'traydigita_site_analytics';

    public const string TRANSIENT_NAME = 'analytics_db_version';

    /**
     * The rewrite tag for tracking post views.
     * @var string
     */
    public const string REWRITE_TAG_QUERY = 'traydigita_stack_track';

    /**
     * @var string The slug used for tracking post views in the URL.
     */
    public const string TRACK_SLUG = 'traydigita_stack_track/track';

    /**
     * The rewrite slug for tracking post views.
     * @var string
     */
    public const string REWRITE_SLUG = '^' . self::TRACK_SLUG . '/([0-9]+)/?$';

    /**
     * The rewrite query for tracking post views.
     * @var string
     */
    public const string REWRITE_QUERY = 'index.php?' . self::REWRITE_TAG_QUERY . '=$matches[1]';

    /**
     * The position of the rewrite rule in the WordPress rewrite rules array.
     * @var string
     */
    public const string REWRITE_POSITION = 'top';

    public const int CACHE_TIME = 300;

    public const int MAX_LIMIT = 200;

    private AnalyticRecords $records;

    /**
     * @var string
     */
    private string $cookieName = self::COOKIE_NAME;

    /**
     * @var string
     */
    private string $tableName;

    /**
     * @param string $name The name of the cookie to set for tracking post views.
     */
    public function setCookieName(string $name): void
    {
        $this->cookieName = $name;
    }

    /**
     * @return string The name of the cookie used for tracking post views.
     */
    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    /**
     * @inheritdoc
     */
    public function isRewriteSupport(): bool
    {
        return true;
    }

    /**
     * Get the table name for storing analytics data.
     */
    public function getTableName(): string
    {
        return $this->tableName ??= $this->database->prefixIt(self::TABLE_NAME);
    }

    /**
     * @var bool $headRendered determines if the wp_head action has been rendered to avoid duplicate output.
     */
    private bool $headRendered = false;

    /**
     * Output the necessary JavaScript for tracking post views in the wp_head action.
     */
    public function wpHeadHooks(): void
    {
        if (!did_action('wp_head')) {
            add_action('wp_head', [$this, 'wpHeadHooks']);
            return;
        }
        if ($this->headRendered) {
            return;
        }
        if (!doing_action('wp_head')) {
            return;
        }
        $this->headRendered = true;
        $json = wp_json_encode([
            'cookie' => $this->getCookieName(),
            'url' => sprintf('/%s', self::TRACK_SLUG),
            'header' => self::HEADER_X_REQUESTED_FOR,
        ], JSON_UNESCAPED_SLASHES);
        echo <<<HTML
<script data-traydigita-analytics>
(function (w) {
    w.__traydigita_analytics = $json;
})(window);
</script>
HTML;
    }

    /**
     * @var bool
     */
    private bool $doingRewrite = false;

    /**
     * Do the rewrite rules for tracking post views.
     * @return void
     */
    public function rewriteHooks(): void
    {
        if (!did_action('init')) {
            add_action('init', [$this, 'rewriteHooks']);
            return;
        }
        if ($this->doingRewrite) {
            return;
        }
        $this->doingRewrite = true;
        add_rewrite_tag('%' . self::REWRITE_TAG_QUERY . '%', '([^&]+)');
        add_rewrite_rule(
            self::REWRITE_SLUG,
            self::REWRITE_QUERY,
            self::REWRITE_POSITION
        );
        $trackViewName = sprintf(
            "%s:%s:%s:%s:%s",
            self::REWRITE_TAG_QUERY,
            self::REWRITE_SLUG,
            self::REWRITE_QUERY,
            self::REWRITE_POSITION,
            self::VERSION
        );

        $transient = $this->site->transients->get(self::TRANSIENT_NAME, '');
        if ($transient !== $trackViewName) {
            flush_rewrite_rules();
            $this->site->transients->set(self::TRANSIENT_NAME, $trackViewName, 3600);
        }
    }

    /**
     * Handle the template redirect for tracking post views.
     */
    public function templateRedirectHooks(): void
    {
        if (!did_action('template_redirect')) {
            add_action('template_redirect', [$this, 'templateRedirectHooks'], -999999);
            return;
        }
        $post_id = get_query_var(self::REWRITE_TAG_QUERY);
        if (!is_numeric($post_id) || (int)$post_id === 0) {
            return;
        }
        if (OutputBuffer::clear()) {
            exit;
        }
        $cookie_name = $this->getCookieName();
        $referer = RawParams::referer();
        $cookieValue = RawParams::cookie($cookie_name);
        $requestedFor = RawParams::header('x-requested-for');
        $header_track = RawParams::header('x-authorize-track');
        // $base64_string =
        // "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=";
        if (!$referer
            || $requestedFor !== self::HEADER_X_REQUESTED_FOR
            || !$header_track
            || !is_string($cookieValue)
            || !is_numeric($cookieValue)
            || str_contains($cookieValue, '.')
            || ((((int)$cookieValue) / 1000) < time() - 3600)
            || parse_url($referer, PHP_URL_HOST) !== $this->site->getHostname()
        ) {
            wp_send_json([
                'error' => self::ERR_INVALID_PAYLOAD,
                'code' => 400
            ], 200);
        }
        // authenticate
        if (!$this->userAgent->isMaybeHumanBrowser()) {
            wp_send_json([
                'error' => self::ERR_BOT_DETECT,
                'code' => 400
            ], 200);
        }
        $user_agent = $this->userAgent->getUserAgent();
        $hmac = hash_hmac('sha1', "$post_id:$user_agent", $cookieValue);
        $valid_integrity = $hmac === $header_track;
        if (!$valid_integrity) {
            wp_send_json([
                'error' => self::ERR_INVALID_INTEGRITY,
                'code' => 400
            ], 200);
        }

        $post_id = (int) $post_id;
        $post = get_post($post_id);
        $permalink = $post ? PostUtil::permalink($post) : null;
        if (!$permalink || !PostUtil::isSingular($post)) {
            wp_send_json([
                'error' => self::ERR_INVALID_TRACK_ID,
                'code' => 400
            ], 200);
        }
        if ($referer !== $permalink) {
            if (str_contains($referer, '#')) {
                $referer = explode('#', $referer)[0];
            }
        }
        $ref = str_contains($referer, '?') ? explode('?', $referer)[0] : $referer;
        $permalink = str_contains($permalink, '?') ? explode('?', $permalink)[0] : $permalink;
        if (($is_permalink = $ref !== $permalink) || current_user_can('edit_posts')) {
            wp_send_json([
                'error' => $is_permalink ? self::ERR_INVALID_REFERER : self::ERR_INVALID_PERMISSION,
                'code' => 400
            ], 200);
        }
        $this->addView($post);
        wp_send_json([
            'id' => $post->ID,
            'code' => 200
        ], 200);
    }

    /**
     * Perform the necessary rewrite actions for tracking post views.
     */
    protected function doRewrite(): void
    {
        $this->wpHeadHooks();
        $this->rewriteHooks();
        $this->templateRedirectHooks();
    }

    /**
     * @param int|WP_Post $post
     * @return void
     */
    public function addView(int|WP_Post $post): void
    {
        $post = get_post($post);
        if (!$post) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }
        $view_date = date('Y-m-d');
        $post_id = $post->ID;
        $table_name = $this->database->quoteIdentifier($this->getTableName());
        $this->database->query(
            "INSERT INTO $table_name (`post_id`, `view_date`, `view_count`)
                    VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE `view_count` = `view_count` + 1",
            $post_id,
            $view_date
        );
    }

    /**
     * @param int $days
     * @param int $limit
     * @param bool $force
     * @return array<int, int>
     */
    public function getPopularPostIdsFromDaysInterval(
        int $days = 30,
        int $limit = 30,
        bool $force = false
    ): array {
        if ($days < 1) {
            return [];
        }
        $limit = min($limit, self::MAX_LIMIT);
        if ($limit < 1) {
            return [];
        }
        $cache_key = 'langgam_popular_posts_[' . $days . '_' . $limit . ']';
        $cached_data = $this->site->transients->get($cache_key);
        if (!$force
            && is_array($cached_data)
            && array_all($cached_data, static fn($k, $v) => is_int($k) && is_int($v))
        ) {
            return $cached_data;
        }
        $this->createTable();
        $table_name = $this->getTableName();
        $table_post = $this->database->getTablePosts();
        $results = $this->database->getResults(
            "SELECT post_id, SUM(view_count) as total_views
                        FROM $table_name as popular
                        INNER JOIN $table_post as post
                            ON (
                                post.ID = popular.post_id
                                AND post.post_type = 'post'
                                AND post.post_status = 'publish'
                            )
                       WHERE
                           view_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                        GROUP BY post_id
                        ORDER BY total_views DESC LIMIT %d",
            true,
            $days,
            $limit
        );
        if (!is_array($results) || empty($results)) {
            if (!$this->database->getLastError()) {
                $this->site->transients->set($cache_key, [], self::CACHE_TIME);
            }
            return [];
        }
        $post_ids = [];
        /**
         * @var array{post_id: int, total_views: int} $result
         */
        foreach ($results as $result) {
            $post_id = isset($result['post_id']) ? (int) $result['post_id'] : null;
            if (!$post_id) {
                continue;
            }
            $total = (int) $result['total_views'];
            $post_ids[$post_id] = $total;
        }
        $this->site->transients->set($cache_key, $post_ids, self::CACHE_TIME);
        return $post_ids;
    }

    /**
     * @param int $days
     * @param int $limit
     * @param bool $force
     * @return array<int, array{post: WP_Post, count: int}>
     */
    public function getPopularPostsFromDaysInterval(
        int $days = 30,
        int $limit = 30,
        bool $force = false
    ): array {
        $this->createTable();
        $post_ids = $this->getPopularPostIdsFromDaysInterval($days, $limit, $force);
        $posts = [];
        foreach (PostUtil::find([
            'include' => array_keys($post_ids),
            'post_type' => 'post',
            'post_status' => 'publish',
            'limit' => -1,
        ]) as $post) {
            $posts[$post->ID] = [
                'post' => $post,
                'count' => (int)($post_ids[$post->ID] ?? 0)
            ];
        }
        return $posts;
    }

    /**
     * @var bool
     */
    private bool $tableCreated = false;

    /**
     * Create the table for storing analytics data if it doesn't exist.
     */
    private function createTable(): void
    {
        if ($this->tableCreated) {
            return;
        }
        $this->tableCreated = true;
        $table_name = $this->getTableName();
        if (!$this->database->hasTable($table_name)) {
            $charset_collate = $this->database->getCharsetCollate();
            $sql = "
CREATE TABLE IF NOT EXISTS $table_name (
    `post_id` bigint(20) NOT NULL,
    `view_date` date NOT NULL,
    `view_count` int(11) DEFAULT 0 NOT NULL,
    PRIMARY KEY (`post_id`, `view_date`),
    INDEX `idx_date_count` (`view_date`, `view_count`)
) $charset_collate;";
            $this->database->executeQuery($sql);
        }
    }

    /**
     * @return AnalyticRecords
     */
    public function getRecords(): AnalyticRecords
    {
        if (isset($this->records)) {
            return $this->records;
        }
        // store the records in the option for caching
        $records = $this->site->options->get(self::OPTION_NAME);
        if (!$records instanceof AnalyticRecords || $records->isExpire(1)) {
            $posts = $this->getPopularPostsFromDaysInterval(7, 100, true);
            $maps = [];
            foreach ($posts as $data) {
                ['post' => $post, 'count' => $count] = $data;
                $map = AnalyticRecord::fromPost($post, $count);
                $maps[] = $map;
            }
            unset($posts);
            $records = new AnalyticRecords(
                'local',
                time(),
                ...$maps
            );
            $this->site->options->set(self::OPTION_NAME, $records);
        }
        $this->records = $records;
        return $this->records;
    }
}
