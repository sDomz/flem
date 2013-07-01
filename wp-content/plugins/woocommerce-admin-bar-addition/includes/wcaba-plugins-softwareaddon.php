<?php
/**
 * Display links to active extensions specific settings' pages: WooCommerce Software Add-On.
 *
 * @package    WooCommerce Admin Bar Addition
 * @subpackage Plugin/Extension Support
 * @author     David Decker - DECKERWEB
 * @copyright  Copyright 2011-2012, David Decker - DECKERWEB
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 * @link       http://genesisthemes.de/en/wp-plugins/woocommerce-admin-bar-addition/
 * @link       http://twitter.com/#!/deckerweb
 *
 * @since 2.4
 */

/**
 * WooCommerce Software Add-On (premium, by WooThemes)
 *
 * @since 2.4
 */

	/** Entries at "Extensions" level submenu */
	$menu_items['extwtsoftwareaddon'] = array(
		'parent' => $extensions,
		'title'  => __( 'Software: Licence Keys', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=wc_software_keys' ),
		'meta'   => array( 'target' => '', 'title' => _x( 'Licence Keys (Software Add-On)', 'Translators: For the tooltip', 'wcaba' ) )
	);
	$menu_items['extwtsoftwareaddon-reports-sales'] = array(
		'parent' => $extwtsoftwareaddon,
		'title'  => __( 'Sales Reports', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=software&chart=0' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Sales Reports', 'wcaba' ) )
	);
	$menu_items['extwtsoftwareaddon-reports-activations'] = array(
		'parent' => $extwtsoftwareaddon,
		'title'  => __( 'Software Activations', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=software&chart=1' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Software Activations', 'wcaba' ) )
	);

	/** Entry at "Products" level submenu */
	$menu_items['p_softwareaddon'] = array(
		'parent' => $products,
		'title'  => __( 'Software Licence Keys', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=wc_software_keys' ),
		'meta'   => array( 'target' => '', 'title' => _x( 'Licence Keys (Software Add-On)', 'Translators: For the tooltip', 'wcaba' ) )
	);

	/** Entries at "Reports" level submenu */
	$menu_items['reports-software-sales'] = array(
		'parent' => $reports,
		'title'  => __( 'Software Sales', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=software&chart=0' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Software Sales', 'wcaba' ) )
	);
	$menu_items['reports-software-activations'] = array(
		'parent' => $reports,
		'title'  => __( 'Software Activations', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=software&chart=1' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Software Activations', 'wcaba' ) )
	);
