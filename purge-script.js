jQuery(document).ready(function($) {
    // Helper function to display a temporary message in the admin bar
    function showStatusMessage(message, type) {
        // Create a unique ID for the message div
        const messageId = 'sncp-status-message-' + Date.now();
        const $messageDiv = $('<div>')
            .attr('id', messageId)
            .addClass('sncp-status-message ' + type)
            .text(message)
            .hide();

        // Get the parent list item of the purge button
        const $purgeButtonLi = $('#wp-admin-bar-sncp-purge-nginx-cache');

        // Append the message to the list item and slide it in
        $purgeButtonLi.append($messageDiv);
        $messageDiv.slideDown(200);

        // Automatically hide the message after 3 seconds
        setTimeout(function() {
            $messageDiv.slideUp(200, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Get the admin bar purge button
    var $purgeButton = $('.sncp-purge-button');

    // Attach click event listener
    $purgeButton.on('click', function(e) {
        e.preventDefault(); // Prevent default link behavior

        // Get the inner text element of the button
        var $buttonItem = $purgeButton.find('.ab-item');
        var originalButtonText = $buttonItem.html(); // Store original text

        // Show purging message with spinner
        $buttonItem.html('<span class="ab-icon dashicons-before dashicons-image-rotate spin"></span> ' + sncp_ajax_object.purging_message);
        $purgeButton.addClass('sncp-purging'); // Add class to disable further clicks and style

        // Send AJAX request
        $.ajax({
            url: sncp_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'my_purge_nginx_cache', // The AJAX action defined in PHP
                nonce: sncp_ajax_object.nonce // The security nonce
            },
            success: function(response) {
                if (response.success) {
                    showStatusMessage(sncp_ajax_object.success_message, 'success');
                } else {
                    showStatusMessage(sncp_ajax_object.error_message + ': ' + response.data.message, 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                showStatusMessage(sncp_ajax_object.error_message + ': ' + textStatus, 'error');
            },
            complete: function() {
                $buttonItem.html(originalButtonText); // Restore original text
                $purgeButton.removeClass('sncp-purging'); // Remove purging class
            }
        });
    });
});
