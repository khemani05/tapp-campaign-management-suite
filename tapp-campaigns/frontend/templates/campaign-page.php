<?php
/**
 * Template: Campaign Page Wrapper
 * Loads the appropriate campaign layout template
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Prepare data for template
$user_id = get_current_user_id();
$products = TAPP_Campaigns_Campaign::get_wc_products($campaign->id);
$has_submitted = TAPP_Campaigns_Response::has_submitted($campaign->id, $user_id);
$user_responses = $has_submitted ? TAPP_Campaigns_Response::get_latest($campaign->id, $user_id) : [];

// Get template layout (default to classic)
$template_layout = $campaign->template_layout ?? 'classic';

// Validate template exists
if (!TAPP_Campaigns_Templates::template_exists($template_layout)) {
    $template_layout = 'classic';
}

// Load the template layout
TAPP_Campaigns_Templates::load_layout($template_layout, [
    'campaign' => $campaign,
    'products' => $products,
    'has_submitted' => $has_submitted,
    'user_responses' => $user_responses,
]);

get_footer();
