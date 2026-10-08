<?php
/**
 * K Khay Webhook & IPN Callback Handler for WHMCS
 *
 * Receives cryptographic webhook notifications from K Khay,
 * validates HMAC-SHA256 signatures, prevents duplicate payments,
 * and marks WHMCS invoices as paid automatically.
 *
 * @package    Kkhay_WHMCS
 * @author     K Khay <support@kkhay.com>
 * @license    MIT
 * @link       https://kkhay.com
 */

// Initialize WHMCS framework
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Gateway module name
$gatewayModuleName = 'kkhay';
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if gateway is not active
if (!$gatewayParams['type']) {
    http_response_code(400);
    die('Module Not Activated');
}

// Retrieve raw payload
$rawPayload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_X_KKHAY_SIGNATURE'] ?? $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if (empty($rawPayload)) {
    http_response_code(400);
    die('Empty payload');
}

// Verify HMAC-SHA256 signature if secret is configured
$webhookSecret = trim($gatewayParams['webhookSecret'] ?? '');
if (!empty($webhookSecret)) {
    if (empty($signatureHeader)) {
        logTransaction($gatewayParams['name'], $_SERVER, 'Webhook rejected: Missing signature header');
        http_response_code(401);
        die('Missing signature header');
    }

    $computedSignature = hash_hmac('sha256', $rawPayload, $webhookSecret);
    if (!hash_equals($computedSignature, trim($signatureHeader))) {
        logTransaction($gatewayParams['name'], [
            'header' => $signatureHeader,
            'computed' => $computedSignature,
        ], 'Webhook rejected: Invalid signature');
        http_response_code(403);
        die('Invalid signature');
    }
}

// Parse JSON
$event = json_decode($rawPayload, true);
if (!is_array($event)) {
    http_response_code(400);
    die('Invalid JSON payload');
}

$eventType = $event['event'] ?? '';
$data = $event['data'] ?? $event;

// Extract invoice and transaction parameters
$kkhayInvoiceId = $data['id'] ?? $data['invoiceId'] ?? '';
$whmcsInvoiceId = $data['orderId'] ?? $data['externalId'] ?? ($data['metadata']['whmcsInvoiceId'] ?? null);
$amountPaid = (float) ($data['amountPaid'] ?? $data['amount'] ?? 0);
$txHash = $data['txHash'] ?? $data['transactionHash'] ?? $data['tx_hash'] ?? $kkhayInvoiceId;
$fee = (float) ($data['fee'] ?? 0);

if (empty($whmcsInvoiceId)) {
    logTransaction($gatewayParams['name'], $event, 'Webhook ignored: Missing WHMCS invoice ID');
    http_response_code(200);
    echo json_encode(['status' => 'ignored', 'reason' => 'Missing orderId']);
    exit;
}

// Validate WHMCS invoice ID exists and is assigned to this gateway
$invoiceId = checkCbInvoiceID($whmcsInvoiceId, $gatewayParams['name']);

switch ($eventType) {
    case 'invoice.paid':
    case 'invoice.completed':
        // Check for replay attacks or duplicate transaction processing
        checkCbTransID($txHash);

        // Apply payment to WHMCS invoice
        addInvoicePayment(
            $invoiceId,
            $txHash,
            $amountPaid,
            $fee,
            $gatewayModuleName
        );

        logTransaction($gatewayParams['name'], $event, 'Successful Payment Applied');
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment applied to Invoice #' . $invoiceId]);
        break;

    case 'invoice.partial':
        logTransaction($gatewayParams['name'], $event, 'Partial Payment Received (' . $amountPaid . ')');
        http_response_code(200);
        echo json_encode(['status' => 'partial_logged']);
        break;

    case 'invoice.expired':
    case 'invoice.cancelled':
        logTransaction($gatewayParams['name'], $event, 'Invoice ' . $eventType);
        http_response_code(200);
        echo json_encode(['status' => 'acknowledged']);
        break;

    default:
        logTransaction($gatewayParams['name'], $event, 'Webhook Event Ignored: ' . $eventType);
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        break;
}

