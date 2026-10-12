# Crezco — WooCommerce Payment Gateway

WooCommerce plugin that lets customers pay via [Crezco](https://www.crezco.com/) open-banking payouts from checkout.

## Stack

- PHP · WordPress plugin API · WooCommerce payment gateway class
- Crezco REST API (programmatic bank payments)

## Layout

```
woocommerce-crezco/
  woocommerce-crezco.php          # plugin bootstrap
  woocommerce-crezco-gateway.php  # WC_Payment_Gateway implementation
  img/                            # gateway icons
```

## Install

1. Copy `woocommerce-crezco/` into `wp-content/plugins/`
2. Activate under **Plugins**
3. Configure under **WooCommerce → Settings → Payments → Crezco** (API credentials from Crezco)

## Notes

Built for production WooCommerce stores that need Crezco as a first-class checkout method alongside cards and other gateways.
