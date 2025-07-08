/**
 * Admin Payments JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';

    // Approve payment
    $('.approve-payment').on('click', function() {
        if (!confirm(tutorMobileMoney.messages.confirm_approve)) {
            return;
        }

        const button = $(this);
        const transactionId = button.data('id');
        
        button.prop('disabled', true).text('Approving...');
        
        $.ajax({
            url: tutorMobileMoney.ajax_url,
            type: 'POST',
            data: {
                action: 'approve_zoyktech_payment',
                nonce: tutorMobileMoney.nonce,
                transaction_id: transactionId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text('Approve');
                }
            },
            error: function() {
                alert('Request failed');
                button.prop('disabled', false).text('Approve');
            }
        });
    });

    // Reject payment
    $('.reject-payment').on('click', function() {
        if (!confirm(tutorMobileMoney.messages.confirm_reject)) {
            return;
        }

        const button = $(this);
        const transactionId = button.data('id');
        
        button.prop('disabled', true).text('Rejecting...');
        
        $.ajax({
            url: tutorMobileMoney.ajax_url,
            type: 'POST',
            data: {
                action: 'reject_zoyktech_payment',
                nonce: tutorMobileMoney.nonce,
                transaction_id: transactionId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text('Reject');
                }
            },
            error: function() {
                alert('Request failed');
                button.prop('disabled', false).text('Reject');
            }
        });
    });
});