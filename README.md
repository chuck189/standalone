# Tutor LMS Zoyktech WooCommerce Gateway

A complete WooCommerce payment gateway that enables mobile money payments for Tutor LMS courses via Zoyktech.

## 🎯 Features

### **WooCommerce Integration**
- **Seamless WooCommerce integration** - Works with existing WooCommerce setup
- **Mobile money integration** - Airtel Money & MTN Mobile Money
- **Automatic enrollment** - Students enrolled in courses after successful payment
- **Mobile-optimized** - Perfect for mobile money users

### **Student Experience**
- **Standard WooCommerce checkout** - Familiar shopping experience
- **Auto provider detection** - Automatically detects Airtel or MTN
- **Mobile payment prompts** - Secure mobile money authorization
- **Order management** - Full WooCommerce order history

### **Admin Features**
- **WooCommerce integration** - All standard WooCommerce features
- **Course-product linking** - Automatic or manual product creation
- **Order management** - Track payments and enrollments
- **Setup wizard** - Easy configuration

## 🚀 Installation

1. **Upload** the plugin folder to `/wp-content/plugins/`
2. **Activate** the plugin through WordPress admin
3. **Run setup wizard** - Follow the guided setup
4. **Configure API credentials** - Enter your Zoyktech details
5. **Create course products** - Link courses to WooCommerce products
6. **Test payments** - Use sandbox mode first

## ⚙️ Configuration

### **Setup Wizard**
1. Go to **Admin > Plugins** and activate the plugin
2. Click **"Run Setup Wizard"** in the admin notice
3. Follow the 4-step setup process:
   - Prerequisites check
   - API configuration
   - Course settings
   - Complete setup

### **Manual Configuration**
1. **WooCommerce Settings** > **Payments** > **Zoyktech**
2. **Enable** the payment method
3. **Enter API credentials** from your Zoyktech dashboard
4. **Set environment** (sandbox/live)
5. **Save settings**

## 📱 Supported Providers

- **Airtel Money** - All Airtel subscribers
- **MTN Mobile Money** - All MTN subscribers
- **Auto-detection** - Provider identified from phone number

## 🔄 Payment Flow

1. **Student** finds course and clicks **"Enroll"** or **"Purchase"**
2. **Added to cart** - Course product added to WooCommerce cart
3. **Checkout process** - Standard WooCommerce checkout
4. **Select Zoyktech** - Choose mobile money payment method
5. **Enter phone number** - Provider auto-detected
6. **Place order** - Order created, payment initiated
7. **Mobile prompt** - Payment authorization on phone
8. **Payment confirmed** - Order completed, course access granted

## 🛒 Course Products

### **Automatic Product Creation**
- **Enable in setup** - Auto-create products for paid courses
- **Course price sync** - Product price matches course price
- **Virtual products** - No shipping required
- **Instant delivery** - Course access after payment

### **Manual Product Linking**
1. **Create WooCommerce product** manually
2. **Edit product** > **Course Settings** meta box
3. **Select associated course** from dropdown
4. **Save product**

### **Product Management**
- **Standard WooCommerce** - All features available
- **Categories and tags** - Organize course products
- **Variable products** - Different course packages
- **Coupons and discounts** - WooCommerce promotions

## 🛠️ Developer Features

### **Action Hooks**
```php
// After successful course enrollment
do_action('tutor_zoyktech_course_enrollment_completed', $course_id, $user_id, $order_id);

// Filter course-product relationships
apply_filters('tutor_zoyktech_course_product_id', $product_id, $course_id);

// Customize auto-product creation
apply_filters('tutor_zoyktech_auto_product_data', $product_data, $course_id);
```

### **Database Tables**
- **WooCommerce orders** - Standard order management
- **`wp_zoyktech_payment_logs`** - Zoyktech-specific payment data
- **Product meta** - `_tutor_course_id` links products to courses
- **Course meta** - `_tutor_wc_product_id` links courses to products

## 🎨 Customization

### **Checkout Styling**
```css
/* Customize Zoyktech checkout fields */
.zoyktech-payment-fields {
    /* Your custom styles */
}

.zoyktech-supported-providers {
    /* Provider information styling */
}
```

### **Product Display**
```css
/* Customize course product display */
.product.course-product {
    /* Your custom styles */
}
```

## 🔧 Troubleshooting

### **Common Issues**

#### **Orders Not Completing**
1. Check **WooCommerce > Settings > Payments > Zoyktech**
2. Verify **API credentials** are correct
3. Check **webhook/callback URLs** are accessible
4. Review **WooCommerce > Status > Logs**

#### **Students Not Enrolled**
1. Check **order status** is "Completed"
2. Verify **course-product link** exists
3. Check **user account** is linked to order
4. Review **enrollment logs**

#### **Products Not Auto-Created**
1. Enable **auto-create products** in settings
2. Set **course price** in course settings
3. Check **user permissions** for product creation
4. Review **error logs**

### **Debug Mode**
```php
// Enable debug logging
// WooCommerce > Settings > Payments > Zoyktech > Enable logging

// Check logs in:
// WooCommerce > Status > Logs
// Select "zoyktech" log files
```

## 📋 Requirements

### **WordPress**
- **Version**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher

### **Plugins**
- **Tutor LMS**: 2.0 or higher (required)
- **WooCommerce**: 5.0 or higher (required)

### **Server**
- **cURL**: Required for API communication
- **SSL**: Required for live payments
- **Memory**: 128MB minimum

### **Zoyktech Account**
- **Merchant account** with Zoyktech
- **API credentials** (Merchant ID, Public Key, Secret Key)
- **Webhook URL** configured

## 🔮 Roadmap

### **Upcoming Features**
- **Subscription courses** - Recurring payments for ongoing access
- **Bulk discounts** - Volume pricing for multiple courses
- **Instructor commissions** - Revenue sharing with course creators
- **Advanced analytics** - Detailed sales and enrollment reports
- **Multi-currency** - Support for additional currencies
- **Payment instalments** - Split payments for expensive courses

## 📄 License