<?php
/**
 * Template: Campaign Creation Form
 * MVP single-page form for creating campaigns
 */

if (!defined('ABSPATH')) {
    exit;
}

$campaign_type = isset(get_query_var('campaign_action')) && get_query_var('campaign_action') === 'create-sales' ? 'sales' : 'team';
$editing = get_query_var('campaign_id');
$campaign = $editing ? TAPP_Campaigns_Campaign::get(intval($_GET['id'])) : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_campaign'])) {
    check_admin_referer('tapp_create_campaign');

    $data = [
        'name' => sanitize_text_field($_POST['campaign_name']),
        'type' => sanitize_text_field($_POST['campaign_type']),
        'start_date' => sanitize_text_field($_POST['start_date']),
        'end_date' => sanitize_text_field($_POST['end_date']),
        'notes' => sanitize_textarea_field($_POST['notes']),
        'description' => wp_kses_post($_POST['description']),
        'selection_limit' => intval($_POST['selection_limit']),
        'department' => sanitize_text_field($_POST['department']),
        'template_layout' => sanitize_text_field($_POST['template_layout'] ?? 'classic'),
        'template_primary_color' => sanitize_hex_color($_POST['template_primary_color'] ?? '#0073aa'),
        'template_button_color' => sanitize_hex_color($_POST['template_button_color'] ?? '#0073aa'),
        'template_hero_image' => esc_url_raw($_POST['template_hero_image'] ?? ''),
        'status' => 'draft',
    ];

    if ($editing) {
        TAPP_Campaigns_Campaign::update($campaign->id, $data);
        $campaign_id = $campaign->id;
    } else {
        $campaign_id = TAPP_Campaigns_Campaign::create($data);
    }

    if ($campaign_id) {
        // Save products
        if (isset($_POST['products']) && is_array($_POST['products'])) {
            $product_ids = array_map('intval', $_POST['products']);
            TAPP_Campaigns_Campaign::set_products($campaign_id, $product_ids);
        }

        // Save participants
        if (!empty($_POST['participant_emails'])) {
            $emails = explode(',', $_POST['participant_emails']);
            foreach ($emails as $email) {
                $email = trim($email);
                $user = get_user_by('email', $email);
                if ($user) {
                    TAPP_Campaigns_Participant::add($campaign_id, $user->ID);

                    // Send invitation email
                    TAPP_Campaigns_Email::send_invitation($campaign_id, $user->ID);
                }
            }
        }

        // Redirect to dashboard
        wp_redirect(home_url('/campaign-manager/'));
        exit;
    }
}

?>

<div class="tapp-campaign-form">
    <h3><?php echo $editing ? __('Edit Campaign', 'tapp-campaigns') : sprintf(__('Create %s Campaign', 'tapp-campaigns'), ucfirst($campaign_type)); ?></h3>

    <form method="post" action="">
        <?php wp_nonce_field('tapp_create_campaign'); ?>
        <input type="hidden" name="create_campaign" value="1">
        <input type="hidden" name="campaign_type" value="<?php echo esc_attr($campaign_type); ?>">

        <table class="form-table">
            <tr>
                <th><label for="campaign_name"><?php _e('Campaign Name', 'tapp-campaigns'); ?> *</label></th>
                <td>
                    <input type="text" name="campaign_name" id="campaign_name" class="regular-text" required
                           value="<?php echo $editing ? esc_attr($campaign->name) : ''; ?>">
                </td>
            </tr>

            <tr>
                <th><label for="start_date"><?php _e('Start Date & Time', 'tapp-campaigns'); ?> *</label></th>
                <td>
                    <input type="datetime-local" name="start_date" id="start_date" required
                           value="<?php echo $editing ? date('Y-m-d\TH:i', strtotime($campaign->start_date)) : ''; ?>">
                </td>
            </tr>

            <tr>
                <th><label for="end_date"><?php _e('End Date & Time', 'tapp-campaigns'); ?> *</label></th>
                <td>
                    <input type="datetime-local" name="end_date" id="end_date" required
                           value="<?php echo $editing ? date('Y-m-d\TH:i', strtotime($campaign->end_date)) : ''; ?>">
                </td>
            </tr>

            <tr>
                <th><label for="selection_limit"><?php _e('Selection Limit', 'tapp-campaigns'); ?></label></th>
                <td>
                    <input type="number" name="selection_limit" id="selection_limit" min="1" value="<?php echo $editing ? $campaign->selection_limit : 1; ?>">
                    <p class="description"><?php _e('Maximum number of items each user can select', 'tapp-campaigns'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="department"><?php _e('Department', 'tapp-campaigns'); ?></label></th>
                <td>
                    <input type="text" name="department" id="department" class="regular-text"
                           value="<?php echo $editing ? esc_attr($campaign->department) : ''; ?>">
                </td>
            </tr>

            <tr>
                <th><label for="notes"><?php _e('Notes', 'tapp-campaigns'); ?></label></th>
                <td>
                    <textarea name="notes" id="notes" rows="3" class="large-text"><?php echo $editing ? esc_textarea($campaign->notes) : ''; ?></textarea>
                    <p class="description"><?php _e('Short message shown at the top of campaign page', 'tapp-campaigns'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="description"><?php _e('Description', 'tapp-campaigns'); ?></label></th>
                <td>
                    <textarea name="description" id="description" rows="6" class="large-text"><?php echo $editing ? esc_textarea($campaign->description) : ''; ?></textarea>
                </td>
            </tr>

            <tr>
                <th><label><?php _e('Page Template', 'tapp-campaigns'); ?></label></th>
                <td>
                    <?php
                    $selected_template = $editing ? ($campaign->template_layout ?? 'classic') : 'classic';
                    $templates = TAPP_Campaigns_Templates::get_all();
                    ?>
                    <div class="template-selector">
                        <?php foreach ($templates as $slug => $template): ?>
                            <label class="template-option">
                                <input type="radio" name="template_layout" value="<?php echo esc_attr($slug); ?>"
                                    <?php checked($selected_template, $slug); ?>>
                                <div class="template-preview">
                                    <strong><?php echo esc_html($template['name']); ?></strong>
                                    <p><?php echo esc_html($template['description']); ?></p>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="description"><?php _e('Choose a layout template for your campaign page', 'tapp-campaigns'); ?></p>
                </td>
            </tr>

            <tr class="template-color-settings">
                <th><label><?php _e('Template Colors', 'tapp-campaigns'); ?></label></th>
                <td>
                    <div style="display: flex; gap: 20px; margin-bottom: 10px;">
                        <div>
                            <label for="template_primary_color"><?php _e('Primary Color', 'tapp-campaigns'); ?></label><br>
                            <input type="color" name="template_primary_color" id="template_primary_color"
                                value="<?php echo $editing ? esc_attr($campaign->template_primary_color ?? '#0073aa') : '#0073aa'; ?>">
                        </div>
                        <div>
                            <label for="template_button_color"><?php _e('Button Color', 'tapp-campaigns'); ?></label><br>
                            <input type="color" name="template_button_color" id="template_button_color"
                                value="<?php echo $editing ? esc_attr($campaign->template_button_color ?? '#0073aa') : '#0073aa'; ?>">
                        </div>
                    </div>
                    <p class="description"><?php _e('Customize the colors for your campaign page', 'tapp-campaigns'); ?></p>
                </td>
            </tr>

            <tr class="template-hero-settings" style="<?php echo ($selected_template !== 'hero') ? 'display:none;' : ''; ?>">
                <th><label for="template_hero_image"><?php _e('Hero Image URL', 'tapp-campaigns'); ?></label></th>
                <td>
                    <input type="url" name="template_hero_image" id="template_hero_image" class="large-text"
                        value="<?php echo $editing ? esc_url($campaign->template_hero_image ?? '') : ''; ?>"
                        placeholder="https://example.com/image.jpg">
                    <p class="description"><?php _e('Enter the URL for the hero banner background image (for Hero template only)', 'tapp-campaigns'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label><?php _e('Products', 'tapp-campaigns'); ?> *</label></th>
                <td>
                    <div id="product-selector">
                        <input type="text" id="product-search" class="regular-text" placeholder="<?php _e('Search products...', 'tapp-campaigns'); ?>">
                        <div id="product-results"></div>
                        <div id="selected-products"></div>
                    </div>
                    <p class="description"><?php _e('Search and select products for this campaign', 'tapp-campaigns'); ?></p>
                </td>
            </tr>

            <tr>
                <th><label for="participant_emails"><?php _e('Participants', 'tapp-campaigns'); ?> *</label></th>
                <td>
                    <textarea name="participant_emails" id="participant_emails" rows="5" class="large-text"><?php
                        if ($editing) {
                            $participants = TAPP_Campaigns_Participant::get_all($campaign->id);
                            $emails = array_map(function($p) { return $p->email; }, $participants);
                            echo esc_textarea(implode(', ', $emails));
                        }
                    ?></textarea>
                    <p class="description"><?php _e('Enter email addresses separated by commas', 'tapp-campaigns'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary button-large">
                <?php echo $editing ? __('Update Campaign', 'tapp-campaigns') : __('Create Campaign', 'tapp-campaigns'); ?>
            </button>
            <a href="<?php echo esc_url(home_url('/campaign-manager/')); ?>" class="button button-secondary">
                <?php _e('Cancel', 'tapp-campaigns'); ?>
            </a>
        </p>
    </form>
</div>

<style>
.template-selector {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 10px;
}

.template-option {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.3s;
    display: block;
}

.template-option:hover {
    border-color: #999;
}

.template-option input[type="radio"] {
    margin-right: 10px;
}

.template-option input[type="radio"]:checked ~ .template-preview {
    font-weight: 600;
}

.template-option:has(input:checked) {
    border-color: #0073aa;
    background: #f0f6fc;
}

.template-preview strong {
    display: block;
    margin-bottom: 5px;
    font-size: 14px;
}

.template-preview p {
    margin: 0;
    font-size: 13px;
    color: #666;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Template selection handler - show/hide hero image field
    $('input[name="template_layout"]').on('change', function() {
        var selectedTemplate = $(this).val();
        if (selectedTemplate === 'hero') {
            $('.template-hero-settings').show();
        } else {
            $('.template-hero-settings').hide();
        }
    });

    // Product search
    $('#product-search').on('keyup', function() {
        var search = $(this).val();
        if (search.length < 2) {
            $('#product-results').empty();
            return;
        }

        $.ajax({
            url: tappCampaigns.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tapp_search_products',
                nonce: tappCampaigns.nonce,
                search: search
            },
            success: function(response) {
                if (response.success) {
                    var html = '<div class="product-search-results">';
                    response.data.forEach(function(product) {
                        html += '<div class="product-result" data-id="' + product.id + '">';
                        html += '<img src="' + product.image + '" width="50">';
                        html += '<span>' + product.name + ' (SKU: ' + product.sku + ')</span>';
                        html += '<button type="button" class="button button-small add-product">Add</button>';
                        html += '</div>';
                    });
                    html += '</div>';
                    $('#product-results').html(html);
                }
            }
        });
    });

    // Add product
    $(document).on('click', '.add-product', function() {
        var $result = $(this).closest('.product-result');
        var productId = $result.data('id');
        var productName = $result.find('span').text();

        if ($('#selected-product-' + productId).length > 0) {
            return; // Already added
        }

        var html = '<div class="selected-product" id="selected-product-' + productId + '">';
        html += '<input type="hidden" name="products[]" value="' + productId + '">';
        html += '<span>' + productName + '</span>';
        html += '<button type="button" class="button button-small remove-product">Remove</button>';
        html += '</div>';

        $('#selected-products').append(html);
        $result.remove();
    });

    // Remove product
    $(document).on('click', '.remove-product', function() {
        $(this).closest('.selected-product').remove();
    });
});
</script>
