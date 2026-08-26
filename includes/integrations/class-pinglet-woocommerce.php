<?php
/**
 * WooCommerce integration.
 *
 * @package Pinglet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a push when a WooCommerce order reaches the processing status.
 *
 * Hook choice: woocommerce_order_status_processing rather than
 * woocommerce_new_order. The new_order hook fires whenever an order object is
 * first saved, which includes the empty "checkout-draft" orders the block
 * based checkout creates before the customer has finished (and it never fires
 * again once that draft becomes a real order). The processing status is the
 * moment a paid, actionable order exists, works identically for the classic
 * and block checkouts, and carries full line item data. A _pinglet_notified
 * order meta flag guards against duplicate pushes when an order bounces back
 * into processing (for example processing to on-hold to processing).
 */
class Pinglet_WooCommerce {

	const NOTIFIED_META = '_pinglet_notified';

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Whether the integration is switched on in the Pinglet settings.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = pinglet_get_settings();
		return ! empty( $settings['enable_woocommerce'] );
	}

	/**
	 * Hook into WooCommerce when available and enabled.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! self::is_available() || ! self::is_enabled() ) {
			return;
		}
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'handle_order' ), 10, 2 );
	}

	/**
	 * Build and send the notification for an order.
	 *
	 * @param int                $order_id Order ID.
	 * @param WC_Order|null $order    Order object when WooCommerce passes it.
	 * @return void
	 */
	public static function handle_order( $order_id, $order = null ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Guard against duplicate pushes if the order re-enters processing.
		if ( 'yes' === $order->get_meta( self::NOTIFIED_META ) ) {
			return;
		}
		$order->update_meta_data( self::NOTIFIED_META, 'yes' );
		$order->save_meta_data();

		$item_count = (int) $order->get_item_count();
		$total      = self::format_total( $order );
		$customer   = self::customer_label( $order );

		if ( '' !== $customer ) {
			$message = sprintf(
				/* translators: 1: order number, 2: number of items, 3: order total, 4: customer name. */
				_n( 'Order #%1$s: %2$d item, %3$s from %4$s', 'Order #%1$s: %2$d items, %3$s from %4$s', $item_count, 'pinglet' ),
				$order->get_order_number(),
				$item_count,
				$total,
				$customer
			);
		} else {
			$message = sprintf(
				/* translators: 1: order number, 2: number of items, 3: order total. */
				_n( 'Order #%1$s: %2$d item, %3$s', 'Order #%1$s: %2$d items, %3$s', $item_count, 'pinglet' ),
				$order->get_order_number(),
				$item_count,
				$total
			);
		}

		pinglet_notify(
			array(
				'title'   => __( 'New WooCommerce order', 'pinglet' ),
				'message' => $message,
				'level'   => 'success',
				'badges'  => array(
					'total' => $total,
					'items' => (string) $item_count,
				),
			)
		);
	}

	/**
	 * Plain text order total, for example "£45.00".
	 *
	 * @param WC_Order $order The order.
	 * @return string
	 */
	private static function format_total( $order ) {
		if ( function_exists( 'wc_price' ) ) {
			$html  = wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) );
			$plain = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
			$plain = str_replace( "\xC2\xA0", ' ', $plain );
			$plain = trim( $plain );
			if ( '' !== $plain ) {
				return $plain;
			}
		}
		return trim( $order->get_currency() . ' ' . $order->get_total() );
	}

	/**
	 * Compact customer label, for example "Jane D.".
	 *
	 * @param WC_Order $order The order.
	 * @return string
	 */
	private static function customer_label( $order ) {
		$first = sanitize_text_field( $order->get_billing_first_name() );
		$last  = sanitize_text_field( $order->get_billing_last_name() );

		if ( '' === $first && '' === $last ) {
			return '';
		}
		if ( '' === $last ) {
			return $first;
		}

		$initial = function_exists( 'mb_substr' ) ? mb_substr( $last, 0, 1, 'UTF-8' ) : substr( $last, 0, 1 );
		return trim( $first . ' ' . $initial . '.' );
	}
}
