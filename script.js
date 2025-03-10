jQuery(document).ready(function($) {
    function updateTotalPrice() {
        let total = 0;
        let shipping = 0;
        let hasShipping = false;

        $('.fbt-checkbox:checked').each(function() {
            let productId = $(this).data('product_id');
            let variationSelect = $('.fbt-variation[data-product_id="' + productId + '"]');
            let variationId = variationSelect.val();
            let variationPrice = variationSelect.find(':selected').data('price');

            // Only add the price if a valid variation is selected
            if (variationSelect.length > 0 && variationId) {
                total += parseFloat(variationPrice);
            } else if (!variationSelect.length) {
                total += parseFloat($(this).data('price'));
            }

            let productShipping = $(this).data('shipping') || 0;
            if (productShipping > 0) {
                hasShipping = true;
                shipping += parseFloat(productShipping);
            }
        });

        let totalPrice = total + shipping;
        $('#fbt-total-price').html('€' + totalPrice.toFixed(2));

        let heading = $('#fbt-heading');
        if (totalPrice > 50) {
            heading.text('Frequently Bought Together (with free shipping)');
        } else if (hasShipping) {
            heading.text('Frequently Bought Together (with shipping)');
        } else {
            heading.text('Frequently Bought Together');
        }
    }

    // Reset dropdown and update price
    $('.fbt-variation').each(function() {
        $(this).prop('selectedIndex', 0); // Ensure "Select Size" is selected
    });

    $('.fbt-checkbox, .fbt-variation').on('change', function() {
        updateTotalPrice();
    });

    $('.add-to-cart').click(function() {
        let productId = $(this).data('product_id');
        let variationSelect = $('.fbt-variation[data-product_id="' + productId + '"]');
        let variationId = variationSelect.val();

        $.ajax({
            type: 'POST',
            url: fbt_ajax.ajax_url,
            data: {
                action: 'fbt_add_to_cart',
                product_id: productId,
                variation_id: variationId || ''
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $(document.body).trigger('wc_fragment_refresh');
                } else {
                    alert('Error adding to cart');
                }
            }
        });
    });

    $('#add-all-to-cart').click(function() {
        let product_data = [];

        $('.fbt-checkbox:checked').each(function() {
            let productId = $(this).data('product_id');
            let variationSelect = $('.fbt-variation[data-product_id="' + productId + '"]');
            let variationId = variationSelect.val();

            product_data.push({
                product_id: productId,
                variation_id: variationId || 0
            });
        });

        if (product_data.length === 0) {
            alert('Please select at least one product.');
            return;
        }

        $.ajax({
            type: 'POST',
            url: fbt_ajax.ajax_url,
            data: {
                action: 'fbt_add_all_to_cart',
                product_data: JSON.stringify(product_data)
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $(document.body).trigger('wc_fragment_refresh');
                } else {
                    alert('Error adding products to cart.');
                }
            }
        });
    });

    updateTotalPrice();
});