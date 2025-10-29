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

        // Check if user is participant
        $user_id = get_current_user_id();
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
