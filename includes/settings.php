<?php
/**
 * Settings tab: WooCommerce → Settings → Fake Orders.
 *
 * @package WFOB
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings field definitions.
 *
 * @return array
 */
function wfob_settings_fields() {
	$d = wfob_defaults();

	return array(
		array(
			'title' => __( 'Order limits', 'wfob' ),
			'type'  => 'title',
			'desc'  => __( 'Block customers who place too many orders in a short time. Counting starts when the plugin is activated. Admins and shop managers are never blocked.', 'wfob' ),
			'id'    => 'wfob_limits',
		),
		array(
			'title'   => __( 'Limit orders per phone', 'wfob' ),
			'desc'    => __( 'Enable', 'wfob' ),
			'id'      => 'wfob_phone_limit_enabled',
			'type'    => 'checkbox',
			'default' => $d['phone_limit_enabled'],
		),
		array(
			'title'             => __( 'Max orders per phone', 'wfob' ),
			'id'                => 'wfob_phone_limit',
			'type'              => 'number',
			'default'           => $d['phone_limit'],
			'custom_attributes' => array( 'min' => 1, 'step' => 1 ),
			'css'               => 'width:80px;',
		),
		array(
			'title'             => __( 'Phone time window (hours)', 'wfob' ),
			'id'                => 'wfob_phone_window',
			'type'              => 'number',
			'default'           => $d['phone_window'],
			'custom_attributes' => array( 'min' => 1, 'step' => 1 ),
			'css'               => 'width:80px;',
		),
		array(
			'title'   => __( 'Limit orders per IP', 'wfob' ),
			'desc'    => __( 'Enable', 'wfob' ),
			'desc_tip' => __( 'Careful: many mobile customers in Bangladesh share one IP address. Keep the limit high.', 'wfob' ),
			'id'      => 'wfob_ip_limit_enabled',
			'type'    => 'checkbox',
			'default' => $d['ip_limit_enabled'],
		),
		array(
			'title'             => __( 'Max orders per IP', 'wfob' ),
			'id'                => 'wfob_ip_limit',
			'type'              => 'number',
			'default'           => $d['ip_limit'],
			'custom_attributes' => array( 'min' => 1, 'step' => 1 ),
			'css'               => 'width:80px;',
		),
		array(
			'title'             => __( 'IP time window (hours)', 'wfob' ),
			'id'                => 'wfob_ip_window',
			'type'              => 'number',
			'default'           => $d['ip_window'],
			'custom_attributes' => array( 'min' => 1, 'step' => 1 ),
			'css'               => 'width:80px;',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'wfob_limits',
		),

		array(
			'title' => __( 'Blacklist', 'wfob' ),
			'type'  => 'title',
			'desc'  => __( 'One entry per line. You can also block a customer from the order screen.', 'wfob' ),
			'id'    => 'wfob_blacklist',
		),
		array(
			'title'       => __( 'Blocked phones', 'wfob' ),
			'desc_tip'    => __( 'Any format works: 01712345678, +8801712345678, 01712-345678.', 'wfob' ),
			'id'          => 'wfob_blacklist_phones',
			'type'        => 'textarea',
			'default'     => $d['blacklist_phones'],
			'css'         => 'width:350px;height:110px;',
			'placeholder' => "01712345678\n01812345678",
		),
		array(
			'title'       => __( 'Blocked IPs', 'wfob' ),
			'desc_tip'    => __( 'Exact IP (103.12.5.9) or a prefix ending in * (103.12.*).', 'wfob' ),
			'id'          => 'wfob_blacklist_ips',
			'type'        => 'textarea',
			'default'     => $d['blacklist_ips'],
			'css'         => 'width:350px;height:80px;',
		),
		array(
			'title'       => __( 'Blocked emails', 'wfob' ),
			'desc_tip'    => __( 'Full address, or a whole domain starting with @ (@tempmail.com).', 'wfob' ),
			'id'          => 'wfob_blacklist_emails',
			'type'        => 'textarea',
			'default'     => $d['blacklist_emails'],
			'css'         => 'width:350px;height:80px;',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'wfob_blacklist',
		),

		array(
			'title' => __( 'Cash on Delivery', 'wfob' ),
			'type'  => 'title',
			'id'    => 'wfob_cod',
		),
		array(
			'title'   => __( 'Put COD orders on hold', 'wfob' ),
			'desc'    => __( 'On-hold orders wait for you to confirm them by phone before shipping.', 'wfob' ),
			'id'      => 'wfob_cod_hold',
			'type'    => 'select',
			'default' => $d['cod_hold'],
			'options' => array(
				'none' => __( 'Never (default)', 'wfob' ),
				'new'  => __( 'Only first-time customers (no delivered order yet)', 'wfob' ),
				'all'  => __( 'All COD orders', 'wfob' ),
			),
		),
		array(
			'title'   => __( 'Clean phone numbers', 'wfob' ),
			'desc'    => __( 'Save Bangladeshi mobile numbers as 01XXXXXXXXX (recommended, makes history lookups accurate)', 'wfob' ),
			'id'      => 'wfob_normalize_phone',
			'type'    => 'checkbox',
			'default' => $d['normalize_phone'],
		),
		array(
			'title'   => __( 'Message shown when blocked', 'wfob' ),
			'id'      => 'wfob_block_message',
			'type'    => 'textarea',
			'default' => $d['block_message'],
			'css'     => 'width:350px;height:70px;',
		),
		array(
			'type' => 'sectionend',
			'id'   => 'wfob_cod',
		),
	);
}

add_filter( 'woocommerce_settings_tabs_array', function ( $tabs ) {
	$tabs['wfob'] = __( 'Fake Orders', 'wfob' );
	return $tabs;
}, 50 );

add_action( 'woocommerce_settings_tabs_wfob', function () {
	woocommerce_admin_fields( wfob_settings_fields() );
	wfob_render_block_log();
} );

add_action( 'woocommerce_update_options_wfob', function () {
	woocommerce_update_options( wfob_settings_fields() );
} );

/**
 * Show the last blocked attempts under the settings.
 *
 * @return void
 */
function wfob_render_block_log() {
	$log   = get_option( 'wfob_block_log', array() );
	$total = (int) get_option( 'wfob_blocked_total', 0 );

	echo '<h2>' . esc_html__( 'Recently blocked attempts', 'wfob' ) . '</h2>';
	/* translators: %d: number of blocked attempts. */
	echo '<p>' . esc_html( sprintf( _n( '%d attempt blocked so far.', '%d attempts blocked so far.', $total, 'wfob' ), $total ) ) . '</p>';

	if ( empty( $log ) || ! is_array( $log ) ) {
		echo '<p><em>' . esc_html__( 'Nothing blocked yet.', 'wfob' ) . '</em></p>';
		return;
	}

	echo '<table class="widefat striped" style="max-width:900px"><thead><tr>';
	foreach ( array( __( 'Time', 'wfob' ), __( 'Reason', 'wfob' ), __( 'Phone', 'wfob' ), __( 'Email', 'wfob' ), __( 'IP', 'wfob' ) ) as $head ) {
		echo '<th>' . esc_html( $head ) . '</th>';
	}
	echo '</tr></thead><tbody>';

	foreach ( $log as $row ) {
		echo '<tr>';
		echo '<td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $row['time'] ) ) . '</td>';
		echo '<td>' . esc_html( wfob_reason_label( $row['reason'] ) ) . '</td>';
		echo '<td>' . esc_html( $row['phone'] ) . '</td>';
		echo '<td>' . esc_html( $row['email'] ) . '</td>';
		echo '<td>' . esc_html( $row['ip'] ) . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
}

/**
 * "Settings" link on the Plugins screen.
 */
add_filter( 'plugin_action_links_' . plugin_basename( WFOB_FILE ), function ( $links ) {
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=wfob' ) ) . '">' . esc_html__( 'Settings', 'wfob' ) . '</a>'
	);
	return $links;
} );
