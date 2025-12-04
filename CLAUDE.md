# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Overview

This is a WordPress installation with a custom WooCommerce payment plugin that integrates with the Monarch payment gateway for ACH bank transfers. The repository contains both the full WordPress installation and a custom payment plugin located in `wp-content/plugins/wordpress-payment/`.

## Plugin Architecture

The payment plugin consists of two main components:

### 1. WooCommerce Monarch ACH Gateway (`woocommerce-monarch-ach.php`)
The primary payment gateway plugin with these key components:
- **Main Plugin File**: `woocommerce-monarch-ach.php` - Plugin bootstrapper and WooCommerce integration
- **Payment Gateway**: `includes/class-wc-monarch-ach-gateway.php` - WooCommerce payment gateway implementation
- **API Integration**: `includes/class-monarch-api.php` - Monarch API client for ACH transactions
- **Admin Interface**: `includes/class-wc-monarch-admin.php` - Admin dashboard with transaction management
- **Logging System**: `includes/class-wc-monarch-logger.php` - API request/response logging

### 2. Generic Payment Plugin (`wordpress-payment.php`)
A basic payment framework (appears to be template/starting point).

## Database Configuration

**Database Name**: `payment-plug`  
**Charset**: `utf8mb4`  
**Table Prefix**: `wp_`

## Database Schema

### wp_monarch_ach_transactions
Stores ACH transaction records with the following structure:
- `order_id` - WooCommerce order ID
- `transaction_id` - Monarch transaction ID
- `monarch_org_id` - Customer organization ID in Monarch
- `paytoken_id` - Bank account token ID
- `amount`, `currency`, `status` - Transaction details
- `api_response` - Full API response for audit trail

## Monarch API Workflow

The plugin implements this complete customer and transaction flow:

1. **Create Organization** - Register new customers with personal/business details
2. **Create PayToken** - Add and verify bank account information
3. **Assign PayToken** - Link bank accounts to customer organizations
4. **Process Sale** - Execute ACH debit transactions

## Key Configuration Areas

### WooCommerce Settings
Payment gateway configuration is at:
**WooCommerce → Settings → Payments → Monarch ACH**

Required settings include:
- API credentials (Test/Live API Key, App ID, Merchant Org ID)
- Partner Name
- Test/Live mode toggle

### Admin Dashboard
Custom admin interface at:
**WooCommerce → Monarch ACH**

Provides tabs for:
- Transaction history and management
- Customer account status
- API connection testing
- Request/response logging

## Frontend Integration

### Checkout Process
- Bank account form appears during checkout when payment method is selected
- Customer information is collected (phone, DOB, bank details)
- Existing customers with linked accounts see simplified interface
- Form validation ensures required fields and data format compliance

### Customer Data Storage
Customer Monarch account data is stored in WordPress user meta:
- `_monarch_org_id` - Organization ID
- `_monarch_user_id` - User ID in Monarch system
- `_monarch_paytoken_id` - Bank account token

## Asset Management

Assets are organized by functionality:
- `assets/css/monarch-ach.css` - Frontend checkout styles
- `assets/css/monarch-admin.css` - Admin interface styles
- `assets/js/monarch-ach.js` - Frontend checkout interactions
- `assets/js/monarch-admin.js` - Admin dashboard functionality

## Development Environment

This is a standard WordPress installation. No special build process or development commands are required. Changes to PHP files take effect immediately.

### Plugin Activation
The plugin creates the required database table automatically on activation.

### Testing Mode
The plugin supports both sandbox and production environments through the test mode toggle in settings.

## Error Handling and Logging

The plugin includes comprehensive logging:
- All API requests/responses are logged with sensitive data masked
- Transaction events are tracked for audit purposes
- Admin interface provides log viewing capabilities
- WordPress debug mode integration for development

## Security Considerations

- All user input is sanitized and validated
- Banking information is masked in logs
- CSRF protection on all AJAX requests
- SSL/HTTPS enforcement for payment processing
- PCI compliance best practices followed

## Integration Points

### WordPress Hooks
The plugin uses standard WordPress/WooCommerce hooks:
- `woocommerce_payment_gateways` - Register payment gateway
- `woocommerce_update_options_payment_gateways_*` - Save settings
- Various AJAX hooks for customer/bank account management

### Custom Actions/Filters
- `woocommerce_monarch_ach_payment_complete` - After successful payment
- `woocommerce_monarch_ach_customer_created` - After customer registration
- `woocommerce_monarch_ach_bank_connected` - After bank account linking