<?php
/**
 * Core Plugin Class
 * Initializes all components
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Core {

    private $onboarding;
    private $frontend;
    private $dashboard;
    private $ajax;

    public function init() {
        // Load dependencies
        $this->load_dependencies();

        // Initialize components
        $this->init_components();

        // Setup hooks
        $this->setup_hooks();
    }

    private function load_dependencies() {
        $includes = [
            'class-onboarding-integration',
            'class-database',
            'class-campaign',
            'class-participant',
            'class-response',
            'class-email',
            'class-ajax',
            'class-cron',
            'class-template',
            'class-user-group',
            'class-templates',
            'class-payment',
            'class-purchase-order',
            'class-activity-log',
            'class-google-sheets',
        ];

        foreach ($includes as $file) {
            $path = TAPP_CAMPAIGNS_PATH . 'includes/' . $file . '.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }

        // Load frontend classes
        if (!is_admin()) {
            require_once TAPP_CAMPAIGNS_PATH . 'frontend/class-frontend.php';
            require_once TAPP_CAMPAIGNS_PATH . 'frontend/class-navigation.php';
            require_once TAPP_CAMPAIGNS_PATH . 'frontend/class-campaign-page.php';
        }

        // Load admin classes
        if (is_admin()) {
            require_once TAPP_CAMPAIGNS_PATH . 'admin/class-admin.php';
            require_once TAPP_CAMPAIGNS_PATH . 'admin/class-dashboard.php';
        }
    }

    private function init_components() {
        // Initialize onboarding integration
        $this->onboarding = new TAPP_Campaigns_Onboarding_Integration();

        // Initialize AJAX handlers
        $this->ajax = new TAPP_Campaigns_Ajax();

        // Initialize payment handler
        new TAPP_Campaigns_Payment();

        // Initialize cron jobs
        $cron = new TAPP_Campaigns_Cron();
        $cron->schedule_events();

        // Initialize frontend or admin
        if (!is_admin()) {
            $this->frontend = new TAPP_Campaigns_Frontend();
            new TAPP_Campaigns_Navigation();
        } else {
            new TAPP_Campaigns_Admin();
        }
    }

    private function setup_hooks() {
        // Add custom query vars
        add_filter('query_vars', [$this, 'add_query_vars']);

        // Add rewrite rules
        add_action('init', [$this, 'add_rewrite_rules']);

        // Add WooCommerce My Account endpoints
        add_action('init', [$this, 'add_account_endpoints']);

        // Template redirect
        add_action('template_redirect', [$this, 'template_redirect']);
    }

    public function add_query_vars($vars) {
        $vars[] = 'campaign';
        $vars[] = 'campaign_page';
        $vars[] = 'campaign_action';
        $vars[] = 'campaign_id';
        $vars[] = 'preview_mode';
        $vars[] = 'preview_token';
        return $vars;
    }

    public function add_rewrite_rules() {
        // Campaign page: /campaign/summer-uniforms/
        add_rewrite_rule(
            '^campaign/([^/]+)/?$',
            'index.php?campaign=$matches[1]',
            'top'
        );

        // Campaign Manager Dashboard: /campaign-manager/
        add_rewrite_rule(
            '^campaign-manager/?$',
            'index.php?campaign_page=manager',
            'top'
        );

        // Campaign Manager Actions: /campaign-manager/create-team/
        add_rewrite_rule(
            '^campaign-manager/([^/]+)/?$',
            'index.php?campaign_page=manager&campaign_action=$matches[1]',
            'top'
        );

        // Campaign Manager with ID: /campaign-manager/edit/123/
        add_rewrite_rule(
            '^campaign-manager/([^/]+)/([0-9]+)/?$',
            'index.php?campaign_page=manager&campaign_action=$matches[1]&campaign_id=$matches[2]',
            'top'
        );

        // My Campaigns: /my-campaigns/
        add_rewrite_rule(
            '^my-campaigns/?$',
            'index.php?campaign_page=my-campaigns',
            'top'
        );
    }

    public function add_account_endpoints() {
        // No longer using WooCommerce endpoints - using standalone pages
    }

    public function template_redirect() {
        // Handle campaign page requests
        $campaign_slug = get_query_var('campaign');
        if ($campaign_slug) {
            $this->load_campaign_template($campaign_slug);
            exit;
        }

        // Handle standalone pages
        $page = get_query_var('campaign_page');
        if ($page) {
            $this->load_standalone_page($page);
            exit;
        }
    }

    private function load_campaign_template($slug) {
        // Check for preview mode
        $preview_mode = get_query_var('preview_mode');
        $preview_token = get_query_var('preview_token');

        if (!is_user_logged_in()) {
            wp_redirect(wc_get_page_permalink('myaccount') . '?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }

        $campaign = TAPP_Campaigns_Campaign::get_by_slug($slug);

        if (!$campaign) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            include get_404_template();
            exit;
        }

        $user_id = get_current_user_id();

        // Handle preview mode
        if ($preview_mode && $preview_token) {
            // Verify preview token
            $expected_token = wp_hash('preview_' . $campaign->id . '_' . $campaign->creator_id);
            if ($preview_token !== $expected_token) {
                wp_die(__('Invalid preview token.', 'tapp-campaigns'));
            }

            // Check if user can manage this campaign
            if (!$this->can_manage_campaign($campaign->id, $user_id)) {
                wp_die(__('You do not have permission to preview this campaign.', 'tapp-campaigns'));
            }

            // Set preview mode flag for template
            define('TAPP_CAMPAIGN_PREVIEW_MODE', true);

            // Load campaign page template
            get_header();
            include TAPP_CAMPAIGNS_PATH . 'frontend/templates/campaign-page.php';
            get_footer();
            return;
        }

        // Normal mode - check if user is participant
        if (!TAPP_Campaigns_Participant::is_participant($campaign->id, $user_id)) {
            // Check if user has permission to view (manager/ceo/admin)
            if (!$this->can_manage_campaign($campaign->id, $user_id)) {
                wp_die(__('You do not have access to this campaign.', 'tapp-campaigns'));
            }
        }

        // Load campaign page template
        include TAPP_CAMPAIGNS_PATH . 'frontend/templates/campaign-page.php';
    }

    private function load_standalone_page($page) {
        if (!is_user_logged_in()) {
            wp_redirect(wc_get_page_permalink('myaccount') . '?redirect=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }

        $user_id = get_current_user_id();

        if ($page === 'manager') {
            // Check if user can manage campaigns
            if (!tapp_campaigns_onboarding()->can_create_campaigns($user_id)) {
                wp_die(__('You do not have permission to access this page.', 'tapp-campaigns'));
            }

            // Get action
            $action = get_query_var('campaign_action');

            // Handle analytics page
            if ($action === 'analytics') {
                $campaign_id = get_query_var('campaign_id');
                if (!$campaign_id) {
                    wp_die(__('Campaign ID is required.', 'tapp-campaigns'));
                }

                // Check if user can manage this campaign
                if (!$this->can_manage_campaign($campaign_id, $user_id)) {
                    wp_die(__('You do not have permission to view this campaign analytics.', 'tapp-campaigns'));
                }

                // Load analytics page
                get_header();
                include TAPP_CAMPAIGNS_PATH . 'frontend/templates/analytics.php';
                get_footer();
                return;
            }

            // Handle quick actions before loading dashboard
            $this->handle_quick_actions();

            // Handle bulk actions
            $this->handle_bulk_actions();

            // Load manager dashboard
            get_header();
            include TAPP_CAMPAIGNS_PATH . 'frontend/templates/dashboard.php';
            get_footer();

        } elseif ($page === 'my-campaigns') {
            // Load user's campaigns page
            get_header();
            include TAPP_CAMPAIGNS_PATH . 'frontend/templates/my-campaigns.php';
            get_footer();
        }
    }

    /**
     * Handle quick actions from dashboard
     */
    private function handle_quick_actions() {
        if (!isset($_GET['action'])) {
            return;
        }

        $action = sanitize_text_field($_GET['action']);
        $campaign_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($action === 'duplicate' && $campaign_id) {
            // Verify nonce
            if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'duplicate_campaign_' . $campaign_id)) {
                wp_die(__('Invalid security token.', 'tapp-campaigns'));
            }

            // Verify user can manage campaigns
            $user_id = get_current_user_id();
            if (!tapp_campaigns_onboarding()->can_create_campaigns($user_id)) {
                wp_die(__('You do not have permission to duplicate campaigns.', 'tapp-campaigns'));
            }

            // Duplicate the campaign
            $new_campaign_id = TAPP_Campaigns_Campaign::duplicate($campaign_id);

            if ($new_campaign_id) {
                // Redirect to edit the new campaign
                wp_redirect(home_url('/campaign-manager/?action=edit&id=' . $new_campaign_id . '&duplicated=1'));
                exit;
            } else {
                wp_die(__('Failed to duplicate campaign.', 'tapp-campaigns'));
            }
        }

        if ($action === 'export' && $campaign_id) {
            // Verify nonce
            if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'export_campaign_' . $campaign_id)) {
                wp_die(__('Invalid security token.', 'tapp-campaigns'));
            }

            // Verify user can manage campaigns
            $user_id = get_current_user_id();
            if (!tapp_campaigns_onboarding()->can_create_campaigns($user_id)) {
                wp_die(__('You do not have permission to export campaigns.', 'tapp-campaigns'));
            }

            // Check if user can manage this campaign
            if (!$this->can_manage_campaign($campaign_id, $user_id)) {
                wp_die(__('You do not have permission to export this campaign.', 'tapp-campaigns'));
            }

            // Get export type
            $export_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'audience';

            // Handle export
            $this->handle_export($campaign_id, $export_type);
            exit;
        }
    }

    /**
     * Handle CSV export
     */
    private function handle_export($campaign_id, $type) {
        global $wpdb;

        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
        if (!$campaign) {
            wp_die(__('Campaign not found.', 'tapp-campaigns'));
        }

        $filename = sanitize_title($campaign->name) . '-' . $type . '-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        $participants_table = $wpdb->prefix . 'tapp_campaign_participants';
        $responses_table = $wpdb->prefix . 'tapp_campaign_responses';

        if ($type === 'audience') {
            // Export audience list
            fputcsv($output, [
                'Campaign Name',
                'User ID',
                'Name',
                'Email',
                'Department',
                'Status',
                'Invited At',
                'Submitted At',
                'Submission Count'
            ]);

            $participants = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    p.user_id,
                    p.invited_at,
                    p.submitted_at,
                    u.display_name,
                    u.user_email,
                    (SELECT COUNT(*) FROM {$responses_table} WHERE user_id = p.user_id AND campaign_id = p.campaign_id) as submission_count
                FROM {$participants_table} p
                INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
                WHERE p.campaign_id = %d
                ORDER BY u.display_name ASC",
                $campaign_id
            ));

            foreach ($participants as $participant) {
                $department = get_user_meta($participant->user_id, 'tapp_department', true);
                if (empty($department)) {
                    $department = get_user_meta($participant->user_id, 'department', true);
                }

                fputcsv($output, [
                    $campaign->name,
                    $participant->user_id,
                    $participant->display_name,
                    $participant->user_email,
                    $department ?: '-',
                    $participant->submitted_at ? 'Submitted' : 'Pending',
                    $participant->invited_at,
                    $participant->submitted_at ?: '-',
                    $participant->submission_count
                ]);
            }

        } elseif ($type === 'responses') {
            // Export responses
            fputcsv($output, [
                'Campaign Name',
                'User ID',
                'Name',
                'Email',
                'Department',
                'Product ID',
                'Product Name',
                'SKU',
                'Color',
                'Size',
                'Quantity',
                'Submitted At',
                'Version'
            ]);

            $responses = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    r.*,
                    p.submitted_at,
                    u.display_name,
                    u.user_email
                FROM {$responses_table} r
                INNER JOIN {$participants_table} p ON r.user_id = p.user_id AND r.campaign_id = p.campaign_id
                INNER JOIN {$wpdb->users} u ON r.user_id = u.ID
                WHERE r.campaign_id = %d AND p.submitted_at IS NOT NULL
                ORDER BY p.submitted_at DESC, u.display_name ASC",
                $campaign_id
            ));

            foreach ($responses as $response) {
                $product = wc_get_product($response->variation_id ? $response->variation_id : $response->product_id);
                $department = get_user_meta($response->user_id, 'tapp_department', true);
                if (empty($department)) {
                    $department = get_user_meta($response->user_id, 'department', true);
                }

                $product_name = '';
                $sku = '';
                $color = '-';
                $size = '-';

                if ($product) {
                    $product_name = $product->get_name();
                    $sku = $product->get_sku();

                    if ($response->variation_id && $product->is_type('variation')) {
                        $attributes = $product->get_variation_attributes();
                        $color = isset($attributes['attribute_pa_color']) ? $attributes['attribute_pa_color'] : '-';
                        $size = isset($attributes['attribute_pa_size']) ? $attributes['attribute_pa_size'] : '-';
                    }
                }

                fputcsv($output, [
                    $campaign->name,
                    $response->user_id,
                    $response->display_name,
                    $response->user_email,
                    $department ?: '-',
                    $response->product_id,
                    $product_name,
                    $sku,
                    $color,
                    $size,
                    $response->quantity,
                    $response->submitted_at,
                    $response->version
                ]);
            }

        } elseif ($type === 'summary') {
            // Export product summary
            fputcsv($output, [
                'Campaign Name',
                'Product ID',
                'Variation ID',
                'Product Name',
                'SKU',
                'Color',
                'Size',
                'Total Quantity',
                'Number of Users'
            ]);

            $summary = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    r.product_id,
                    r.variation_id,
                    SUM(r.quantity) as total_quantity,
                    COUNT(DISTINCT r.user_id) as user_count
                FROM {$responses_table} r
                INNER JOIN {$participants_table} p ON r.user_id = p.user_id AND r.campaign_id = p.campaign_id
                WHERE r.campaign_id = %d AND p.submitted_at IS NOT NULL
                GROUP BY r.product_id, r.variation_id
                ORDER BY total_quantity DESC",
                $campaign_id
            ));

            foreach ($summary as $item) {
                $product = wc_get_product($item->variation_id ? $item->variation_id : $item->product_id);

                $product_name = '';
                $sku = '';
                $color = '-';
                $size = '-';

                if ($product) {
                    $product_name = $product->get_name();
                    $sku = $product->get_sku();

                    if ($item->variation_id && $product->is_type('variation')) {
                        $attributes = $product->get_variation_attributes();
                        $color = isset($attributes['attribute_pa_color']) ? $attributes['attribute_pa_color'] : '-';
                        $size = isset($attributes['attribute_pa_size']) ? $attributes['attribute_pa_size'] : '-';
                    }
                }

                fputcsv($output, [
                    $campaign->name,
                    $item->product_id,
                    $item->variation_id ?: '-',
                    $product_name,
                    $sku,
                    $color,
                    $size,
                    $item->total_quantity,
                    $item->user_count
                ]);
            }
        }

        fclose($output);
    }

    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bulk_action'])) {
            return;
        }

        // Verify nonce
        if (!isset($_POST['bulk_nonce']) || !wp_verify_nonce($_POST['bulk_nonce'], 'tapp_bulk_actions')) {
            wp_die(__('Invalid security token.', 'tapp-campaigns'));
        }

        // Verify user can manage campaigns
        $user_id = get_current_user_id();
        if (!tapp_campaigns_onboarding()->can_create_campaigns($user_id)) {
            wp_die(__('You do not have permission to perform bulk actions.', 'tapp-campaigns'));
        }

        $action = sanitize_text_field($_POST['bulk_action']);
        $campaign_ids = isset($_POST['campaign_ids']) ? array_map('intval', $_POST['campaign_ids']) : [];

        if (empty($campaign_ids) || empty($action)) {
            return;
        }

        $count = 0;
        $errors = 0;

        foreach ($campaign_ids as $campaign_id) {
            $result = false;

            switch ($action) {
                case 'activate':
                    $result = TAPP_Campaigns_Campaign::update_status($campaign_id, 'active');
                    break;

                case 'end':
                    $result = TAPP_Campaigns_Campaign::update_status($campaign_id, 'ended');
                    break;

                case 'archive':
                    $result = TAPP_Campaigns_Campaign::update_status($campaign_id, 'archived');
                    break;

                case 'delete':
                    $result = TAPP_Campaigns_Campaign::delete($campaign_id);
                    break;
            }

            if ($result) {
                $count++;
            } else {
                $errors++;
            }
        }

        // Redirect with message
        $redirect_url = home_url('/campaign-manager/');
        $redirect_url = add_query_arg([
            'bulk_action' => $action,
            'count' => $count,
            'errors' => $errors,
        ], $redirect_url);

        wp_redirect($redirect_url);
        exit;
    }

    private function handle_dashboard_action($action) {
        // Actions handled by dashboard class
        if (class_exists('TAPP_Campaigns_Dashboard')) {
            $dashboard = new TAPP_Campaigns_Dashboard();
            $dashboard->handle_action($action);
        }
    }

    private function can_manage_campaign($campaign_id, $user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        $roles = $user->roles;
        if (in_array('administrator', $roles) || in_array('ceo', $roles)) {
            return true;
        }

        if (in_array('manager', $roles)) {
            $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
            if ($campaign && $campaign->creator_id == $user_id) {
                return true;
            }
        }

        return false;
    }
}
