/**
 * Zoyktech WooCommerce Checkout JavaScript
 */

(function($) {
    'use strict';

    // Zoyktech Checkout Handler
    class ZoyktechCheckout {
        constructor() {
            this.phoneInput = null;
            this.providerSelect = null;
            
            this.init();
        }

        init() {
            // Initialize when payment method changes
            $(document.body).on('updated_checkout', this.handleCheckoutUpdate.bind(this));
            
            // Initialize immediately if Zoyktech is already selected
            if ($('input[name="payment_method"]:checked').val() === 'zoyktech') {
                this.initializeFields();
            }
        }

        handleCheckoutUpdate() {
            // Re-initialize fields when checkout updates
            if ($('input[name="payment_method"]:checked').val() === 'zoyktech') {
                this.initializeFields();
            }
        }

        initializeFields() {
            this.phoneInput = $('#zoyktech-phone-number');
            this.providerSelect = $('#zoyktech-provider');

            if (this.phoneInput.length && this.providerSelect.length) {
                this.bindEvents();
            }
        }

        bindEvents() {
            // Auto-detect provider from phone number
            this.phoneInput.on('input', this.handlePhoneInput.bind(this));
            
            // Format phone number as user types
            this.phoneInput.on('input', this.formatPhoneNumber.bind(this));
            
            // Provider selection feedback
            this.providerSelect.on('change', this.handleProviderChange.bind(this));
            
            // Validation on blur
            this.phoneInput.on('blur', this.validatePhoneNumber.bind(this));
        }

        handlePhoneInput() {
            const phoneNumber = this.phoneInput.val();
            const provider = this.detectProvider(phoneNumber);
            
            // Update provider selection
            if (provider) {
                this.providerSelect.val(provider.id);
                this.highlightProvider(provider.name.toLowerCase());
                this.showProviderDetected(provider.name);
            } else {
                this.providerSelect.val('');
                this.clearProviderHighlight();
            }
        }

        formatPhoneNumber() {
            let value = this.phoneInput.val().replace(/[^\d+]/g, '');
            
            // Ensure it starts with +260 for Zambian numbers
            if (value.length > 0 && !value.startsWith('+')) {
                if (value.startsWith('260')) {
                    value = '+' + value;
                } else if (value.startsWith('0')) {
                    value = '+260' + value.substring(1);
                } else if (/^[79]/.test(value)) {
                    value = '+260' + value;
                }
            }
            
            this.phoneInput.val(value);
        }

        detectProvider(phoneNumber) {
            const cleaned = phoneNumber.replace(/[^\d+]/g, '');
            
            if (/^\+260(95|96|97)/.test(cleaned)) {
                return { id: '289', name: 'Airtel' };
            } else if (/^\+260(76|77)/.test(cleaned)) {
                return { id: '237', name: 'MTN' };
            }
            
            return null;
        }

        highlightProvider(providerName) {
            this.providerSelect.removeClass('provider-airtel provider-mtn');
            
            if (providerName === 'airtel') {
                this.providerSelect.addClass('provider-airtel');
            } else if (providerName === 'mtn') {
                this.providerSelect.addClass('provider-mtn');
            }
        }

        clearProviderHighlight() {
            this.providerSelect.removeClass('provider-airtel provider-mtn');
        }

        showProviderDetected(providerName) {
            this.phoneInput.addClass('provider-detected');
            
            // Show temporary notification
            this.showNotification(
                zoyktechCheckout.messages.provider_detected + ': ' + providerName,
                'success'
            );
        }

        validatePhoneNumber() {
            const phoneNumber = this.phoneInput.val();
            
            if (!phoneNumber) {
                return; // Empty is okay, required validation will handle it
            }
            
            if (!this.isValidPhoneNumber(phoneNumber)) {
                this.showFieldError(this.phoneInput, zoyktechCheckout.messages.phone_invalid);
                return false;
            }
            
            this.clearFieldError(this.phoneInput);
            return true;
        }

        isValidPhoneNumber(phoneNumber) {
            const cleaned = phoneNumber.replace(/[^\d+]/g, '');
            return /^\+260[0-9]{9}$/.test(cleaned);
        }

        showFieldError(field, message) {
            field.addClass('zoyktech-error');
            
            // Remove existing error message
            field.siblings('.zoyktech-error-message').remove();
            
            // Add new error message
            field.after('<span class="zoyktech-error-message">' + message + '</span>');
        }

        clearFieldError(field) {
            field.removeClass('zoyktech-error');
            field.siblings('.zoyktech-error-message').remove();
        }

        showNotification(message, type = 'info') {
            // Create notification element
            const notification = $('<div class="zoyktech-notification zoyktech-' + type + '">' + message + '</div>');
            
            // Add to checkout form
            $('.woocommerce-checkout-payment').prepend(notification);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }

        handleProviderChange() {
            const selectedProvider = this.providerSelect.val();
            
            if (selectedProvider === '289') {
                this.highlightProvider('airtel');
            } else if (selectedProvider === '237') {
                this.highlightProvider('mtn');
            } else {
                this.clearProviderHighlight();
            }
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new ZoyktechCheckout();
        
        // Add custom validation to WooCommerce checkout
        $(document.body).on('checkout_error', function() {
            // Re-validate our fields when checkout fails
            const zoyktechCheckout = new ZoyktechCheckout();
            if ($('input[name="payment_method"]:checked').val() === 'zoyktech') {
                zoyktechCheckout.initializeFields();
            }
        });
    });

})(jQuery);

// Add notification styles
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        .zoyktech-notification {
            padding: 12px 16px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .zoyktech-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .zoyktech-info {
            background: #cce5ff;
            color: #004085;
            border: 1px solid #99d6ff;
        }
        
        .zoyktech-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .zoyktech-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    `;
    document.head.appendChild(style);
});