<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Collector\Analytics;

use TrayDigita\WP\Stack\Source\Components\Data\Site;
use TrayDigita\WP\Stack\Source\Components\Database\Database;
use TrayDigita\WP\Stack\Source\Components\Networks\UserAgent;

abstract class AbstractsAnalytics
{
    public const string ERR_INVALID_PAYLOAD = 'INVALID_PAYLOAD';

    public const string ERR_BOT_DETECT = 'BOT_DETECT';

    public const string ERR_INVALID_INTEGRITY = 'INVALID_INTEGRITY';

    public const string ERR_INVALID_TRACK_ID = 'INVALID_TRACK_ID';

    public const string ERR_INVALID_REFERER = 'INVALID_REFERER';

    public const string ERR_INVALID_PERMISSION = 'INVALID_PERMISSION';

    // sitekit?
    public const string ERR_NOT_READY = 'NOT_READY';

    public const string ERR_NOT_CONNECTED = 'NOT_CONNECTED';

    public const string ERR_NO_INSTALLED = 'NOT_INSTALLED';

    public const string ERR_NO_RECORDS = 'NO_RECORDS';

    /**
     * @var Site The Site instance representing the site for which analytics data is being collected.
     */
    public readonly Site $site;

    /**
     * The WPDB instance for database interactions.
     *
     * @var Database
     */
    protected Database $database;

    /**
     * The UserAgent instance for user agent detection.
     *
     * @var UserAgent
     */
    protected UserAgent $userAgent;

    /**
     * Indicates whether to rewrite the analytics data.
     *
     * @var bool
     */
    private bool $rewrite = false;

    /**
     * AbstractsAnalytics constructor.
     *
     * @param Site $site The Site instance representing the site for which analytics data is being collected.
     * @param Database $database The WPDB instance for database interactions.
     * @param UserAgent|null $userAgent The UserAgent instance for user agent detection.
     */
    public function __construct(
        Database $database,
        Site $site,
        ?UserAgent $userAgent = null
    ) {
        $this->site = $site;
        $this->database = $database;
        $this->userAgent = $userAgent ?? new UserAgent();
    }

    /**
     * Check if the analytics data supports rewriting.
     *
     * @return bool True if rewriting is supported, false otherwise.
     */
    public function isRewriteSupport(): bool
    {
        return false;
    }

    /**
     * Rewrite the analytics data if not already rewritten.
     *
     * This method checks if the analytics data has already been rewritten. If not, it sets the
     * rewrite flag to true and calls the doRewrite() method to perform the actual rewriting of
     * analytics data.
     */
    public function rewrite(): void
    {
        if ($this->rewrite) {
            return;
        }
        if (!$this->isRewriteSupport()) {
            return;
        }
        $this->rewrite = true;
        $this->doRewrite();
    }

    /**
     * Perform the actual rewriting of analytics data.
     *
     * This method is intended to be overridden by subclasses to implement the specific logic
     * for rewriting analytics data. By default, it does nothing.
     */
    protected function doRewrite() // @phpstan-ignore-line
    {
    }

    /**
     * Get the analytic records.
     *
     * @return AnalyticRecords The analytic records.
     */
    abstract public function getRecords(): AnalyticRecords;
}
