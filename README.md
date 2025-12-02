\# Turn14 Smart Fulfillment



WordPress plugin for intelligent stock, pricing, and shipping management with Turn14 integration.



\## Features



\- \*\*Dual Pricing\*\*: Automatically show local warehouse price when in stock, Turn14 price when out of stock

\- \*\*Dual Stock Management\*\*: Track both warehouse and Turn14 inventory

\- \*\*Smart Shipping Split\*\*: Separate shipping methods for local vs drop-ship items

\- \*\*Order Routing\*\*: Automatically tag orders for fulfillment routing

\- \*\*WooCommerce Integration\*\*: Seamless integration with WooCommerce



\## Installation



1\. Download the plugin ZIP file or clone this repository

2\. Upload to `/wp-content/plugins/turn14-smart-fulfillment/`

3\. Activate the plugin through WordPress admin

4\. Go to \*\*Turn14 Fulfillment → Dashboard\*\* to configure settings



\## Requirements



\- WordPress 5.8 or higher

\- WooCommerce 5.0 or higher

\- PHP 7.4 or higher



\## Configuration



\### Price Management



Navigate to \*\*Turn14 Fulfillment → Dashboard\*\* and configure:



\- \*\*Price Display Mode\*\*: Choose how prices are displayed based on stock

&nbsp; - Auto (Recommended): Shows local price when stock > threshold, Turn14 price otherwise

&nbsp; - Always Local: Always displays local warehouse price

&nbsp; - Always Turn14: Always displays Turn14 price

&nbsp; - Manual: No automatic price switching

\- \*\*Stock Threshold\*\*: When to switch from local to Turn14 pricing (default: 0)



\### Shipping Management



\- \*\*Turn14 Shipping Method ID\*\*: Your Turn14 shipping plugin's method ID (e.g., "turn14\_shipping")

\- \*\*Local Shipping Methods\*\*: Select which methods apply to local warehouse items



\## Meta Fields



The plugin uses these WooCommerce product meta fields:



| Meta Key | Description | Updated By |

|----------|-------------|------------|

| `\_stock` | Local warehouse stock | Your inventory system |

| `\_local\_price` | Your warehouse price | Manual or sync |

| `\_turn14\_price` | Turn14 price with markup | Turn14 sync script |

| `\_turn14\_stock` | Turn14 available stock | Turn14 sync script |

| `\_turn14\_data` | Full Turn14 product data (JSON) | Turn14 sync script |

| `\_turn14\_sku` | Turn14 SKU | Turn14 sync script |

| `\_fulfillment\_source` | Order item fulfillment source | Auto-tagged at checkout |



\## How It Works



1\. \*\*Product Sync\*\*: Your Turn14 sync script populates `\_turn14\_price`, `\_turn14\_stock`, `\_turn14\_data`

2\. \*\*Price Display\*\*: Plugin automatically shows correct price based on stock availability

3\. \*\*Cart Split\*\*: Items are separated into local and Turn14 packages at checkout

4\. \*\*Shipping\*\*: Appropriate shipping methods are shown for each package type

5\. \*\*Order Tagging\*\*: Each item is tagged with fulfillment source for routing



\### Example: Mixed Cart



