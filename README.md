# K Khay – Sovereign Crypto Gateway for WHMCS

[![Latest Release](https://img.shields.io/github/v/release/boboaungdev/kkhay-whmcs?color=10b981&label=Release)](https://github.com/boboaungdev/kkhay-whmcs/releases)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![WHMCS Compatibility](https://img.shields.io/badge/WHMCS-7.x%20--%208.x-orange.svg)](https://whmcs.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%20--%208.3-777BB4.svg)](https://php.net)

The official **[K Khay](https://kkhay.com)** payment gateway module for **WHMCS**. Accept non-custodial and custodial crypto payments (USDT, USDC, BNB, ETH across BNB Smart Chain, Polygon, Arbitrum, Base, and Ethereum) directly on your WHMCS hosting and billing platform with **instant on-chain verification** and **zero chargebacks**.

---

## 🌟 Key Features

* **Multi-Chain Stablecoins**: Accept **USDT** & **USDC** across BSC, Polygon, Arbitrum, Base, and Ethereum mainnet.
* **Native Assets**: Accept **BNB** and **ETH** natively.
* **Non-Custodial & Sovereign**: Receive funds directly into your own merchant wallets — immune to payment processor freezes.
* **Hosted Checkout Experience**: Mobile-responsive checkout with dynamic QR codes, live network switches, and real-time payment listeners.
* **Instant Payment Notifications (IPN)**: Webhooks cryptographically signed with **HMAC-SHA256** automatically mark WHMCS invoices as Paid upon blockchain confirmation.
* **Replay Protection**: Automatic transaction hash duplicate checks (`checkCbTransID`) prevent double-crediting.
* **Self-Hosted Gateway Ready**: Point the module to your private self-hosted K Khay gateway instance or use the global cloud gateway.

---

## 📋 Requirements

* **WHMCS**: Version 7.10 through 8.11+
* **PHP**: 7.4, 8.0, 8.1, 8.2, or 8.3
* **PHP Extensions**: `curl`, `json`, `hash`, `openssl`

---

## 🚀 Installation

### Option 1: Quick Install via Release Archive (Recommended)

1. Download the latest **`kkhay-whmcs.zip`** from [GitHub Releases](https://github.com/boboaungdev/kkhay-whmcs/releases).
2. Upload and extract the archive into your WHMCS root directory:
   ```bash
   cd /path/to/whmcs
   unzip kkhay-whmcs.zip
   ```
   *This extracts files into `modules/gateways/kkhay.php`, `modules/gateways/callback/kkhay.php`, and `modules/gateways/kkhay/`.*

### Option 2: Clone via Git

```bash
cd /path/to/whmcs
git clone https://github.com/boboaungdev/kkhay-whmcs.git /tmp/kkhay-whmcs
cp -r /tmp/kkhay-whmcs/modules/gateways/* modules/gateways/
rm -rf /tmp/kkhay-whmcs
```

---

## ⚙️ Configuration in WHMCS

1. Log in to your **WHMCS Admin Area**.
2. Navigate to:
   * **WHMCS 8.x**: **System Settings** &rarr; **Payment Gateways** &rarr; **All Payment Gateways** tab.
   * **WHMCS 7.x**: **Setup** &rarr; **Payments** &rarr; **Payment Gateways** &rarr; **All Payment Gateways** tab.
3. Locate **K Khay (Crypto: USDT, USDC, BNB, ETH)** and click **Activate**.
4. Configure the gateway fields:
   * **Show on Order Form**: Check to enable for customers.
   * **Visible Name**: `K Khay (Crypto: USDT, USDC, BNB, ETH)`
   * **Merchant API Key**: Enter your API Key from [K Khay Merchant Dashboard](https://kkhay.com) &rarr; **API Keys**.
   * **Webhook / IPN Secret**: Enter your Webhook Secret from the K Khay Dashboard.
   * **API Base URL**: `https://api.kkhay.com` *(or your custom self-hosted gateway URL)*.
5. Copy your **Webhook / IPN URL** shown on the configuration screen:
   ```text
   https://yourdomain.com/modules/gateways/callback/kkhay.php
   ```
6. Paste this URL into your **K Khay Merchant Dashboard** under **Webhook Settings** and select events (`invoice.paid`, `invoice.completed`).
7. Click **Save Changes**.

---

## 🔄 How the Payment Flow Works

```
┌───────────┐         ┌───────────┐         ┌───────────┐         ┌───────────┐
│  Client   │         │   WHMCS   │         │  K Khay   │         │Blockchain │
│  Invoice  │         │  Gateway  │         │  Checkout │         │  (On-Chain│
└─────┬─────┘         └─────┬─────┘         └─────┬─────┘         └─────┬─────┘
      │                     │                     │                     │
      │ 1. Clicks "Pay"     │                     │                     │
      │────────────────────>│                     │                     │
      │                     │ 2. Create Invoice   │                     │
      │                     │────────────────────>│                     │
      │                     │ 3. Returns URL      │                     │
      │                     │<────────────────────│                     │
      │ 4. Redirects to     │                     │                     │
      │    Hosted Checkout  │                     │                     │
      │──────────────────────────────────────────>│                     │
      │                     │                     │ 5. Scans QR & Pays  │
      │                     │                     │────────────────────>│
      │                     │                     │ 6. Verified On-Chain│
      │                     │                     │<────────────────────│
      │                     │ 7. HMAC Webhook     │                     │
      │                     │<────────────────────│                     │
      │                     │ 8. Marks Paid       │                     │
      │<────────────────────│                     │                     │
```

1. **Checkout**: Customer views invoice in WHMCS (`viewinvoice.php`) and clicks the branded **"Pay with Crypto"** button.
2. **Dynamic Session**: K Khay initializes a payment session with real-time crypto-to-fiat conversion rates.
3. **Settlement**: Customer sends crypto via MetaMask, Trust Wallet, Binance, or any Web3 wallet.
4. **Instant Verification**: K Khay detects the transaction on-chain and fires an HMAC-SHA256 webhook to `callback/kkhay.php`.
5. **Invoice Cleared**: WHMCS verifies the cryptographic signature, records the blockchain transaction hash, and marks the invoice as **Paid** automatically.

---

## 🛡️ Security Best Practices

* **Signature Verification**: Every incoming webhook is authenticated via HMAC-SHA256 signature against your private Webhook Secret.
* **Replay Protection**: WHMCS `checkCbTransID()` ensures that no transaction hash can be credited twice.
* **TLS / SSL**: Always serve your WHMCS installation over HTTPS with valid SSL certificates.

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

## 💬 Support & Community

* **Website**: [https://kkhay.com](https://kkhay.com)
* **GitHub Issues**: [https://github.com/boboaungdev/kkhay-whmcs/issues](https://github.com/boboaungdev/kkhay-whmcs/issues)
* **Telegram**: [@kkhaysupport](https://t.me/kkhay)

