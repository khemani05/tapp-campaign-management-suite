<?php
/**
 * Template: Campaign Page Wrapper
 * Loads the appropriate campaign layout template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if in preview mode
$preview_mode = defined('TAPP_CAMPAIGN_PREVIEW_MODE') && TAPP_CAMPAIGN_PREVIEW_MODE;

if (!$preview_mode) {
    get_header();
}

// Show preview banner if in preview mode
if ($preview_mode) {
    ?>
    <div class="tapp-preview-banner">
        <div class="tapp-preview-banner-content">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 10px;">
                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
            </svg>
            <strong><?php _e('PREVIEW MODE', 'tapp-campaigns'); ?></strong>
            <span style="margin-left: 10px;"><?php _e('This is how participants will see your campaign. Submissions are disabled in preview mode.', 'tapp-campaigns'); ?></span>
            <a href="<?php echo home_url('/campaign-manager/'); ?>" class="tapp-preview-back-button"><?php _e('Back to Dashboard', 'tapp-campaigns'); ?></a>
        </div>
    </div>
    <style>
    .tapp-preview-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 15px 20px;
        position: sticky;
        top: 0;
        z-index: 9999;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .tapp-preview-banner-content {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .tapp-preview-back-button {
        margin-left: auto;
        background: rgba(255,255,255,0.2);
        color: #fff;
        padding: 8px 16px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 600;
        transition: background 0.3s;
    }
    .tapp-preview-back-button:hover {
        background: rgba(255,255,255,0.3);
        color: #fff;
    }
    </style>
    <?php
}

// Prepare data for template
$user_id = get_current_user_id();
$products = TAPP_Campaigns_Campaign::get_wc_products($campaign->id);
$has_submitted = $preview_mode ? false : TAPP_Campaigns_Response::has_submitted($campaign->id, $user_id);
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
    'preview_mode' => $preview_mode,
]);

if (!$preview_mode) {
    get_footer();
}
