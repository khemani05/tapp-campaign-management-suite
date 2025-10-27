<?php
/**
 * Email Class
 * Handles email notifications (MVP - Direct sending)
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Email {

    /**
     * Send invitation email
     */
    public static function send_invitation($campaign_id, $user_id) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
        $user = get_user_by('id', $user_id);

        if (!$campaign || !$user) {
            return false;
        }

        $to = $user->user_email;
        $subject = sprintf(__('[%s] You\'re Invited: %s', 'tapp-campaigns'), get_bloginfo('name'), $campaign->name);

        $campaign_url = TAPP_Campaigns_Campaign::get_url($campaign_id);

        $message = self::get_email_template('invitation', [
            'user_name' => $user->display_name,
            'campaign_name' => $campaign->name,
            'campaign_type' => ucfirst($campaign->type),
            'start_date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->start_date)),
            'end_date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->end_date)),
            'selection_limit' => $campaign->selection_limit,
            'notes' => $campaign->notes,
            'campaign_url' => $campaign_url,
        ]);

        $headers = self::get_headers();

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send confirmation email
     */
    public static function send_confirmation($campaign_id, $user_id) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
        $user = get_user_by('id', $user_id);

        if (!$campaign || !$user) {
            return false;
        }

        $responses = TAPP_Campaigns_Response::get_latest($campaign_id, $user_id);

        $to = $user->user_email;
        $subject = sprintf(__('[%s] Response Confirmed: %s', 'tapp-campaigns'), get_bloginfo('name'), $campaign->name);

        $selections_html = '';
        foreach ($responses as $response) {
            $product = wc_get_product($response->product_id);
            if ($product) {
                $selections_html .= sprintf(
                    "<li>%s%s%s (Qty: %d)</li>",
                    $product->get_name(),
                    $response->color ? ' - ' . $response->color : '',
                    $response->size ? ' - ' . $response->size : '',
                    $response->quantity
                );
            }
        }

        $message = self::get_email_template('confirmation', [
            'user_name' => $user->display_name,
            'campaign_name' => $campaign->name,
            'selections' => $selections_html,
            'submitted_at' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
            'campaign_url' => TAPP_Campaigns_Campaign::get_url($campaign_id),
        ]);

        $headers = self::get_headers();

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send reminder email
     */
    public static function send_reminder($campaign_id, $user_id) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
        $user = get_user_by('id', $user_id);

        if (!$campaign || !$user) {
            return false;
        }

        $to = $user->user_email;
        $subject = sprintf(__('[%s] Reminder: Campaign Ending Soon - %s', 'tapp-campaigns'), get_bloginfo('name'), $campaign->name);

        $campaign_url = TAPP_Campaigns_Campaign::get_url($campaign_id);

        $time_remaining = human_time_diff(time(), strtotime($campaign->end_date));

        $message = self::get_email_template('reminder', [
            'user_name' => $user->display_name,
            'campaign_name' => $campaign->name,
            'time_remaining' => $time_remaining,
            'end_date' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->end_date)),
            'campaign_url' => $campaign_url,
        ]);

        $headers = self::get_headers();

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get email headers
     */
    private static function get_headers() {
        $from_name = get_option('tapp_campaigns_email_from_name', get_bloginfo('name'));
        $from_email = get_option('tapp_campaigns_email_from_email', get_option('admin_email'));

        return [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email),
        ];
    }

    /**
     * Get email template
     */
    private static function get_email_template($type, $data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #0073aa; color: #fff; padding: 20px; text-align: center; }
                .content { padding: 30px 20px; background-color: #f5f5f5; }
                .button { display: inline-block; padding: 12px 30px; background-color: #0073aa; color: #fff; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                ul { padding-left: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo get_bloginfo('name'); ?></h1>
                </div>
                <div class="content">
                    <?php
                    switch ($type) {
                        case 'invitation':
                            ?>
                            <h2><?php _e('You\'re Invited to a Campaign!', 'tapp-campaigns'); ?></h2>
                            <p><?php printf(__('Hi %s,', 'tapp-campaigns'), esc_html($data['user_name'])); ?></p>
                            <p><?php _e('You have been invited to participate in:', 'tapp-campaigns'); ?></p>
                            <h3><?php echo esc_html($data['campaign_name']); ?></h3>
                            <p><strong><?php _e('Type:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['campaign_type']); ?></p>
                            <p><strong><?php _e('Start Date:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['start_date']); ?></p>
                            <p><strong><?php _e('End Date:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['end_date']); ?></p>
                            <p><strong><?php _e('Selection Limit:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['selection_limit']); ?> <?php _e('items', 'tapp-campaigns'); ?></p>
                            <?php if ($data['notes']): ?>
                                <p><em><?php echo esc_html($data['notes']); ?></em></p>
                            <?php endif; ?>
                            <p><a href="<?php echo esc_url($data['campaign_url']); ?>" class="button"><?php _e('View Campaign', 'tapp-campaigns'); ?></a></p>
                            <?php
                            break;

                        case 'confirmation':
                            ?>
                            <h2><?php _e('Response Confirmed!', 'tapp-campaigns'); ?></h2>
                            <p><?php printf(__('Hi %s,', 'tapp-campaigns'), esc_html($data['user_name'])); ?></p>
                            <p><?php _e('Thank you for submitting your response to:', 'tapp-campaigns'); ?></p>
                            <h3><?php echo esc_html($data['campaign_name']); ?></h3>
                            <p><strong><?php _e('Your Selections:', 'tapp-campaigns'); ?></strong></p>
                            <ul><?php echo $data['selections']; ?></ul>
                            <p><strong><?php _e('Submitted:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['submitted_at']); ?></p>
                            <p><a href="<?php echo esc_url($data['campaign_url']); ?>" class="button"><?php _e('View Campaign', 'tapp-campaigns'); ?></a></p>
                            <?php
                            break;

                        case 'reminder':
                            ?>
                            <h2><?php _e('Campaign Ending Soon!', 'tapp-campaigns'); ?></h2>
                            <p><?php printf(__('Hi %s,', 'tapp-campaigns'), esc_html($data['user_name'])); ?></p>
                            <p><?php _e('This is a reminder that the following campaign is ending soon:', 'tapp-campaigns'); ?></p>
                            <h3><?php echo esc_html($data['campaign_name']); ?></h3>
                            <p><strong><?php _e('Time Remaining:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['time_remaining']); ?></p>
                            <p><strong><?php _e('End Date:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['end_date']); ?></p>
                            <p><?php _e('Don\'t miss out! Submit your response now.', 'tapp-campaigns'); ?></p>
                            <p><a href="<?php echo esc_url($data['campaign_url']); ?>" class="button"><?php _e('Submit Response Now', 'tapp-campaigns'); ?></a></p>
                            <?php
                            break;
                    }
                    ?>
                </div>
                <div class="footer">
                    <p><?php printf(__('&copy; %s %s. All rights reserved.', 'tapp-campaigns'), date('Y'), get_bloginfo('name')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
