<?php
/**
 * Campaign Template Model Class
 * Handles saving and loading campaign templates
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Template {

    /**
     * Create template from campaign
     */
    public static function create_from_campaign($campaign_id, $template_name, $description = '') {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

        if (!$campaign) {
            return false;
        }

        // Get campaign products
        $products = TAPP_Campaigns_Campaign::get_products($campaign_id);
        $product_ids = array_map(function($p) { return $p->product_id; }, $products);

        // Prepare template data (exclude campaign-specific fields)
        $template_data = [
            'type' => $campaign->type,
            'department' => $campaign->department,
            'notes' => $campaign->notes,
            'description' => $campaign->description,
            'selection_limit' => $campaign->selection_limit,
            'selection_min' => $campaign->selection_min,
            'edit_policy' => $campaign->edit_policy,
            'ask_color' => $campaign->ask_color,
            'color_config' => $campaign->color_config,
            'allowed_colors' => $campaign->allowed_colors,
            'ask_size' => $campaign->ask_size,
            'ask_quantity' => $campaign->ask_quantity,
            'min_quantity' => $campaign->min_quantity,
            'max_quantity' => $campaign->max_quantity,
            'page_template' => $campaign->page_template,
            'template_primary_color' => $campaign->template_primary_color,
            'template_button_color' => $campaign->template_button_color,
            'template_hero_image' => $campaign->template_hero_image,
            'payment_enabled' => $campaign->payment_enabled,
            'generate_po' => $campaign->generate_po,
            'generate_invoice' => $campaign->generate_invoice,
            'invoice_recipients' => $campaign->invoice_recipients,
            'send_invitation' => $campaign->send_invitation,
            'send_confirmation' => $campaign->send_confirmation,
            'send_reminder' => $campaign->send_reminder,
            'reminder_hours' => $campaign->reminder_hours,
            'webhook_url' => $campaign->webhook_url,
        ];

        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_templates';

        $result = $wpdb->insert($table, [
            'name' => sanitize_text_field($template_name),
            'description' => sanitize_textarea_field($description),
            'type' => $campaign->type,
            'creator_id' => get_current_user_id(),
            'template_data' => wp_json_encode($template_data),
            'product_ids' => implode(',', $product_ids),
            'is_public' => 0,
            'usage_count' => 0,
        ]);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Create template from scratch
     */
    public static function create($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_templates';

        $template_data = isset($data['template_data']) ? $data['template_data'] : [];
        $product_ids = isset($data['product_ids']) ? $data['product_ids'] : [];

        $result = $wpdb->insert($table, [
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'type' => sanitize_text_field($data['type'] ?? 'custom'),
            'creator_id' => get_current_user_id(),
            'template_data' => is_array($template_data) ? wp_json_encode($template_data) : $template_data,
            'product_ids' => is_array($product_ids) ? implode(',', $product_ids) : $product_ids,
            'is_public' => isset($data['is_public']) ? intval($data['is_public']) : 0,
            'usage_count' => 0,
        ]);

        if ($result) {
            return $wpdb->insert_id;
        }

        return false;
    }

    /**
     * Get template by ID
     */
    public static function get($template_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_templates';

        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $template_id
        ));

        if ($template) {
            $template->template_data = json_decode($template->template_data, true);
            $template->product_ids = $template->product_ids ? explode(',', $template->product_ids) : [];
        }

        return $template;
    }

    /**
     * Get all templates
     */
    public static function get_all($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_templates';

        $where = ['1=1'];
        $params = [];

        // Filter by creator
        if (isset($args['creator_id'])) {
            $where[] = 'creator_id = %d';
            $params[] = $args['creator_id'];
        }

        // Filter by type
        if (isset($args['type'])) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }

        // Include public templates
        if (isset($args['include_public']) && $args['include_public']) {
            $where[] = '(creator_id = %d OR is_public = 1)';
            $params[] = get_current_user_id();
        }

        $where_sql = implode(' AND ', $where);

        $query = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $templates = $wpdb->get_results($query);

        foreach ($templates as $template) {
            $template->template_data = json_decode($template->template_data, true);
            $template->product_ids = $template->product_ids ? explode(',', $template->product_ids) : [];
        }

        return $templates;
    }

    /**
     * Update template
     */
    public static function update($template_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_templates';

        // Check ownership
        $template = self::get($template_id);
        if (!$template || $template->creator_id != get_current_user_id()) {
            return false;
        }

        $update_data = [];

        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['type'])) {
            $update_data['type'] = sanitize_text_field($data['type']);
        }

        if (isset($data['template_data'])) {
            $update_data['template_data'] = is_array($data['template_data']) ? wp_json_encode($data['template_data']) : $data['template_data'];
        }

        if (isset($data['product_ids'])) {
            $update_data['product_ids'] = is_array($data['product_ids']) ? implode(',', $data['product_ids']) : $data['product_ids'];
        }

        if (isset($data['is_public'])) {
            $update_data['is_public'] = intval($data['is_public']);
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $template_id]
        );

        return $result !== false;
    }

    /**
     * Delete template
     */
    public static function delete($template_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_templates';

        // Check ownership
        $template = self::get($template_id);
        if (!$template || $template->creator_id != get_current_user_id()) {
            return false;
        }

        $result = $wpdb->delete($table, ['id' => $template_id]);

        return $result !== false;
    }

    /**
     * Increment usage count
     */
    public static function increment_usage($template_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_templates';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET usage_count = usage_count + 1 WHERE id = %d",
            $template_id
        ));
    }

    /**
     * Create campaign from template
     */
    public static function create_campaign_from_template($template_id, $campaign_name, $start_date, $end_date) {
        $template = self::get($template_id);

        if (!$template) {
            return false;
        }

        // Prepare campaign data from template
        $campaign_data = array_merge($template->template_data, [
            'name' => $campaign_name,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'draft',
            'creator_id' => get_current_user_id(),
        ]);

        // Create campaign
        $campaign_id = TAPP_Campaigns_Campaign::create($campaign_data);

        if ($campaign_id) {
            // Add products
            if (!empty($template->product_ids)) {
                TAPP_Campaigns_Campaign::set_products($campaign_id, $template->product_ids);
            }

            // Increment usage count
            self::increment_usage($template_id);

            return $campaign_id;
        }

        return false;
    }

    /**
     * Count templates
     */
    public static function count($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_templates';

        $where = ['1=1'];
        $params = [];

        if (isset($args['creator_id'])) {
            $where[] = 'creator_id = %d';
            $params[] = $args['creator_id'];
        }

        if (isset($args['type'])) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }

        $where_sql = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return (int) $wpdb->get_var($query);
    }
}
