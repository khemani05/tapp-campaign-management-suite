<?php
/**
 * Participant Model Class
 * Handles campaign participants
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Participant {

    /**
     * Add participant to campaign
     */
    public static function add($campaign_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_participants';

        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        // Check if already participant
        if (self::is_participant($campaign_id, $user_id)) {
            return true;
        }

        $result = $wpdb->insert($table, [
            'campaign_id' => $campaign_id,
            'user_id' => $user_id,
            'email' => $user->user_email,
            'status' => 'invited',
        ]);

        if ($result) {
            do_action('tapp_campaigns_participant_added', $campaign_id, $user_id);
        }

        return $result !== false;
    }

    /**
     * Add multiple participants
     */
    public static function add_multiple($campaign_id, $user_ids) {
        $added = 0;
        foreach ($user_ids as $user_id) {
            if (self::add($campaign_id, $user_id)) {
                $added++;
            }
        }
        return $added;
    }

    /**
     * Remove participant from campaign
     */
    public static function remove($campaign_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_participants';

        $result = $wpdb->delete($table, [
            'campaign_id' => $campaign_id,
            'user_id' => $user_id,
        ]);

        if ($result) {
            do_action('tapp_campaigns_participant_removed', $campaign_id, $user_id);
        }

        return $result !== false;
    }

    /**
     * Check if user is participant
     */
    public static function is_participant($campaign_id, $user_id) {
        return TAPP_Campaigns_Database::is_participant($campaign_id, $user_id);
    }

    /**
     * Get participant data
     */
    public static function get($campaign_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_participants';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE campaign_id = %d AND user_id = %d",
            $campaign_id,
            $user_id
        ));
    }

    /**
     * Get all participants for a campaign
     */
    public static function get_all($campaign_id, $args = []) {
        return TAPP_Campaigns_Database::get_participants($campaign_id, $args);
    }

    /**
     * Update participant status
     */
    public static function update_status($campaign_id, $user_id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_participants';

        $data = ['status' => $status];

        if ($status === 'submitted') {
            $data['submitted_at'] = current_time('mysql');
        }

        $result = $wpdb->update(
            $table,
            $data,
            [
                'campaign_id' => $campaign_id,
                'user_id' => $user_id,
            ]
        );

        return $result !== false;
    }

    /**
     * Increment response count
     */
    public static function increment_response_count($campaign_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_participants';

        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET response_count = response_count + 1 WHERE campaign_id = %d AND user_id = %d",
            $campaign_id,
            $user_id
        ));
    }

    /**
     * Get participants who haven't submitted
     */
    public static function get_pending($campaign_id) {
        return self::get_all($campaign_id, ['status' => 'invited']);
    }

    /**
     * Get participants who have submitted
     */
    public static function get_submitted($campaign_id) {
        return self::get_all($campaign_id, ['status' => 'submitted']);
    }

    /**
     * Count participants
     */
    public static function count($campaign_id, $status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_participants';

        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE campaign_id = %d AND status = %s",
                $campaign_id,
                $status
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE campaign_id = %d",
            $campaign_id
        ));
    }
}
