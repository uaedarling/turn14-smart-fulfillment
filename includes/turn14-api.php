<?php
/**
 * Turn14 API client
 */

if (!defined('ABSPATH')) {
    exit;
}

class Turn14_API_Client {

    private $endpoint;
    private $api_key;
    private $timeout = 20;

    public function __construct() {
        $this->endpoint = Turn14_Smart_Fulfillment::get_option('turn14_api_endpoint', '');
        $this->api_key = Turn14_Smart_Fulfillment::get_option('turn14_api_key', '');
        $timeout = Turn14_Smart_Fulfillment::get_option('turn14_api_timeout', 20);
        $this->timeout = $timeout ? intval($timeout) : 20;
    }

    /**
     * Get shipping rates from Turn14 API for a package
     * Returns an array of rates: array(array('carrier'=>'UPS','service'=>'Ground','price'=>12.34), ...)
     */
    public function get_rates_for_package($package) {
        // Build payload: include items (product id, qty, weight), destination
        $items = array();
        foreach ($package['contents'] as $cart_item) {
            $product = $cart_item['data'];
            $items[] = array(
                'product_id' => $product->get_id(),
                'quantity'   => isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 1,
                'weight'     => floatval($product->get_weight() ? $product->get_weight() : 0),
                'length'     => floatval($product->get_length() ? $product->get_length() : 0),
                'width'      => floatval($product->get_width() ? $product->get_width() : 0),
                'height'     => floatval($product->get_height() ? $product->get_height() : 0),
            );
        }

        $destination = isset($package['destination']) ? $package['destination'] : array();

        $payload = array(
            'items' => $items,
            'destination' => array(
                'country' => isset($destination['country']) ? $destination['country'] : '',
                'state'   => isset($destination['state']) ? $destination['state'] : '',
                'postcode'=> isset($destination['postcode']) ? $destination['postcode'] : '',
                'city'    => isset($destination['city']) ? $destination['city'] : '',
            ),
            'currency' => get_woocommerce_currency(),
        );

        $response = $this->post('/shipping/rates', $payload);

        if (!is_array($response) || empty($response['rates'])) {
            return array();
        }

        // Expecting response['rates'] = array of ['carrier','service','price']
        $rates = array();
        foreach ($response['rates'] as $r) {
            if (!isset($r['price'])) {
                continue;
            }
            $rates[] = array(
                'carrier' => isset($r['carrier']) ? $r['carrier'] : '',
                'service' => isset($r['service']) ? $r['service'] : '',
                'price'   => floatval($r['price']),
                'meta'    => isset($r['meta']) ? $r['meta'] : array(),
            );
        }

        return $rates;
    }

    /**
     * POST helper
     */
    private function post($path, $data) {
        if (empty($this->endpoint)) {
            return array();
        }

        $url = rtrim($this->endpoint, '/') . $path;

        $args = array(
            'method'    => 'POST',
            'timeout'   => $this->timeout,
            'headers'   => array(
                'Content-Type' => 'application/json',
            ),
            'body'      => wp_json_encode($data),
        );

        if (!empty($this->api_key)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->api_key;
        }

        $resp = wp_remote_post($url, $args);

        if (is_wp_error($resp)) {
            error_log('Turn14 API request error: ' . $resp->get_error_message());
            return array();
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);

        if ($code < 200 || $code >= 300) {
            error_log('Turn14 API non-200 response (' . $code . '): ' . $body);
            return array();
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Turn14 API JSON decode error: ' . json_last_error_msg());
            return array();
        }

        return $decoded;
    }
}
