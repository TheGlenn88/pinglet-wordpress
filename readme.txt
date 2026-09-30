=== Pinglet ===
Contributors: pinglet
Tags: push notifications, notifications, forms, woocommerce, contact form 7
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Get a push notification on your phone when someone submits a form on your site or places a WooCommerce order.

== Description ==

Pinglet connects your WordPress site to the Pinglet push notification service (https://pinglet.dev). Publishing to a Pinglet topic delivers a native push notification to every phone subscribed to that topic, so you find out about form submissions and new orders the moment they happen.

Out of the box the plugin can notify you about:

* Contact Form 7 submissions
* WPForms entries
* Gravity Forms entries
* Elementor Pro form submissions
* New WooCommerce orders (with the total and item count as badges)

Each integration has its own on/off switch and only runs when the matching plugin is active. Notifications are sent with a non blocking HTTP request, so your visitors never wait on the push.

Developers can send custom pushes from any plugin or theme:

`pinglet_notify( array( 'message' => 'Backup finished', 'level' => 'success' ) );`

or, without depending on the function existing:

`do_action( 'pinglet_send', array( 'message' => 'Backup finished' ) );`

The plugin never sends fields that look sensitive: anything whose name matches password, card, cvv and similar patterns is stripped before the notification is built.

You need a Pinglet account, an API key and a namespace from https://pinglet.dev. Topics are created automatically the first time you publish to them.

== Installation ==

1. Install and activate the plugin.
2. Go to Settings, then Pinglet.
3. Enter your Pinglet API key, your namespace and a default topic.
4. Press "Send test notification" to confirm everything works.
5. Tick the integrations you want and save.

== Frequently Asked Questions ==

= Do I need a Pinglet account? =

Yes. Sign up at https://pinglet.dev, create a namespace and copy your API key (it starts with pinglet_). Subscribe your phone to a topic in the Pinglet app.

= Will this slow down my forms or checkout? =

No. Notifications are dispatched with a non blocking request and a 5 second timeout, so the visitor's request never waits on Pinglet.

= Which WooCommerce event triggers the notification? =

The order reaching the processing status, which is the point where a paid, actionable order exists. Each order is only notified once, even if its status changes back and forth.

= Are form values sent to Pinglet? =

A short summary of up to 8 fields is included in the notification body. Fields with names that look sensitive (password, card, cvv and similar) and values longer than 200 characters are always skipped. If you prefer no field data at all, send your own notifications with pinglet_notify().

= Can other plugins send notifications? =

Yes. Call pinglet_notify( $args ) or fire do_action( 'pinglet_send', $args ). Supported keys: message (required), title, level (info, success, warning, error), priority (silent, normal, urgent), badges (up to 3 key/value pairs) and topic (to override the default).

== External services ==

This plugin connects to the Pinglet API (https://pinglet.dev), a push notification service run by Bitnix Limited, to deliver notifications to the phones subscribed to your Pinglet topic. It is required for the plugin to work.

A request is sent to https://pinglet.dev/{your namespace}/{topic} only when one of these happens:

* A visitor submits a form handled by an integration you have switched on (Contact Form 7, WPForms, Gravity Forms or Elementor Pro).
* A WooCommerce order reaches the processing status, if the WooCommerce integration is switched on.
* You press "Send test notification" on the settings page.
* Your own code, or another plugin, calls pinglet_notify() or fires the pinglet_send action.

Each request carries your Pinglet API key and the notification itself: a title, a message and optional badges. For form submissions the message includes a short summary of up to 8 submitted fields, and the submitter's name, email address, phone number, subject and website, when the form has them, are attached as metadata. Fields that look sensitive (password, card, cvv and similar) are never sent. For WooCommerce orders the plugin sends the order number, total and item count. Nothing is sent when no integration fires.

Pinglet terms of service: https://pinglet.dev/terms/
Pinglet privacy policy: https://pinglet.dev/privacy/

== Changelog ==

= 1.0.0 =
* Initial release.
* Settings page with API key, namespace, default topic and per-integration toggles.
* Integrations: Contact Form 7, WPForms, Gravity Forms, Elementor Pro forms, WooCommerce.
* Test notification button.
* pinglet_notify() function and pinglet_send action for custom pushes.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
