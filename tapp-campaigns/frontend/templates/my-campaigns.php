<?php
/**
 * Template: My Campaigns
 * Shows campaigns user is invited to
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();

global $wpdb;
$campaigns_table = $wpdb->prefix . 'tapp_campaigns';
$participants_table = $wpdb->prefix . 'tapp_participants';

// Get user's campaigns
$campaigns = $wpdb->get_results($wpdb->prepare("
    SELECT c.*, p.status as participant_status, p.submitted_at
    FROM $campaigns_table c
    INNER JOIN $participants_table p ON c.id = p.campaign_id
    WHERE p.user_id = %d
    ORDER BY c.end_date DESC
", $user_id));

?>

<div class="tapp-my-campaigns">
    <h2><?php _e('My Campaigns', 'tapp-campaigns'); ?></h2>

    <?php if (empty($campaigns)): ?>
        <p><?php _e('You are not invited to any campaigns yet.', 'tapp-campaigns'); ?></p>
    <?php else: ?>
        <div class="campaigns-grid">
            <?php foreach ($campaigns as $campaign): ?>
                <?php
                $is_active = $campaign->status === 'active' &&
                             strtotime($campaign->start_date) <= time() &&
                             strtotime($campaign->end_date) > time();
                $has_submitted = $campaign->participant_status === 'submitted';
                ?>
                <div class="campaign-card status-<?php echo esc_attr($campaign->status); ?> <?php echo $has_submitted ? 'submitted' : 'pending'; ?>">
                    <div class="card-header">
                        <h3><?php echo esc_html($campaign->name); ?></h3>
                        <span class="type-badge"><?php echo esc_html(ucfirst($campaign->type)); ?></span>
                    </div>

                    <div class="card-body">
                        <?php if ($campaign->notes): ?>
                            <p class="campaign-notes"><?php echo esc_html($campaign->notes); ?></p>
                        <?php endif; ?>

                        <p class="campaign-dates">
                            <strong><?php _e('Start:', 'tapp-campaigns'); ?></strong>
                            <?php echo date_i18n(get_option('date_format'), strtotime($campaign->start_date)); ?>
                            <br>
                            <strong><?php _e('End:', 'tapp-campaigns'); ?></strong>
                            <?php echo date_i18n(get_option('date_format'), strtotime($campaign->end_date)); ?>
                        </p>

                        <?php if ($has_submitted): ?>
                            <div class="submission-status success">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('Response Submitted', 'tapp-campaigns'); ?>
                                <br>
                                <small><?php echo date_i18n(get_option('date_format'), strtotime($campaign->submitted_at)); ?></small>
                            </div>
                        <?php elseif ($is_active): ?>
                            <div class="submission-status pending">
                                <span class="dashicons dashicons-clock"></span>
                                <?php _e('Awaiting Response', 'tapp-campaigns'); ?>
                            </div>
                        <?php else: ?>
                            <div class="submission-status ended">
                                <?php _e('Campaign Ended', 'tapp-campaigns'); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer">
                        <a href="<?php echo esc_url(TAPP_Campaigns_Campaign::get_url($campaign->id)); ?>" class="button button-primary">
                            <?php echo $is_active && !$has_submitted ? __('Respond Now', 'tapp-campaigns') : __('View Campaign', 'tapp-campaigns'); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
