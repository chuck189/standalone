/**
 * Tutor LMS Zoyktech Integration - Frontend JavaScript
 * Handles mobile money payment form interactions
 */

(function($) {
    'use strict';

    // Payment form handler
    class TutorZoyktechPayment {
        constructor() {
            this.form = $('#tutor-zoyktech-payment-form');
            this.payButton = $('#tutor-zoyktech-pay-btn');
            this.statusDiv = $('#tutor-zoyktech-payment-status');
            this.phoneInput = $('#zoyktech-phone-number');
            this.providerSelect = $('#zoyktech-provider');
            
            this.init();
        }

        init() {
            if (this.form.length === 0) return;

            // Bind events
            this.form.on('submit', this.handlePayment.bind(this));
            this.phoneInput.on('input', this.handlePhoneInput.bind(this));
            this.providerSelect.on('change', this.handleProviderChange.bind(this));

            // Auto-detect provider on phone input
            this.phoneInput.on('blur', this.autoDetectProvider.bind(this));

            // Format phone number as user types
            this.phoneInput.on('input', this.formatPhoneNumber.bind(this));
        }

        handlePayment(e) {
            e.preventDefault();

            if (this.isProcessing) {
                return false;
            }

            // Validate form
            if (!this.validateForm()) {
                return false;
            }

            this.startPayment();
        }

        validateForm() {
            const phoneNumber = this.phoneInput.val().trim();
            
            // Check if phone number is provided
            if (!phoneNumber) {
                this.showError(tutorZoyktech.messages.phone_required);
                this.phoneInput.focus();
                return false;
            }

            // Validate phone number format
            if (!this.isValidPhoneNumber(phoneNumber)) {
                this.showError(tutorZoyktech.messages.phone_invalid);
                this.phoneInput.focus();
                return false;
            }

            return true;
        }

        isValidPhoneNumber(phone) {
            // Remove all non-digit characters except +
            const cleaned = phone.replace(/[^\d+]/g, '');
            
            // Check if it's a valid Zambian number
            return /^\+260[0-9]{9}$/.test(cleaned);
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

        autoDetectProvider() {
            const phoneNumber = this.phoneInput.val().trim();
            
            if (!phoneNumber) return;

            // Auto-detect provider based on phone number
            if (/^\+260(95|96|97)/.test(phoneNumber)) {
                this.providerSelect.val('289'); // Airtel
                this.highlightProvider('airtel');
            } else if (/^\+260(76|77)/.test(phoneNumber)) {
                this.providerSelect.val('237'); // MTN
                this.highlightProvider('mtn');
            } else {
                this.providerSelect.val('');
                this.clearProviderHighlight();
            }
        }

        highlightProvider(provider) {
            this.providerSelect.removeClass('provider-airtel provider-mtn');
            this.providerSelect.addClass('provider-' + provider);
        }

        clearProviderHighlight() {
            this.providerSelect.removeClass('provider-airtel provider-mtn');
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

        startPayment() {
            this.isProcessing = true;
            this.updatePayButton('processing');
            this.showStatus('processing', tutorZoyktech.messages.processing);

            const formData = {
                action: 'tutor_zoyktech_payment',
                nonce: this.form.find('[name="nonce"]').val(),
                course_id: this.form.find('[name="course_id"]').val(),
                phone_number: this.phoneInput.val().trim(),
                provider_id: this.providerSelect.val()
            };

            $.ajax({
                url: tutorZoyktech.ajax_url,
                type: 'POST',
                data: formData,
                timeout: 30000,
                success: this.handlePaymentSuccess.bind(this),
                error: this.handlePaymentError.bind(this)
            });
        }

        handlePaymentSuccess(response) {
            this.isProcessing = false;

            if (response.success) {
                this.showStatus('success', tutorZoyktech.messages.success);
                this.updatePayButton('success');
                
                // Show payment details
                const details = `
                    <div class="payment-details">
                        <p><strong>Order ID:</strong> ${response.data.order_id}</p>
                        <p><strong>Provider:</strong> ${response.data.provider}</p>
                        <p class="payment-instruction">
                            📱 <strong>Check your mobile device for the payment prompt</strong>
                        </p>
                    </div>
                `;
                
                this.statusDiv.find('.status-details').html(details);

                // Start polling for payment status
                this.startStatusPolling(response.data.order_id);

            } else {
                this.handlePaymentError(response);
            }
        }

        handlePaymentError(response) {
            this.isProcessing = false;
            this.updatePayButton('error');

            let errorMessage = tutorZoyktech.messages.error;
            
            if (response.data && response.data.message) {
                errorMessage = response.data.message;
            } else if (response.responseJSON && response.responseJSON.data && response.responseJSON.data.message) {
                errorMessage = response.responseJSON.data.message;
            }

            this.showStatus('error', errorMessage);
        }

        startStatusPolling(orderId) {
            let pollCount = 0;
            const maxPolls = 60; // Poll for 5 minutes (5 second intervals)

            const pollInterval = setInterval(() => {
                pollCount++;

                if (pollCount > maxPolls) {
                    clearInterval(pollInterval);
                    this.showStatus('timeout', 'Payment timeout. Please check your payment status in your dashboard.');
                    return;
                }

                this.checkPaymentStatus(orderId, (status) => {
                    if (status === 'completed') {
                        clearInterval(pollInterval);
                        this.handlePaymentCompleted();
                    } else if (status === 'failed' || status === 'cancelled') {
                        clearInterval(pollInterval);
                        this.showStatus('error', 'Payment was not completed. Please try again.');
                        this.updatePayButton('default');
                    }
                });

            }, 5000); // Poll every 5 seconds
        }

        checkPaymentStatus(orderId, callback) {
            $.ajax({
                url: tutorZoyktech.ajax_url,
                type: 'POST',
                data: {
                    action: 'tutor_zoyktech_check_status',
                    nonce: tutorZoyktech.nonce,
                    order_id: orderId
                },
                success: function(response) {
                    if (response.success && response.data.status) {
                        callback(response.data.status);
                    }
                },
                error: function() {
                    // Silently fail status checks
                }
            });
        }

        handlePaymentCompleted() {
            this.showStatus('completed', '🎉 Payment completed successfully! You now have access to the course.');
            this.updatePayButton('completed');

            // Redirect to course or refresh page after a delay
            setTimeout(() => {
                window.location.reload();
            }, 3000);
        }

        updatePayButton(state) {
            const button = this.payButton;
            const btnText = button.find('.btn-text');
            const btnIcon = button.find('.btn-icon');

            button.removeClass('btn-processing btn-success btn-error btn-completed');

            switch (state) {
                case 'processing':
                    button.addClass('btn-processing').prop('disabled', true);
                    btnIcon.text('⏳');
                    btnText.text('Processing...');
                    break;

                case 'success':
                    button.addClass('btn-success').prop('disabled', true);
                    btnIcon.text('📱');
                    btnText.text('Check Your Phone');
                    break;

                case 'completed':
                    button.addClass('btn-completed').prop('disabled', true);
                    btnIcon.text('✅');
                    btnText.text('Payment Completed');
                    break;

                case 'error':
                    button.addClass('btn-error').prop('disabled', false);
                    btnIcon.text('❌');
                    btnText.text('Try Again');
                    break;

                default:
                    button.prop('disabled', false);
                    btnIcon.text('💳');
                    btnText.text('Pay Now');
                    break;
            }
        }

        showStatus(type, message) {
            const statusDiv = this.statusDiv;
            const statusIcon = statusDiv.find('.status-icon');
            const statusMessage = statusDiv.find('.status-message');

            statusDiv.removeClass('status-processing status-success status-error status-completed status-timeout');
            statusDiv.addClass('status-' + type);

            // Set appropriate icon
            const icons = {
                processing: '⏳',
                success: '📱',
                completed: '🎉',
                error: '❌',
                timeout: '⏰'
            };

            statusIcon.text(icons[type] || 'ℹ️');
            statusMessage.text(message);

            statusDiv.slideDown(300);
        }

        showError(message) {
            this.showStatus('error', message);
        }
    }

    // Provider detection helper
    class ProviderDetector {
        static detect(phoneNumber) {
            const cleaned = phoneNumber.replace(/[^\d+]/g, '');
            
            if (/^\+260(95|96|97)/.test(cleaned)) {
                return { id: 289, name: 'Airtel Money', color: '#ff6600' };
            } else if (/^\+260(76|77)/.test(cleaned)) {
                return { id: 237, name: 'MTN Mobile Money', color: '#ffcc00' };
            }
            
            return null;
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize payment form
        new TutorZoyktechPayment();

        // Add provider detection visual feedback
        $(document).on('input', '#zoyktech-phone-number', function() {
            const phoneNumber = $(this).val();
            const provider = ProviderDetector.detect(phoneNumber);
            const wrapper = $(this).closest('.phone-input-wrapper');
            
            wrapper.removeClass('provider-detected provider-airtel provider-mtn');
            
            if (provider) {
                wrapper.addClass('provider-detected');
                if (provider.id === 289) {
                    wrapper.addClass('provider-airtel');
                } else if (provider.id === 237) {
                    wrapper.addClass('provider-mtn');
                }
            }
        });

        // Add smooth scrolling to payment form
        if ($('#tutor-zoyktech-payment-form').length) {
            $('html, body').animate({
                scrollTop: $('#tutor-zoyktech-payment-form').offset().top - 100
            }, 500);
        }
    });

})(jQuery);

