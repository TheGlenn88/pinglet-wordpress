# Pinglet for WordPress

Get a push notification on your phone when someone submits a form on your WordPress site or places a WooCommerce order. Powered by [Pinglet](https://pinglet.dev), the hosted webhook-to-push service.

## Features

- Push notifications for Contact Form 7, WPForms, Gravity Forms and Elementor Pro form submissions
- WooCommerce order notifications with the total and item count as badges
- Per-integration on/off switches; integrations only run when their plugin is active
- Non blocking HTTP requests, so visitors never wait on the push
- Sensitive fields (password, card, cvv and similar) are never included
- "Send test notification" button on the settings page

## Setup

1. Copy this directory into `wp-content/plugins/pinglet` and activate the plugin.
2. Go to **Settings -> Pinglet** and enter your Pinglet API key, namespace and a default topic.
3. Press **Send test notification** and check your phone.

## For developers

Send a custom push from any plugin or theme:

```php
pinglet_notify( array(
    'message'  => 'Backup finished',
    'title'    => 'Nightly backup',
    'level'    => 'success',            // info | success | warning | error
    'priority' => 'normal',             // silent | normal | urgent
    'badges'   => array( 'size' => '1.2 GB' ), // up to 3 pairs
    'topic'    => 'ops',                // optional, overrides the default topic
) );
```

Or without a hard dependency on the plugin:

```php
do_action( 'pinglet_send', array( 'message' => 'Backup finished' ) );
```

## Requirements

- WordPress 5.8 or newer
- PHP 7.4 or newer
- A [Pinglet](https://pinglet.dev) account (API key and namespace)

## License

GPL-2.0-or-later.
