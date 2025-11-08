<?php
/**
 * Activity Log Admin Page
 * Display campaign activity logs with filtering
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options') && !current_user_can('view_all_campaigns')) {
    wp_die(__('You do not have permission to view activity logs.', 'tapp-campaigns'));
}

// Get filter parameters
$campaign_id = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : null;
$action_type = isset($_GET['action_type']) ? sanitize_text_field($_GET['action_type']) : null;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 50;

// Build query args
$args = [
    'limit' => $per_page,
    'offset' => ($page - 1) * $per_page,
    'order_by' => 'created_at',
    'order' => 'DESC',
];

if ($campaign_id) {
    $args['campaign_id'] = $campaign_id;
}

if ($action_type) {
    $args['action_type'] = $action_type;
}

if ($user_id) {
    $args['user_id'] = $user_id;
}

// Get logs
$logs = TAPP_Campaigns_Activity_Log::get_logs($args);
$total_logs = TAPP_Campaigns_Activity_Log::count_logs($args);
$total_pages = ceil($total_logs / $per_page);

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && check_admin_referer('tapp_export_logs', 'nonce')) {
    $csv_content = TAPP_Campaigns_Activity_Log::export_to_csv($args);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=activity-logs-' . date('Y-m-d-His') . '.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo $csv_content;
    exit;
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Activity Log', 'tapp-campaigns'); ?></h1>

    <?php if (current_user_can('manage_options')): ?>
        <a href="<?php echo wp_nonce_url(add_query_arg(['export' => 'csv']), 'tapp_export_logs', 'nonce'); ?>" class="page-title-action">
            <?php _e('Export to CSV', 'tapp-campaigns'); ?>
        </a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <!-- Filters -->
    <div class="tablenav top">
        <form method="get" action="">
            <input type="hidden" name="page" value="tapp-activity-log">

            <div class="alignleft actions">
                <!-- Action Type Filter -->
                <select name="action_type" id="action-type-filter">
                    <option value=""><?php _e('All Action Types', 'tapp-campaigns'); ?></option>
                    <option value="campaign" <?php selected($action_type, 'campaign'); ?>><?php _e('Campaign', 'tapp-campaigns'); ?></option>
                    <option value="participant" <?php selected($action_type, 'participant'); ?>><?php _e('Participant', 'tapp-campaigns'); ?></option>
                    <option value="response" <?php selected($action_type, 'response'); ?>><?php _e('Response', 'tapp-campaigns'); ?></option>
                    <option value="template" <?php selected($action_type, 'template'); ?>><?php _e('Template', 'tapp-campaigns'); ?></option>
                    <option value="group" <?php selected($action_type, 'group'); ?>><?php _e('Group', 'tapp-campaigns'); ?></option>
                    <option value="system" <?php selected($action_type, 'system'); ?>><?php _e('System', 'tapp-campaigns'); ?></option>
                </select>

                <?php submit_button(__('Filter', 'tapp-campaigns'), 'button', 'filter', false); ?>

                <?php if ($action_type || $campaign_id || $user_id): ?>
                    <a href="?page=tapp-activity-log" class="button"><?php _e('Clear Filters', 'tapp-campaigns'); ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Logs Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 60px;"><?php _e('ID', 'tapp-campaigns'); ?></th>
                <th style="width: 150px;"><?php _e('Date/Time', 'tapp-campaigns'); ?></th>
                <th><?php _e('User', 'tapp-campaigns'); ?></th>
                <th><?php _e('Action', 'tapp-campaigns'); ?></th>
                <th><?php _e('Type', 'tapp-campaigns'); ?></th>
                <th><?php _e('Description', 'tapp-campaigns'); ?></th>
                <th><?php _e('Campaign', 'tapp-campaigns'); ?></th>
                <th style="width: 120px;"><?php _e('IP Address', 'tapp-campaigns'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 20px;">
                        <?php _e('No activity logs found.', 'tapp-campaigns'); ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->id); ?></td>
                        <td>
                            <?php
                            echo esc_html(date_i18n(
                                get_option('date_format') . ' ' . get_option('time_format'),
                                strtotime($log->created_at)
                            ));
                            ?>
                        </td>
                        <td>
                            <?php
                            if (isset($log->user_name)) {
                                echo esc_html($log->user_name);
                            } else {
                                echo '<em>' . __('System', 'tapp-campaigns') . '</em>';
                            }
                            ?>
                        </td>
                        <td><code><?php echo esc_html($log->action); ?></code></td>
                        <td>
                            <span class="tapp-log-badge tapp-log-<?php echo esc_attr($log->action_type); ?>">
                                <?php echo esc_html(ucfirst($log->action_type)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->description); ?></td>
                        <td>
                            <?php
                            if (isset($log->campaign_name)) {
                                $analytics_url = admin_url('admin.php?page=tapp-activity-log&campaign_id=' . $log->campaign_id);
                                echo '<a href="' . esc_url($analytics_url) . '">' . esc_html($log->campaign_name) . '</a>';
                            } else {
                                echo '-';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($log->ip_address ?: '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(_n('%s item', '%s items', $total_logs, 'tapp-campaigns'), number_format_i18n($total_logs)); ?>
                </span>
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $page,
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.tapp-log-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.tapp-log-campaign {
    background: #d4edda;
    color: #155724;
}
.tapp-log-participant {
    background: #d1ecf1;
    color: #0c5460;
}
.tapp-log-response {
    background: #fff3cd;
    color: #856404;
}
.tapp-log-template {
    background: #e2e3e5;
    color: #383d41;
}
.tapp-log-group {
    background: #cce5ff;
    color: #004085;
}
.tapp-log-system {
    background: #f8d7da;
    color: #721c24;
}
</style>
