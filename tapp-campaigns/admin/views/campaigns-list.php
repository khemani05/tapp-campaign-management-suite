<?php
/**
 * Admin View: Campaigns List
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="wrap">
    <h1><?php _e('TAPP Campaigns', 'tapp-campaigns'); ?></h1>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Campaign Name', 'tapp-campaigns'); ?></th>
                <th><?php _e('Type', 'tapp-campaigns'); ?></th>
                <th><?php _e('Status', 'tapp-campaigns'); ?></th>
                <th><?php _e('Creator', 'tapp-campaigns'); ?></th>
                <th><?php _e('Dates', 'tapp-campaigns'); ?></th>
                <th><?php _e('Participants', 'tapp-campaigns'); ?></th>
                <th><?php _e('Actions', 'tapp-campaigns'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($campaigns)): ?>
                <tr>
                    <td colspan="7"><?php _e('No campaigns found.', 'tapp-campaigns'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach ($campaigns as $campaign): ?>
                    <?php
                    $creator = get_user_by('id', $campaign->creator_id);
                    $stats = TAPP_Campaigns_Campaign::get_stats($campaign->id);
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($campaign->name); ?></strong></td>
                        <td><?php echo esc_html(ucfirst($campaign->type)); ?></td>
                        <td><?php echo esc_html(ucfirst($campaign->status)); ?></td>
                        <td><?php echo $creator ? esc_html($creator->display_name) : '-'; ?></td>
                        <td>
                            <?php echo date_i18n(get_option('date_format'), strtotime($campaign->start_date)); ?>
                            -
                            <?php echo date_i18n(get_option('date_format'), strtotime($campaign->end_date)); ?>
                        </td>
                        <td><?php echo $stats['total_submitted'] . ' / ' . $stats['total_invited']; ?></td>
                        <td>
                            <a href="<?php echo esc_url(TAPP_Campaigns_Campaign::get_url($campaign->id)); ?>" class="button button-small">
                                <?php _e('View', 'tapp-campaigns'); ?>
                            </a>
                            <a href="<?php echo esc_url(add_query_arg(['tapp_export' => 'responses', 'campaign_id' => $campaign->id])); ?>" class="button button-small">
                                <?php _e('Export', 'tapp-campaigns'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
