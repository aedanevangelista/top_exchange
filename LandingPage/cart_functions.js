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
                                    ${item.is_preorder ? '<span class="badge badge-danger">Pre-order</span>' : ''}
                                </td>
                                <td>₱${price.toFixed(2)}</td>
                                <td>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <button class="btn btn-outline-secondary decrease-quantity"
                                                    type="button"
                                                    data-product-id="${item.product_id}"
                                                    onclick="event.stopPropagation(); updateCartItemQuantity('${item.product_id}', -1); return false;">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                        </div>
                                        <input type="number"
                                               class="form-control text-center quantity-input"
                                               value="${parseInt(item.quantity) || 1}"
                                               min="1"
                                               max="100"
                                               data-product-id="${item.product_id}"
                                               data-old-quantity="${parseInt(item.quantity) || 1}"
                                               onchange="updateQuantityManually(this)">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary increase-quantity"
                                                    type="button"
                                                    data-product-id="${item.product_id}"
                                                    onclick="event.stopPropagation(); updateCartItemQuantity('${item.product_id}', 1); return false;">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
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
function initializeCartFunctions() {
    // Add to cart handler
    $(document).on('click', '.add-to-cart', function(e) {
        e.preventDefault();

        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name');
        const productPrice = $(this).data('product-price');
        const imagePath = $(this).data('image-path');
        const packaging = $(this).data('packaging');

        $.ajax({
            url: 'add_to_cart.php',
            type: 'POST',
            dataType: 'json',
            data: {
                product_id: productId,
                product_name: productName,
                product_price: productPrice,
                image_path: imagePath,
                packaging: packaging
            },
            success: function(response) {
                if(response.success) {
                    $('#cart-count').text(response.cart_count);
                    showPopup(productName + " added to cart!");

                    // Update cart modal if it's open
                    if ($('#cartModal').hasClass('show')) {
                        updateCartModal();
                    }
                } else {
                    showPopup(response.message || "Error adding to cart", true);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error adding product to cart:", error);
                showPopup("Error adding product to cart.", true);
            }
        });
    });

    // Note: Quantity adjustment and remove handlers are now handled by direct onclick attributes in the HTML

    // Update cart modal when it's shown
    $('#cartModal').on('show.bs.modal', function() {
        updateCartModal();
    });
}