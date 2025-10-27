<?php
/**
 * Cron Jobs Class
 * Handles scheduled tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Cron {

    public function __construct() {
        // Register cron hooks
        add_action('tapp_campaigns_check_scheduled', [$this, 'check_scheduled_campaigns']);
        add_action('tapp_campaigns_check_ending', [$this, 'check_ending_campaigns']);
        add_action('tapp_campaigns_send_reminders', [$this, 'send_reminders']);
    }

    /**
     * Schedule cron events
     */
    public function schedule_events() {
        // Check for scheduled campaigns (every 5 minutes)
        if (!wp_next_scheduled('tapp_campaigns_check_scheduled')) {
            wp_schedule_event(time(), 'tapp_5min', 'tapp_campaigns_check_scheduled');
        }

        // Check for ending campaigns (every 5 minutes)
        if (!wp_next_scheduled('tapp_campaigns_check_ending')) {
            wp_schedule_event(time(), 'tapp_5min', 'tapp_campaigns_check_ending');
        }

        // Send reminders (hourly)
        if (!wp_next_scheduled('tapp_campaigns_send_reminders')) {
            wp_schedule_event(time(), 'hourly', 'tapp_campaigns_send_reminders');
        }

        // Add custom cron schedule
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['tapp_5min'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'tapp-campaigns'),
        ];
        return $schedules;
    }

    /**
     * Check for campaigns that should start
     */
    public function check_scheduled_campaigns() {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        // Find scheduled campaigns that should start now
        $campaigns = $wpdb->get_results($wpdb->prepare("
            SELECT id FROM $table
            WHERE status = 'scheduled'
            AND start_date <= %s
        ", current_time('mysql')));

        foreach ($campaigns as $campaign) {
            TAPP_Campaigns_Campaign::update_status($campaign->id, 'active');
            do_action('tapp_campaigns_campaign_started', $campaign->id);
        }
    }

    /**
     * Check for campaigns that should end
     */
    public function check_ending_campaigns() {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        // Find active campaigns that should end now
        $campaigns = $wpdb->get_results($wpdb->prepare("
            SELECT id FROM $table
            WHERE status = 'active'
            AND end_date <= %s
        ", current_time('mysql')));

        foreach ($campaigns as $campaign) {
            TAPP_Campaigns_Campaign::update_status($campaign->id, 'ended');
            do_action('tapp_campaigns_campaign_ended', $campaign->id);
        }
    }

    /**
     * Send reminder emails
     */
    public function send_reminders() {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        // Find active campaigns ending within reminder window
        $campaigns = $wpdb->get_results($wpdb->prepare("
            SELECT id, reminder_hours FROM $table
            WHERE status = 'active'
            AND send_reminder = 1
            AND end_date > %s
            AND end_date <= DATE_ADD(%s, INTERVAL reminder_hours HOUR)
        ", current_time('mysql'), current_time('mysql')));

        foreach ($campaigns as $campaign) {
            // Check if reminder already sent
            $reminder_sent = get_transient('tapp_reminder_sent_' . $campaign->id);
            if ($reminder_sent) {
                continue;
            }

            // Get pending participants
            $participants = TAPP_Campaigns_Participant::get_pending($campaign->id);

            foreach ($participants as $participant) {
                TAPP_Campaigns_Email::send_reminder($campaign->id, $participant->user_id);
            }

            // Mark reminder as sent (don't send again)
            set_transient('tapp_reminder_sent_' . $campaign->id, true, 24 * HOUR_IN_SECONDS);

            do_action('tapp_campaigns_reminders_sent', $campaign->id);
        }
    }
}
