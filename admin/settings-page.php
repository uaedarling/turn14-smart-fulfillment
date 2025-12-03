<?php
/**
 * Main Settings/Dashboard Page test
 */

if (!defined('ABSPATH')) {
    exit;
}

if (isset($_POST['t14sf_save_settings'])) {
    // Capability check
    if (! current_user_can('manage_options')) {
        wp_die(__('You do not have permission to perform this action.', 'turn14-smart-fulfillment'));
    }

    // Nonce verification
    check_admin_referer('t14sf_settings_nonce');

    $price_mode = isset($_POST['price_mode']) ? sanitize_text_field($_POST['price_mode']) : 'auto';
    $stock_threshold = isset($_POST['stock_threshold']) ? intval($_POST['stock_threshold']) : 0;
    $turn14_method_id = isset($_POST['turn14_method_id']) ? sanitize_text_field($_POST['turn14_method_id']) : '';
    $local_methods = isset($_POST['local_methods']) ? array_map('sanitize_text_field', (array) $_POST['local_methods']) : array();

    // Update options
    Turn14_Smart_Fulfillment::update_option('price_mode', $price_mode);
    Turn14_Smart_Fulfillment::update_option('stock_threshold', $stock_threshold);
    Turn14_Smart_Fulfillment::update_option('turn14_method_id', $turn14_method_id);
    Turn14_Smart_Fulfillment::update_option('local_methods', $local_methods);

    // Redirect to prevent resubmission and go back to correct admin page slug
    wp_redirect(admin_url('admin.php?page=t14sf-dashboard&settings-updated=true'));
    exit;
}

$price_mode = Turn14_Smart_Fulfillment::get_option('price_mode', 'auto');
$stock_threshold = Turn14_Smart_Fulfillment::get_option('stock_threshold', 0);
$turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');
$local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));

global $wpdb;
$total_products = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");

$products_with_local_stock = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_stock' AND CAST(meta_value AS UNSIGNED) > 0");

$products_with_turn14_price = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_price' AND meta_value != ''");

$products_with_turn14_stock = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_stock' AND CAST(meta_value AS UNSIGNED) > 0");

$shipping_methods = null;
if (function_exists('WC') && WC()->shipping()) {
    $shipping_methods = WC()->shipping()->get_shipping_methods();
}

?>
<?php
// Restrict rendering output to our settings page (use the real slug)
if (isset($_GET['page']) && sanitize_text_field($_GET['page']) === 't14sf-dashboard') {
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
                <h3>Products with Local Stock</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $products_with_local_stock ) ); ?></p>
            </div>
        </div>

        <div class="t14sf-stat-card t14sf-stat-primary">
            <div class="t14sf-stat-icon">💲</div>
            <div class="t14sf-stat-content">
                <h3>Products with Turn14 Price</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $products_with_turn14_price ) ); ?></p>
            </div>
        </div>

        <div class="t14sf-stat-card t14sf-stat-warning">
            <div class="t14sf-stat-icon">🚚</div>
            <div class="t14sf-stat-content">
                <h3>Products with Turn14 Stock</h3>
                <p class="t14sf-stat-number"><?php echo esc_html( number_format_i18n( $products_with_turn14_stock ) ); ?></p>
            </div>
        </div>
    </div>
</div>
<?php
} // End conditional logic
?>
