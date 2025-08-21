# Olvy Cache Purger

[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.txt)

Manages Nginx FastCGI cache for WordPress with global and automatic purging for posts, pages, and WooCommerce products/categories.

## Description

Olvy Cache Purger is a lightweight and efficient WordPress plugin created by [Olvy](https://olvy.net) (Managed Hosting provider) and designed to seamlessly integrate with your Nginx FastCGI cache setup. It provides robust cache management features directly from your WordPress dashboard, ensuring your website's content is always up-to-date and served quickly.

**Key Features:**

* **Global Cache Purge:** A convenient "Purge Olvy Cache" button is added to your WordPress admin bar, allowing administrators to clear the entire Nginx FastCGI cache with a single click. This operation is performed server-side, bypassing potential browser-related conflicts.
* **Automatic Post/Page Purging:** Automatically purges the Nginx cache for individual posts and pages whenever they are saved, updated, or published. This ensures that changes to your content are immediately reflected on your live site.
* **Automatic WooCommerce Product Purging:** When a WooCommerce product is saved or updated, its corresponding product page cache is automatically purged.
* **Automatic WooCommerce Product Category Purging:** Automatically purges the Nginx cache for product category archive pages when a product category is created, updated, or deleted. This helps maintain fresh content for your e-commerce listings.
* **Debug Logging:** Integrates with WordPress's debugging system to provide detailed logs of purge attempts and their outcomes, aiding in troubleshooting.

This plugin is ideal for WordPress sites running on Nginx with FastCGI caching enabled, providing essential tools for maintaining optimal site performance and cache freshness.

## Installation

1.  **Download the plugin:** Download the `olvy-cache-purger.zip` file.
2.  **Upload via WordPress Admin:**
    * Log in to your WordPress admin dashboard.
    * Go to `Plugins` > `Add New`.
    * Click the "Upload Plugin" button at the top of the page.
    * Click "Choose File" and select the `olvy-cache-purger.zip` file.
    * Click "Install Now".
    * After installation, click "Activate Plugin".
3.  **Manual Installation (Alternative):**
    * Unzip the `olvy-cache-purger.zip` file.
    * Upload the entire `olvy-cache-purger` folder to your `wp-content/plugins/` directory via FTP/SFTP.
    * Log in to your WordPress admin dashboard.
    * Go to `Plugins` > `Installed Plugins`.
    * Locate "Olvy Cache Purger" and click "Activate".
4.  **Nginx Configuration (Crucial):**
    For the plugin to work, your Nginx configuration needs to allow your WordPress server to send a purge request to the `/purge/*` URL. Ensure you have a `location` block in your Nginx configuration similar to this, allowing access from `127.0.0.1` (localhost) or your server's internal IP:

    ```nginx
    location ~ /purge(/.*) {
        allow 127.0.0.1; # Or your server's internal IP if different
        deny all;
        fastcgi_cache_purge YOUR_CACHE_ZONE_NAME "$scheme$request_method$host$1";
        # Replace 'YOUR_CACHE_ZONE_NAME' with the actual name of your keys_zone (e.g., WORDPRESS)
        # Ensure '$1' correctly captures the URI part after /purge/
        # The fastcgi_cache_key in your main Nginx config must match this pattern.
    }
    ```
    After modifying Nginx, always test the configuration and reload Nginx:
    `sudo nginx -t`
    `sudo systemctl reload nginx` (or `sudo service nginx reload`)

## Changelog

### 1.0.1
* Resolved a JavaScript syntax error (missing name after . operator) in purge-script.js.
* Added visual feedback for purge actions, including a loading spinner and temporary status messages (success/error).
* Implemented new CSS to style the visual feedback elements for a better user experience.

### 1.0.0
* Initial release of the Olvy Cache Purger plugin.
* Added global "Purge Olvy Cache" button to the admin bar.
* Implemented automatic purging for individual posts and pages on save/update.
* Added automatic purging for WooCommerce product pages on save/update.
* Added automatic purging for WooCommerce product category pages on create/edit/delete.
* Improved debug logging for all purge actions.
