/**
 * TAPP Campaigns Admin JavaScript
 */

(function($) {
    'use strict';

    // Admin initialization
    $(document).ready(function() {
        // Confirm delete actions
        $('.delete-campaign').on('click', function(e) {
            if (!confirm(tappCampaigns.strings.confirmDelete)) {
                e.preventDefault();
            }
        });
    });

})(jQuery);
