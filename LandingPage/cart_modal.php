<?php if (isset($_SESSION['username'])): ?>
<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1" role="dialog" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cartModalLabel">Your Shopping Cart</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="empty-cart-message" class="text-center py-4" style="display: <?php echo empty($_SESSION['cart']) ? 'block' : 'none'; ?>;">
                    <i class="fa fa-shopping-cart fa-4x mb-3" style="color: #ddd;"></i>
                    <h4>Your cart is empty</h4>
                    <p>Start shopping to add items to your cart</p>
                </div>
                <div id="cart-items-container" style="display: <?php echo empty($_SESSION['cart']) ? 'none' : 'block'; ?>;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Product</th>
                                    <th>Description</th>
                                    <th style="width: 100px;">Price</th>
                                    <th style="width: 150px;">Quantity</th>
                                    <th style="width: 100px;">Subtotal</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="cart-items-list">
                                <?php if (!empty($_SESSION['cart'])): ?>
                                    <?php
                                    $subtotal = 0;
                                    foreach ($_SESSION['cart'] as $productId => $item):
                                        $itemSubtotal = $item['price'] * $item['quantity'];
                                        $subtotal += $itemSubtotal;
                                    ?>
                                        <tr>
                                            <td>
                                                <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'images/default-product.jpg'); ?>"
                                                     style="width: 80px; height: 80px; object-fit: cover;">
                                            </td>
                                            <td>
                                                <h6><?php echo htmlspecialchars($item['name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['packaging'] ?? ''); ?></small>
                                                <?php if (isset($item['is_preorder']) && $item['is_preorder']): ?>
                                                <span class="badge badge-danger">Pre-order</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <button class="btn btn-outline-secondary decrease-quantity"
                                                                type="button"
                                                                data-product-id="<?php echo $productId; ?>"
                                                                onclick="event.stopPropagation(); updateCartItemQuantity('<?php echo $productId; ?>', -1); return false;">
                                                            <i class="fa fa-minus"></i>
                                                        </button>
                                                    </div>
                                                    <input type="number"
                                                           class="form-control text-center quantity-input"
                                                           value="<?php echo $item['quantity']; ?>"
                                                           min="1"
                                                           max="100"
                                                           data-product-id="<?php echo $productId; ?>"
                                                           onchange="updateQuantityManually(this)">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-outline-secondary increase-quantity"
                                                                type="button"
                                                                data-product-id="<?php echo $productId; ?>"
                                                                onclick="event.stopPropagation(); updateCartItemQuantity('<?php echo $productId; ?>', 1); return false;">
                                                            <i class="fa fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>₱<?php echo number_format($itemSubtotal, 2); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-danger remove-from-cart"
                                                        data-product-id="<?php echo $productId; ?>"
                                                        onclick="event.stopPropagation(); removeCartItem('<?php echo $productId; ?>'); return false;">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="special-instructions">Special Instructions</label>
                                <textarea class="form-control" id="special-instructions" rows="3" placeholder="Any special requests or notes for your order..."></textarea>
                            </div>
                        </div>
                        <div class="col-md-6 text-right">
                            <div class="order-summary">
                                <h5>Order Summary</h5>
                                <div class="d-flex justify-content-between">
                                    <span>Subtotal:</span>
                                    <span id="subtotal-amount">₱<?php echo number_format($subtotal ?? 0, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Delivery Fee:</span>
                                    <span id="delivery-fee">₱<?php echo number_format(($subtotal ?? 0) > 500 ? 0 : 50, 2); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <strong id="total-amount">₱<?php echo number_format(($subtotal ?? 0) + (($subtotal ?? 0) > 500 ? 0 : 50), 2); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Continue Shopping</button>
                <button type="button" class="btn btn-primary" id="checkout-button">Proceed to Checkout</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>