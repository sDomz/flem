<?php 
/**
 * Main plugin file.
 * This plugin adds useful admin links and massive resources for the WooCommerce Shop Plugin to the WordPress Toolbar / Admin Bar.
 *
 * @package   WooCommerce Admin Bar Addition
 * @author    David Decker
 * @link      http://twitter.com/#!/deckerweb
 * @copyright Copyright 2011-2012, David Decker - DECKERWEB
 *
 * @credits   Inspired and based on the plugin "WooThemes Admin Bar Addition" by Remkus de Vries @defries.
 * @link      http://remkusdevries.com/
 * @link      http://twitter.com/#!/defries
 *
 * Plugin Name: WooCommerce Admin Bar Addition
 * Plugin URI: http://genesisthemes.de/en/wp-plugins/woocommerce-admin-bar-addition/
 * Description: This plugin adds useful admin links and resources for the WooCommerce Shop Plugin to the WordPress Toolbar / Admin Bar.
 * Version: 2.4
 * Author: David Decker - DECKERWEB
 * Author URI: http://deckerweb.de/
 * License: GPLv2 or later
 * License URI: http://www.opensource.org/licenses/gpl-license.php
 * Text Domain: wcaba
 * Domain Path: /languages/
 *
 * Copyright 2011-2012 David Decker - DECKERWEB
 *
 *     This file is part of WooCommerce Admin Bar Addition,
 *     a plugin for WordPress.
 *
 *     WooCommerce Admin Bar Addition is free software:
 *     You can redistribute it and/or modify it under the terms of the
 *     GNU General Public License as published by the Free Software
 *     Foundation, either version 2 of the License, or (at your option)
 *     any later version.
 *
 *     WooCommerce Admin Bar Addition is distributed in the hope that
 *     it will be useful, but WITHOUT ANY WARRANTY; without even the
 *     implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *     PURPOSE. See the GNU General Public License for more details.
 *
 *     You should have received a copy of the GNU General Public License
 *     along with WordPress. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Setting constants
 *
 * @since 1.0
 * @version 1.2
 */
/** Plugin directory */
define( 'WCABA_PLUGIN_DIR', dirname( __FILE__ ) );

/** Plugin base directory (folder) */
define( 'WCABA_PLUGIN_BASEDIR', dirname( plugin_basename( __FILE__ ) ) );

/** Various link/content related helper constants */
define( 'WCABA_VTUTORIALS_YTBE', apply_filters( 'wcaba_filter_video_tutorials', 'http://www.youtube.com/results?search_type=videos&search_query=woocommerce&search_sort=video_date_uploaded' ) );


add_action( 'init', 'ddw_woocommerce_aba_init' );
/**
 * Load the text domain for translation of the plugin.
 * Load admin helper functions - only within 'wp-admin'.
 * 
 * @since 1.0
 * @version 1.4
 */
function ddw_woocommerce_aba_init() {

	/** First look in WordPress' "languages" folder = custom & update-secure! */
	load_plugin_textdomain( 'wcaba', false, WCABA_PLUGIN_BASEDIR . '/../../languages/wcaba/' );

	/** Then look in plugin's "languages" folder = default */
	load_plugin_textdomain( 'wcaba', false, WCABA_PLUGIN_BASEDIR . '/languages/' );

	/** If 'wp-admin' include admin helper functions */
	if ( is_admin() ) {
		require_once( WCABA_PLUGIN_DIR . '/includes/wcaba-admin.php' );
	}

	/** Define constants and set defaults for removing all or certain sections */
	if ( ! defined( 'WCABA_DISPLAY' ) ) {
		define( 'WCABA_DISPLAY', TRUE );
	}

	if ( ! defined( 'WCABA_EXTENSIONS_DISPLAY' ) ) {
		define( 'WCABA_EXTENSIONS_DISPLAY', TRUE );
	}

	if ( ! defined( 'WCABA_THEMES_DISPLAY' ) ) {
		define( 'WCABA_THEMES_DISPLAY', TRUE );
	}

	if ( ! defined( 'WCABA_RESOURCES_DISPLAY' ) ) {
		define( 'WCABA_RESOURCES_DISPLAY', TRUE );
	}

	if ( ! defined( 'WCABA_DE_DISPLAY' ) ) {
		define( 'WCABA_DE_DISPLAY', TRUE );
	}

	if ( ! defined( 'WCABA_NL_DISPLAY' ) ) {
		define( 'WCABA_NL_DISPLAY', TRUE );
	}

	if ( ! defined( 'WCABA_DEBUG_DISPLAY' ) ) {
		define( 'WCABA_DEBUG_DISPLAY', TRUE );
	}

	if ( ! defined( 'WCABA_REPORTS_DISPLAY' ) ) {
		define( 'WCABA_REPORTS_DISPLAY', TRUE );
	}

}  // end of function ddw_woocommerce_aba_init


add_action( 'admin_bar_menu', 'ddw_woocommerce_aba_admin_bar_menu', 98 );
/**
 * Add new menu items to the WordPress Toolbar / Admin Bar.
 * 
 * @since 1.0
 * @version 1.2
 *
 * @global mixed $wp_admin_bar 
 */
function ddw_woocommerce_aba_admin_bar_menu() {

	global $wp_admin_bar;

	/**
	 * Allows for filtering the general user capability to see main & sub-level items
	 *
	 * Default capability: 'manage_woocommerce' (set by WooCommerce plugin itself!)
	 *
	 * @since 2.2
	 */
	$wcaba_filter_capability = apply_filters( 'wcaba_filter_capability_all', 'manage_woocommerce' );

	/**
	 * Required WooCommerce/ WordPress cabability to display new admin bar entry
	 * Only showing items if toolbar / admin bar is activated and user is logged in!
	 *
	 * @since 1.0
	 * @version 1.2
	 */
	if ( ! is_user_logged_in() || 
		! is_admin_bar_showing() || 
		! current_user_can( $wcaba_filter_capability ) ||  // users without proper cap won't see anything!
		! WCABA_DISPLAY  // allows for custom disabling
	)
		return;

	/** Set unique prefix */
	$prefix = 'ddw-woocommerce-';
	
	/** Create parent menu item references */
	$woocommercebar = $prefix . 'admin-bar';		// root level
		$support = $prefix . 'wcsupport';				// sub level: support
		$wcdocs = $prefix . 'wcdocs';					// sub level: wc docs
		$woocommercesites = $prefix . 'woocommercesites';		// sub level: wc sites
		$settings = $prefix . 'settings';				// sub level: settings
			$sgeneralsettings = $prefix . 'sgeneralsettings';		// third level: general settings / wc debug
			$settingsintegration = $prefix . 'settingsintegration';		// third level: integration(s)
			$s_dynpricing = $prefix . 's_dynpricing';			// third level settings: dynamic pricing
			$s_commissionking = $prefix . 's_commissionking';		// third level settings: comission king
		$products = $prefix . 'products';				// sub level: products
			$p_compareproductslite = $prefix . 'p_compareproductslite';	// third level products: compare products lite
			$p_compareproductspro = $prefix . 'p_compareproductspro';	// third level products: compare products pro
			$p_csvimportsuite = $prefix . 'p_csvimportsuite';		// third level products: csv import suite
		$coupons = $prefix . 'coupons';					// sub level: coupons
		$orders = $prefix . 'orders';					// sub level: orders
		$reports = $prefix . 'reports';					// sub level: reports
			$reportssales = $prefix . 'reportssales';			// third level: reports sales
		$shoplink = $prefix . 'shoplink';				// sub level: shop link
			$shoplinkcheckout = $prefix . 'shoplinkcheckout';		// third level: shop link checkout
			$shoplinkmyaccount = $prefix . 'shoplinkmyaccount';		// third level: shop link my account
			$shoplinkde = $prefix . 'shoplinkde';				// third level: shop link german extension
		$extensions = $prefix . 'extensions';				// sub level: extensions
			$extpideal = $prefix . 'extpideal';				// third level plugin ext.: pronamic ideal
			$extwtsoftwareaddon = $prefix . 'extwtsoftwareaddon';		// third level plugin ext.: software add-on
			$extwtdynpricing = $prefix . 'extwtdynpricing';			// third level plugin ext.: dynamic pricing
			$extwtcommissionking = $prefix . 'extwtcommissionking';		// third level plugin ext.: comission king
			$extwtclickatellsms = $prefix . 'extwtclickatellsms';		// third level plugin ext.: clickatell sms
			$extwtquickbooks = $prefix . 'extwtquickbooks';			// third level plugin ext.: quickbooks
			$extwccplite = $prefix . 'extwccplite';				// third level plugin ext.: compare products lite
			$extwccppro = $prefix . 'extwccppro';				// third level plugin ext.: compare products pro
			$extwtcsvimportsuite = $prefix . 'extwtcsvimportsuite';		// third level plugin ext.: csv import suite
		$wcgroup = $prefix . 'wcgroup';					// sub level: wc group (resources)


	/** Make the "WooCommerce" name filterable within menu items */
	$wcaba_woocommerce_name = apply_filters( 'wcaba_filter_woocommerce_name', __( 'WooCommerce', 'wcaba' ) );

	/** Make the "WooCommerce" name's tooltip filterable within menu items */
	$wcaba_woocommerce_name_tooltip = apply_filters( 'wcaba_filter_woocommerce_name_tooltip', _x( 'WooCommerce', 'Translators: For the tooltip', 'wcaba' ) );


	/** For the Docs/Codex search */
	$wcaba_search_docs = __( 'Search Docs', 'wcaba' );
	$wcaba_go_button = '<input type="submit" value="' . __( 'GO', 'wcaba' ) . '" class="wcaba-search-go"  /></form>';


	/**
	 * Check for WordPress version to add parent ids for resource links group
	 * Check against WP 3.3+ only function "wp_editor" - if true use "$wcgroup" as parent (WP 3.3+ style)
	 * otherwise use "$woocommercebar" as parent (WP 3.1/3.2 style)
	 *
	 * @since 2.1
	 *
	 * @param $wcgroup_check_item
	 */
	if ( function_exists( 'wp_editor' ) ) {
		$wcgroup_check_item = $wcgroup;
	} else {
		$wcgroup_check_item = $woocommercebar;
	}

	/** Display the following items also when WooCommerce plugin is not installed */
	if ( WCABA_RESOURCES_DISPLAY ) {

		$menu_items = array(
			/** Support menu items */
			'wcsupport' => array(
				'parent' => $wcgroup_check_item,
				'title'  => __( 'WooCommerce Support', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/support-forum/?viewforum=131',
				'meta'   => array( 'title' => __( 'WooCommerce Support', 'wcaba' ) )
			),
			'wcforum' => array(
				'parent' => $support,
				'title'  => __( 'Official Forum (Premium)', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/support-forum/?viewforum=131',
				'meta'   => array( 'title' => __( 'Official Forum (Premium)', 'wcaba' ) )
			),
			'wcpublicforum' => array(
				'parent' => $support,
				'title'  => __( 'Public Community Forum', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/support-forum/?viewforum=150',
				'meta'   => array( 'title' => __( 'Public Community Forum', 'wcaba' ) )
			),
			'wcudashboard' => array(
				'parent' => $support,
				'title'  => __( 'My User Dashboard', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/dashboard/',
				'meta'   => array( 'title' => __( 'My User Dashboard at WooThemes.com', 'wcaba' ) )
			),
			'wcuprofile' => array(
				'parent' => $support,
				'title'  => __( 'My User Profile', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/profile/',
				'meta'   => array( 'title' => __( 'My User Profile at WooThemes.com', 'wcaba' ) )
			),
			'wcsupportwporg' => array(
				'parent' => $support,
				'title'  => __( 'Free Support Forum (WP.org)', 'wcaba' ),
				'href'   => 'http://wordpress.org/tags/woocommerce?forum_id=10',
				'meta'   => array( 'title' => __( 'Free Support Forum (WP.org)', 'wcaba' ) )
			),

			/** Docs/Codex menu items */
			'wcdocs' => array(
				'parent' => $wcgroup_check_item,
				'title'  => __( 'Documentation &amp; User Guide', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/woocommerce-docs/',
				'meta'   => array( 'title' => __( 'Documentation &amp; User Guide', 'wcaba' ) )
			),
			'wcdocs-userguide' => array(
				'parent' => $wcdocs,
				'title'  => __( 'User Guide', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/woocommerce-docs/category/user-guide/',
				'meta'   => array( 'title' => __( 'User Guide', 'wcaba' ) )
			),
			'wcdocs-codex' => array(
				'parent' => $wcdocs,
				'title'  => __( 'Codex', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/woocommerce-docs/category/codex/',
				'meta'   => array( 'title' => __( 'Codex', 'wcaba' ) )
			),
			'wcdocs-tutorials' => array(
				'parent' => $wcdocs,
				'title'  => __( 'Tutorials', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/woocommerce-docs/category/tutorials/',
				'meta'   => array( 'title' => __( 'Tutorials', 'wcaba' ) )
			),
			'wcdocs-snippets' => array(
				'parent' => $wcdocs,
				'title'  => __( 'Snippets', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/woocommerce-docs/category/snippets/',
				'meta'   => array( 'title' => __( 'Snippets', 'wcaba' ) )
			),
			'wcdocs-communityvideos' => array(
				'parent' => $wcdocs,
				'title'  => __( 'Community Videos', 'wcaba' ),
				'href'   => esc_url( WCABA_VTUTORIALS_YTBE ),
				'meta'   => array( 'title' => __( 'Community Videos', 'wcaba' ) )
			),

			/** Docs/Codex search form */
			'wcdocs-searchform' => array(
				'parent' => $wcgroup_check_item,
				'title' => '<form method="get" action="http://www.woothemes.com/woocommerce-docs/" class=" " target="_blank">
				<input type="text" placeholder="' . $wcaba_search_docs . '" onblur="this.value=(this.value==\'\') ? \'' . $wcaba_search_docs . '\' : this.value;" onfocus="this.value=(this.value==\'' . $wcaba_search_docs . '\') ? \'\' : this.value;" value="' . $wcaba_search_docs . '" name="s" value="' . esc_attr( 'Search Docs', 'wcaba' ) . '" class="text wcaba-search-input" />' . $wcaba_go_button,
				'href'   => false,
				'meta'   => array( 'target' => '', 'title' => _x( 'Search Docs', 'Translators: For the tooltip', 'wcaba' ) )
			),

			/** WooCommerce HQ menu items */
			'woocommercesites' => array(
				'parent' => $wcgroup_check_item,
				'title'  => __( 'WooCommerce HQ', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/woocommerce/',
				'meta'   => array( 'title' => __( 'WooCommerce HQ', 'wcaba' ) )
			),
			'wcideas' => array(
				'parent' => $woocommercesites,
				'title'  => __( 'Ideas &amp; Feature Requests', 'wcaba' ),
				'href'   => 'http://woo.uservoice.com/forums/133476-woocommerce',
				'meta'   => array( 'title' => __( 'Ideas &amp; Feature Requests', 'wcaba' ) )
			),
			'wcbugs' => array(
				'parent' => $woocommercesites,
				'title'  => __( 'Issues &amp; Bug Reports', 'wcaba' ),
				'href'   => 'https://github.com/woothemes/woocommerce/issues',
				'meta'   => array( 'title' => __( 'Issues &amp; Bug Reports', 'wcaba' ) )
			),
			'wcdev' => array(
				'parent' => $woocommercesites,
				'title'  => __( 'GitHub Repository Developer Center', 'wcaba' ),
				'href'   => 'https://github.com/woothemes/woocommerce/',
				'meta'   => array( 'title' => __( 'GitHub Repository Developer Center', 'wcaba' ) )
			),
			'wcextensions' => array(
				'parent' => $woocommercesites,
				'title'  => __( 'Official WooCommerce Extensions', 'wcaba' ),
				'href'   => 'http://www.woothemes.com/extensions/woocommerce-extensions/',
				'meta'   => array( 'title' => __( 'Official WooCommerce Extensions', 'wcaba' ) )
			),
			'wcccmarket' => array(
				'parent' => $woocommercesites,
				'title'  => __( 'More premium extensions at CodeCanyon', 'wcaba' ),
				'href'   => 'http://ddwb.me/wccc',
				'meta'   => array( 'title' => __( 'More premium plugins/extensions at CodeCanyon Marketplace', 'wcaba' ) )
			),
			'wcplugins' => array(
				'parent' => $woocommercesites,
				'title'  => __( 'More free plugins/extensions at WP.org', 'wcaba' ),
				'href'   => 'http://wordpress.org/extend/plugins/search.php?q=woocommerce',
				'meta'   => array( 'title' => __( 'More free plugins/extensions at WP.org', 'wcaba' ) )
			),
			'wcffnews' => array(
				'parent' => $woocommercesites,
				'title'  => __( 'WooCommerce News Planet', 'wcaba' ),
				'href'   => 'http://friendfeed.com/woocommerce-news',
				'meta'   => array( 'title' => _x( 'WooCommerce News Planet (official and community news via FriendFeed service)', 'Translators: For the tooltip', 'wcaba' ) )
			),
		);

	}  // end-if constant check for displaying resources


	/** Display these links only for these locales: de_DE, de_AT, de_CH, de_LU */
	if ( WCABA_DE_DISPLAY && ( get_locale() == 'de_DE' ||
						get_locale() == 'de_AT' ||
						get_locale() == 'de_CH' ||
						get_locale() == 'de_LU' ) 
	) {

		/** WooCommerce German Extension plugin */
		if ( class_exists( 'Woocommerce_German_Extension' ) ) {
			$menu_items['wcgermany'] = array(
				'parent' => $settings,
				'title'  => __( 'Preferences DE', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=preferences_de' ),
				'meta'   => array( 'target' => '', 'title' => _x( 'Preferences DE', 'Translators: For the tooltip', 'wcaba' ) )
			);
			$menu_items['preferences-de'] = array(
				'parent' => $extensions,
				'title'  => __( 'Preferences DE', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=preferences_de' ),
				'meta'   => array( 'target' => '', 'title' => _x( 'Preferences DE', 'Translators: For the tooltip', 'wcaba' ) )
			);
		}  // end-if German Extension plugin

		/** German language plugin - only if DE plugin not active */
		if ( !class_exists( 'WooCommerce_de_DE' ) ) {
			$menu_items['wclanguages-de'] = array(
				'parent' => $wcgroup_check_item,
				'title'  => __( 'German language plugin', 'wcaba' ),
				'href'   => 'http://wordpress.org/extend/plugins/woocommerce-de/',
				'meta'   => array( 'title' => _x( 'German language plugin for WooCommerce - with complete translations and formal/informal version support!', 'Translators: For the tooltip', 'wcaba' ) )
			);
		}  // end-if German language plugin

		/** Downloadable German language packs */
		$menu_items['languages-de'] = array(
			'parent' => $wcgroup_check_item,
			'title'  => __( 'German language files', 'wcaba' ),
			'href'   => 'http://deckerweb.de/material/sprachdateien/woocommerce-und-extensions/',
			'meta'   => array( 'title' => _x( 'German language files', 'Translators: For the tooltip', 'wcaba' ) )
		);
	}  // end-if German locale


	/** Display links to language plugin only for this locale: nl, nl_NL - and when NL plugin not active */
	if ( WCABA_NL_DISPLAY && 
		( ( get_locale() == 'nl' || get_locale() == 'nl_NL' ) && !class_exists( 'WooCommerceNL' ) ) 
	) {
		$menu_items['wclanguages-nl'] = array(
			'parent' => $wcgroup_check_item,
			'title'  => __( 'Dutch language plugin', 'wcaba' ),
			'href'   => 'http://wordpress.org/extend/plugins/woocommerce-nl/',
			'meta'   => array( 'title' => _x( 'Dutch language plugin for WooCommerce - with complete translations!', 'Translators: For the tooltip', 'wcaba' ) )
		);
	}  // end-if Dutch locale


	/**
	 * Show these items only if WooCommerce plugin is actually installed
	 * Check for classes "woocommerce" (WC prior v1.4) or "Woocommerce" (WC v1.4+)
	 *
	 * @since 1.0
	 * @version 1.1
	 */
	if ( class_exists( 'woocommerce' ) || class_exists( 'Woocommerce' ) ) {

		/** Settings links */
		if ( current_user_can( 'manage_woocommerce' ) ) {
			$menu_items['settings'] = array(
				'parent' => $woocommercebar,
				'title'  => __( 'Shop Settings', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Shop Settings', 'wcaba' ) )
			);
			$menu_items['sgeneralsettings'] = array(
				'parent' => $settings,
				'title'  => __( 'General Settings', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=general' ),
				'meta'   => array( 'target' => '', 'title' => __( 'General Settings', 'wcaba' ) )
			);

			/** Allows for hiding the WC Debug link */
			if ( WCABA_DEBUG_DISPLAY ) {
				$menu_items['sgeneralsettings-debug'] = array(
					'parent' => $sgeneralsettings,
					'title'  => __( 'Debugging', 'wcaba' ),
					'href'   => admin_url( 'tools.php?page=woocommerce_debug' ),
					'meta'   => array( 'target' => '', 'title' => __( 'WooCommerce Debugging', 'wcaba' ) )
				);
			}  // end-if debug constant check

			$menu_items['s-shop-pages'] = array(
				'parent' => $settings,
				'title'  => __( 'Shop Pages', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=pages' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Shop Pages', 'wcaba' ) )
			);
			$menu_items['s-shop-catalog'] = array(
				'parent' => $settings,
				'title'  => __( 'Shop Catalog', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=catalog' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Shop Catalog', 'wcaba' ) )			
			);
			$menu_items['s-inventory'] = array(
				'parent' => $settings,
				'title'  => __( 'Inventory', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=inventory' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Inventory', 'wcaba' ) )
			);
			$menu_items['s-shipping-costs'] = array(
				'parent' => $settings,
				'title'  => __( 'Shipping Options &amp; Costs', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=shipping' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Shipping Options &amp; Costs', 'wcaba' ) )
			);

			/**
			 * Check for "Shipping Methods" settings link - prior WooCommerce v1.4
			 * Just for backwards compatibility
			 *
			 * Checks for existing function "woocommerce_init" (only prior WooCommerce v1.4)
			 *
			 * @since 1.6
			 */
			if ( function_exists ( 'woocommerce_init' ) ) {
				$menu_items['s-shipping-methods'] = array(
					'parent' => $settings,
					'title'  => __( 'Shipping Methods', 'wcaba' ),
					'href'   => admin_url( 'admin.php?page=woocommerce&tab=shipping_methods' ),
					'meta'   => array( 'target' => '', 'title' => __( 'Shipping Methods', 'wcaba' ) )
				);
			}

			/** Setting links continued */
			$menu_items['s-taxes'] = array(
				'parent' => $settings,
				'title'  => __( 'Taxes', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=tax' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Taxes', 'wcaba' ) )
			);
			$menu_items['s-payment-gateways'] = array(
				'parent' => $settings,
				'title'  => __( 'Paymet Gateways', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=payment_gateways' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Paymet Gateways', 'wcaba' ) )
			);
			$menu_items['s-emails'] = array(
				'parent' => $settings,
				'title'  => __( 'Emails', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce&tab=email' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Emails', 'wcaba' ) )
			);

			/**
			 * Check for "Integration" settings link - since WooCommerce v1.4+
			 *
			 * Checks for non-existing function "woocommerce_init" (only prior WooCommerce v1.4)
			 *
			 * @since 1.6
			 */
			if ( ! function_exists ( 'woocommerce_init' ) ) {
				$menu_items['settingsintegration'] = array(
					'parent' => $settings,
					'title'  => __( 'Integration (Other Services)', 'wcaba' ),
					'href'   => admin_url( 'admin.php?page=woocommerce&tab=integration' ),
					'meta'   => array( 'target' => '', 'title' => _x( 'Integration (Other Services)', 'Translators: For the tooltip', 'wcaba' ) )
				);
				$menu_items['settingsintegration-googleanalytics'] = array(
					'parent' => $settingsintegration,
					'title'  => __( 'Google Analytics', 'wcaba' ),
					'href'   => admin_url( 'admin.php?page=woocommerce&tab=integration&section=google_analytics' ),
					'meta'   => array( 'target' => '', 'title' => __( 'Google Analytics', 'wcaba' ) )
				);
				$menu_items['settingsintegration-sharethis'] = array(
					'parent' => $settingsintegration,
					'title'  => __( 'ShareThis', 'wcaba' ),
					'href'   => admin_url( 'admin.php?page=woocommerce&tab=integration&section=sharethis' ),
					'meta'   => array( 'target' => '', 'title' => __( 'ShareThis', 'wcaba' ) )
				);
				$menu_items['settingsintegration-shareyourcart'] = array(
					'parent' => $settingsintegration,
					'title'  => __( 'ShareYourCart', 'wcaba' ),
					'href'   => admin_url( 'admin.php?page=woocommerce&tab=integration&section=shareyourcart' ),
					'meta'   => array( 'target' => '', 'title' => __( 'ShareYourCart', 'wcaba' ) )
				);
			}  // end-if WC 1.4+ check

			/** Setting links continued once again... */
			if ( current_user_can( 'edit_theme_options' ) ) {
				$menu_items['s-widgets'] = array(
					'parent' => $settings,
					'title'  => esc_attr__( $wcaba_woocommerce_name ) . ' ' . __( 'Widgets', 'wcaba' ),
					'href'   => admin_url( 'widgets.php' ),
					'meta'   => array( 'target' => '', 'title' => esc_attr__( $wcaba_woocommerce_name_tooltip ) . ' ' . __( 'Widgets', 'wcaba' ) )
				);
				$menu_items['s-menus'] = array(
					'parent' => $settings,
					'title'  => esc_attr__( $wcaba_woocommerce_name ) . ' ' . __( 'Menus', 'wcaba' ),
					'href'   => admin_url( 'nav-menus.php' ),
					'meta'   => array( 'target' => '', 'title' => esc_attr__( $wcaba_woocommerce_name_tooltip ) . ' ' . __( 'Menus', 'wcaba' ) )
				);
			}  // end-if cap check

		}  // end-if WC cap check

		/** Display "Products" section only for users with the capability 'manage_woocommerce_products' */
		if ( current_user_can( 'manage_woocommerce_products' ) ) {
			$menu_items['products'] = array(
				'parent' => $woocommercebar,
				'title'  => __( 'Products', 'wcaba' ),
				'href'   => admin_url( 'edit.php?post_type=product' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Products', 'wcaba' ) )
			);
			$menu_items['p-add-product'] = array(
				'parent' => $products,
				'title'  => __( 'Add new product', 'wcaba' ),
				'href'   => admin_url( 'post-new.php?post_type=product' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Add new product', 'wcaba' ) )
			);
			$menu_items['p-product-categories'] = array(
				'parent' => $products,
				'title'  => __( 'Product Categories', 'wcaba' ),
				'href'   => admin_url( 'edit-tags.php?taxonomy=product_cat&post_type=product' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Product Categories', 'wcaba' ) )
			);
			$menu_items['p-product-tags'] = array(
				'parent' => $products,
				'title'  => __( 'Product Tags', 'wcaba' ),
				'href'   => admin_url( 'edit-tags.php?taxonomy=product_tag&post_type=product' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Product Tags', 'wcaba' ) )
			);
			$menu_items['p-product-shippingclasses'] = array(
				'parent' => $products,
				'title'  => __( 'Products - Shipping Classes', 'wcaba' ),
				'href'   => admin_url( 'edit-tags.php?taxonomy=product_shipping_class&post_type=product' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Products - Shipping Classes', 'wcaba' ) )
			);
			$menu_items['p-product-variants'] = array(
				'parent' => $products,
				'title'  => __( 'Product Variants (Attributes)', 'wcaba' ),
				'href'   => admin_url( 'edit.php?post_type=product&page=woocommerce_attributes' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Product Variants (Attributes)', 'wcaba' ) )
			);
		}  // end-if products cap check

		/** Display "Orders" section only for users with the capability 'manage_woocommerce_orders' */
		if ( current_user_can( 'manage_woocommerce_orders' ) ) {
			$menu_items['orders'] = array(
				'parent' => $woocommercebar,
				'title'  => __( 'Orders', 'wcaba' ),
				'href'   => admin_url( 'edit.php?post_type=shop_order' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Orders', 'wcaba' ) )
			);

			/** Display order status links if theme support is added */
			if ( current_theme_supports( 'wcaba-order-status' ) ) {
				require_once( WCABA_PLUGIN_DIR . '/includes/wcaba-orderstatus.php' );
			}  // end-if theme support check

			$menu_items['o-add-order'] = array(
				'parent' => $orders,
				'title'  => __( 'Add new order', 'wcaba' ),
				'href'   => admin_url( 'post-new.php?post_type=shop_order' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Add new order', 'wcaba' ) )
			);
			$menu_items['o-customers'] = array(
				'parent' => $orders,
				'title'  => __( 'Current Customers', 'wcaba' ),
				'href'   => admin_url( 'users.php?role=customer' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Current Customers', 'wcaba' ) )
			);
		}  // end-if orders cap check

		/** Display "Coupons" section only for users with the capability 'manage_woocommerce_coupons' */
		if ( current_user_can( 'manage_woocommerce_coupons' ) ) {
			$menu_items['coupons'] = array(
				'parent' => $woocommercebar,
				'title'  => __( 'Coupons', 'wcaba' ),
				'href'   => admin_url( 'edit.php?post_type=shop_coupon' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Coupons', 'wcaba' ) )
			);
			$menu_items['c-add-coupon'] = array(
				'parent' => $coupons,
				'title'  => __( 'Add new coupon', 'wcaba' ),
				'href'   => admin_url( 'post-new.php?post_type=shop_coupon' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Add new coupon', 'wcaba' ) )
			);
		}  // end-if coupons cap check

		/** Display "Reports" section only for users with the capability 'view_woocommerce_reports' */
		if ( WCABA_REPORTS_DISPLAY && current_user_can( 'view_woocommerce_reports' ) ) {
			$menu_items['reports'] = array(
				'parent' => $woocommercebar,
				'title'  => __( 'Reports', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce_reports' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Reports', 'wcaba' ) )
			);

			/** Displaying sub-level reports tabs links */
			$menu_items['reportssales'] = array(
				'parent' => $reports,
				'title'  => __( 'Sales Overview', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=sales' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Sales Overview', 'wcaba' ) )
			);
			$menu_items['reportssales-byday'] = array(
				'parent' => $reportssales,
				'title'  => __( 'Sales by Day', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=sales&chart=1' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Sales by Day', 'wcaba' ) )
			);
			$menu_items['reportssales-bymonth'] = array(
				'parent' => $reportssales,
				'title'  => __( 'Sales by Month', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=sales&chart=2' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Sales by Month', 'wcaba' ) )
			);
			$menu_items['reportssales-products'] = array(
				'parent' => $reportssales,
				'title'  => __( 'Product Sales', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=sales&chart=3' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Product Sales', 'wcaba' ) )
			);
			$menu_items['reportssales-topsellers'] = array(
				'parent' => $reportssales,
				'title'  => __( 'Top Sellers', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=sales&chart=4' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Top Sellers', 'wcaba' ) )
			);
			$menu_items['reportssales-topearners'] = array(
				'parent' => $reportssales,
				'title'  => __( 'Top Earners', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=sales&chart=5' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Top Earners', 'wcaba' ) )
			);
			$menu_items['reports-customers'] = array(
				'parent' => $reports,
				'title'  => __( 'Customers', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=customers' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Customers', 'wcaba' ) )
			);
			$menu_items['reports-stock'] = array(
				'parent' => $reports,
				'title'  => __( 'Stock', 'wcaba' ),
				'href'   => admin_url( 'admin.php?page=woocommerce_reports&tab=stock' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Stock', 'wcaba' ) )
			);
		}  // end-if reports cap check

		/** Display shop links if theme support is added */
		if ( current_theme_supports( 'wcaba-shop-links' ) && current_user_can( 'edit_pages' ) ) {
			require_once( WCABA_PLUGIN_DIR . '/includes/wcaba-shoplinks.php' );
		}  // end-if theme support check

		/**
		 * Display last main item in the menu for active extensions/plugins
		 * ATTENTION: This is where plugins/extensions hook in on the sub-level hierarchy
		 *
		 * @since 1.0
		 * @version 1.2
		 */
		if ( WCABA_EXTENSIONS_DISPLAY && current_user_can( 'activate_plugins' ) ) {
			$menu_items['extensions'] = array(
				'parent' => $woocommercebar,
				'title'  => __( 'Active Extensions', 'wcaba' ),
				'href'   => is_network_admin() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ),
				'meta'   => array( 'target' => '', 'title' => __( 'Active Extensions', 'wcaba' ) )
			);

			/**
			 * Action Hook 'wcaba_custom_extension_items'
			 * allows for hooking other extension-related items in
			 *
			 * @since 2.3
			 */
			do_action( 'wcaba_custom_extension_items' );

		}  // end-if constant & cap check for displaying extensions

	}  // end-if WooCommerce conditional for settings items


	/**
	 * Display links to active WooCommerce specific themes settings' pages
	 *
	 * @since 1.0
	 * @version 1.2
	 */
		/** Include plugin file with theme support links */
		if ( WCABA_THEMES_DISPLAY ) {

			require_once( WCABA_PLUGIN_DIR . '/includes/wcaba-themes.php' );

			/**
			 * Action Hook 'wcaba_custom_theme_items'
			 * allows for hooking other theme-related items in
			 *
			 * @since 2.3
			 */
			do_action( 'wcaba_custom_theme_items' );

		}  // end-if constant check


	/**
	 * Display links to active WooCommerce plugins/extensions settings' pages
	 *
	 * @since 1.0
	 * @version 1.1
	 */
		/** Include plugin file with plugin support links */
		require_once( WCABA_PLUGIN_DIR . '/includes/wcaba-plugins.php' );


	/** Allow menu items to be filtered, but pass in parent menu item IDs */
	$menu_items = (array) apply_filters( 'ddw_woocommerce_aba_menu_items', $menu_items, $prefix, $woocommercebar, 
						$support, $woocommercesites, $settings, $sgeneralsettings, $settingsintegration, 
							$s_dynpricing, $s_commissionking, 
						$products, $p_compareproductslite, $p_compareproductspro, $p_csvimportsuite, $coupons, 
							$orders, 
						$reports, $reportssales, $shoplink, $shoplinkcheckout, $shoplinkmyaccount, $shoplinkde, 
						$extensions, $extpideal, $extwtdynpricing, $extwtcommissionking, 
						$extwtclickatellsms, $extwtquickbooks, $extwccplite, $extwccppro, $extwtcsvimportsuite, 
						$wcgroup
	);  // end of array


	/**
	 * Add the WooCommerce top-level menu item
	 *
	 * @since 1.0
	 * @version 1.1
	 *
	 * @param $wcaba_main_item_title
	 * @param $wcaba_main_item_title_tooltip
	 * @param $wcaba_main_item_icon_display
	 */
		/** Filter the main item name */
		$wcaba_main_item_title = apply_filters( 'wcaba_filter_main_item', _x( 'WooCommerce', 'Translators: Main item', 'wcaba' ) );

		/** Filter the main item name's tooltip */
		$wcaba_main_item_title_tooltip = apply_filters( 'wcaba_filter_main_item_tooltip', _x( 'WooCommerce Shop Plugin', 'Translators: Main item - for the tooltip', 'wcaba' ) );

		/** Filter the main item icon's class/display */
		$wcaba_main_item_icon_display = apply_filters( 'wcaba_filter_main_item_icon_display', 'icon-woocommerce' );

		/** Add the top-level menu item */
		$wp_admin_bar->add_menu( array(
			'id'    => $woocommercebar,
			'title' => $wcaba_main_item_title,
			'href'  => admin_url( 'admin.php?page=woocommerce' ),
			'meta'  => array( 'class' => $wcaba_main_item_icon_display, 'title' => $wcaba_main_item_title_tooltip )
		) );


	/** Loop through the menu items */
	foreach ( $menu_items as $id => $menu_item ) {
		
		/** Add in the item ID */
		$menu_item['id'] = $prefix . $id;

		/** Add meta target to each item where it's not already set, so links open in new window/tab */
		if ( ! isset( $menu_item['meta']['target'] ) )		
			$menu_item['meta']['target'] = '_blank';

		/** Add class to links that open up in a new window/tab */
		if ( '_blank' === $menu_item['meta']['target'] ) {
			if ( ! isset( $menu_item['meta']['class'] ) )
				$menu_item['meta']['class'] = '';
			$menu_item['meta']['class'] .= $prefix . 'wcaba-new-tab';
		}

		/** Add menu items */
		$wp_admin_bar->add_menu( $menu_item );

	}  // end foreach


	/**
	 * Action Hook 'wcaba_custom_main_items'
	 * allows for hooking other main items in
	 *
	 * @since 2.3
	 */
	do_action( 'wcaba_custom_main_items' );


	/**
	 * Check for WordPress version to add resource links group
	 * Check against WP 3.3+ only function "wp_editor" - if true display group styling
	 * otherwise display links in WP 3.1/3.2 style
	 *
	 * @since 2.1
	 */
	if ( function_exists( 'wp_editor' ) ) {
		$wp_admin_bar->add_group( array(
			'parent' => $woocommercebar,
			'id'     => $wcgroup,
			'meta'   => array( 'class' => 'ab-sub-secondary' )
		) );
	} else {
		$wp_admin_bar->add_menu( array(
			'parent' => $woocommercebar,
			'id'     => $wcgroup
		) );
	}  // end-if wp version check


	/**
	 * Action Hook 'wcaba_custom_group_items'
	 * allows for hooking other WooCommerce Group items in
	 *
	 * @since 2.3
	 */
	do_action( 'wcaba_custom_group_items' );

}  // end of main function


add_action( 'wp_head', 'ddw_woocommerce_aba_admin_style' );
add_action( 'admin_head', 'ddw_woocommerce_aba_admin_style' );
/**
 * Add the styles for new WordPress Toolbar / Admin Bar entry
 * 
 * @since 1.0
 * @version 1.3
 *
 * @param $wcaba_main_icon
 */
function ddw_woocommerce_aba_admin_style() {

	/** No styles if admin bar is disabled or user is not logged in or items are disabled via constant */
	if ( !is_admin_bar_showing() || !is_user_logged_in() || ! WCABA_DISPLAY )
		return;

	/**
	 * Add CSS styles to wp_head/admin_head
	 * Check against WP 3.3+ only function "wp_editor"
	 */
	/** Styles for WordPress 3.3 or higher */
	$wcaba_main_icon = apply_filters( 'wcaba_filter_main_icon', plugins_url( 'woocommerce-admin-bar-addition/images/wcaba-icon.png', dirname( __FILE__ ) ) );

	if ( function_exists( 'wp_editor' ) ) {

		?>
		<style type="text/css">
			#wpadminbar.nojs .ab-top-menu > li.menupop.icon-woocommerce:hover > .ab-item,
			#wpadminbar .ab-top-menu > li.menupop.icon-woocommerce.hover > .ab-item,
			#wpadminbar.nojs .ab-top-menu > li.menupop.icon-woocommerce > .ab-item,
			#wpadminbar .ab-top-menu > li.menupop.icon-woocommerce > .ab-item {
	      			background-image: url(<?php echo $wcaba_main_icon; ?>);
				background-repeat: no-repeat;
				background-position: 0.85em 50%;
				padding-left: 30px;
			}
			#wp-admin-bar-ddw-woocommerce-extensions {
	    			border-top: 1px solid;
				padding-bottom: 3px !important;
				padding-top: 3px !important;
			}
			#wp-admin-bar-ddw-woocommerce-languages-de > .ab-item:before,
			#wp-admin-bar-ddw-woocommerce-wclanguages-de > .ab-item:before,
			#wp-admin-bar-ddw-woocommerce-wclanguages-nl > .ab-item:before {
				color: #ff9900;
				content: '• ';
			}
			#wpadminbar abbr,
			#wpadminbar .wcaba-search-input,
			#wpadminbar .wcaba-search-go {
				color: #666;
				text-shadow: none;
			}
			#wpadminbar a > abbr,
			#wpadminbar .wcaba-search-input,
			#wpadminbar .wcaba-search-go {
				color: #21759b;
			}
			#wpadminbar .wcaba-search-input,
			#wpadminbar .wcaba-search-go {
				background-color: #fff;
				height: 18px;
				line-height: 18px;
			}
			#wpadminbar .wcaba-search-go {
				-webkit-border-radius: 11px;
				   -moz-border-radius: 11px;
				        border-radius: 11px;
				font-size: 0.67em;
				margin: 0 0 0 2px;
				padding: 1px 4px;
			}
		</style>
		<?php

	/** Styles for WordPress prior 3.3 */
	} else {

		?>
		<style type="text/css">
			#wpadminbar .icon-woocommerce > a {
				background: url(<?php echo $wcaba_main_icon; ?>) no-repeat 0.85em 50% transparent;
				padding-left: 30px;
			}
			#wp-admin-bar-ddw-woocommerce-settings,
			#wp-admin-bar-ddw-woocommerce-extensions {
	    			border-top: 1px solid;
			}
			#wp-admin-bar-ddw-woocommerce-languages-de,
			#wp-admin-bar-ddw-woocommerce-wclanguages-de,
			#wp-admin-bar-ddw-woocommerce-wclanguages-nl {
				background-color: #ffffcc !important;
			}
			#wp-admin-bar-ddw-woocommerce-languages-de:hover,
			#wp-admin-bar-ddw-woocommerce-wclanguages-de:hover,
			#wp-admin-bar-ddw-woocommerce-wclanguages-nl:hover {
				background-color: #d9e3ed !important;
			}
			#wpadminbar abbr,
			#wpadminbar .wcaba-search-input,
			#wpadminbar .wcaba-search-go {
				color: #666;
				text-shadow: none;
			}
			#wpadminbar a > abbr,
			#wpadminbar .wcaba-search-input,
			#wpadminbar .wcaba-search-go {
				color: #21759b;
			}
			#wpadminbar .wcaba-search-input,
			#wpadminbar .wcaba-search-go {
				background-color: #fff;
				height: 18px;
				line-height: 18px;
				padding: 1px 4px;
			}
			#wpadminbar .wcaba-search-go {
				-webkit-border-radius: 11px;
				   -moz-border-radius: 11px;
				        border-radius: 11px;
				font-size: 0.67em;
				margin: 0 0 0 2px;
			}
		</style>
		<?php

	}  // end if else WP check

}  // end of function ddw_woocommerce_aba_admin_style


/**
 * Helper functions for custom branding of the plugin
 * Plus: Integrated support for "WooCommerce Branding" (extension) styles/icons/names
 *
 * @since 2.2
 */
	/** Include plugin file with special "WC Branding" extension stuff/integration */
	require_once( WCABA_PLUGIN_DIR . '/includes/wcaba-wcbranding.php' );


add_action( 'wp_before_admin_bar_render', 'ddw_woocommerce_aba_admin_tweaks' );
/**
 * In backend, remove "View Order" and "View Coupon" links from toolbar/admin bar
 * Only for the post types "shop_order" and "shop_coupon" as there's no frontend view for both!
 *
 * @since 2.2
 */
function ddw_woocommerce_aba_admin_tweaks() {

	global $wp_admin_bar;

    	if ( is_admin() && ( 'shop_order' == get_post_type() || 'shop_coupon' == get_post_type() ) ) {
		$wp_admin_bar->remove_menu( 'view' );
	}

}  // end of function ddw_woocommerce_aba_admin_tweaks
