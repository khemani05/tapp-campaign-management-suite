<?php
/**
 * Admin Settings Page View
 * Configuration options for TAPP Campaigns
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tapp_save_settings'])) {
    check_admin_referer('tapp_settings');

    // General Settings
    update_option('tapp_campaigns_banner_enabled', isset($_POST['banner_enabled']) ? 1 : 0);
    update_option('tapp_campaigns_banner_style', sanitize_text_field($_POST['banner_style']));
    update_option('tapp_campaigns_max_banners', intval($_POST['max_banners']));

    // Email Settings
    update_option('tapp_campaigns_email_from_name', sanitize_text_field($_POST['email_from_name']));
    update_option('tapp_campaigns_email_from_email', sanitize_email($_POST['email_from_email']));
    update_option('tapp_campaigns_send_invitations', isset($_POST['send_invitations']) ? 1 : 0);
    update_option('tapp_campaigns_send_confirmations', isset($_POST['send_confirmations']) ? 1 : 0);
    update_option('tapp_campaigns_send_reminders', isset($_POST['send_reminders']) ? 1 : 0);
    update_option('tapp_campaigns_reminder_hours', intval($_POST['reminder_hours']));

    // Invitation Email Template
    update_option('tapp_campaigns_invitation_subject', sanitize_text_field($_POST['invitation_subject']));
    update_option('tapp_campaigns_invitation_body', wp_kses_post($_POST['invitation_body']));

    // Confirmation Email Template
    update_option('tapp_campaigns_confirmation_subject', sanitize_text_field($_POST['confirmation_subject']));
    update_option('tapp_campaigns_confirmation_body', wp_kses_post($_POST['confirmation_body']));

    // Campaign Defaults
    update_option('tapp_campaigns_default_template', sanitize_text_field($_POST['default_template']));
    update_option('tapp_campaigns_default_selection_limit', intval($_POST['default_selection_limit']));
    update_option('tapp_campaigns_default_edit_policy', sanitize_text_field($_POST['default_edit_policy']));

    // Advanced Settings
    update_option('tapp_campaigns_enable_analytics', isset($_POST['enable_analytics']) ? 1 : 0);
    update_option('tapp_campaigns_auto_archive_days', intval($_POST['auto_archive_days']));
    update_option('tapp_campaigns_enable_cache', isset($_POST['enable_cache']) ? 1 : 0);

    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'tapp-campaigns') . '</p></div>';
}

// Get current settings
$banner_enabled = get_option('tapp_campaigns_banner_enabled', 1);
$banner_style = get_option('tapp_campaigns_banner_style', 'banner');
$max_banners = get_option('tapp_campaigns_max_banners', 3);

$email_from_name = get_option('tapp_campaigns_email_from_name', get_bloginfo('name'));
$email_from_email = get_option('tapp_campaigns_email_from_email', get_option('admin_email'));
$send_invitations = get_option('tapp_campaigns_send_invitations', 1);
$send_confirmations = get_option('tapp_campaigns_send_confirmations', 1);
$send_reminders = get_option('tapp_campaigns_send_reminders', 1);
$reminder_hours = get_option('tapp_campaigns_reminder_hours', 24);

$invitation_subject = get_option('tapp_campaigns_invitation_subject', __('You\'re invited to participate in {campaign_name}', 'tapp-campaigns'));
$invitation_body = get_option('tapp_campaigns_invitation_body', __('Hi {user_name},

You have been invited to participate in the campaign: {campaign_name}

Campaign Details:
{campaign_description}

Please submit your selections by {end_date}.

Click here to get started: {campaign_url}

Thank you!', 'tapp-campaigns'));

$confirmation_subject = get_option('tapp_campaigns_confirmation_subject', __('Thank you for your submission - {campaign_name}', 'tapp-campaigns'));
$confirmation_body = get_option('tapp_campaigns_confirmation_body', __('Hi {user_name},

Thank you for submitting your selections for: {campaign_name}

Your response has been recorded. You selected {selection_count} item(s).

If you have any questions, please contact your campaign manager.

Thank you!', 'tapp-campaigns'));

$default_template = get_option('tapp_campaigns_default_template', 'classic');
$default_selection_limit = get_option('tapp_campaigns_default_selection_limit', 1);
$default_edit_policy = get_option('tapp_campaigns_default_edit_policy', 'once');

$enable_analytics = get_option('tapp_campaigns_enable_analytics', 1);
$auto_archive_days = get_option('tapp_campaigns_auto_archive_days', 30);
$enable_cache = get_option('tapp_campaigns_enable_cache', 1);

?>

<div class="wrap tapp-settings-page">
    <h1><?php _e('TAPP Campaigns Settings', 'tapp-campaigns'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('tapp_settings'); ?>
        <input type="hidden" name="tapp_save_settings" value="1">

        <div class="tapp-settings-tabs">
            <nav class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'tapp-campaigns'); ?></a>
                <a href="#emails" class="nav-tab"><?php _e('Email Settings', 'tapp-campaigns'); ?></a>
                <a href="#templates" class="nav-tab"><?php _e('Email Templates', 'tapp-campaigns'); ?></a>
                <a href="#defaults" class="nav-tab"><?php _e('Campaign Defaults', 'tapp-campaigns'); ?></a>
                <a href="#advanced" class="nav-tab"><?php _e('Advanced', 'tapp-campaigns'); ?></a>
            </nav>

            <!-- General Settings Tab -->
            <div id="general" class="tab-content active">
                <h2><?php _e('General Settings', 'tapp-campaigns'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Homepage Banners', 'tapp-campaigns'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="banner_enabled" value="1" <?php checked($banner_enabled, 1); ?>>
                                <?php _e('Enable campaign banners on homepage', 'tapp-campaigns'); ?>
                            </label>
                            <p class="description"><?php _e('Show pending campaigns to invited users on the homepage.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Banner Style', 'tapp-campaigns'); ?></th>
                        <td>
                            <select name="banner_style">
                                <option value="banner" <?php selected($banner_style, 'banner'); ?>><?php _e('Full Banner', 'tapp-campaigns'); ?></option>
                                <option value="compact" <?php selected($banner_style, 'compact'); ?>><?php _e('Compact', 'tapp-campaigns'); ?></option>
                                <option value="minimal" <?php selected($banner_style, 'minimal'); ?>><?php _e('Minimal', 'tapp-campaigns'); ?></option>
                            </select>
                            <p class="description"><?php _e('Choose the banner display style.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Max Banners', 'tapp-campaigns'); ?></th>
                        <td>
                            <input type="number" name="max_banners" value="<?php echo esc_attr($max_banners); ?>" min="1" max="10" class="small-text">
                            <p class="description"><?php _e('Maximum number of campaign banners to display simultaneously.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Email Settings Tab -->
            <div id="emails" class="tab-content">
                <h2><?php _e('Email Settings', 'tapp-campaigns'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('From Name', 'tapp-campaigns'); ?></th>
                        <td>
                            <input type="text" name="email_from_name" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text">
                            <p class="description"><?php _e('The name that appears in campaign emails.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('From Email', 'tapp-campaigns'); ?></th>
                        <td>
                            <input type="email" name="email_from_email" value="<?php echo esc_attr($email_from_email); ?>" class="regular-text">
                            <p class="description"><?php _e('The email address used to send campaign emails.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Email Notifications', 'tapp-campaigns'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="send_invitations" value="1" <?php checked($send_invitations, 1); ?>>
                                    <?php _e('Send invitation emails when users are added to campaigns', 'tapp-campaigns'); ?>
                                </label><br>

                                <label>
                                    <input type="checkbox" name="send_confirmations" value="1" <?php checked($send_confirmations, 1); ?>>
                                    <?php _e('Send confirmation emails when users submit selections', 'tapp-campaigns'); ?>
                                </label><br>

                                <label>
                                    <input type="checkbox" name="send_reminders" value="1" <?php checked($send_reminders, 1); ?>>
                                    <?php _e('Send reminder emails to users who haven\'t submitted', 'tapp-campaigns'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Reminder Timing', 'tapp-campaigns'); ?></th>
                        <td>
                            <input type="number" name="reminder_hours" value="<?php echo esc_attr($reminder_hours); ?>" min="1" max="168" class="small-text">
                            <?php _e('hours before campaign ends', 'tapp-campaigns'); ?>
                            <p class="description"><?php _e('Send reminder emails this many hours before the campaign deadline.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Email Templates Tab -->
            <div id="templates" class="tab-content">
                <h2><?php _e('Email Templates', 'tapp-campaigns'); ?></h2>
                <p class="description"><?php _e('Available variables: {user_name}, {campaign_name}, {campaign_url}, {campaign_description}, {end_date}, {selection_count}', 'tapp-campaigns'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Invitation Subject', 'tapp-campaigns'); ?></th>
                        <td>
                            <input type="text" name="invitation_subject" value="<?php echo esc_attr($invitation_subject); ?>" class="large-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Invitation Body', 'tapp-campaigns'); ?></th>
                        <td>
                            <textarea name="invitation_body" rows="10" class="large-text code"><?php echo esc_textarea($invitation_body); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Confirmation Subject', 'tapp-campaigns'); ?></th>
                        <td>
                            <input type="text" name="confirmation_subject" value="<?php echo esc_attr($confirmation_subject); ?>" class="large-text">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Confirmation Body', 'tapp-campaigns'); ?></th>
                        <td>
                            <textarea name="confirmation_body" rows="10" class="large-text code"><?php echo esc_textarea($confirmation_body); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Campaign Defaults Tab -->
            <div id="defaults" class="tab-content">
                <h2><?php _e('Campaign Defaults', 'tapp-campaigns'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Template', 'tapp-campaigns'); ?></th>
                        <td>
                            <select name="default_template">
                                <option value="classic" <?php selected($default_template, 'classic'); ?>><?php _e('Classic Grid', 'tapp-campaigns'); ?></option>
                                <option value="modern" <?php selected($default_template, 'modern'); ?>><?php _e('Modern Carousel', 'tapp-campaigns'); ?></option>
                                <option value="minimal" <?php selected($default_template, 'minimal'); ?>><?php _e('Minimal List', 'tapp-campaigns'); ?></option>
                                <option value="hero" <?php selected($default_template, 'hero'); ?>><?php _e('Hero Banner', 'tapp-campaigns'); ?></option>
                            </select>
                            <p class="description"><?php _e('Default campaign page template for new campaigns.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Default Selection Limit', 'tapp-campaigns'); ?></th>
                        <td>
                            <input type="number" name="default_selection_limit" value="<?php echo esc_attr($default_selection_limit); ?>" min="1" max="100" class="small-text">
                            <p class="description"><?php _e('Default number of items users can select.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Default Edit Policy', 'tapp-campaigns'); ?></th>
                        <td>
                            <select name="default_edit_policy">
                                <option value="once" <?php selected($default_edit_policy, 'once'); ?>><?php _e('One-time submission', 'tapp-campaigns'); ?></option>
                                <option value="multiple" <?php selected($default_edit_policy, 'multiple'); ?>><?php _e('Multiple edits allowed', 'tapp-campaigns'); ?></option>
                                <option value="until_end" <?php selected($default_edit_policy, 'until_end'); ?>><?php _e('Edit until campaign ends', 'tapp-campaigns'); ?></option>
                            </select>
                            <p class="description"><?php _e('Default policy for allowing users to edit their submissions.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Advanced Settings Tab -->
            <div id="advanced" class="tab-content">
                <h2><?php _e('Advanced Settings', 'tapp-campaigns'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Analytics', 'tapp-campaigns'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_analytics" value="1" <?php checked($enable_analytics, 1); ?>>
                                <?php _e('Enable campaign analytics and reporting', 'tapp-campaigns'); ?>
                            </label>
                            <p class="description"><?php _e('Track detailed campaign performance metrics.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Auto-Archive', 'tapp-campaigns'); ?></th>
                        <td>
                            <input type="number" name="auto_archive_days" value="<?php echo esc_attr($auto_archive_days); ?>" min="0" max="365" class="small-text">
                            <?php _e('days after ending', 'tapp-campaigns'); ?>
                            <p class="description"><?php _e('Automatically archive campaigns this many days after they end. Set to 0 to disable.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php _e('Performance', 'tapp-campaigns'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_cache" value="1" <?php checked($enable_cache, 1); ?>>
                                <?php _e('Enable caching for improved performance', 'tapp-campaigns'); ?>
                            </label>
                            <p class="description"><?php _e('Cache campaign data to reduce database queries.', 'tapp-campaigns'); ?></p>
                        </td>
                    </tr>
                </table>

                <h3><?php _e('System Information', 'tapp-campaigns'); ?></h3>
                <table class="widefat">
                    <tr>
                        <td><strong><?php _e('Plugin Version:', 'tapp-campaigns'); ?></strong></td>
                        <td><?php echo TAPP_CAMPAIGNS_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('WordPress Version:', 'tapp-campaigns'); ?></strong></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('WooCommerce Version:', 'tapp-campaigns'); ?></strong></td>
                        <td><?php echo defined('WC_VERSION') ? WC_VERSION : __('Not detected', 'tapp-campaigns'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Total Campaigns:', 'tapp-campaigns'); ?></strong></td>
                        <td><?php echo TAPP_Campaigns_Database::count_campaigns(); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                <?php _e('Save Settings', 'tapp-campaigns'); ?>
            </button>
        </p>
    </form>
</div>

<style>
.tapp-settings-page {
    max-width: 1200px;
}

.nav-tab-wrapper {
    margin-bottom: 20px;
}

.tab-content {
    display: none;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-top: none;
}

.tab-content.active {
    display: block;
}

.tab-content h2 {
    margin-top: 0;
}

.widefat td {
    padding: 10px;
}

.widefat tr:nth-child(even) {
    background: #f9f9f9;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').removeClass('active');
        $(target).addClass('active');
    });
});
</script>
