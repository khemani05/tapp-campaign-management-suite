<?php
/**
 * Navigation Class
 * Adds campaigns to main navigation menu
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Navigation {

    public function __construct() {
        add_filter('wp_nav_menu_items', [$this, 'add_campaigns_menu'], 10, 2);
    }

    /**
     * Add campaigns to navigation menu
     */
    public function add_campaigns_menu($items, $args) {
        // Only add to primary menu
        if (!isset($args->theme_location) || $args->theme_location !== 'primary') {
            return $items;
        }

        if (!is_user_logged_in()) {
            return $items;
        }

        $user_id = get_current_user_id();
        $onboarding = tapp_campaigns_onboarding();

        // Check if user can manage campaigns
        if ($onboarding->can_create_campaigns($user_id)) {
            $items .= $this->get_manager_menu();
        } else {
            $items .= $this->get_user_menu();
        }

        return $items;
    }

    /**
     * Get manager menu HTML
     */
    private function get_manager_menu() {
        $user_id = get_current_user_id();
        $active_count = $this->get_active_campaigns_count($user_id);

        $dashboard_url = home_url('/campaign-manager/');

        ob_start();
        ?>
        <li class="menu-item menu-item-has-children tapp-manager-menu">
            <a href="<?php echo esc_url($dashboard_url); ?>">
                <?php _e('Campaigns', 'tapp-campaigns'); ?>
                <?php if ($active_count > 0): ?>
                    <span class="tapp-badge"><?php echo $active_count; ?></span>
                <?php endif; ?>
            </a>
            <ul class="sub-menu">
                <li><a href="<?php echo esc_url($dashboard_url); ?>"><?php _e('Dashboard', 'tapp-campaigns'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/campaign-manager/create-team/')); ?>"><?php _e('Create Team Campaign', 'tapp-campaigns'); ?></a></li>
                <li><a href="<?php echo esc_url(home_url('/campaign-manager/create-sales/')); ?>"><?php _e('Create Sales Campaign', 'tapp-campaigns'); ?></a></li>
                <li><a href="<?php echo esc_url(add_query_arg('filter', 'active', $dashboard_url)); ?>"><?php _e('Active Campaigns', 'tapp-campaigns'); ?></a></li>
                <li><a href="<?php echo esc_url(add_query_arg('filter', 'scheduled', $dashboard_url)); ?>"><?php _e('Scheduled Campaigns', 'tapp-campaigns'); ?></a></li>
                <li><a href="<?php echo esc_url(add_query_arg('filter', 'ended', $dashboard_url)); ?>"><?php _e('Ended Campaigns', 'tapp-campaigns'); ?></a></li>
            </ul>
        </li>
        <?php
        return ob_get_clean();
    }

    /**
     * Get user menu HTML
     */
    private function get_user_menu() {
        $user_id = get_current_user_id();
        $campaigns = $this->get_user_active_campaigns($user_id);

        if (empty($campaigns)) {
            return '';
        }

        $pending_count = 0;
        foreach ($campaigns as $campaign) {
            if (!TAPP_Campaigns_Response::has_submitted($campaign->id, $user_id)) {
                $pending_count++;
            }
        }

        ob_start();
        ?>
        <li class="menu-item menu-item-has-children tapp-user-menu">
            <a href="<?php echo esc_url(home_url('/my-campaigns/')); ?>">
                <?php _e('Campaigns', 'tapp-campaigns'); ?>
                <?php if ($pending_count > 0): ?>
                    <span class="tapp-badge tapp-badge-pending"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <ul class="sub-menu">
                <?php foreach ($campaigns as $campaign): ?>
                    <?php
                    $has_submitted = TAPP_Campaigns_Response::has_submitted($campaign->id, $user_id);
                    $time_remaining = $this->get_time_remaining($campaign->end_date);
                    ?>
                    <li class="<?php echo $has_submitted ? 'submitted' : 'pending'; ?>">
                        <a href="<?php echo esc_url(TAPP_Campaigns_Campaign::get_url($campaign->id)); ?>">
                            <span class="campaign-name"><?php echo esc_html($campaign->name); ?></span>
                            <small class="time-left">
                                <?php if ($has_submitted): ?>
                                    <?php _e('✓ Submitted', 'tapp-campaigns'); ?>
                                <?php else: ?>
                                    <?php echo esc_html($time_remaining); ?>
                                <?php endif; ?>
                            </small>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php
        return ob_get_clean();
    }

    /**
     * Get active campaigns count for manager
     */
    private function get_active_campaigns_count($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'tapp_campaigns';

        $onboarding = tapp_campaigns_onboarding();

        if ($onboarding->can_view_all_campaigns($user_id)) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status = 'active' AND creator_id = %d",
            $user_id
        ));
    }

    /**
     * Get user's active campaigns (where they are participants)
     */
    private function get_user_active_campaigns($user_id) {
        global $wpdb;
        $campaigns_table = $wpdb->prefix . 'tapp_campaigns';
        $participants_table = $wpdb->prefix . 'tapp_participants';

        return $wpdb->get_results($wpdb->prepare("
            SELECT c.*
            FROM $campaigns_table c
            INNER JOIN $participants_table p ON c.id = p.campaign_id
            WHERE p.user_id = %d
            AND c.status = 'active'
            AND c.start_date <= %s
            AND c.end_date > %s
            ORDER BY c.end_date ASC
            LIMIT 10
        ", $user_id, current_time('mysql'), current_time('mysql')));
    }

    /**
     * Get time remaining formatted
     */
    private function get_time_remaining($end_date) {
        $remaining = strtotime($end_date) - time();

        if ($remaining <= 0) {
            return __('Ended', 'tapp-campaigns');
        }

        $days = floor($remaining / DAY_IN_SECONDS);
        $hours = floor(($remaining % DAY_IN_SECONDS) / HOUR_IN_SECONDS);

        if ($days > 0) {
            return sprintf(__('%dd left', 'tapp-campaigns'), $days);
        } elseif ($hours > 0) {
            return sprintf(__('%dh left', 'tapp-campaigns'), $hours);
        } else {
            return __('Ending soon!', 'tapp-campaigns');
        }
    }
}
