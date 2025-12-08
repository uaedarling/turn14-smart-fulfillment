<?php
/**
 * Main Settings/Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all available shipping methods from WooCommerce zones
 * Returns array of methods with their details
 */
function t14sf_get_available_shipping_methods() {
    $available = array();
    
    if (!class_exists('WC_Shipping_Zones')) {
        return $available;
    }
    
    try {
        // Get all zones
        $zones = WC_Shipping_Zones::get_zones();
        
        foreach ($zones as $zone) {
            $zone_name = isset($zone['zone_name']) ? $zone['zone_name'] : 'Unknown Zone';
            
            if (isset($zone['shipping_methods']) && is_array($zone['shipping_methods'])) {
                foreach ($zone['shipping_methods'] as $method) {
                    if (!is_object($method) || !isset($method->enabled) || $method->enabled !== 'yes') {
                        continue;
                    }
                    
                    $method_id = isset($method->id) ? $method->id : 'unknown';
                    $instance_id = isset($method->instance_id) ? $method->instance_id : null;
                    $title = isset($method->title) ? $method->title : 'Unknown Method';
                    
                    // Create the rate ID as WooCommerce does (method_id:instance_id)
                    $rate_id = $instance_id ? $method_id . ':' . $instance_id : $method_id;
                    
                    $available[$rate_id] = array(
                        'id' => $rate_id,
                        'method_id' => $method_id,
                        'instance_id' => $instance_id,
                        'title' => $title,
                        'zone' => $zone_name,
                    );
                }
            }
        }
        
        // Get default zone (zone 0)
        if (class_exists('WC_Shipping_Zone')) {
            $default_zone = new WC_Shipping_Zone(0);
            $methods = $default_zone->get_shipping_methods();
            
            foreach ($methods as $method) {
                if (!is_object($method) || !isset($method->enabled) || $method->enabled !== 'yes') {
                    continue;
                }
                
                $method_id = isset($method->id) ? $method->id : 'unknown';
                $instance_id = isset($method->instance_id) ? $method->instance_id : null;
                $title = isset($method->title) ? $method->title : 'Unknown Method';
                
                $rate_id = $instance_id ? $method_id . ':' . $instance_id : $method_id;
                
                $available[$rate_id] = array(
                    'id' => $rate_id,
                    'method_id' => $method_id,
                    'instance_id' => $instance_id,
                    'title' => $title,
                    'zone' => 'Default Zone (Everywhere else)',
                );
            }
        }
    } catch (Exception $e) {
        error_log('T14SF: Error getting available shipping methods - ' . $e->getMessage());
    }
    
    return $available;
}

// Handle API Settings Save
if (isset($_POST['t14sf_save_api_settings'])) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('t14sf_api_settings_nonce');
    
    // Sanitize and save API settings
    $api_client_id = isset($_POST['api_client_id']) ? sanitize_text_field($_POST['api_client_id']) : '';
    $api_client_secret = isset($_POST['api_client_secret']) ? sanitize_text_field($_POST['api_client_secret']) : '';
    $api_base_url = isset($_POST['api_base_url']) ? esc_url_raw($_POST['api_base_url']) : '';
    $api_currency_rate = isset($_POST['api_currency_rate']) ? floatval($_POST['api_currency_rate']) : 3.699;
    
    update_option('t14sf_api_client_id', $api_client_id);
    update_option('t14sf_api_client_secret', $api_client_secret);
    update_option('t14sf_api_base_url', $api_base_url);
    update_option('t14sf_api_currency_rate', $api_currency_rate);
    
    // Clear token cache when credentials change
    Turn14_API_Config::clear_token_cache();
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>API settings saved successfully!</strong></p></div>';
}

// Handle General Settings Save
if (isset($_POST['t14sf_save_settings'])) {
    if (! current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('t14sf_settings_nonce');

    // General Settings
    $price_mode = isset($_POST['price_mode']) ? sanitize_text_field($_POST['price_mode']) : 'auto';
    $stock_threshold = isset($_POST['stock_threshold']) ? intval($_POST['stock_threshold']) : 0;

    // Save Options
    Turn14_Smart_Fulfillment::update_option('price_mode', $price_mode);
    Turn14_Smart_Fulfillment::update_option('stock_threshold', $stock_threshold);

    echo '<div class="notice notice-success is-dismissible"><p><strong>Settings saved successfully!</strong></p></div>';
}

// Handle Shipping Settings Save
if (isset($_POST['t14sf_save_shipping_settings'])) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    check_admin_referer('t14sf_shipping_settings_nonce');
    
    $turn14_shipping_markup = isset($_POST['turn14_shipping_markup']) ? floatval($_POST['turn14_shipping_markup']) : 0;
    $turn14_method_id = isset($_POST['turn14_method_id']) ? sanitize_text_field($_POST['turn14_method_id']) : 'turn14_shipping';
    
    $raw_methods = isset($_POST['local_methods']) ? $_POST['local_methods'] : array();
    $local_methods = is_array($raw_methods) ? array_map('sanitize_text_field', $raw_methods) : array();
    
    $enable_debug = isset($_POST['enable_debug']) ? true : false;
    
    Turn14_Smart_Fulfillment::update_option('turn14_shipping_markup', $turn14_shipping_markup);
    Turn14_Smart_Fulfillment::update_option('turn14_method_id', $turn14_method_id);
    Turn14_Smart_Fulfillment::update_option('local_methods', $local_methods);
    Turn14_Smart_Fulfillment::update_option('enable_debug', $enable_debug);
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>Shipping settings saved successfully!</strong></p></div>';
}

// Retrieve Options
$price_mode = Turn14_Smart_Fulfillment::get_option('price_mode', 'auto');
$stock_threshold = Turn14_Smart_Fulfillment::get_option('stock_threshold', 0);

// Shipping Settings
$turn14_shipping_markup = Turn14_Smart_Fulfillment::get_option('turn14_shipping_markup', 0);
$turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');
$enable_debug = Turn14_Smart_Fulfillment::get_option('enable_debug', false);

// CRITICAL FIX: Ensure $local_methods is strictly an array
$local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));
if (!is_array($local_methods)) {
    $local_methods = (array) $local_methods;
}

// Get API Configuration Options
$api_client_id = get_option('t14sf_api_client_id', 'c387a85cac64c9c4fa6d57a5ef0dfb8ad97a6d26');
$api_client_secret = get_option('t14sf_api_client_secret', '15469b8ad5adf0c1f9d48faa969dc5a6d6e59e9e');
$api_base_url = get_option('t14sf_api_base_url', 'https://apitest.turn14.com');
$api_currency_rate = get_option('t14sf_api_currency_rate', 3.699);

// Get Stats
global $wpdb;
$total_products = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
$products_with_local_stock = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_stock' AND CAST(meta_value AS UNSIGNED) > 0");
$products_with_turn14_price = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_price' AND meta_value != ''");
$products_with_turn14_stock = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_stock' AND CAST(meta_value AS UNSIGNED) > 0");
?>

<div class="wrap t14sf-dashboard">
    <h1>🚀 Turn14 Smart Fulfillment Dashboard</h1>
    <p class="description">
        Intelligent stock, pricing, and shipping management for Turn14 integration.
        <a href="<?php echo esc_url(admin_url('admin.php?page=t14sf-system-check')); ?>" class="button button-secondary" style="margin-left: 10px;">
            🔍 System Check
        </a>
    </p>

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

    <!-- API Configuration Section -->
    <form method="post" class="t14sf-settings-form" style="margin-bottom: 30px;">
        <?php wp_nonce_field('t14sf_api_settings_nonce'); ?>
        <input type="hidden" name="t14sf_save_api_settings" value="1">

        <div class="t14sf-settings-section">
            <h2>🔑 API Configuration</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="api_client_id">Client ID</label></th>
                    <td>
                        <input type="text" name="api_client_id" id="api_client_id" value="<?php echo esc_attr($api_client_id); ?>" class="regular-text" required />
                        <p class="description">Your Turn14 API Client ID.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="api_client_secret">Client Secret</label></th>
                    <td>
                        <input type="password" name="api_client_secret" id="api_client_secret" value="<?php echo esc_attr($api_client_secret); ?>" class="regular-text" required />
                        <p class="description">Your Turn14 API Client Secret.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="api_base_url">API Base URL</label></th>
                    <td>
                        <input type="url" name="api_base_url" id="api_base_url" value="<?php echo esc_attr($api_base_url); ?>" class="regular-text" required />
                        <p class="description">Base URL for Turn14 API (e.g., https://apitest.turn14.com).</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="api_currency_rate">Currency Rate</label></th>
                    <td>
                        <input type="number" name="api_currency_rate" id="api_currency_rate" value="<?php echo esc_attr($api_currency_rate); ?>" step="0.001" min="0" class="small-text" required />
                        <p class="description">Exchange rate for currency conversion.</p>
                    </td>
                </tr>
            </table>

            <p class="submit" style="display: flex; gap: 10px; align-items: center;">
                <button type="submit" name="t14sf_save_api_settings" class="button button-primary">💾 Save API Settings</button>
                <button type="button" id="t14sf_test_connection" class="button button-secondary">🔌 Test Connection</button>
                <span id="t14sf_test_result" style="margin-left: 10px;"></span>
            </p>
        </div>
    </form>

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

        <p class="submit">
            <button type="submit" name="t14sf_save_settings" class="button button-primary button-large">💾 Save Settings</button>
        </p>
    </form>

    <!-- Shipping Settings Section -->
    <form method="post" class="t14sf-settings-form">
        <?php wp_nonce_field('t14sf_shipping_settings_nonce'); ?>
        <input type="hidden" name="t14sf_save_shipping_settings" value="1">

        <div class="t14sf-settings-section">
            <h2>🚚 Shipping Settings</h2>
            <p class="description">Configure how shipping is handled for local and Turn14 orders.</p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="turn14_shipping_markup">Turn14 Shipping Markup (%)</label></th>
                    <td>
                        <input type="number" id="turn14_shipping_markup" name="turn14_shipping_markup" 
                               value="<?php echo esc_attr($turn14_shipping_markup); ?>" 
                               step="0.01" min="0" class="small-text" />
                        <p class="description">Percentage markup to apply to Turn14 shipping rates (e.g., 10 for 10%)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="turn14_method_id">Turn14 Shipping Method ID</label></th>
                    <td>
                        <input type="text" id="turn14_method_id" name="turn14_method_id" 
                               value="<?php echo esc_attr($turn14_method_id); ?>" 
                               class="regular-text" />
                        <p class="description">WooCommerce shipping method ID for Turn14 rates (default: turn14_shipping)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Local Shipping Methods</label></th>
                    <td>
                        <?php
                        // Get available methods from WooCommerce zones
                        $available_methods = t14sf_get_available_shipping_methods();
                        
                        if (empty($available_methods)) {
                            echo '<p style="color: #dc3232;">⚠️ No enabled shipping methods found in your WooCommerce zones.</p>';
                            echo '<p class="description">Please go to <a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=shipping')) . '" target="_blank">WooCommerce → Settings → Shipping</a> to configure shipping zones and methods.</p>';
                        } else {
                            echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">';
                            
                            // Group by base method ID for better organization
                            $grouped = array();
                            foreach ($available_methods as $rate_id => $method) {
                                // Skip Turn14 shipping method
                                if (strpos($method['method_id'], 'turn14') !== false) {
                                    continue;
                                }
                                
                                $base_id = $method['method_id'];
                                if (!isset($grouped[$base_id])) {
                                    $grouped[$base_id] = array();
                                }
                                $grouped[$base_id][] = $method;
                            }
                            
                            foreach ($grouped as $base_id => $methods) {
                                // Get readable name for method type
                                $method_type_name = ucwords(str_replace('_', ' ', $base_id));
                                
                                echo '<div style="margin-bottom: 15px;">';
                                echo '<strong style="display: block; margin-bottom: 5px; color: #2271b1;">' . esc_html($method_type_name) . '</strong>';
                                
                                foreach ($methods as $method) {
                                    $checked = in_array($method['id'], $local_methods) ? ' checked="checked"' : '';
                                    
                                    echo '<label style="display: block; margin: 5px 0 5px 15px; padding: 5px; background: #f9f9f9;">';
                                    echo '<input type="checkbox" name="local_methods[]" value="' . esc_attr($method['id']) . '"' . $checked . '> ';
                                    echo '<strong>' . esc_html($method['title']) . '</strong>';
                                    echo ' <code style="font-size: 11px; background: #fff; padding: 2px 6px; border: 1px solid #ddd;">' . esc_html($method['id']) . '</code>';
                                    echo '<br><span style="margin-left: 20px; font-size: 12px; color: #666;">Zone: ' . esc_html($method['zone']) . '</span>';
                                    echo '</label>';
                                }
                                
                                echo '</div>';
                            }
                            
                            echo '</div>';
                        }
                        ?>
                        <p class="description" style="margin-top: 10px;">
                            💡 Select shipping methods that should be available for items fulfilled from your local warehouse. 
                            Turn14 shipping methods are automatically excluded from this list.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="enable_debug">Enable Debug Mode</label></th>
                    <td>
                        <input type="checkbox" id="enable_debug" name="enable_debug" value="1" 
                               <?php checked($enable_debug, true); ?> />
                        <p class="description">Enable detailed logging for troubleshooting shipping issues. View logs at <a href="<?php echo esc_url(admin_url('admin.php?page=t14sf-shipping-debug')); ?>">Shipping Debug</a> page.</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="t14sf_save_shipping_settings" class="button button-primary button-large">💾 Save Shipping Settings</button>
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#t14sf_test_connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $result = $('#t14sf_test_result');
        
        // Disable button and show loading
        $button.prop('disabled', true);
        $result.html('<span style="color: #666;">⏳ Testing connection...</span>');
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 't14sf_test_api_connection',
                nonce: '<?php echo wp_create_nonce('t14sf_test_api_connection'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: #46b450; font-weight: 600;">✅ ' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="color: #dc3232; font-weight: 600;">❌ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                $result.html('<span style="color: #dc3232; font-weight: 600;">❌ Request failed. Please try again.</span>');
            },
            complete: function() {
                // Re-enable button after 2 seconds
                setTimeout(function() {
                    $button.prop('disabled', false);
                }, 2000);
            }
        });
    });
});
</script>

