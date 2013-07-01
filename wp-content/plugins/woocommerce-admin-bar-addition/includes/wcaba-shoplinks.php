<?php
/**
 * Display shop links if theme support is enabled
 *
 * @package    WooCommerce Admin Bar Addition
 * @subpackage Shop Links
 * @author     David Decker - DECKERWEB
 * @copyright  Copyright 2012, David Decker - DECKERWEB
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 * @link       http://genesisthemes.de/en/wp-plugins/woocommerce-admin-bar-addition/
 * @link       http://twitter.com/#!/deckerweb
 *
 * @since 2.2
 */

/**
 * Display shop links for testing purposes
 * Will only be displayed if theme support is added via
 * "add_theme_support( 'wcaba-shop-links' );" to active theme/child theme
 *
 * @since 2.2
 */
$menu_items['shoplink'] = array(
	'parent' => $woocommercebar,
	'title'  => __( 'Go to Shop', 'wcaba' ),
	'href'   => get_permalink( woocommerce_get_page_id( 'shop' ) ),
	'meta'   => array( 'target' => '_new', 'title' => _x( 'Go to Shop Base Page', 'Translators: For the tooltip', 'wcaba' ) )
);
$menu_items['shoplink-cart'] = array(
	'parent' => $shoplink,
	'title'  => __( 'Cart Page', 'wcaba' ),
	'href'   => get_permalink( woocommerce_get_page_id( 'cart' ) ),
	'meta'   => array( 'target' => '_new', 'title' => __( 'Cart Page', 'wcaba' ) )
);
$menu_items['shoplinkcheckout'] = array(
	'parent' => $shoplink,
	'title'  => __( 'Checkout Page', 'wcaba' ),
	'href'   => get_permalink( woocommerce_get_page_id( 'checkout' ) ),
	'meta'   => array( 'target' => '_new', 'title' => __( 'Checkout Page', 'wcaba' ) )
);
	$menu_items['shoplinkcheckout-pay'] = array(
		'parent' => $shoplinkcheckout,
		'title'  => __( 'Pay Page', 'wcaba' ),
		'href'   => get_permalink( woocommerce_get_page_id( 'pay' ) ),
		'meta'   => array( 'target' => '_new', 'title' => __( 'Pay Page', 'wcaba' ) )
	);
	$menu_items['shoplinkcheckout-thanks'] = array(
		'parent' => $shoplinkcheckout,
		'title'  => __( 'Thanks Page', 'wcaba' ),
		'href'   => get_permalink( woocommerce_get_page_id( 'thanks' ) ),
		'meta'   => array( 'target' => '_new', 'title' => __( 'Thanks Page', 'wcaba' ) )
	);
$menu_items['shoplinkmyaccount'] = array(
	'parent' => $shoplink,
	'title'  => __( 'My Account Page', 'wcaba' ),
	'href'   => get_permalink( woocommerce_get_page_id( 'myaccount' ) ),
	'meta'   => array( 'target' => '_new', 'title' => __( 'My Account Page', 'wcaba' ) )
);
	$menu_items['shoplinkmyaccount-editaddress'] = array(
		'parent' => $shoplinkmyaccount,
		'title'  => __( 'Edit Address Page', 'wcaba' ),
		'href'   => get_permalink( woocommerce_get_page_id( 'edit_address' ) ),
		'meta'   => array( 'target' => '_new', 'title' => __( 'Edit Address Page', 'wcaba' ) )
	);
	$menu_items['shoplinkmyaccount-vieworder'] = array(
		'parent' => $shoplinkmyaccount,
		'title'  => __( 'View Order Page', 'wcaba' ),
		'href'   => get_permalink( woocommerce_get_page_id( 'view_order' ) ),
		'meta'   => array( 'target' => '_new', 'title' => __( 'View Order Page', 'wcaba' ) )
	);
	$menu_items['shoplinkmyaccount-changepassword'] = array(
		'parent' => $shoplinkmyaccount,
		'title'  => __( 'Change Password Page', 'wcaba' ),
		'href'   => get_permalink( woocommerce_get_page_id( 'change_password' ) ),
		'meta'   => array( 'target' => '_new', 'title' => __( 'Change Password Page', 'wcaba' ) )
	);
$menu_items['shoplink-terms'] = array(
	'parent' => $shoplink,
	'title'  => __( 'Terms &amp; Conditions Page', 'wcaba' ),
	'href'   => get_permalink( woocommerce_get_page_id( 'terms' ) ),
	'meta'   => array( 'target' => '_new', 'title' => __( 'Terms &amp; Conditions Page', 'wcaba' ) )
);

// Display only for WooCommerce German Extension
if ( class_exists( 'Woocommerce_German_Extension' ) ) {
	$menu_items['shoplinkde'] = array(
		'parent' => $shoplink,
		'title'  => '<abbr title="' . _x( 'German Extension for Legal Certainty (by Inpsyde GmbH)', 'Translators: For the tooltip', 'wcaba' ) . '">' . __( 'German Extension:', 'wcaba' ) . '</abbr>',
		'href'   => false,
		'meta'   => array( 'target' => '_new', 'title' => _x( 'German Extension for Legal Certainty (by Inpsyde GmbH)', 'Translators: For the tooltip', 'wcaba' ) )
	);
		$menu_items['shoplinkde-imprint'] = array(
			'parent' => $shoplinkde,
			'title'  => __( 'Imprint Page', 'wcaba' ),
			'href'   => get_permalink( woocommerce_get_page_id( 'impressum' ) ),
			'meta'   => array( 'target' => '_new', 'title' => __( 'Imprint Page', 'wcaba' ) )
		);
		$menu_items['shoplinkde-shippingcost'] = array(
			'parent' => $shoplinkde,
			'title'  => __( 'Shipping Costs Page', 'wcaba' ),
			'href'   => get_permalink( woocommerce_get_page_id( 'versandkosten' ) ),
			'meta'   => array( 'target' => '_new', 'title' => __( 'Shipping Costs Page', 'wcaba' ) )
		);
		$menu_items['shoplinkde-refund'] = array(
			'parent' => $shoplinkde,
			'title'  => __( 'Refund Policy Page', 'wcaba' ),
			'href'   => get_permalink( woocommerce_get_page_id( 'widerruf' ) ),
			'meta'   => array( 'target' => '_new', 'title' => __( 'Refund Policy Page', 'wcaba' ) )
		);
		$menu_items['shoplinkde-privacy'] = array(
			'parent' => $shoplinkde,
			'title'  => __( 'Privacy Page', 'wcaba' ),
			'href'   => get_permalink( woocommerce_get_page_id( 'datenschutz' ) ),
			'meta'   => array( 'target' => '_new', 'title' => __( 'Privacy Page', 'wcaba' ) )
		);
		$menu_items['shoplinkde-orderinstructions'] = array(
			'parent' => $shoplinkde,
			'title'  => __( 'Order Instructions Page', 'wcaba' ),
			'href'   => get_permalink( woocommerce_get_page_id( 'bestellvorgang' ) ),
			'meta'   => array( 'target' => '_new', 'title' => __( 'Order Instructions Page', 'wcaba' ) )
		);
		$menu_items['shoplinkde-paymentgateways'] = array(
			'parent' => $shoplinkde,
			'title'  => __( 'Payment Gateways Page', 'wcaba' ),
			'href'   => get_permalink( woocommerce_get_page_id( 'zahlungsarten' ) ),
			'meta'   => array( 'target' => '_new', 'title' => __( 'Payment Gateways Page', 'wcaba' ) )
		);
		$menu_items['shoplinkde-checkorder'] = array(
			'parent' => $shoplinkde,
			'title'  => __( 'Check Order Page', 'wcaba' ),
			'href'   => get_permalink( woocommerce_get_page_id( 'check' ) ),
			'meta'   => array( 'target' => '_new', 'title' => __( 'Check Order Page', 'wcaba' ) )
		);
}  // end-if German Extension plugin
