<?php
/**
 * Display links to active extensions specific settings' pages: Pronamic iDEAL.
 *
 * @package    WooCommerce Admin Bar Addition
 * @subpackage Plugin/Extension Support
 * @author     David Decker - DECKERWEB
 * @copyright  Copyright 2011-2012, David Decker - DECKERWEB
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 * @link       http://genesisthemes.de/en/wp-plugins/woocommerce-admin-bar-addition/
 * @link       http://twitter.com/#!/deckerweb
 *
 * @since 1.7
 * @version 1.1
 */

/**
 * Pronamic iDEAL settings pages (free, by Pronamic)
 *
 * @since 1.7
 */

	$menu_items['extpideal'] = array(
		'parent' => $extensions,
		'title'  => __( 'Pronamic iDEAL Payment Gateway', 'wcaba' ),
		'href'   => admin_url( 'admin.php?page=pronamic_ideal' ),
		'meta'   => array( 'target' => '', 'title' => __( 'Pronamic iDEAL Payment Gateway', 'wcaba' ) )
	);

	/** Check for Payments */
	if ( current_user_can( 'pronamic_ideal_payments' ) ) {
		$menu_items['ext-pideal-payments'] = array(
			'parent' => $extpideal,
			'title'  => __( 'Payments', 'wcaba' ),
			'href'   => admin_url( 'admin.php?page=pronamic_ideal_payments' ),
			'meta'   => array( 'target' => '', 'title' => __( 'Payments', 'wcaba' ) )
		);
	}  // end-if cap check

	/** Check for Settings */
	if ( current_user_can( 'pronamic_ideal_settings' ) ) {
		$menu_items['ext-pideal-settings'] = array(
			'parent' => $extpideal,
			'title'  => __( 'Settings', 'wcaba' ),
			'href'   => admin_url( 'admin.php?page=pronamic_ideal_settings' ),
			'meta'   => array( 'target' => '', 'title' => __( 'Settings', 'wcaba' ) )
		);
	}  // end-if cap check

	/** Check for Pages Generator */
	if ( current_user_can( 'pronamic_ideal_pages_generator' ) ) {
		$menu_items['ext-pideal-pages-generator'] = array(
			'parent' => $extpideal,
			'title'  => __( 'Pages Generator', 'wcaba' ),
			'href'   => admin_url( 'admin.php?page=pronamic_ideal_pages_generator' ),
			'meta'   => array( 'target' => '', 'title' => __( 'Pages Generator', 'wcaba' ) )
		);
	}  // end-if cap check

	/** Check for Variants */
	if ( current_user_can( 'pronamic_ideal_variants' ) ) {
		$menu_items['ext-pideal-variants'] = array(
			'parent' => $extpideal,
			'title'  => __( 'Variants', 'wcaba' ),
			'href'   => admin_url( 'admin.php?page=pronamic_ideal_variants' ),
			'meta'   => array( 'target' => '', 'title' => __( 'Variants', 'wcaba' ) )
		);
	}  // end-if cap check

	/** Check for Documentation */
	if ( current_user_can( 'pronamic_ideal_documentation' ) ) {
		$menu_items['ext-pideal-documentation'] = array(
			'parent' => $extpideal,
			'title'  => __( 'Documentation', 'wcaba' ),
			'href'   => admin_url( 'admin.php?page=pronamic_ideal_documentation' ),
			'meta'   => array( 'target' => '', 'title' => __( 'Documentation', 'wcaba' ) )
		);
	}  // end-if cap check
