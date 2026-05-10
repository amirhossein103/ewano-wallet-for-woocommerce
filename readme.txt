=== EWANO Wallet for WooCommerce ===
Contributors: amirhossein103
Donate link: https://ewano.ir
Tags: payment, wallet, e-wallet, EWANO, woocommerce, MCI, همراه اول
Requires at least: 6.0
Tested up to: 8.5
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Direct payment from EWANO wallet in WooCommerce stores, with contract signing, refund support, and flexible phone number extraction.

== Description ==

[EWANO](https://ewano.ir) wallet payment gateway for WooCommerce. This plugin integrates the official EWANO wallet, enabling customers to pay directly using their mobile wallet balance.

**Features:**

* Secure payment with OTP confirmation via EWANO SDK.
* Automatic contract signing flow for new users.
* Full refund support directly from WooCommerce admin.
* Detect phone number from billing, user meta, custom meta or manual input.
* Persian and English user interface.
* Configurable environment: Sandbox, Stage, Production.
* Works with standard WordPress HTTP API or direct cURL (for legacy servers).
* Fully compatible with WooCommerce 5.0+ and PHP 7.4+.

== Installation ==

1. Upload the `ewano-wallet-for-woocommerce` folder to `/wp-content/plugins/` or install via the WordPress plugin uploader.
2. Activate the plugin through the 'Plugins' screen.
3. Navigate to **WooCommerce > Settings > Payments** and enable **EWANO Wallet**.

== Configuration ==

1. Go to **WooCommerce > Settings > Payments > EWANO Wallet**.
2. Set your **Client ID** and **Client Secret** (provided by EWANO).
3. Choose the appropriate environment (Sandbox, Stage, Production).
4. Select how to obtain the customer's phone number (billing, manual input, user/posts meta, etc.).
5. Save changes.

Complete documentation: [GitHub Repository](https://github.com/amirhossein103/ewano-wallet-for-woocommerce)

== Usage ==

1. Customer selects **EWANO Wallet** on checkout.
2. Phone number is auto‑detected or entered manually.
3. If no active contract exists, the customer is redirected to EWANO to sign the agreement.
4. After signing, the EWANO SDK opens; payment is completed with an OTP.
5. Order is marked as paid in WooCommerce.

== Troubleshooting ==

= Connection errors =
If you experience `cURL error 6` or `TLS connect error`, switch the **Request Method** to `direct cURL` in the payment gateway settings. Note: SSL verification is disabled in cURL mode for compatibility with older servers.

= Invalid phone number =
Make sure the phone number is entered as a 10‑digit number starting with `9` (e.g., `9123456789`). Persian and Arabic digits are automatically converted.

= Payment not completing =
Check your **Client ID** and **Client Secret**. Also, ensure the **Callback URL** is reachable – the plugin uses home URL by default.

== Security ==

* Keep your **Client Secret** confidential.
* In `cURL` mode SSL verification is bypassed intentionally to support legacy environments; prefer the `WordPress HTTP API` method in production.
* Nonce verification is used on all AJAX and callback endpoints to prevent request forgery.

== Changelog ==

= 1.0.0 =
* Initial stable release.
* Support for contract creation, reserve, confirm, SDK integration, and refund.
* Multiple phone number sources.
* Dual HTTP request modes (WP HTTP API / cURL).

== Upgrade Notice ==

= 1.0.0 =
First release. No upgrade steps required.

== License ==

This plugin is released under the GPLv2 (or later) license. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.

== Credits ==

Developed by [Amirhossein Lalehei](https://github.com/amirhossein103).  
EWANO is a service of Mobile Telecommunication Company of Iran (MCI).