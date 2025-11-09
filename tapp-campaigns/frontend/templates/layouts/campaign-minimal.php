<?php
/**
 * Template: Minimal List Layout
 * Clean, compact list view optimized for mobile
 */

if (!defined('ABSPATH')) {
    exit;
}

$colors = TAPP_Campaigns_Templates::get_campaign_colors($campaign);
$now = time();
$end_time = strtotime($campaign->end_date);
$is_ended = $end_time <= $now || $campaign->status === 'ended';
$can_edit = !$is_ended && (!$has_submitted || $campaign->edit_policy !== 'once');

?>

<style>
    :root {
        --tapp-primary-color: <?php echo esc_attr($colors['primary']); ?>;
        --tapp-button-color: <?php echo esc_attr($colors['button']); ?>;
    }
</style>

<div class="tapp-campaign-page tapp-template-minimal">
    <div class="container-minimal">

        <!-- Minimal Header -->
        <div class="minimal-header">
            <h1><?php echo esc_html($campaign->name); ?></h1>
            <?php if (!$is_ended): ?>
                <div class="countdown-compact" data-end-time="<?php echo $end_time; ?>">
                    ⏰ <span class="countdown-timer"></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($campaign->notes): ?>
            <div class="notes-minimal">
                <?php echo esc_html($campaign->notes); ?>
            </div>
        <?php endif; ?>

        <?php if ($has_submitted && !$can_edit): ?>
            <!-- Submitted -->
            <div class="submitted-minimal">
                <div class="check-icon">✓</div>
                <p><?php _e('Response submitted', 'tapp-campaigns'); ?></p>
            </div>

        <?php elseif (!$is_ended): ?>
            <!-- Product List -->
            <form id="tapp-campaign-form" class="minimal-form">
                <?php wp_nonce_field('tapp_campaign_submit', 'tapp_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">

                <div class="selection-bar">
                    <span><?php _e('Selected:', 'tapp-campaigns'); ?> <strong><span class="count">0</span>/<?php echo $campaign->selection_limit; ?></strong></span>
                </div>

                <div class="product-list-minimal">
                    <?php foreach ($products as $product): ?>
                        <div class="product-item-minimal" data-product-id="<?php echo $product->get_id(); ?>">
                            <div class="item-image">
                                <?php echo $product->get_image('thumbnail'); ?>
                            </div>
                            <div class="item-content">
                                <h3><?php echo $product->get_name(); ?></h3>
                                <p class="sku"><?php echo $product->get_sku(); ?></p>

                                <?php
                                $colors = TAPP_Campaigns_Campaign_Page::get_available_colors($product->get_id(), $campaign);
                                $sizes = TAPP_Campaigns_Campaign_Page::get_available_sizes($product->get_id(), $campaign);
                                ?>

                                <div class="options-compact">
                                    <?php if (!empty($colors)): ?>
                                        <select class="product-color compact-select">
                                            <option value=""><?php _e('Color', 'tapp-campaigns'); ?></option>
                                            <?php foreach ($colors as $color): ?>
                                                <option value="<?php echo esc_attr($color['slug']); ?>"><?php echo esc_html($color['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>

                                    <?php if (!empty($sizes)): ?>
                                        <select class="product-size compact-select">
                                            <option value=""><?php _e('Size', 'tapp-campaigns'); ?></option>
                                            <?php foreach ($sizes as $size): ?>
                                                <option value="<?php echo esc_attr($size['slug']); ?>"><?php echo esc_html($size['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>

                                    <?php if ($campaign->ask_quantity): ?>
                                        <input type="number" class="product-quantity compact-input" min="1" max="10" value="1">
                                    <?php endif; ?>
                                </div>

                                <label class="checkbox-minimal">
                                    <input type="checkbox" class="product-checkbox" value="<?php echo $product->get_id(); ?>">
                                    <span class="checkbox-label"><?php _e('Select', 'tapp-campaigns'); ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="sticky-footer">
                    <button type="submit" class="button-minimal">
                        <?php _e('Submit Selections', 'tapp-campaigns'); ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>
