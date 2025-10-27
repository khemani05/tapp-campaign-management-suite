<?php
/**
 * Admin View: Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

$banner_enabled = get_option('tapp_campaigns_banner_enabled', true);
$banner_position = get_option('tapp_campaigns_banner_position', 'before_footer');
$email_from_name = get_option('tapp_campaigns_email_from_name', get_bloginfo('name'));
$email_from_email = get_option('tapp_campaigns_email_from_email', get_option('admin_email'));

?>

<div class="wrap">
    <h1><?php _e('TAPP Campaigns Settings', 'tapp-campaigns'); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('tapp_campaigns_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Banner Display', 'tapp-campaigns'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="banner_enabled" value="1" <?php checked($banner_enabled, true); ?>>
                        <?php _e('Enable campaign banners on homepage', 'tapp-campaigns'); ?>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Banner Position', 'tapp-campaigns'); ?></th>
                <td>
                    <select name="banner_position">
                        <option value="before_footer" <?php selected($banner_position, 'before_footer'); ?>><?php _e('Before Footer', 'tapp-campaigns'); ?></option>
                        <option value="after_header" <?php selected($banner_position, 'after_header'); ?>><?php _e('After Header', 'tapp-campaigns'); ?></option>
                        <option value="after_content" <?php selected($banner_position, 'after_content'); ?>><?php _e('After Content', 'tapp-campaigns'); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Email From Name', 'tapp-campaigns'); ?></th>
                <td>
                    <input type="text" name="email_from_name" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Email From Email', 'tapp-campaigns'); ?></th>
                <td>
                    <input type="email" name="email_from_email" value="<?php echo esc_attr($email_from_email); ?>" class="regular-text">
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="save_settings" class="button button-primary">
                <?php _e('Save Settings', 'tapp-campaigns'); ?>
            </button>
        </p>
    </form>
</div>
