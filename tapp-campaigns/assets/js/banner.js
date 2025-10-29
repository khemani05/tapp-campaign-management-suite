/**
 * TAPP Campaigns - Homepage Banner JavaScript
 */

(function($) {
    'use strict';

    // Initialize banner functionality
    $(document).ready(function() {
        initBannerCountdowns();
        initBannerDismiss();
    });

    /**
     * Initialize countdown timers in banners
     */
    function initBannerCountdowns() {
        $('.campaign-countdown').each(function() {
            var $countdown = $(this);
            var endTime = parseInt($countdown.data('end-time')) * 1000;
            var $text = $countdown.find('.countdown-text');

            function updateCountdown() {
                var now = new Date().getTime();
                var distance = endTime - now;

                if (distance < 0) {
                    $text.text('Ended');
                    $countdown.closest('.tapp-campaign-banner').fadeOut(300, function() {
                        $(this).remove();
                    });
                    return;
                }

                var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));

                var text = '';
                if (days > 0) {
                    text = days + ' day' + (days !== 1 ? 's' : '') + ' left';
                } else if (hours > 0) {
                    text = hours + ' hour' + (hours !== 1 ? 's' : '') + ', ' + minutes + ' min left';
                } else if (minutes > 0) {
                    text = minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' left';
                } else {
                    text = 'Less than 1 minute left';
                }

                $text.text(text);

                // Update urgency class based on time remaining
                var $banner = $countdown.closest('.tapp-campaign-banner');
                $banner.removeClass('tapp-urgency-normal tapp-urgency-warning tapp-urgency-urgent');

                if (distance < 86400000) { // Less than 24 hours
                    $banner.addClass('tapp-urgency-urgent');
                } else if (distance < 259200000) { // Less than 3 days
                    $banner.addClass('tapp-urgency-warning');
                } else {
                    $banner.addClass('tapp-urgency-normal');
                }
            }

            updateCountdown();
            setInterval(updateCountdown, 60000); // Update every minute
        });
    }

    /**
     * Handle banner dismiss
     */
    function initBannerDismiss() {
        $(document).on('click', '.banner-dismiss', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $banner = $button.closest('.tapp-campaign-banner');
            var campaignId = $button.data('campaign-id');

            // Disable button to prevent multiple clicks
            $button.prop('disabled', true);

            // Add dismissing class for animation
            $banner.addClass('dismissing');

            // Send AJAX request
            $.ajax({
                url: tappCampaigns.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tapp_dismiss_banner',
                    nonce: tappCampaigns.nonce,
                    campaign_id: campaignId
                },
                success: function(response) {
                    if (response.success) {
                        // Wait for animation to complete
                        setTimeout(function() {
                            $banner.slideUp(300, function() {
                                $(this).remove();

                                // If no more banners, remove container
                                if ($('.tapp-campaign-banner').length === 0) {
                                    $('.tapp-campaigns-banner-container').fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                }
                            });
                        }, 300);
                    } else {
                        // Revert on error
                        $banner.removeClass('dismissing');
                        $button.prop('disabled', false);
                        alert(response.data.message || 'Failed to dismiss banner');
                    }
                },
                error: function() {
                    // Revert on error
                    $banner.removeClass('dismissing');
                    $button.prop('disabled', false);
                    alert('An error occurred. Please try again.');
                }
            });
        });
    }

    /**
     * Handle banner visibility based on scroll (for fixed banners)
     */
    if ($('.tapp-banner-fixed').length > 0) {
        var lastScrollTop = 0;
        var scrollThreshold = 100;

        $(window).on('scroll', function() {
            var scrollTop = $(this).scrollTop();

            if (scrollTop > lastScrollTop && scrollTop > scrollThreshold) {
                // Scrolling down - hide banner
                $('.tapp-banner-fixed').addClass('hidden');
            } else {
                // Scrolling up - show banner
                $('.tapp-banner-fixed').removeClass('hidden');
            }

            lastScrollTop = scrollTop;
        });
    }

})(jQuery);
