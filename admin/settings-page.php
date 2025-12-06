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
            
            if ($local_stock > $threshold) {
                $local_items[$cart_key] = $cart_item;
            } else {
                $turn14_items[$cart_key] = $cart_item;
            }
        }
        
        if (empty($local_items) || empty($turn14_items)) {
            if (! empty($turn14_items)) {
                $packages[0]['t14sf_type'] = 'turn14';
            } else {
                $packages[0]['t14sf_type'] = 'local';
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
        );
        
        $new_packages[] = array(
            'contents' => $turn14_items,
            'contents_cost' => array_sum(wp_list_pluck($turn14_items, 'line_total')),
            'applied_coupons' => isset($base['applied_coupons']) ? $base['applied_coupons'] : array(),
            'user' => isset($base['user']) ? $base['user'] : array(),
            'destination' => isset($base['destination']) ? $base['destination'] : array(),
            't14sf_type' => 'turn14',
        );
        
        return $new_packages;
    }
    
    public function filter_rates_by_package($rates, $package) {
        $package_type = isset($package['t14sf_type']) ? $package['t14sf_type'] : 'local';
        
        $turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');
        $local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array());
        
        // If no local methods configured, fallback to original behavior (allow all)
        // or you might want to return empty array() to block checkout if settings are missing.
        if (empty($local_methods) && $package_type === 'local') {
             return $rates; 
        }
        
        $filtered = array();
        
        foreach ($rates as $rate_id => $rate) {
            // Safe method ID retrieval
            $method_id = method_exists($rate, 'get_method_id') ? $rate->get_method_id() : (isset($rate->method_id) ? $rate->method_id : '');

            if ($package_type === 'local') {
                // FIX: Only allow methods specifically checked in settings
                // We use in_array to check against the saved list
                if (in_array($method_id, $local_methods)) {
                    $filtered[$rate_id] = $rate;
                }
            } else {
                // Turn14 package: keep ONLY the Turn14 method
                if ($method_id === $turn14_method_id) {
                    $filtered[$rate_id] = $rate;
                }
            }
        }
        
        return $filtered;
    }
    
    public function custom_package_name($name, $i, $package) {
        $type = isset($package['t14sf_type']) ? $package['t14sf_type'] : 'local';
        
        if ($type === 'local') {
            return 'Local Stock Items';
        } else {
            return 'Drop-Ship Items';
        }
    }
}

new T14SF_Shipping_Splitter();
