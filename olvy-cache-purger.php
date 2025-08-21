<?php
/**
 * Plugin Name: Olvy Cache Purger
 * Plugin URI:  https://github.com/olvycloud/olvy-cache-purger
 * Description: Manages Nginx FastCGI cache for WordPress with global and automatic purging for posts, pages, and WooCommerce products/categories.
 * Version:     1.0.0
 * Author:      Olvy Cloud
 * Author URI:  https://olvy.net/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: olvy-cache-purger
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Add custom CSS for the admin bar button and status messages.
 */
function sncp_add_admin_styles() {
    ?>
    <style type="text/css">
        /* Styles for the loading spinner */
        .sncp-purge-button .spin {
            animation: sncp-rotate 1.5s linear infinite;
        }
        @keyframes sncp-rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Set the parent list item as the positioning context */
        #wp-admin-bar-sncp-purge-nginx-cache {
            position: relative;
        }

        /* General styles for the status messages */
        .sncp-status-message {
            position: absolute;
            top: 100%; /* Position directly below the button */
            left: 0; /* Align with the left edge of the button's container */
            padding: 10px 15px;
            margin-top: 5px; /* A little space between the button and message */
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            font-size: 14px;
            color: #fff;
            z-index: 99999;
            white-space: nowrap; /* Prevents text from wrapping */
        }

        /* Specific colors for success and error messages */
        .sncp-status-message.success {
            background-color: #46b450; /* WordPress green */
        }
        .sncp-status-message.error {
            background-color: #dc3232; /* WordPress red */
        }
    </style>
    <?php
}
add_action( 'admin_head', 'sncp_add_admin_styles' );
add_action( 'wp_head', 'sncp_add_admin_styles' );

/**
 * Add the "Purge Olvy Cache" button to the WordPress admin bar.
 */
function sncp_add_purge_button_to_admin_bar( $wp_admin_bar ) {
    // Check if the current user can manage options (typically administrators)
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Add the main purge button
    $wp_admin_bar->add_node( array(
        'id'    => 'sncp-purge-nginx-cache',
        'title' => '<span class="ab-icon dashicons-before dashicons-image-rotate"></span> Purge Olvy Cache',
        'href'  => '#', // This will be handled by JavaScript
        'meta'  => array(
            'title' => __( 'Purge All Olvy Cache', 'olvy-cache-purger' ),
            'class' => 'sncp-purge-button', // Add a class for JavaScript targeting
        ),
    ) );
}
add_action( 'admin_bar_menu', 'sncp_add_purge_button_to_admin_bar', 999 ); // High priority to appear towards the right

/**
 * Enqueue JavaScript and localize script for AJAX handling.
 */
function sncp_enqueue_scripts() {
    // Only enqueue in the admin area and if the user can manage options
    if ( is_admin() && current_user_can( 'manage_options' ) ) {
        wp_enqueue_script(
            'sncp-purge-script',
            plugins_url( 'purge-script.js', __FILE__ ),
            array( 'jquery' ),
            '1.0.0',
            true // Load in footer
        );

        // Pass necessary data to the JavaScript file
        wp_localize_script(
            'sncp-purge-script',
            'sncp_ajax_object',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'sncp_purge_nonce' ), // Create a security nonce
                'purging_message' => __( 'Purging Olvy cache...', 'olvy-cache-purger' ),
                'success_message' => __( 'Olvy cache purged successfully!', 'olvy-cache-purger' ),
                'error_message'   => __( 'Error purging Olvy cache. Check server logs.', 'olvy-cache-purger' ),
                'permission_error' => __( 'You do not have permission to purge the cache.', 'olvy-cache-purger' ),
            )
        );
    }
}
add_action( 'admin_enqueue_scripts', 'sncp_enqueue_scripts' );
add_action( 'wp_enqueue_scripts', 'sncp_enqueue_scripts' ); // Also for frontend admin bar

/**
 * Helper function for logging messages only when WP_DEBUG_LOG is enabled.
 *
 * @param string $message The message to log.
 */
function _sncp_log( $message ) {
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG === true ) {
        error_log( 'SNCP: ' . $message );
    }
}

/**
 * Handles sending the purge request to Nginx and logging the response.
 * This function is internal to the plugin and prefixed with _sncp_
 * to indicate it's not meant for direct external use.
 *
 * @param string $purge_url The full URL to send the purge request to.
 * @param string $context_type A string describing the context (e.g., 'post', 'term', 'all').
 * @param int|string $context_id The ID of the item being purged (e.g., post ID, term ID, or 'all').
 * @return array An array with 'success' (bool) and 'message' (string).
 */
function _sncp_send_purge_request( $purge_url, $context_type, $context_id ) {
    $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
    if ( empty( $host ) ) {
        _sncp_log( 'Could not determine host for purging ' . $context_type . ' cache for ID ' . $context_id );
        return array( 'success' => false, 'message' => __( 'Could not determine host.', 'olvy-cache-purger' ) );
    }

    _sncp_log( 'Attempting to purge URL: ' . $purge_url . ' for ' . $context_type . ' (ID: ' . $context_id . ')' );

    $response = wp_remote_get( $purge_url, array(
        'timeout'     => 10,
        'sslverify'   => false, // Set to true in production if you have valid SSL on your Nginx purge endpoint
        'headers'     => array(
            'Host' => $host,
        ),
    ) );

    if ( is_wp_error( $response ) ) {
        _sncp_log( 'Error purging ' . $context_type . ' cache for ID ' . $context_id . ': ' . $response->get_error_message() );
        return array( 'success' => false, 'message' => $response->get_error_message() );
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code >= 200 && $response_code < 300 ) {
            _sncp_log( 'Successfully purged cache for ' . $context_type . ' ID ' . $context_id . ' (URL: ' . $purge_url . ')' );
            return array( 'success' => true, 'message' => __( 'Cache purged successfully!', 'olvy-cache-purger' ) );
        } else {
            $body = wp_remote_retrieve_body( $response );
            _sncp_log( 'Error purging ' . $context_type . ' cache for ID ' . $context_id . '. HTTP Code: ' . $response_code . ' Body: ' . $body );
            return array( 'success' => false, 'message' => __( 'HTTP Error: ', 'olvy-cache-purger' ) . $response_code . ' - ' . $body );
        }
    }
}


/**
 * Handle the AJAX request to purge Nginx cache.
 */
function sncp_handle_purge_request() {
    // Define messages directly in PHP for server-side use
    $permission_error_msg = __( 'You do not have permission to purge the cache.', 'olvy-cache-purger' );
    $host_error_msg       = __( 'Could not determine host for purge URL.', 'olvy-cache-purger' );
    $purge_error_msg      = __( 'Error purging Olvy cache. Check server logs.', 'olvy-cache-purger' );
    $success_msg          = __( 'Olvy cache purged successfully!', 'olvy-cache-purger' );

    // Sanitize and unslash the nonce before verification to satisfy static analysis tools
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

    // Verify nonce for security
    if ( ! wp_verify_nonce( $nonce, 'sncp_purge_nonce' ) ) {
        wp_send_json_error( array( 'message' => $permission_error_msg ) );
        wp_die();
    }

    // Check if the user can manage options
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => $permission_error_msg ) );
        wp_die();
    }

    // Get the current host to construct the purge URL
    $host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
    if ( empty( $host ) ) {
        wp_send_json_error( array( 'message' => $host_error_msg ) );
        wp_die();
    }

    // Construct the full purge URL for all cache
    $purge_url = 'https://' . $host . '/purge/*'; // Assuming HTTPS and /purge/* for all cache

    // Call the helper function to send the purge request
    $result = _sncp_send_purge_request( $purge_url, 'all', 'all' );

    if ( $result['success'] ) {
        wp_send_json_success( array( 'message' => $success_msg ) );
    } else {
        wp_send_json_error( array( 'message' => $purge_error_msg . ' ' . $result['message'] ) );
    }

    wp_die(); // Always die at the end of an AJAX handler
}
add_action( 'wp_ajax_my_purge_nginx_cache', 'sncp_handle_purge_request' ); // For logged-in users
// add_action( 'wp_ajax_nopriv_my_purge_nginx_cache', 'sncp_handle_purge_request' ); // Uncomment if you need to allow non-logged-in users (NOT RECOMMENDED for cache purging)


/**
 * Purge Nginx cache for a specific post/page when it is saved or updated.
 *
 * @param int     $post_id The post ID.
 * @param WP_Post $post    The post object.
 */
function sncp_purge_on_save_post( $post_id, $post ) {
    // Bail if it's an autosave or a revision
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    // Log the post type for debugging
    _sncp_log( 'save_post triggered for post type: ' . $post->post_type . ' (ID: ' . $post_id . ')' );

    // Check if the post status is publish or future (for scheduled posts)
    if ( 'publish' !== $post->post_status && 'future' !== $post->post_status ) {
        _sncp_log( 'Post status ' . $post->post_type . ' is ' . $post->post_status . '. Skipping purge for ID: ' . $post_id );
        return;
    }

    // Get the permalink of the post
    $permalink = get_permalink( $post_id );

    // If permalink is empty, something is wrong, log and exit
    if ( empty( $permalink ) ) {
        _sncp_log( 'Could not get permalink for post ID ' . $post_id );
        return;
    }

    // Extract the path from the permalink to use in the purge URL
    $parsed_url = wp_parse_url( $permalink );
    $path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';

    // Construct the full purge URL for the specific page/post
    $purge_url = 'https://' . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . '/purge' . $path;

    // Call the helper function to send the purge request
    _sncp_send_purge_request( $purge_url, $post->post_type, $post_id );
}
add_action( 'save_post', 'sncp_purge_on_save_post', 10, 2 );


/**
 * Purge Nginx cache for a specific taxonomy term (like product categories) when it is updated.
 *
 * @param int    $term_id The term ID.
 * @param int    $tt_id   The term taxonomy ID.
 * @param string $taxonomy The taxonomy slug.
 */
function sncp_purge_on_edit_term( $term_id, $tt_id, $taxonomy ) {
    // Only proceed if the taxonomy is 'product_cat' (WooCommerce product categories)
    if ( 'product_cat' !== $taxonomy ) {
        _sncp_log( 'edited_term triggered for non-product_cat taxonomy: ' . $taxonomy . '. Skipping purge for ID: ' . $term_id );
        return;
    }

    // Get the term object
    $term = get_term( $term_id, $taxonomy );

    // If term is not found or is a WP_Error, log and exit
    if ( is_wp_error( $term ) || ! $term ) {
        _sncp_log( 'Could not retrieve term object for ID ' . $term_id . ' in taxonomy ' . $taxonomy );
        return;
    }

    // Get the permalink of the term (category)
    $permalink = get_term_link( $term, $taxonomy );

    // If permalink is empty or a WP_Error, log and exit
    if ( is_wp_error( $permalink ) || empty( $permalink ) ) {
        _sncp_log( 'Could not get permalink for term ID ' . $term_id . ' in taxonomy ' . $taxonomy . ': ' . ( is_wp_error( $permalink ) ? $permalink->get_error_message() : 'Empty permalink' ) );
        return;
    }

    // Extract the path from the permalink to use in the purge URL
    $parsed_url = wp_parse_url( $permalink );
    $path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';

    // Construct the full purge URL for the specific taxonomy page
    $purge_url = 'https://' . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . '/purge' . $path;

    // Call the helper function to send the purge request
    _sncp_send_purge_request( $purge_url, $taxonomy, $term_id );
}
add_action( 'edited_term', 'sncp_purge_on_edit_term', 10, 3 );
add_action( 'create_term', 'sncp_purge_on_edit_term', 10, 3 ); // Also purge on new term creation
add_action( 'delete_term', 'sncp_purge_on_edit_term', 10, 3 ); // Also purge on term deletion

