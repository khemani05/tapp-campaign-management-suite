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

        // Dashboard actions: /my-account/campaigns/create/
        add_rewrite_rule(
            '^my-account/campaigns/([^/]+)/?$',
            'index.php?pagename=my-account&campaign_action=$matches[1]',
            'top'
        );
    }

    public function add_account_endpoints() {
        if (!function_exists('wc_get_page_id')) {
            return;
        }

        // Add endpoints to WooCommerce My Account
        add_rewrite_endpoint('campaigns', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('my-campaigns', EP_ROOT | EP_PAGES);
    }

    public function template_redirect() {
        // Handle campaign page requests
        $campaign_slug = get_query_var('campaign');
        if ($campaign_slug) {
            $this->load_campaign_template($campaign_slug);
            exit;
        }

        // Handle dashboard actions
        $action = get_query_var('campaign_action');
        if ($action && is_account_page()) {
            $this->handle_dashboard_action($action);
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
