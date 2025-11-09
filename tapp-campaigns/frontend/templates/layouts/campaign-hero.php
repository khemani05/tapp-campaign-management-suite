<?php
/**
 * Template: Hero Banner Layout
 * Large hero image with product grid
 */

if (!defined('ABSPATH')) {
    exit;
}

$colors = TAPP_Campaigns_Templates::get_campaign_colors($campaign);
$hero_image = $campaign->template_hero_image ?? '';
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

<div class="tapp-campaign-page tapp-template-hero">

    <!-- Hero Banner -->
    <div class="hero-banner" <?php if ($hero_image): ?>style="background-image: url('<?php echo esc_url($hero_image); ?>');"<?php endif; ?>>
        <div class="hero-overlay">
            <div class="hero-content">
                <h1 class="hero-title"><?php echo esc_html($campaign->name); ?></h1>
                <?php if ($campaign->notes): ?>
                    <p class="hero-subtitle"><?php echo esc_html($campaign->notes); ?></p>
                <?php endif; ?>
                <?php if (!$is_ended): ?>
                    <div class="hero-countdown" data-end-time="<?php echo $end_time; ?>">
                        <div class="countdown-timer-hero"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="container">

        <?php if ($campaign->description): ?>
            <div class="campaign-intro">
                <?php echo wp_kses_post($campaign->description); ?>
            </div>
        <?php endif; ?>

        <?php if ($has_submitted && !$can_edit): ?>
            <!-- Submitted -->
            <div class="hero-submitted">
                <h2><?php _e('✓ Thank You for Participating!', 'tapp-campaigns'); ?></h2>
                <p><?php _e('Your selections have been recorded.', 'tapp-campaigns'); ?></p>
            </div>

        <?php elseif (!$is_ended): ?>
            <!-- Product Grid -->
            <form id="tapp-campaign-form" class="hero-form">
                <?php wp_nonce_field('tapp_campaign_submit', 'tapp_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">

                <div class="selection-header-hero">
                    <h2><?php _e('Choose Your Items', 'tapp-campaigns'); ?></h2>
                    <div class="selection-counter-hero">
                        <span class="count">0</span> / <?php echo $campaign->selection_limit; ?> <?php _e('selected', 'tapp-campaigns'); ?>
                    </div>
                </div>

                <div class="products-grid-hero">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card-hero" data-product-id="<?php echo $product->get_id(); ?>">
                            <div class="card-image">
                                <?php echo $product->get_image('medium'); ?>
                            </div>
                            <div class="card-body">
                                <h3><?php echo $product->get_name(); ?></h3>
                                <p class="sku"><?php echo $product->get_sku(); ?></p>

                                <?php
                                $colors = TAPP_Campaigns_Campaign_Page::get_available_colors($product->get_id(), $campaign);
                                $sizes = TAPP_Campaigns_Campaign_Page::get_available_sizes($product->get_id(), $campaign);
                                ?>

                                <?php if (!empty($colors)): ?>
                                    <div class="option-group">
                                        <label><?php _e('Color', 'tapp-campaigns'); ?></label>
                                        <select class="product-color">
                                            <option value=""><?php _e('Select', 'tapp-campaigns'); ?></option>
                                            <?php foreach ($colors as $color): ?>
                                                <option value="<?php echo esc_attr($color['slug']); ?>"><?php echo esc_html($color['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($sizes)): ?>
                                    <div class="option-group">
                                        <label><?php _e('Size', 'tapp-campaigns'); ?></label>
                                        <select class="product-size">
                                            <option value=""><?php _e('Select', 'tapp-campaigns'); ?></option>
                                            <?php foreach ($sizes as $size): ?>
                                                <option value="<?php echo esc_attr($size['slug']); ?>"><?php echo esc_html($size['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if ($campaign->ask_quantity): ?>
                                    <div class="option-group">
                                        <label><?php _e('Qty', 'tapp-campaigns'); ?></label>
                                        <input type="number" class="product-quantity" min="1" max="10" value="1">
                                    </div>
                                <?php endif; ?>

                                <label class="checkbox-card">
                                    <input type="checkbox" class="product-checkbox" value="<?php echo $product->get_id(); ?>">
                                    <span><?php _e('Select This Item', 'tapp-campaigns'); ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-footer-hero">
                    <button type="submit" class="button-hero button-large">
                        <?php _e('Submit Your Selections', 'tapp-campaigns'); ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>
