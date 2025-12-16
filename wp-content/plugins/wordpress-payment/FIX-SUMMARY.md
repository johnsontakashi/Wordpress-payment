# Fix Summary: "Invalid request headers for this org_Id" Error

## Problem Identified ✅

The error **"Invalid request headers for this org_Id"** was caused by purchaser organizations being created as **standalone organizations** instead of **child organizations** under the merchant account.

### Root Cause

In [class-monarch-api.php:39](includes/class-monarch-api.php#L39), the `parentOrgId` was hardcoded to an empty string:

```php
'parentOrgId' => '',  // ❌ WRONG - creates standalone org
```

This meant:
1. When creating a purchaser organization, it had NO parent
2. When calling `getLatestPayToken` with merchant credentials, Monarch returned 404 because the organization didn't belong to the merchant account
3. The merchant API credentials couldn't access organizations that weren't their children

### Evidence from Logs

From the debug logs at `http://localhost/payment/wp-content/plugins/wordpress-payment/test-credentials-debug.php`:

**Organization Creation Response (18:51:41)**:
```json
{
  "orgId": "5889495783",
  "parentOrgId": "",  // ❌ Empty - not linked to merchant!
  "orgType": "PURCHASER"
}
```

**getLatestPayToken Call (18:52:54)**:
```json
{
  "error": {
    "statusCode": 404,
    "message": "Invalid request headers for this org_Id"
  }
}
```

The merchant credentials (ending in `d361` and `843f`) couldn't access organization `5889495783` because it wasn't a child of merchant org `5253091918`.

## Solution Applied ✅

Changed [class-monarch-api.php:39](includes/class-monarch-api.php#L39) to:

```php
'parentOrgId' => $this->merchant_org_id,  // ✅ CORRECT - creates child org
```

Now purchaser organizations are created as **child organizations** under the merchant account, allowing the merchant credentials to query them via `getLatestPayToken`.

## Files Modified

1. **[includes/class-monarch-api.php](includes/class-monarch-api.php)** - Line 39
   - Changed `parentOrgId` from empty string to `$this->merchant_org_id`

2. **[includes/class-wc-monarch-ach-gateway.php](includes/class-wc-monarch-ach-gateway.php)** - Lines 462-470, 721-729
   - Added comprehensive debug logging to track credentials and parent org ID

3. **[test-credentials-debug.php](test-credentials-debug.php)** - New file
   - Created debug page to view logs and verify credentials

## Testing Instructions

### Before Testing
Make sure your `wp-config.php` has debug mode enabled:
```php
define('WP_DEBUG', true);
```

### Test Steps

1. **Clear any cached data** - Start fresh checkout flow

2. **Go through checkout**:
   - Add product to cart
   - Go to checkout page
   - Fill in billing details
   - Select "ACH/Bank Transfer" payment method
   - Fill in required fields (phone, DOB, company name)
   - Click "Connect Bank Account"

3. **Bank linking flow**:
   - Modal opens with embedded Monarch/Yodlee iframe
   - Search and select a test bank (use "Bank of America" or "Chase")
   - Complete bank linking
   - Click "I've Connected My Bank" button

4. **Verify success**:
   - Should see "Bank account connected successfully" message
   - Modal should close
   - Payment form should show connected bank status
   - Can complete checkout

5. **Check debug logs**:
   - Visit: `http://localhost/payment/wp-content/plugins/wordpress-payment/test-credentials-debug.php`
   - Look for organization creation in logs
   - Verify `parentOrgId` is now set to your merchant org ID (5253091918)
   - Verify `getLatestPayToken` call succeeds

### Expected Log Output

**Organization Creation** (should now show):
```json
{
  "orgId": "58xxxxx",
  "parentOrgId": "5253091918",  // ✅ Now has parent!
  "orgType": "PURCHASER"
}
```

**getLatestPayToken Call** (should now succeed):
```json
{
  "success": true,
  "data": {
    "_id": "...",
    "paytoken_id": "..."
  }
}
```

## Alternative Test Using Test File

You can also test using the automated test file:

```
http://localhost/payment/wp-content/plugins/wordpress-payment/test-getlatestpaytoken.php
```

This will:
1. Create a test organization (now with correct parentOrgId)
2. Call getLatestPayToken
3. Show detailed results

Expected behavior:
- Organization creation: ✓ Success
- Organization should have `parentOrgId: "5253091918"`
- getLatestPayToken: May return 404 if no bank linked yet (this is expected)
- After bank linking in real flow, getLatestPayToken should return the paytoken

## Technical Explanation

### Monarch Organization Hierarchy

```
Merchant Organization (5253091918)
├── API Credentials: ...d361 / ...843f
└── Purchaser Organizations (created during checkout)
    ├── Org 1 (parentOrgId: 5253091918)
    ├── Org 2 (parentOrgId: 5253091918)
    └── Org 3 (parentOrgId: 5253091918)
```

### Why This Matters

1. **Merchant credentials can only query child orgs**: When you call `/v1/getlatestpaytoken/[orgId]` with merchant credentials, Monarch checks if that org is a child of the merchant
2. **Without parentOrgId set**: The org is standalone and not accessible by merchant credentials
3. **With parentOrgId set**: The org is a child and the merchant can query its paytokens

### API Flow

1. **POST /v1/organization** with `parentOrgId: "5253091918"`
   - Creates purchaser org as child of merchant
   - Returns org ID and embedded bank linking URL

2. **User links bank** through embedded iframe
   - Yodlee handles bank connection
   - Paytoken is created automatically

3. **GET /v1/getlatestpaytoken/[orgId]** with merchant credentials
   - Now works because org is a child of merchant
   - Returns the paytoken ID

4. **POST /v1/transaction/sale** with paytoken
   - Processes payment using the linked bank account

## Version Update

After confirming the fix works, bump the version in `woocommerce-monarch-ach.php`:

```php
define('WC_MONARCH_ACH_VERSION', '1.1.1'); // Bug fix for parentOrgId
```

## Additional Notes

- This fix is critical for the embedded bank linking flow to work
- Without this fix, all `getLatestPayToken` calls will fail with "Invalid request headers"
- The purchaser org's own API credentials (returned in the response) are NOT used for getLatestPayToken - merchant credentials are used
- Each purchaser org gets its own credentials, but those are for future transactions, not for querying paytokens
