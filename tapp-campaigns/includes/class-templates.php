<?php
/**
 * Campaign Templates Class
 * Manages different campaign page layouts
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Templates {

    private static $templates = [
        'classic' => [
            'name' => 'Classic Grid',
            'description' => 'Traditional product grid with sidebar filters. Best for campaigns with many products.',
            'preview_image' => 'classic-preview.jpg',
            'layout' => 'grid',
            'columns' => 3,
            'has_sidebar' => true,
        ],
        'modern' => [
            'name' => 'Modern Carousel',
            'description' => 'Featured product carousel with smooth transitions. Great for showcasing products.',
            'preview_image' => 'modern-preview.jpg',
            'layout' => 'carousel',
            'has_featured' => true,
            'has_sidebar' => false,
        ],
        'minimal' => [
            'name' => 'Minimal List',
            'description' => 'Clean, compact list view. Perfect for mobile and quick selections.',
            'preview_image' => 'minimal-preview.jpg',
            'layout' => 'list',
            'columns' => 1,
            'has_sidebar' => false,
        ],
        'hero' => [
            'name' => 'Hero Banner',
            'description' => 'Large hero image with product grid below. Ideal for branded campaigns.',
            'preview_image' => 'hero-preview.jpg',
            'layout' => 'grid',
            'has_hero' => true,
            'columns' => 4,
        ]
    ];

    /**
     * Get all available templates
     */
    public static function get_all() {
        return apply_filters('tapp_campaign_templates', self::$templates);
    }

    /**
     * Get single template
     */
    public static function get($slug) {
        return self::$templates[$slug] ?? self::$templates['classic'];
    }

    /**
     * Get template file path
     */
    public static function get_template_file($slug) {
        $file = TAPP_CAMPAIGNS_PATH . "frontend/templates/layouts/campaign-{$slug}.php";
        return file_exists($file) ? $file : TAPP_CAMPAIGNS_PATH . 'frontend/templates/layouts/campaign-classic.php';
    }

    /**
     * Render campaign page with template
     */
    public static function render($campaign, $products, $user_responses = []) {
        $template_slug = $campaign->page_template ?? 'classic';
        $template_file = self::get_template_file($template_slug);

        // Make variables available to template
        $has_submitted = !empty($user_responses);
        $user_id = get_current_user_id();

        // Include the template
        include $template_file;
    }

    /**
     * Get campaign colors (for customization)
     */
    public static function get_campaign_colors($campaign) {
        return [
            'primary' => $campaign->template_primary_color ?? '#0073aa',
            'button' => $campaign->template_button_color ?? '#0073aa',
        ];
    }

    /**
     * Check if template exists
     */
    public static function template_exists($slug) {
        return isset(self::$templates[$slug]) && file_exists(self::get_template_file($slug));
    }

    /**
     * Load campaign layout template
     */
    public static function load_layout($slug, $args = []) {
        $template_file = self::get_template_file($slug);

        // Extract args to variables
        extract($args);

        // Include the template
        include $template_file;
    }
}
