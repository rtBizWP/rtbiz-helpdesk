RT Product Sync
===============

This class gives a special attribute to link it with other Custom Post Types.

Many a times, we want to link our Custom Post Types with the products in our E-commerce Store such as WooCommerce or EasyDigitalDownload.

This class takes all the existing products from either a WC or EDD store and synchronize them with the terms of this special attribute taxonomy "Products".

So that we can assign these products to any custom post types that are registered with this taxonomy.

## How to use

    $terms_caps = array(
 				'manage_terms' => true,//$editor_cap,
 				'edit_terms'   => true,//$editor_cap,
 				'delete_terms' => true,//$editor_cap,
 				'assign_terms' => true,//$editor_cap,
 				);
    $product_plugin= 'download'; /* post_type of woocommerce('product') or edd('download') */
    $rtbiz_product_sync = new RT_Product_Sync( $terms_caps, $product_plugin );