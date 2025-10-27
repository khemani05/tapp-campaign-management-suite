<?php
/**
 * Plugin Activator
 * Handles plugin activation: database creation, default options, roles
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Activator {

    public static function activate() {
        global $wpdb;

        self::create_tables();
        self::create_roles();
        self::set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Store activation timestamp
        update_option('tapp_campaigns_activated', time());
        update_option('tapp_campaigns_version', TAPP_CAMPAIGNS_VERSION);
    }

    private static function create_tables() {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sql = [];

        // Campaigns table
        $sql[] = "CREATE TABLE {$prefix}tapp_campaigns (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            type ENUM('team', 'sales') NOT NULL DEFAULT 'team',
            status ENUM('draft', 'scheduled', 'active', 'ended', 'archived') NOT NULL DEFAULT 'draft',
            creator_id BIGINT UNSIGNED NOT NULL,
            department VARCHAR(100) DEFAULT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            notes TEXT DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            selection_limit INT DEFAULT 1,
            selection_min INT DEFAULT 0,
            edit_policy ENUM('once', 'multiple', 'until_end') DEFAULT 'once',
            ask_color TINYINT(1) DEFAULT 1,
            color_config ENUM('all', 'specific') DEFAULT 'all',
            allowed_colors TEXT DEFAULT NULL,
            ask_size TINYINT(1) DEFAULT 1,
            ask_quantity TINYINT(1) DEFAULT 1,
            min_quantity INT DEFAULT 1,
            max_quantity INT DEFAULT 10,
            page_template VARCHAR(50) DEFAULT 'classic',
            template_primary_color VARCHAR(7) DEFAULT '#0073aa',
            template_button_color VARCHAR(7) DEFAULT '#0073aa',
            template_hero_image VARCHAR(500) DEFAULT NULL,
            payment_enabled TINYINT(1) DEFAULT 0,
            generate_po TINYINT(1) DEFAULT 0,
            generate_invoice TINYINT(1) DEFAULT 0,
            invoice_recipients TEXT DEFAULT NULL,
            send_invitation TINYINT(1) DEFAULT 1,
            send_confirmation TINYINT(1) DEFAULT 1,
            send_reminder TINYINT(1) DEFAULT 1,
            reminder_hours INT DEFAULT 24,
            webhook_url VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_creator (creator_id),
            INDEX idx_dates (start_date, end_date),
            INDEX idx_type (type),
            INDEX idx_department (department),
            INDEX idx_slug (slug)
        ) $charset_collate;";

        // Campaign Products junction table
        $sql[] = "CREATE TABLE {$prefix}tapp_campaign_products (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            display_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_campaign_product (campaign_id, product_id),
            INDEX idx_campaign (campaign_id),
            INDEX idx_product (product_id),
            INDEX idx_order (display_order)
        ) $charset_collate;";

        // Participants table
        $sql[] = "CREATE TABLE {$prefix}tapp_participants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(100) NOT NULL,
            status ENUM('invited', 'submitted', 'pending') DEFAULT 'invited',
            invited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            submitted_at DATETIME DEFAULT NULL,
            response_count INT DEFAULT 0,
            UNIQUE KEY unique_campaign_user (campaign_id, user_id),
            INDEX idx_campaign (campaign_id),
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_email (email)
        ) $charset_collate;";

        // Responses table (with version tracking)
        $sql[] = "CREATE TABLE {$prefix}tapp_responses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            variation_id BIGINT UNSIGNED DEFAULT 0,
            color VARCHAR(100) DEFAULT NULL,
            size VARCHAR(100) DEFAULT NULL,
            quantity INT DEFAULT 1,
            version INT DEFAULT 1,
            is_latest TINYINT(1) DEFAULT 1,
            edited_by BIGINT UNSIGNED DEFAULT NULL,
            edit_reason TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_campaign_user_latest (campaign_id, user_id, is_latest),
            INDEX idx_campaign (campaign_id),
            INDEX idx_user (user_id),
            INDEX idx_product (product_id),
            INDEX idx_version (version)
        ) $charset_collate;";

        // Campaign meta table
        $sql[] = "CREATE TABLE {$prefix}tapp_campaign_meta (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            campaign_id BIGINT UNSIGNED NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT DEFAULT NULL,
            INDEX idx_campaign (campaign_id),
            INDEX idx_key (meta_key(191)),
            UNIQUE KEY unique_campaign_meta (campaign_id, meta_key(191))
        ) $charset_collate;";

        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    private static function create_roles() {
        // Only create roles if they don't exist (might be created by onboarding plugin)
        if (!get_role('manager')) {
            add_role('manager', __('Manager', 'tapp-campaigns'), [
                'read' => true,
                'create_campaigns' => true,
                'edit_campaigns' => true,
                'delete_campaigns' => true,
                'view_campaigns' => true,
            ]);
        }

        if (!get_role('ceo')) {
            add_role('ceo', __('CEO', 'tapp-campaigns'), [
                'read' => true,
                'create_campaigns' => true,
                'edit_campaigns' => true,
                'edit_all_campaigns' => true,
                'delete_campaigns' => true,
                'delete_all_campaigns' => true,
                'view_all_campaigns' => true,
            ]);
        }

        if (!get_role('staff')) {
            add_role('staff', __('Staff', 'tapp-campaigns'), [
                'read' => true,
                'participate_campaigns' => true,
            ]);
        }

        // Add capabilities to administrator
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('create_campaigns');
            $admin->add_cap('edit_campaigns');
            $admin->add_cap('edit_all_campaigns');
            $admin->add_cap('delete_campaigns');
            $admin->add_cap('delete_all_campaigns');
            $admin->add_cap('view_all_campaigns');
            $admin->add_cap('manage_campaign_settings');
        }

        // Add capabilities to customer role
        $customer = get_role('customer');
        if ($customer) {
            $customer->add_cap('participate_campaigns');
        }
    }

    private static function set_default_options() {
        $defaults = [
            'tapp_campaigns_banner_enabled' => true,
            'tapp_campaigns_banner_position' => 'before_footer',
            'tapp_campaigns_banner_dismissal' => '24hours',
            'tapp_campaigns_quick_select' => true,
            'tapp_campaigns_mobile_banner' => 'sticky_bottom',
            'tapp_campaigns_default_template' => 'classic',
            'tapp_campaigns_email_from_name' => get_bloginfo('name'),
            'tapp_campaigns_email_from_email' => get_option('admin_email'),
            'tapp_campaigns_per_page' => 20,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
