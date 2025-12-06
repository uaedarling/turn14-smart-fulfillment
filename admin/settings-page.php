<?php
/**
 * Main Settings/Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle Save
if (isset($_POST['t14sf_save_settings'])) {
    if (! current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('t14sf_settings_nonce');

    // General Settings
    $price_mode = isset($_POST['price_mode']) ? sanitize_text_field($_POST['price_mode']) : 'auto';
    $stock_threshold = isset($_POST['stock_threshold']) ? intval($_POST['stock_threshold']) : 0;
    
    // Shipping IDs
    $turn14_method_id = isset($_POST['turn14_method_id']) ? sanitize_text_field($_POST['turn14_method_id']) : '';
    
    // Safely handle array input
    $raw_methods = isset($_POST['local_methods']) ? $_POST['local_methods'] : array();
    $local_methods = is_array($raw_methods) ? array_map('sanitize_text_field', $raw_methods) : array();

    // Turn14 API settings
    $turn14_api_endpoint = isset($_POST['turn14_api_endpoint']) ? esc_url_raw($_POST['turn14_api_endpoint']) : '';
    $turn14_api_key = isset($_POST['turn14_api_key']) ? sanitize_text_field($_POST['turn14_api_key']) : '';
    $turn14_markup_percent = isset($_POST['turn14_markup_percent']) ? floatval($_POST['turn14_markup_percent']) : 0;

    // Save Options
    Turn14_Smart_Fulfillment::update_option('price_mode', $price_mode);
    Turn14_Smart_Fulfillment::update_option('stock_threshold', $stock_threshold);
    Turn14_Smart_Fulfillment::update_option('turn14_method_id', $turn14_method_id);
    Turn14_Smart_Fulfillment::update_option('local_methods', $local_methods);

    Turn14_Smart_Fulfillment::update_option('turn14_api_endpoint', $turn14_api_endpoint);
    Turn14_Smart_Fulfillment::update_option('turn14_api_key', $turn14_api_key);
    Turn14_Smart_Fulfillment::update_option('turn14_markup_percent', $turn14_markup_percent);

    echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved successfully!</strong></p></div>';
}

// Retrieve Options
$price_mode = Turn14_Smart_Fulfillment::get_option('price_mode', 'auto');
$stock_threshold = Turn14_Smart_Fulfillment::get_option('stock_threshold', 0);
$turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');

// CRITICAL FIX: Ensure $local_methods is strictly an array
$local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));
if (!is_array($local_methods)) {
    $local_methods = (array) $local_methods;
}

$turn14_api_endpoint = Turn14_Smart_Fulfillment::get_option('turn14_api_endpoint', '');
$turn14_api_key = Turn14_Smart_Fulfillment::get_option('turn14_api_key', '');
$turn14_markup_percent = Turn14_Smart_Fulfillment::get_option('turn14_markup_percent', 0);

// Get Stats
global $wpdb;
$total_products = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
$products_with_local_stock = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_stock' AND CAST(meta_value AS UNSIGNED) > 0");
$products_with_turn14_price = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_price' AND meta_value != ''");
$products_with_turn14_stock = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_stock' AND CAST(meta_value AS UNSIGNED) > 0");

// Get WC Shipping Methods
$shipping_methods = array();
if (function_exists('WC') && WC()->shipping()) {
    $shipping_methods = WC()->shipping()->get_shipping_methods();
}
?>

<div class="wrap t14sf-dashboard">
    <h1>🚀 Turn14 Smart Fulfillment Dashboard</h1>
    <p class="description">Intelligent stock, pricing, and shipping management for Turn14 integration.</p>

    <div class="t14sf-stats-grid">
        <div class="t14sf-stat-card">
            <div class="t14sf-stat-icon">📦</div>
            <div class="t14sf-stat-content">
                <h3>Total Products</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $total_products ) ); ?></p>
            </div>
        </div>
        
        <div class="t14sf-stat-card t14sf-stat-success">
            <div class="t14sf-stat-icon">🏭</div>
            <div class="t14sf-stat-content">
                <h3>Local Stock</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $products_with_local_stock ) ); ?></p>
            </div>
        </div>

        <div class="t14sf-stat-card t14sf-stat-primary">
            <div class="t14sf-stat-icon">💲</div>
            <div class="t14sf-stat-content">
                <h3>T14 Prices</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $products_with_turn14_price ) ); ?></p>
            </div>
        </div>

        <div class="t14sf-stat-card t14sf-stat-warning">
            <div class="t14sf-stat-icon">🚚</div>
            <div class="t14sf-stat-content">
                <h3>T14 Stock</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $products_with_turn14_stock ) ); ?></p>
            </div>
        </div>
    </div>

    <form method="post" class="t14sf-settings-form">
        <?php wp_nonce_field('t14sf_settings_nonce'); ?>
        <input type="hidden" name="t14sf_save_settings" value="1">

        <div class="t14sf-settings-section">
            <h2>💰 Price Management</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="price_mode">Price Display Mode</label></th>
                    <td>
                        <select name="price_mode" id="price_mode" class="regular-text">
                            <option value="auto" <?php selected($price_mode, 'auto'); ?>>Auto (Based on Stock)</option>
                            <option value="always_local" <?php selected($price_mode, 'always_local'); ?>>Always Show Local Price</option>
                            <option value="always_turn14" <?php selected($price_mode, 'always_turn14'); ?>>Always Show Turn14 Price</option>
                            <option value="manual" <?php selected($price_mode, 'manual'); ?>>Manual</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="stock_threshold">Stock Threshold</label></th>
                    <td>
                        <input type="number" name="stock_threshold" id="stock_threshold" value="<?php echo esc_attr($stock_threshold); ?>" min="0" step="1" class="small-text" />
                        <p class="description">Switch to Turn14 fulfillment when local stock drops to this level or below.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="t14sf-settings-section">
            <h2>🚚 Shipping & Turn14 API</h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="turn14_api_endpoint">Turn14 API Endpoint</label></th>
                    <td>
                        <input type="url" name="turn14_api_endpoint" id="turn14_api_endpoint" value="<?php echo esc_attr($turn14_api_endpoint); ?>" class="regular-text" placeholder="https://api.turn14.com" />
                        <p class="description">Enter the base URL for your Turn14 shipping API. The plugin posts to <code>/shipping/rates</code> on this endpoint.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="turn14_api_key">Turn14 API Key</label></th>
                    <td>
                        <input type="text" name="turn14_api_key" id="turn14_api_key" value="<?php echo esc_attr($turn14_api_key); ?>" class="regular-text" />
                        <p class="description">API Key used for authentication (will be sent in the Authorization header).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="turn14_markup_percent">Turn14 Markup (%)</label></th>
                    <td>
                        <input type="number" name="turn14_markup_percent" id="turn14_markup_percent" value="<?php echo esc_attr($turn14_markup_percent); ?>" min="0" step="0.1" class="small-text" />
                        <p class="description">Apply a percentage markup to Turn14-provided rates (e.g., 10 for 10%).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="turn14_method_id">Turn14 Shipping Method ID</label></th>
                    <td>
                        <input type="text" name="turn14_method_id" id="turn14_method_id" value="<?php echo esc_attr($turn14_method_id); ?>" class="regular-text" placeholder="turn14_shipping" />
                        <p class="description">Method ID used to identify Turn14 shipping rates (default: <code>turn14_shipping</code>).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Local Warehouse Shipping Methods</th>
                    <td>
                        <?php if (!empty($shipping_methods)): ?>
                            <?php foreach ($shipping_methods as $method_id => $method): ?>
                                <?php if ($method_id === $turn14_method_id) continue; ?>
                                <label style="display: block; margin: 8px 0;">
                                    <input type="checkbox" name="local_methods[]" value="<?php echo esc_attr($method_id); ?>" <?php checked(in_array($method_id, $local_methods)); ?> />
                                    <strong><?php echo esc_html($method->get_method_title()); ?></strong>
                                    <code style="color: #666;">(<?php echo esc_html($method_id); ?>)</code>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="description">No shipping methods found. Configure them in WooCommerce → Settings → Shipping.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="t14sf_save_settings" class="button button-primary button-large">💾 Save Settings</button>
        </p>
    </form>
</div>
