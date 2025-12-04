# WooCommerce Monarch ACH Payment Gateway

A custom WordPress WooCommerce plugin that integrates the Monarch payment gateway for ACH transactions.

## Features

- **ACH Bank Transfers**: Secure bank-to-bank transfers via Monarch API
- **Customer Management**: Automatic customer creation and bank account linking
- **Transaction Processing**: Real-time payment processing with detailed logging
- **Admin Dashboard**: Comprehensive admin panel for transaction and customer management
- **Security**: PCI-compliant handling of sensitive banking information
- **Bank Connection Modal**: Built-in bank account connection interface
- **Test & Live Modes**: Full sandbox and production environment support

## Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/woocommerce-monarch-ach/`
3. Activate the plugin through the WordPress admin
4. Configure settings under WooCommerce → Settings → Payments → Monarch ACH

## Configuration

### Required API Credentials
- **API Key**: Your Monarch API key
- **App ID**: Your Monarch application ID  
- **Merchant Org ID**: Your merchant organization ID
- **Partner Name**: Your registered partner name

### Test Mode
Enable test mode to use sandbox credentials for development and testing.

## API Integration

The plugin implements the complete Monarch API workflow:

1. **Create Organization** - Register new customers
2. **Create PayToken** - Add bank account information
3. **Assign PayToken** - Link bank accounts to customers
4. **Process Sale** - Execute ACH transactions

## Required Fields

### Customer Information
- First Name, Last Name
- Email Address
- Phone Number
- Date of Birth
- Address (Street, City, State, ZIP, Country)
- Company Name (optional)

### Bank Account Information
- Bank Name
- Account Type (Checking/Savings)
- Routing Number (9 digits)
- Account Number
- Account Number Confirmation

## Admin Features

### Transactions Dashboard
- View all ACH transactions
- Filter by status and date
- Export transaction reports
- View detailed transaction information

### Customer Management
- List customers with Monarch accounts
- View bank connection status
- Manage customer data

### API Settings
- Test API connection
- Configure credentials
- Switch between test/live modes

### Logging System
- Detailed API request/response logging
- Transaction event tracking
- Error monitoring
- Sensitive data masking

## File Structure

```
woocommerce-monarch-ach/
├── woocommerce-monarch-ach.php        # Main plugin file
├── includes/
│   ├── class-wc-monarch-ach-gateway.php   # Payment gateway class
│   ├── class-monarch-api.php              # API integration
│   ├── class-wc-monarch-admin.php         # Admin interface
│   └── class-wc-monarch-logger.php        # Logging system
├── assets/
│   ├── css/
│   │   ├── monarch-ach.css               # Frontend styles
│   │   └── monarch-admin.css             # Admin styles
│   └── js/
│       ├── monarch-ach.js                # Frontend scripts
│       └── monarch-admin.js              # Admin scripts
└── README.md
```

## Database Tables

### wp_monarch_ach_transactions
Stores transaction data and API responses for audit trail and reporting.

## Security Features

- **Data Sanitization**: All input data is sanitized and validated
- **Sensitive Data Masking**: Banking information is masked in logs
- **Nonce Verification**: CSRF protection for all AJAX requests
- **SSL Requirements**: Enforces HTTPS for payment processing
- **PCI Compliance**: Follows best practices for handling payment data

## Hooks and Filters

### Actions
- `woocommerce_monarch_ach_payment_complete` - After successful payment
- `woocommerce_monarch_ach_customer_created` - After customer registration
- `woocommerce_monarch_ach_bank_connected` - After bank account linking

### Filters
- `woocommerce_monarch_ach_api_timeout` - Modify API timeout
- `woocommerce_monarch_ach_customer_data` - Filter customer data before API call
- `woocommerce_monarch_ach_transaction_data` - Filter transaction data

## Troubleshooting

### Common Issues

1. **API Connection Failed**
   - Verify API credentials in settings
   - Check if test/live mode is correctly configured
   - Ensure server can make outbound HTTPS requests

2. **Payment Processing Errors**
   - Check API logs in admin dashboard
   - Verify customer has valid bank account connected
   - Confirm merchant org ID is correct

3. **Bank Connection Issues**
   - Ensure routing number is valid (9 digits)
   - Verify account numbers match
   - Check if bank supports ACH transfers

### Debug Mode
Enable WordPress debug mode and check logs under WooCommerce → Monarch ACH → Logs.

## API Documentation

Full Monarch API documentation available at: https://developer.monarch.is/docs/

## Support

For technical support and questions, please contact your Monarch integration team.

## License

GPL v2 or later

## Changelog

### Version 1.0.0
- Initial release
- Complete Monarch API integration
- Admin dashboard
- Transaction logging
- Customer management
- Bank connection modal