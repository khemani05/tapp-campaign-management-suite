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

// Get stats
$total_campaigns = TAPP_Campaigns_Database::count_campaigns(['creator_id' => $args['creator_id'] ?? null]);
$active_campaigns = TAPP_Campaigns_Database::count_campaigns(array_merge($args, ['status' => 'active']));

?>

<div class="tapp-dashboard">
    <div class="dashboard-header">
        <h2><?php _e('Campaign Manager', 'tapp-campaigns'); ?></h2>

        <div class="dashboard-actions">
            <a href="<?php echo esc_url(add_query_arg('action', 'create-team')); ?>" class="button button-primary">
                <?php _e('+ Create Team Campaign', 'tapp-campaigns'); ?>
            </a>
            <a href="<?php echo esc_url(add_query_arg('action', 'create-sales')); ?>" class="button button-secondary">
                <?php _e('+ Create Sales Campaign', 'tapp-campaigns'); ?>
            </a>
        </div>
    </div>

    <div class="dashboard-stats">
        <div class="stat-card">
            <h3><?php echo $total_campaigns; ?></h3>
            <p><?php _e('Total Campaigns', 'tapp-campaigns'); ?></p>
        </div>
        <div class="stat-card">
            <h3><?php echo $active_campaigns; ?></h3>
            <p><?php _e('Active Campaigns', 'tapp-campaigns'); ?></p>
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

    <?php if (isset($_GET['action']) && in_array($_GET['action'], ['create-team', 'create-sales'])): ?>
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
                                    <a href="<?php echo esc_url(TAPP_Campaigns_Campaign::get_url($campaign->id)); ?>" class="button button-small">
                                        <?php _e('View', 'tapp-campaigns'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg(['action' => 'edit', 'id' => $campaign->id])); ?>" class="button button-small">
                                        <?php _e('Edit', 'tapp-campaigns'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(add_query_arg(['action' => 'export', 'id' => $campaign->id])); ?>" class="button button-small">
                                        <?php _e('Export', 'tapp-campaigns'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
