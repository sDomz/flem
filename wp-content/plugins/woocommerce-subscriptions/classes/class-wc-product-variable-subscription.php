<?php
/**
 * Variable Subscription Product Class
 *
 * This class extends the WC Variable product class to create variable products with recurring payments.
 *
 * @class 		WC_Product_Variable_Subscription
 * @package		WooCommerce Subscriptions
 * @category	Class
 * @since		1.3
 * 
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( class_exists( 'WC_Product_Simple' ) ) : // WC 1.x compatibility

class WC_Product_Variable_Subscription extends WC_Product_Variable {

	var $subscription_price;

	var $subscription_period;

	var $subscription_period_interval;

	var $product_type;

	/**
	 * Create a simple subscription product object.
	 *
	 * @access public
	 * @param mixed $product
	 */
	public function __construct( $product ) {

		parent::__construct( $product );

		$this->product_type = 'variable-subscription';

		// Load all meta fields
		$this->product_custom_fields = get_post_meta( $this->id );

		// Convert selected subscription meta fields for easy access
		if ( ! empty( $this->product_custom_fields['_subscription_price'][0] ) )
			$this->subscription_price = $this->product_custom_fields['_subscription_price'][0];

		if ( ! empty( $this->product_custom_fields['_subscription_period'][0] ) )
			$this->subscription_period = $this->product_custom_fields['_subscription_period'][0];

		// Make sure we have 0 values for subscription fields we ignore
		$this->product_custom_fields['_subscription_length'][0] = 0;
		$this->product_custom_fields['_subscription_trial_length'][0] = 0;
		$this->product_custom_fields['_subscription_sign_up_fee'][0] = 0;

		$this->limit_subscriptions = ( ! isset( $this->product_custom_fields['_subscription_limit'][0] ) ) ? 'no' : $this->product_custom_fields['_subscription_limit'][0];

		add_filter( 'woocommerce_add_to_cart_handler', array( &$this, 'add_to_cart_handler' ), 10, 2 );
	}


	/**
	 * Sync variable product prices with the childs lowest/highest prices.
	 *
	 * @access public
	 * @return void
	 */
	public function variable_product_sync() {
		global $woocommerce;

		parent::variable_product_sync();

		$children = get_posts( array(
			'post_parent' 	=> $this->id,
			'posts_per_page'=> -1,
			'post_type' 	=> 'product_variation',
			'fields' 		=> 'ids',
			'post_status'	=> 'publish'
		));

		if ( $children ) {
			foreach ( $children as $child ) {

				$child_price          = get_post_meta( $child, '_price', true );
				$child_billing_period = get_post_meta( $child, '_subscription_period', true );

				// We only care about the lowest price
				if ( $child_price !== $this->min_variation_price )
					continue;

				// Set to the longest possible billing period
				$this->subscription_period = WC_Subscriptions::get_longest_period( $this->subscription_period, $child_billing_period );

				// Set the longest billing interval
				if ( $this->subscription_period === $child_billing_period ) {
					$child_billing_interval = get_post_meta( $child, '_subscription_period_interval', true );

					if ( $child_billing_interval > $this->subscription_period_interval )
						$this->subscription_period_interval = $child_billing_interval;

				}

			}

			$woocommerce->clear_product_transients( $this->id );
		}

	}

	/**
	 * Returns the price in html format.
	 *
	 * @access public
	 * @param string $price (default: '')
	 * @return string
	 */
	public function get_price_html( $price = '' ) {

		$price = parent::get_price_html( $price );

		if ( ! isset( $this->subscription_period ) || ! isset( $this->subscription_period_interval ) )
			$this->variable_product_sync();

		$price = WC_Subscriptions_Product::get_price_html( $price, $this );

		return apply_filters( 'woocommerce_variable_subscription_price_html', $price, $this );
	}

	/**
	 * get_child function.
	 *
	 * @access public
	 * @param mixed $child_id
	 * @return object WC_Product_Subscription or WC_Product_Subscription_Variation
	 */
	public function get_child( $child_id ) {
		return get_product( $child_id, array(
			'product_type' => 'Subscription_Variation',
			'parent_id'    => $this->id,
			'parent'       => $this,
			) );
	}

	/**
	 *
	 * @param $product_type string A string representation of a product type
	 * @return $product object Any WC_Product_* object
	 */
	public function add_to_cart_handler( $handler, $product ) {

		if ( 'variable-subscription' === $handler )
			$handler = 'variable';

		return $handler;
	}

	/**
	 * Checks if the store manager has requested the current product be limited to one purchase
	 * per customer, and if so, checks whether the customer already has an active subscription to
	 * the product.
	 *
	 * @access public
	 * @return bool
	 */
	function is_purchasable() {

		$purchasable = parent::is_purchasable();

		if ( true === $purchasable && 'yes' == $this->limit_subscriptions )
			if ( WC_Subscriptions_Manager::user_has_subscription( 0, $this->id, 'active' ) )
				$purchasable = false;

		return apply_filters( 'woocommerce_subscription_is_purchasable', $purchasable, $this );
	}
}

endif;