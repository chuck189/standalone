# Tutor LMS Zoyktech Integration

A direct integration plugin that enables mobile money payments for Tutor LMS courses without requiring WooCommerce.

## 🎯 Features

### **Direct Course Payments**
- **No WooCommerce dependency** - Lightweight and fast
- **Mobile money integration** - Airtel Money & MTN Mobile Money
- **Instant enrollment** - Automatic course access after payment
- **Mobile-optimized** - Perfect for mobile money users

### **Student Experience**
- **Streamlined checkout** - One-click course purchase
- **Payment history dashboard** - Track all transactions
- **Receipt downloads** - PDF receipts for all payments
- **Real-time status** - Live payment status updates

### **Admin Features**
- **Payment management** - View all transactions
- **Course pricing** - Set individual course prices
- **Enrollment tracking** - Monitor paid enrollments
- **Revenue reporting** - Track course earnings

## 🚀 Installation

1. **Upload** the plugin folder to `/wp-content/plugins/`
2. **Activate** the plugin through WordPress admin
3. **Configure** Zoyktech API credentials in Tutor LMS settings
4. **Set course prices** in individual course settings
5. **Test** with a small payment to verify integration

## ⚙️ Configuration

### **API Settings**
```php
// In Tutor LMS Settings > Zoyktech Integration
Merchant ID: your_merchant_id
Public Key: your_public_key
Secret Key: your_secret_key
Environment: live (or sandbox for testing)
Currency: ZMW
```

### **Course Pricing**
1. Edit any course
2. Go to **Course Settings**
3. Set **Course Price (ZMW)**
4. Save the course

## 📱 Supported Providers

### **Airtel Money**
- **Phone Numbers**: +260 95, 96, 97
- **Provider ID**: 289
- **Auto-detection**: Automatic from phone number

### **MTN Mobile Money**
- **Phone Numbers**: +260 76, 77
- **Provider ID**: 237
- **Auto-detection**: Automatic from phone number

## 🔄 Payment Flow

1. **Student** visits course page
2. **Sees payment form** (if course has price)
3. **Enters phone number** (+260971234567)
4. **Provider auto-detected** (Airtel/MTN)
5. **Clicks "Pay Now"**
6. **Receives mobile prompt** on their device
7. **Confirms payment** on mobile
8. **Gets instant course access**

## 📊 Student Dashboard

### **Payment History Tab**
- **All transactions** with status and details
- **Receipt downloads** for completed payments
- **Payment statistics** and spending overview
- **Provider usage** tracking

### **Course Access**
- **Instant enrollment** after successful payment
- **Course progress** tracking
- **Certificate generation** (if enabled)

## 🛠️ Developer Features

### **Hooks & Filters**
```php
// After successful enrollment
do_action('tutor_zoyktech_after_enrollment', $course_id, $user_id);

// Before payment processing
apply_filters('tutor_zoyktech_before_payment', $payment_data);

// Custom payment validation
apply_filters('tutor_zoyktech_validate_payment', $is_valid, $payment_data);
```

### **Database Tables**
- **`wp_tutor_zoyktech_transactions`** - Payment records
- **Course meta** - `_tutor_course_price` for pricing
- **Enrollment meta** - `_tutor_zoyktech_paid_enrollment`

## 🎨 Customization

### **Payment Form Styling**
```css
/* Customize payment form appearance */
.tutor-zoyktech-payment-card {
    /* Your custom styles */
}

.btn-pay {
    /* Customize payment button */
}
```

### **Course Price Badges**
```css
/* Customize course price badges */
.tutor-course-price-badge {
    /* Your custom styles */
}
```

## 🔧 Troubleshooting

### **Common Issues**

#### **Payment Not Processing**
1. Check API credentials in settings
2. Verify phone number format (+260...)
3. Ensure course has a price set
4. Check error logs for details

#### **Mobile Prompt Not Received**
1. Verify phone number is correct
2. Check mobile money account balance
3. Ensure provider is supported
4. Try with different phone number

#### **Enrollment Not Working**
1. Check payment status in dashboard
2. Verify course exists and is published
3. Check user permissions
4. Review enrollment logs

### **Debug Mode**
```php
// Enable debug logging
define('TUTOR_ZOYKTECH_DEBUG', true);

// Check logs in:
// /wp-content/debug.log
// /wp-content/tutor-zoyktech-debug.log
```

## 📋 Requirements

### **WordPress**
- **Version**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher

### **Plugins**
- **Tutor LMS**: 2.0 or higher (required)
- **WooCommerce**: Not required

### **Server**
- **cURL**: Enabled for API calls
- **JSON**: PHP JSON extension
- **SSL**: HTTPS recommended for production

## 🔐 Security

### **API Security**
- **Signature verification** for all callbacks
- **Nonce protection** for AJAX requests
- **User authentication** for all operations
- **Data sanitization** for all inputs

### **Payment Security**
- **No card data storage** - Mobile money only
- **Encrypted API communication**
- **Secure callback handling**
- **Transaction logging** for audit trail

## 📈 Performance

### **Optimizations**
- **No WooCommerce overhead** - Faster loading
- **Minimal database queries** - Efficient operations
- **Cached provider detection** - Quick responses
- **Optimized mobile interface** - Fast mobile experience

### **Scalability**
- **Handles high transaction volume**
- **Efficient database design**
- **Minimal server resources**
- **CDN-friendly assets**

## 🆘 Support

### **Documentation**
- **Plugin documentation**: Available in `/docs` folder
- **API documentation**: Zoyktech API docs
- **Video tutorials**: Coming soon

### **Getting Help**
1. **Check troubleshooting** section above
2. **Review error logs** for specific issues
3. **Test in sandbox** environment first
4. **Contact support** with detailed error information

## 📝 Changelog

### **Version 1.0.0**
- Initial release
- Direct Tutor LMS integration
- Mobile money payment support
- Student dashboard with payment history
- Admin payment management
- Course pricing system
- Automatic enrollment
- Receipt generation

## 🔮 Roadmap

### **Upcoming Features**
- **Bulk course purchases** - Multiple courses at once
- **Subscription support** - Recurring course payments
- **Instructor payouts** - Revenue sharing
- **Advanced reporting** - Detailed analytics
- **Multi-currency** - Support for USD, EUR
- **Payment plans** - Installment payments

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🤝 Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📞 Contact

For support and inquiries:
- **Email**: support@example.com
- **Website**: https://example.com
- **Documentation**: https://docs.example.com

