<?php
/**
 * Purchase Order Generator
 * Generates purchase orders for campaign responses as HTML/PDF
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Purchase_Order {

    /**
     * Generate purchase order HTML for a campaign
     *
     * @param int $campaign_id Campaign ID
     * @return string|WP_Error HTML file path or error
     */
    public static function generate($campaign_id) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

        if (!$campaign) {
            return new WP_Error('invalid_campaign', __('Campaign not found', 'tapp-campaigns'));
        }

        // Get all responses
        $responses = self::get_campaign_responses($campaign_id);

        if (empty($responses)) {
            return new WP_Error('no_responses', __('No responses found for this campaign', 'tapp-campaigns'));
        }

        // Generate HTML content
        $html = self::get_pdf_html($campaign, $responses);

        // Save HTML file
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/tapp-campaigns/purchase-orders';

        // Create directory if it doesn't exist
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        $filename = 'po-' . $campaign->id . '-' . date('Y-m-d-His') . '.html';
        $filepath = $pdf_dir . '/' . $filename;

        // Save to file
        file_put_contents($filepath, $html);

        return $filepath;
    }

    /**
     * Get all campaign responses with product details
     *
     * @param int $campaign_id Campaign ID
     * @return array Responses data
     */
    private static function get_campaign_responses($campaign_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'tapp_campaign_responses';

        $query = $wpdb->prepare("
            SELECT
                r.*,
                u.display_name,
                u.user_email
            FROM {$table} r
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE r.campaign_id = %d
            ORDER BY r.user_id, r.product_id
        ", $campaign_id);

        $responses = $wpdb->get_results($query);

        // Get product details for each response
        foreach ($responses as $response) {
            $product = wc_get_product($response->product_id);
            if ($product) {
                $response->product_name = $product->get_name();
                $response->product_sku = $product->get_sku();
                $response->product_price = $product->get_price();
            }
        }

        return $responses;
    }

    /**
     * Generate HTML content for PDF
     *
     * @param object $campaign Campaign object
     * @param array $responses Responses data
     * @return string HTML content
     */
    private static function get_pdf_html($campaign, $responses) {
        // Group responses by user
        $grouped = [];
        foreach ($responses as $response) {
            if (!isset($grouped[$response->user_id])) {
                $grouped[$response->user_id] = [
                    'user' => [
                        'name' => $response->display_name,
                        'email' => $response->user_email,
                    ],
                    'items' => [],
                ];
            }
            $grouped[$response->user_id]['items'][] = $response;
        }

        // Calculate totals
        $total_items = count($responses);
        $total_quantity = array_sum(array_column($responses, 'quantity'));
        $total_value = 0;
        foreach ($responses as $response) {
            if (isset($response->product_price)) {
                $total_value += floatval($response->product_price) * intval($response->quantity);
            }
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Purchase Order - <?php echo esc_html($campaign->name); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.6; padding: 30px; }
                h1 { color: #0073aa; font-size: 24px; margin-bottom: 10px; }
                h2 { color: #333; font-size: 18px; margin-top: 20px; margin-bottom: 10px; }
                h3 { color: #555; font-size: 14px; margin-top: 15px; margin-bottom: 8px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                th { background-color: #f5f5f5; padding: 8px; text-align: left; font-weight: bold; border-bottom: 2px solid #ddd; }
                td { padding: 8px; border-bottom: 1px solid #eee; }
                .header-info { margin-bottom: 20px; }
                .header-info p { margin: 5px 0; font-size: 11px; color: #666; }
                .section { margin: 25px 0; }
                .summary-box { background-color: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #0073aa; }
                .summary-box p { margin: 5px 0; }
                .total-row { font-weight: bold; background-color: #f5f5f5; }
                .user-section { page-break-inside: avoid; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; font-size: 9px; color: #999; }
                .print-button {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 10px 20px;
                    background: #0073aa;
                    color: #fff;
                    border: none;
                    cursor: pointer;
                    border-radius: 4px;
                    font-size: 14px;
                }
                @media print {
                    body { padding: 0; }
                    .print-button { display: none; }
                    .page-break { page-break-before: always; }
                }
            </style>
        </head>
        <body>
            <button class="print-button" onclick="window.print()">Print / Save as PDF</button>

        <!-- Header -->
        <h1><?php echo esc_html__('Purchase Order', 'tapp-campaigns'); ?></h1>

        <div class="header-info">
            <p><strong><?php echo esc_html__('Generated:', 'tapp-campaigns'); ?></strong> <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format')); ?></p>
            <p><strong><?php echo esc_html__('Organization:', 'tapp-campaigns'); ?></strong> <?php echo esc_html(get_bloginfo('name')); ?></p>
        </div>

        <!-- Campaign Information -->
        <div class="summary-box">
            <h2><?php echo esc_html__('Campaign Details', 'tapp-campaigns'); ?></h2>
            <p><strong><?php echo esc_html__('Name:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($campaign->name); ?></p>
            <p><strong><?php echo esc_html__('Type:', 'tapp-campaigns'); ?></strong> <?php echo esc_html(ucfirst($campaign->campaign_type)); ?></p>
            <p><strong><?php echo esc_html__('Duration:', 'tapp-campaigns'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($campaign->start_date)); ?> - <?php echo date_i18n(get_option('date_format'), strtotime($campaign->end_date)); ?></p>
            <?php if ($campaign->notes): ?>
                <p><strong><?php echo esc_html__('Notes:', 'tapp-campaigns'); ?></strong> <?php echo esc_html($campaign->notes); ?></p>
            <?php endif; ?>
        </div>

        <!-- Summary Statistics -->
        <div class="summary-box">
            <h2><?php echo esc_html__('Order Summary', 'tapp-campaigns'); ?></h2>
            <p><strong><?php echo esc_html__('Total Participants:', 'tapp-campaigns'); ?></strong> <?php echo count($grouped); ?></p>
            <p><strong><?php echo esc_html__('Total Items:', 'tapp-campaigns'); ?></strong> <?php echo $total_items; ?></p>
            <p><strong><?php echo esc_html__('Total Quantity:', 'tapp-campaigns'); ?></strong> <?php echo $total_quantity; ?></p>
            <?php if ($total_value > 0): ?>
                <p><strong><?php echo esc_html__('Estimated Total Value:', 'tapp-campaigns'); ?></strong> <?php echo wc_price($total_value); ?></p>
            <?php endif; ?>
        </div>

        <!-- Product Summary Table -->
        <h2><?php echo esc_html__('Product Summary', 'tapp-campaigns'); ?></h2>
        <table>
            <thead>
                <tr>
                    <th><?php echo esc_html__('Product', 'tapp-campaigns'); ?></th>
                    <th><?php echo esc_html__('SKU', 'tapp-campaigns'); ?></th>
                    <th style="text-align: center;"><?php echo esc_html__('Qty', 'tapp-campaigns'); ?></th>
                    <?php if ($total_value > 0): ?>
                        <th style="text-align: right;"><?php echo esc_html__('Unit Price', 'tapp-campaigns'); ?></th>
                        <th style="text-align: right;"><?php echo esc_html__('Total', 'tapp-campaigns'); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // Aggregate by product
                $product_summary = [];
                foreach ($responses as $response) {
                    $key = $response->product_id;
                    if (!isset($product_summary[$key])) {
                        $product_summary[$key] = [
                            'name' => $response->product_name ?? 'Unknown Product',
                            'sku' => $response->product_sku ?? 'N/A',
                            'quantity' => 0,
                            'price' => $response->product_price ?? 0,
                            'total' => 0,
                        ];
                    }
                    $product_summary[$key]['quantity'] += intval($response->quantity);
                    $product_summary[$key]['total'] += floatval($response->product_price ?? 0) * intval($response->quantity);
                }

                foreach ($product_summary as $product):
                ?>
                    <tr>
                        <td><?php echo esc_html($product['name']); ?></td>
                        <td><?php echo esc_html($product['sku']); ?></td>
                        <td style="text-align: center;"><?php echo $product['quantity']; ?></td>
                        <?php if ($total_value > 0): ?>
                            <td style="text-align: right;"><?php echo wc_price($product['price']); ?></td>
                            <td style="text-align: right;"><?php echo wc_price($product['total']); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if ($total_value > 0): ?>
                    <tr class="total-row">
                        <td colspan="<?php echo $total_value > 0 ? 4 : 2; ?>" style="text-align: right;">
                            <strong><?php echo esc_html__('Total:', 'tapp-campaigns'); ?></strong>
                        </td>
                        <td style="text-align: right;"><strong><?php echo wc_price($total_value); ?></strong></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Detailed Breakdown by Participant -->
        <div style="page-break-before: always;"></div>
        <h2><?php echo esc_html__('Detailed Breakdown by Participant', 'tapp-campaigns'); ?></h2>

        <?php foreach ($grouped as $user_id => $user_data): ?>
            <div class="user-section">
                <h3><?php echo esc_html($user_data['user']['name']); ?> (<?php echo esc_html($user_data['user']['email']); ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Product', 'tapp-campaigns'); ?></th>
                            <?php if ($campaign->ask_color): ?>
                                <th><?php echo esc_html__('Color', 'tapp-campaigns'); ?></th>
                            <?php endif; ?>
                            <?php if ($campaign->ask_size): ?>
                                <th><?php echo esc_html__('Size', 'tapp-campaigns'); ?></th>
                            <?php endif; ?>
                            <th style="text-align: center;"><?php echo esc_html__('Qty', 'tapp-campaigns'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_data['items'] as $item): ?>
                            <tr>
                                <td><?php echo esc_html($item->product_name ?? 'Unknown Product'); ?></td>
                                <?php if ($campaign->ask_color): ?>
                                    <td><?php echo esc_html($item->color ?: '-'); ?></td>
                                <?php endif; ?>
                                <?php if ($campaign->ask_size): ?>
                                    <td><?php echo esc_html($item->size ?: '-'); ?></td>
                                <?php endif; ?>
                                <td style="text-align: center;"><?php echo intval($item->quantity); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <div class="footer">
            <p><?php echo sprintf(__('Generated by %s on %s', 'tapp-campaigns'), get_bloginfo('name'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'))); ?></p>
        </div>

        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send purchase order PDF via email
     *
     * @param string $filepath PDF file path
     * @param int $campaign_id Campaign ID
     * @param array $recipients Email addresses
     * @return bool Success
     */
    public static function send_email($filepath, $campaign_id, $recipients = []) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

        if (!$campaign || !file_exists($filepath)) {
            return false;
        }

        // Use default recipients if not provided
        if (empty($recipients)) {
            $recipients = $campaign->invoice_recipients ? explode(',', $campaign->invoice_recipients) : [];
        }

        if (empty($recipients)) {
            return false;
        }

        $subject = sprintf(
            __('[%s] Purchase Order - %s', 'tapp-campaigns'),
            get_bloginfo('name'),
            $campaign->name
        );

        $message = sprintf(
            __('Please find attached the purchase order for the campaign "%s".<br><br>Generated on: %s', 'tapp-campaigns'),
            $campaign->name,
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'))
        );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $attachments = [$filepath];

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (is_email($recipient)) {
                wp_mail($recipient, $subject, $message, $headers, $attachments);
            }
        }

        return true;
    }

    /**
     * Auto-generate purchase order when campaign ends
     * Called from cron job
     *
     * @param int $campaign_id Campaign ID
     * @return bool Success
     */
    public static function auto_generate_on_end($campaign_id) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

        if (!$campaign || !$campaign->generate_invoice) {
            return false;
        }

        // Generate PDF
        $filepath = self::generate($campaign_id);

        if (is_wp_error($filepath)) {
            error_log('TAPP Campaigns: Failed to generate PO for campaign ' . $campaign_id . ': ' . $filepath->get_error_message());
            return false;
        }

        // Send email
        $sent = self::send_email($filepath, $campaign_id);

        if ($sent) {
            // Update campaign meta to track PO generation
            update_post_meta($campaign_id, '_tapp_po_generated', time());
            update_post_meta($campaign_id, '_tapp_po_filepath', $filepath);
        }

        return $sent;
    }
}
