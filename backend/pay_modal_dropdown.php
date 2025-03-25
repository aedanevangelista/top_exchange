<div class="payment-method-section">
    <label for="paymentMethodDropdown">Select Payment Method:</label>
    <select id="paymentMethodDropdown" class="form-control" onchange="selectPaymentMethod(this.value)">
        <option value="internal">Internal Payment (Use Balance)</option>
        <option value="external">External Payment (Bank Transfer)</option>
    </select>
    
    <div class="payment-method-content active" id="internalPaymentContent">
        <p>Use your available balance to make this payment.</p>
        <div class="input-group">
            <label for="amountToPay">Amount to Pay (PHP)</label>
            <input type="number" id="amountToPay" min="1" step="0.01" readonly>
        </div>
    </div>
    
    <div class="payment-method-content" id="externalPaymentContent">
        <p>Pay using bank transfer or other external payment method.</p>
        <div class="input-group">
            <label for="externalAmountToPay">Amount to Pay (PHP)</label>
            <input type="number" id="externalAmountToPay" min="1" step="0.01" readonly>
        </div>
        <div class="input-group">
            <label for="paymentProof">Payment Proof</label>
            <input type="file" id="paymentProof" accept="image/*" onchange="previewImage(this)">
            <small>Upload proof of payment (image file)</small>
        </div>
        <div class="preview-container" id="imagePreview">
            <img id="previewImg" src="#" alt="Preview">
        </div>
    </div>
</div>