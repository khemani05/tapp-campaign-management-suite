<?php
/**
 * Database Helper Class
 * Provides database query methods
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Database {

    private static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'tapp_' . $table;
    }

    /**
     * Get campaigns with filters
     */
    public static function get_campaigns($args = []) {
        global $wpdb;
        $table = self::get_table_name('campaigns');

        $defaults = [
            'status' => null,
            'type' => null,
            'creator_id' => null,
            'department' => null,
            'search' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];
        $params = [];

        if ($args['status']) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if ($args['type']) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }

        if ($args['creator_id']) {
            $where[] = 'creator_id = %d';
            $params[] = $args['creator_id'];
        }

        if ($args['department']) {
            $where[] = 'department = %s';
            $params[] = $args['department'];
        }

        if ($args['search']) {
            $where[] = '(name LIKE %s OR notes LIKE %s OR description LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $where_clause = implode(' AND ', $where);
        $order_clause = sprintf('%s %s', $args['orderby'], $args['order']);

        $sql = "SELECT * FROM $table WHERE $where_clause ORDER BY $order_clause LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Count campaigns
     */
    public static function count_campaigns($args = []) {
        global $wpdb;
        $table = self::get_table_name('campaigns');

        $where = ['1=1'];
        $params = [];

        if (isset($args['status'])) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if (isset($args['creator_id'])) {
            $where[] = 'creator_id = %d';
            $params[] = $args['creator_id'];
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM $table WHERE $where_clause";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get campaign products
     */
    public static function get_campaign_products($campaign_id) {
        global $wpdb;
        $table = self::get_table_name('campaign_products');

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE campaign_id = %d ORDER BY display_order ASC",
            $campaign_id
        ));
    }

    /**
     * Get campaign participants
     */
    public static function get_participants($campaign_id, $args = []) {
        global $wpdb;
        $table = self::get_table_name('participants');

        $defaults = [
            'status' => null,
            'limit' => 100,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = ['campaign_id = %d'];
        $params = [$campaign_id];

        if ($args['status']) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        $where_clause = implode(' AND ', $where);
        $sql = "SELECT * FROM $table WHERE $where_clause LIMIT %d OFFSET %d";
        $params[] = $args['limit'];
        $params[] = $args['offset'];

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    /**
     * Get user responses for a campaign
     */
    public static function get_user_responses($campaign_id, $user_id, $latest_only = true) {
        global $wpdb;
        $table = self::get_table_name('responses');

        $sql = "SELECT * FROM $table WHERE campaign_id = %d AND user_id = %d";

        if ($latest_only) {
            $sql .= " AND is_latest = 1";
        }

        $sql .= " ORDER BY created_at DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $campaign_id, $user_id));
    }

    /**
     * Get all responses for a campaign
     */
    public static function get_campaign_responses($campaign_id, $latest_only = true) {
        global $wpdb;
        $table = self::get_table_name('responses');

        $sql = "SELECT * FROM $table WHERE campaign_id = %d";

        if ($latest_only) {
            $sql .= " AND is_latest = 1";
        }

        $sql .= " ORDER BY user_id, created_at DESC";

        return $wpdb->get_results($wpdb->prepare($sql, $campaign_id));
    }

    /**
     * Get campaign statistics
     */
    public static function get_campaign_stats($campaign_id) {
        global $wpdb;
        $participants_table = self::get_table_name('participants');
        $responses_table = self::get_table_name('responses');

        $stats = [
            'total_invited' => 0,
            'total_submitted' => 0,
            'pending_count' => 0,
            'participation_rate' => 0,
            'total_items' => 0,
        ];

        // Get participant counts
        $participant_stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_invited,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as total_submitted,
                SUM(CASE WHEN status != 'submitted' THEN 1 ELSE 0 END) as pending_count
            FROM $participants_table
            WHERE campaign_id = %d
        ", $campaign_id));

        if ($participant_stats) {
            $stats['total_invited'] = (int) $participant_stats->total_invited;
            $stats['total_submitted'] = (int) $participant_stats->total_submitted;
            $stats['pending_count'] = (int) $participant_stats->pending_count;

            if ($stats['total_invited'] > 0) {
                $stats['participation_rate'] = round(($stats['total_submitted'] / $stats['total_invited']) * 100, 2);
            }
        }

        // Get total items selected
        $total_items = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(quantity)
            FROM $responses_table
            WHERE campaign_id = %d AND is_latest = 1
        ", $campaign_id));

        $stats['total_items'] = (int) $total_items;

        return $stats;
    }

    /**
     * Check if user is participant
     */
    public static function is_participant($campaign_id, $user_id) {
        global $wpdb;
        $table = self::get_table_name('participants');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE campaign_id = %d AND user_id = %d",
            $campaign_id,
            $user_id
        ));

        return $count > 0;
    }

    /**
     * Check if user has submitted
     */
    public static function has_submitted($campaign_id, $user_id) {
        global $wpdb;
        $table = self::get_table_name('participants');

        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table WHERE campaign_id = %d AND user_id = %d",
            $campaign_id,
            $user_id
        ));

        return $status === 'submitted';
    }
}
