<?php
/**
 * Main Settings/Dashboard Page test
 */

if (!defined('ABSPATH')) {
    exit;
}

if (isset($_POST['t14sf_save_settings'])) {
    check_admin_referer('t14sf_settings_nonce');

    $price_mode = isset($_POST['price_mode']) ? sanitize_text_field($_POST['price_mode']) : 'auto';
    $stock_threshold = isset($_POST['stock_threshold']) ? intval($_POST['stock_threshold']) : 0;
    $turn14_method_id = isset($_POST['turn14_method_id']) ? sanitize_text_field($_POST['turn14_method_id']) : '';
    $local_methods = isset($_POST['local_methods']) ? array_map('sanitize_text_field', $_POST['local_methods']) : array();

    // Update options
    Turn14_Smart_Fulfillment::update_option('price_mode', $price_mode);
    Turn14_Smart_Fulfillment::update_option('stock_threshold', $stock_threshold);
    Turn14_Smart_Fulfillment::update_option('turn14_method_id', $turn14_method_id);
    Turn14_Smart_Fulfillment::update_option('local_methods', $local_methods);

    // Redirect to prevent resubmission with warning handling
    wp_redirect(admin_url('admin.php?page=turn14-smart-fulfillment&settings-updated=true'));
    exit;
}

$price_mode = Turn14_Smart_Fulfillment::get_option('price_mode', 'auto');
$stock_threshold = Turn14_Smart_Fulfillment::get_option('stock_threshold', 0);
$turn14_method_id = Turn14_Smart_Fulfillment::get_option('turn14_method_id', 'turn14_shipping');
$local_methods = Turn14_Smart_Fulfillment::get_option('local_methods', array('flat_rate', 'free_shipping', 'local_pickup'));

global $wpdb;
$total_products = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");

$products_with_local_stock = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_stock' AND CAST(meta_value AS UNSIGNED) > 0");

$products_with_turn14_price = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_price' AND meta_value != ''");

$products_with_turn14_stock = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_turn14_stock' AND CAST(meta_value AS UNSIGNED) > 0");

$shipping_methods = WC()->shipping()->get_shipping_methods();
?>

<?php
// Conditional logic added to restrict rendering output to settings page
if (isset($_GET['page']) && $_GET['page'] === 'turn14-smart-fulfillment') {
?>
<div class="wrap t14sf-dashboard">
    <h1>🚀 Turn14 Smart Fulfillment Dashboard</h1>
    <p class="description">Intelligent stock, pricing, and shipping management for Turn14 integration.</p>
    
    <div class="t14sf-stats-grid">
        <div class="t14sf-stat-card">
            <div class="t14sf-stat-icon">📦</div>
            <div class="t14sf-stat-content">
                <h3>Total Products</h3>
                <p class="t14sf-stat-number"><?php echo number_format($total_products); ?></p>
            </div>
        </div>
        
        <div class="t14sf-stat-card t14sf-stat-success">
            <div class="t14sf-stat-icon">🏭</div>
        </div>
    </div>
</div>
<?php
} // End conditional logic
?>
