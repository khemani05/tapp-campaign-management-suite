<?php
/**
 * AJAX Handler Class
 * Handles all AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Ajax {

    public function __construct() {
        // Search products
        add_action('wp_ajax_tapp_search_products', [$this, 'search_products']);

        // Search users
        add_action('wp_ajax_tapp_search_users', [$this, 'search_users']);

        // Submit campaign response
        add_action('wp_ajax_tapp_submit_response', [$this, 'submit_response']);

        // Get campaign stats
        add_action('wp_ajax_tapp_get_stats', [$this, 'get_stats']);

        // Delete response (manager action)
        add_action('wp_ajax_tapp_delete_response', [$this, 'delete_response']);
    }

    /**
     * Search WooCommerce products
     */
    public function search_products() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!current_user_can('create_campaigns')) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 20,
            's' => $search,
        ];

        $query = new WP_Query($args);
        $products = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());

                if ($product) {
                    $products[] = [
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'sku' => $product->get_sku(),
                        'price' => $product->get_price_html(),
                        'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                        'type' => $product->get_type(),
                    ];
                }
            }
            wp_reset_postdata();
        }

        wp_send_json_success($products);
    }

    /**
     * Search users
     */
    public function search_users() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!current_user_can('create_campaigns')) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $users = get_users([
            'search' => '*' . $search . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number' => 20,
        ]);

        $results = [];
        foreach ($users as $user) {
            $department = tapp_campaigns_onboarding()->get_user_department($user->ID);

            $results[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'department' => $department,
            ];
        }

        wp_send_json_success($results);
    }

    /**
     * Submit campaign response
     */
    public function submit_response() {
        check_ajax_referer('tapp_campaigns_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in', 'tapp-campaigns')]);
        }

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $selections = isset($_POST['selections']) ? json_decode(stripslashes($_POST['selections']), true) : [];

        if (!$campaign_id || empty($selections)) {
            wp_send_json_error(['message' => __('Invalid data', 'tapp-campaigns')]);
        }

        $user_id = get_current_user_id();

        // Check if user is participant
        if (!TAPP_Campaigns_Participant::is_participant($campaign_id, $user_id)) {
            wp_send_json_error(['message' => __('You are not a participant in this campaign', 'tapp-campaigns')]);
        }

        // Check if campaign is active
        if (!TAPP_Campaigns_Campaign::is_active($campaign_id)) {
            wp_send_json_error(['message' => __('This campaign is not active', 'tapp-campaigns')]);
        }

        // Check edit policy
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
        $has_submitted = TAPP_Campaigns_Response::has_submitted($campaign_id, $user_id);

        if ($has_submitted && $campaign->edit_policy === 'once') {
            wp_send_json_error(['message' => __('You have already submitted and cannot edit', 'tapp-campaigns')]);
        }

        // Validate selections
        $validated_selections = [];
        foreach ($selections as $selection) {
            $validated_selections[] = [
                'product_id' => intval($selection['product_id']),
                'variation_id' => isset($selection['variation_id']) ? intval($selection['variation_id']) : 0,
                'color' => isset($selection['color']) ? sanitize_text_field($selection['color']) : null,
                'size' => isset($selection['size']) ? sanitize_text_field($selection['size']) : null,
                'quantity' => isset($selection['quantity']) ? intval($selection['quantity']) : 1,
            ];
        }

        // Create response
        $result = TAPP_Campaigns_Response::create($campaign_id, $user_id, $validated_selections);

        if ($result) {
            // Send confirmation email
            if ($campaign->send_confirmation) {
                TAPP_Campaigns_Email::send_confirmation($campaign_id, $user_id);
            }

            wp_send_json_success([
                'message' => __('Your selections have been submitted successfully!', 'tapp-campaigns'),
                'redirect' => false,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save your selections', 'tapp-campaigns')]);
        }
    }

    /**
     * Get campaign statistics
     */
    public function get_stats() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(['message' => __('Invalid campaign', 'tapp-campaigns')]);
        }

        // Check permissions
        if (!tapp_campaigns_onboarding()->can_edit_campaign($campaign_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $stats = TAPP_Campaigns_Campaign::get_stats($campaign_id);

        wp_send_json_success($stats);
    }

    /**
     * Delete user response (manager action)
     */
    public function delete_response() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$campaign_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid data', 'tapp-campaigns')]);
        }

        // Check permissions
        if (!tapp_campaigns_onboarding()->can_edit_campaign($campaign_id, get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $result = TAPP_Campaigns_Response::delete($campaign_id, $user_id);

        if ($result) {
            wp_send_json_success(['message' => __('Response deleted successfully', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete response', 'tapp-campaigns')]);
        }
    }
}
