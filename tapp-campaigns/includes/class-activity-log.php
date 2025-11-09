<?php
/**
 * Activity Log Class
 * Logs all campaign-related activities for audit trail and GDPR compliance
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Activity_Log {

    /**
     * Log an activity
     *
     * @param string $action Action name (e.g., 'campaign_created', 'response_submitted')
     * @param string $action_type Type of action (campaign, participant, response, template, group, system)
     * @param string $description Human-readable description
     * @param int|null $campaign_id Campaign ID (if applicable)
     * @param int|null $user_id User ID (if applicable, defaults to current user)
     * @param array $metadata Additional metadata to store
     * @return int|false Log ID or false on failure
     */
    public static function log($action, $action_type, $description, $campaign_id = null, $user_id = null, $metadata = []) {
        global $wpdb;

        // Get current user if not provided
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        // Get IP address
        $ip_address = self::get_client_ip();

        // Get user agent
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : '';

        // Prepare metadata
        $metadata_json = !empty($metadata) ? wp_json_encode($metadata) : null;

        $table = $wpdb->prefix . 'tapp_activity_log';

        $result = $wpdb->insert(
            $table,
            [
                'campaign_id' => $campaign_id,
                'user_id' => $user_id ?: null,
                'action' => sanitize_text_field($action),
                'action_type' => sanitize_text_field($action_type),
                'description' => sanitize_text_field($description),
                'metadata' => $metadata_json,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get activity logs with filtering
     *
     * @param array $args Query arguments
     * @return array Activity logs
     */
    public static function get_logs($args = []) {
        global $wpdb;

        $defaults = [
            'campaign_id' => null,
            'user_id' => null,
            'action_type' => null,
            'action' => null,
            'limit' => 100,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $table = $wpdb->prefix . 'tapp_activity_log';
        $where_clauses = [];
        $where_values = [];

        // Build WHERE clause
        if ($args['campaign_id']) {
            $where_clauses[] = 'campaign_id = %d';
            $where_values[] = $args['campaign_id'];
        }

        if ($args['user_id']) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if ($args['action_type']) {
            $where_clauses[] = 'action_type = %s';
            $where_values[] = $args['action_type'];
        }

        if ($args['action']) {
            $where_clauses[] = 'action = %s';
            $where_values[] = $args['action'];
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        // Build ORDER BY clause
        $order_by = sanitize_sql_orderby($args['order_by'] . ' ' . $args['order']);
        if (!$order_by) {
            $order_by = 'created_at DESC';
        }

        // Build LIMIT clause
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);

        // Build query
        $query = "SELECT * FROM {$table} {$where_sql} ORDER BY {$order_by} LIMIT {$offset}, {$limit}";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        $logs = $wpdb->get_results($query);

        // Enrich logs with user data
        foreach ($logs as $log) {
            if ($log->user_id) {
                $user = get_user_by('id', $log->user_id);
                if ($user) {
                    $log->user_name = $user->display_name;
                    $log->user_email = $user->user_email;
                }
            }

            if ($log->campaign_id) {
                $campaign = TAPP_Campaigns_Campaign::get($log->campaign_id);
                if ($campaign) {
                    $log->campaign_name = $campaign->name;
                }
            }

            // Decode metadata
            if ($log->metadata) {
                $log->metadata = json_decode($log->metadata, true);
            }
        }

        return $logs;
    }

    /**
     * Get total count of logs
     *
     * @param array $args Query arguments (same as get_logs)
     * @return int Total count
     */
    public static function count_logs($args = []) {
        global $wpdb;

        $table = $wpdb->prefix . 'tapp_activity_log';
        $where_clauses = [];
        $where_values = [];

        // Build WHERE clause (same logic as get_logs)
        if (!empty($args['campaign_id'])) {
            $where_clauses[] = 'campaign_id = %d';
            $where_values[] = $args['campaign_id'];
        }

        if (!empty($args['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }

        if (!empty($args['action_type'])) {
            $where_clauses[] = 'action_type = %s';
            $where_values[] = $args['action_type'];
        }

        if (!empty($args['action'])) {
            $where_clauses[] = 'action = %s';
            $where_values[] = $args['action'];
        }

        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        $query = "SELECT COUNT(*) FROM {$table} {$where_sql}";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Delete logs older than specified days
     * For GDPR compliance - auto-cleanup old logs
     *
     * @param int $days Number of days to retain
     * @return int|false Number of deleted rows or false
     */
    public static function cleanup_old_logs($days = 90) {
        global $wpdb;

        $table = $wpdb->prefix . 'tapp_activity_log';
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $date
            )
        );

        return $result;
    }

    /**
     * Delete logs for a specific user
     * For GDPR compliance - right to be forgotten
     *
     * @param int $user_id User ID
     * @return int|false Number of deleted rows or false
     */
    public static function delete_user_logs($user_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'tapp_activity_log';

        $result = $wpdb->delete(
            $table,
            ['user_id' => $user_id],
            ['%d']
        );

        return $result;
    }

    /**
     * Delete logs for a specific campaign
     *
     * @param int $campaign_id Campaign ID
     * @return int|false Number of deleted rows or false
     */
    public static function delete_campaign_logs($campaign_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'tapp_activity_log';

        $result = $wpdb->delete(
            $table,
            ['campaign_id' => $campaign_id],
            ['%d']
        );

        return $result;
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip = '';

        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Sanitize and validate
        $ip = filter_var($ip, FILTER_VALIDATE_IP);

        return $ip ?: '';
    }

    /**
     * Export logs to CSV
     *
     * @param array $args Query arguments
     * @return string CSV content
     */
    public static function export_to_csv($args = []) {
        $logs = self::get_logs(array_merge($args, ['limit' => 10000]));

        $csv = [];
        $csv[] = ['ID', 'Date/Time', 'User', 'Campaign', 'Action', 'Type', 'Description', 'IP Address'];

        foreach ($logs as $log) {
            $csv[] = [
                $log->id,
                $log->created_at,
                isset($log->user_name) ? $log->user_name : 'N/A',
                isset($log->campaign_name) ? $log->campaign_name : 'N/A',
                $log->action,
                $log->action_type,
                $log->description,
                $log->ip_address,
            ];
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        return $csv_content;
    }

    // ==================== Helper Methods for Common Actions ====================

    /**
     * Log campaign creation
     */
    public static function log_campaign_created($campaign_id, $user_id = null) {
        return self::log(
            'campaign_created',
            'campaign',
            'Campaign created',
            $campaign_id,
            $user_id
        );
    }

    /**
     * Log campaign updated
     */
    public static function log_campaign_updated($campaign_id, $user_id = null, $changes = []) {
        return self::log(
            'campaign_updated',
            'campaign',
            'Campaign updated',
            $campaign_id,
            $user_id,
            $changes
        );
    }

    /**
     * Log campaign deleted
     */
    public static function log_campaign_deleted($campaign_id, $campaign_name, $user_id = null) {
        return self::log(
            'campaign_deleted',
            'campaign',
            sprintf('Campaign "%s" deleted', $campaign_name),
            null, // Campaign no longer exists
            $user_id,
            ['campaign_id' => $campaign_id, 'campaign_name' => $campaign_name]
        );
    }

    /**
     * Log campaign status change
     */
    public static function log_campaign_status_changed($campaign_id, $old_status, $new_status, $user_id = null) {
        return self::log(
            'campaign_status_changed',
            'campaign',
            sprintf('Status changed from %s to %s', $old_status, $new_status),
            $campaign_id,
            $user_id,
            ['old_status' => $old_status, 'new_status' => $new_status]
        );
    }

    /**
     * Log participant added
     */
    public static function log_participant_added($campaign_id, $participant_user_id, $added_by_user_id = null) {
        $user = get_user_by('id', $participant_user_id);
        return self::log(
            'participant_added',
            'participant',
            sprintf('Participant %s added', $user ? $user->display_name : '#' . $participant_user_id),
            $campaign_id,
            $added_by_user_id,
            ['participant_user_id' => $participant_user_id]
        );
    }

    /**
     * Log participant removed
     */
    public static function log_participant_removed($campaign_id, $participant_user_id, $removed_by_user_id = null) {
        $user = get_user_by('id', $participant_user_id);
        return self::log(
            'participant_removed',
            'participant',
            sprintf('Participant %s removed', $user ? $user->display_name : '#' . $participant_user_id),
            $campaign_id,
            $removed_by_user_id,
            ['participant_user_id' => $participant_user_id]
        );
    }

    /**
     * Log response submitted
     */
    public static function log_response_submitted($campaign_id, $user_id, $product_count) {
        return self::log(
            'response_submitted',
            'response',
            sprintf('Response submitted with %d product(s)', $product_count),
            $campaign_id,
            $user_id,
            ['product_count' => $product_count]
        );
    }

    /**
     * Log response updated
     */
    public static function log_response_updated($campaign_id, $user_id, $product_count) {
        return self::log(
            'response_updated',
            'response',
            sprintf('Response updated with %d product(s)', $product_count),
            $campaign_id,
            $user_id,
            ['product_count' => $product_count]
        );
    }

    /**
     * Log response deleted
     */
    public static function log_response_deleted($campaign_id, $target_user_id, $deleted_by_user_id = null) {
        $user = get_user_by('id', $target_user_id);
        return self::log(
            'response_deleted',
            'response',
            sprintf('Response from %s deleted', $user ? $user->display_name : '#' . $target_user_id),
            $campaign_id,
            $deleted_by_user_id,
            ['target_user_id' => $target_user_id]
        );
    }

    /**
     * Log reminder sent
     */
    public static function log_reminder_sent($campaign_id, $recipient_count, $sent_by_user_id = null) {
        return self::log(
            'reminder_sent',
            'campaign',
            sprintf('Reminder sent to %d participant(s)', $recipient_count),
            $campaign_id,
            $sent_by_user_id,
            ['recipient_count' => $recipient_count]
        );
    }

    /**
     * Log template created
     */
    public static function log_template_created($template_id, $template_name, $user_id = null) {
        return self::log(
            'template_created',
            'template',
            sprintf('Template "%s" created', $template_name),
            null,
            $user_id,
            ['template_id' => $template_id, 'template_name' => $template_name]
        );
    }

    /**
     * Log template used
     */
    public static function log_template_used($template_id, $template_name, $campaign_id, $user_id = null) {
        return self::log(
            'template_used',
            'template',
            sprintf('Template "%s" used', $template_name),
            $campaign_id,
            $user_id,
            ['template_id' => $template_id, 'template_name' => $template_name]
        );
    }

    /**
     * Log group created
     */
    public static function log_group_created($group_id, $group_name, $user_id = null) {
        return self::log(
            'group_created',
            'group',
            sprintf('User group "%s" created', $group_name),
            null,
            $user_id,
            ['group_id' => $group_id, 'group_name' => $group_name]
        );
    }

    /**
     * Log CSV export
     */
    public static function log_csv_exported($campaign_id, $export_type, $user_id = null) {
        return self::log(
            'csv_exported',
            'campaign',
            sprintf('%s CSV exported', ucfirst($export_type)),
            $campaign_id,
            $user_id,
            ['export_type' => $export_type]
        );
    }
}
