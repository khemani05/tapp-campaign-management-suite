<?php
/**
 * Admin Class
 * Handles WordPress admin area
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_export']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('TAPP Campaigns', 'tapp-campaigns'),
            __('Campaigns', 'tapp-campaigns'),
            'manage_options',
            'tapp-campaigns',
            [$this, 'admin_page'],
            'dashicons-megaphone',
            30
        );

        add_submenu_page(
            'tapp-campaigns',
            __('Settings', 'tapp-campaigns'),
            __('Settings', 'tapp-campaigns'),
            'manage_options',
            'tapp-campaigns-settings',
            [$this, 'settings_page']
        );
    }

    /**
     * Admin page
     */
    public function admin_page() {
        $campaigns = TAPP_Campaigns_Database::get_campaigns(['limit' => 100]);
        include TAPP_CAMPAIGNS_PATH . 'admin/views/campaigns-list.php';
    }

    /**
     * Settings page
     */
    public function settings_page() {
        // Handle settings save
        if (isset($_POST['save_settings'])) {
            check_admin_referer('tapp_campaigns_settings');

            update_option('tapp_campaigns_banner_enabled', isset($_POST['banner_enabled']));
            update_option('tapp_campaigns_banner_position', sanitize_text_field($_POST['banner_position']));
            update_option('tapp_campaigns_email_from_name', sanitize_text_field($_POST['email_from_name']));
            update_option('tapp_campaigns_email_from_email', sanitize_email($_POST['email_from_email']));

            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'tapp-campaigns') . '</p></div>';
        }

        include TAPP_CAMPAIGNS_PATH . 'admin/views/settings.php';
    }

    /**
     * Handle CSV export
     */
    public function handle_export() {
        if (isset($_GET['tapp_export']) && isset($_GET['campaign_id'])) {
            $campaign_id = intval($_GET['campaign_id']);

            if (!current_user_can('manage_options') && !tapp_campaigns_onboarding()->can_edit_campaign($campaign_id, get_current_user_id())) {
                wp_die(__('Unauthorized', 'tapp-campaigns'));
            }

            $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
            if (!$campaign) {
                wp_die(__('Campaign not found', 'tapp-campaigns'));
            }

            $export_type = sanitize_text_field($_GET['tapp_export']);
            $filename = sanitize_title($campaign->name) . '-' . $export_type . '-' . date('Y-m-d') . '.csv';

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            if ($export_type === 'responses') {
                // Export responses
                fputcsv($output, ['User', 'Email', 'Product', 'SKU', 'Color', 'Size', 'Quantity', 'Submitted At']);

                $data = TAPP_Campaigns_Response::export($campaign_id);
                foreach ($data as $row) {
                    fputcsv($output, [
                        $row['user_name'],
                        $row['user_email'],
                        $row['product_name'],
                        $row['sku'],
                        $row['color'],
                        $row['size'],
                        $row['quantity'],
                        $row['submitted_at'],
                    ]);
                }
            } elseif ($export_type === 'summary') {
                // Export summary
                fputcsv($output, ['Product', 'SKU', 'Color', 'Size', 'Total Quantity', 'Number of Users']);

                $summary = TAPP_Campaigns_Response::get_product_summary($campaign_id);
                foreach ($summary as $row) {
                    fputcsv($output, [
                        $row['product_name'],
                        $row['sku'],
                        $row['color'],
                        $row['size'],
                        $row['total_quantity'],
                        $row['user_count'],
                    ]);
                }
            }

            fclose($output);
            exit;
        }
    }
}
