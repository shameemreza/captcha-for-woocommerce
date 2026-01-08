=== Captcha for WooCommerce ===
Contributors: shameemreza
Donate link: https://ko-fi.com/shameemreza
Tags: captcha, recaptcha, turnstile, woocommerce, spam
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add CAPTCHA protection to your WooCommerce store. Supports Google reCAPTCHA, Cloudflare Turnstile, hCaptcha, and a self-hosted honeypot option.

== Description ==

**Captcha for WooCommerce** helps protect your WordPress and WooCommerce forms from spam bots and automated attacks. Choose from multiple CAPTCHA providers based on your needs.

= Supported Providers =

* **Cloudflare Turnstile:** Privacy-focused, often invisible to users.
* **Google reCAPTCHA v3:** Score-based, runs in background.
* **Google reCAPTCHA v2:** Classic checkbox challenge.
* **hCaptcha:** Privacy-focused alternative.
* **Self-Hosted Honeypot:** No external service needed, good for GDPR.

= Protected Forms =

**WordPress**

* Login
* Registration
* Lost Password
* Comments

**WooCommerce**

* Checkout (Classic and Block-based)
* My Account Login
* My Account Registration
* Lost Password
* Pay for Order

**WooCommerce Extensions**

* Product Vendors Registration
* Subscriptions
* Memberships

= Payment Gateway Compatibility =

The plugin detects when **WooCommerce PayPal Payments** has its own reCAPTCHA enabled and skips CAPTCHA for those payment methods to avoid duplicate verification. Express payment methods like Apple Pay and Google Pay are also handled appropriately.

= How It Works =

1. Choose your CAPTCHA provider.
2. Enter your API keys (not needed for Honeypot).
3. Select which forms to protect.
4. Save and you're done.

Scripts only load on pages with protected forms, keeping your site fast.

= For Developers =

The plugin includes hooks and filters for customization:

* Skip CAPTCHA for specific conditions.
* Add protection to custom forms.
* Customize error messages.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/captcha-for-woocommerce/`.
2. Activate through the Plugins screen.
3. Go to WooCommerce > Settings > CAPTCHA.
4. Select a provider and enter your API keys.
5. Choose which forms to protect.
6. Save changes.

= Getting API Keys =

**Cloudflare Turnstile:**
[Cloudflare Dashboard](https://dash.cloudflare.com/?to=/:account/turnstile)

**Google reCAPTCHA:**
[Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin/create)

**hCaptcha:**
[hCaptcha Dashboard](https://dashboard.hcaptcha.com/sites/new)

== Frequently Asked Questions ==

= Do I need API keys? =

Yes, for reCAPTCHA, Turnstile, and hCaptcha. The self-hosted honeypot option works without external APIs.

= Does it work with WooCommerce Block Checkout? =

Yes. The plugin integrates with the WooCommerce Store API for Block Checkout support.

= Which provider should I use? =

Cloudflare Turnstile is a good choice for most sites. It's usually invisible, privacy-focused, and has no usage limits on the free tier.

= What if the CAPTCHA service is unavailable? =

You can configure failsafe behavior in settings:
* Block submissions.
* Fall back to honeypot.
* Allow submissions.

= Is it GDPR compliant? =

The honeypot option doesn't send data to external services. For other providers, include them in your privacy policy.

= Does it work with Product Vendors? =

Yes. The vendor registration form is supported.

== Screenshots ==

1. Settings page - Provider selection.
2. Form protection options.
3. CAPTCHA widget on checkout.
4. My Account login with CAPTCHA.

== Changelog ==

= 1.0.0 =
* Initial release
* Support for Google reCAPTCHA v2 and v3, Cloudflare Turnstile, hCaptcha, and self-hosted Honeypot
* WordPress form protection: Login, Registration, Lost Password, Comments
* WooCommerce form protection: Checkout (Classic and Block), My Account, Pay for Order
* WooCommerce extension support: Product Vendors, Subscriptions, Memberships
* Block Checkout integration via Store API
* PayPal Payments compatibility
* Express payment method handling (Apple Pay, Google Pay)
* IP and role-based skip options
* Failsafe mode configuration
* Debug logging via WooCommerce logs
* Settings export and import
* Translation ready

== External Services ==

This plugin connects to third-party CAPTCHA services to verify that form submissions are from humans, not bots. These connections are essential for the plugin's core functionality.

= Cloudflare Turnstile =

When Turnstile is selected as your CAPTCHA provider:

* **JavaScript API loaded from:** `https://challenges.cloudflare.com/turnstile/v0/api.js`
* **Verification requests sent to:** `https://challenges.cloudflare.com/turnstile/v0/siteverify`
* **Data transmitted:** CAPTCHA token, site key, user IP address
* **Service Terms:** [Cloudflare Terms of Service](https://www.cloudflare.com/website-terms/)
* **Privacy Policy:** [Cloudflare Privacy Policy](https://www.cloudflare.com/privacypolicy/)

= Google reCAPTCHA =

When reCAPTCHA v2 or v3 is selected:

* **JavaScript API loaded from:** `https://www.google.com/recaptcha/api.js`
* **Verification requests sent to:** `https://www.google.com/recaptcha/api/siteverify`
* **Data transmitted:** CAPTCHA token, site key, secret key, user IP address
* **Service Terms:** [Google Terms of Service](https://policies.google.com/terms)
* **Privacy Policy:** [Google Privacy Policy](https://policies.google.com/privacy)

= hCaptcha =

When hCaptcha is selected:

* **JavaScript API loaded from:** `https://js.hcaptcha.com/1/api.js`
* **Verification requests sent to:** `https://hcaptcha.com/siteverify`
* **Data transmitted:** CAPTCHA token, site key, secret key, user IP address
* **Service Terms:** [hCaptcha Terms of Service](https://www.hcaptcha.com/terms)
* **Privacy Policy:** [hCaptcha Privacy Policy](https://www.hcaptcha.com/privacy)

= Self-Hosted Honeypot =

The honeypot option does NOT connect to any external services. All validation is performed locally on your server.

== Privacy ==

**Important:** When using external CAPTCHA providers (Turnstile, reCAPTCHA, hCaptcha), user data including IP addresses is transmitted to third-party servers. You should:

1. Disclose this in your site's privacy policy.
2. Consider GDPR/CCPA compliance requirements.
3. Use the self-hosted honeypot option if you need to avoid external data transmission.

Debug logs, when enabled, are stored locally using WooCommerce's logging system and are not transmitted externally.
