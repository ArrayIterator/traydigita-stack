<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Collector;

use Throwable;
use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\AnalyticRecord;
use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\AnalyticRecords;
use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\Providers\SiteAnalytics;
use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\Providers\SiteKitGoogle;
use TrayDigita\WP\Stack\Source\Components\Data\Site;
use TrayDigita\WP\Stack\Source\Components\Database\Database;
use TrayDigita\WP\Stack\Source\Libs\Resolver\PostUtil;
use function count;
use function time;

final class PopularPosts
{
    /**
     * @var Database The Database instance for database interactions.
     */
    public readonly Database $database;

    /**
     * @var Site The Site instance representing the site for which popular posts are being collected.
     */
    public readonly Site $site;

    /**
     * @var AnalyticRecords The analytic records containing popular posts.
     */
    private AnalyticRecords $records;

    /**
     * @var SiteKitGoogle The SiteKitGoogle instance for collecting analytics data from Google Site Kit.
     */
    private SiteKitGoogle $siteKitGoogle;

    /**
     * @var SiteAnalytics The SiteAnalytics instance for collecting analytics data from the site.
     */
    private SiteAnalytics $siteAnalytics;

    /**
     * @var bool Indicates whether the PopularPosts instance has been initialized.
     */
    private bool $initialized = false;

    /**
     * PopularPosts constructor.
     *
     * @param Database $database The Database instance for database interactions.
     * @param Site $site The Site instance representing the site for which popular posts are being collected.
     */
    private function __construct(
        Database $database,
        Site $site
    ) {
        $this->database = $database;
        $this->site = $site;
    }

    /**
     * Set up the PopularPosts instance by initializing necessary components.
     *
     * @return void
     */
    public function setup() : void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;
        if (!SiteKitGoogle::isSIteKitReady()) {
            $this->getSiteAnalytics()->rewrite();
        }
    }

    /**
     * Get the siteKitGoogle instance for collecting analytics data from Google Site Kit.
     * @return SiteKitGoogle
     */
    public function getSiteKit(): SiteKitGoogle
    {
        return $this->siteKitGoogle ??= new SiteKitGoogle($this->database, $this->site);
    }

    /**
     * Get the SiteAnalytics instance for collecting analytics data from the site.
     *
     * @return SiteAnalytics The SiteAnalytics instance.
     */
    public function getSiteAnalytics(): SiteAnalytics
    {
        return $this->siteAnalytics ??= new SiteAnalytics($this->database, $this->site);
    }

    /**
     * Get the analytic records containing popular posts.
     *
     * @return AnalyticRecords The analytic records containing popular posts.
     */
    public function getPopularPosts(): AnalyticRecords
    {
        if (isset($this->records)) {
            return $this->records;
        }
        try {
            $records = $this->getSiteKit()->getRecords();
            if (count($records) >= 15) {
                return $this->records = $records;
            }
            $additional = $this->getSiteAnalytics()->getRecords();
            $records = $records->mergeWith($additional);
        } catch (Throwable) {
            try {
                $records = $this->getSiteAnalytics()->getRecords();
            } catch (Throwable) {
                $records = new AnalyticRecords('local', time());
            }
        }
        if (count($records) >= 15) {
            return $this->records = $records;
        }
        /**
         * @var int[] $post_ids
         */
        $post_ids = [];
        foreach ($records as $rec) {
            $post_ids[] = $rec->id;
        }
        $count = 15 - count($post_ids);
        if ($count <= 0) {
            return $this->records;
        }
        $maps = [];
        foreach (PostUtil::find([
            'exclude' => $post_ids,
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => $count,
        ]) as $post) {
            $maps[] = AnalyticRecord::fromPost($post, 0);
        }
        $records = $records->mergeWith(
            new AnalyticRecords('local', time(), ...$maps)
        );
        return $this->records = $records;
    }
}
