<?php
/**
 * Display links to active extensions specific settings' pages: WooCommerce Commission King.
 *
 * @package    WooCommerce Admin Bar Addition
 * @subpackage Plugin/Extension Support
 * @author     David Decker - DECKERWEB
 * @copyright  Copyright 2011-2012, David Decker - DECKERWEB
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 * @link       http://genesisthemes.de/en/wp-plugins/woocommerce-admin-bar-addition/
 * @link       http://twitter.com/#!/deckerweb
 *
 * @since 1.8
 * @version 1.1
 */

/**
 * WooCommerce Commission King (premium, by WooThemes)
 *
 * @since 1.8
 */
	/** Entry at "Extensions" level submenu */
	$menu_items['extwtcommissionking'] = array(
		'parent' => $extensions,
		'title'  => __( 'Commission King Options', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=woocommerce&tab=woo_ck' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Commission King Options', 'wcaba' ) )
	);
	$menu_items['extwtcommissionking-commissions'] = array(
		'parent' => $extwtcommissionking,
		'title'  => __( 'Commissions', 'wcaba' ),
		'href'   => admin_url( 'edit.php?post_type=shop_commission' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Commissions', 'wcaba' ) )
	);
	$menu_items['extwtcommissionking-pay'] = array(
		'parent' => $extwtcommissionking,
		'title'  => __( 'Pay Commissions', 'wcaba' ),
		'href'   => admin_url( 'edit.php?post_type=shop_commission&page=woo_ck_commission_pay' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Pay Commissions', 'wcaba' ) )
	);
	$menu_items['extwtcommissionking-settings'] = array(
		'parent' => $extwtcommissionking,
		'title'  => __( 'More Settings', 'wcaba' ),
		'href'   => admin_url( 'edit.php?post_type=shop_commission&page=woo_ck_commission_settings' ),
		'meta'   => array( 'target' => '', 'title' => __( 'More Settings', 'wcaba' ) )
	);

	/** Entry at "Settings" level submenu */
	$menu_items['s_commissionking'] = array(
		'parent' => $settings,
		'title'  => __( 'Commission King Options', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=woocommerce&tab=woo_ck' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Commission King Options', 'wcaba' ) )
	);
	$menu_items['s_commissionking-commissions'] = array(
		'parent' => $s_commissionking,
		'title'  => __( 'Commissions', 'wcaba' ),
		'href'   => admin_url( 'edit.php?post_type=shop_commission' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Commissions', 'wcaba' ) )
	);
	$menu_items['s_commissionking-pay'] = array(
		'parent' => $s_commissionking,
		'title'  => __( 'Pay Commissions', 'wcaba' ),
		'href'   => admin_url( 'edit.php?post_type=shop_commission&page=woo_ck_commission_pay' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Pay Commissions', 'wcaba' ) )
	);
	$menu_items['s_commissionking-settings'] = array(
		'parent' => $s_commissionking,
		'title'  => __( 'More Settings', 'wcaba' ),
		'href'   => admin_url( 'edit.php?post_type=shop_commission&page=woo_ck_commission_settings' ),
		'meta'   => array( 'target' => '', 'title' => __( 'More Settings', 'wcaba' ) )
	);
