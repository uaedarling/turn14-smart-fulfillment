<?php
/**
 * Shipping Splitter
 * Splits cart into local and Turn14 packages
 */

if (!defined('ABSPATH')) {
    exit;
}

class T14SF_Shipping_Splitter {
    
    public function __construct() {
        add_filter('woocommerce_cart_shipping_packages', array($this, 'split_packages'), 10, 1);
        add_filter('woocommerce_package_rates', array($this, 'filter_rates_by_package'), 10, 2);
        add_filter('woocommerce_shipping_package_name', array($this, 'custom_package_name'), 10, 3);
        
        // Add zone matching debug
        add_action('woocommerce_shipping_zone_before_methods', array($this, 'log_zone_match'), 10, 1);
    }
    
    public function split_packages($packages) {
        if (empty(WC()->cart)) {
            return $packages;
        }
        
        $local_items = array();
        $turn14_items = array();
        $threshold = intval(Turn14_Smart_Fulfillment::get_option('stock_threshold', 0));
        
        foreach (WC()->cart->get_cart() as $cart_key => $cart_item) {
            $product = $cart_item['data'];
            $product_id = $product->get_id();
            
            $local_stock = get_post_meta($product_id, '_stock', true);
            $local_stock = ($local_stock === '') ? 0 : intval($local_stock);
            
            $turn14_stock = get_post_meta($product_id, '_turn14_stock', true);
            $turn14_stock = ($turn14_stock === '') ? 0 : intval($turn14_stock);
            
            $quantity = $cart_item['quantity'];
            
            // Split based on which source can fulfill
            if ($local_stock > $threshold && $local_stock >= $quantity) {
                $local_items[$cart_key] = $cart_item;
            } elseif ($turn14_stock >= $quantity) {
                $turn14_items[$cart_key] = $cart_item;
            } else {
                // Default to local if both are 0 (backorder case)
                $local_items[$cart_key] = $cart_item;
            }
        }
        
        if (empty($local_items) || empty($turn14_items)) {
            if (! empty($turn14_items)) {
                $packages[0]['t14sf_type'] = 'turn14';
                $packages[0]['t14sf_label'] = 'Turn14 Drop-Ship';
            } else {
                $packages[0]['t14sf_type'] = 'local';
                $packages[0]['t14sf_label'] = 'Local Warehouse Shipping';
            }
            return $packages;
        }
        
        $base = reset($packages);
        
        $new_packages = array();
        
        $new_packages[] = array(
            'contents' => $local_items,
            'contents_cost' => array_sum(wp_list_pluck($local_items, 'line_total')),
            'applied_coupons' => isset($base['applied_coupons']) ? $base['applied_coupons'] : array(),
            'user' => isset($base['user']) ? $base['user'] : array(),
            'destination' => isset($base['destination']) ? $base['destination'] : array(),
            't14sf_type' => 'local',
            't14sf_label' => 'Local Warehouse Shipping',
        );
        
        $new_packages[] = array(
            'contents' => $turn14_items,
            'contents_cost' => array_sum(wp_list_pluck($turn14_items, 'line_total')),
            'applied_coupons' => isset($base['applied_coupons']) ? $base['applied_coupons'] : array(),
            'user' => isset($base['user']) ? $base['user'] : array(),
            'destination' => isset($base['destination']) ? $base['destination'] : array(),
            't14sf_type' => 'turn14',
            't14sf_label' => 'Turn14 Drop-Ship',
        );
        
        return $new_packages;
    }
    
    /**
     * Log which zone is being used for shipping calculation
     */
    public function log_zone_match($zone) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (is_object($zone) && method_exists($zone, 'get_zone_name')) {
                error_log('Turn14 SF: Zone matched: ' . $zone->get_zone_name() . ' (ID: ' . $zone->get_id() . ')');
            }
        }
    }
    
    public function filter_rates_by_package($rates, $package) {
        // Get package type (default to 'local' for backward compatibility)
        $package_type = isset($package['t14sf_type']) ? $package['t14sf_type'] : 'local';
        
        // Log package destination details
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Turn14 SF: === SHIPPING FILTER START ===');
            error_log('Turn14 SF: Package type: ' . $package_type);
            error_log('Turn14 SF: Rate count before filter: ' . count($rates));
            error_log('Turn14 SF: Rate IDs: ' . implode(', ', array_keys($rates)));
            
            // Log destination
            if (isset($package['destination'])) {
                $dest = $package['destination'];
                error_log('Turn14 SF: Destination - Country: ' . ($dest['country'] ?? 'none') . 
                         ', State: ' . ($dest['state'] ?? 'none') . 
                         ', Postcode: ' . ($dest['postcode'] ?? 'none') . 
                         ', City: ' . ($dest['city'] ?? 'none'));
            }
        }
        
        // For LOCAL packages, KEEP local methods and REMOVE Turn14 shipping
        if ($package_type === 'local') {
            foreach ($rates as $rate_id => $rate) {
                // Remove ONLY Turn14 shipping from local packages
                if ($this->is_turn14_method($rate_id)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Turn14 SF: Removing Turn14 method from local package: ' . $rate_id);
                    }
                    unset($rates[$rate_id]);
                }
            }
        }
        
        // For TURN14 packages, KEEP Turn14 shipping and REMOVE local methods
        elseif ($package_type === 'turn14') {
            foreach ($rates as $rate_id => $rate) {
                // Remove local methods from Turn14 packages
                if ($this->is_local_method($rate_id)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('Turn14 SF: Removing local method from Turn14 package: ' . $rate_id);
                    }
                    unset($rates[$rate_id]);
                }
            }
        }
        
        // Log after filtering
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Turn14 SF: Rate count after filter: ' . count($rates));
            
            if (empty($rates)) {
                error_log('Turn14 SF: ⚠️ WARNING - No rates left after filtering!');
                error_log('Turn14 SF: This means WooCommerce provided rates but they were all filtered out.');
                if ($package_type === 'local') {
                    error_log('Turn14 SF: LOCAL package had only Turn14 methods. Need local methods (free_shipping, flat_rate, local_pickup) in the matched zone!');
                } elseif ($package_type === 'turn14') {
                    error_log('Turn14 SF: TURN14 package had only local methods. Need Turn14 shipping method in the matched zone!');
                }
            } else {
                error_log('Turn14 SF: ✅ Final rate IDs: ' . implode(', ', array_keys($rates)));
            }
            
            error_log('Turn14 SF: === SHIPPING FILTER END ===');
        }
        
        // Fallback mechanism for empty local package rates
        if (empty($rates) && $package_type === 'local') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Turn14 SF: No local methods available, customer will see "no shipping options"');
                error_log('Turn14 SF: Check that zones covering this destination have local shipping methods enabled');
            }
            
            // Optional: Add admin notice (only once per session)
            if (is_admin() && current_user_can('manage_woocommerce')) {
                $transient_key = 't14sf_no_local_methods_notice';
                if (false === get_transient($transient_key)) {
                    set_transient($transient_key, true, HOUR_IN_SECONDS);
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-warning"><p>';
                        echo '<strong>Turn14 SF Warning:</strong> Local package has no shipping options. ';
                        echo 'Ensure zones covering customer locations have free_shipping, flat_rate, or local_pickup enabled.';
                        echo '</p></div>';
                    });
                }
            }
        }
        
        return $rates;
    }
    
    /**
     * Check if rate is a Turn14 shipping method
     */
    private function is_turn14_method($rate_id) {
        $turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');
        
        // Match turn14_shipping or turn14_shipping:2
        return ($rate_id === $turn14_method_id || strpos($rate_id, $turn14_method_id . ':') === 0);
    }
    
    /**
     * Check if rate is a local shipping method
     */
    private function is_local_method($rate_id) {
        $local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));
        
        foreach ($local_methods as $method) {
            // Match exact ID or instance ID (e.g., free_shipping:3)
            if ($rate_id === $method || strpos($rate_id, $method . ':') === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    public function custom_package_name($name, $i, $package) {
        if (isset($package['t14sf_label'])) {
            return $package['t14sf_label'];
        }
        
        $type = isset($package['t14sf_type']) ? $package['t14sf_type'] : 'local';
        
        if ($type === 'local') {
            return 'Local Warehouse Shipping';
        } else {
            return 'Turn14 Drop-Ship';
        }
    }
}

new T14SF_Shipping_Splitter();
