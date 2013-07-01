<?php
/**
 * Display links to active extensions specific settings' pages: WooCommerce Dynamic Pricing.
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
 * WooCommerce Dynamic Pricing (premium, by WooThemes)
 *
 * @since 1.8
 */
	/** Entry at "Extensions" level submenu */
	$menu_items['extwtdynpricing'] = array(
		'parent' => $extensions,
		'title'  => __( 'Dynamic Pricing - Roles', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=wc_dynamic_pricing&tab=roles' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Dynamic Pricing - Roles', 'wcaba' ) )
	);
	$menu_items['extwt-dynpricing-cat'] = array(
		'parent' => $extwtdynpricing,
		'title'  => __( 'Category Pricing', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=wc_dynamic_pricing&tab=category' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Category Pricing', 'wcaba' ) )
	);
	$menu_items['extwt-dynpricing-cat-adv'] = array(
		'parent' => $extwtdynpricing,
		'title'  => __( 'Advanced Category Pricing', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=wc_dynamic_pricing&tab=category&view=1' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Advanced Category Pricing', 'wcaba' ) )
	);

	/** Entry at "Settings" level submenu */
	$menu_items['s_dynpricing'] = array(
		'parent' => $settings,
		'title'  => __( 'Dynamic Pricing - Roles', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=wc_dynamic_pricing&tab=roles' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Dynamic Pricing - Roles', 'wcaba' ) )
	);
	$menu_items['s_dynpricing-cat'] = array(
		'parent' => $s_dynpricing,
		'title'  => __( 'Category Pricing', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=wc_dynamic_pricing&tab=category' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Category Pricing', 'wcaba' ) )
	);
	$menu_items['s_dynpricing-cat-adv'] = array(
		'parent' => $s_dynpricing,
		'title'  => __( 'Advanced Category Pricing', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=wc_dynamic_pricing&tab=category&view=1' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Advanced Category Pricing', 'wcaba' ) )
	);
