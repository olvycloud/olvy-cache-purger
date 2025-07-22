jQuery(document).ready(function($) {
    // Get the admin bar purge button
    var $purgeButton = $('#wp-admin-bar-sncp-purge-nginx-cache .ab-item');

    // Attach click event listener
    $purgeButton.on('click', function(e) {
        e.preventDefault(); // Prevent default link behavior

        var originalButtonText = $purgeButton.html(); // Store original text
        $purgeButton.html('<span class="ab-icon dashicons-before dashicons-update spin"></span> ' + sncp_ajax_object.purging_message); // Show purging message with spinner
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
                    alert(sncp_ajax_object.success_message);
                } else {
                    alert(sncp_ajax_object.error_message + '\n' + response.data.message);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                alert(sncp_ajax_object.error_message + '\n' + textStatus);
            },
            complete: function() {
                $purgeButton.html(originalButtonText); // Restore original text
                $purgeButton.removeClass('sncp-purging'); // Remove purging class
            }
        });
    });

    // Optional: Add some basic CSS for the spinner
    // You can also enqueue a separate CSS file for more complex styling
    $('head').append('<style>' +
        '.sncp-purging { pointer-events: none; opacity: 0.8; }' +
        '.sncp-purging .dashicons-update.spin { animation: sncp-spin 1s infinite linear; }' +
        '@keyframes sncp-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }' +
        '</style>');
});
