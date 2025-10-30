<?php
/**
 * Template: Manager Dashboard
 * Campaign management interface
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
$onboarding = tapp_campaigns_onboarding();

// Get campaigns based on user permissions
$args = [
    'status' => isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : null,
    'limit' => 20,
    'offset' => 0,
];

// Handle search
if (isset($_GET['s']) && !empty($_GET['s'])) {
    $args['search'] = sanitize_text_field($_GET['s']);
}

// Handle type filter
if (isset($_GET['type']) && in_array($_GET['type'], ['team', 'sales'])) {
    $args['type'] = sanitize_text_field($_GET['type']);
}

// Handle date range
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $args['date_from'] = sanitize_text_field($_GET['date_from']);
}
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $args['date_to'] = sanitize_text_field($_GET['date_to']);
}

if (!$onboarding->can_view_all_campaigns($user_id)) {
    $args['creator_id'] = $user_id;
}

$campaigns = TAPP_Campaigns_Database::get_campaigns($args);

// Get comprehensive stats
$stats_args = ['creator_id' => $args['creator_id'] ?? null];
$total_campaigns = TAPP_Campaigns_Database::count_campaigns($stats_args);
$active_campaigns = TAPP_Campaigns_Database::count_campaigns(array_merge($stats_args, ['status' => 'active']));
$draft_campaigns = TAPP_Campaigns_Database::count_campaigns(array_merge($stats_args, ['status' => 'draft']));
$scheduled_campaigns = TAPP_Campaigns_Database::count_campaigns(array_merge($stats_args, ['status' => 'scheduled']));
$ended_campaigns = TAPP_Campaigns_Database::count_campaigns(array_merge($stats_args, ['status' => 'ended']));

// Calculate aggregate statistics
global $wpdb;
$creator_filter = isset($args['creator_id']) ? $wpdb->prepare(' AND c.creator_id = %d', $args['creator_id']) : '';
$aggregate_stats = $wpdb->get_row("
    SELECT
        COUNT(DISTINCT c.id) as total_campaigns,
        COUNT(DISTINCT p.id) as total_participants,
        COUNT(DISTINCT CASE WHEN p.submitted_at IS NOT NULL THEN p.id END) as total_responses,
        ROUND(AVG(CASE WHEN p.submitted_at IS NOT NULL THEN 100 ELSE 0 END), 1) as avg_response_rate
    FROM {$wpdb->prefix}tapp_campaigns c
    LEFT JOIN {$wpdb->prefix}tapp_campaign_participants p ON c.id = p.campaign_id
    WHERE 1=1 {$creator_filter}
");

?>

<div class="tapp-dashboard">
    <div class="dashboard-header">
        <h2><?php _e('Campaign Manager', 'tapp-campaigns'); ?></h2>

        <div class="dashboard-actions">
            <a href="<?php echo esc_url(home_url('/campaign-manager/create-team/')); ?>" class="button button-primary">
                <?php _e('+ Create Team Campaign', 'tapp-campaigns'); ?>
            </a>
            <a href="<?php echo esc_url(home_url('/campaign-manager/create-sales/')); ?>" class="button button-secondary">
                <?php _e('+ Create Sales Campaign', 'tapp-campaigns'); ?>
            </a>
        </div>
    </div>

    <div class="dashboard-stats">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo $active_campaigns; ?></h3>
                <p><?php _e('Active Now', 'tapp-campaigns'); ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <path d="M9 3v18"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_campaigns; ?></h3>
                <p><?php _e('Total Campaigns', 'tapp-campaigns'); ?></p>
                <small><?php echo $draft_campaigns; ?> <?php _e('drafts', 'tapp-campaigns'); ?></small>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($aggregate_stats->total_participants ?? 0); ?></h3>
                <p><?php _e('Total Participants', 'tapp-campaigns'); ?></p>
                <small><?php echo number_format($aggregate_stats->total_responses ?? 0); ?> <?php _e('responses', 'tapp-campaigns'); ?></small>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M12 6v6l4 2"></path>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($aggregate_stats->avg_response_rate ?? 0, 1); ?>%</h3>
                <p><?php _e('Avg Response Rate', 'tapp-campaigns'); ?></p>
                <small><?php echo $scheduled_campaigns; ?> <?php _e('upcoming', 'tapp-campaigns'); ?></small>
            </div>
        </div>
    </div>

    <div class="dashboard-filters">
        <a href="<?php echo esc_url(remove_query_arg('filter')); ?>" class="filter-link <?php echo !isset($_GET['filter']) ? 'active' : ''; ?>">
            <?php _e('All', 'tapp-campaigns'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('filter', 'draft')); ?>" class="filter-link <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'draft') ? 'active' : ''; ?>">
            <?php _e('Draft', 'tapp-campaigns'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('filter', 'scheduled')); ?>" class="filter-link <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'scheduled') ? 'active' : ''; ?>">
            <?php _e('Scheduled', 'tapp-campaigns'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('filter', 'active')); ?>" class="filter-link <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'active') ? 'active' : ''; ?>">
            <?php _e('Active', 'tapp-campaigns'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('filter', 'ended')); ?>" class="filter-link <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'ended') ? 'active' : ''; ?>">
            <?php _e('Ended', 'tapp-campaigns'); ?>
        </a>
    </div>

    <!-- Advanced Search & Filters -->
    <div class="dashboard-search-bar">
        <form method="get" action="" class="search-form">
            <input type="hidden" name="campaign_page" value="manager">
            <?php if (isset($_GET['filter'])): ?>
                <input type="hidden" name="filter" value="<?php echo esc_attr($_GET['filter']); ?>">
            <?php endif; ?>

            <div class="search-fields">
                <div class="search-input-wrapper">
                    <svg class="search-icon" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                    </svg>
                    <input type="text" name="s" class="search-input"
                           placeholder="<?php _e('Search campaigns...', 'tapp-campaigns'); ?>"
                           value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
                </div>

                <select name="type" class="filter-select">
                    <option value=""><?php _e('All Types', 'tapp-campaigns'); ?></option>
                    <option value="team" <?php selected(isset($_GET['type']) && $_GET['type'] === 'team'); ?>><?php _e('Team', 'tapp-campaigns'); ?></option>
                    <option value="sales" <?php selected(isset($_GET['type']) && $_GET['type'] === 'sales'); ?>><?php _e('Sales', 'tapp-campaigns'); ?></option>
                </select>

                <input type="date" name="date_from" class="date-input"
                       placeholder="<?php _e('From date', 'tapp-campaigns'); ?>"
                       value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>">

                <input type="date" name="date_to" class="date-input"
                       placeholder="<?php _e('To date', 'tapp-campaigns'); ?>"
                       value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>">

                <button type="submit" class="button button-primary search-button">
                    <?php _e('Search', 'tapp-campaigns'); ?>
                </button>

                <?php if (isset($_GET['s']) || isset($_GET['type']) || isset($_GET['date_from']) || isset($_GET['date_to'])): ?>
                    <a href="<?php echo esc_url(remove_query_arg(['s', 'type', 'date_from', 'date_to'])); ?>" class="button clear-filters">
                        <?php _e('Clear', 'tapp-campaigns'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (get_query_var('campaign_action') && in_array($_GET['action'], ['create-team', 'create-sales'])): ?>
        <!-- Campaign Creation Form -->
        <?php include TAPP_CAMPAIGNS_PATH . 'frontend/templates/campaign-form.php'; ?>

    <?php else: ?>
        <!-- Campaign List -->
        <div class="campaigns-list">
            <?php if (empty($campaigns)): ?>
                <p class="no-campaigns"><?php _e('No campaigns found.', 'tapp-campaigns'); ?></p>
            <?php else: ?>
                <form id="bulk-actions-form" method="post" action="">
                    <?php wp_nonce_field('tapp_bulk_actions', 'bulk_nonce'); ?>

                    <div class="bulk-actions-bar">
                        <label class="select-all-wrapper">
                            <input type="checkbox" id="select-all-campaigns" class="select-all-checkbox">
                            <span><?php _e('Select All', 'tapp-campaigns'); ?></span>
                        </label>

                        <select name="bulk_action" id="bulk-action-select" class="bulk-action-select">
                            <option value=""><?php _e('Bulk Actions', 'tapp-campaigns'); ?></option>
                            <option value="activate"><?php _e('Activate', 'tapp-campaigns'); ?></option>
                            <option value="end"><?php _e('End', 'tapp-campaigns'); ?></option>
                            <option value="archive"><?php _e('Archive', 'tapp-campaigns'); ?></option>
                            <option value="delete"><?php _e('Delete', 'tapp-campaigns'); ?></option>
                        </select>

                        <button type="submit" class="button bulk-action-button" disabled>
                            <?php _e('Apply', 'tapp-campaigns'); ?>
                        </button>

                        <span class="selected-count" style="display:none;">
                            <span class="count">0</span> <?php _e('selected', 'tapp-campaigns'); ?>
                        </span>
                    </div>

                    <table class="campaigns-table">
                        <thead>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" class="select-all-checkbox-header">
                                </th>
                                <th><?php _e('Campaign Name', 'tapp-campaigns'); ?></th>
                            <th><?php _e('Type', 'tapp-campaigns'); ?></th>
                            <th><?php _e('Status', 'tapp-campaigns'); ?></th>
                            <th><?php _e('Dates', 'tapp-campaigns'); ?></th>
                            <th><?php _e('Responses', 'tapp-campaigns'); ?></th>
                            <th><?php _e('Actions', 'tapp-campaigns'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($campaigns as $campaign): ?>
                            <?php $stats = TAPP_Campaigns_Campaign::get_stats($campaign->id); ?>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" name="campaign_ids[]" value="<?php echo esc_attr($campaign->id); ?>" class="campaign-checkbox">
                                </td>
                                <td>
                                    <strong><?php echo esc_html($campaign->name); ?></strong>
                                </td>
                                <td>
                                    <span class="type-badge type-<?php echo esc_attr($campaign->type); ?>">
                                        <?php echo esc_html(ucfirst($campaign->type)); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($campaign->status); ?>">
                                        <?php echo esc_html(ucfirst($campaign->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date_i18n(get_option('date_format'), strtotime($campaign->start_date)); ?>
                                    <br>
                                    <small><?php echo date_i18n(get_option('date_format'), strtotime($campaign->end_date)); ?></small>
                                </td>
                                <td>
                                    <?php echo $stats['total_submitted']; ?> / <?php echo $stats['total_invited']; ?>
                                    <br>
                                    <small><?php echo $stats['participation_rate']; ?>%</small>
                                </td>
                                <td class="actions">
                                    <div class="action-buttons">
                                        <a href="<?php echo esc_url(TAPP_Campaigns_Campaign::get_url($campaign->id)); ?>" class="button button-small button-view" title="<?php _e('View Campaign', 'tapp-campaigns'); ?>">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                            </svg>
                                            <?php _e('View', 'tapp-campaigns'); ?>
                                        </a>
                                        <a href="<?php echo esc_url(home_url('/campaign-manager/analytics/' . $campaign->id . '/')); ?>" class="button button-small button-analytics" title="<?php _e('View Analytics', 'tapp-campaigns'); ?>">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M0 0h1v15h15v1H0V0zm14.817 3.113a.5.5 0 0 1 .07.704l-4.5 5.5a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61 4.15-5.073a.5.5 0 0 1 .704-.07z"/>
                                            </svg>
                                            <?php _e('Analytics', 'tapp-campaigns'); ?>
                                        </a>
                                        <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $campaign->id])); ?>" class="button button-small button-edit" title="<?php _e('Edit Campaign', 'tapp-campaigns'); ?>">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708l-3-3zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207l6.5-6.5zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.499.499 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11l.178-.178z"/>
                                            </svg>
                                            <?php _e('Edit', 'tapp-campaigns'); ?>
                                        </a>
                                        <a href="<?php echo esc_url(add_query_arg(['action' => 'duplicate', 'id' => $campaign->id, 'nonce' => wp_create_nonce('duplicate_campaign_' . $campaign->id)])); ?>" class="button button-small button-duplicate" title="<?php _e('Duplicate Campaign', 'tapp-campaigns'); ?>">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M4 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V2zm2-1a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H6zM2 5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-1h1v1a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1v1H2z"/>
                                            </svg>
                                            <?php _e('Duplicate', 'tapp-campaigns'); ?>
                                        </a>
                                        <a href="<?php echo esc_url(add_query_arg(['action' => 'export', 'id' => $campaign->id])); ?>" class="button button-small button-export" title="<?php _e('Export Results', 'tapp-campaigns'); ?>">
                                            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                                                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                                            </svg>
                                            <?php _e('Export', 'tapp-campaigns'); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Select all functionality
    $('#select-all-campaigns, .select-all-checkbox-header').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.campaign-checkbox').prop('checked', isChecked);
        $('#select-all-campaigns, .select-all-checkbox-header').prop('checked', isChecked);
        updateBulkActionsBar();
    });

    // Individual checkbox
    $('.campaign-checkbox').on('change', function() {
        updateBulkActionsBar();
    });

    // Bulk action selector
    $('#bulk-action-select').on('change', function() {
        updateBulkActionsBar();
    });

    function updateBulkActionsBar() {
        var checked = $('.campaign-checkbox:checked').length;
        var bulkAction = $('#bulk-action-select').val();

        if (checked > 0) {
            $('.bulk-action-button').prop('disabled', !bulkAction);
            $('.selected-count').show().find('.count').text(checked);
        } else {
            $('.bulk-action-button').prop('disabled', true);
            $('.selected-count').hide();
        }

        // Update select all checkboxes
        var total = $('.campaign-checkbox').length;
        $('#select-all-campaigns, .select-all-checkbox-header').prop('checked', checked === total && total > 0);
    }

    // Handle bulk action form submission
    $('#bulk-actions-form').on('submit', function(e) {
        var action = $('#bulk-action-select').val();
        var checked = $('.campaign-checkbox:checked').length;

        if (!action || checked === 0) {
            e.preventDefault();
            return false;
        }

        if (action === 'delete') {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to delete the selected campaigns? This action cannot be undone.', 'tapp-campaigns')); ?>')) {
                e.preventDefault();
                return false;
            }
        } else if (action === 'end') {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to end the selected campaigns?', 'tapp-campaigns')); ?>')) {
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>
