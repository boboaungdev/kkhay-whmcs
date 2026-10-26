<?php
/**
 * K Khay Sovereign Crypto Gateway for WHMCS
 *
 * Accept non-custodial and custodial crypto payments (USDT, USDC, BNB, ETH
 * on BSC, Polygon, Arbitrum, Base, Ethereum) with instant verification and zero chargebacks.
 *
 * @package    Kkhay_WHMCS
 * @author     K Khay <support@kkhay.com>
 * @license    MIT
 * @link       https://kkhay.com
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/kkhay/lib/Client.php';

/**
 * Define gateway metadata.
 *
 * @return array
 */
function kkhay_MetaData(): array
{
    return [
        'DisplayName' => 'K Khay – Sovereign Crypto Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

/**
 * Define gateway configuration options.
 *
 * @return array
 */
function kkhay_config(): array
{
    $systemUrl = '';
    if (function_exists('App')) {
        try {
            $systemUrl = \App::getSystemUrl();
        } catch (\Throwable $e) {
            $systemUrl = '';
        }
    }
    if (empty($systemUrl)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $systemUrl = $protocol . $host . '/';
    }
    $callbackUrl = rtrim($systemUrl, '/') . '/modules/gateways/callback/kkhay.php';

    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'K Khay (Crypto: USDT, USDC, BNB, ETH)',
        ],
        'apiKey' => [
            'FriendlyName' => 'Gateway API Key',
            'Type' => 'text',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Your K Khay API Key from Gateway Dashboard &rarr; API Keys',
        ],
        'webhookSecret' => [
            'FriendlyName' => 'Webhook / IPN Secret',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Used to verify HMAC-SHA256 signatures of payment notifications',
        ],
        'apiBaseUrl' => [
            'FriendlyName' => 'API Base URL',
            'Type' => 'text',
            'Size' => '50',
            'Default' => 'https://api.kkhay.com',
            'Description' => 'Default: <code>https://api.kkhay.com</code> (or custom self-hosted gateway URL)',
        ],
        'webhookInfo' => [
            'FriendlyName' => 'Webhook / IPN URL',
            'Type' => 'info',
            'Description' => '<code>' . htmlspecialchars($callbackUrl) . '</code><br>' .
                             '<small style="color:#6b7280;">Copy this URL and paste it into your K Khay Gateway Dashboard under Webhook Settings.</small>',
        ],
    ];
}

/**
 * Generate the payment checkout button / link on viewinvoice.php.
 *
 * @param array $params Gateway module parameters
 * @return string HTML code for payment link
 */
function kkhay_link(array $params): string
{
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $returnUrl = $params['returnurl'];
    $callbackUrl = $systemUrl . '/modules/gateways/callback/kkhay.php';

    $apiKey = trim($params['apiKey'] ?? '');
    $apiBaseUrl = trim($params['apiBaseUrl'] ?? 'https://api.kkhay.com');
    if (empty($apiBaseUrl)) {
        $apiBaseUrl = 'https://api.kkhay.com';
    }

    if (empty($apiKey)) {
        return '<div class="alert alert-danger" style="margin:10px 0;border-radius:8px;">'
             . '<strong>K Khay:</strong> Gateway is active but Gateway API Key is not configured. Please configure in WHMCS Admin Area.'
             . '</div>';
    }

    $client = new \Kkhay\Whmcs\Client($apiKey, $apiBaseUrl);

    $customerName = trim(($params['clientdetails']['firstname'] ?? '') . ' ' . ($params['clientdetails']['lastname'] ?? ''));
    $customerEmail = trim($params['clientdetails']['email'] ?? '');

    $payload = [
        'orderId' => (string) $invoiceId,
        'amount' => (float) $amount,
        'priceAmount' => (float) $amount,
        'currency' => strtoupper($currency),
        'priceCurrency' => strtoupper($currency),
        'title' => 'Invoice #' . $invoiceId,
        'description' => 'Payment for WHMCS Invoice #' . $invoiceId,
        'callbackUrl' => $callbackUrl,
        'ipnCallbackUrl' => $callbackUrl,
        'returnUrl' => $returnUrl,
        'redirectUrl' => $returnUrl,
        'customerName' => $customerName,
        'customerEmail' => $customerEmail,
        'customer' => [
            'name' => $customerName,
            'email' => $customerEmail,
        ],
        'metadata' => [
            'source' => 'whmcs',
            'whmcsInvoiceId' => (string) $invoiceId,
            'clientEmail' => $customerEmail,
        ],
    ];

    try {
        $response = $client->createInvoice($payload);

        $checkoutUrl = $response['hostedUrl']
            ?? $response['checkoutUrl']
            ?? $response['paymentUrl']
            ?? ($response['invoice']['hostedUrl'] ?? null)
            ?? ($response['invoice']['checkoutUrl'] ?? null)
            ?? ($response['data']['hostedUrl'] ?? null)
            ?? ($response['data']['checkoutUrl'] ?? null)
            ?? ($response['data']['paymentUrl'] ?? null);

        if (empty($checkoutUrl)) {
            throw new \Exception('Missing checkout URL in API response.');
        }

        $badgeUrl = htmlspecialchars($systemUrl . '/modules/gateways/kkhay/assets/logo.svg');
        $safeUrl = htmlspecialchars($checkoutUrl);
        $safeAmount = htmlspecialchars(number_format((float) $amount, 2));
        $safeCurrency = htmlspecialchars($currency);

        return '
        <div class="kkhay-checkout-container" style="margin:16px 0;text-align:center;">
            <a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-lg kkhay-btn" style="
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                border: none;
                color: #ffffff;
                font-size: 15px;
                font-weight: 600;
                padding: 12px 24px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                gap: 12px;
                text-decoration: none;
                box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
                transition: all 0.2s ease-in-out;
            ">
                <span>Pay ' . $safeAmount . ' ' . $safeCurrency . ' with Crypto</span>
                <img src="' . $badgeUrl . '" alt="USDT, USDC, BNB, ETH" style="height:22px;vertical-align:middle;" />
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left:2px;">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </a>
            <div style="margin-top:8px;font-size:12px;color:#6b7280;display:flex;align-items:center;justify-content:center;gap:6px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                <span>Instant on-chain confirmation &bull; Zero chargebacks &bull; Non-custodial</span>
            </div>
        </div>';
    } catch (\Throwable $e) {
        if (function_exists('logTransaction')) {
            logTransaction('kkhay', ['payload' => $payload, 'error' => $e->getMessage()], 'Error Creating Invoice');
        }

        return '<div class="alert alert-danger" style="margin:10px 0;border-radius:8px;">'
             . '<strong>K Khay:</strong> Unable to initialize crypto checkout: ' . htmlspecialchars($e->getMessage())
             . '</div>';
    }
}

