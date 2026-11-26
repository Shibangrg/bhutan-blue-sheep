<?php

namespace BlueSheep\BusinessManager\Modules\Quotations;

use RuntimeException;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages financial line items within quotation versions.
 *
 * Itinerary content is managed separately.
 */
final class QuotationItemService
{
    private $wpdb;

    private string $quotation_table;
    private string $version_table;
    private string $item_table;
    private string $activity_table;

    private const ITEM_TYPES = [
        'accommodation',
        'transport',
        'activity',
        'guide',
        'meal',
        'other',
    ];

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;

        $this->quotation_table = $wpdb->prefix . 'bs_quotations';
        $this->version_table = $wpdb->prefix . 'bs_quotation_versions';
        $this->item_table = $wpdb->prefix . 'bs_quotation_items';
        $this->activity_table = $wpdb->prefix . 'bs_activity_log';
    }

    /**
     * Add a financial item to a draft quotation version.
     *
     * @return int|WP_Error
     */
    public function addItem(int $version_id, array $input)
    {
        if (!$this->canManage()) {
            return $this->error(
                'permission_denied',
                'You do not have permission to manage quotation items.'
            );
        }

        $data = $this->validateItem($input);

        if (is_wp_error($data)) {
            return $data;
        }

        return $this->runTransaction(function () use ($version_id, $data) {
            $version = $this->lockDraftVersion($version_id);

            $sort_order = isset($data['sort_order'])
                ? $data['sort_order']
                : (int) $this->wpdb->get_var(
                    $this->wpdb->prepare(
                        "SELECT COALESCE(MAX(sort_order), 0) + 1
                         FROM {$this->item_table}
                         WHERE quotation_version_id = %d",
                        $version_id
                    )
                );

            if ($this->wpdb->last_error !== '') {
                throw new RuntimeException(
                    'The quotation item order could not be determined.'
                );
            }

            $inserted = $this->wpdb->insert(
                $this->item_table,
                [
                    'quotation_version_id' => $version_id,
                    'item_type' => $data['item_type'],
                    'day_number' => $data['day_number'],
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'quantity' => $data['quantity'],
                    'unit_amount' => $data['unit_amount'],
                    'total_amount' => $this->calculateLineTotal(
                        $data['quantity'],
                        $data['unit_amount']
                    ),
                    'sort_order' => $sort_order,
                ]
            );

            if ($inserted !== 1) {
                throw new RuntimeException(
                    'The quotation item could not be added: '
                    . $this->wpdb->last_error
                );
            }

            $item_id = (int) $this->wpdb->insert_id;

            $this->recalculateTotals($version_id);
            $this->logActivity(
                (int) $version['quotation_id'],
                'quotation_item_added',
                'Quotation cost item added: ' . $data['title'],
                $version_id
            );

            return $item_id;
        });
    }

    /**
     * Update an existing item belonging to a draft version.
     *
     * @return bool|WP_Error
     */
    public function updateItem(
        int $version_id,
        int $item_id,
        array $input
    ) {
        if (!$this->canManage()) {
            return $this->error(
                'permission_denied',
                'You do not have permission to manage quotation items.'
            );
        }

        if ($item_id <= 0) {
            return $this->error(
                'invalid_item',
                'A valid quotation item is required.'
            );
        }

        $data = $this->validateItem($input);

        if (is_wp_error($data)) {
            return $data;
        }

        return $this->runTransaction(function () use (
            $version_id,
            $item_id,
            $data
        ) {
            $version = $this->lockDraftVersion($version_id);

            $item = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id
                     FROM {$this->item_table}
                     WHERE id = %d
                     AND quotation_version_id = %d
                     FOR UPDATE",
                    $item_id,
                    $version_id
                ),
                ARRAY_A
            );

            if ($this->wpdb->last_error !== '') {
                throw new RuntimeException(
                    'The quotation item could not be retrieved.'
                );
            }

            if (!is_array($item)) {
                throw new RuntimeException(
                    'The quotation item does not belong to this version.'
                );
            }

            $updated = $this->wpdb->update(
                $this->item_table,
                [
                    'item_type' => $data['item_type'],
                    'day_number' => $data['day_number'],
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'quantity' => $data['quantity'],
                    'unit_amount' => $data['unit_amount'],
                    'total_amount' => $this->calculateLineTotal(
                        $data['quantity'],
                        $data['unit_amount']
                    ),
                    'sort_order' => $data['sort_order'] ?? 0,
                ],
                [
                    'id' => $item_id,
                    'quotation_version_id' => $version_id,
                ]
            );

            if ($updated === false) {
                throw new RuntimeException(
                    'The quotation item could not be updated.'
                );
            }

            $this->recalculateTotals($version_id);
            $this->logActivity(
                (int) $version['quotation_id'],
                'quotation_item_updated',
                'Quotation cost item updated: ' . $data['title'],
                $version_id
            );

            return true;
        });
    }

    /**
     * Remove an item from a draft quotation version.
     *
     * @return bool|WP_Error
     */
    public function deleteItem(int $version_id, int $item_id)
    {
        if (!$this->canManage()) {
            return $this->error(
                'permission_denied',
                'You do not have permission to manage quotation items.'
            );
        }

        if ($item_id <= 0) {
            return $this->error(
                'invalid_item',
                'A valid quotation item is required.'
            );
        }

        return $this->runTransaction(function () use (
            $version_id,
            $item_id
        ) {
            $version = $this->lockDraftVersion($version_id);

            $item = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT id, title
                     FROM {$this->item_table}
                     WHERE id = %d
                     AND quotation_version_id = %d
                     FOR UPDATE",
                    $item_id,
                    $version_id
                ),
                ARRAY_A
            );

            if ($this->wpdb->last_error !== '') {
                throw new RuntimeException(
                    'The quotation item could not be retrieved.'
                );
            }

            if (!is_array($item)) {
                throw new RuntimeException(
                    'The quotation item does not belong to this version.'
                );
            }

            $deleted = $this->wpdb->delete(
                $this->item_table,
                [
                    'id' => $item_id,
                    'quotation_version_id' => $version_id,
                ],
                ['%d', '%d']
            );

            if ($deleted !== 1) {
                throw new RuntimeException(
                    'The quotation item could not be removed.'
                );
            }

            $this->recalculateTotals($version_id);
            $this->logActivity(
                (int) $version['quotation_id'],
                'quotation_item_deleted',
                'Quotation cost item removed: ' . $item['title'],
                $version_id
            );

            return true;
        });
    }

    /**
     * Retrieve items for a quotation version.
     *
     * @return array|WP_Error
     */
    public function getItems(int $version_id)
    {
        if ($version_id <= 0) {
            return $this->error(
                'invalid_version',
                'A valid quotation version is required.'
            );
        }

        $items = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT *
                 FROM {$this->item_table}
                 WHERE quotation_version_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $version_id
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            return $this->error(
                'items_retrieval_failed',
                'Quotation items could not be retrieved.'
            );
        }

        return is_array($items) ? $items : [];
    }

    /**
     * Lock and validate the current draft quotation version.
     */
    private function lockDraftVersion(int $version_id): array
    {
        if ($version_id <= 0) {
            throw new RuntimeException(
                'A valid quotation version is required.'
            );
        }

        $version = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT
                    v.id,
                    v.quotation_id,
                    v.status,
                    v.discount,
                    q.current_version_id
                 FROM {$this->version_table} AS v
                 INNER JOIN {$this->quotation_table} AS q
                    ON q.id = v.quotation_id
                 WHERE v.id = %d
                 FOR UPDATE",
                $version_id
            ),
            ARRAY_A
        );

        if ($this->wpdb->last_error !== '') {
            throw new RuntimeException(
                'The quotation version could not be retrieved.'
            );
        }

        if (!is_array($version)) {
            throw new RuntimeException(
                'The quotation version could not be found.'
            );
        }

        if ((int) $version['current_version_id'] !== $version_id) {
            throw new RuntimeException(
                'Only the current quotation version can be edited.'
            );
        }

        if ($version['status'] !== 'draft') {
            throw new RuntimeException(
                'Only draft quotation versions can be edited.'
            );
        }

        return $version;
    }

    /**
     * Validate submitted item data.
     *
     * @return array|WP_Error
     */
    private function validateItem(array $input)
    {
        $item_type = isset($input['item_type'])
            ? sanitize_key($input['item_type'])
            : '';

        $title = isset($input['title'])
            ? sanitize_text_field($input['title'])
            : '';

        $description = isset($input['description'])
            ? sanitize_textarea_field($input['description'])
            : '';

        $quantity = isset($input['quantity'])
            ? trim((string) $input['quantity'])
            : '';

        $unit_amount = isset($input['unit_amount'])
            ? trim((string) $input['unit_amount'])
            : '';

        if (!in_array($item_type, self::ITEM_TYPES, true)) {
            return $this->error(
                'invalid_item_type',
                'Select a supported quotation item type.'
            );
        }

        if ($title === '' || mb_strlen($title, 'UTF-8') > 190) {
            return $this->error(
                'invalid_item_title',
                'Enter an item title of no more than 190 characters.'
            );
        }

        if (
            !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $quantity)
            || (float) $quantity <= 0
        ) {
            return $this->error(
                'invalid_quantity',
                'Enter a quantity greater than zero with up to two decimal places.'
            );
        }

        if (
            !preg_match('/^\d{1,15}(?:\.\d{1,4})?$/', $unit_amount)
        ) {
            return $this->error(
                'invalid_unit_amount',
                'Enter a valid non-negative unit amount with up to four decimal places.'
            );
        }

        $day_number = null;

        if (
            isset($input['day_number'])
            && $input['day_number'] !== ''
        ) {
            $day_number = filter_var(
                $input['day_number'],
                FILTER_VALIDATE_INT
            );

            if ($day_number === false || $day_number <= 0) {
                return $this->error(
                    'invalid_day_number',
                    'The itinerary day number must be a positive integer.'
                );
            }
        }

        $sort_order = isset($input['sort_order'])
            ? filter_var($input['sort_order'], FILTER_VALIDATE_INT)
            : 0;

        if ($sort_order === false || $sort_order < 0) {
            return $this->error(
                'invalid_sort_order',
                'The item order must be a non-negative integer.'
            );
        }

        return [
            'item_type' => $item_type,
            'day_number' => $day_number,
            'title' => $title,
            'description' => $description,
            'quantity' => $quantity,
            'unit_amount' => $unit_amount,
            'sort_order' => $sort_order,
        ];
    }

    /**
     * Calculate a line total using MySQL decimal arithmetic.
     */
    private function calculateLineTotal(
        string $quantity,
        string $unit_amount
    ): string {
        $total = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT ROUND(%s * %s, 4)',
                $quantity,
                $unit_amount
            )
        );

        if ($this->wpdb->last_error !== '' || $total === null) {
            throw new RuntimeException(
                'The quotation item total could not be calculated.'
            );
        }

        return number_format((float) $total, 4, '.', '');
    }

    /**
     * Recalculate the quotation version subtotal and total.
     */
    private function recalculateTotals(int $version_id): void
    {
        $subtotal = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COALESCE(SUM(total_amount), 0)
                 FROM {$this->item_table}
                 WHERE quotation_version_id = %d",
                $version_id
            )
        );

        if ($this->wpdb->last_error !== '' || $subtotal === null) {
            throw new RuntimeException(
                'The quotation subtotal could not be calculated.'
            );
        }

        $version = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT discount
                 FROM {$this->version_table}
                 WHERE id = %d
                 FOR UPDATE",
                $version_id
            ),
            ARRAY_A
        );

        if (!is_array($version)) {
            throw new RuntimeException(
                'The quotation version could not be retrieved for recalculation.'
            );
        }

        $totals = $this->wpdb->get_row(
            $this->wpdb->prepare(
                'SELECT
                    CAST(%s AS DECIMAL(19,4)) AS subtotal,
                    CAST(%s AS DECIMAL(19,4)) AS discount,
                    ROUND(
                        CAST(%s AS DECIMAL(19,4))
                        - CAST(%s AS DECIMAL(19,4)),
                        4
                    ) AS total_amount',
                $subtotal,
                $version['discount'],
                $subtotal,
                $version['discount']
            ),
            ARRAY_A
        );

        if (
            !is_array($totals)
            || $this->wpdb->last_error !== ''
            || (float) $totals['total_amount'] < 0
        ) {
            throw new RuntimeException(
                'The quotation discount cannot exceed its subtotal.'
            );
        }

        $updated = $this->wpdb->update(
            $this->version_table,
            [
                'subtotal' => $totals['subtotal'],
                'total_amount' => $totals['total_amount'],
            ],
            ['id' => $version_id]
        );

        if ($updated === false) {
            throw new RuntimeException(
                'The quotation totals could not be saved.'
            );
        }
    }

    /**
     * Write an activity record using the approved schema.
     */
    private function logActivity(
        int $quotation_id,
        string $action,
        string $description,
        int $version_id
    ): void {
        $inserted = $this->wpdb->insert(
            $this->activity_table,
            [
                'user_id' => get_current_user_id(),
                'entity_type' => 'quotation',
                'entity_id' => $quotation_id,
                'action' => $action,
                'description' => $description,
                'created_at' => current_time('mysql'),
                'reason' => 'Quotation version ' . $version_id,
            ]
        );

        if ($inserted !== 1) {
            throw new RuntimeException(
                'Quotation activity could not be recorded: '
                . $this->wpdb->last_error
            );
        }
    }

    /**
     * Execute an operation inside a database transaction.
     *
     * @return mixed
     */
    private function runTransaction(callable $callback)
    {
        $started = false;

        try {
            if ($this->wpdb->query('START TRANSACTION') === false) {
                throw new RuntimeException(
                    'The database transaction could not be started.'
                );
            }

            $started = true;

            $result = $callback();

            if ($this->wpdb->query('COMMIT') === false) {
                throw new RuntimeException(
                    'The database transaction could not be committed.'
                );
            }

            $started = false;

            return $result;

        } catch (Throwable $exception) {
            if ($started) {
                $this->wpdb->query('ROLLBACK');
            }

            error_log(
                'Blue Sheep quotation item operation failed: '
                . $exception->getMessage()
            );

            return $this->error(
                'quotation_item_operation_failed',
                $exception->getMessage()
            );
        }
    }

    /**
     * Check administrator permissions.
     */
    private function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Create a WordPress error.
     */
    private function error(string $code, string $message): \WP_Error
    {
        return new \WP_Error(
            $code,
            __($message, 'blue-sheep-business-manager')
        );
    }
}
