<?php
/**
 * Plugin Deactivator
 * Handles plugin deactivation
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Deactivator {

    public static function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('tapp_campaigns_check_scheduled');
        wp_clear_scheduled_hook('tapp_campaigns_check_ending');
        wp_clear_scheduled_hook('tapp_campaigns_send_reminders');
        wp_clear_scheduled_hook('tapp_campaigns_process_email_queue');

        // Flush rewrite rules
        flush_rewrite_rules();

        // Note: We don't delete data on deactivation
        // Data should only be deleted via uninstall.php
    }
}
