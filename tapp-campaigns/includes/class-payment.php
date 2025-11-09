<?php
/**
 * Payment Handler Class
 * Handles WooCommerce cart integration and checkout flow for payment-enabled campaigns
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Payment {

    public function __construct() {
        // Hook into order completion
        add_action('woocommerce_order_status_completed', [$this, 'handle_order_completed']);
        add_action('woocommerce_payment_complete', [$this, 'handle_payment_complete']);

        // Prevent editing of campaign items in cart
        add_filter('woocommerce_cart_item_remove_link', [$this, 'prevent_campaign_item_removal'], 10, 2);
        add_filter('woocommerce_cart_item_quantity', [$this, 'prevent_campaign_item_quantity_change'], 10, 3);

        // Add campaign info to order
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_campaign_metadata_to_order_item'], 10, 4);
    }

    /**
     * Add selections to WooCommerce cart for payment-enabled campaigns
     *
     * @param int $campaign_id Campaign ID
     * @param int $user_id User ID
     * @param array $selections Array of product selections
     * @return array|WP_Error Cart data or error
     */
    public static function add_to_cart($campaign_id, $user_id, $selections) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

        if (!$campaign || !$campaign->payment_enabled) {
            return new WP_Error('invalid_campaign', __('Campaign is not payment-enabled', 'tapp-campaigns'));
        }

        // Clear existing campaign items from cart (in case of re-submission)
        self::clear_campaign_items_from_cart($campaign_id);

        $cart_items = [];
        $total_price = 0;

        foreach ($selections as $selection) {
            $product_id = $selection['product_id'];
            $variation_id = isset($selection['variation_id']) ? $selection['variation_id'] : 0;
            $quantity = isset($selection['quantity']) ? intval($selection['quantity']) : 1;

            // Get product
            $product = wc_get_product($variation_id ? $variation_id : $product_id);

            if (!$product || !$product->is_purchasable()) {
                continue;
            }

            // Prepare cart item data with campaign metadata
            $cart_item_data = [
                'tapp_campaign_id' => $campaign_id,
                'tapp_user_id' => $user_id,
                'tapp_is_campaign_item' => true,
            ];

            // Add color/size if present
            if (!empty($selection['color'])) {
                $cart_item_data['tapp_color'] = sanitize_text_field($selection['color']);
            }

            if (!empty($selection['size'])) {
                $cart_item_data['tapp_size'] = sanitize_text_field($selection['size']);
            }

            // Add to cart
            $cart_item_key = WC()->cart->add_to_cart(
                $product_id,
                $quantity,
                $variation_id,
                [],
                $cart_item_data
            );

            if ($cart_item_key) {
                $cart_items[] = [
                    'cart_item_key' => $cart_item_key,
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'quantity' => $quantity,
                    'price' => $product->get_price(),
                ];

                $total_price += floatval($product->get_price()) * $quantity;
            }
        }

        if (empty($cart_items)) {
            return new WP_Error('cart_error', __('Failed to add items to cart', 'tapp-campaigns'));
        }

        return [
            'cart_items' => $cart_items,
            'total_price' => $total_price,
            'checkout_url' => wc_get_checkout_url(),
        ];
    }

    /**
     * Clear campaign items from cart for a specific campaign
     *
     * @param int $campaign_id Campaign ID
     */
    private static function clear_campaign_items_from_cart($campaign_id) {
        if (!WC()->cart) {
            return;
        }

        $cart = WC()->cart->get_cart();

        foreach ($cart as $cart_item_key => $cart_item) {
            if (isset($cart_item['tapp_campaign_id']) && $cart_item['tapp_campaign_id'] == $campaign_id) {
                WC()->cart->remove_cart_item($cart_item_key);
            }
        }
    }

    /**
     * Prevent removal of campaign items from cart
     *
     * @param string $link Remove link HTML
     * @param string $cart_item_key Cart item key
     * @return string Modified or empty link
     */
    public function prevent_campaign_item_removal($link, $cart_item_key) {
        $cart_item = WC()->cart->get_cart_item($cart_item_key);

        if (isset($cart_item['tapp_is_campaign_item']) && $cart_item['tapp_is_campaign_item']) {
            return ''; // Remove the link
        }

        return $link;
    }

    /**
     * Prevent quantity change for campaign items
     *
     * @param string $product_quantity Quantity HTML
     * @param string $cart_item_key Cart item key
     * @param array $cart_item Cart item data
     * @return string Modified quantity HTML or original
     */
    public function prevent_campaign_item_quantity_change($product_quantity, $cart_item_key, $cart_item) {
        if (isset($cart_item['tapp_is_campaign_item']) && $cart_item['tapp_is_campaign_item']) {
            return sprintf('<span class="quantity">%s</span>', $cart_item['quantity']);
        }

        return $product_quantity;
    }

    /**
     * Add campaign metadata to order line items
     *
     * @param WC_Order_Item_Product $item Order item
     * @param string $cart_item_key Cart item key
     * @param array $values Cart item values
     * @param WC_Order $order Order object
     */
    public function add_campaign_metadata_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values['tapp_campaign_id'])) {
            $item->add_meta_data('_tapp_campaign_id', $values['tapp_campaign_id'], true);

            // Add campaign name for clarity
            $campaign = TAPP_Campaigns_Campaign::get($values['tapp_campaign_id']);
            if ($campaign) {
                $item->add_meta_data('Campaign', $campaign->name, false);
            }
        }

        if (isset($values['tapp_color'])) {
            $item->add_meta_data('Color', $values['tapp_color'], false);
        }

        if (isset($values['tapp_size'])) {
            $item->add_meta_data('Size', $values['tapp_size'], false);
        }
    }

    /**
     * Handle order completion
     *
     * @param int $order_id Order ID
     */
    public function handle_order_completed($order_id) {
        $this->process_campaign_order($order_id);
    }

    /**
     * Handle payment completion
     *
     * @param int $order_id Order ID
     */
    public function handle_payment_complete($order_id) {
        $this->process_campaign_order($order_id);
    }

    /**
     * Process campaign order after payment
     *
     * @param int $order_id Order ID
     */
    private function process_campaign_order($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Check if already processed
        if ($order->get_meta('_tapp_campaign_processed')) {
            return;
        }

        $campaign_ids = [];

        // Get all campaign IDs from order items
        foreach ($order->get_items() as $item) {
            $campaign_id = $item->get_meta('_tapp_campaign_id');
            if ($campaign_id) {
                $campaign_ids[$campaign_id] = true;
            }
        }

        // Generate invoices if needed
        foreach (array_keys($campaign_ids) as $campaign_id) {
            $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

            if ($campaign && $campaign->generate_invoice) {
                $this->generate_invoice($campaign_id, $order_id);
            }
        }

        // Mark as processed
        $order->update_meta_data('_tapp_campaign_processed', true);
        $order->save();
    }

    /**
     * Generate invoice for campaign order
     *
     * @param int $campaign_id Campaign ID
     * @param int $order_id Order ID
     * @return bool Success
     */
    private function generate_invoice($campaign_id, $order_id) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
        $order = wc_get_order($order_id);

        if (!$campaign || !$order) {
            return false;
        }

        // Get invoice recipients
        $recipients = $campaign->invoice_recipients ? explode(',', $campaign->invoice_recipients) : [];

        if (empty($recipients)) {
            return false;
        }

        // Prepare invoice data
        $invoice_data = [
            'campaign_name' => $campaign->name,
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'customer_name' => $order->get_formatted_billing_full_name(),
            'customer_email' => $order->get_billing_email(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'items' => [],
        ];

        // Get campaign-related items
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_tapp_campaign_id') == $campaign_id) {
                $invoice_data['items'][] = [
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                ];
            }
        }

        // Send invoice email
        $this->send_invoice_email($recipients, $invoice_data);

        return true;
    }

    /**
     * Send invoice email to recipients
     *
     * @param array $recipients Email addresses
     * @param array $invoice_data Invoice data
     * @return bool Success
     */
    private function send_invoice_email($recipients, $invoice_data) {
        $subject = sprintf(
            __('[%s] Campaign Invoice - Order #%s', 'tapp-campaigns'),
            get_bloginfo('name'),
            $invoice_data['order_number']
        );

        $message = $this->get_invoice_email_template($invoice_data);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (is_email($recipient)) {
                wp_mail($recipient, $subject, $message, $headers);
            }
        }

        return true;
    }

    /**
     * Get invoice email template
     *
     * @param array $data Invoice data
     * @return string HTML email content
     */
    private function get_invoice_email_template($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: #fff; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .invoice-details { background: #fff; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa; }
                .invoice-details p { margin: 5px 0; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; background: #fff; }
                .items-table th { background: #f5f5f5; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; }
                .items-table td { padding: 10px; border-bottom: 1px solid #eee; }
                .total-row { font-weight: bold; background: #f9f9f9; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html__('Campaign Invoice', 'tapp-campaigns'); ?></h1>
                </div>

                <div class="content">
                    <div class="invoice-details">
                        <h2><?php echo esc_html__('Order Details', 'tapp-campaigns'); ?></h2>
                        <p><strong><?php echo esc_html__('Campaign:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['campaign_name']); ?></p>
                        <p><strong><?php echo esc_html__('Order Number:', 'tapp-campaigns'); ?></strong> #<?php echo esc_html($data['order_number']); ?></p>
                        <p><strong><?php echo esc_html__('Date:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['date']); ?></p>
                        <p><strong><?php echo esc_html__('Customer:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['customer_name']); ?></p>
                        <p><strong><?php echo esc_html__('Email:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($data['customer_email']); ?></p>
                    </div>

                    <table class="items-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Item', 'tapp-campaigns'); ?></th>
                                <th><?php echo esc_html__('Quantity', 'tapp-campaigns'); ?></th>
                                <th><?php echo esc_html__('Total', 'tapp-campaigns'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['items'] as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['name']); ?></td>
                                    <td><?php echo esc_html($item['quantity']); ?></td>
                                    <td><?php echo wc_price($item['total'], ['currency' => $data['currency']]); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="2"><?php echo esc_html__('Total', 'tapp-campaigns'); ?></td>
                                <td><?php echo wc_price($data['total'], ['currency' => $data['currency']]); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <p><?php echo esc_html__('This invoice has been automatically generated for your campaign order.', 'tapp-campaigns'); ?></p>
                </div>

                <div class="footer">
                    <p>&copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(get_bloginfo('name')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Check if cart has campaign items
     *
     * @return bool
     */
    public static function cart_has_campaign_items() {
        if (!WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['tapp_is_campaign_item']) && $cart_item['tapp_is_campaign_item']) {
                return true;
            }
        }

        return false;
    }
}
