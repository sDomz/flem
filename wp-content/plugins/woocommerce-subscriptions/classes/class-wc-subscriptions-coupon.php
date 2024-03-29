<?php
/**
 * Subscriptions Coupon Class
 *
 * Mirrors a few functions in the WC_Cart class to handle subscription-specific discounts
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Coupon
 * @category	Class
 * @author		Max Rice
 * @since		1.2
 */
class WC_Subscriptions_Coupon {

	/** @var string error message for invalid subscription coupons */
	public static $coupon_error;

	/**
	 * Stores the coupons not applied to a given calculation (so they can be applied later)
	 * 
	 * @since 1.3.5
	 */
	private static $removed_coupons = array();

	/**
	 * Set up the class, including it's hooks & filters, when the file is loaded.
	 *
	 * @since 1.2
	 **/
	public static function init() {

		// Add custom coupon types
		add_filter( 'woocommerce_coupon_discount_types', __CLASS__ . '::add_discount_types' );

		// Handle before tax discounts
		add_filter( 'woocommerce_get_discounted_price', __CLASS__ . '::apply_subscription_discount_before_tax', 10, 3 );

		// Handle after tax discounts
		add_action( 'woocommerce_product_discount_after_tax_sign_up_fee', __CLASS__ . '::apply_subscription_discount_after_tax' );
		add_action( 'woocommerce_product_discount_after_tax_recurring_fee', __CLASS__ . '::apply_subscription_discount_after_tax' );

		// Validate subscription coupons
		add_filter( 'woocommerce_coupon_is_valid', __CLASS__ . '::validate_subscription_coupon', 10, 2 );

		// Remove coupons which don't apply to certain cart calculations
		add_action( 'woocommerce_before_calculate_totals', __CLASS__ . '::remove_coupons', 10 );
		add_action( 'woocommerce_calculate_totals', __CLASS__ . '::restore_coupons', 10 );
	}

	/**
	 * Add discount types
	 *
	 * @since 1.2
	 */
	public static function add_discount_types( $discount_types ) {

		return array_merge( 
			$discount_types, 
			array( 
				'sign_up_fee'   => __( 'Sign Up Fee Discount', WC_Subscriptions::$text_domain ),
				'recurring_fee' => __( 'Recurring Fee Discount', WC_Subscriptions::$text_domain )
			) 
		);
	}

	/**
	 * Apply sign up fee or recurring fee discount before tax is calculated
	 *
	 * @since 1.2
	 */
	public static function apply_subscription_discount_before_tax( $original_price, $product, $cart ) {
		global $woocommerce;

		if( ! WC_Subscriptions_Product::is_subscription( $product['product_id'] ) )
			return $original_price;

		$price = $original_price;

		$calculation_type = WC_Subscriptions_Cart::get_calculation_type();

		if ( ! empty( $cart->applied_coupons ) ) {
				foreach ( $cart->applied_coupons as $code ) {
					$coupon = new WC_Coupon( $code );

					if ( $coupon->apply_before_tax() && $coupon->is_valid() ) {

						// Apply sign-up fee discounts to sign-up total calculations
						$apply_sign_up_coupon   = ( 'sign_up_fee_total' == $calculation_type && 'sign_up_fee' == $coupon->type ) ? true : false;

						// Apply recurring fee discounts to recurring total calculations
						$apply_recurring_coupon = ( 'recurring_total' == $calculation_type && 'recurring_fee' == $coupon->type ) ? true : false;

						$apply_initial_coupon = false;

						if ( in_array( $calculation_type, array( 'initial_total', 'none' ) ) ) {
							if ( 'recurring_fee' == $coupon->type && ! WC_Subscriptions_Cart::cart_contains_free_trial() )
								$apply_initial_coupon = true;
							elseif ( 'sign_up_fee' == $coupon->type && WC_Subscriptions_Cart::get_cart_subscription_sign_up_fee() > 0 )
								$apply_initial_coupon = true;
						}

						if ( $apply_sign_up_coupon || $apply_recurring_coupon || $apply_initial_coupon ) {

							$discount_amount = ( $original_price < $coupon->amount ) ? $original_price : $coupon->amount;

							$price = $original_price - $coupon->amount;

							if ( $price < 0 )
								$price = 0;

							// add to discount totals
							$woocommerce->cart->discount_cart = $woocommerce->cart->discount_cart + ( $discount_amount * $product['quantity'] );
						}
					}
				}
		}

		return $price;
	}

	/**
	 * Apply sign up fee or recurring fee discount after tax is calculated
	 *
	 * Unable to handle percentage discounts without having correct price to calculate discount
	 * Unable to check if the after-tax price is less than the coupon amount without having after tax price available
	 * Hook added in WC 1.7 fixes these issues
	 *
	 * @since 1.2
	 */
	public static function apply_subscription_discount_after_tax( $coupon ) {
		global $woocommerce;

		$calculation_type = WC_Subscriptions_Cart::get_calculation_type();

		if ( sizeof( $woocommerce->cart->cart_contents ) > 0 ) {

			foreach ( $woocommerce->cart->cart_contents as $cart_item_key => $values ) {

				if ( ! $coupon->apply_before_tax() && $coupon->is_valid() && self::is_subscription_discountable( $values, $coupon ) ) {

					$apply_sign_up_coupon   = ( 'sign_up_fee_total' == $calculation_type && 'sign_up_fee' == $coupon->type ) ? true : false;
					$apply_recurring_coupon = ( 'recurring_total' == $calculation_type && 'recurring_fee' == $coupon->type ) ? true : false;
					$apply_initial_coupon   = false;

					if ( in_array( $calculation_type, array( 'initial_total', 'none' ) ) ) {
						if ( 'recurring_fee' == $coupon->type && ! WC_Subscriptions_Cart::cart_contains_free_trial() )
							$apply_initial_coupon = true;
						elseif ( 'sign_up_fee' == $coupon->type && WC_Subscriptions_Cart::get_cart_subscription_sign_up_fee() > 0 )
							$apply_initial_coupon = true;
					}

					if ( $apply_sign_up_coupon || $apply_recurring_coupon || $apply_initial_coupon )
						$woocommerce->cart->discount_total = $woocommerce->cart->discount_total + ( $coupon->amount * $values['quantity'] );
				}
			}
		}
	}

	/**
	 * Determine if the cart contains a discount code of a given coupon type.
	 *
	 * Used internally for checking if a WooCommerce discount coupon ('core') has been applied, or for if a specific
	 * subscription coupon type, like 'recurring_fee' or 'sign_up_fee', has been applied.
	 *
	 * @param $coupon_type string Any available coupon type or a special keyword referring to a class of coupons. Can be:
	 *  - 'any' to check for any type of discount
	 *  - 'core' for any core WooCommerce coupon
	 *  - 'recurring_fee' for the recurring amount subscription coupon
	 *  - 'sign_up_fee' for the sign-up fee subscription coupon
	 *
	 * @since 1.3.5
	 */
	public static function cart_contains_discount( $coupon_type = 'any' ) {
		global $woocommerce;

		$contains_discount = false;
		$core_coupons = array( 'fixed_product', 'percent_product', 'fixed_cart', 'percent' );

		if ( $woocommerce->cart->applied_coupons ) {

			foreach ( $woocommerce->cart->applied_coupons as $code ) {

				$coupon = new WC_Coupon( $code );

				if ( 'any' == $coupon_type || $coupon_type == $coupon->type || ( 'core' == $coupon_type && in_array( $coupon->type, $core_coupons ) ) ){
					$contains_discount = true;
					break;
				}

			}

		}

		return $contains_discount;
	}

	/**
	 * Check is a subscription coupon is valid before applying
	 *
	 * @since 1.2
	 */
	public static function validate_subscription_coupon( $valid, $coupon ) {

		// ignore non-subscription coupons
		if ( ! in_array( $coupon->type, array( 'recurring_fee', 'sign_up_fee' ) ) ) {

			// but make sure there is actually something for the coupon to be applied to (i.e. not a free trial)
			if ( WC_Subscriptions_Cart::cart_contains_free_trial() && 0 == WC_Subscriptions_Cart::get_cart_subscription_sign_up_fee() ) {
				self::$coupon_error = __( 'Sorry, this coupon is only valid for an initial payment and the subscription already has a free trial.', WC_Subscriptions::$text_domain );
				add_filter( 'woocommerce_coupon_error', __CLASS__ . '::add_coupon_error', 10 );
				$valid = false;
			}

			return $valid;
		}

		// prevent subscription coupons from being applied to renewal payments
		if ( WC_Subscriptions_Cart::cart_contains_subscription_renewal() ) {

			$valid = false;

			self::$coupon_error = __( 'Sorry, this coupon is only valid for new subscriptions.', WC_Subscriptions::$text_domain );
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::add_coupon_error', 10 );
		}

		// prevent subscription coupons from being applied to non-subscription products
		if ( ! WC_Subscriptions_Cart::cart_contains_subscription_renewal() && ! WC_Subscriptions_Cart::cart_contains_subscription() ) {

			$valid = false;

			self::$coupon_error = __( 'Sorry, this coupon is only valid for subscription products.', WC_Subscriptions::$text_domain );
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::add_coupon_error', 10 );
		}

		// prevent sign up fee coupons from being applied to subscriptions without a sign up fee
		if ( 0 == WC_Subscriptions_Cart::get_cart_subscription_sign_up_fee() && 'sign_up_fee' == $coupon->type ) {

			$valid = false;

			self::$coupon_error = __( 'Sorry, this coupon is only valid for subscription products with a sign-up fee.', WC_Subscriptions::$text_domain );
			add_filter( 'woocommerce_coupon_error', __CLASS__ . '::add_coupon_error', 10 );
		}

		return $valid;
	}

	/**
	 * Returns a subscription coupon-specific error if validation failed
	 *
	 * @since 1.2
	 */
	public static function add_coupon_error( $error ) {

		if( self::$coupon_error )
			return self::$coupon_error;
		else
			return $error;

	}

	/**
	 * Checks a given product / coupon combination to determine if the subscription should be discounted
	 *
	 * @since 1.2
	 */
	private static function is_subscription_discountable( $values, $coupon ) {

		$product_cats = wp_get_post_terms( $values['product_id'], 'product_cat', array("fields" => "ids") );

		$this_item_is_discounted = false;

		// Specific products get the discount
		if ( sizeof( $coupon->product_ids ) > 0 ) {

			if (in_array($values['product_id'], $coupon->product_ids) || in_array($values['variation_id'], $coupon->product_ids) || in_array($values['data']->get_parent(), $coupon->product_ids))
				$this_item_is_discounted = true;

			// Category discounts
		} elseif ( sizeof( $coupon->product_categories ) > 0 ) {

			if ( sizeof( array_intersect( $product_cats, $coupon->product_categories ) ) > 0 )
				$this_item_is_discounted = true;

		} else {

			// No product ids - all items discounted
			$this_item_is_discounted = true;

		}

		// Specific product ID's excluded from the discount
		if ( sizeof( $coupon->exclude_product_ids ) > 0 )
			if ( in_array( $values['product_id'], $coupon->exclude_product_ids ) || in_array( $values['variation_id'], $coupon->exclude_product_ids ) || in_array( $values['data']->get_parent(), $coupon->exclude_product_ids ) )
				$this_item_is_discounted = false;

		// Specific categories excluded from the discount
		if ( sizeof( $coupon->exclude_product_categories ) > 0 )
			if ( sizeof( array_intersect( $product_cats, $coupon->exclude_product_categories ) ) > 0 )
				$this_item_is_discounted = false;

		// Apply filter
		return apply_filters( 'woocommerce_item_is_discounted', $this_item_is_discounted, $values, $before_tax = false );
	}

	/**
	 * Sets which coupons should be applied for this calculation.
	 *
	 * This function is hooked to "woocommerce_before_calculate_totals" so that WC will calculate a subscription
	 * product's total based on the total of it's price per period and sign up fee (if any).
	 *
	 * @since 1.3.5
	 */
	public static function remove_coupons( $cart ) {
		global $woocommerce;

		$calculation_type = WC_Subscriptions_Cart::get_calculation_type();

		// Only hook when totals are being calculated completely (on cart & checkout pages)
		if ( 'none' == $calculation_type || ! WC_Subscriptions_Cart::cart_contains_subscription() || ( ! is_checkout() && ! is_cart() && ! defined( 'WOOCOMMERCE_CHECKOUT' ) && ! defined( 'WOOCOMMERCE_CART' ) ) )
			return;

		$applied_coupons = $cart->get_applied_coupons();

		// If we're calculating a sign-up fee or recurring fee only amount, remove irrelevant coupons
		if ( ! empty( $applied_coupons ) ) {

			// Keep track of which coupons, if any, need to be reapplied immediately
			$coupons_to_reapply = array();

			if ( in_array( $calculation_type, array( 'initial_total', 'sign_up_fee_total', 'recurring_total' ) ) ) {

				foreach ( $applied_coupons as $coupon_code ) {

					$coupon = new WC_Coupon( $coupon_code );

					if ( $coupon->type == 'recurring_fee' && 'recurring_total' == $calculation_type ) // always apply coupons to their specific calculation case
						$coupons_to_reapply[] = $coupon_code;
					elseif ( $coupon->type != 'recurring_fee' && in_array( $calculation_type, array( 'initial_total', 'sign_up_fee_total', 'none' ) ) ) // apply all coupons to the first payment
						$coupons_to_reapply[] = $coupon_code;
					else
						self::$removed_coupons[] = $coupon_code;

				}

				// Now remove all coupons (WC only provides a function to remove all coupons)
				$cart->remove_coupons();

				// And re-apply those which relate to this calculation
				$woocommerce->cart->applied_coupons = $coupons_to_reapply;
			}
		}
	}

	/**
	 * Restores discount coupons which had been removed for special subscription calculations.
	 *
	 * @since 1.3.5
	 */
	public static function restore_coupons( $cart ) {
		global $woocommerce;

		if ( ! empty ( self::$removed_coupons ) ) {

			// Can't use $cart->add_dicount here as it calls calculate_totals()
			$woocommerce->cart->applied_coupons = array_merge( $woocommerce->cart->applied_coupons, self::$removed_coupons );

			self::$removed_coupons = array();
		}
	}

	/* Deprecated */

	/**
	 * Determines if cart contains a recurring fee discount code
	 *
	 * Does not check if the code is valid, etc
	 *
	 * @since 1.2
	 */
	public static function cart_contains_recurring_discount() {

		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.3.5', __CLASS__ .'::cart_contains_discount( "recurring_fee" )' );

		return self::cart_contains_discount( 'recurring_fee' );
	}

	/**
	 * Determines if cart contains a sign up fee discount code
	 *
	 * Does not check if the code is valid, etc
	 *
	 * @since 1.2
	 */
	public static function cart_contains_sign_up_discount() {

		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.3.5', __CLASS__ .'::cart_contains_discount( "sign_up_fee" )' );

		return self::cart_contains_discount( 'sign_up_fee' );
	}
}

WC_Subscriptions_Coupon::init();
