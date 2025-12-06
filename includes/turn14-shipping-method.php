<?php
/**
 * Turn14 Shipping Method for WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class T14SF_Turn14_Shipping extends WC_Shipping_Method {

    private $api_client;

    public function __construct($instance_id = 0) {
        $this->id                 = 'turn14_shipping';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Turn14 Shipping', 'turn14-smart-fulfillment');
        $this->method_description = __('Fetch live shipping rates from Turn14 API for drop-shipped items.', 'turn14-smart-fulfillment');
        $this->supports           = array('shipping-zones', 'instance-settings');

        $this->init();
    }

    public function init() {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        $this->title = isset($this->settings['title']) ? $this->settings['title'] : $this->method_title;
        $this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';

        // Instantiate Turn14 API client
        $this->api_client = new Turn14_API_Client();

        // Save settings
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'turn14-smart-fulfillment'),
                'type'  => 'checkbox',
                'label' => __('Enable Turn14 live rates', 'turn14-smart-fulfillment'),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __('Method Title', 'turn14-smart-fulfillment'),
                'type'  => 'text',
                'default' => __('Turn14 Shipping', 'turn14-smart-fulfillment'),
            ),
        );
    }

    /**
     * Calculate shipping rates for a package
     *
     * This method will only be called for a package if the package is assigned to a shipping zone that includes this method.
     */
    public function calculate_shipping($package = array()) {
        // Only act for package flagged as turn14 by our splitter
        if (!isset($package['t14sf_type']) || $package['t14sf_type'] !== 'turn14') {
            return;
        }

        // Get rates from Turn14 API
        $rates = $this->api_client->get_rates_for_package($package);

        if (empty($rates)) {
            return;
        }

        // Apply markup
        $markup_percent = Turn14_Smart_Fulfillment::get_option('turn14_markup_percent', 0);
        $markup_multiplier = 1 + (floatval($markup_percent) / 100);

        foreach ($rates as $r) {
            $price = floatval($r['price']) * $markup_multiplier;
            $rate_id = sanitize_title($this->id . '_' . $r['carrier'] . '_' . $r['service']);

            $rate = array(
                'id'    => $rate_id,
                'label' => trim($r['carrier'] . ' - ' . $r['service']),
                'cost'  => $price,
                'meta_data' => array('turn14_raw' => $r['meta']),
            );

            $this->add_rate($rate);
        }
    }
}
