<?php
/**
 * Campaign Model Class
 * Handles campaign CRUD operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Campaign {

    /**
     * Create a new campaign
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        $defaults = [
            'name' => '',
            'type' => 'team',
            'status' => 'draft',
            'creator_id' => get_current_user_id(),
            'department' => null,
            'start_date' => null,
            'end_date' => null,
            'notes' => null,
            'description' => null,
            'selection_limit' => 1,
            'selection_min' => 0,
            'edit_policy' => 'once',
            'ask_color' => 1,
            'color_config' => 'all',
            'allowed_colors' => null,
            'ask_size' => 1,
            'ask_quantity' => 1,
            'min_quantity' => 1,
            'max_quantity' => 10,
            'page_template' => 'classic',
            'template_primary_color' => '#0073aa',
            'template_button_color' => '#0073aa',
            'send_invitation' => 1,
            'send_confirmation' => 1,
            'send_reminder' => 1,
            'reminder_hours' => 24,
        ];

        $data = wp_parse_args($data, $defaults);

        // Generate slug
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
            // Ensure unique slug
            $slug = $data['slug'];
            $count = 1;
            while (self::slug_exists($slug)) {
                $slug = $data['slug'] . '-' . $count;
                $count++;
            }
            $data['slug'] = $slug;
        }

        // Insert campaign
        $result = $wpdb->insert($table, $data);

        if ($result === false) {
            return false;
        }

        $campaign_id = $wpdb->insert_id;

        // Clear cache
        wp_cache_delete('campaign_' . $campaign_id, 'tapp_campaigns');

        do_action('tapp_campaigns_campaign_created', $campaign_id, $data);

        return $campaign_id;
    }

    /**
     * Update campaign
     */
    public static function update($campaign_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        $result = $wpdb->update(
            $table,
            $data,
            ['id' => $campaign_id],
            null,
            ['%d']
        );

        if ($result !== false) {
            wp_cache_delete('campaign_' . $campaign_id, 'tapp_campaigns');
            wp_cache_delete('campaign_slug_' . $data['slug'], 'tapp_campaigns');

            do_action('tapp_campaigns_campaign_updated', $campaign_id, $data);
        }

        return $result !== false;
    }

    /**
     * Get campaign by ID
     */
    public static function get($campaign_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        $cache_key = 'campaign_' . $campaign_id;
        $campaign = wp_cache_get($cache_key, 'tapp_campaigns');

        if ($campaign === false) {
            $campaign = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                $campaign_id
            ));

            if ($campaign) {
                wp_cache_set($cache_key, $campaign, 'tapp_campaigns', 3600);
            }
        }

        return $campaign;
    }

    /**
     * Get campaign by slug
     */
    public static function get_by_slug($slug) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        $cache_key = 'campaign_slug_' . $slug;
        $campaign = wp_cache_get($cache_key, 'tapp_campaigns');

        if ($campaign === false) {
            $campaign = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE slug = %s",
                $slug
            ));

            if ($campaign) {
                wp_cache_set($cache_key, $campaign, 'tapp_campaigns', 3600);
            }
        }

        return $campaign;
    }

    /**
     * Delete campaign
     */
    public static function delete($campaign_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        // Delete related data (cascading)
        $wpdb->delete($wpdb->prefix . 'tapp_campaign_products', ['campaign_id' => $campaign_id]);
        $wpdb->delete($wpdb->prefix . 'tapp_participants', ['campaign_id' => $campaign_id]);
        $wpdb->delete($wpdb->prefix . 'tapp_responses', ['campaign_id' => $campaign_id]);
        $wpdb->delete($wpdb->prefix . 'tapp_campaign_meta', ['campaign_id' => $campaign_id]);

        // Delete campaign
        $result = $wpdb->delete($table, ['id' => $campaign_id], ['%d']);

        if ($result) {
            wp_cache_delete('campaign_' . $campaign_id, 'tapp_campaigns');
            do_action('tapp_campaigns_campaign_deleted', $campaign_id);
        }

        return $result !== false;
    }

    /**
     * Check if slug exists
     */
    private static function slug_exists($slug) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE slug = %s",
            $slug
        ));

        return $count > 0;
    }

    /**
     * Add product to campaign
     */
    public static function add_product($campaign_id, $product_id, $display_order = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_products';

        $result = $wpdb->insert($table, [
            'campaign_id' => $campaign_id,
            'product_id' => $product_id,
            'display_order' => $display_order,
        ]);

        return $result !== false;
    }

    /**
     * Remove product from campaign
     */
    public static function remove_product($campaign_id, $product_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_products';

        return $wpdb->delete($table, [
            'campaign_id' => $campaign_id,
            'product_id' => $product_id,
        ]) !== false;
    }

    /**
     * Set campaign products (replaces existing)
     */
    public static function set_products($campaign_id, $product_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_products';

        // Delete existing products
        $wpdb->delete($table, ['campaign_id' => $campaign_id]);

        // Add new products
        foreach ($product_ids as $index => $product_id) {
            self::add_product($campaign_id, $product_id, $index);
        }

        return true;
    }

    /**
     * Get campaign products
     */
    public static function get_products($campaign_id) {
        return TAPP_Campaigns_Database::get_campaign_products($campaign_id);
    }

    /**
     * Get WooCommerce product objects
     */
    public static function get_wc_products($campaign_id) {
        $campaign_products = self::get_products($campaign_id);
        $products = [];

        foreach ($campaign_products as $cp) {
            $product = wc_get_product($cp->product_id);
            if ($product) {
                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * Update campaign status
     */
    public static function update_status($campaign_id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        $valid_statuses = ['draft', 'scheduled', 'active', 'ended', 'archived'];
        if (!in_array($status, $valid_statuses)) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            ['status' => $status],
            ['id' => $campaign_id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_cache_delete('campaign_' . $campaign_id, 'tapp_campaigns');
            do_action('tapp_campaigns_status_changed', $campaign_id, $status);
        }

        return $result !== false;
    }

    /**
     * Get campaign URL
     */
    public static function get_url($campaign_id) {
        $campaign = self::get($campaign_id);
        if (!$campaign) {
            return '';
        }

        return home_url('/campaign/' . $campaign->slug . '/');
    }

    /**
     * Get edit URL
     */
    public static function get_edit_url($campaign_id) {
        return add_query_arg([
            'page' => 'tapp-campaigns',
            'action' => 'edit',
            'id' => $campaign_id,
        ], admin_url('admin.php'));
    }

    /**
     * Check if campaign is active
     */
    public static function is_active($campaign_id) {
        $campaign = self::get($campaign_id);
        if (!$campaign) {
            return false;
        }

        if ($campaign->status !== 'active') {
            return false;
        }

        $now = current_time('mysql');
        return $campaign->start_date <= $now && $campaign->end_date > $now;
    }

    /**
     * Check if campaign has ended
     */
    public static function has_ended($campaign_id) {
        $campaign = self::get($campaign_id);
        if (!$campaign) {
            return true;
        }

        $now = current_time('mysql');
        return $campaign->end_date <= $now || $campaign->status === 'ended';
    }

    /**
     * Get campaign statistics
     */
    public static function get_stats($campaign_id) {
        return TAPP_Campaigns_Database::get_campaign_stats($campaign_id);
    }
}
