<?php
/**
 * Helper functions: settings, phone normalization, blacklist and rate limits.
 *
 * @package WFOB
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

/**
 * Default settings. Stored as options named wfob_{key}.
 *
 * @return array<string, mixed>
 */
function wfob_defaults() {
	return array(
		'phone_limit_enabled' => 'yes',
		'phone_limit'         => 3,
		'phone_window'        => 24,
		'ip_limit_enabled'    => 'no',
		'ip_limit'            => 10,
		'ip_window'           => 24,
		'blacklist_phones'    => '',
		'blacklist_ips'       => '',
		'blacklist_emails'    => '',
		'cod_hold'            => 'none',
		'normalize_phone'     => 'yes',
		'block_message'       => __( 'Sorry, we could not place your order right now. Please call or message us to complete your order.', 'wfob' ),
	);
}

/**
 * Get a setting. Filter: wfob_option_{key}.
 *
 * @param string $key Setting key without the wfob_ prefix.
 * @return mixed
 */
function wfob_option( $key ) {
	$defaults = wfob_defaults();
	$value    = get_option( 'wfob_' . $key, isset( $defaults[ $key ] ) ? $defaults[ $key ] : '' );

	return apply_filters( 'wfob_option_' . $key, $value );
}

/**
 * Whether a yes/no setting is on.
 *
 * @param string $key Setting key.
 * @return bool
 */
function wfob_is_on( $key ) {
	return 'yes' === wfob_option( $key );
}

/* -------------------------------------------------------------------------
 * Phone numbers
 * ---------------------------------------------------------------------- */

/**
 * Normalize a phone number.
 *
 * Bangladeshi mobiles in any common format (01712-345678, +8801712345678,
 * 008801712345678, 880 1712 345678) become 01712345678. Other numbers are
 * reduced to their digits.
 *
 * @param string $phone Raw phone number.
 * @return string
 */
function wfob_normalize_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );

	if ( preg_match( '/^(?:00)?(?:88)?(01[3-9]\d{8})$/', $digits, $m ) ) {
		return $m[1];
	}

	return $digits;
}

/**
 * Whether a normalized phone is a valid Bangladeshi mobile number.
 *
 * @param string $phone Normalized phone.
 * @return bool
 */
function wfob_is_bd_mobile( $phone ) {
	return (bool) preg_match( '/^01[3-9]\d{8}$/', (string) $phone );
}

/**
 * The formats a phone may be stored in on older orders, used for lookups.
 *
 * @param string $phone Any phone format.
 * @return string[]
 */
function wfob_phone_variants( $phone ) {
	$normalized = wfob_normalize_phone( $phone );

	if ( '' === $normalized ) {
		return array();
	}

	$variants = array( $normalized );

	if ( wfob_is_bd_mobile( $normalized ) ) {
		$variants[] = '+88' . $normalized;
		$variants[] = '88' . $normalized;
	}

	if ( '' !== trim( (string) $phone ) ) {
		$variants[] = trim( (string) $phone );
	}

	return array_values( array_unique( $variants ) );
}

/* -------------------------------------------------------------------------
 * Blacklist
 * ---------------------------------------------------------------------- */

/**
 * Read a one-entry-per-line list setting.
 *
 * @param string $key Setting key.
 * @return string[]
 */
function wfob_list( $key ) {
	$lines = preg_split( '/[\r\n,]+/', (string) wfob_option( $key ) );
	$lines = array_map( 'trim', (array) $lines );

	return array_values( array_filter( $lines, 'strlen' ) );
}

/**
 * Add an entry to a list setting (no duplicates).
 *
 * @param string $key   Setting key.
 * @param string $entry Entry to add.
 * @return bool True if it was added.
 */
function wfob_add_to_list( $key, $entry ) {
	$entry = trim( (string) $entry );
	$list  = wfob_list( $key );

	if ( '' === $entry || in_array( $entry, $list, true ) ) {
		return false;
	}

	$list[] = $entry;
	update_option( 'wfob_' . $key, implode( "\n", $list ) );

	return true;
}

/**
 * Remove an entry from a list setting.
 *
 * @param string $key   Setting key.
 * @param string $entry Entry to remove.
 * @return void
 */
function wfob_remove_from_list( $key, $entry ) {
	$list = array_diff( wfob_list( $key ), array( trim( (string) $entry ) ) );
	update_option( 'wfob_' . $key, implode( "\n", $list ) );
}

/**
 * Whether a phone is blacklisted (any format matches).
 *
 * @param string $phone Phone.
 * @return bool
 */
function wfob_phone_blacklisted( $phone ) {
	$phone = wfob_normalize_phone( $phone );

	if ( '' === $phone ) {
		return false;
	}

	foreach ( wfob_list( 'blacklist_phones' ) as $entry ) {
		if ( wfob_normalize_phone( $entry ) === $phone ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether an IP is blacklisted. Entries can be exact (103.12.5.9)
 * or a prefix ending in * (103.12.*).
 *
 * @param string $ip IP address.
 * @return bool
 */
function wfob_ip_blacklisted( $ip ) {
	$ip = trim( (string) $ip );

	if ( '' === $ip ) {
		return false;
	}

	foreach ( wfob_list( 'blacklist_ips' ) as $entry ) {
		if ( '*' === substr( $entry, -1 ) ) {
			if ( 0 === strpos( $ip, substr( $entry, 0, -1 ) ) ) {
				return true;
			}
		} elseif ( $entry === $ip ) {
			return true;
		}
	}

	return false;
}

/**
 * Whether an email is blacklisted. Entries can be a full address
 * or a domain starting with @ (@tempmail.com).
 *
 * @param string $email Email.
 * @return bool
 */
function wfob_email_blacklisted( $email ) {
	$email = strtolower( trim( (string) $email ) );

	if ( '' === $email ) {
		return false;
	}

	foreach ( wfob_list( 'blacklist_emails' ) as $entry ) {
		$entry = strtolower( $entry );

		if ( '@' === $entry[0] ) {
			if ( substr( $email, -strlen( $entry ) ) === $entry ) {
				return true;
			}
		} elseif ( $entry === $email ) {
			return true;
		}
	}

	return false;
}

/* -------------------------------------------------------------------------
 * Rate limits (stored in transients, independent of order storage)
 * ---------------------------------------------------------------------- */

/**
 * Transient key for a counter.
 *
 * @param string $type  phone | ip.
 * @param string $value Normalized value.
 * @return string
 */
function wfob_rate_key( $type, $value ) {
	return 'wfob_' . $type . '_' . md5( strtolower( (string) $value ) );
}

/**
 * Number of orders recorded for a value within the last X hours.
 *
 * @param string $type  phone | ip.
 * @param string $value Normalized value.
 * @param int    $hours Window in hours.
 * @return int
 */
function wfob_recent_count( $type, $value, $hours ) {
	if ( '' === (string) $value ) {
		return 0;
	}

	$times = get_transient( wfob_rate_key( $type, $value ) );
	$since = time() - max( 1, (int) $hours ) * HOUR_IN_SECONDS;

	if ( ! is_array( $times ) ) {
		return 0;
	}

	return count(
		array_filter(
			$times,
			function ( $t ) use ( $since ) {
				return (int) $t > $since;
			}
		)
	);
}

/**
 * Record one order for a value.
 *
 * @param string $type  phone | ip.
 * @param string $value Normalized value.
 * @param int    $hours Window in hours.
 * @return void
 */
function wfob_record( $type, $value, $hours ) {
	if ( '' === (string) $value ) {
		return;
	}

	$hours = max( 1, (int) $hours );
	$key   = wfob_rate_key( $type, $value );
	$since = time() - $hours * HOUR_IN_SECONDS;
	$times = get_transient( $key );
	$times = is_array( $times ) ? $times : array();

	$times   = array_values(
		array_filter(
			$times,
			function ( $t ) use ( $since ) {
				return (int) $t > $since;
			}
		)
	);
	$times[] = time();

	set_transient( $key, $times, $hours * HOUR_IN_SECONDS );
}

/* -------------------------------------------------------------------------
 * The check
 * ---------------------------------------------------------------------- */

/**
 * Decide whether an order should be blocked.
 *
 * Filter: wfob_check_result (receives the reason, phone, email and IP).
 *
 * @param string $phone Billing phone (any format).
 * @param string $email Billing email.
 * @param string $ip    Customer IP address.
 * @return string Empty string to allow, or a reason code.
 */
function wfob_check_order( $phone, $email, $ip ) {
	$phone  = wfob_normalize_phone( $phone );
	$reason = '';

	if ( wfob_phone_blacklisted( $phone ) ) {
		$reason = 'blacklist_phone';
	} elseif ( wfob_email_blacklisted( $email ) ) {
		$reason = 'blacklist_email';
	} elseif ( wfob_ip_blacklisted( $ip ) ) {
		$reason = 'blacklist_ip';
	} elseif ( wfob_is_on( 'phone_limit_enabled' ) && $phone && wfob_recent_count( 'phone', $phone, wfob_option( 'phone_window' ) ) >= (int) wfob_option( 'phone_limit' ) ) {
		$reason = 'phone_limit';
	} elseif ( wfob_is_on( 'ip_limit_enabled' ) && $ip && wfob_recent_count( 'ip', $ip, wfob_option( 'ip_window' ) ) >= (int) wfob_option( 'ip_limit' ) ) {
		$reason = 'ip_limit';
	}

	return (string) apply_filters( 'wfob_check_result', $reason, $phone, $email, $ip );
}

/**
 * Human-readable reason.
 *
 * @param string $reason Reason code.
 * @return string
 */
function wfob_reason_label( $reason ) {
	$labels = array(
		'blacklist_phone' => __( 'Blacklisted phone', 'wfob' ),
		'blacklist_email' => __( 'Blacklisted email', 'wfob' ),
		'blacklist_ip'    => __( 'Blacklisted IP', 'wfob' ),
		'phone_limit'     => __( 'Too many orders from this phone', 'wfob' ),
		'ip_limit'        => __( 'Too many orders from this IP', 'wfob' ),
	);

	return isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
}

/**
 * Keep a short log of blocked attempts (last 50) and a running total.
 *
 * @param string $reason Reason code.
 * @param string $phone  Phone.
 * @param string $email  Email.
 * @param string $ip     IP.
 * @return void
 */
function wfob_log_block( $reason, $phone, $email, $ip ) {
	$log = get_option( 'wfob_block_log', array() );
	$log = is_array( $log ) ? $log : array();

	array_unshift(
		$log,
		array(
			'time'   => time(),
			'reason' => $reason,
			'phone'  => wfob_normalize_phone( $phone ),
			'email'  => sanitize_email( $email ),
			'ip'     => sanitize_text_field( $ip ),
		)
	);

	update_option( 'wfob_block_log', array_slice( $log, 0, 50 ), false );
	update_option( 'wfob_blocked_total', (int) get_option( 'wfob_blocked_total', 0 ) + 1, false );

	do_action( 'wfob_order_blocked', $reason, $phone, $email, $ip );
}

/**
 * Shop managers and admins are never blocked (useful when testing).
 * Filter: wfob_bypass.
 *
 * @return bool
 */
function wfob_bypass() {
	$bypass = is_user_logged_in() && current_user_can( 'manage_woocommerce' );

	return (bool) apply_filters( 'wfob_bypass', $bypass );
}

/* -------------------------------------------------------------------------
 * Customer history
 * ---------------------------------------------------------------------- */

/**
 * Order statistics for a phone number across all its formats.
 *
 * @param string $phone      Phone (any format).
 * @param int    $exclude_id Order ID to leave out (the current order).
 * @return array{total:int, completed:int, active:int, problem:int, rate:int|null}
 */
function wfob_phone_stats( $phone, $exclude_id = 0 ) {
	$stats = array(
		'total'     => 0,
		'completed' => 0,
		'active'    => 0,
		'problem'   => 0,
		'rate'      => null,
	);

	$statuses = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) );
	$seen     = array();

	foreach ( wfob_phone_variants( $phone ) as $variant ) {
		$orders = wc_get_orders(
			array(
				'billing_phone' => $variant,
				'status'        => $statuses,
				'limit'         => 200,
				'type'          => 'shop_order',
			)
		);

		foreach ( $orders as $order ) {
			$id = $order->get_id();

			if ( $id === (int) $exclude_id || isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;
			++$stats['total'];

			$status = $order->get_status();

			if ( 'completed' === $status ) {
				++$stats['completed'];
			} elseif ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ) {
				++$stats['problem'];
			} else {
				++$stats['active'];
			}
		}
	}

	$finished = $stats['completed'] + $stats['problem'];

	if ( $finished > 0 ) {
		$stats['rate'] = (int) round( 100 * $stats['completed'] / $finished );
	}

	return $stats;
}
