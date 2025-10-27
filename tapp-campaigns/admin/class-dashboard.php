<?php
/**
 * Dashboard Class
 * Handles dashboard actions
 */

if (!defined('ABSPATH')) {
    exit;
}

class TAPP_Campaigns_Dashboard {

    public function handle_action($action) {
        switch ($action) {
            case 'export':
                $this->handle_export_action();
                break;
        }
    }

    private function handle_export_action() {
        if (!isset($_GET['id'])) {
            return;
        }

        $campaign_id = intval($_GET['id']);
        $export_url = add_query_arg([
            'tapp_export' => 'responses',
            'campaign_id' => $campaign_id,
        ], admin_url('admin.php'));

        wp_redirect($export_url);
        exit;
    }
}
