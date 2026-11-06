<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Components\Collector\Analytics\Providers;

use Google\Site_Kit\Core\Modules\Modules;
use Google\Site_Kit\Modules\Analytics_4;
use Google\Site_Kit\Plugin;
use Google\Site_Kit_Dependencies\Google\Service\AnalyticsData\RunReportResponse;
use Throwable;
use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\AbstractsAnalytics;
use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\AnalyticRecord;
use TrayDigita\WP\Stack\Source\Components\Collector\Analytics\AnalyticRecords;
use TrayDigita\WP\Stack\Source\Exceptions\RuntimeException;
use function array_keys;
use function class_exists;
use function count;
use function date;
use function doing_action;
use function explode;
use function get_posts;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function is_wp_error;
use function strtotime;
use function time;
use function trim;

class SiteKitGoogle extends AbstractsAnalytics
{
    public const string OPTION_NAME = 'traydigita_sitekit_analytics';

    public const int RESULT_NUM = 15;

    private static bool $siteKitReady = false;

    private Analytics_4 $analytics_4;

    /**
     * @var array{
     *      metrics: array<array{name: string}>,
     *      dimensions: array<array{name: string}>,
     *      startDate: string,
     *      endDate: string,
     *      orderby: array<array-key, array{metric: array{metricName: string}, desc: bool}>,
     *      limit: int,
     *      offset: int
     *  } $args
     */
    private array $args;

    private ?string $error = null;

    private ?Throwable $throwable = null;

    private function getAnalyticsObject(): ?Analytics_4
    {
        if (!self::isSIteKitReady()) {
            return null;
        }
        if (isset($this->analytics_4)) {
            return $this->analytics_4;
        }
        try {
            $plugin = Plugin::instance();
            $context = $plugin->context();
            $modules = new Modules($context);
            $analytics = $modules->get_module(Analytics_4::MODULE_SLUG);
            if (!$analytics instanceof Analytics_4
                || !$analytics->is_connected()
            ) {
                $this->throwable = new RuntimeException(
                    'Analytics 4 is not connected'
                );
                $this->error = self::ERR_NOT_CONNECTED;
                return null;
            }
            return $this->analytics_4 = $analytics;
        } catch (Throwable $e) {
            $this->throwable = $e;
            $this->error = self::ERR_NOT_READY;
            return null;
        }
    }

    /**
     * @return Throwable|null
     */
    public function getThrowable(): ?Throwable
    {
        return $this->throwable;
    }

    /**
     * @return array{
     *     metrics: array<array{name: string}>,
     *     dimensions: array<array{name: string}>,
     *     startDate: string,
     *     endDate: string,
     *     orderby: array<array-key, array{metric: array{metricName: string}, desc: bool}>,
     *     limit: int,
     *     offset: int
     * }
     */
    private function getAnalyticsArgs(): array
    {
        return $this->args ??= [
            'metrics' => [
                ['name' => 'screenPageViews']
            ],
            'dimensions' => [
                ['name' => 'pagePath'],
            ],
            'startDate' => date('Y-m-d', strtotime('-7 days')),
            'endDate' => date('Y-m-d', strtotime('-1 day')),
            'orderby' => [
                [
                    'metric' => ['metricName' => 'screenPageViews'],
                    'desc' => true,
                ],
            ],
            //'keepEmptyRows' => true,
            'limit' => 100,
            'offset' => 0,
        ];
    }

    public static function isSIteKitReady(): bool
    {
        if (self::$siteKitReady) {
            return true;
        }
        if (doing_action('after_setup_theme')) {
            return false;
        }
        return self::$siteKitReady = class_exists(Plugin::class)
            && class_exists(Analytics_4::class)
            && class_exists(RunReportResponse::class)
            && class_exists(Modules::class);
    }

    /**
     * @param AnalyticRecords $data
     * @return void
     */
    private function setData(AnalyticRecords $data): void
    {
        $this->site->options->set(self::OPTION_NAME, $data);
    }

    private AnalyticRecords $records;

    /**
     * @return AnalyticRecords
     * @throws Throwable
     */
    public function getRecords(): AnalyticRecords
    {
        if (isset($this->records)) {
            return $this->records;
        }
        $option = $this->site->options->get(self::OPTION_NAME);
        if (!$option instanceof AnalyticRecords) {
            $option = $this->getForceRecords();
            if (!$option instanceof AnalyticRecords) {
                $err = $this->getThrowable();
                if ($err) {
                    throw new $err;
                }
                throw new RuntimeException(
                    'Can not get record'
                );
            }
            if (!isset($this->records)) {
                $this->setData($option);
            }
            $this->records = $option;
            return $this->records;
        }
        $this->records = $option;
        if ($this->records->isExpire()) {
            $data = $this->getForceRecords();
            if ($data instanceof AnalyticRecords) {
                $this->records = $data->mergeWith($option, self::RESULT_NUM);
            }
        }
        return $this->records;
    }

    /**
     * @return string|AnalyticRecords
     */
    public function getForceRecords(): string|AnalyticRecords
    {
        if (!self::isSIteKitReady()) {
            return self::ERR_NOT_READY;
        }
        $analytics = $this->getAnalyticsObject();
        if (!$analytics) {
            if ($this->error) {
                return $this->error;
            }
            return self::ERR_NOT_READY;
        }
        try {
            $settings = $analytics->get_settings()->get();
            if (!is_array($settings)) {
                $this->throwable = new RuntimeException(
                    'Can not get settings for analytics'
                );
                return self::ERR_NOT_READY;
            }
            $propertyId = $settings['propertyID'] ?? null;
            $propertyId = is_scalar($propertyId) ? (string)$propertyId : null;
            if (!is_string($propertyId)) {
                $this->throwable = new RuntimeException(
                    'Can not get property id for analytics'
                );
                return self::ERR_NOT_READY;
            }
            $response = $analytics->get_data('report', $this->getAnalyticsArgs());
            if (is_wp_error($response)) {
                $this->throwable = new RuntimeException(
                    $response->get_error_message()
                );
                return self::ERR_NO_RECORDS;
            }
            if ($response instanceof RunReportResponse) {
                $rows = $response->getRows();
                $paths = [];
                foreach ($rows as $row) {
                    $pathObj = $row->getDimensionValues()[0] ?? null;
                    $metrics = $row->getMetricValues()[0] ?? null;
                    if (!$pathObj || !$metrics) {
                        continue;
                    }
                    $path = $pathObj->getValue();
                    $metric = $metrics->getValue();
                    if (!is_string($path)) {
                        continue;
                    }
                    $path = trim($path, '/');
                    if ($path === '' || str_starts_with($path, '^page/')) {
                        continue;
                    }
                    // Get rid of the #anchor.
                    $path = explode('#', $path)[0];
                    $path = explode('?', $path)[0];
                    $metric = is_string($metric) ? trim($metric) : $metric;
                    $metric = !is_numeric($metric) ? 0 : (int)$metric;
                    $paths[$path] = $metric;
                }
                if (empty($paths)) {
                    return new AnalyticRecords($propertyId, time());
                }
                $posts = get_posts([
                    'post_name__in' => array_keys($paths),
                    'post_type' => 'any',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                ]);
                $maps = [];
                foreach ($posts as $post) {
                    $count = $paths[$post->post_name] ?? 0;
                    $map = AnalyticRecord::fromPost($post, $count);
                    $maps[] = $map;
                }
                unset($paths, $posts);
                $return = new AnalyticRecords(
                    $propertyId,
                    time(),
                    ...$maps
                );
                if (count($maps) >= 15) {
                    $this->records = $return;
                    $this->setData($return);
                }
                return $return;
            }
        } catch (Throwable $e) {
            $this->throwable = $e;
            return self::ERR_NO_RECORDS;
        }
        $this->throwable = new RuntimeException(
            'Can not get analytics data'
        );
        return self::ERR_NO_RECORDS;
    }
}
