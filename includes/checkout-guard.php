<?php
/**
 * Checkout protection: normalize phone, block, record, and hold COD orders.
 * Works with the classic checkout and the Checkout block (Store API).
 *
 * @package WFOB
 */

defined( 'ABSPATH' ) || exit;

/**
 * Save Bangladeshi mobile numbers in one clean format (01XXXXXXXXX).
 * This also makes the order history lookups reliable.
 */
add_filter( 'woocommerce_process_checkout_field_billing_phone', function ( $value ) {
	if ( ! wfob_is_on( 'normalize_phone' ) ) {
		return $value;
	}

	$normalized = wfob_normalize_phone( $value );

	return wfob_is_bd_mobile( $normalized ) ? $normalized : $value;
} );

/**
 * Get the customer's IP address.
 *
 * @return string
 */
function wfob_current_ip() {
	return class_exists( 'WC_Geolocation' ) ? (string) WC_Geolocation::get_ip_address() : '';
}

/**
 * Record a placed order against its phone and IP.
 *
 * @param WC_Order $order Order.
 * @return void
 */
function wfob_record_order( $order ) {
	if ( ! $order instanceof WC_Order || $order->get_meta( '_wfob_recorded' ) ) {
		return;
	}

	$ip = $order->get_customer_ip_address() ? $order->get_customer_ip_address() : wfob_current_ip();

	wfob_record( 'phone', wfob_normalize_phone( $order->get_billing_phone() ), wfob_option( 'phone_window' ) );
	wfob_record( 'ip', $ip, wfob_option( 'ip_window' ) );

	$order->update_meta_data( '_wfob_recorded', 1 );
	$order->save_meta_data();
}

/* -------------------------------------------------------------------------
 * Classic checkout
 * ---------------------------------------------------------------------- */

add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( wfob_bypass() || $errors->has_errors() ) {
		return;
	}

	$phone  = isset( $data['billing_phone'] ) ? $data['billing_phone'] : '';
	$email  = isset( $data['billing_email'] ) ? $data['billing_email'] : '';
	$ip     = wfob_current_ip();
	$reason = wfob_check_order( $phone, $email, $ip );

	if ( '' !== $reason ) {
		wfob_log_block( $reason, $phone, $email, $ip );
		$errors->add( 'wfob_blocked', wp_kses_post( wfob_option( 'block_message' ) ) );
	}
}, 20, 2 );

add_action( 'woocommerce_checkout_order_processed', function ( $order_id, $posted_data = array(), $order = null ) {
	wfob_record_order( $order instanceof WC_Order ? $order : wc_get_order( $order_id ) );
}, 10, 3 );

/* -------------------------------------------------------------------------
 * Checkout block (Store API)
 * ---------------------------------------------------------------------- */

add_action( 'woocommerce_store_api_checkout_update_order_from_request', function ( $order, $request ) {
	if ( wfob_bypass() || ! $order instanceof WC_Order ) {
		return;
	}

	$phone  = $order->get_billing_phone();
	$email  = $order->get_billing_email();
	$ip     = wfob_current_ip();
	$reason = wfob_check_order( $phone, $email, $ip );

	if ( '' === $reason ) {
		return;
	}

	wfob_log_block( $reason, $phone, $email, $ip );

	throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
		'wfob_blocked',
		wp_strip_all_tags( (string) wfob_option( 'block_message' ) ),
		400
	);
}, 10, 2 );

add_action( 'woocommerce_store_api_checkout_order_processed', 'wfob_record_order' );

/* -------------------------------------------------------------------------
 * Put Cash on Delivery orders on hold for a confirmation call
 * ---------------------------------------------------------------------- */

add_filter( 'woocommerce_cod_process_payment_order_status', function ( $status, $order = null ) {
	$mode = wfob_option( 'cod_hold' );

	if ( 'none' === $mode || ! $order instanceof WC_Order ) {
		return $status;
	}

	if ( 'new' === $mode ) {
		$stats = wfob_phone_stats( $order->get_billing_phone(), $order->get_id() );

		if ( $stats['completed'] > 0 ) {
			return $status; // Returning customer with a delivered order.
		}

		$note = __( 'Fake Order Blocker: first-time customer, order put on hold. Please confirm by phone before shipping.', 'wfob' );
	} else {
		$note = __( 'Fake Order Blocker: order put on hold. Please confirm by phone before shipping.', 'wfob' );
	}

	$order->add_order_note( $note );

	return 'on-hold';
}, 10, 2 );
