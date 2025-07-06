<?php
/**
 * Mobile Money Payment Form Template
 * 
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Safely get options
$options = get_option('tutor_zoyktech_options', array());
$currency = isset($options['zoyktech_currency']) ? $options['zoyktech_currency'] : 'ZMW';

// Format price
$currency_symbols = array(
    'ZMW' => 'K',
    'USD' => '$'
);
$symbol = isset($currency_symbols[$currency]) ? $currency_symbols[$currency] : $currency;
$formatted_price = $symbol . number_format($price, 2);
?>

<div class="tutor-zoyktech-payment-section">
    <div class="tutor-zoyktech-payment-card">
        <div class="payment-header">
            <h3 class="payment-title">
                <span class="payment-icon">📱</span>
                <?php _e('Mobile Money Payment', 'tutor-zoyktech'); ?>
            </h3>
            <div class="course-price">
                <span class="price-amount"><?php echo esc_html($formatted_price); ?></span>
                <span class="price-currency"><?php echo esc_html($currency); ?></span>
            </div>
        </div>

        <div class="payment-description">
            <p><?php _e('Pay securely with your mobile money account to get instant access to this course.', 'tutor-zoyktech'); ?></p>
        </div>

        <form id="tutor-zoyktech-payment-form" class="tutor-payment-form">
            <?php wp_nonce_field('tutor_zoyktech_nonce', 'nonce'); ?>
            <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>">
            <input type="hidden" name="action" value="tutor_zoyktech_payment">

            <div class="form-group">
                <label for="zoyktech-phone-number" class="form-label">
                    <span class="label-text"><?php _e('Mobile Money Number', 'tutor-zoyktech'); ?></span>
                    <span class="required-indicator">*</span>
                </label>
                <div class="phone-input-wrapper">
                    <input 
                        type="tel" 
                        id="zoyktech-phone-number" 
                        name="phone_number" 
                        class="form-control phone-input"
                        placeholder="+260971234567"
                        required
                        pattern="^\+260[0-9]{9}$"
                        title="<?php esc_attr_e('Please enter a valid Zambian mobile number with country code', 'tutor-zoyktech'); ?>"
                    >
                    <div class="input-icon">📞</div>
                </div>
                <small class="form-help">
                    <?php _e('Enter your mobile money number with country code (e.g., +260971234567)', 'tutor-zoyktech'); ?>
                </small>
            </div>

            <div class="form-group">
                <label for="zoyktech-provider" class="form-label">
                    <span class="label-text"><?php _e('Mobile Money Provider', 'tutor-zoyktech'); ?></span>
                </label>
                <div class="provider-select-wrapper">
                    <select id="zoyktech-provider" name="provider_id" class="form-control provider-select">
                        <option value=""><?php _e('Auto-detect from phone number', 'tutor-zoyktech'); ?></option>
                        <option value="289" data-prefix="+260 95,96,97">
                            🟠 <?php _e('Airtel Money', 'tutor-zoyktech'); ?>
                        </option>
                        <option value="237" data-prefix="+260 76,77">
                            🟡 <?php _e('MTN Mobile Money', 'tutor-zoyktech'); ?>
                        </option>
                    </select>
                    <div class="select-icon">⬇️</div>
                </div>
                <small class="form-help">
                    <?php _e('Provider will be automatically detected from your phone number', 'tutor-zoyktech'); ?>
                </small>
            </div>

            <div class="payment-summary">
                <div class="summary-row">
                    <span class="summary-label"><?php _e('Course:', 'tutor-zoyktech'); ?></span>
                    <span class="summary-value"><?php echo esc_html(get_the_title($course_id)); ?></span>
                </div>
                <div class="summary-row total-row">
                    <span class="summary-label"><?php _e('Total Amount:', 'tutor-zoyktech'); ?></span>
                    <span class="summary-value total-amount"><?php echo esc_html($formatted_price); ?></span>
                </div>
            </div>

            <div class="payment-actions">
                <button type="submit" class="btn btn-primary btn-pay" id="tutor-zoyktech-pay-btn">
                    <span class="btn-icon">💳</span>
                    <span class="btn-text"><?php _e('Pay Now', 'tutor-zoyktech'); ?></span>
                    <span class="btn-amount"><?php echo esc_html($formatted_price); ?></span>
                </button>
            </div>

            <div class="payment-security">
                <div class="security-badges">
                    <span class="security-badge">
                        <span class="badge-icon">🔒</span>
                        <span class="badge-text"><?php _e('Secure Payment', 'tutor-zoyktech'); ?></span>
                    </span>
                    <span class="security-badge">
                        <span class="badge-icon">⚡</span>
                        <span class="badge-text"><?php _e('Instant Access', 'tutor-zoyktech'); ?></span>
                    </span>
                    <span class="security-badge">
                        <span class="badge-icon">📱</span>
                        <span class="badge-text"><?php _e('Mobile Friendly', 'tutor-zoyktech'); ?></span>
                    </span>
                </div>
            </div>
        </form>

        <div id="tutor-zoyktech-payment-status" class="payment-status" style="display: none;">
            <div class="status-content">
                <div class="status-icon"></div>
                <div class="status-message"></div>
                <div class="status-details"></div>
            </div>
        </div>
    </div>

    <div class="payment-info-section">
        <div class="info-card">
            <h4 class="info-title">
                <span class="info-icon">ℹ️</span>
                <?php _e('How Mobile Money Payment Works', 'tutor-zoyktech'); ?>
            </h4>
            <ol class="payment-steps">
                <li>
                    <span class="step-number">1</span>
                    <span class="step-text"><?php _e('Enter your mobile money number above', 'tutor-zoyktech'); ?></span>
                </li>
                <li>
                    <span class="step-number">2</span>
                    <span class="step-text"><?php _e('Click "Pay Now" to initiate payment', 'tutor-zoyktech'); ?></span>
                </li>
                <li>
                    <span class="step-number">3</span>
                    <span class="step-text"><?php _e('Check your phone for payment prompt', 'tutor-zoyktech'); ?></span>
                </li>
                <li>
                    <span class="step-number">4</span>
                    <span class="step-text"><?php _e('Confirm payment on your mobile device', 'tutor-zoyktech'); ?></span>
                </li>
                <li>
                    <span class="step-number">5</span>
                    <span class="step-text"><?php _e('Get instant access to the course!', 'tutor-zoyktech'); ?></span>
                </li>
            </ol>
        </div>

        <div class="supported-providers">
            <h4 class="providers-title"><?php _e('Supported Mobile Money Providers', 'tutor-zoyktech'); ?></h4>
            <div class="providers-list">
                <div class="provider-item">
                    <span class="provider-logo">🟠</span>
                    <span class="provider-name"><?php _e('Airtel Money', 'tutor-zoyktech'); ?></span>
                    <span class="provider-numbers">+260 95, 96, 97</span>
                </div>
                <div class="provider-item">
                    <span class="provider-logo">🟡</span>
                    <span class="provider-name"><?php _e('MTN Mobile Money', 'tutor-zoyktech'); ?></span>
                    <span class="provider-numbers">+260 76, 77</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Inline styles for immediate visual feedback - will be moved to CSS file */
.tutor-zoyktech-payment-section {
    max-width: 600px;
    margin: 20px auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.tutor-zoyktech-payment-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    padding: 24px;
    margin-bottom: 20px;
    border: 1px solid #e1e5e9;
}

.payment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f0f0f0;
}

.payment-title {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 8px;
}

.course-price {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 18px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #34495e;
}

.required-indicator {
    color: #e74c3c;
    margin-left: 4px;
}

.phone-input-wrapper,
.provider-select-wrapper {
    position: relative;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 16px;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.phone-input {
    padding-right: 50px;
    font-family: monospace;
    letter-spacing: 1px;
}

.input-icon,
.select-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    pointer-events: none;
}

.form-help {
    display: block;
    margin-top: 6px;
    color: #7f8c8d;
    font-size: 14px;
}

.payment-summary {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
    margin: 20px 0;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.summary-row:last-child {
    margin-bottom: 0;
}

.total-row {
    border-top: 1px solid #dee2e6;
    padding-top: 8px;
    font-weight: 600;
    font-size: 16px;
}

.btn-pay {
    width: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 16px 24px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-pay:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.btn-pay:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.security-badges {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 16px;
    flex-wrap: wrap;
}

.security-badge {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #7f8c8d;
    font-size: 12px;
}

.payment-steps {
    list-style: none;
    padding: 0;
    margin: 0;
}

.payment-steps li {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
    padding: 8px 0;
}

.step-number {
    background: #667eea;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

.providers-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.provider-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
}

.provider-logo {
    font-size: 20px;
}

.provider-name {
    font-weight: 500;
    flex: 1;
}

.provider-numbers {
    color: #7f8c8d;
    font-size: 14px;
}

@media (max-width: 768px) {
    .tutor-zoyktech-payment-section {
        margin: 10px;
    }
    
    .payment-header {
        flex-direction: column;
        gap: 12px;
        text-align: center;
    }
    
    .security-badges {
        flex-direction: column;
        gap: 8px;
    }
    
    .providers-list {
        gap: 8px;
    }
    
    .provider-item {
        padding: 8px;
    }
}
</style>

