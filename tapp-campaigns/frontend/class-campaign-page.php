<?php
/**
 * Campaign Page Class
 * Handles campaign page rendering
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Campaign_Page {

    /**
     * Get available colors for product in campaign
     */
    public static function get_available_colors($product_id, $campaign) {
        if (!$campaign->ask_color) {
            return [];
        }

        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return [];
        }

        $colors = [];
        $variations = $product->get_available_variations();

        foreach ($variations as $variation) {
            if (isset($variation['attributes']['attribute_pa_color'])) {
                $color_slug = $variation['attributes']['attribute_pa_color'];
                $color_term = get_term_by('slug', $color_slug, 'pa_color');

                if ($color_term && !isset($colors[$color_slug])) {
                    $colors[$color_slug] = [
                        'name' => $color_term->name,
                        'slug' => $color_slug,
                    ];
                }
            }
        }

        // Filter by allowed colors if specific mode
        if ($campaign->color_config === 'specific' && !empty($campaign->allowed_colors)) {
            $allowed = json_decode($campaign->allowed_colors, true);
            if (is_array($allowed)) {
                $colors = array_filter($colors, function($color) use ($allowed) {
                    return in_array($color['slug'], $allowed);
                });
            }
        }

        return array_values($colors);
    }

    /**
     * Get available sizes for product in campaign
     */
    public static function get_available_sizes($product_id, $campaign) {
        if (!$campaign->ask_size) {
            return [];
        }

        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return [];
        }

        $sizes = [];
        $variations = $product->get_available_variations();

        foreach ($variations as $variation) {
            if (isset($variation['attributes']['attribute_pa_size'])) {
                $size_slug = $variation['attributes']['attribute_pa_size'];
                $size_term = get_term_by('slug', $size_slug, 'pa_size');

                if ($size_term && !isset($sizes[$size_slug])) {
                    $sizes[$size_slug] = [
                        'name' => $size_term->name,
                        'slug' => $size_slug,
                    ];
                }
            }
        }

        return array_values($sizes);
    }
}
