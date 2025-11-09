<?php
/**
 * Template: Modern Carousel Layout
 * Features a prominent carousel/slider for products
 */

if (!defined('ABSPATH')) {
    exit;
}

$colors = TAPP_Campaigns_Templates::get_campaign_colors($campaign);
$now = time();
$end_time = strtotime($campaign->end_date);
$start_time = strtotime($campaign->start_date);
$is_started = $start_time <= $now;
$is_ended = $end_time <= $now || $campaign->status === 'ended';
$can_edit = !$is_ended && (!$has_submitted || $campaign->edit_policy !== 'once');

?>

<style>
    :root {
        --tapp-primary-color: <?php echo esc_attr($colors['primary']); ?>;
        --tapp-button-color: <?php echo esc_attr($colors['button']); ?>;
    }
</style>

<div class="tapp-campaign-page tapp-template-modern">
    <div class="container">

        <!-- Campaign Header -->
        <div class="tapp-campaign-header modern-header">
            <div class="header-content">
                <h1><?php echo esc_html($campaign->name); ?></h1>
                <div class="campaign-type-badge"><?php echo esc_html(ucfirst($campaign->type)); ?></div>

                <?php if (!$is_ended): ?>
                    <div class="tapp-countdown-modern" data-end-time="<?php echo $end_time; ?>">
                        <div class="countdown-label"><?php echo $is_started ? __('Ends in', 'tapp-campaigns') : __('Starts in', 'tapp-campaigns'); ?></div>
                        <div class="countdown-timer-large"></div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($campaign->notes): ?>
                <div class="campaign-notes-modern">
                    <p><?php echo esc_html($campaign->notes); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($has_submitted && !$can_edit): ?>
            <!-- Submitted view -->
            <div class="tapp-submission-complete">
                <div class="success-icon">✓</div>
                <h2><?php _e('Thank You!', 'tapp-campaigns'); ?></h2>
                <p><?php _e('Your selections have been submitted successfully.', 'tapp-campaigns'); ?></p>

                <h3><?php _e('Your Selections:', 'tapp-campaigns'); ?></h3>
                <div class="selected-items-list">
                    <?php foreach ($user_responses as $response): ?>
                        <?php $product = wc_get_product($response->product_id); ?>
                        <?php if ($product): ?>
                            <div class="selected-item-card">
                                <img src="<?php echo wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'); ?>" alt="">
                                <div>
                                    <strong><?php echo esc_html($product->get_name()); ?></strong>
                                    <?php if ($response->color): ?>
                                        <span><?php echo esc_html($response->color); ?></span>
                                    <?php endif; ?>
                                    <?php if ($response->size): ?>
                                        <span><?php echo esc_html($response->size); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif (!$is_started || $is_ended): ?>
            <div class="campaign-status-message">
                <h2><?php echo $is_ended ? __('Campaign Ended', 'tapp-campaigns') : __('Campaign Not Started', 'tapp-campaigns'); ?></h2>
            </div>

        <?php else: ?>
            <!-- Product Carousel -->
            <form id="tapp-campaign-form" method="post" class="modern-form">
                <?php wp_nonce_field('tapp_campaign_submit', 'tapp_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">

                <div class="selection-counter-modern">
                    <span class="label"><?php _e('Selected:', 'tapp-campaigns'); ?></span>
                    <span class="count">0</span>
                    <span class="limit">/ <?php echo $campaign->selection_limit; ?></span>
                </div>

                <!-- Featured Carousel -->
                <div class="tapp-product-carousel">
                    <button type="button" class="carousel-nav prev" aria-label="Previous">‹</button>
                    <div class="carousel-container">
                        <div class="carousel-track">
                            <?php foreach ($products as $index => $product): ?>
                                <div class="carousel-slide" data-product-id="<?php echo $product->get_id(); ?>">
                                    <div class="slide-content">
                                        <div class="slide-image">
                                            <?php echo $product->get_image('large'); ?>
                                        </div>
                                        <div class="slide-info">
                                            <h3><?php echo $product->get_name(); ?></h3>
                                            <p class="product-sku"><?php echo $product->get_sku(); ?></p>

                                            <?php
                                            $colors = TAPP_Campaigns_Campaign_Page::get_available_colors($product->get_id(), $campaign);
                                            $sizes = TAPP_Campaigns_Campaign_Page::get_available_sizes($product->get_id(), $campaign);
                                            ?>

                                            <div class="product-options-modern">
                                                <?php if (!empty($colors)): ?>
                                                    <div class="option-group">
                                                        <label><?php _e('Color:', 'tapp-campaigns'); ?></label>
                                                        <div class="color-swatches">
                                                            <?php foreach ($colors as $color): ?>
                                                                <button type="button" class="color-swatch" data-color="<?php echo esc_attr($color['slug']); ?>" title="<?php echo esc_attr($color['name']); ?>">
                                                                    <?php echo esc_html($color['name']); ?>
                                                                </button>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($sizes)): ?>
                                                    <div class="option-group">
                                                        <label><?php _e('Size:', 'tapp-campaigns'); ?></label>
                                                        <div class="size-buttons">
                                                            <?php foreach ($sizes as $size): ?>
                                                                <button type="button" class="size-button" data-size="<?php echo esc_attr($size['slug']); ?>">
                                                                    <?php echo esc_html($size['name']); ?>
                                                                </button>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($campaign->ask_quantity): ?>
                                                    <div class="option-group">
                                                        <label><?php _e('Quantity:', 'tapp-campaigns'); ?></label>
                                                        <input type="number" class="product-quantity" min="<?php echo $campaign->min_quantity; ?>" max="<?php echo $campaign->max_quantity; ?>" value="1">
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <button type="button" class="add-to-selection-btn" data-product-id="<?php echo $product->get_id(); ?>">
                                                <span class="btn-add"><?php _e('+ Add to Selection', 'tapp-campaigns'); ?></span>
                                                <span class="btn-added"><?php _e('✓ Added', 'tapp-campaigns'); ?></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="button" class="carousel-nav next" aria-label="Next">›</button>
                </div>

                <!-- Carousel Dots -->
                <div class="carousel-dots">
                    <?php foreach ($products as $index => $product): ?>
                        <button type="button" class="dot <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>"></button>
                    <?php endforeach; ?>
                </div>

                <!-- Product Grid (thumbnails) -->
                <div class="product-thumbnails">
                    <h3><?php _e('All Products', 'tapp-campaigns'); ?></h3>
                    <div class="thumbnail-grid">
                        <?php foreach ($products as $product): ?>
                            <div class="product-thumbnail" data-product-id="<?php echo $product->get_id(); ?>">
                                <?php echo $product->get_image('thumbnail'); ?>
                                <span><?php echo $product->get_name(); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions-modern">
                    <button type="submit" class="button-modern button-large">
                        <?php echo $has_submitted ? __('Update Selections', 'tapp-campaigns') : __('Submit Selections', 'tapp-campaigns'); ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>
