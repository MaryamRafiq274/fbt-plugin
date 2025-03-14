jQuery(document).ready(function ($) {
    function updateTotalPrice() {
        let total = 0;
        let selectedProducts = {};

        $('.fbt-checkbox:checked').each(function () {
            let $checkbox = $(this);
            let productId = $checkbox.data('product_id');
            let basePrice = parseFloat($checkbox.data('price')) || 0;
            let hasVariations = $('.fbt-variations[data-product_id="' + productId + '"] select.fbt-variation').length > 0;

            // Initialize product entry
            if (!selectedProducts[productId]) {
                selectedProducts[productId] = {
                    basePrice: hasVariations ? 0 : basePrice, // Include base price only if no variations exist
                    variationsTotal: 0
                };
            }

            // Find the corresponding variation dropdowns and add selected prices
            $('.fbt-variations[data-product_id="' + productId + '"] select.fbt-variation').each(function () {
                let selectedPrice = parseFloat($(this).find(':selected').attr('data-price')) || 0;
                if (selectedPrice > 0) {
                    selectedProducts[productId].variationsTotal += selectedPrice;
                }
            });
        });

        // Calculate the final total
        $.each(selectedProducts, function (productId, productData) {
            let productTotal = productData.variationsTotal > 0 ? productData.variationsTotal : productData.basePrice;
            total += productTotal;
        });

        console.log("Updated Total Price:", total); // Debugging
        $('#fbt-total-price').html('<span class="woocommerce-Price-amount amount">€' + total.toFixed(2) + '</span>');
    }

    // Update total price when a checkbox or variation dropdown changes
    $(document).on('change', '.fbt-checkbox, .fbt-variation', function () {
        updateTotalPrice();
    });

    // AJAX: Add all selected products to cart
    $('#add-all-to-cart').click(function () {
        let productData = [];
        let $button = $(this);
    
        $('.fbt-checkbox:checked').each(function () {
            let $checkbox = $(this);
            let productId = $checkbox.data('product_id');
            let variations = {};
            
            // Capture all selected variations
            $('.fbt-variations[data-product_id="' + productId + '"] select.fbt-variation').each(function () {
                let attrName = $(this).data('attribute');
                let attrValue = $(this).val();
                
                if (attrValue) {
                    variations[attrName] = attrValue;
                } else {
                    console.warn("Variation missing for product:", productId, "Attribute:", attrName);
                }
            });
    
            productData.push({
                product_id: productId,
                variations: variations
            });
        });
    
        if (productData.length === 0) {
            alert('Please select at least one product.');
            return;
        }
    
        console.log("🚀 Sending product data:", JSON.stringify(productData, null, 2)); // ✅ Log to console
    
        $button.prop('disabled', true).text('Adding...');
    
        $.ajax({
            type: 'POST',
            url: fbt_ajax.ajax_url,
            data: {
                action: 'fbt_add_all_to_cart',
                product_data: JSON.stringify(productData)
            },
            success: function (response) {
                console.log("✅ Response from server:", response); // ✅ Log to console
    
                if (response.success) {
                    alert(response.data.message);
                    $(document.body).trigger('wc_fragment_refresh'); // Refresh cart
                } else {
                    alert(response.data.message || 'Error adding products to cart.');
                }
            },
            error: function () {
                alert('An unexpected error occurred.');
            },
            complete: function () {
                $button.prop('disabled', false).text('Add All to Cart');
            }
        });
    });

    // Run initially to set correct price
    updateTotalPrice();
});