/**
 * TAPP Campaigns Frontend JavaScript
 */

(function($) {
    'use strict';

    // Countdown Timer - handles all templates
    function initCountdown() {
        // Handle multiple countdown selectors for different templates
        var countdownSelectors = [
            '.tapp-countdown',
            '.countdown-compact',
            '.hero-countdown',
            '.timer-modern'
        ];

        countdownSelectors.forEach(function(selector) {
            $(selector).each(function() {
                var $countdown = $(this);
                var endTime = parseInt($countdown.data('end-time')) * 1000;
                var $timer = $countdown.find('.countdown-timer, .countdown-timer-hero');

                if (!$timer.length) {
                    $timer = $countdown;
                }

                function updateCountdown() {
                    var now = new Date().getTime();
                    var distance = endTime - now;

                    if (distance < 0) {
                        $timer.text('Ended');
                        return;
                    }

                    var days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    var text = '';
                    if (days > 0) {
                        text = days + 'd ' + hours + 'h ' + minutes + 'm';
                    } else if (hours > 0) {
                        text = hours + 'h ' + minutes + 'm ' + seconds + 's';
                    } else {
                        text = minutes + 'm ' + seconds + 's';
                    }

                    $timer.text(text);
                }

                updateCountdown();
                setInterval(updateCountdown, 1000);
            });
        });
    }

    // Campaign Form - supports all templates
    function initCampaignForm() {
        var $form = $('#tapp-campaign-form');
        if (!$form.length) return;

        // Find counter element - different templates have different locations
        var $counter = $('.selection-counter .count, .selection-counter-hero .count, .selection-counter-modern .count, .selection-bar .count');

        // Get selection limit from various possible locations
        var selectionLimit = 1;
        var limitText = $('.tapp-selection-info, .selection-header-hero, .selection-bar').text();
        var limitMatch = limitText.match(/(\d+)/);
        if (limitMatch) {
            selectionLimit = parseInt(limitMatch[1]);
        }

        // All possible card container selectors
        var cardSelectors = [
            '.tapp-product-card',
            '.product-card-modern',
            '.product-item-minimal',
            '.product-card-hero'
        ];

        // Track selections
        function updateCounter() {
            var count = $form.find('.product-checkbox:checked').length;
            $counter.text(count);

            // Disable checkboxes if limit reached
            if (count >= selectionLimit) {
                $form.find('.product-checkbox:not(:checked)').prop('disabled', true);
            } else {
                $form.find('.product-checkbox').prop('disabled', false);
            }

            // Update card styling for all template types
            cardSelectors.forEach(function(selector) {
                $form.find(selector).each(function() {
                    var $card = $(this);
                    var $checkbox = $card.find('.product-checkbox');
                    if ($checkbox.is(':checked')) {
                        $card.addClass('selected');
                    } else {
                        $card.removeClass('selected');
                    }
                });
            });
        }

        $form.on('change', '.product-checkbox', updateCounter);

        // Form submission
        $form.on('submit', function(e) {
            e.preventDefault();

            var selectedCount = $form.find('.product-checkbox:checked').length;

            if (selectedCount === 0) {
                alert(tappCampaigns.strings.selectProduct || 'Please select at least one item');
                return false;
            }

            if (selectedCount > selectionLimit) {
                alert('You can only select up to ' + selectionLimit + ' items.');
                return false;
            }

            // Collect selections from any template type
            var selections = [];
            $form.find('.product-checkbox:checked').each(function() {
                // Find parent card - try all possible selectors
                var $card = null;
                cardSelectors.forEach(function(selector) {
                    if (!$card || !$card.length) {
                        $card = $(this).closest(selector);
                    }
                }.bind(this));

                if (!$card || !$card.length) {
                    return;
                }

                var productId = $card.data('product-id');

                var selection = {
                    product_id: productId,
                    variation_id: 0,
                    color: $card.find('.product-color').val() || null,
                    size: $card.find('.product-size').val() || null,
                    quantity: parseInt($card.find('.product-quantity').val()) || 1
                };

                selections.push(selection);
            });

            // Submit via AJAX
            $.ajax({
                url: tappCampaigns.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tapp_submit_response',
                    nonce: tappCampaigns.nonce,
                    campaign_id: $form.find('input[name="campaign_id"]').val(),
                    selections: JSON.stringify(selections)
                },
                beforeSend: function() {
                    $form.find('button[type="submit"]').prop('disabled', true).text('Submitting...');
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message || 'An error occurred');
                        $form.find('button[type="submit"]').prop('disabled', false).text('Submit Selections');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                    $form.find('button[type="submit"]').prop('disabled', false).text('Submit Selections');
                }
            });
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initCountdown();
        initCampaignForm();
    });

})(jQuery);
