<?php
/**
 * Plugin Name: Frequently Bought Together (fbt)
 * Plugin URI: https://yourwebsite.com
 * Description: A WooCommerce plugin to display frequently bought together products.
 * Version: 1.0.0
 * Author: Maryam Rafiq
 * Author URI: https://yourwebsite.com
 * License: GPL2
 * Text Domain: frequently-bought-together
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Enqueue styles and scripts
function fbt_enqueue_scripts() {
    if (is_product()) {
        wp_enqueue_style('fbt-style', plugin_dir_url(__FILE__) . 'assets/style.css');
        wp_enqueue_script('fbt-script', plugin_dir_url(__FILE__) . 'assets/script.js', array('jquery'), null, true);
        wp_localize_script('fbt-script', 'fbt_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
    }
}
add_action('wp_enqueue_scripts', 'fbt_enqueue_scripts');

// Display the Frequently Bought Together section
function fbt_display_section() {
    global $product;

    if (!$product) {
        return;
    }

    $product_id = $product->get_id();
    $fbt_products = get_post_meta($product_id, '_fbt_products', true);

    if (empty($fbt_products)) {
        return;
    }

    // Add the main product to the list
    array_unshift($fbt_products, $product_id);

    echo '<div class="fbt-section">';
    echo '<h3 id="fbt-heading">' . __('Frequently Bought Together', 'frequently-bought-together') . '</h3>';
    echo '<div class="fbt-products">';

    foreach ($fbt_products as $fbt_product_id) {
        $fbt_product = wc_get_product($fbt_product_id);
        if (!$fbt_product) continue;

        $product_price = wc_price($fbt_product->get_price());

        echo '<div class="fbt-product">';
        echo '<input type="checkbox" class="fbt-checkbox" data-product_id="' . esc_attr($fbt_product_id) . '" data-price="' . esc_attr($fbt_product->get_price()) . '" checked>';
        echo '<a href="' . esc_url(get_permalink($fbt_product_id)) . '">' . $fbt_product->get_image() . '</a>';
        echo '<p>' . esc_html($fbt_product->get_name()) . ' - <span class="woocommerce-Price-amount amount">' . $product_price . '</span></p>';

        if ($fbt_product->is_type('variable')) {
            $variations = $fbt_product->get_available_variations();
            $attributes = $fbt_product->get_attributes();
            
            echo '<div class="fbt-variations" data-product_id="' . esc_attr($fbt_product_id) . '">';

            foreach ($attributes as $attribute_name => $attribute) {
                $attribute_label = wc_attribute_label($attribute_name);
                
                echo '<label>' . esc_html($attribute_label) . '</label>';
                echo '<select class="fbt-variation" data-attribute="' . esc_attr($attribute_name) . '" data-product_id="' . esc_attr($fbt_product_id) . '">';
                echo '<option value="">' . __('Select', 'frequently-bought-together') . ' ' . esc_html($attribute_label) . '</option>';
            
                foreach ($attribute->get_options() as $option) {
                    $variation_price = '';
            
                    foreach ($variations as $variation) {
                        if (isset($variation['attributes']['attribute_' . $attribute_name]) && $variation['attributes']['attribute_' . $attribute_name] == $option) {
                            // Only set price if this is the size attribute
                            if (strpos($attribute_name, 'size') !== false) {
                                $variation_price = wc_get_price_to_display($fbt_product, ['price' => $variation['display_price']]);
                            }
                            break;
                        }
                    }
            
                    echo '<option value="' . esc_attr($option) . '" data-price="' . esc_attr($variation_price) . '">';
                    echo esc_html($option);
            
                    // Only show price if this is a size variation and has a valid price
                    if (!empty($variation_price) && $variation_price > 0 && strpos($attribute_name, 'size') !== false) {
                        echo ' - ' . wc_price($variation_price);
                    }
            
                    echo '</option>';
                }
            
                echo '</select>';
            }
            

            echo '</div>'; // Close .fbt-variations
        }

        echo '</div>'; // Close .fbt-product
    }

    echo '</div>'; // Close .fbt-products
    echo '<p id="fbt-total-price-1"><strong>' . __('Total Price:', 'frequently-bought-together') . ' <span id="fbt-total-price">€0.00</span></strong></p>';
    echo '<button id="add-all-to-cart" class="button">' . __('Add All to Cart', 'frequently-bought-together') . '</button>';
    echo '</div>'; // Close .fbt-section
}
add_action('woocommerce_after_single_product', 'fbt_display_section', 15);


// AJAX: Add single product to cart
function fbt_ajax_add_to_cart() {
    if (!isset($_POST['product_id'])) {
        wp_send_json_error(['message' => __('Invalid request', 'frequently-bought-together')]);
    }

    $product_id = intval($_POST['product_id']);
    $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;

    $cart_item_key = WC()->cart->add_to_cart($product_id, 1, $variation_id);
    if ($cart_item_key) {
        wp_send_json_success(['message' => __('Product added to cart', 'frequently-bought-together')]);
    } else {
        wp_send_json_error(['message' => __('Could not add product to cart', 'frequently-bought-together')]);
    }

    wp_die();
}
add_action('wp_ajax_fbt_add_to_cart', 'fbt_ajax_add_to_cart');
add_action('wp_ajax_nopriv_fbt_add_to_cart', 'fbt_ajax_add_to_cart');

// AJAX: Add all selected products to cart


function fbt_ajax_add_all_to_cart() {
    if (!isset($_POST['product_data'])) {
        wp_send_json_error(['message' => 'No product data received']);
    }

    $product_data = json_decode(stripslashes($_POST['product_data']), true);

    if (empty($product_data)) {
        wp_send_json_error(['message' => 'Invalid product data']);
    }

    foreach ($product_data as $item) {
        $product_id = intval($item['product_id']);
        $variations = isset($item['variations']) ? $item['variations'] : [];
        $variation_id = 0;

        // Get the product
        $product = wc_get_product($product_id);
        if (!$product) {
            continue;
        }

        // Find correct variation ID if it's a variable product
        if ($product->is_type('variable')) {
            $variation_id = find_matching_variation_id($product_id, $variations);
            if (!$variation_id) {
                continue;
            }
        }

        // Add to cart with the correct variation
        WC()->cart->add_to_cart($product_id, 1, $variation_id, $variations);
    }

    wp_send_json_success(['message' => 'Products added to cart successfully']);
}

add_action('wp_ajax_fbt_add_all_to_cart', 'fbt_add_all_to_cart');
add_action('wp_ajax_nopriv_fbt_add_all_to_cart', 'fbt_add_all_to_cart');

function fbt_add_all_to_cart() {
    if (!isset($_POST['product_data'])) {
        wp_send_json_error(['message' => 'No product data received.']);
        return;
    }

    $product_data = json_decode(stripslashes($_POST['product_data']), true);

    if (empty($product_data)) {
        wp_send_json_error(['message' => 'Empty product data.']);
        return;
    }

    error_log("🔥 Received product data: " . print_r($product_data, true)); // ✅ Debug log

    $added_count = 0;

    foreach ($product_data as $item) {
        $product_id = intval($item['product_id']);
        $variations = $item['variations'] ?? [];

        // Find correct variation ID if it's a variable product
        $variation_id = find_matching_variation_id($product_id, $variations);
        if ($variation_id) {
            $added = WC()->cart->add_to_cart($product_id, 1, $variation_id, $variations);
        } else {
            $added = WC()->cart->add_to_cart($product_id, 1);
        }

        error_log("📌 Adding to cart: Product ID: $product_id, Variation ID: $variation_id, Status: " . ($added ? 'Success' : 'Failed'));

        if ($added) {
            $added_count++;
        }
    }

    if ($added_count > 0) {
        wp_send_json_success(['message' => 'Products added to cart.']);
    } else {
        wp_send_json_error(['message' => 'Failed to add products to cart.']);
    }
}


function find_matching_variation_id($product_id, $selected_variations) {
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        return 0; // Not a variable product
    }

    $variations = $product->get_available_variations();
    
    foreach ($variations as $variation) {
        $match = true;
        
        foreach ($selected_variations as $attribute => $value) {
            $attr_key = 'attribute_' . sanitize_title($attribute);

            // Ensure we check only attributes that exist in the variation
            if (isset($variation['attributes'][$attr_key])) {
                if (strtolower($variation['attributes'][$attr_key]) !== strtolower($value)) {
                    $match = false;
                    break;
                }
            }
        }
        
        if ($match) {
            return $variation['variation_id']; // Return matched variation ID
        }
    }
    return 0; // No match found
}


// Add meta box in product editor
function fbt_add_meta_box() {
    add_meta_box(
        'fbt_meta_box',
        __('Frequently Bought Together', 'frequently-bought-together'),
        'fbt_meta_box_callback',
        'product',
        'side'
    );
}
add_action('add_meta_boxes', 'fbt_add_meta_box');

// Meta box callback function
function fbt_meta_box_callback($post) {
    $fbt_products = get_post_meta($post->ID, '_fbt_products', true) ?: [];
    $all_products = wc_get_products(['limit' => -1]);

    // Ensure main product is always displayed
    $main_product_id = $post->ID;

    echo '<p><strong>Main Product:</strong> ' . get_the_title($main_product_id) . '</p>';

    echo '<div id="selected-products">';
    if (!empty($fbt_products)) {
        foreach ($fbt_products as $product_id) {
            echo '<span class="selected-product" data-id="' . $product_id . '">'
                . get_the_title($product_id)
                . ' <span class="remove-product" style="cursor:pointer; color:red;">✖</span></span>';
        }
    }
    echo '</div>';

    echo '<select id="fbt-products-select" style="width:100%;">';
    echo '<option value="">Select a product</option>';
    foreach ($all_products as $product) {
        if ($product->get_id() == $main_product_id) continue; // Skip main product
        echo '<option value="' . $product->get_id() . '">' . $product->get_name() . '</option>';
    }
    echo '</select>';

    echo '<input type="hidden" name="fbt_products" id="fbt-products-hidden" value="' . implode(',', $fbt_products) . '">';

    // JavaScript for selecting/removing products
    ?>
    <script>
    jQuery(document).ready(function ($) {
        let selectedProducts = $('#fbt-products-hidden').val().split(',').filter(Boolean);
        
        $('#fbt-products-select').on('change', function () {
            let productId = $(this).val();
            let productName = $(this).find('option:selected').text();

            if (productId && !selectedProducts.includes(productId) && selectedProducts.length < 2) {
                selectedProducts.push(productId);
                $('#selected-products').append(
                    <span class="selected-product" data-id="${productId}">${productName} 
                        <span class="remove-product" style="cursor:pointer; color:red;">✖</span>
                    </span>
                );
                updateHiddenField();
            } else if (selectedProducts.length >= 2) {
                alert('You can select a maximum of 2 additional products.');
            }
        });

        $(document).on('click', '.remove-product', function () {
            let productId = $(this).parent().data('id');
            selectedProducts = selectedProducts.filter(id => id !== String(productId));
            $(this).parent().remove();
            updateHiddenField();
        });

        function updateHiddenField() {
            $('#fbt-products-hidden').val(selectedProducts.join(','));
        }
    });
    </script>
    <style>
        #selected-products { margin-top: 10px; }
        .selected-product {
            display: inline-block;
            background: #e0e0e0;
            padding: 5px;
            margin: 3px;
            border-radius: 3px;
        }
    </style>
    <?php
}

// Save selected products (excluding main product)
function fbt_save_meta_box($post_id) {
    if (isset($_POST['fbt_products'])) {
        $selected_products = explode(',', sanitize_text_field($_POST['fbt_products']));
        $selected_products = array_slice(array_filter($selected_products), 0, 2); // Ensure max 2
        update_post_meta($post_id, '_fbt_products', $selected_products);
    }
}
add_action('save_post', 'fbt_save_meta_box');

// function fbt_display_products() {
//     global $post;
//     $fbt_products = get_post_meta($post->ID, '_fbt_products', true) ?: [];

//     if (!empty($fbt_products)) {
//         echo '<div class="fbt-products"><h3>Frequently Bought Together</h3><ul>';
//         foreach ($fbt_products as $product_id) {
//             echo '<li><a href="' . get_permalink($product_id) . '">' . get_the_title($product_id) . '</a></li>';
//         }
//         echo '</ul></div>';
//     }
// }
// add_action('woocommerce_after_single_product_summary', 'fbt_display_products', 20);
// Handle AJAX request to get the updated cart count
function fbt_update_cart_count() {
    wp_send_json_success(['cart_count' => WC()->cart->get_cart_contents_count()]);
    wp_die();
}
add_action('wp_ajax_fbt_update_cart_count', 'fbt_update_cart_count');
add_action('wp_ajax_nopriv_fbt_update_cart_count', 'fbt_update_cart_count');

function ensure_woocommerce_cart_fragments() {
    if (is_cart() || is_checkout()) return;

    wp_enqueue_script('wc-cart-fragments', WC()->plugin_url() . '/assets/js/frontend/cart-fragments.min.js', array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'ensure_woocommerce_cart_fragments');
