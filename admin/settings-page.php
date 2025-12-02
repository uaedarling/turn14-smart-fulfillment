<?php
/**
 * Main Settings/Dashboard Page
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
    wp_redirect(admin_url('admin.php?page=t14sf-dashboard&settings-updated=true'));
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
if (isset($_GET['page']) && $_GET['page'] === 't14sf-dashboard') {
?>
<div class="wrap t14sf-dashboard">
    <h1>🚀 Turn14 Smart Fulfillment Dashboard</h1>
    <p class="description">Intelligent stock, pricing, and shipping management for Turn14 integration.</p>
    
    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>Settings saved successfully!</strong></p>
        </div>
    <?php endif; ?>
    
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
            <div class="t14sf-stat-content">
                <h3>Local Stock</h3>
                <p class="t14sf-stat-number"><?php echo esc_html(number_format($products_with_local_stock)); ?></p>
                <small>Products in warehouse</small>
            </div>
        </div>
        
        <div class="t14sf-stat-card t14sf-stat-primary">
            <div class="t14sf-stat-icon">💰</div>
            <div class="t14sf-stat-content">
                <h3>Turn14 Prices</h3>
                <p class="t14sf-stat-number"><?php echo esc_html(number_format($products_with_turn14_price)); ?></p>
                <small>With Turn14 pricing</small>
            </div>
        </div>
        
        <div class="t14sf-stat-card t14sf-stat-warning">
            <div class="t14sf-stat-icon">📊</div>
            <div class="t14sf-stat-content">
                <h3>Turn14 Stock</h3>
                <p class="t14sf-stat-number"><?php echo esc_html(number_format($products_with_turn14_stock)); ?></p>
                <small>Available from Turn14</small>
            </div>
        </div>
    </div>
    
    <div class="t14sf-settings-section">
        <h2>⚙️ Price Management Settings</h2>
        <form method="post" action="">
            <?php wp_nonce_field('t14sf_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="price_mode">Price Display Mode</label>
                    </th>
                    <td>
                        <select name="price_mode" id="price_mode" class="regular-text">
                            <option value="auto" <?php selected($price_mode, 'auto'); ?>>Auto (Show local price when in stock, Turn14 when out)</option>
                            <option value="always_local" <?php selected($price_mode, 'always_local'); ?>>Always Local Price</option>
                            <option value="always_turn14" <?php selected($price_mode, 'always_turn14'); ?>>Always Turn14 Price</option>
                            <option value="manual" <?php selected($price_mode, 'manual'); ?>>Manual (No automatic switching)</option>
                        </select>
                        <p class="description">Controls which price is displayed to customers based on stock availability.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="stock_threshold">Stock Threshold</label>
                    </th>
                    <td>
                        <input type="number" name="stock_threshold" id="stock_threshold" value="<?php echo esc_attr($stock_threshold); ?>" min="0" class="small-text" />
                        <p class="description">When local stock drops to or below this number, switch to Turn14 pricing (in Auto mode).</p>
                    </td>
                </tr>
            </table>
            
            <h2>🚚 Shipping Management Settings</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="turn14_method_id">Turn14 Shipping Method ID</label>
                    </th>
                    <td>
                        <input type="text" name="turn14_method_id" id="turn14_method_id" value="<?php echo esc_attr($turn14_method_id); ?>" class="regular-text" />
                        <p class="description">The method ID used by your Turn14 shipping plugin (e.g., "turn14_shipping").</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label>Local Warehouse Shipping Methods</label>
                    </th>
                    <td>
                        <?php if (!empty($shipping_methods)): ?>
                            <?php foreach ($shipping_methods as $method_id => $method): ?>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="local_methods[]" value="<?php echo esc_attr($method_id); ?>" <?php checked(in_array($method_id, $local_methods)); ?> />
                                    <?php echo esc_html($method->get_method_title()); ?> (<?php echo esc_html($method_id); ?>)
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No shipping methods found. Please configure WooCommerce shipping first.</p>
                        <?php endif; ?>
                        <p class="description">Select which shipping methods apply to items fulfilled from your local warehouse.</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="t14sf_save_settings" id="submit" class="button button-primary" value="Save Settings" />
            </p>
        </form>
    </div>
    
    <div class="t14sf-settings-section">
        <h2>📋 Product Meta Fields Reference</h2>
        <p class="description">The plugin uses these WooCommerce product meta fields for managing inventory and pricing:</p>
        
        <table class="t14sf-meta-table">
            <thead>
                <tr>
                    <th>Meta Key</th>
                    <th>Description</th>
                    <th>Updated By</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>_stock</code></td>
                    <td>Local warehouse stock quantity</td>
                    <td><span class="t14sf-badge-primary">Your inventory system</span></td>
                </tr>
                <tr>
                    <td><code>_local_price</code></td>
                    <td>Your warehouse price</td>
                    <td><span class="t14sf-badge-primary">Manual or sync</span></td>
                </tr>
                <tr>
                    <td><code>_turn14_price</code></td>
                    <td>Turn14 price with markup</td>
                    <td><span class="t14sf-badge-success">Turn14 sync script</span></td>
                </tr>
                <tr>
                    <td><code>_turn14_stock</code></td>
                    <td>Turn14 available stock quantity</td>
                    <td><span class="t14sf-badge-success">Turn14 sync script</span></td>
                </tr>
                <tr>
                    <td><code>_turn14_data</code></td>
                    <td>Full Turn14 product data (JSON)</td>
                    <td><span class="t14sf-badge-success">Turn14 sync script</span></td>
                </tr>
                <tr>
                    <td><code>_turn14_sku</code></td>
                    <td>Turn14 SKU identifier</td>
                    <td><span class="t14sf-badge-success">Turn14 sync script</span></td>
                </tr>
                <tr>
                    <td><code>_fulfillment_source</code></td>
                    <td>Order item fulfillment source (local/turn14)</td>
                    <td><span class="t14sf-badge-secondary">Auto-tagged at checkout</span></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="t14sf-settings-section">
        <h2>💡 How It Works</h2>
        <ol style="line-height: 1.8;">
            <li><strong>Product Sync:</strong> Your Turn14 sync script populates <code>_turn14_price</code>, <code>_turn14_stock</code>, and <code>_turn14_data</code> meta fields.</li>
            <li><strong>Price Display:</strong> The plugin automatically shows the correct price based on stock availability and your configured mode.</li>
            <li><strong>Cart Split:</strong> At checkout, items are separated into local warehouse and Turn14 packages.</li>
            <li><strong>Shipping:</strong> Appropriate shipping methods are displayed for each package type.</li>
            <li><strong>Order Tagging:</strong> Each order item is tagged with its fulfillment source for routing to the correct fulfillment center.</li>
        </ol>
    </div>
</div>
<?php
} // End conditional logic
?>
