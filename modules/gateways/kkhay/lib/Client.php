<?php
/**
 * K Khay API Client for WHMCS
 *
 * @package    Kkhay_WHMCS
 * @author     K Khay <support@kkhay.com>
 * @license    MIT
 * @link       https://kkhay.com
 */

namespace Kkhay\Whmcs;

class Client
{
    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var int
     */
    private $timeout;

    /**
     * Constructor.
     *
     * @param string $apiKey
     * @param string $baseUrl
     * @param int    $timeout
     */
    public function __construct(string $apiKey, string $baseUrl = 'https://api.kkhay.com', int $timeout = 30)
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->timeout = $timeout;
    }

    /**
     * Build the full endpoint URL.
     *
     * @param string $path
     * @return string
     */
    private function buildUrl(string $path): string
    {
        $cleanPath = '/' . ltrim($path, '/');
        if (substr($this->baseUrl, -4) === '/api' || strpos($this->baseUrl, 'api.') !== false) {
            return $this->baseUrl . $cleanPath;
        }
        return $this->baseUrl . '/api' . $cleanPath;
    }

    /**
     * Perform HTTP request via cURL.
     *
     * @param string     $path
     * @param string     $method
     * @param array|null $payload
     * @return array
     * @throws \Exception
     */
    public function request(string $path, string $method = 'GET', ?array $payload = null): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('K Khay API Key is missing. Please configure your API key in WHMCS Payment Gateways.');
        }

        $url = $this->buildUrl($path);

        $headers = [
            'Accept: application/json',
            'x-api-key: ' . $this->apiKey,
            'User-Agent: kkhay-whmcs/1.0.0 (PHP/' . PHP_VERSION . ')',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $upperMethod = strtoupper($method);
        if ($upperMethod === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload !== null) {
                $jsonData = json_encode($payload);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($jsonData);
            }
        } elseif ($upperMethod !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upperMethod);
            if ($payload !== null) {
                $jsonData = json_encode($payload);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Content-Length: ' . strlen($jsonData);
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \Exception('Network error connecting to K Khay: ' . $curlError);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = is_array($decoded) ? ($decoded['message'] ?? $decoded['error'] ?? 'API error') : $response;
            throw new \Exception('K Khay API error (' . $httpCode . '): ' . $errorMsg);
        }

        return is_array($decoded) ? $decoded : ['data' => $response];
    }

    /**
     * Create an invoice for a WHMCS billing invoice.
     *
     * @param array $payload
     * @return array
     * @throws \Exception
     */
    public function createInvoice(array $payload): array
    {
        return $this->request('/v1/merchant/invoices', 'POST', $payload);
    }

    /**
     * Retrieve status and transaction details of an invoice.
     *
     * @param string $invoiceId
     * @return array
     * @throws \Exception
     */
    public function getInvoice(string $invoiceId): array
    {
        return $this->request('/v1/merchant/invoices/' . rawurlencode(trim($invoiceId)), 'GET');
    }

    /**
     * Health check endpoint.
     *
     * @return array
     * @throws \Exception
     */
    public function checkHealth(): array
    {
        return $this->request('/health', 'GET');
    }
}

