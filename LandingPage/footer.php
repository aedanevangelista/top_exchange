    <!-- copyright section start -->
    <div class="copyright_section">
        <div class="container">
            <p class="copyright_text">2025 All Rights Reserved. Design by STI Munoz Students</p>
        </div>
    </div>
    <!-- copyright section end -->

    <!-- Javascript files-->
    <script src="/LandingPage/js/jquery.min.js"></script>
    <script src="/LandingPage/js/popper.min.js"></script>
    <script src="/LandingPage/js/bootstrap.bundle.min.js"></script>
    <!-- Make sure Bootstrap JS is loaded for modals -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.min.js"></script>
    <script src="/LandingPage/js/jquery-3.0.0.min.js"></script>
    <script src="/LandingPage/js/plugin.js"></script>
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- sidebar -->
    <script src="/LandingPage/js/jquery.mCustomScrollbar.concat.min.js"></script>
    <script src="/LandingPage/js/custom.js"></script>

    <script>
        // Initialize AOS animation
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Back to top button
        $(window).scroll(function() {
            if ($(this).scrollTop() > 300) {
                $('.back-to-top').addClass('active');
            } else {
                $('.back-to-top').removeClass('active');
            }
        });

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

        // Close profile dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const profileDropdown = document.getElementById('profileDropdown');
            const isClickInside = profileDropdown.contains(event.target);

            if (!isClickInside) {
                profileDropdown.classList.remove('active');
            }
        });
    </script>

    <?php if (isset($_SESSION['username'])): ?>
    <script>
        // Function to update cart item quantity
        function updateCartItemQuantity(productId, change) {
            $.ajax({
                url: '/LandingPage/update_cart_item.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId,
                    quantity_change: change
                },
                success: function(response) {
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
            $.ajax({
                url: '/LandingPage/remove_cart_item.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId
                },
                success: function(response) {
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

        // Function to handle manual quantity input
        function updateQuantityManually(input) {
            const productId = $(input).data('product-id');
            const newQuantity = parseInt($(input).val());

            // Validate quantity
            if (isNaN(newQuantity) || newQuantity < 1) {
                $(input).val(1);
                showPopup("Quantity must be at least 1", true);
                return;
            }

            $.ajax({
                url: '/LandingPage/update_cart_item.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    product_id: productId,
                    quantity: newQuantity,
                    manual_update: true
                },
                success: function(response) {
                    if(response.success) {
                        $('#cart-count').text(response.cart_count);
                        updateCartModal();
                        showPopup("Quantity updated successfully");
                    } else {
                        showPopup(response.message || "Error updating quantity", true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error updating quantity:", error);
                    showPopup("Error updating quantity", true);
                }
            });
        }

        // Function to update the cart modal
        function updateCartModal() {
            console.log('Updating cart modal...');
            $.ajax({
                url: '/LandingPage/fetch_cart_items.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Cart response:', response);
                    if(response && response.cart_items !== undefined) {
                        // Update the cart count in the header with the total_items from the response
                        if(response.total_items !== undefined) {
                            $('#cart-count').text(response.total_items);
                        }

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
                                                <div class="input-group-prepend">
                                                    <button class="btn btn-outline-secondary decrease-quantity"
                                                            type="button"
                                                            data-product-id="${item.product_id}"
                                                            onclick="updateCartItemQuantity('${item.product_id}', -1); return false;">
                                                        <i class="fa fa-minus"></i>
                                                    </button>
                                                </div>
                                                <input type="number"
                                                       class="form-control text-center quantity-input"
                                                       value="${parseInt(item.quantity) || 1}"
                                                       min="1"
                                                       max="100"
                                                       data-product-id="${item.product_id}"
                                                       onchange="updateQuantityManually(this)">
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary increase-quantity"
                                                            type="button"
                                                            data-product-id="${item.product_id}"
                                                            onclick="updateCartItemQuantity('${item.product_id}', 1); return false;">
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

                            // No delivery fee
                            const totalAmount = subtotal;
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

        $(document).ready(function() {
            // Cart button click handler
            $(document).on('click', '.cart-button', function(e) {
                e.preventDefault();
                console.log('Cart button clicked');
                $('#cartModal').modal('show');
            });

            // Note: Quantity adjustment and remove handlers are handled by inline onclick attributes in the HTML

            // Checkout button handler
            $(document).on('click', '#checkout-button', function() {
                const specialInstructions = $('#special-instructions').val();
                sessionStorage.setItem('specialInstructions', specialInstructions);
                $('#cartModal').modal('hide');

                // Use relative path to ensure it works in all environments
                window.location.href = 'checkout.php';
            });

            // Update cart modal when it's shown
            $('#cartModal').on('show.bs.modal', function() {
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
    </script>
    <?php endif; ?>

    <?php
    // Include the cart modal if user is logged in and not on a Bootstrap 5 page
    if (isset($_SESSION['username']) && (!isset($_SESSION['skip_cart_modal_in_footer']) || $_SESSION['skip_cart_modal_in_footer'] !== true)) {
        include_once 'cart_modal.php';
    }
    ?>
</body>
</html>