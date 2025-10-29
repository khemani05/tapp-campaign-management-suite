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

    <?php if (get_query_var('campaign_action') && in_array($_GET['action'], ['create-team', 'create-sales'])): ?>
        <!-- Campaign Creation Form -->
        <?php include TAPP_CAMPAIGNS_PATH . 'frontend/templates/campaign-form.php'; ?>

    <?php else: ?>
        <!-- Campaign List -->
        <div class="campaigns-list">
            <?php if (empty($campaigns)): ?>
                <p class="no-campaigns"><?php _e('No campaigns found.', 'tapp-campaigns'); ?></p>
            <?php else: ?>
                <table class="campaigns-table">
                    <thead>
                        <tr>
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
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
