<?php
/**
 * Campaign Banner System
 * Displays campaign notifications on homepage for invited users
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Banner {

    /**
     * Initialize banner system
     */
    public static function init() {
        // Only show to logged-in users
        if (!is_user_logged_in()) {
            return;
        }

        // Check if banners are enabled
        $enabled = get_option('tapp_campaigns_banner_enabled', true);
        if (!$enabled) {
            return;
        }

        // Add banner to homepage
        add_action('wp', [__CLASS__, 'setup_banner_hooks']);

        // AJAX handler for dismissing banners
        add_action('wp_ajax_tapp_dismiss_banner', [__CLASS__, 'ajax_dismiss_banner']);

        // Enqueue banner scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Setup hooks for banner placement
     */
    public static function setup_banner_hooks() {
        if (!is_front_page() && !is_home()) {
            return;
        }

        // Get user's pending campaigns
        $user_id = get_current_user_id();
        $pending = self::get_user_pending_campaigns($user_id);

        if (empty($pending)) {
            return;
        }

        // Try to detect best hook based on active theme
        $hook = self::detect_best_hook();
        add_action($hook, [__CLASS__, 'render_banner'], 10);
    }

    /**
     * Detect best hook for banner placement based on theme
     */
    private static function detect_best_hook() {
        $theme = wp_get_theme()->get_template();

        // Theme-specific hooks
        $theme_hooks = [
            'woodmart' => 'woodmart_before_header',
            'storefront' => 'storefront_before_header',
            'astra' => 'astra_header_before',
            'oceanwp' => 'ocean_before_header',
            'generatepress' => 'generate_before_header',
            'kadence' => 'kadence_before_header',
        ];

        if (isset($theme_hooks[$theme])) {
            return $theme_hooks[$theme];
        }

        // Fallback to generic hooks
        if (has_action('wp_body_open')) {
            return 'wp_body_open';
        }

        return 'wp_footer'; // Last resort
    }

    /**
     * Get user's pending campaigns
     */
    public static function get_user_pending_campaigns($user_id) {
        global $wpdb;

        $participants_table = $wpdb->prefix . 'tapp_campaign_participants';
        $campaigns_table = $wpdb->prefix . 'tapp_campaigns';

        // Get campaigns user is invited to but hasn't submitted
        $sql = $wpdb->prepare("
            SELECT c.*, p.submitted_at, p.dismissed_banner
            FROM {$campaigns_table} c
            INNER JOIN {$participants_table} p ON c.id = p.campaign_id
            WHERE p.user_id = %d
            AND c.status IN ('active', 'scheduled')
            AND p.submitted_at IS NULL
            AND (p.dismissed_banner IS NULL OR p.dismissed_banner = 0)
            AND c.end_date > NOW()
            ORDER BY c.end_date ASC
            LIMIT 3
        ", $user_id);

        return $wpdb->get_results($sql);
    }

    /**
     * Render banner
     */
    public static function render_banner() {
        $user_id = get_current_user_id();
        $campaigns = self::get_user_pending_campaigns($user_id);

        if (empty($campaigns)) {
            return;
        }

        $banner_style = get_option('tapp_campaigns_banner_style', 'banner');

        ?>
        <div class="tapp-campaigns-banner-container">
            <?php foreach ($campaigns as $campaign): ?>
                <?php
                $campaign_obj = (object) $campaign;
                $campaign_url = home_url('/campaign/' . $campaign_obj->slug . '/');
                $now = time();
                $start_time = strtotime($campaign_obj->start_date);
                $end_time = strtotime($campaign_obj->end_date);
                $is_started = $start_time <= $now;
                $time_remaining = $end_time - $now;

                // Calculate urgency level
                $urgency = 'normal';
                if ($time_remaining < 86400) { // Less than 24 hours
                    $urgency = 'urgent';
                } elseif ($time_remaining < 259200) { // Less than 3 days
                    $urgency = 'warning';
                }
                ?>

                <div class="tapp-campaign-banner tapp-banner-<?php echo esc_attr($banner_style); ?> tapp-urgency-<?php echo esc_attr($urgency); ?>"
                     data-campaign-id="<?php echo esc_attr($campaign_obj->id); ?>">

                    <div class="banner-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>

                    <div class="banner-content">
                        <div class="banner-header">
                            <h4 class="banner-title">
                                <?php if (!$is_started): ?>
                                    <?php _e('Upcoming Campaign:', 'tapp-campaigns'); ?>
                                <?php else: ?>
                                    <?php _e('Action Required:', 'tapp-campaigns'); ?>
                                <?php endif; ?>
                                <?php echo esc_html($campaign_obj->name); ?>
                            </h4>
                            <button class="banner-dismiss" data-campaign-id="<?php echo esc_attr($campaign_obj->id); ?>" aria-label="<?php _e('Dismiss', 'tapp-campaigns'); ?>">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                                </svg>
                            </button>
                        </div>

                        <?php if ($campaign_obj->notes): ?>
                            <p class="banner-description"><?php echo esc_html($campaign_obj->notes); ?></p>
                        <?php endif; ?>

                        <div class="banner-meta">
                            <span class="campaign-type-badge">
                                <?php echo esc_html(ucfirst($campaign_obj->type)); ?>
                            </span>
                            <span class="campaign-countdown" data-end-time="<?php echo $end_time; ?>">
                                <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71V3.5z"/>
                                    <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z"/>
                                </svg>
                                <span class="countdown-text"><?php _e('Loading...', 'tapp-campaigns'); ?></span>
                            </span>
                        </div>
                    </div>

                    <div class="banner-action">
                        <a href="<?php echo esc_url($campaign_url); ?>" class="banner-button">
                            <?php if (!$is_started): ?>
                                <?php _e('View Details', 'tapp-campaigns'); ?>
                            <?php else: ?>
                                <?php _e('Submit Now', 'tapp-campaigns'); ?>
                            <?php endif; ?>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" d="M4 8a.5.5 0 0 1 .5-.5h5.793L8.146 5.354a.5.5 0 1 1 .708-.708l3 3a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708-.708L10.293 8.5H4.5A.5.5 0 0 1 4 8z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * AJAX handler to dismiss banner
     */
    public static function ajax_dismiss_banner() {
        check_ajax_referer('tapp_campaigns_frontend', 'nonce');

        $campaign_id = intval($_POST['campaign_id']);
        $user_id = get_current_user_id();

        if (!$campaign_id || !$user_id) {
            wp_send_json_error(['message' => __('Invalid request', 'tapp-campaigns')]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaign_participants';

        $updated = $wpdb->update(
            $table,
            ['dismissed_banner' => 1],
            [
                'campaign_id' => $campaign_id,
                'user_id' => $user_id
            ],
            ['%d'],
            ['%d', '%d']
        );

        if ($updated !== false) {
            wp_send_json_success(['message' => __('Banner dismissed', 'tapp-campaigns')]);
        } else {
            wp_send_json_error(['message' => __('Failed to dismiss banner', 'tapp-campaigns')]);
        }
    }

    /**
     * Enqueue banner scripts
     */
    public static function enqueue_scripts() {
        if (!is_front_page() && !is_home()) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $pending = self::get_user_pending_campaigns($user_id);
        if (empty($pending)) {
            return;
        }

        wp_enqueue_style(
            'tapp-campaigns-banner',
            TAPP_CAMPAIGNS_URL . 'assets/css/banner.css',
            [],
            TAPP_CAMPAIGNS_VERSION
        );

        wp_enqueue_script(
            'tapp-campaigns-banner',
            TAPP_CAMPAIGNS_URL . 'assets/js/banner.js',
            ['jquery'],
            TAPP_CAMPAIGNS_VERSION,
            true
        );
    }
}

// Initialize
add_action('init', ['TAPP_Campaigns_Banner', 'init']);
