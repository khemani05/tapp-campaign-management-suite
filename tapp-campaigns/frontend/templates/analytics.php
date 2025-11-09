<?php
/**
 * Campaign Analytics Page Template
 * Displays detailed statistics, charts, and participant management
 */

if (!defined('ABSPATH')) {
    exit;
}

$campaign_id = get_query_var('campaign_id');
$campaign = TAPP_Campaigns_Campaign::get($campaign_id);

if (!$campaign) {
    wp_die(__('Campaign not found.', 'tapp-campaigns'));
}

// Get campaign statistics
global $wpdb;
$participants_table = $wpdb->prefix . 'tapp_campaign_participants';
$responses_table = $wpdb->prefix . 'tapp_campaign_responses';
$products_table = $wpdb->prefix . 'tapp_campaign_products';

// Basic stats
$total_participants = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$participants_table} WHERE campaign_id = %d",
    $campaign_id
));

$total_submitted = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(DISTINCT user_id) FROM {$participants_table} WHERE campaign_id = %d AND submitted_at IS NOT NULL",
    $campaign_id
));

$total_pending = $total_participants - $total_submitted;
$participation_rate = $total_participants > 0 ? round(($total_submitted / $total_participants) * 100, 1) : 0;

// Submissions over time (last 14 days or campaign duration)
$submissions_by_date = $wpdb->get_results($wpdb->prepare(
    "SELECT DATE(submitted_at) as date, COUNT(DISTINCT user_id) as count
    FROM {$participants_table}
    WHERE campaign_id = %d AND submitted_at IS NOT NULL
    GROUP BY DATE(submitted_at)
    ORDER BY date ASC",
    $campaign_id
));

// Product popularity
$product_stats = $wpdb->get_results($wpdb->prepare(
    "SELECT
        r.product_id,
        r.variation_id,
        SUM(r.quantity) as total_quantity,
        COUNT(DISTINCT r.user_id) as user_count
    FROM {$responses_table} r
    INNER JOIN {$participants_table} p ON r.user_id = p.user_id AND r.campaign_id = p.campaign_id
    WHERE r.campaign_id = %d AND p.submitted_at IS NOT NULL
    GROUP BY r.product_id, r.variation_id
    ORDER BY total_quantity DESC",
    $campaign_id
));

// Get product details for stats
foreach ($product_stats as &$stat) {
    $product = wc_get_product($stat->variation_id ? $stat->variation_id : $stat->product_id);
    if ($product) {
        $stat->product_name = $product->get_name();
        $stat->sku = $product->get_sku();

        // Get variation attributes if applicable
        if ($stat->variation_id && $product->is_type('variation')) {
            $attributes = $product->get_variation_attributes();
            $stat->color = isset($attributes['attribute_pa_color']) ? $attributes['attribute_pa_color'] : '-';
            $stat->size = isset($attributes['attribute_pa_size']) ? $attributes['attribute_pa_size'] : '-';
        } else {
            $stat->color = '-';
            $stat->size = '-';
        }
    }
}

// Get all participants with their status
$participants = $wpdb->get_results($wpdb->prepare(
    "SELECT
        p.user_id,
        p.invited_at,
        p.submitted_at,
        p.updated_at,
        u.display_name,
        u.user_email
    FROM {$participants_table} p
    INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
    WHERE p.campaign_id = %d
    ORDER BY p.submitted_at DESC, u.display_name ASC",
    $campaign_id
));

// Add department info to participants
foreach ($participants as &$participant) {
    $participant->department = get_user_meta($participant->user_id, 'tapp_department', true);
    if (empty($participant->department)) {
        $participant->department = get_user_meta($participant->user_id, 'department', true);
    }
    $participant->status = $participant->submitted_at ? 'submitted' : 'pending';
}

// Calculate average response time
$avg_response_time = 0;
if ($total_submitted > 0) {
    $response_times = $wpdb->get_col($wpdb->prepare(
        "SELECT TIMESTAMPDIFF(HOUR, invited_at, submitted_at) as hours
        FROM {$participants_table}
        WHERE campaign_id = %d AND submitted_at IS NOT NULL",
        $campaign_id
    ));
    if (!empty($response_times)) {
        $avg_response_time = round(array_sum($response_times) / count($response_times), 1);
    }
}

?>

<div class="tapp-campaigns-analytics-page">
    <div class="analytics-header">
        <div class="analytics-title-section">
            <a href="<?php echo home_url('/campaign-manager/'); ?>" class="back-button">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 18l-8-8 8-8 1.4 1.4L5.8 9H18v2H5.8l5.6 5.6L10 18z"/>
                </svg>
                <?php _e('Back to Dashboard', 'tapp-campaigns'); ?>
            </a>
            <h1><?php echo esc_html($campaign->name); ?> - <?php _e('Analytics', 'tapp-campaigns'); ?></h1>
            <div class="campaign-meta">
                <span class="campaign-type-badge type-<?php echo esc_attr($campaign->type); ?>">
                    <?php echo esc_html(ucfirst($campaign->type)); ?>
                </span>
                <span class="campaign-status-badge status-<?php echo esc_attr($campaign->status); ?>">
                    <?php echo esc_html(ucfirst($campaign->status)); ?>
                </span>
                <span class="campaign-dates">
                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->start_date)); ?>
                    -
                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($campaign->end_date)); ?>
                </span>
            </div>
        </div>

        <div class="analytics-actions">
            <a href="<?php echo home_url('/campaign/' . $campaign->slug . '/'); ?>" class="button button-secondary" target="_blank">
                <?php _e('View Campaign', 'tapp-campaigns'); ?>
            </a>
            <a href="<?php echo wp_nonce_url(home_url('/campaign-manager/?action=export&type=audience&id=' . $campaign_id), 'export_campaign_' . $campaign_id, 'nonce'); ?>" class="button button-secondary">
                <?php _e('Export Audience', 'tapp-campaigns'); ?>
            </a>
            <a href="<?php echo wp_nonce_url(home_url('/campaign-manager/?action=export&type=responses&id=' . $campaign_id), 'export_campaign_' . $campaign_id, 'nonce'); ?>" class="button button-secondary">
                <?php _e('Export Responses', 'tapp-campaigns'); ?>
            </a>
            <a href="<?php echo wp_nonce_url(home_url('/campaign-manager/?action=export&type=summary&id=' . $campaign_id), 'export_campaign_' . $campaign_id, 'nonce'); ?>" class="button button-primary">
                <?php _e('Export Summary', 'tapp-campaigns'); ?>
            </a>
        </div>
    </div>

    <!-- Real-Time Stats -->
    <div class="analytics-stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_participants); ?></h3>
                <p><?php _e('Total Invited', 'tapp-campaigns'); ?></p>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_submitted); ?></h3>
                <p><?php _e('Submitted', 'tapp-campaigns'); ?></p>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo number_format($total_pending); ?></h3>
                <p><?php _e('Pending', 'tapp-campaigns'); ?></p>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M11 17h2v-6h-2v6zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zM11 9h2V7h-2v2z"/>
                </svg>
            </div>
            <div class="stat-content">
                <h3><?php echo $participation_rate; ?>%</h3>
                <p><?php _e('Participation Rate', 'tapp-campaigns'); ?></p>
            </div>
        </div>
    </div>

    <!-- Additional Metrics -->
    <div class="analytics-metrics-row">
        <div class="metric-item">
            <span class="metric-label"><?php _e('Avg Response Time:', 'tapp-campaigns'); ?></span>
            <span class="metric-value"><?php echo $avg_response_time; ?> <?php _e('hours', 'tapp-campaigns'); ?></span>
        </div>
        <div class="metric-item">
            <span class="metric-label"><?php _e('Selection Limit:', 'tapp-campaigns'); ?></span>
            <span class="metric-value"><?php echo $campaign->selection_limit; ?> <?php _e('items', 'tapp-campaigns'); ?></span>
        </div>
        <div class="metric-item">
            <span class="metric-label"><?php _e('Total Products:', 'tapp-campaigns'); ?></span>
            <span class="metric-value"><?php echo count($product_stats); ?></span>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="analytics-charts">
        <div class="chart-container submissions-chart">
            <h2><?php _e('Submissions Over Time', 'tapp-campaigns'); ?></h2>
            <?php if (!empty($submissions_by_date)): ?>
                <canvas id="submissions-chart" width="400" height="200"></canvas>
                <script>
                    var submissionsData = <?php echo json_encode($submissions_by_date); ?>;
                </script>
            <?php else: ?>
                <p class="no-data"><?php _e('No submission data yet.', 'tapp-campaigns'); ?></p>
            <?php endif; ?>
        </div>

        <div class="chart-container products-chart">
            <h2><?php _e('Top 10 Popular Products', 'tapp-campaigns'); ?></h2>
            <?php if (!empty($product_stats)): ?>
                <canvas id="products-chart" width="400" height="200"></canvas>
                <script>
                    var productsData = <?php echo json_encode(array_slice($product_stats, 0, 10)); ?>;
                </script>
            <?php else: ?>
                <p class="no-data"><?php _e('No product data yet.', 'tapp-campaigns'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product Summary Table -->
    <div class="analytics-section product-summary">
        <h2><?php _e('Product Summary', 'tapp-campaigns'); ?></h2>
        <?php if (!empty($product_stats)): ?>
            <table class="analytics-table product-table">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'tapp-campaigns'); ?></th>
                        <th><?php _e('SKU', 'tapp-campaigns'); ?></th>
                        <th><?php _e('Color', 'tapp-campaigns'); ?></th>
                        <th><?php _e('Size', 'tapp-campaigns'); ?></th>
                        <th><?php _e('Total Qty', 'tapp-campaigns'); ?></th>
                        <th><?php _e('Users', 'tapp-campaigns'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($product_stats as $stat): ?>
                        <tr>
                            <td><strong><?php echo esc_html($stat->product_name); ?></strong></td>
                            <td><?php echo esc_html($stat->sku); ?></td>
                            <td><?php echo esc_html(ucfirst($stat->color)); ?></td>
                            <td><?php echo esc_html(strtoupper($stat->size)); ?></td>
                            <td><strong><?php echo number_format($stat->total_quantity); ?></strong></td>
                            <td><?php echo number_format($stat->user_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data"><?php _e('No product selections yet.', 'tapp-campaigns'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Participant List -->
    <div class="analytics-section participant-list">
        <h2><?php _e('Participant Management', 'tapp-campaigns'); ?></h2>
        <div class="participant-filters">
            <input type="text" id="participant-search" class="search-input" placeholder="<?php _e('Search participants...', 'tapp-campaigns'); ?>">
            <select id="status-filter" class="filter-select">
                <option value=""><?php _e('All Statuses', 'tapp-campaigns'); ?></option>
                <option value="submitted"><?php _e('Submitted', 'tapp-campaigns'); ?></option>
                <option value="pending"><?php _e('Pending', 'tapp-campaigns'); ?></option>
            </select>
        </div>

        <?php if (!empty($participants)): ?>
            <table class="analytics-table participants-table" id="participants-table">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'tapp-campaigns'); ?></th>
                        <th><?php _e('Email', 'tapp-campaigns'); ?></th>
                        <th><?php _e('Department', 'tapp-campaigns'); ?></th>
                        <th><?php _e('Status', 'tapp-campaigns'); ?></th>
                        <th><?php _e('Submitted At', 'tapp-campaigns'); ?></th>
                        <th><?php _e('Actions', 'tapp-campaigns'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $participant): ?>
                        <tr data-user-id="<?php echo esc_attr($participant->user_id); ?>" data-status="<?php echo esc_attr($participant->status); ?>">
                            <td><strong><?php echo esc_html($participant->display_name); ?></strong></td>
                            <td><?php echo esc_html($participant->user_email); ?></td>
                            <td><?php echo esc_html($participant->department ?: '-'); ?></td>
                            <td>
                                <?php if ($participant->status === 'submitted'): ?>
                                    <span class="status-badge status-submitted"><?php _e('Submitted', 'tapp-campaigns'); ?></span>
                                <?php else: ?>
                                    <span class="status-badge status-pending"><?php _e('Pending', 'tapp-campaigns'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($participant->submitted_at): ?>
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($participant->submitted_at)); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <?php if ($participant->status === 'submitted'): ?>
                                    <button class="button-link view-response" data-user-id="<?php echo esc_attr($participant->user_id); ?>">
                                        <?php _e('View', 'tapp-campaigns'); ?>
                                    </button>
                                    <button class="button-link edit-response" data-user-id="<?php echo esc_attr($participant->user_id); ?>">
                                        <?php _e('Edit', 'tapp-campaigns'); ?>
                                    </button>
                                    <button class="button-link delete-response" data-user-id="<?php echo esc_attr($participant->user_id); ?>">
                                        <?php _e('Delete', 'tapp-campaigns'); ?>
                                    </button>
                                <?php else: ?>
                                    <button class="button-link send-reminder" data-user-id="<?php echo esc_attr($participant->user_id); ?>">
                                        <?php _e('Remind', 'tapp-campaigns'); ?>
                                    </button>
                                    <button class="button-link remove-participant" data-user-id="<?php echo esc_attr($participant->user_id); ?>">
                                        <?php _e('Remove', 'tapp-campaigns'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data"><?php _e('No participants yet.', 'tapp-campaigns'); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Response View Modal -->
<div id="response-modal" class="tapp-modal" style="display:none;">
    <div class="tapp-modal-content">
        <span class="tapp-modal-close">&times;</span>
        <h2><?php _e('Participant Response', 'tapp-campaigns'); ?></h2>
        <div id="response-modal-body"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var campaignId = <?php echo $campaign_id; ?>;

    // Participant search
    $('#participant-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        filterParticipants();
    });

    // Status filter
    $('#status-filter').on('change', function() {
        filterParticipants();
    });

    function filterParticipants() {
        var searchTerm = $('#participant-search').val().toLowerCase();
        var statusFilter = $('#status-filter').val();

        $('#participants-table tbody tr').each(function() {
            var $row = $(this);
            var name = $row.find('td:eq(0)').text().toLowerCase();
            var email = $row.find('td:eq(1)').text().toLowerCase();
            var status = $row.data('status');

            var matchesSearch = name.includes(searchTerm) || email.includes(searchTerm);
            var matchesStatus = !statusFilter || status === statusFilter;

            if (matchesSearch && matchesStatus) {
                $row.show();
            } else {
                $row.hide();
            }
        });
    }

    // View response
    $(document).on('click', '.view-response', function() {
        var userId = $(this).data('user-id');
        loadResponse(userId, 'view');
    });

    // Edit response
    $(document).on('click', '.edit-response', function() {
        var userId = $(this).data('user-id');
        loadResponse(userId, 'edit');
    });

    // Delete response
    $(document).on('click', '.delete-response', function() {
        if (!confirm('<?php _e('Are you sure you want to delete this response? This cannot be undone.', 'tapp-campaigns'); ?>')) {
            return;
        }

        var userId = $(this).data('user-id');
        deleteResponse(userId);
    });

    // Send reminder
    $(document).on('click', '.send-reminder', function() {
        var userId = $(this).data('user-id');
        sendReminder(userId);
    });

    // Remove participant
    $(document).on('click', '.remove-participant', function() {
        if (!confirm('<?php _e('Are you sure you want to remove this participant?', 'tapp-campaigns'); ?>')) {
            return;
        }

        var userId = $(this).data('user-id');
        removeParticipant(userId);
    });

    // Modal close
    $('.tapp-modal-close').on('click', function() {
        $('#response-modal').hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).is('#response-modal')) {
            $('#response-modal').hide();
        }
    });

    function loadResponse(userId, mode) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'tapp_load_response',
                campaign_id: campaignId,
                user_id: userId,
                mode: mode,
                nonce: '<?php echo wp_create_nonce('tapp_analytics_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#response-modal-body').html(response.data.html);
                    $('#response-modal').show();
                } else {
                    alert(response.data.message || '<?php _e('Error loading response.', 'tapp-campaigns'); ?>');
                }
            }
        });
    }

    function deleteResponse(userId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'tapp_delete_response',
                campaign_id: campaignId,
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('tapp_analytics_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('Error deleting response.', 'tapp-campaigns'); ?>');
                }
            }
        });
    }

    function sendReminder(userId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'tapp_send_reminder',
                campaign_id: campaignId,
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('tapp_analytics_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert(response.data.message || '<?php _e('Error sending reminder.', 'tapp-campaigns'); ?>');
                }
            }
        });
    }

    function removeParticipant(userId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'tapp_remove_participant',
                campaign_id: campaignId,
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('tapp_analytics_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('Error removing participant.', 'tapp-campaigns'); ?>');
                }
            }
        });
    }
});
</script>
