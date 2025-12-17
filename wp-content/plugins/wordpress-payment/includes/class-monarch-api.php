<?php

if (!defined('ABSPATH')) {
    exit;
}

class Monarch_API {
    
    private $api_key;
    private $app_id;
    private $base_url;
    private $merchant_org_id;
    private $partner_name;
    
    public function __construct($api_key, $app_id, $merchant_org_id, $partner_name, $sandbox = true) {
        $this->api_key = $api_key;
        $this->app_id = $app_id;
        $this->merchant_org_id = $merchant_org_id;
        $this->partner_name = $partner_name;
        $this->base_url = $sandbox ? 'https://devapi.monarch.is/v1' : 'https://api.monarch.is/v1';
    }
    
    /**
     * Create a new organization (customer)
     */
    public function create_organization($customer_data) {
        $url = $this->base_url . '/organization';

        $data = array(
            'first_name' => $customer_data['first_name'],
            'last_name' => $customer_data['last_name'],
            'email' => $customer_data['email'],
            'password' => $customer_data['password'],
            'odfi_endpoint' => 'ODFI210',
            'orgType' => 'purchaser',
            'originationClient' => 'partner_app',
            'partnerName' => $this->partner_name,
            'authType' => '',
            'parentOrgId' => $this->merchant_org_id, // Set merchant as parent
            'user_metadata' => array(
                'phone' => $customer_data['phone'],
                'companyName' => $customer_data['company_name'],
                'dob' => $customer_data['dob'],
                'add1' => $customer_data['address_1'],
                'add2' => $customer_data['address_2'],
                'city' => $customer_data['city'],
                'state' => $customer_data['state'],
                'zip' => $customer_data['zip'],
                'country' => $customer_data['country']
            )
        );

        return $this->make_request('POST', $url, $data);
    }
    
    /**
     * Create PayToken (add bank account)
     */
    public function create_paytoken($user_id, $bank_data) {
        $url = $this->base_url . '/paytoken';
        
        $data = array(
            'pay_type' => 'Helox',
            'bankName' => $bank_data['bank_name'],
            'userId' => $user_id,
            'dda' => $bank_data['account_number'],
            'routing' => $bank_data['routing_number'],
            'accountId' => $bank_data['account_number'],
            'providerAccountId' => $bank_data['account_number'],
            'accountType' => strtoupper($bank_data['account_type']),
            'currentBalance' => array(
                'currency' => 'USD',
                'amount' => 0
            ),
            'yodlee' => true,
            'networkId' => '',
            'cc_account_number' => '',
            'cc_card_number' => '',
            'cvv' => '',
            'cc_expiration_month' => '',
            'cc_expiration_year' => ''
        );
        
        return $this->make_request('POST', $url, $data);
    }
    
    /**
     * Assign PayToken to organization
     */
    public function assign_paytoken($paytoken_id, $org_id) {
        $url = $this->base_url . '/organization/paytoken/assign';
        
        $data = array(
            'payTokenId' => $paytoken_id,
            'orgId' => $org_id
        );
        
        return $this->make_request('PUT', $url, $data);
    }
    
    /**
     * Process a sale transaction
     */
    public function create_sale_transaction($transaction_data) {
        $url = $this->base_url . '/transaction/sale';
        
        $data = array(
            'amount' => floatval($transaction_data['amount']),
            'orgId' => $transaction_data['org_id'],
            'comment' => $transaction_data['comment'],
            'service_origin' => 'partner_app',
            'partnerName' => $this->partner_name,
            'account_type' => 'C',
            'payTokenId' => $transaction_data['paytoken_id'],
            'subscription_plan_id' => '',
            'taxRemittance' => '',
            'merchantOrgId' => $this->merchant_org_id
        );
        
        return $this->make_request('POST', $url, $data);
    }
    
    /**
     * Get transaction status for ACH transactions
     * @param string $transaction_id The transaction ID to check
     * @return array Response with success status and transaction data
     */
    public function get_transaction_status($transaction_id) {
        $url = $this->base_url . '/transaction/status/' . $transaction_id;

        return $this->make_request('GET', $url);
    }

    /**
     * Get latest PayToken for an organization
     * Used after embedded bank linking to retrieve the paytoken
     * Per Monarch documentation: GET /v1/getlatestpaytoken/{organizationID}
     * @param string $org_id The organization ID (e.g., "5473522089")
     * @return array Response with success status and paytoken data
     */
    public function get_latest_paytoken($org_id) {
        // IMPORTANT: Use the correct endpoint /getlatestpaytoken/ NOT /organization/
        $url = $this->base_url . '/getlatestpaytoken/' . $org_id;

        // Log for debugging
        $logger = WC_Monarch_Logger::instance();
        $logger->debug('get_latest_paytoken called', array(
            'org_id' => $org_id,
            'full_url' => $url,
            'api_key_last_4' => substr($this->api_key, -4),
            'app_id' => $this->app_id
        ));

        return $this->make_request('GET', $url);
    }

    /**
     * Make HTTP request to Monarch API
     */
    private function make_request($method, $url, $data = array()) {
        $logger = WC_Monarch_Logger::instance();
        
        $headers = array(
            'accept' => 'application/json',
            'X-API-KEY' => $this->api_key,
            'X-APP-ID' => $this->app_id,
            'Content-Type' => 'application/json'
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true
        );
        
        if (!empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        // Log the request
        $logger->log_api_request($method, $url, $data, $headers);
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error = $response->get_error_message();
            $logger->log_api_error($url, $error);
            return array(
                'success' => false,
                'error' => $error
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);
        
        // Log the response
        $logger->log_api_response($url, $decoded_body, $status_code);
        
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'data' => $decoded_body
            );
        } else {
            // Provide user-friendly error messages for common HTTP errors
            $error = $decoded_body['error']['message'] ?? $decoded_body['message'] ?? null;

            if (!$error) {
                switch ($status_code) {
                    case 400:
                        $error = 'Invalid request. Please check your information and try again.';
                        break;
                    case 401:
                        $error = 'Authentication failed. Please contact support.';
                        break;
                    case 403:
                        $error = 'Access denied. Please contact support.';
                        break;
                    case 404:
                        $error = 'Resource not found.';
                        break;
                    case 429:
                        $error = 'Too many requests. Please wait a moment and try again.';
                        break;
                    case 500:
                        $error = 'Server error. Please try again in a few minutes.';
                        break;
                    case 502:
                        $error = 'Gateway error. The payment service is temporarily unavailable. Please try again in a few minutes.';
                        break;
                    case 503:
                        $error = 'Service temporarily unavailable. The Monarch payment server is currently down for maintenance. Please try again in a few minutes.';
                        break;
                    case 504:
                        $error = 'Gateway timeout. The payment service is taking too long to respond. Please try again.';
                        break;
                    default:
                        $error = 'API request failed (Error ' . $status_code . '). Please try again.';
                }
            }

            $logger->log_api_error($url, $error, $status_code);
            return array(
                'success' => false,
                'error' => $error,
                'status_code' => $status_code,
                'response' => $decoded_body
            );
        }
    }
    
    /**
     * Log API responses for debugging
     */
    public function log($message, $data = array()) {
        $logger = WC_Monarch_Logger::instance();
        $logger->debug($message, $data);
    }
}