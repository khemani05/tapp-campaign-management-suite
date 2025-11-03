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

        // Templates
        add_action('wp_ajax_tapp_create_template', [$this, 'create_template']);
        add_action('wp_ajax_tapp_get_templates', [$this, 'get_templates']);
        add_action('wp_ajax_tapp_delete_template', [$this, 'delete_template']);
        add_action('wp_ajax_tapp_use_template', [$this, 'use_template']);

        // User Groups
        add_action('wp_ajax_tapp_create_group', [$this, 'create_group']);
        add_action('wp_ajax_tapp_get_groups', [$this, 'get_groups']);
        add_action('wp_ajax_tapp_get_group_members', [$this, 'get_group_members']);
        add_action('wp_ajax_tapp_delete_group', [$this, 'delete_group']);
        add_action('wp_ajax_tapp_add_group_member', [$this, 'add_group_member']);
        add_action('wp_ajax_tapp_remove_group_member', [$this, 'remove_group_member']);
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
            // Check if payment is enabled for this campaign
            if ($campaign->payment_enabled) {
                // Add items to WooCommerce cart
                $cart_result = TAPP_Campaigns_Payment::add_to_cart($campaign_id, $user_id, $validated_selections);

                if (is_wp_error($cart_result)) {
                    wp_send_json_error(['message' => $cart_result->get_error_message()]);
                }

                // Redirect to checkout
                wp_send_json_success([
                    'message' => __('Redirecting to checkout...', 'tapp-campaigns'),
                    'redirect' => $cart_result['checkout_url'],
                    'cart_total' => $cart_result['total_price'],
                ]);
            } else {
                // Send confirmation email for non-payment campaigns
                if ($campaign->send_confirmation) {
                    TAPP_Campaigns_Email::send_confirmation($campaign_id, $user_id);
                }

                wp_send_json_success([
                    'message' => __('Your selections have been submitted successfully!', 'tapp-campaigns'),
                    'redirect' => false,
                ]);
            }
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

    /**
     * Create template from campaign
     */
    public function create_template() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $campaign_id = isset($_POST['campaign_id']) ? intval($_POST['campaign_id']) : 0;
        $template_name = isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';

        if (!$campaign_id || empty($template_name)) {
            wp_send_json_error(['message' => __('Invalid data', 'tapp-campaigns')]);
        }

        $template_id = TAPP_Campaigns_Template::create_from_campaign($campaign_id, $template_name, $description);

        if ($template_id) {
            wp_send_json_success([
                'message' => __('Template created successfully', 'tapp-campaigns'),
                'template_id' => $template_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to create template', 'tapp-campaigns')]);
        }
    }

    /**
     * Get templates
     */
    public function get_templates() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';

        $args = [
            'include_public' => true,
        ];

        if (!empty($type) && in_array($type, ['team', 'sales', 'custom'])) {
            $args['type'] = $type;
        }

        $templates = TAPP_Campaigns_Template::get_all($args);

        wp_send_json_success(['templates' => $templates]);
    }

    /**
     * Delete template
     */
    public function delete_template() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

        if (!$template_id) {
            wp_send_json_error(['message' => __('Invalid template', 'tapp-campaigns')]);
        }

        $result = TAPP_Campaigns_Template::delete($template_id);

        if ($result) {
            wp_send_json_success(['message' => __('Template deleted successfully', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete template', 'tapp-campaigns')]);
        }
    }

    /**
     * Use template (get template data for campaign creation)
     */
    public function use_template() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;

        if (!$template_id) {
            wp_send_json_error(['message' => __('Invalid template', 'tapp-campaigns')]);
        }

        $template = TAPP_Campaigns_Template::get($template_id);

        if ($template) {
            wp_send_json_success(['template' => $template]);
        } else {
            wp_send_json_error(['message' => __('Template not found', 'tapp-campaigns')]);
        }
    }

    /**
     * Create user group
     */
    public function create_group() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $department = isset($_POST['department']) ? sanitize_text_field($_POST['department']) : '';
        $user_ids = isset($_POST['user_ids']) ? array_map('intval', $_POST['user_ids']) : [];

        if (empty($name)) {
            wp_send_json_error(['message' => __('Group name is required', 'tapp-campaigns')]);
        }

        $data = [
            'name' => $name,
            'description' => $description,
            'department' => $department,
            'user_ids' => $user_ids,
        ];

        $group_id = TAPP_Campaigns_User_Group::create($data);

        if ($group_id) {
            wp_send_json_success([
                'message' => __('Group created successfully', 'tapp-campaigns'),
                'group_id' => $group_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to create group', 'tapp-campaigns')]);
        }
    }

    /**
     * Get user groups
     */
    public function get_groups() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $department = isset($_POST['department']) ? sanitize_text_field($_POST['department']) : '';

        $args = [];

        if (!empty($search)) {
            $args['search'] = $search;
        }

        if (!empty($department)) {
            $args['department'] = $department;
        }

        $groups = TAPP_Campaigns_User_Group::get_all($args);

        wp_send_json_success(['groups' => $groups]);
    }

    /**
     * Get group members
     */
    public function get_group_members() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

        if (!$group_id) {
            wp_send_json_error(['message' => __('Invalid group', 'tapp-campaigns')]);
        }

        $members = TAPP_Campaigns_User_Group::get_members($group_id);

        wp_send_json_success(['members' => $members]);
    }

    /**
     * Delete user group
     */
    public function delete_group() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

        if (!$group_id) {
            wp_send_json_error(['message' => __('Invalid group', 'tapp-campaigns')]);
        }

        $result = TAPP_Campaigns_User_Group::delete($group_id);

        if ($result) {
            wp_send_json_success(['message' => __('Group deleted successfully', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete group', 'tapp-campaigns')]);
        }
    }

    /**
     * Add member to group
     */
    public function add_group_member() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$group_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid data', 'tapp-campaigns')]);
        }

        $result = TAPP_Campaigns_User_Group::add_member($group_id, $user_id);

        if ($result) {
            wp_send_json_success(['message' => __('Member added successfully', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to add member', 'tapp-campaigns')]);
        }
    }

    /**
     * Remove member from group
     */
    public function remove_group_member() {
        check_ajax_referer('tapp_campaigns_admin', 'nonce');

        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            wp_send_json_error(['message' => __('Unauthorized', 'tapp-campaigns')]);
        }

        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        if (!$group_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid data', 'tapp-campaigns')]);
        }

        $result = TAPP_Campaigns_User_Group::remove_member($group_id, $user_id);

        if ($result) {
            wp_send_json_success(['message' => __('Member removed successfully', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to remove member', 'tapp-campaigns')]);
        }
    }
}
