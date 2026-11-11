<?php
declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols,PSR1.Methods.CamelCapsMethodName.NotCamelCaps
namespace TrayDigita\WP\Stack\Source\Libs\Messaging;

use TrayDigita\WP\Stack\Source\Components\Database\Builder\CompositeExpression;
use TrayDigita\WP\Stack\Source\Components\Database\Builder\Expression;
use TrayDigita\WP\Stack\Source\Libs\Helpers\RawParams;
use TrayDigita\WP\Stack\Source\Libs\Messaging\Records\HistoryList;
use TrayDigita\WP\Stack\Source\Stack;
use WP_Screen;
use function absint;
use function add_query_arg;
use function ceil;
use function class_exists;
use function esc_attr;
use function esc_html;
use function esc_url;
use function get_current_user_id;
use function get_user_meta;
use function in_array;
use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function max;
use function min;
use function remove_query_arg;
use function sanitize_text_field;
use function sprintf;
use function strlen;
use function strtoupper;
use function substr;
use function trim;
use function wp_strip_all_tags;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

abstract class WordPressTableView extends \WP_List_Table
{
    /**
     * @var array<string, string> $titles
     * The titles for the columns in the table view.
     */
    protected array $titles = [
        'id' => 'ID',
        'uuid' => 'UUID',
        'title' => 'Title',
        'message' => 'Message',
        'messenger_id' => 'Sender Id',
        'target_url' => 'Target URL',
        'status' => 'Status',
        'response_data' => 'Response',
        'created_at' => 'Created At'
    ];

    /**
     * @var array<string, string> $descriptions
     * The descriptions for the columns in the table view.
     */
    protected array $descriptions = [
        'id' => 'The unique identifier for the push notification.',
        'uuid' => 'The unique universal identifier associated with the transaction.',
        'title' => 'The title or subject of the push notification.',
        'message' => 'The body content or payload message of the notification.',
        'messenger_id' => 'The unique identifier of the messenger sender.',
        'target_url' => 'The destination link opened when the notification is clicked.',
        'status' => 'The delivery status (e.g., success or failed).',
        'response_data' => 'The raw JSON response data returned from the Firebase server.',
        'created_at' => 'The exact timestamp when the notification record was created.'
    ];

    /**
     * @var array<string, string> $actionLabels
     * The labels for the status filter in the table view.
     */
    protected array $actionLabels = [
        'success' => 'Success',
        'failed' => 'Failed',
        'all' => 'All',
        'open_link' => 'Open Link',
        'delete' => 'Delete'
    ];

    /**
     * @var HistoryList $historyList
     * The history list instance.
     */
    protected HistoryList $historyList;

    public const string FCM_POST_NAME = 'fcm_history';

    public function __construct(
        ?HistoryList $historyList = null,
        ?string $screen = null,
        string $singular = 'fcm_history',
        string $plural = 'fcm_histories'
    ) {
        global $page_hook;
        $this->historyList = $historyList ?? Stack::getInstance()->make(HistoryList::class) ?? new HistoryList(
            Stack::getInstance()->getDatabase()
        );
        parent::__construct([
            'singular' => $singular,
            'plural' => $plural,
            'ajax' => false,
            'screen' => $screen ?: $page_hook,
        ]);
    }

    /**
     * Get the title for a given column identifier.
     *
     * @param string $identifier The column identifier.
     * @param ?string $default The default title to return if the identifier is not found.
     * @return string The title for the specified column identifier or the default title.
     */
    public function getTitleOf(string $identifier, ?string $default = null): string
    {
        return $this->titles[$identifier] ?? $default ?? $identifier;
    }

    /**
     * Set the title for a given column identifier.
     *
     * @param string $identifier The column identifier.
     * @param string $title The title to set for the specified column identifier.
     */
    public function setTitle(string $identifier, string $title): void
    {
        $this->titles[$identifier] = $title;
    }

    /**
     * Set multiple titles for columns.
     *
     * @param array<string, string> $titles An associative array of column identifiers and their corresponding titles.
     */
    public function setTitles(array $titles): void
    {
        foreach ($titles as $identifier => $title) {
            if (is_string($identifier) && is_string($title)) {
                $this->setTitle($identifier, $title);
            }
        }
    }

    /**
     * Get the titles for all columns.
     *
     * @return array<string, string> The titles for all columns.
     */
    public function getTitles(): array
    {
        return $this->titles;
    }

    public function getDescriptionOf(string $identifier, ?string $default = null): string
    {
        return $this->descriptions[$identifier] ?? $default ?? '';
    }

    /**
     * @return array<string, string>
     * The descriptions for all columns.
     */
    public function getDescriptions() : array
    {
        return $this->descriptions;
    }

    /**
     * Set the description for a given column identifier.
     *
     * @param string $identifier The column identifier.
     * @param string $description The description to set for the specified column identifier.
     */
    public function setDescription(string $identifier, string $description): void
    {
        $this->descriptions[$identifier] = $description;
    }

    /**
     * Set multiple descriptions for columns.
     *
     * @param array<string, string> $descriptions
     * An associative array of column identifiers and their corresponding descriptions.
     */
    public function setDescriptions(array $descriptions): void
    {
        foreach ($descriptions as $identifier => $description) {
            if (is_string($identifier) && is_string($description)) {
                $this->setDescription($identifier, $description);
            }
        }
    }

    /**
     * Get the title for a given status.
     *
     * @param string $status The status identifier.
     * @param ?string $default The default title to return if the status is not found.
     * @return string The title for the specified status or the default title.
     */
    public function getActionLabel(string $status, ?string $default = null): string
    {
        return $this->actionLabels[$status] ?? $default ?? $status;
    }

    /**
     * Set the action label for a given status.
     *
     * @param string $status The status identifier.
     * @param string $label The label to set for the specified status.
     */
    public function setStatusLabel(string $status, string $label): void
    {
        $this->actionLabels[$status] = $label;
    }

    /**
     * Set the action labels for the status filter.
     *
     * @param array<string, string> $actionLabels The action labels for the status filter.
     */
    public function setActionLabels(array $actionLabels): void
    {
        foreach ($actionLabels as $status => $label) {
            if (is_string($status) && is_string($label)) {
                $this->setStatusLabel($status, $label);
            }
        }
    }

    /**
     * Get the action labels for the status filter.
     *
     * @return array<string, string> The action labels for the status filter.
     */
    public function getActionLabels(): array
    {
        return $this->actionLabels;
    }

    /**
     * Get the columns for the table view.
     *
     * @return array<string, string> The columns for the table view.
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'title' => $this->getTitleOf('title'),
            'message' => $this->getTitleOf('message'),
            'messenger_id' => $this->getTitleOf('messenger_id'),
            'target_url' => $this->getTitleOf('target_url'),
            'status' => $this->getTitleOf('status'),
            'response_data' => $this->getTitleOf('response_data'),
            'created_at' => $this->getTitleOf('created_at')
        ];
    }

    /**
     * @param mixed $data
     * @param WP_Screen|mixed $screen
     * @return array<string, mixed>
     */
    public function defaultHiddenColumns(mixed $data = null, mixed $screen = null): array
    {
        if (!is_array($data) || !$screen instanceof WP_Screen) {
            return $data;
        }
        if ($screen->id === $this->screen->id) {
            $data[] = 'id';
            $data[] = 'uuid';
            $data[] = 'response_data';
        }
        return $data;
    }

    /**
     * Get the screen definitions for the columns.
     *
     * @return array<string, string> The screen definitions.
     */
    public function columnsScreen(): array
    {
        $data = [
            'cb' => '<input type="checkbox" />',
        ];
        foreach ($this->columnsScreenDefinitions() as $key => $value) {
            $data[$key] = is_string($value) ? $value : $value['title'];
        }
        return $data;
    }

    /**
     * Get the screen definitions for the columns.
     *
     * @return array<string, array<string, string>> The screen definitions.
     */
    public function columnsScreenDefinitions(): array
    {
        return [
            'id' => [
                'title' => $this->getTitleOf('id'),
                'description' => $this->getDescriptionOf('id'),
            ],
            'title' => [
                'title' => $this->getTitleOf('title'),
                'description' => $this->getDescriptionOf('title'),
            ],
            'messenger_id' => [
                'title' => $this->getTitleOf('messenger_id'),
                'description' => $this->getDescriptionOf('messenger_id'),
            ],
            'uuid' => [
                'title' => $this->getTitleOf('uuid'),
                'description' => $this->getDescriptionOf('uuid'),
            ],
            'message' => [
                'title' => $this->getTitleOf('message'),
                'description' => $this->getDescriptionOf('message'),
            ],
            'target_url' => [
                'title' => $this->getTitleOf('target_url'),
                'description' => $this->getDescriptionOf('target_url'),
            ],
            'status' => [
                'title' => $this->getTitleOf('status'),
                'description' => $this->getDescriptionOf('status'),
            ],
            'response_data' => [
                'title' => $this->getTitleOf('response_data'),
                'description' => $this->getDescriptionOf('response_data'),
            ],
            'created_at' => [
                'title' => $this->getTitleOf('created_at'),
                'description' => $this->getDescriptionOf('created_at'),
            ],
        ];
    }

    protected function get_sortable_columns(): array
    {
        return [
            'id' => ['id', false],
            'title' => ['title', false],
            'uuid' => ['uuid', false],
            'status' => ['status', false],
            'messenger_id' => ['messenger_id', false],
            'target_url' => ['target_url', false],
            'created_at' => ['created_at', false],
        ];
    }

    /**
     * Render the checkbox column for each item.
     *
     * @param array<string, mixed> $item The current item being rendered.
     * @return string The HTML output for the checkbox column.
     */
    protected function column_cb($item) : string
    {
        $item = is_object($item) ? (array)$item : (is_array($item) ? $item : []);
        return sprintf(
            '<input type="checkbox" name="%s[delete][]" value="%s" data-uuid="%s">',
            esc_attr(self::FCM_POST_NAME),
            esc_attr((string)($item['id']??'')),
            $item['uuid'] ?? ''
        );
    }

    /**
     * @return array{
     *     success: string,
     *     failed: string,
     *     all: string,
     * }
     */
    protected function get_views(): array
    {
        $status_slug = RawParams::get('status', 'all');
        $metadata = $this->historyList->getMetadata();
        $total_success = $metadata['success'] ?? 0;
        $total_failed = $metadata['failed'] ?? 0;
        $total_all = $metadata['total'] ?? 0;
        $current_url = remove_query_arg(['status', 'paged']);

        /** @noinspection HtmlUnknownTarget */
        /** @noinspection HtmlUnknownAttribute */
        return [
            'all' => sprintf(
                '<a href="%s" %s>%s <span class="count">(%d)</span></a>',
                esc_url($current_url),
                $status_slug === 'all' ? 'class="current"' : '',
                $this->getActionLabel('all'),
                $total_all
            ),
            'success' => sprintf(
                '<a href="%s" %s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'success', $current_url)),
                $status_slug === 'success' ? 'class="current"' : '',
                $this->getActionLabel('success'),
                $total_success
            ),
            'failed' => sprintf(
                '<a href="%s" %s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('status', 'failed', $current_url)),
                $status_slug === 'failed' ? 'class="current"' : '',
                $this->getActionLabel('failed'),
                $total_failed
            ),
        ];
    }

    /**
     * Default column handler.
     *
     * @param array<string, mixed> $item
     * @param $column_name
     * @return string
     */
    protected function column_default($item, $column_name) : string
    {
        $item = is_object($item) ? (array)$item : (is_array($item) ? $item : []);
        switch ($column_name) {
            case 'status':
                $value = absint($item[$column_name] ?? 0);
                return $value ?
                    sprintf(
                        '<span class="dashicons dashicons-yes" style="color: green"></span> %s',
                        $this->getActionLabel('success')
                    ) :
                    sprintf(
                        '<span class="dashicons dashicons-no error-message"></span> %s',
                        $this->getActionLabel('failed')
                    );
            case 'id':
            case 'uuid':
            case 'messenger_id':
            case 'created_at':
                return isset($item[$column_name]) ? esc_html((string)$item[$column_name]) : '';
            case 'title':
            case 'message':
                $value = isset($item[$column_name]) ? wp_strip_all_tags($item[$column_name]) : '';
                $excerpt = substr($value, 0, 50);
                $excerpt .= strlen($value) > 50 ? '&hellip;' : '';
                return sprintf('<p title="%s">%s</p>', esc_attr($value), esc_html($excerpt));
            case 'target_url':
                $url = $item[$column_name] ?? '';
                if (!empty($url)) {
                    /** @noinspection HtmlUnknownTarget */
                    return sprintf(
                        '<a href="%1$s" target="_blank" title="%1$s">%2$s</a>',
                        esc_url($url),
                        esc_html(substr($url, 0, 50) . (strlen($url) > 50 ? '&hellip;' : ''))
                    );
                }
                return '-';
            case 'response_data':
                return sprintf(
                    '<pre style="overflow: auto">%s</pre>',
                    isset($item[$column_name]) ? esc_html((string)$item[$column_name]) : '-'
                );
            default:
                return isset($item[$column_name]) ? esc_html((string)$item[$column_name]) : '-';
        }
    }

    protected function get_bulk_actions(): array
    {
        return [
            'bulk-delete' => $this->getActionLabel('delete')
        ];
    }

    public function prepare_items(): void
    {
        $per_page = get_user_meta(get_current_user_id(), 'fcm_tab_history_per_page', true);
        $per_page = is_numeric($per_page) && $per_page > 0 ? (int)$per_page : 10;
        $per_page = max(1, min(100, $per_page));

        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        $s = is_string($_POST['s'] ?? null) ? $_POST['s'] : (is_string($_GET['s'] ?? null) ? $_GET['s'] : '');
        $search = trim(sanitize_text_field($s));
        $stat = is_string($_GET['status'] ?? null) ? sanitize_text_field($_GET['status']) : 'all';
        $status_filter = in_array($stat, ['success', 'failed'], true) ? $stat : 'all';
        $composite = CompositeExpression::createAnd();
        $expression = Expression::create();

        if (!empty($search)) {
            $search_composite = CompositeExpression::createOr();
            $search_composite->add($expression->like('title', '%' . $search . '%'))
                ->add($expression->like('message', '%' . $search . '%'))
                ->add($expression->like('messenger_id', '%' . $search . '%'))
                ->add($expression->like('uuid', '%' . $search . '%'));
            $composite->add($search_composite);
        }

        if ($status_filter === 'success') {
            $composite->add($expression->eq('status', 1));
        } elseif ($status_filter === 'failed') {
            $composite->add($expression->eq('status', 0));
        }

        $orderby = !empty($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'id';
        $order = !empty($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';

//        $columns = $this->get_columns();
//        $hidden = [];
//        $sortable = $this->get_sortable_columns();
//        $this->_column_headers = [$columns, $hidden, $sortable];

        $allowed_orderby = ['id', 'title', 'created_at', 'messenger_id', 'status', 'target_url', 'uuid'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'id';
        }

        $query = $this->historyList->findBy(
            $composite,
            $per_page,
            $offset,
            [
                $orderby => $order
            ]
        );

        $this->items = $query->fetchAll();
        $total_items = $query->getTotal();
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => (int)ceil($total_items / $per_page)
        ]);
    }
}
