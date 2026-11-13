<?php
/**
 * Order screen: customer history box with one-click block / unblock.
 *
 * @package WFOB
 */

defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes', function () {
	$screen = 'shop_order';

	if ( function_exists( 'wc_get_container' )
		&& class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
		&& wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
	) {
		$screen = wc_get_page_screen_id( 'shop-order' );
	}

	add_meta_box( 'wfob-history', __( 'Customer check', 'wfob' ), 'wfob_render_history_box', $screen, 'side', 'high' );
} );

/**
 * Render the customer history box.
 *
 * @param WP_Post|WC_Order $post_or_order Post (legacy storage) or order (HPOS).
 * @return void
 */
function wfob_render_history_box( $post_or_order ) {
	$order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;

	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$phone = wfob_normalize_phone( $order->get_billing_phone() );
	$ip    = $order->get_customer_ip_address();

	if ( '' === $phone ) {
		echo '<p>' . esc_html__( 'No phone number on this order.', 'wfob' ) . '</p>';
		return;
	}

	$stats = wfob_phone_stats( $phone, $order->get_id() );

	if ( wfob_phone_blacklisted( $phone ) ) {
		$badge = array( '#b32d2e', __( 'Blacklisted', 'wfob' ) );
	} elseif ( 0 === $stats['total'] ) {
		$badge = array( '#996800', __( 'New customer', 'wfob' ) );
	} elseif ( null !== $stats['rate'] && $stats['rate'] < 50 ) {
		$badge = array( '#b32d2e', __( 'Risky', 'wfob' ) );
	} elseif ( $stats['completed'] > 0 ) {
		$badge = array( '#008a20', __( 'Trusted', 'wfob' ) );
	} else {
		$badge = array( '#996800', __( 'No delivered orders yet', 'wfob' ) );
	}

	printf(
		'<p><span style="display:inline-block;padding:2px 8px;border-radius:3px;color:#fff;background:%1$s;font-weight:600;">%2$s</span></p>',
		esc_attr( $badge[0] ),
		esc_html( $badge[1] )
	);

	echo '<p><strong>' . esc_html__( 'Phone:', 'wfob' ) . '</strong> ' . esc_html( $phone ) . '</p>';
	echo '<ul style="margin:0 0 12px;">';
	/* translators: %d: number of orders. */
	echo '<li>' . esc_html( sprintf( __( 'Other orders: %d', 'wfob' ), $stats['total'] ) ) . '</li>';
	/* translators: %d: number of orders. */
	echo '<li>' . esc_html( sprintf( __( 'Delivered (completed): %d', 'wfob' ), $stats['completed'] ) ) . '</li>';
	/* translators: %d: number of orders. */
	echo '<li>' . esc_html( sprintf( __( 'Cancelled / failed / refunded: %d', 'wfob' ), $stats['problem'] ) ) . '</li>';
	/* translators: %d: number of orders. */
	echo '<li>' . esc_html( sprintf( __( 'In progress: %d', 'wfob' ), $stats['active'] ) ) . '</li>';
	if ( null !== $stats['rate'] ) {
		/* translators: %d: success rate percentage. */
		echo '<li><strong>' . esc_html( sprintf( __( 'Success rate: %d%%', 'wfob' ), $stats['rate'] ) ) . '</strong></li>';
	}
	echo '</ul>';

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$links = array();

	if ( wfob_phone_blacklisted( $phone ) ) {
		$links[] = wfob_action_link( $order, 'unblock_phone', __( 'Unblock phone', 'wfob' ) );
	} else {
		$links[] = wfob_action_link( $order, 'block_phone', __( 'Block phone', 'wfob' ) );
	}

	if ( $ip ) {
		if ( wfob_ip_blacklisted( $ip ) ) {
			$links[] = wfob_action_link( $order, 'unblock_ip', __( 'Unblock IP', 'wfob' ) );
		} else {
			$links[] = wfob_action_link( $order, 'block_ip', __( 'Block IP', 'wfob' ) );
		}
	}

	echo '<p>' . implode( ' ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* in wfob_action_link().
}

/**
 * Build a block/unblock button.
 *
 * @param WC_Order $order Order.
 * @param string   $task  block_phone | unblock_phone | block_ip | unblock_ip.
 * @param string   $label Button text.
 * @return string
 */
function wfob_action_link( $order, $task, $label ) {
	$url = wp_nonce_url(
		add_query_arg(
			array(
				'action'   => 'wfob_customer',
				'task'     => $task,
				'order_id' => $order->get_id(),
			),
			admin_url( 'admin-post.php' )
		),
		'wfob_customer_' . $order->get_id()
	);

	$class = 0 === strpos( $task, 'block' ) ? 'button button-small' : 'button button-small button-secondary';

	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
}

/**
 * Handle block / unblock buttons.
 */
add_action( 'admin_post_wfob_customer', function () {
	$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

	check_admin_referer( 'wfob_customer_' . $order_id );

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'wfob' ), 403 );
	}

	$order = wc_get_order( $order_id );
	$task  = isset( $_GET['task'] ) ? sanitize_key( wp_unslash( $_GET['task'] ) ) : '';

	if ( ! $order instanceof WC_Order ) {
		wp_die( esc_html__( 'Order not found.', 'wfob' ), 404 );
	}

	$phone = wfob_normalize_phone( $order->get_billing_phone() );
	$ip    = $order->get_customer_ip_address();
	$user  = wp_get_current_user()->display_name;

	switch ( $task ) {
		case 'block_phone':
			wfob_add_to_list( 'blacklist_phones', $phone );
			/* translators: 1: phone, 2: user name. */
			$order->add_order_note( sprintf( __( 'Phone %1$s blacklisted by %2$s.', 'wfob' ), $phone, $user ) );
			break;

		case 'unblock_phone':
			foreach ( wfob_list( 'blacklist_phones' ) as $entry ) {
				if ( wfob_normalize_phone( $entry ) === $phone ) {
					wfob_remove_from_list( 'blacklist_phones', $entry );
				}
			}
			/* translators: 1: phone, 2: user name. */
			$order->add_order_note( sprintf( __( 'Phone %1$s removed from the blacklist by %2$s.', 'wfob' ), $phone, $user ) );
			break;

		case 'block_ip':
			wfob_add_to_list( 'blacklist_ips', $ip );
			/* translators: 1: IP address, 2: user name. */
			$order->add_order_note( sprintf( __( 'IP %1$s blacklisted by %2$s.', 'wfob' ), $ip, $user ) );
			break;

		case 'unblock_ip':
			wfob_remove_from_list( 'blacklist_ips', $ip );
			/* translators: 1: IP address, 2: user name. */
			$order->add_order_note( sprintf( __( 'IP %1$s removed from the blacklist by %2$s.', 'wfob' ), $ip, $user ) );
			break;
	}

	wp_safe_redirect( $order->get_edit_order_url() );
	exit;
} );
