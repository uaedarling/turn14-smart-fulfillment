<?php
// (Replace only the load_dependencies() method inside your main plugin class)

private function load_dependencies() {
    // Core non-admin includes
    $core_files = array(
        'includes/price-manager.php',
        'includes/stock-manager.php',
        'includes/shipping-splitter.php',
        'includes/order-tagging.php',
        'includes/turn14-api.php',
        'includes/turn14-shipping-method.php',
    );

    foreach ($core_files as $relative) {
        $path = T14SF_PLUGIN_DIR . $relative;
        if (file_exists($path)) {
            require_once $path;
        } else {
            error_log('Turn14 Smart Fulfillment missing file: ' . $path);
            add_action('admin_notices', function() use ($relative) {
                printf(
                    '<div class="notice notice-warning"><p><strong>Turn14 Smart Fulfillment:</strong> Missing file: %s. Please re-upload the plugin files.</p></div>',
                    esc_html($relative)
                );
            });
        }
    }

    // Do NOT include admin files here. They will be included only when rendering the admin page.
}
