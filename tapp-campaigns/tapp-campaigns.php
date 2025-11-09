<?php
/**
 * Plugin Name: TAPP Campaigns
 * Description: Enterprise-scale campaign management for internal teams. Create time-boxed product selection campaigns with WooCommerce integration.
 * Version: 1.0.0
 * Author: TAPP
 * Text Domain: tapp-campaigns
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('TAPP_CAMPAIGNS_VERSION', '1.0.0');
define('TAPP_CAMPAIGNS_PATH', plugin_dir_path(__FILE__));
define('TAPP_CAMPAIGNS_URL', plugin_dir_url(__FILE__));
define('TAPP_CAMPAIGNS_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>TAPP Campaigns:</strong> This plugin requires WooCommerce to be installed and active.';
        echo '</p></div>';
    });
    return;
}

// Autoload classes
spl_autoload_register(function($class) {
    $prefix = 'TAPP_Campaigns_';
    $base_dir = TAPP_CAMPAIGNS_PATH . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once TAPP_CAMPAIGNS_PATH . 'includes/class-activator.php';
    TAPP_Campaigns_Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    require_once TAPP_CAMPAIGNS_PATH . 'includes/class-deactivator.php';
    TAPP_Campaigns_Deactivator::deactivate();
});

// Initialize plugin
add_action('plugins_loaded', function() {
    // Load text domain
    load_plugin_textdomain('tapp-campaigns', false, dirname(TAPP_CAMPAIGNS_BASENAME) . '/languages');

    // Initialize core components
    if (class_exists('TAPP_Campaigns_Core')) {
        $plugin = new TAPP_Campaigns_Core();
        $plugin->init();
    }

    // Initialize homepage banner system
    require_once TAPP_CAMPAIGNS_PATH . 'frontend/class-banner.php';
}, 10);

// Admin enqueue scripts
add_action('admin_enqueue_scripts', function($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'tapp-campaigns') === false) {
        return;
    }

    wp_enqueue_style('tapp-campaigns-admin', TAPP_CAMPAIGNS_URL . 'assets/css/admin.css', [], TAPP_CAMPAIGNS_VERSION);
    wp_enqueue_script('tapp-campaigns-admin', TAPP_CAMPAIGNS_URL . 'assets/js/admin.js', ['jquery'], TAPP_CAMPAIGNS_VERSION, true);

    wp_localize_script('tapp-campaigns-admin', 'tappCampaigns', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('tapp_campaigns_admin'),
        'strings' => [
            'confirmDelete' => __('Are you sure you want to delete this campaign?', 'tapp-campaigns'),
            'error' => __('An error occurred. Please try again.', 'tapp-campaigns'),
        ]
    ]);
});

// Frontend enqueue scripts
add_action('wp_enqueue_scripts', function() {
    if (!is_user_logged_in()) {
        return;
    }

    wp_enqueue_style('tapp-campaigns-frontend', TAPP_CAMPAIGNS_URL . 'assets/css/frontend.css', [], TAPP_CAMPAIGNS_VERSION);
    wp_enqueue_script('tapp-campaigns-frontend', TAPP_CAMPAIGNS_URL . 'assets/js/frontend.js', ['jquery'], TAPP_CAMPAIGNS_VERSION, true);

    wp_localize_script('tapp-campaigns-frontend', 'tappCampaigns', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('tapp_campaigns_frontend'),
        'strings' => [
            'selectProduct' => __('Please select at least one product', 'tapp-campaigns'),
            'selectColor' => __('Please select color', 'tapp-campaigns'),
            'selectSize' => __('Please select size', 'tapp-campaigns'),
            'confirmSubmit' => __('Are you sure you want to submit your selections?', 'tapp-campaigns'),
        ]
    ]);

    // Enqueue analytics assets on analytics page
    $campaign_action = get_query_var('campaign_action');
    if ($campaign_action === 'analytics') {
        // Enqueue Chart.js from CDN
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);

        // Enqueue analytics CSS
        wp_enqueue_style('tapp-campaigns-analytics', TAPP_CAMPAIGNS_URL . 'assets/css/analytics.css', [], TAPP_CAMPAIGNS_VERSION);

        // Enqueue analytics JS
        wp_enqueue_script('tapp-campaigns-analytics', TAPP_CAMPAIGNS_URL . 'assets/js/analytics.js', ['jquery', 'chartjs'], TAPP_CAMPAIGNS_VERSION, true);
    }
});
