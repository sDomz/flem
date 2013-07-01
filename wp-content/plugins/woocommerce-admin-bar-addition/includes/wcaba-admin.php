<?php
/**
 * Helper functions for the admin - plugin links.
 *
 * @package    WooCommerce Admin Bar Addition
 * @subpackage Admin
 * @author     David Decker - DECKERWEB
 * @copyright  Copyright 2011-2012, David Decker - DECKERWEB
 * @license    http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 * @link       http://genesisthemes.de/en/wp-plugins/woocommerce-admin-bar-addition/
 * @link       http://twitter.com/#!/deckerweb
 *
 * @since 1.0
 * @version 1.1
 */

add_filter( 'plugin_row_meta', 'ddw_wcaba_plugin_links', 10, 2 );
/**
 * Add various support links to plugin page
 *
 * @since 1.0
 *
 * @param  $wcaba_links
 * @param  $wcaba_file
 * @return strings plugin links
 */
function ddw_wcaba_plugin_links( $wcaba_links, $wcaba_file ) {

	if ( ! current_user_can( 'install_plugins' ) )
		return $wcaba_links;

	if ( $wcaba_file == WCABA_PLUGIN_BASEDIR . '/woocommerce-admin-bar-addition.php' ) {
		$wcaba_links[] = '<a href="http://wordpress.org/extend/plugins/woocommerce-admin-bar-addition/faq/" target="_new" title="' . __( 'FAQ', 'wcaba' ) . '">' . __( 'FAQ', 'wcaba' ) . '</a>';
		$wcaba_links[] = '<a href="http://wordpress.org/support/plugin/woocommerce-admin-bar-addition" target="_new" title="' . __( 'Support', 'wcaba' ) . '">' . __( 'Support', 'wcaba' ) . '</a>';
		$wcaba_links[] = '<a href="' . __( 'http://genesisthemes.de/en/donate/', 'wcaba' ) . '" target="_new" title="' . __( 'Donate', 'wcaba' ) . '">' . __( 'Donate', 'wcaba' ) . '</a>';
	}

	return $wcaba_links;

}  // end of function ddw_wcaba_plugin_links
