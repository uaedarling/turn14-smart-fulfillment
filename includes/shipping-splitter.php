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
    
    public function filter_rates_by_package($rates, $package) {
        $package_type = isset($package['t14sf_type']) ? $package['t14sf_type'] : 'local';
        
        // Log what rates are available
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('T14SF: Available rates for package type: ' . $package_type);
            error_log('T14SF: Rate IDs: ' . implode(', ', array_keys($rates)));
        }
        
        $turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');
        $local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));
        
        // Safety check to ensure array
        if (!is_array($local_methods)) {
            $local_methods = (array) $local_methods;
        }
        
        $filtered = array();
        
        foreach ($rates as $rate_id => $rate) {
            $method_id = method_exists($rate, 'get_method_id') ? $rate->get_method_id() : (isset($rate->method_id) ? $rate->method_id : '');

            if ($package_type === 'local') {
                // For local packages, only include rates from configured local methods
                // Use helper to check both simple and instance-based IDs
                if ($this->is_local_shipping_method($rate_id)) {
                    $filtered[$rate_id] = $rate;
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('T14SF: Excluded non-local method from local package: ' . $rate_id);
                    }
                }
            } else {
                // For Turn14 packages, remove local methods
                $is_local_method = $this->is_local_shipping_method($rate_id);
                
                if (!$is_local_method && (strpos($rate_id, $turn14_method_id) === 0 || $method_id === $turn14_method_id)) {
                    // Include Turn14 shipping method
                    $filtered[$rate_id] = $rate;
                } elseif ($is_local_method) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('T14SF: Removed local method from Turn14 package: ' . $rate_id);
                    }
                }
            }
        }
        
        // Log final available rates
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('T14SF: Final rates count: ' . count($filtered));
            if (empty($filtered)) {
                error_log('T14SF: WARNING - No shipping rates available after filtering!');
            }
        }
        
        // Return filtered rates (may be empty if no matching methods found)
        return $filtered;
    }
    
    /**
     * Check if a rate ID matches configured local shipping methods
     * Handles both simple IDs and instance IDs
     */
    private function is_local_shipping_method($rate_id) {
        $local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));
        
        foreach ($local_methods as $method) {
            // Match exact ID or instance-based ID (e.g., flat_rate:1, flat_rate:2)
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
