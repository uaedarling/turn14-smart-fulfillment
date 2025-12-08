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
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            ?>
            <div class="wrap t14sf-dashboard">
                <h1>🔍 Shipping Debug Information</h1>
                <div class="notice notice-error inline">
                    <p><strong>⚠️ WooCommerce is not active!</strong> This debug page requires WooCommerce to be installed and activated.</p>
                </div>
            </div>
            <?php
            return;
        }
        
        ?>
        <div class="wrap t14sf-dashboard">
            <h1>🔍 Shipping Debug Information</h1>
            <p class="description">
                Debug shipping configuration, zones, and package splitting behavior.
            </p>
            
            <div class="t14sf-settings-section">
                <h2>WooCommerce Shipping Zones</h2>
                <?php 
                try {
                    self::display_shipping_zones();
                } catch (Exception $e) {
                    echo '<div class="notice notice-error inline"><p><strong>Error displaying shipping zones:</strong> ' . esc_html($e->getMessage()) . '</p>';
                    echo '<p><em>File: ' . esc_html($e->getFile()) . ' Line: ' . esc_html($e->getLine()) . '</em></p></div>';
                    error_log('T14SF Shipping Debug - display_shipping_zones error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                }
                ?>
            </div>
            
            <div class="t14sf-settings-section">
                <h2>Turn14 Plugin Settings</h2>
                <?php 
                try {
                    self::display_plugin_settings();
                } catch (Exception $e) {
                    echo '<div class="notice notice-error inline"><p><strong>Error displaying plugin settings:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
                    error_log('T14SF Shipping Debug - display_plugin_settings error: ' . $e->getMessage());
                }
                ?>
            </div>
            
            <div class="t14sf-settings-section">
                <h2>Address Zone Matching</h2>
                <p class="description">Test if customer addresses match your configured zones.</p>
                <?php 
                try {
                    self::test_address_zone_matching(); 
                } catch (Exception $e) {
                    echo '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
                }
                ?>
            </div>
            
            <div class="t14sf-settings-section">
                <h2>Test Package Creation</h2>
                <?php 
                try {
                    self::test_package_creation(); 
                } catch (Exception $e) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>Critical error in package testing:</strong> ' . esc_html($e->getMessage());
                    echo '</p></div>';
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        echo '<details><summary>Stack Trace</summary><pre>';
                        echo esc_html($e->getTraceAsString());
                        echo '</pre></details>';
                    }
                } catch (Error $e) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>PHP Error in package testing:</strong> ' . esc_html($e->getMessage());
                    echo '</p></div>';
                }
                ?>
            </div>
            
            <div class="t14sf-settings-section">
                <h2>Recent Debug Log</h2>
                <?php 
                try {
                    self::display_debug_log();
                } catch (Exception $e) {
                    echo '<div class="notice notice-error inline"><p><strong>Error displaying debug log:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
                    error_log('T14SF Shipping Debug - display_debug_log error: ' . $e->getMessage());
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private static function display_shipping_zones() {
        // Check if WC_Shipping_Zones class exists
        if (!class_exists('WC_Shipping_Zones')) {
            echo '<div class="notice notice-error inline"><p><strong>⚠️ WC_Shipping_Zones class not found!</strong> Make sure WooCommerce is properly installed and activated.</p></div>';
            error_log('T14SF: WC_Shipping_Zones class does not exist');
            return;
        }
        
        try {
            $shipping_zones = WC_Shipping_Zones::get_zones();
            
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Zone Name</th><th>Regions</th><th>Shipping Methods</th></tr></thead>';
            echo '<tbody>';
            
            // Process each zone with proper error checking
            if (is_array($shipping_zones)) {
                foreach ($shipping_zones as $zone) {
                    try {
                        echo '<tr>';
                        
                        // Zone name with fallback
                        $zone_name = isset($zone['zone_name']) ? $zone['zone_name'] : 'Unknown Zone';
                        echo '<td><strong>' . esc_html($zone_name) . '</strong></td>';
                        
                        // Zone locations with validation
                        echo '<td>';
                        if (isset($zone['formatted_zone_location']) && is_array($zone['formatted_zone_location'])) {
                            echo esc_html(implode(', ', $zone['formatted_zone_location']));
                        } elseif (isset($zone['zone_locations']) && is_array($zone['zone_locations'])) {
                            // Fallback to zone_locations if formatted_zone_location not available
                            $locations = array();
                            foreach ($zone['zone_locations'] as $location) {
                                if (isset($location->code)) {
                                    $locations[] = $location->code;
                                }
                            }
                            echo esc_html(implode(', ', $locations));
                        } else {
                            echo '<em style="color: #999;">No locations specified</em>';
                        }
                        echo '</td>';
                        
                        // Shipping methods with validation
                        echo '<td>';
                        if (isset($zone['shipping_methods']) && is_array($zone['shipping_methods']) && !empty($zone['shipping_methods'])) {
                            echo '<ul style="margin:0;">';
                            foreach ($zone['shipping_methods'] as $method) {
                                // Validate method is an object
                                if (!is_object($method)) {
                                    continue;
                                }
                                
                                // Safely get enabled status
                                $enabled = '❌';
                                if (isset($method->enabled)) {
                                    $enabled = $method->enabled === 'yes' ? '✅' : '❌';
                                }
                                
                                // Safely get method ID
                                $method_id = isset($method->id) ? $method->id : 'unknown';
                                
                                echo '<li>' . $enabled . ' <strong>' . esc_html($method_id) . '</strong>';
                                
                                // Safely get instance ID
                                if (isset($method->instance_id) && $method->instance_id) {
                                    echo ' (Instance: <code>' . esc_html($method_id . ':' . $method->instance_id) . '</code>)';
                                }
                                
                                // Safely get title
                                $title = isset($method->title) ? $method->title : 'Unknown Method';
                                echo ' - ' . esc_html($title) . '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<em style="color:red;">No methods configured</em>';
                        }
                        
                        echo '</td>';
                        echo '</tr>';
                    } catch (Exception $e) {
                        echo '<tr><td colspan="3"><div class="notice notice-warning inline" style="margin: 5px 0;"><p><strong>Error processing zone:</strong> ' . esc_html($e->getMessage()) . '</p></div></td></tr>';
                        error_log('T14SF: Error processing shipping zone - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
                    }
                }
            }
            
            // Default/Everywhere zone with error handling
            try {
                if (class_exists('WC_Shipping_Zone')) {
                    $default_zone = new WC_Shipping_Zone(0);
                    $methods = $default_zone->get_shipping_methods();
                    
                    if (is_array($methods) && !empty($methods)) {
                        echo '<tr>';
                        echo '<td><strong>Default Zone (Everywhere else)</strong></td>';
                        echo '<td>Locations not covered by other zones</td>';
                        echo '<td><ul style="margin:0;">';
                        
                        foreach ($methods as $method) {
                            // Validate method is an object
                            if (!is_object($method)) {
                                continue;
                            }
                            
                            // Safely get enabled status
                            $enabled = '❌';
                            if (isset($method->enabled)) {
                                $enabled = $method->enabled === 'yes' ? '✅' : '❌';
                            }
                            
                            // Safely get method ID
                            $method_id = isset($method->id) ? $method->id : 'unknown';
                            
                            echo '<li>' . $enabled . ' <strong>' . esc_html($method_id) . '</strong>';
                            
                            // Safely get instance ID
                            if (isset($method->instance_id) && $method->instance_id) {
                                echo ' (Instance: <code>' . esc_html($method_id . ':' . $method->instance_id) . '</code>)';
                            }
                            
                            // Safely get title
                            $title = isset($method->title) ? $method->title : 'Unknown Method';
                            echo ' - ' . esc_html($title) . '</li>';
                        }
                        
                        echo '</ul></td>';
                        echo '</tr>';
                    }
                }
            } catch (Exception $e) {
                echo '<tr><td colspan="3"><div class="notice notice-warning inline" style="margin: 5px 0;"><p><strong>Error processing default zone:</strong> ' . esc_html($e->getMessage()) . '</p></div></td></tr>';
                error_log('T14SF: Error processing default shipping zone - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            
            echo '</tbody></table>';
            
            // Check if we have any zones at all
            $has_zones = !empty($shipping_zones);
            $has_default_methods = false;
            
            try {
                if (class_exists('WC_Shipping_Zone')) {
                    $default_zone = new WC_Shipping_Zone(0);
                    $methods = $default_zone->get_shipping_methods();
                    $has_default_methods = !empty($methods);
                }
            } catch (Exception $e) {
                // Silently fail for this non-critical check
                // We're just determining if the "no zones" message should display
                error_log('T14SF: Error checking default zone for display decision - ' . $e->getMessage());
            }
            
            if (!$has_zones && !$has_default_methods) {
                echo '<div class="notice notice-error inline"><p><strong>⚠️ No shipping zones configured!</strong> Go to WooCommerce → Settings → Shipping to add zones.</p></div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error inline"><p><strong>⚠️ Critical error loading shipping zones:</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '<p><em>File: ' . esc_html($e->getFile()) . ' Line: ' . esc_html($e->getLine()) . '</em></p></div>';
            error_log('T14SF: Critical error in display_shipping_zones - ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
    
    private static function test_address_zone_matching() {
        echo '<h3>Address Zone Matching Test</h3>';
        
        if (!class_exists('WC_Shipping_Zones')) {
            echo '<p>WooCommerce Shipping Zones not available.</p>';
            return;
        }
        
        // Get customer address if available
        $test_address = array(
            'country' => 'AE',
            'state' => '',
            'postcode' => 'AUH358223',
            'city' => 'Abu Dhabi',
        );
        
        echo '<p><strong>Testing address:</strong> Abu Dhabi, AUH358223, AE</p>';
        
        try {
            $matched_zone = WC_Shipping_Zones::get_zone_matching_package($test_address);
            
            if ($matched_zone) {
                echo '<div style="background:#d4edda; border:1px solid #c3e6cb; padding:10px; border-radius:4px;">';
                echo '✅ <strong>Address matches zone:</strong> ' . esc_html($matched_zone->get_zone_name());
                echo ' (Zone ID: ' . $matched_zone->get_id() . ')';
                
                // Show methods in this zone
                $methods = $matched_zone->get_shipping_methods(true); // true = enabled only
                if (!empty($methods)) {
                    echo '<br><strong>Available methods in this zone:</strong><ul style="margin:5px 0;">';
                    foreach ($methods as $method) {
                        echo '<li>' . esc_html($method->id . ':' . $method->instance_id) . ' - ' . esc_html($method->title) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<br><span style="color:red;">⚠️ No shipping methods enabled in this zone!</span>';
                }
                
                echo '</div>';
            } else {
                echo '<div style="background:#f8d7da; border:1px solid #f5c6cb; padding:10px; border-radius:4px;">';
                echo '❌ <strong>Address does not match any zone!</strong><br>';
                echo 'This is why "No shipping options" appears. The address needs to match a zone.';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Error testing zone match: ' . esc_html($e->getMessage()) . '</p></div>';
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
        
        try {
            // Check if WooCommerce function exists
            if (!function_exists('WC')) {
                echo '<div class="notice notice-error"><p>WooCommerce is not available.</p></div>';
                return;
            }
            
            // Try to get WooCommerce instance
            $wc = WC();
            if (!$wc) {
                echo '<div class="notice notice-error"><p>WooCommerce instance not available.</p></div>';
                return;
            }
            
            // Check if cart is available
            if (!isset($wc->cart) || !is_object($wc->cart)) {
                echo '<div class="notice notice-warning"><p>';
                echo 'WooCommerce cart is not initialized. This is normal on admin pages.<br>';
                echo 'To test package splitting: Go to your store front, add items to cart, then return here.';
                echo '</p></div>';
                return;
            }
            
            // Check if cart has required methods
            if (!method_exists($wc->cart, 'is_empty')) {
                echo '<div class="notice notice-error"><p>Cart object exists but is missing required methods.</p></div>';
                return;
            }
            
            // Check if cart is empty
            if ($wc->cart->is_empty()) {
                echo '<p><em>Cart is currently empty. Add products to your cart to see package splitting.</em></p>';
                return;
            }
            
            echo '<div class="notice notice-info"><p>';
            echo '✅ Cart has ' . $wc->cart->get_cart_contents_count() . ' item(s). ';
            echo 'Package splitting info:';
            echo '</p></div>';
            
            // Try to get packages
            if (!method_exists($wc->cart, 'get_shipping_packages')) {
                echo '<p><em>Cart exists but shipping packages method not available.</em></p>';
                return;
            }
            
            $packages = $wc->cart->get_shipping_packages();
            
            if (empty($packages)) {
                echo '<p><em>No packages generated yet. Try proceeding to checkout to trigger package creation.</em></p>';
                return;
            }
            
            echo '<h3>Current Cart Packages:</h3>';
            
            foreach ($packages as $i => $package) {
                echo '<div style="border:1px solid #ccc; padding:15px; margin:10px 0; background:#f9f9f9; border-radius:4px;">';
                echo '<h4 style="margin-top:0;">📦 Package ' . ($i + 1) . '</h4>';
                
                // Package type
                $type = isset($package['t14sf_type']) ? $package['t14sf_type'] : 'not set';
                $type_color = ($type === 'not set') ? 'red' : ($type === 'local' ? 'green' : 'blue');
                echo '<p><strong>Type:</strong> <span style="color:' . esc_attr($type_color) . '; font-weight:bold;">' . esc_html($type) . '</span></p>';
                
                // Package label
                $label = isset($package['t14sf_label']) ? $package['t14sf_label'] : 'not set';
                echo '<p><strong>Label:</strong> ' . esc_html($label) . '</p>';
                
                // Item count
                $item_count = isset($package['contents']) ? count($package['contents']) : 0;
                echo '<p><strong>Items:</strong> ' . $item_count . '</p>';
                
                // Show items if available
                if (!empty($package['contents'])) {
                    echo '<details style="margin-top:10px;"><summary style="cursor:pointer; font-weight:bold;">View Items</summary>';
                    echo '<ul style="font-size:12px; margin:10px 0;">';
                    
                    foreach ($package['contents'] as $item) {
                        if (isset($item['data']) && is_object($item['data'])) {
                            $product = $item['data'];
                            $product_name = method_exists($product, 'get_name') ? $product->get_name() : 'Unknown Product';
                            $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
                            
                            echo '<li>' . esc_html($product_name) . ' (Qty: ' . $quantity . ')</li>';
                        }
                    }
                    
                    echo '</ul></details>';
                }
                
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Error displaying cart packages:</strong><br>';
            echo esc_html($e->getMessage()) . '<br>';
            echo '<small>File: ' . esc_html($e->getFile()) . ' | Line: ' . $e->getLine() . '</small>';
            echo '</p></div>';
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<pre style="background:#f5f5f5; padding:10px; font-size:11px; overflow:auto;">';
                echo esc_html($e->getTraceAsString());
                echo '</pre>';
            }
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
        
        // Check if file is readable
        if (!is_readable($log_file)) {
            echo '<div class="notice notice-error inline"><p><strong>⚠️ Cannot read debug.log file!</strong> Check file permissions.</p></div>';
            error_log('T14SF: debug.log exists but is not readable');
            return;
        }
        
        try {
            // Check file size before reading
            $file_size = filesize($log_file);
            if ($file_size === false) {
                throw new Exception('Cannot determine file size');
            }
            
            if ($file_size > 5 * 1024 * 1024) { // 5MB
                echo '<div class="notice notice-warning inline"><p>Debug log file is very large (' . size_format($file_size) . '). Showing only recent Turn14 entries.</p></div>';
            }
            
            // Read file more efficiently for large files
            $t14sf_lines = array();
            $handle = fopen($log_file, 'r');
            
            if ($handle === false) {
                throw new Exception('Cannot open debug.log file');
            }
            
            while (($line = fgets($handle)) !== false) {
                if (strpos($line, 'T14SF:') !== false || strpos($line, 'Turn14 SF:') !== false) {
                    $t14sf_lines[] = $line;
                }
            }
            fclose($handle);
            
            // Get last 50 entries without reversing entire array first
            $recent_lines = array_slice($t14sf_lines, -50);
            $recent_lines = array_reverse($recent_lines);
            
            if (empty($recent_lines)) {
                echo '<p><em>No Turn14 plugin debug messages found in the log.</em></p>';
            } else {
                echo '<p>Showing last 50 Turn14-related log entries:</p>';
                echo '<pre style="background:#f5f5f5; padding:10px; overflow:auto; max-height:400px; border: 1px solid #ddd; border-radius: 4px;">';
                echo esc_html(implode('', $recent_lines));
                echo '</pre>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error inline"><p><strong>Error reading debug log:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
            error_log('T14SF: Error reading debug.log - ' . $e->getMessage());
        }
    }
}
