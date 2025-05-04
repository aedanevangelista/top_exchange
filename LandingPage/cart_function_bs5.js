// Function to show custom popup message
function showPopup(message, isError = false) {
    const popup = $('#customPopup');
    const popupMessage = $('#popupMessage');

    popupMessage.text(message);
    popup.removeClass('error');

    if (isError) {
        popup.addClass('error');
    }

    // Reset animation by briefly showing/hiding
    popup.hide().show();

    // Automatically hide after 3 seconds
    setTimeout(() => {
        popup.hide();
    }, 3000);
}

// Function to update cart item quantity
function updateCartItemQuantity(productId, change) {
    console.log('Updating cart item quantity:', productId, change);

    $.ajax({
        url: '/LandingPage/update_cart_item.php',
        type: 'POST',
        dataType: 'json',
        data: {
            product_id: productId,
            quantity_change: change
        },
        success: function(response) {
            console.log('Update response:', response);
            if(response.success) {
                $('#cart-count').text(response.cart_count);
                updateCartModal();
                showPopup("Cart updated successfully");
            } else {
                showPopup(response.message || "Error updating quantity", true);
            }
        },
        error: function(xhr, status, error) {
            console.error("Error updating cart item:", error);
            showPopup("Error updating cart item.", true);
        }
    });
}

// Function to remove cart item
function removeCartItem(productId) {
    console.log('Removing cart item:', productId);

    $.ajax({
        url: '/LandingPage/remove_cart_item.php',
        type: 'POST',
        dataType: 'json',
        data: {
            product_id: productId
        },
        success: function(response) {
            console.log('Remove response:', response);
            if(response.success) {
                $('#cart-count').text(response.cart_count);
                updateCartModal();
                showPopup("Item removed from cart");
            } else {
                showPopup(response.message || "Error removing item", true);
            }
        },
        error: function(xhr, status, error) {
            console.error("Error removing cart item:", error);
            showPopup("Error removing cart item.", true);
        }
    });
}

// Function to update the cart modal
function updateCartModal() {
    console.log('Updating cart modal');

    $.ajax({
        url: '/LandingPage/fetch_cart_items.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('Cart items response:', response);
            if(response && response.cart_items !== undefined) {
                if(response.cart_items.length === 0) {
                    $('#empty-cart-message').show();
                    $('#cart-items-container').hide();
                } else {
                    $('#empty-cart-message').hide();
                    $('#cart-items-container').show();

                    let cartItemsHtml = '';
                    let subtotal = 0;

                    response.cart_items.forEach(item => {
                        const price = parseFloat(item.price);
                        const itemSubtotal = price * item.quantity;
                        subtotal += itemSubtotal;

                        cartItemsHtml += `
                            <tr>
                                <td>
                                    <img src="${item.image_path ? item.image_path : '/LandingPage/images/default-product.jpg'}"
                                         alt="${item.name}"
                                         onerror="this.src='/LandingPage/images/default-product.jpg'; this.onerror=null;"
                                         style="width: 80px; height: 80px; object-fit: contain;">
                                </td>
                                <td>
                                    <h6>${item.name}</h6>
                                    <small class="text-muted">${item.packaging || ''}</small>
                                </td>
                                <td>₱${price.toFixed(2)}</td>
                                <td>
                                    <div class="input-group">
                                        <button class="btn btn-outline-secondary decrease-quantity"
                                                type="button"
                                                data-product-id="${item.product_id}"
                                                onclick="event.stopPropagation(); updateCartItemQuantity('${item.product_id}', -1); return false;">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                        <input type="number"
                                               class="form-control text-center quantity-input"
                                               value="${parseInt(item.quantity) || 1}"
                                               min="1"
                                               max="100"
                                               data-product-id="${item.product_id}"
                                               data-old-quantity="${parseInt(item.quantity) || 1}"
                                               onchange="updateQuantityManually(this)">
                                        <button class="btn btn-outline-secondary increase-quantity"
                                                type="button"
                                                data-product-id="${item.product_id}"
                                                onclick="event.stopPropagation(); updateCartItemQuantity('${item.product_id}', 1); return false;">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>₱${itemSubtotal.toFixed(2)}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger remove-from-cart"
                                            data-product-id="${item.product_id}"
                                            onclick="event.stopPropagation(); removeCartItem('${item.product_id}'); return false;">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    $('#cart-items-list').html(cartItemsHtml);
                    $('#subtotal-amount').text('₱' + subtotal.toFixed(2));

                    // Calculate delivery fee
                    const deliveryFee = subtotal > 500 ? 0 : 50;
                    $('#delivery-fee').text('₱' + deliveryFee.toFixed(2));

                    const totalAmount = subtotal + deliveryFee;
                    $('#total-amount').text('₱' + totalAmount.toFixed(2));
                }
            }
        },
        error: function(xhr, status, error) {
            console.error("Error fetching cart items:", error);
            showPopup("Error fetching cart items.", true);
        }
    });
}

// Function to manually update quantity
function updateQuantityManually(input) {
    const productId = $(input).data('product-id');
    const newQuantity = parseInt($(input).val());
    const oldQuantity = parseInt($(input).attr('data-old-quantity') || 1);

    console.log('Manual quantity update:', productId, 'from', oldQuantity, 'to', newQuantity);

    // Update the data attribute for next time
    $(input).attr('data-old-quantity', newQuantity);

    // Calculate the change
    const change = newQuantity - oldQuantity;

    if (change !== 0) {
        updateCartItemQuantity(productId, change);
    }
}

// Document ready function for cart operations
$(document).ready(function() {
    // Cart button click handler
    $(document).on('click', '.cart-button', function(e) {
        e.preventDefault();
        console.log('Cart button clicked');
        var cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
        cartModal.show();
    });

    // Checkout button handler
    $(document).on('click', '#checkout-button', function() {
        const specialInstructions = $('#special-instructions').val();
        sessionStorage.setItem('specialInstructions', specialInstructions);
        
        // Hide the modal using Bootstrap 5 method
        var cartModal = bootstrap.Modal.getInstance(document.getElementById('cartModal'));
        if (cartModal) {
            cartModal.hide();
        }

        // Use relative path to ensure it works in all environments
        window.location.href = '/LandingPage/checkout.php';
    });

    // Update cart modal when it's shown
    document.getElementById('cartModal').addEventListener('show.bs.modal', function() {
        // First clean up the cart by removing any invalid items
        $.ajax({
            url: '/LandingPage/clean_cart.php',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                console.log('Cart cleaned:', response);
                // Then update the cart modal
                updateCartModal();
            },
            error: function(xhr, status, error) {
                console.error("Error cleaning cart:", error);
                // Still try to update the cart modal
                updateCartModal();
            }
        });
    });
});
