<?php
/**
 * Response Model Class
 * Handles user responses/submissions with version tracking
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Response {

    /**
     * Create a new response (submission)
     */
    public static function create($campaign_id, $user_id, $selections, $edited_by = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_responses';

        // Get current version number
        $current_version = self::get_latest_version($campaign_id, $user_id);
        $new_version = $current_version + 1;

        // Mark previous responses as not latest
        if ($current_version > 0) {
            $wpdb->update(
                $table,
                ['is_latest' => 0],
                [
                    'campaign_id' => $campaign_id,
                    'user_id' => $user_id,
                ],
                ['%d'],
                ['%d', '%d']
            );
        }

        // Insert new responses
        foreach ($selections as $selection) {
            $data = [
                'campaign_id' => $campaign_id,
                'user_id' => $user_id,
                'product_id' => $selection['product_id'],
                'variation_id' => $selection['variation_id'] ?? 0,
                'color' => $selection['color'] ?? null,
                'size' => $selection['size'] ?? null,
                'quantity' => $selection['quantity'] ?? 1,
                'version' => $new_version,
                'is_latest' => 1,
                'edited_by' => $edited_by,
            ];

            $wpdb->insert($table, $data);
        }

        // Update participant status
        TAPP_Campaigns_Participant::update_status($campaign_id, $user_id, 'submitted');
        TAPP_Campaigns_Participant::increment_response_count($campaign_id, $user_id);

        // Clear cache
        wp_cache_delete('responses_' . $campaign_id . '_' . $user_id, 'tapp_campaigns');

        do_action('tapp_campaigns_response_created', $campaign_id, $user_id, $new_version);

        return true;
    }

    /**
     * Get user's latest responses
     */
    public static function get_latest($campaign_id, $user_id) {
        return TAPP_Campaigns_Database::get_user_responses($campaign_id, $user_id, true);
    }

    /**
     * Get all versions of user's responses
     */
    public static function get_all_versions($campaign_id, $user_id) {
        return TAPP_Campaigns_Database::get_user_responses($campaign_id, $user_id, false);
    }

    /**
     * Get latest version number
     */
    public static function get_latest_version($campaign_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_responses';

        $version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version) FROM $table WHERE campaign_id = %d AND user_id = %d",
            $campaign_id,
            $user_id
        ));

        return (int) $version;
    }

    /**
     * Check if user has submitted
     */
    public static function has_submitted($campaign_id, $user_id) {
        return TAPP_Campaigns_Database::has_submitted($campaign_id, $user_id);
    }

    /**
     * Delete user's responses
     */
    public static function delete($campaign_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_responses';

        $result = $wpdb->delete($table, [
            'campaign_id' => $campaign_id,
            'user_id' => $user_id,
        ]);

        if ($result) {
            // Update participant status back to invited
            TAPP_Campaigns_Participant::update_status($campaign_id, $user_id, 'invited');

            wp_cache_delete('responses_' . $campaign_id . '_' . $user_id, 'tapp_campaigns');

            do_action('tapp_campaigns_response_deleted', $campaign_id, $user_id);
        }

        return $result !== false;
    }

    /**
     * Get all responses for a campaign
     */
    public static function get_campaign_responses($campaign_id, $latest_only = true) {
        return TAPP_Campaigns_Database::get_campaign_responses($campaign_id, $latest_only);
    }

    /**
     * Export responses to array
     */
    public static function export($campaign_id) {
        $responses = self::get_campaign_responses($campaign_id, true);
        $export = [];

        foreach ($responses as $response) {
            $user = get_user_by('id', $response->user_id);
            $product = wc_get_product($response->product_id);

            $export[] = [
                'user_id' => $response->user_id,
                'user_name' => $user ? $user->display_name : '',
                'user_email' => $user ? $user->user_email : '',
                'product_id' => $response->product_id,
                'product_name' => $product ? $product->get_name() : '',
                'sku' => $product ? $product->get_sku() : '',
                'color' => $response->color,
                'size' => $response->size,
                'quantity' => $response->quantity,
                'submitted_at' => $response->created_at,
            ];
        }

        return $export;
    }

    /**
     * Get product summary (aggregated by product/color/size)
     */
    public static function get_product_summary($campaign_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_responses';

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT
                product_id,
                color,
                size,
                SUM(quantity) as total_quantity,
                COUNT(DISTINCT user_id) as user_count
            FROM $table
            WHERE campaign_id = %d AND is_latest = 1
            GROUP BY product_id, color, size
            ORDER BY product_id, color, size
        ", $campaign_id));

        $summary = [];

        foreach ($results as $row) {
            $product = wc_get_product($row->product_id);

            $summary[] = [
                'product_id' => $row->product_id,
                'product_name' => $product ? $product->get_name() : '',
                'sku' => $product ? $product->get_sku() : '',
                'color' => $row->color,
                'size' => $row->size,
                'total_quantity' => (int) $row->total_quantity,
                'user_count' => (int) $row->user_count,
            ];
        }

        return $summary;
    }
}
