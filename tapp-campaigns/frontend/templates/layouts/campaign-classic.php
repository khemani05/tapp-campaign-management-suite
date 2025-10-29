<?php
/**
 * Template: Classic Layout
 * Traditional grid view with detailed product cards
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

<div class="tapp-campaign-page tapp-template-classic">
    <div class="container">

        <!-- Campaign Header -->
        <div class="tapp-campaign-header">
            <h1><?php echo esc_html($campaign->name); ?></h1>

            <div class="tapp-campaign-meta">
                <span class="status-badge status-<?php echo esc_attr($campaign->status); ?>">
                    <?php echo esc_html(ucfirst($campaign->status)); ?>
                </span>

                <?php if (!$is_ended): ?>
                    <div class="tapp-countdown" data-end-time="<?php echo $end_time; ?>">
                        <?php if (!$is_started): ?>
                            <span class="countdown-label"><?php _e('Starts in:', 'tapp-campaigns'); ?></span>
                        <?php else: ?>
                            <span class="countdown-label"><?php _e('Ends in:', 'tapp-campaigns'); ?></span>
                        <?php endif; ?>
                        <span class="countdown-timer"></span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($campaign->notes): ?>
                <div class="tapp-campaign-notes">
                    <p><?php echo esc_html($campaign->notes); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($campaign->description): ?>
                <div class="tapp-campaign-description">
                    <?php echo wp_kses_post($campaign->description); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($has_submitted && !$can_edit): ?>
            <!-- Read-only view -->
            <div class="tapp-submission-complete">
                <div class="success-message">
                    <h2><?php _e('✓ Your Response Has Been Submitted', 'tapp-campaigns'); ?></h2>
                    <p><?php _e('Thank you for participating in this campaign.', 'tapp-campaigns'); ?></p>
                </div>

                <h3><?php _e('Your Selections:', 'tapp-campaigns'); ?></h3>
                <div class="tapp-selected-items">
                    <?php foreach ($user_responses as $response): ?>
                        <?php $product = wc_get_product($response->product_id); ?>
                        <?php if ($product): ?>
                            <div class="selected-item">
                                <img src="<?php echo wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
                                <div class="item-details">
                                    <h4><?php echo esc_html($product->get_name()); ?></h4>
                                    <?php if ($response->color): ?>
                                        <p><strong><?php _e('Color:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($response->color); ?></p>
                                    <?php endif; ?>
                                    <?php if ($response->size): ?>
                                        <p><strong><?php _e('Size:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($response->size); ?></p>
                                    <?php endif; ?>
                                    <p><strong><?php _e('Quantity:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($response->quantity); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif ($is_ended): ?>
            <!-- Campaign ended -->
            <div class="tapp-campaign-ended">
                <h2><?php _e('This Campaign Has Ended', 'tapp-campaigns'); ?></h2>
                <p><?php _e('Thank you for your participation.', 'tapp-campaigns'); ?></p>
            </div>

        <?php elseif (!$is_started): ?>
            <!-- Campaign not started yet -->
            <div class="tapp-campaign-pending">
                <h2><?php _e('Campaign Starting Soon', 'tapp-campaigns'); ?></h2>
                <p><?php printf(__('This campaign will start on %s', 'tapp-campaigns'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $start_time)); ?></p>
            </div>

        <?php else: ?>
            <!-- Product Selection Form -->
            <form id="tapp-campaign-form" method="post" action="">
                <?php wp_nonce_field('tapp_campaign_submit', 'tapp_nonce'); ?>
                <input type="hidden" name="campaign_id" value="<?php echo esc_attr($campaign->id); ?>">

                <div class="tapp-selection-info">
                    <p class="selection-limit">
                        <?php printf(__('Select up to %d item(s)', 'tapp-campaigns'), $campaign->selection_limit); ?>
                    </p>
                    <p class="selection-counter">
                        <?php _e('Selected:', 'tapp-campaigns'); ?> <span class="count">0</span> / <?php echo $campaign->selection_limit; ?>
                    </p>
                </div>

                <div class="tapp-products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="tapp-product-card" data-product-id="<?php echo $product->get_id(); ?>">
                            <div class="product-image">
                                <?php echo $product->get_image('medium'); ?>
                            </div>

                            <div class="product-info">
                                <h3 class="product-name"><?php echo $product->get_name(); ?></h3>
                                <p class="product-sku"><?php echo __('SKU:', 'tapp-campaigns') . ' ' . $product->get_sku(); ?></p>

                                <?php if ($campaign->payment_enabled): ?>
                                    <p class="product-price"><?php echo $product->get_price_html(); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="product-options">
                                <?php
                                $colors = TAPP_Campaigns_Campaign_Page::get_available_colors($product->get_id(), $campaign);
                                $sizes = TAPP_Campaigns_Campaign_Page::get_available_sizes($product->get_id(), $campaign);
                                ?>

                                <?php if (!empty($colors)): ?>
                                    <div class="option-group">
                                        <label><?php _e('Color:', 'tapp-campaigns'); ?></label>
                                        <select class="product-color" name="color[<?php echo $product->get_id(); ?>]">
                                            <option value=""><?php _e('Select Color', 'tapp-campaigns'); ?></option>
                                            <?php foreach ($colors as $color): ?>
                                                <option value="<?php echo esc_attr($color['slug']); ?>">
                                                    <?php echo esc_html($color['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($sizes)): ?>
                                    <div class="option-group">
                                        <label><?php _e('Size:', 'tapp-campaigns'); ?></label>
                                        <select class="product-size" name="size[<?php echo $product->get_id(); ?>]">
                                            <option value=""><?php _e('Select Size', 'tapp-campaigns'); ?></option>
                                            <?php foreach ($sizes as $size): ?>
                                                <option value="<?php echo esc_attr($size['slug']); ?>">
                                                    <?php echo esc_html($size['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <?php if ($campaign->ask_quantity): ?>
                                    <div class="option-group">
                                        <label><?php _e('Quantity:', 'tapp-campaigns'); ?></label>
                                        <input type="number"
                                               class="product-quantity"
                                               name="quantity[<?php echo $product->get_id(); ?>]"
                                               min="<?php echo $campaign->min_quantity; ?>"
                                               max="<?php echo $campaign->max_quantity; ?>"
                                               value="<?php echo $campaign->min_quantity; ?>">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="product-action">
                                <label class="checkbox-label">
                                    <input type="checkbox"
                                           class="product-checkbox"
                                           name="products[]"
                                           value="<?php echo $product->get_id(); ?>">
                                    <span class="checkbox-text"><?php _e('Select This Product', 'tapp-campaigns'); ?></span>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="tapp-form-actions">
                    <button type="submit" class="button button-primary button-large" id="submit-campaign">
                        <?php echo $has_submitted ? __('Update Selections', 'tapp-campaigns') : __('Submit Selections', 'tapp-campaigns'); ?>
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>
