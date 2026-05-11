<?php

namespace TofuPlugin\Init;

use TofuPlugin\Helpers\Encryptor;
use TofuPlugin\Models\Record;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class AdminPage
{
    /**
     * Register the admin_menu action. Call once from the plugin bootstrap.
     */
    public static function register(): void
    {
        add_action('admin_menu', [static::class, 'addMenuPage']);
    }

    /**
     * Register the TOFU top-level admin menu page.
     */
    public static function addMenuPage(): void
    {
        add_menu_page(
            page_title: __('TOFU Records', 'template-oriented-form-utilities'),
            menu_title: __('TOFU', 'template-oriented-form-utilities'),
            capability: 'manage_options',
            menu_slug:  'tofu-records',
            callback:   [static::class, 'renderPage'],
            icon_url:   'dashicons-feedback',
            position:   80,
        );
    }

    /**
     * Render the records list page or detail page depending on query params.
     */
    public static function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'template-oriented-form-utilities'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only
        $recordId = isset($_GET['record_id']) ? (int) $_GET['record_id'] : 0;

        if ($recordId > 0) {
            static::renderDetailPage($recordId);
        } else {
            static::renderListPage();
        }
    }

    /**
     * Render the records list page.
     */
    protected static function renderListPage(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change
        $formId  = isset($_GET['form_id']) ? sanitize_key($_GET['form_id']) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, no state change
        $page    = max(1, (int) ($_GET['paged'] ?? 1));
        $perPage = 25;

        $result  = Record::getRecords($formId !== '' ? $formId : null, $perPage, $page);
        $items   = $result['items'];
        $total   = $result['total'];
        $pages   = (int) ceil($total / $perPage);
        $formIds = Record::getFormIds();

        $baseUrl = admin_url('admin.php?page=tofu-records');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('TOFU Records', 'template-oriented-form-utilities'); ?></h1>

            <form method="get" action="<?php echo esc_url($baseUrl); ?>">
                <input type="hidden" name="page" value="tofu-records">
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <label for="tofu-filter-form-id" class="screen-reader-text">
                            <?php echo esc_html__('Filter by form', 'template-oriented-form-utilities'); ?>
                        </label>
                        <select id="tofu-filter-form-id" name="form_id">
                            <option value=""><?php echo esc_html__('All forms', 'template-oriented-form-utilities'); ?></option>
                            <?php foreach ($formIds as $fid) : ?>
                                <option value="<?php echo esc_attr($fid); ?>" <?php selected($formId, $fid); ?>>
                                    <?php echo esc_html($fid); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php submit_button(__('Filter', 'template-oriented-form-utilities'), 'button', 'filter_action', false); ?>
                    </div>
                    <?php if ($pages > 1) : ?>
                        <div class="tablenav-pages">
                            <?php
                            echo wp_kses_post(paginate_links([
                                'base'      => add_query_arg('paged', '%#%', $baseUrl . ($formId !== '' ? '&form_id=' . rawurlencode($formId) : '')),
                                'format'    => '',
                                'current'   => $page,
                                'total'     => $pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ]));
                            ?>
                        </div>
                    <?php endif; ?>
                    <br class="clear">
                </div>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width:60px"><?php echo esc_html__('ID', 'template-oriented-form-utilities'); ?></th>
                        <th scope="col" style="width:160px"><?php echo esc_html__('Form', 'template-oriented-form-utilities'); ?></th>
                        <th scope="col" style="width:180px"><?php echo esc_html__('Submitted', 'template-oriented-form-utilities'); ?></th>
                        <th scope="col"><?php echo esc_html__('Fields', 'template-oriented-form-utilities'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)) : ?>
                        <tr>
                            <td colspan="4"><?php echo esc_html__('No records found.', 'template-oriented-form-utilities'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($items as $row) :
                            $detailUrl = add_query_arg([
                                'page'      => 'tofu-records',
                                'record_id' => (int) $row->id,
                            ], admin_url('admin.php'));
                        ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url($detailUrl); ?>">
                                        <?php echo esc_html((string) $row->id); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html((string) $row->form_id); ?></td>
                                <td><?php echo esc_html(isset($row->submitted_at) ? (string) $row->submitted_at : '—'); ?></td>
                                <td>
                                    <a href="<?php echo esc_url($detailUrl); ?>">
                                        <?php echo esc_html(static::summarizeData($row->data ?? null)); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="col"><?php echo esc_html__('ID', 'template-oriented-form-utilities'); ?></th>
                        <th scope="col"><?php echo esc_html__('Form', 'template-oriented-form-utilities'); ?></th>
                        <th scope="col"><?php echo esc_html__('Submitted', 'template-oriented-form-utilities'); ?></th>
                        <th scope="col"><?php echo esc_html__('Fields', 'template-oriented-form-utilities'); ?></th>
                    </tr>
                </tfoot>
            </table>

            <?php if ($pages > 1) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(paginate_links([
                            'base'      => add_query_arg('paged', '%#%', $baseUrl . ($formId !== '' ? '&form_id=' . rawurlencode($formId) : '')),
                            'format'    => '',
                            'current'   => $page,
                            'total'     => $pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ]));
                        ?>
                    </div>
                    <br class="clear">
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the detail page for a single record.
     *
     * @param int $recordId The record primary key.
     */
    protected static function renderDetailPage(int $recordId): void
    {
        $row = Record::getRecord($recordId);

        $listUrl = admin_url('admin.php?page=tofu-records');

        if ($row === null) {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html__('TOFU Records', 'template-oriented-form-utilities'); ?></h1>
                <p><?php echo esc_html__('Record not found.', 'template-oriented-form-utilities'); ?></p>
                <a href="<?php echo esc_url($listUrl); ?>" class="button">
                    &larr; <?php echo esc_html__('Back to list', 'template-oriented-form-utilities'); ?>
                </a>
            </div>
            <?php
            return;
        }

        $decoded = ($row->data !== null && $row->data !== '')
            ? Encryptor::decrypt((string) $row->data)
            : false;

        ?>
        <div class="wrap">
            <h1>
                <?php
                echo esc_html(sprintf(
                    /* translators: %d = record ID */
                    __('TOFU Record #%d', 'template-oriented-form-utilities'),
                    $recordId
                ));
                ?>
            </h1>

            <a href="<?php echo esc_url($listUrl); ?>" class="button" style="margin-bottom:1em;display:inline-block;">
                &larr; <?php echo esc_html__('Back to list', 'template-oriented-form-utilities'); ?>
            </a>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('ID', 'template-oriented-form-utilities'); ?></th>
                        <td><?php echo esc_html((string) $row->id); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Form', 'template-oriented-form-utilities'); ?></th>
                        <td><?php echo esc_html((string) $row->form_id); ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Submitted', 'template-oriented-form-utilities'); ?></th>
                        <td><?php echo esc_html(isset($row->submitted_at) ? (string) $row->submitted_at : '—'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2><?php echo esc_html__('Submitted Fields', 'template-oriented-form-utilities'); ?></h2>

            <?php if ($decoded === false || !is_array($decoded)) : ?>
                <p><?php echo esc_html__('Field data could not be decrypted or is unavailable.', 'template-oriented-form-utilities'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
                    <thead>
                        <tr>
                            <th scope="col" style="width:220px"><?php echo esc_html__('Field', 'template-oriented-form-utilities'); ?></th>
                            <th scope="col"><?php echo esc_html__('Value', 'template-oriented-form-utilities'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($decoded as $key => $value) :
                            $displayValue = is_array($value)
                                ? implode(', ', array_map('strval', $value))
                                : (string) $value;
                        ?>
                            <tr>
                                <th scope="row" style="font-weight:600"><?php echo esc_html((string) $key); ?></th>
                                <td style="white-space:pre-wrap;word-break:break-word;"><?php echo esc_html($displayValue); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Decrypt the `data` column and produce a short key→value summary string.
     * Returns "—" when the column is absent, empty, or decryption fails.
     *
     * @param string|null $encryptedData Raw value from the `data` column.
     * @return string Plain-text summary, truncated to 200 characters.
     */
    protected static function summarizeData(?string $encryptedData): string
    {
        if ($encryptedData === null || $encryptedData === '') {
            return '—';
        }

        $decoded = Encryptor::decrypt($encryptedData);

        if ($decoded === false || !is_array($decoded)) {
            return '—';
        }

        $parts = [];
        foreach ($decoded as $key => $value) {
            $keyStr   = mb_substr((string) $key, 0, 40);
            $valueStr = is_array($value)
                ? implode(', ', array_map('strval', $value))
                : (string) $value;
            $valueStr = mb_substr($valueStr, 0, 80);
            $parts[]  = $keyStr . ': ' . $valueStr;
        }

        $summary = implode(' | ', $parts);

        return mb_strlen($summary) > 200 ? mb_substr($summary, 0, 197) . '…' : $summary;
    }
}
