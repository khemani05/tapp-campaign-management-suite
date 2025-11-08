<?php
/**
 * Google Sheets Export Class
 * Exports campaign data to Google Sheets using Google Sheets API v4
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Google_Sheets {

    /**
     * Export campaign responses to Google Sheets
     *
     * @param int $campaign_id Campaign ID
     * @param string $spreadsheet_id Google Sheets spreadsheet ID
     * @param string $sheet_name Sheet name (defaults to campaign name)
     * @return array|WP_Error Result or error
     */
    public static function export_to_sheet($campaign_id, $spreadsheet_id, $sheet_name = null) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

        if (!$campaign) {
            return new WP_Error('invalid_campaign', __('Campaign not found', 'tapp-campaigns'));
        }

        // Get Google API credentials
        $api_key = get_option('tapp_google_sheets_api_key');
        $access_token = get_option('tapp_google_sheets_access_token');

        if (!$access_token && !$api_key) {
            return new WP_Error('no_credentials', __('Google Sheets API credentials not configured', 'tapp-campaigns'));
        }

        // Use campaign name as sheet name if not provided
        if (!$sheet_name) {
            $sheet_name = sanitize_title($campaign->name);
        }

        // Get campaign data
        $data = self::get_campaign_export_data($campaign_id);

        if (empty($data)) {
            return new WP_Error('no_data', __('No data to export', 'tapp-campaigns'));
        }

        // Export using Google Sheets API
        $result = self::write_to_google_sheets($spreadsheet_id, $sheet_name, $data, $access_token);

        if (is_wp_error($result)) {
            return $result;
        }

        // Log activity
        TAPP_Campaigns_Activity_Log::log(
            'google_sheets_exported',
            'campaign',
            'Data exported to Google Sheets',
            $campaign_id,
            get_current_user_id(),
            ['spreadsheet_id' => $spreadsheet_id, 'sheet_name' => $sheet_name]
        );

        return $result;
    }

    /**
     * Get campaign data formatted for export
     *
     * @param int $campaign_id Campaign ID
     * @return array Data rows
     */
    private static function get_campaign_export_data($campaign_id) {
        global $wpdb;

        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);
        $table = $wpdb->prefix . 'tapp_campaign_responses';

        $query = $wpdb->prepare("
            SELECT
                r.*,
                u.display_name,
                u.user_email
            FROM {$table} r
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE r.campaign_id = %d
            ORDER BY u.display_name, r.product_id
        ", $campaign_id);

        $responses = $wpdb->get_results($query);

        // Build data array
        $data = [];

        // Header row
        $headers = ['Name', 'Email', 'Product ID', 'Product Name'];

        if ($campaign->ask_color) {
            $headers[] = 'Color';
        }

        if ($campaign->ask_size) {
            $headers[] = 'Size';
        }

        $headers[] = 'Quantity';
        $headers[] = 'Submitted At';

        $data[] = $headers;

        // Data rows
        foreach ($responses as $response) {
            $product = wc_get_product($response->product_id);
            $product_name = $product ? $product->get_name() : 'Unknown Product';

            $row = [
                $response->display_name,
                $response->user_email,
                $response->product_id,
                $product_name,
            ];

            if ($campaign->ask_color) {
                $row[] = $response->color ?: '';
            }

            if ($campaign->ask_size) {
                $row[] = $response->size ?: '';
            }

            $row[] = $response->quantity;
            $row[] = $response->submitted_at;

            $data[] = $row;
        }

        return $data;
    }

    /**
     * Write data to Google Sheets using API
     *
     * @param string $spreadsheet_id Spreadsheet ID
     * @param string $sheet_name Sheet name
     * @param array $data Data to write
     * @param string $access_token Access token
     * @return array|WP_Error Result or error
     */
    private static function write_to_google_sheets($spreadsheet_id, $sheet_name, $data, $access_token) {
        $api_url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}/values/{$sheet_name}!A1:append";

        $body = [
            'values' => $data,
            'majorDimension' => 'ROWS',
        ];

        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ];

        $response = wp_remote_request($api_url . '?valueInputOption=RAW', $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error';
            return new WP_Error('api_error', sprintf(__('Google Sheets API error: %s', 'tapp-campaigns'), $error_message));
        }

        return $result;
    }

    /**
     * Create a new Google Sheet for campaign
     *
     * @param int $campaign_id Campaign ID
     * @param string $access_token Access token
     * @return string|WP_Error Spreadsheet ID or error
     */
    public static function create_new_sheet($campaign_id, $access_token) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

        if (!$campaign) {
            return new WP_Error('invalid_campaign', __('Campaign not found', 'tapp-campaigns'));
        }

        $api_url = 'https://sheets.googleapis.com/v4/spreadsheets';

        $body = [
            'properties' => [
                'title' => $campaign->name . ' - ' . date('Y-m-d'),
            ],
            'sheets' => [
                [
                    'properties' => [
                        'title' => 'Responses',
                    ],
                ],
            ],
        ];

        $args = [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ];

        $response = wp_remote_request($api_url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error';
            return new WP_Error('api_error', sprintf(__('Google Sheets API error: %s', 'tapp-campaigns'), $error_message));
        }

        return $result['spreadsheetId'];
    }

    /**
     * Get OAuth2 authorization URL
     *
     * @return string Authorization URL
     */
    public static function get_oauth_url() {
        $client_id = get_option('tapp_google_sheets_client_id');
        $redirect_uri = admin_url('admin.php?page=tapp-campaigns-settings&tab=google-sheets&action=oauth_callback');

        $params = [
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     *
     * @param string $code Authorization code
     * @return array|WP_Error Token data or error
     */
    public static function exchange_code_for_token($code) {
        $client_id = get_option('tapp_google_sheets_client_id');
        $client_secret = get_option('tapp_google_sheets_client_secret');
        $redirect_uri = admin_url('admin.php?page=tapp-campaigns-settings&tab=google-sheets&action=oauth_callback');

        $api_url = 'https://oauth2.googleapis.com/token';

        $body = [
            'code' => $code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code',
        ];

        $args = [
            'method' => 'POST',
            'body' => $body,
            'timeout' => 30,
        ];

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if ($status_code !== 200) {
            $error_message = isset($result['error_description']) ? $result['error_description'] : 'Unknown error';
            return new WP_Error('oauth_error', sprintf(__('OAuth error: %s', 'tapp-campaigns'), $error_message));
        }

        // Save tokens
        update_option('tapp_google_sheets_access_token', $result['access_token']);
        if (isset($result['refresh_token'])) {
            update_option('tapp_google_sheets_refresh_token', $result['refresh_token']);
        }
        update_option('tapp_google_sheets_token_expires', time() + $result['expires_in']);

        return $result;
    }

    /**
     * Refresh access token using refresh token
     *
     * @return bool Success
     */
    public static function refresh_access_token() {
        $client_id = get_option('tapp_google_sheets_client_id');
        $client_secret = get_option('tapp_google_sheets_client_secret');
        $refresh_token = get_option('tapp_google_sheets_refresh_token');

        if (!$refresh_token) {
            return false;
        }

        $api_url = 'https://oauth2.googleapis.com/token';

        $body = [
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token',
        ];

        $args = [
            'method' => 'POST',
            'body' => $body,
            'timeout' => 30,
        ];

        $response = wp_remote_post($api_url, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if ($status_code !== 200 || !isset($result['access_token'])) {
            return false;
        }

        // Update access token
        update_option('tapp_google_sheets_access_token', $result['access_token']);
        update_option('tapp_google_sheets_token_expires', time() + $result['expires_in']);

        return true;
    }

    /**
     * Check if access token is expired and refresh if needed
     *
     * @return bool Success
     */
    public static function ensure_valid_token() {
        $expires = get_option('tapp_google_sheets_token_expires', 0);

        // If token expires in less than 5 minutes, refresh it
        if (time() + 300 > $expires) {
            return self::refresh_access_token();
        }

        return true;
    }

    /**
     * Check if Google Sheets integration is configured
     *
     * @return bool
     */
    public static function is_configured() {
        $access_token = get_option('tapp_google_sheets_access_token');
        $client_id = get_option('tapp_google_sheets_client_id');
        $client_secret = get_option('tapp_google_sheets_client_secret');

        return !empty($access_token) && !empty($client_id) && !empty($client_secret);
    }

    /**
     * Disconnect Google Sheets integration
     */
    public static function disconnect() {
        delete_option('tapp_google_sheets_access_token');
        delete_option('tapp_google_sheets_refresh_token');
        delete_option('tapp_google_sheets_token_expires');
    }

    /**
     * Get shareable link for spreadsheet
     *
     * @param string $spreadsheet_id Spreadsheet ID
     * @return string Shareable URL
     */
    public static function get_spreadsheet_url($spreadsheet_id) {
        return "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/edit";
    }

    /**
     * Auto-sync campaign responses to Google Sheets
     * Called from cron or on response submission
     *
     * @param int $campaign_id Campaign ID
     * @return bool Success
     */
    public static function auto_sync($campaign_id) {
        $campaign = TAPP_Campaigns_Campaign::get($campaign_id);

        if (!$campaign) {
            return false;
        }

        // Check if auto-sync is enabled for this campaign
        $auto_sync_enabled = get_post_meta($campaign_id, '_tapp_google_sheets_auto_sync', true);
        $spreadsheet_id = get_post_meta($campaign_id, '_tapp_google_sheets_spreadsheet_id', true);

        if (!$auto_sync_enabled || !$spreadsheet_id) {
            return false;
        }

        // Ensure token is valid
        if (!self::ensure_valid_token()) {
            return false;
        }

        // Export to sheet
        $result = self::export_to_sheet($campaign_id, $spreadsheet_id);

        return !is_wp_error($result);
    }
}
