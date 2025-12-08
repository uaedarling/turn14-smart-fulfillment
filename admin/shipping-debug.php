<?php
/**
 * Shipping Debug Page
 * Provides comprehensive debugging for shipping configuration and package splitting
 */

if (!defined('ABSPATH')) {
    exit;
}

class T14SF_Shipping_Debug {
    
    public static function render() {
        ?>
        <div class="wrap t14sf-dashboard">
            <h1>🔍 Shipping Debug Information</h1>
            <p class="description">
                Debug shipping configuration, zones, and package splitting behavior.
            </p>
            
            <div class="t14sf-settings-section">
                <h2>WooCommerce Shipping Zones</h2>
                <?php self::display_shipping_zones(); ?>
            </div>
            
            <div class="t14sf-settings-section">
                <h2>Turn14 Plugin Settings</h2>
                <?php self::display_plugin_settings(); ?>
            </div>
            
            <div class="t14sf-settings-section">
                <h2>Test Package Creation</h2>
                <?php self::test_package_creation(); ?>
            </div>
            
            <div class="t14sf-settings-section">
                <h2>Recent Debug Log</h2>
                <?php self::display_debug_log(); ?>
            </div>
        </div>
        <?php
    }
    
    private static function display_shipping_zones() {
        $shipping_zones = WC_Shipping_Zones::get_zones();
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Zone Name</th><th>Regions</th><th>Shipping Methods</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($shipping_zones as $zone) {
            echo '<tr>';
            echo '<td><strong>' . esc_html($zone['zone_name']) . '</strong></td>';
            echo '<td>' . esc_html(implode(', ', $zone['formatted_zone_location'])) . '</td>';
            echo '<td>';
            
            if (!empty($zone['shipping_methods'])) {
                echo '<ul style="margin:0;">';
                foreach ($zone['shipping_methods'] as $method) {
                    $enabled = $method->enabled === 'yes' ? '✅' : '❌';
                    echo '<li>' . $enabled . ' <strong>' . esc_html($method->id) . '</strong>';
                    if ($method->instance_id) {
                        echo ' (Instance: <code>' . esc_html($method->id . ':' . $method->instance_id) . '</code>)';
                    }
                    echo ' - ' . esc_html($method->title) . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<em style="color:red;">No methods configured</em>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        
        // Default/Everywhere zone
        $default_zone = new WC_Shipping_Zone(0);
        $methods = $default_zone->get_shipping_methods();
        
        if (!empty($methods)) {
            echo '<tr>';
            echo '<td><strong>Default Zone (Everywhere else)</strong></td>';
            echo '<td>Locations not covered by other zones</td>';
            echo '<td><ul style="margin:0;">';
            foreach ($methods as $method) {
                $enabled = $method->enabled === 'yes' ? '✅' : '❌';
                echo '<li>' . $enabled . ' <strong>' . esc_html($method->id) . '</strong>';
                if (isset($method->instance_id)) {
                    echo ' (Instance: <code>' . esc_html($method->id . ':' . $method->instance_id) . '</code>)';
                }
                echo ' - ' . esc_html($method->title) . '</li>';
            }
            echo '</ul></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        if (empty($shipping_zones) && empty($methods)) {
            echo '<div class="notice notice-error inline"><p><strong>⚠️ No shipping zones configured!</strong> Go to WooCommerce → Settings → Shipping to add zones.</p></div>';
        }
    }
    
    private static function display_plugin_settings() {
        $local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array());
        $turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');
        $enable_debug = Turn14_Smart_Fulfillment::get_option('enable_debug', false);
        
        echo '<table class="widefat">';
        echo '<tr><th style="width: 30%;">Setting</th><th>Value</th></tr>';
        echo '<tr><td><strong>Local Shipping Methods</strong></td><td>';
        if (!empty($local_methods)) {
            echo '<ul style="margin:0;">';
            foreach ($local_methods as $method) {
                echo '<li><code>' . esc_html($method) . '</code></li>';
            }
            echo '</ul>';
        } else {
            echo '<em style="color:red;">None selected</em>';
        }
        echo '</td></tr>';
        echo '<tr><td><strong>Turn14 Method ID</strong></td><td><code>' . esc_html($turn14_method_id) . '</code></td></tr>';
        echo '<tr><td><strong>Debug Mode</strong></td><td>' . ($enable_debug ? '✅ Enabled' : '❌ Disabled') . '</td></tr>';
        echo '</table>';
        
        echo '<p class="description" style="margin-top: 15px;">💡 <strong>Tip:</strong> These settings control which shipping methods are available for local vs Turn14 packages. Make sure the configured method IDs match the actual WooCommerce shipping method IDs shown above (including instance IDs like <code>flat_rate:1</code>).</p>';
    }
    
    private static function test_package_creation() {
        echo '<p>Add items to cart and view this page to see package splitting in action.</p>';
        
        if (WC()->cart && !WC()->cart->is_empty()) {
            echo '<h3>Current Cart Packages:</h3>';
            $packages = WC()->cart->get_shipping_packages();
            
            if (empty($packages)) {
                echo '<div class="notice notice-warning inline"><p>No packages found. Cart may not have shipping enabled.</p></div>';
            } else {
                foreach ($packages as $i => $package) {
                    echo '<div style="border:1px solid #ccc; padding:15px; margin:10px 0; background: #f9f9f9; border-radius: 4px;">';
                    echo '<h4 style="margin-top: 0;">Package ' . ($i + 1) . '</h4>';
                    echo '<table class="widefat" style="background: white;">';
                    echo '<tr><td style="width: 150px;"><strong>Type:</strong></td><td>' . (isset($package['t14sf_type']) ? '<code>' . esc_html($package['t14sf_type']) . '</code>' : '<em style="color: #999;">not set</em>') . '</td></tr>';
                    echo '<tr><td><strong>Label:</strong></td><td>' . (isset($package['t14sf_label']) ? esc_html($package['t14sf_label']) : '<em style="color: #999;">not set</em>') . '</td></tr>';
                    echo '<tr><td><strong>Items:</strong></td><td>' . count($package['contents']) . '</td></tr>';
                    echo '<tr><td><strong>Contents Cost:</strong></td><td>' . wc_price($package['contents_cost']) . '</td></tr>';
                    echo '</table>';
                    
                    if (!empty($package['contents'])) {
                        echo '<h5 style="margin-bottom: 8px;">Items in Package:</h5>';
                        echo '<ul style="margin: 0;">';
                        foreach ($package['contents'] as $item) {
                            echo '<li>' . esc_html($item['data']->get_name()) . ' (Qty: ' . $item['quantity'] . ')</li>';
                        }
                        echo '</ul>';
                    }
                    
                    echo '</div>';
                }
            }
        } else {
            echo '<p><em>Cart is empty. Add products to test package splitting.</em></p>';
        }
    }
    
    private static function display_debug_log() {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            echo '<div class="notice notice-info inline"><p>Debug mode is not enabled. To enable logging, add these lines to your <code>wp-config.php</code>:</p>';
            echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">define(\'WP_DEBUG\', true);\ndefine(\'WP_DEBUG_LOG\', true);\ndefine(\'WP_DEBUG_DISPLAY\', false);</pre></div>';
        }
        
        if (!file_exists($log_file)) {
            echo '<p><em>No debug.log file found. Enable WP_DEBUG_LOG in wp-config.php to start logging.</em></p>';
            return;
        }
        
        $log_content = file_get_contents($log_file);
        $lines = explode("\n", $log_content);
        $t14sf_lines = array_filter($lines, function($line) {
            return strpos($line, 'T14SF:') !== false || strpos($line, 'Turn14 SF:') !== false;
        });
        
        $recent_lines = array_slice(array_reverse($t14sf_lines), 0, 50);
        
        if (empty($recent_lines)) {
            echo '<p><em>No Turn14 plugin debug messages found in the log.</em></p>';
        } else {
            echo '<p>Showing last 50 Turn14-related log entries:</p>';
            echo '<pre style="background:#f5f5f5; padding:10px; overflow:auto; max-height:400px; border: 1px solid #ddd; border-radius: 4px;">';
            echo esc_html(implode("\n", $recent_lines));
            echo '</pre>';
        }
    }
}
