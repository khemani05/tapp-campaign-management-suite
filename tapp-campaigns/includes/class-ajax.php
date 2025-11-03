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

        // Analytics page actions
        add_action('wp_ajax_tapp_load_response', [$this, 'load_response']);
        add_action('wp_ajax_tapp_delete_response', [$this, 'delete_response']);
        add_action('wp_ajax_tapp_send_reminder', [$this, 'send_reminder']);
        add_action('wp_ajax_tapp_remove_participant', [$this, 'remove_participant']);

        // Dismiss banner
        add_action('wp_ajax_tapp_dismiss_banner', [$this, 'dismiss_banner']);
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
     * Load user response for viewing/editing
     */
    public function load_response() {
        check_ajax_referer('tapp_analytics_nonce', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'view';

        if (!$campaign_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid data', 'tapp-campaigns')]);
        }

        // Check permissions
        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        // Get user responses
        global $wpdb;
        $responses_table = $wpdb->prefix . 'tapp_campaign_responses';
        $participants_table = $wpdb->prefix . 'tapp_campaign_participants';

        $responses = $wpdb->get_results($wpdb->prepare(
            "SELECT r.* FROM {$responses_table} r
            INNER JOIN {$participants_table} p ON r.user_id = p.user_id AND r.campaign_id = p.campaign_id
            WHERE r.campaign_id = %d AND r.user_id = %d
            ORDER BY r.version DESC",
            $campaign_id,
            $user_id
        ));

        if (empty($responses)) {
            wp_send_json_error(['message' => __('No response found', 'tapp-campaigns')]);
        }

        // Get user info
        $user = get_userdata($user_id);
        $participant = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$participants_table} WHERE campaign_id = %d AND user_id = %d",
            $campaign_id,
            $user_id
        ));

        // Build HTML
        ob_start();
        ?>
        <div class="response-details">
            <div class="response-header">
                <p><strong><?php _e('Participant:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)</p>
                <p><strong><?php _e('Submitted:', 'tapp-campaigns'); ?></strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($participant->submitted_at)); ?></p>
                <p><strong><?php _e('Total Versions:', 'tapp-campaigns'); ?></strong> <?php echo count($responses); ?></p>
            </div>

            <?php if ($mode === 'view'): ?>
                <h3><?php _e('Latest Selections', 'tapp-campaigns'); ?></h3>
                <table class="response-table">
                    <thead>
                        <tr>
                            <th><?php _e('Product', 'tapp-campaigns'); ?></th>
                            <th><?php _e('SKU', 'tapp-campaigns'); ?></th>
                            <th><?php _e('Color', 'tapp-campaigns'); ?></th>
                            <th><?php _e('Size', 'tapp-campaigns'); ?></th>
                            <th><?php _e('Quantity', 'tapp-campaigns'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($responses as $response): ?>
                            <?php
                            $product = wc_get_product($response->variation_id ? $response->variation_id : $response->product_id);
                            $color = $response->color ?: '-';
                            $size = $response->size ?: '-';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($product ? $product->get_name() : __('Product not found', 'tapp-campaigns')); ?></strong></td>
                                <td><?php echo esc_html($product ? $product->get_sku() : '-'); ?></td>
                                <td><?php echo esc_html(ucfirst($color)); ?></td>
                                <td><?php echo esc_html(strtoupper($size)); ?></td>
                                <td><?php echo esc_html($response->quantity); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <h3><?php _e('Edit Response', 'tapp-campaigns'); ?></h3>
                <p class="description"><?php _e('This feature is coming soon. For now, the participant can edit their own response by visiting the campaign page.', 'tapp-campaigns'); ?></p>
            <?php endif; ?>
        </div>

        <style>
        .response-details {
            padding: 10px 0;
        }
        .response-header {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .response-header p {
            margin: 5px 0;
        }
        .response-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .response-table th,
        .response-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .response-table th {
            background: #f9f9f9;
            font-weight: 600;
        }
        </style>
        <?php
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Delete user response (manager action)
     */
    public function delete_response() {
        check_ajax_referer('tapp_analytics_nonce', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$campaign_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid data', 'tapp-campaigns')]);
        }

        // Check permissions
        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        // Delete responses
        global $wpdb;
        $responses_table = $wpdb->prefix . 'tapp_campaign_responses';
        $participants_table = $wpdb->prefix . 'tapp_campaign_participants';

        // Delete all response versions
        $result = $wpdb->delete($responses_table, [
            'campaign_id' => $campaign_id,
            'user_id' => $user_id
        ]);

        // Reset participant submission status
        $wpdb->update(
            $participants_table,
            [
                'submitted_at' => null,
                'updated_at' => current_time('mysql')
            ],
            [
                'campaign_id' => $campaign_id,
                'user_id' => $user_id
            ]
        );

        if ($result !== false) {
            wp_send_json_success(['message' => __('Response deleted successfully. Participant can now submit again.', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete response', 'tapp-campaigns')]);
        }
    }

    /**
     * Send reminder email to participant
     */
    public function send_reminder() {
        check_ajax_referer('tapp_analytics_nonce', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$campaign_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid data', 'tapp-campaigns')]);
        }

        // Check permissions
        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        // Send reminder email
        $result = TAPP_Campaigns_Email::send_reminder($campaign_id, $user_id);

        if ($result) {
            wp_send_json_success(['message' => __('Reminder email sent successfully', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to send reminder email', 'tapp-campaigns')]);
        }
    }

    /**
     * Remove participant from campaign
     */
    public function remove_participant() {
        check_ajax_referer('tapp_analytics_nonce', 'nonce');

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$campaign_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid data', 'tapp-campaigns')]);
        }

        // Check permissions
        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        // Remove participant
        $result = TAPP_Campaigns_Participant::remove($campaign_id, $user_id);

        if ($result) {
            wp_send_json_success(['message' => __('Participant removed successfully', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove participant', 'tapp-campaigns')]);
        }
    }

    /**
     * Dismiss campaign banner
     */
    public function dismiss_banner() {
        check_ajax_referer('tapp_campaigns_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Please log in', 'tapp-campaigns')]);
        }

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;

        if (!$campaign_id) {
            wp_send_json_error(['message' => __('Invalid campaign', 'tapp-campaigns')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_participants';

        $result = $wpdb->update(
            $table,
            ['dismissed_banner' => 1],
            [
                'campaign_id' => $campaign_id,
                'user_id' => get_current_user_id()
            ]
        );

        if ($result !== false) {
            wp_send_json_success(['message' => __('Banner dismissed', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to dismiss banner', 'tapp-campaigns')]);
        }
    }
}
