<?php
/**
 * Frontend Class
 * Handles frontend initialization
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Frontend {

    public function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Add WooCommerce My Account tabs
        add_filter('woocommerce_account_menu_items', [$this, 'add_account_menu_items'], 40);
        add_action('woocommerce_account_campaigns_endpoint', [$this, 'campaigns_endpoint_content']);
        add_action('woocommerce_account_my-campaigns_endpoint', [$this, 'my_campaigns_endpoint_content']);
    }

    /**
     * Add custom tabs to My Account
     */
    public function add_account_menu_items($items) {
        $user_id = get_current_user_id();
        $onboarding = tapp_campaigns_onboarding();

        // Add "Campaign Manager" tab for managers/CEOs
        if ($onboarding->can_create_campaigns($user_id)) {
            $new_items = [];
            foreach ($items as $key => $value) {
                $new_items[$key] = $value;
                if ($key === 'orders') {
                    $new_items['campaigns'] = __('Campaign Manager', 'tapp-campaigns');
                }
            }
            $items = $new_items;
        }

        // Add "My Campaigns" tab for all users
        $new_items = [];
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            if ($key === 'orders') {
                $new_items['my-campaigns'] = __('My Campaigns', 'tapp-campaigns');
            }
        }

        return $new_items;
    }

    /**
     * Campaign Manager endpoint content
     */
    public function campaigns_endpoint_content() {
        if (!tapp_campaigns_onboarding()->can_create_campaigns(get_current_user_id())) {
            echo '<p>' . __('You do not have permission to access this page.', 'tapp-campaigns') . '</p>';
            return;
        }

        include TAPP_CAMPAIGNS_PATH . 'frontend/templates/dashboard.php';
    }

    /**
     * My Campaigns endpoint content
     */
    public function my_campaigns_endpoint_content() {
        include TAPP_CAMPAIGNS_PATH . 'frontend/templates/my-campaigns.php';
    }
}
