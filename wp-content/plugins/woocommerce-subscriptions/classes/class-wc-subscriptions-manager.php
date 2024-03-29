<?php
/**
 * Subscriptions Management Class
 * 
 * An API of Subscription utility functions and Account Management functions.
 * 
 * Subscription activation and cancellation functions are hooked directly to order status changes
 * so your payment gateway only needs to work with WooCommerce APIs. You can however call other
 * management functions directly when necessary.
 * 
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Manager
 * @category	Class
 * @author		Brent Shepherd
 * @since		1.0
 */
class WC_Subscriptions_Manager {

	/**
	 * The database key for user's subscriptions. 
	 * 
	 * @since 1.0
	 */
	public static $users_meta_key = 'woocommerce_subscriptions';

	/**
	 * A variable for storing any filters that are removed by @see self::safeguard_scheduled_payments()
	 * 
	 * @since 1.1.2
	 */
	private static $removed_filter_cache = array();

	/**
	 * Set up the class, including it's hooks & filters, when the file is loaded.
	 *
	 * @since 1.0
	 **/
	public static function init() {

		// When an order's status is changed, run the appropriate subscription function
		add_action( 'woocommerce_order_status_refunded', __CLASS__ . '::cancel_subscriptions_for_order' );
		add_action( 'woocommerce_order_status_cancelled', __CLASS__ . '::cancel_subscriptions_for_order' );
		add_action( 'woocommerce_order_status_failed', __CLASS__ . '::failed_subscription_sign_ups_for_order' );
		add_action( 'woocommerce_order_status_on-hold', __CLASS__ . '::put_subscription_on_hold_for_order' );
		add_action( 'woocommerce_order_status_processing', __CLASS__ . '::activate_subscriptions_for_order' );
		add_action( 'woocommerce_order_status_completed', __CLASS__ . '::activate_subscriptions_for_order' );

		// Create a subscription entry when a new order is placed
		add_action( 'woocommerce_checkout_order_processed', __CLASS__ . '::process_subscriptions_on_checkout', 10, 2 );

		// Check if a user is requesting to cancel their subscription
		add_action( 'init', __CLASS__ . '::maybe_change_users_subscription', 100 );

		// Expire a user's subscription
		add_action( 'scheduled_subscription_expiration', __CLASS__ . '::expire_subscription', 10, 2 );

		// Expire a user's subscription
		add_action( 'scheduled_subscription_end_of_prepaid_term', __CLASS__ . '::subscription_end_of_prepaid_term', 10, 2 );

		// Set default cancelled role after the pre-paid term on a subscriber's account
		add_action( 'subscription_end_of_prepaid_term', __CLASS__ . '::make_user_cancelled', 10, 1 );

		// Subscription Trial End
		add_action( 'scheduled_subscription_trial_end', __CLASS__ . '::subscription_trial_end', 0, 2 );

		// Make sure a scheduled subscription payment is never fired repeatedly to safeguard against WP-Cron inifinite loop bugs
		add_action( 'scheduled_subscription_payment', __CLASS__ . '::safeguard_scheduled_payments', 0, 2 );

		// Automatically handle subscription payments for subscriptions with $0 recurring total
		add_action( 'scheduled_subscription_payment', __CLASS__ . '::maybe_process_subscription_payment', 11, 2 );

		// Reschedule payments after the most recent payment has been made (and should have been recorded against the subscription)
		add_action( 'scheduled_subscription_payment', __CLASS__ . '::maybe_reschedule_subscription_payment', 100, 2 );

		// Order is trashed, trash subscription
		add_action( 'wp_trash_post', __CLASS__ . '::maybe_trash_subscription', 10 );

		// Update payment date via ajax call from Manage Subscriptions page
		add_action( 'wp_ajax_wcs_update_next_payment_date', __CLASS__ . '::ajax_update_next_payment_date', 10 );

		// If a gateway doesn't manage scheduled payments, then we should suspend the subscription
		add_action( 'scheduled_subscription_payment', __CLASS__ . '::maybe_put_subscription_on_hold', 1, 2 );
	}

	/**
	 * Marks a single subscription as active on a users account.
	 *
	 * @param $user_id int The id of the user whose subscription is to be activated.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function activate_subscription( $user_id, $subscription_key ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		if ( empty( $subscription ) || $subscription['status'] == 'active' )
			return false;

		$order = new WC_Order( $subscription['order_id'] );

		$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $subscription['product_id'] );

		if ( $subscription['status'] != 'pending' && ! self::can_subscription_be_changed_to( 'active', $subscription_key, $user_id ) ) {

		 	$order->add_order_note( sprintf( __( 'Unable to activate subscription "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			do_action( 'unable_to_activate_subscription', $user_id, $subscription_key );

			$activated_subscription = false;

		} else {

			// Mark subscription as active
			$users_subscriptions = self::update_users_subscriptions( $user_id, array( $subscription_key => array( 'status' => 'active', 'end_date' => 0 ) ) );

			// Make sure subscriber is marked as a "Paying Customer"
			self::mark_paying_customer( $subscription['order_id'] );

			// Assign default subscriber role to user
			self::update_users_role( $user_id, 'default_subscriber_role' );

			// Log activation on order
			$order->add_order_note( sprintf( __( 'Activated Subscription "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			// Schedule expiration & payment hooks
			$hook_args = array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key );

			self::set_next_payment_date( $subscription_key, $user_id );

			self::set_trial_expiration_date( $subscription_key, $user_id );

			self::set_expiration_date( $subscription_key, $user_id );

			do_action( 'activated_subscription', $user_id, $subscription_key );

			$activated_subscription = true;

		}

		return $activated_subscription;
	}

	/**
	 * Changes a single subscription from on-hold to active on a users account.
	 *
	 * @param $user_id int The id of the user whose subscription is to be activated.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function reactivate_subscription( $user_id, $subscription_key ) {

		if ( false !== self::activate_subscription( $user_id, $subscription_key ) )
			do_action( 'reactivated_subscription', $user_id, $subscription_key );
	}

	/**
	 * Suspends a single subscription on a users account by placing it in the "on-hold" status. 
	 *
	 * @param $user_id int The id of the user whose subscription should be put on-hold.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function put_subscription_on_hold( $user_id, $subscription_key ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		if ( empty( $subscription ) || $subscription['status'] == 'on-hold' )
			return false;

		$order = new WC_Order( $subscription['order_id'] );

		$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $subscription['product_id'] );

		if ( ! self::can_subscription_be_changed_to( 'on-hold', $subscription_key, $user_id ) ) {

		 	$order->add_order_note( sprintf( __( 'Unable to put subscription on-hold: "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			do_action( 'unable_to_put_subscription_on-hold', $user_id, $subscription_key );
			do_action( 'unable_to_suspend_subscription', $user_id, $subscription_key );

		} else {

			// Mark subscription as on-hold
			$suspension_count = 1 + ( isset( $subscription['suspension_count'] ) ? $subscription['suspension_count'] : 0 );
			$users_subscriptions = self::update_users_subscriptions( $user_id, array(
				$subscription_key => array(
					'status'           => 'on-hold',
					'suspension_count' => $suspension_count
					)
				)
			);

			// Clear hooks
			$hook_args = array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key );
			wp_clear_scheduled_hook( 'scheduled_subscription_expiration', $hook_args );
			wp_clear_scheduled_hook( 'scheduled_subscription_payment', $hook_args );
			wp_clear_scheduled_hook( 'scheduled_subscription_trial_end', $hook_args );

			// Unset subscriber as a "Paying Customer"
			self::mark_not_paying_customer( $subscription['order_id'] );

			// Assign default inactive subscriber role to user
			self::make_user_inactive( $user_id );

			// Log cancellation on order
			$order->add_order_note( sprintf( __( 'Subscription On-hold: "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			do_action( 'subscription_put_on-hold', $user_id, $subscription_key );
			// Backward compatibility
			do_action( 'suspended_subscription', $user_id, $subscription_key );
		}
	}

	/**
	 * Cancels a single subscription on a users account.
	 *
	 * @param $user_id int The id of the user whose subscription should be cancelled.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function cancel_subscription( $user_id, $subscription_key ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		if ( empty( $subscription ) || $subscription['status'] == 'cancelled' )
			return false;

		$order = new WC_Order( $subscription['order_id'] );

		$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $subscription['product_id'] );

		if ( ! self::can_subscription_be_changed_to( 'cancelled', $subscription_key, $user_id ) ) {

		 	$order->add_order_note( sprintf( __( 'Unable to cancel subscription "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			do_action( 'unable_to_cancel_subscription', $user_id, $subscription_key );

		} else {

			$hook_args = array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key );

			// Schedule a hook to fire at the end of the currently paid up period if an active subscription is being cancelled
			if ( 'active' == $subscription['status'] ) {
				$end_of_term = self::get_next_payment_date( $subscription_key, $user_id, 'timestamp' );
				wp_schedule_single_event( $end_of_term, 'scheduled_subscription_end_of_prepaid_term', $hook_args );
			} else {
				// Assign default cancelled subscriber role to user as this won't be done on the 'subscription_end_of_prepaid_term' hook
				self::update_users_role( $user_id, 'default_cancelled_role' );
			}

			// Mark subscription as cancelled
			$users_subscriptions = self::update_users_subscriptions( $user_id, array( $subscription_key => array( 'status' => 'cancelled', 'end_date' => gmdate( 'Y-m-d H:i:s' ) ) ) );

			// Clear scheduled expiration and payment hooks
			wp_clear_scheduled_hook( 'scheduled_subscription_expiration', $hook_args );
			wp_clear_scheduled_hook( 'scheduled_subscription_payment', $hook_args );
			wp_clear_scheduled_hook( 'scheduled_subscription_trial_end', $hook_args );

			// Unset subscriber as a "Paying Customer"
			self::mark_not_paying_customer( $subscription['order_id'] );

			// Log cancellation on order
			$order->add_order_note( sprintf( __( 'Cancelled Subscription "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			do_action( 'cancelled_subscription', $user_id, $subscription_key );

		}
	}

	/**
	 * Sets a single subscription on a users account to be 'on-hold' and keeps a record of the failed sign up on an order.
	 *
	 * @param $user_id int The id of the user whose subscription should be cancelled.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function failed_subscription_signup( $user_id, $subscription_key ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		if ( empty( $subscription ) || $subscription['status'] == 'on-hold' )
			return false;

		// Place the subscription on-hold
		self::put_subscription_on_hold( $user_id, $subscription_key );

		// Log failure on order
		$order = new WC_Order( $subscription['order_id'] );

		$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $subscription['product_id'] );

		$order->add_order_note( sprintf( __( 'Failed sign-up for subscription "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

		do_action( 'subscription_sign_up_failed', $user_id, $subscription_key );

	}

	/**
	 * Trashes a single subscription on a users account.
	 *
	 * @param $user_id int The ID of the user who the subscription belongs to
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function trash_subscription( $user_id, $subscription_key ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		if ( empty( $subscription ) || $subscription['status'] == 'trash' )
			return false;

		$order = new WC_Order( $subscription['order_id'] );

		$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $subscription['product_id'] );

		if ( ! self::can_subscription_be_changed_to( 'trash', $subscription_key, $user_id ) ) {

		 	$order->add_order_note( sprintf( __( 'Unable to trash subscription "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			do_action( 'unable_to_trash_subscription', $user_id, $subscription_key );

		} else {

			// Run all cancellation related functions on the subscription
			if ( $subscription['status'] != 'cancelled' )
				self::cancel_subscription( $user_id, $subscription_key );

			// Log deletion on order
			$order->add_order_note( sprintf( __( 'Trashed Subscription "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			$users_subscriptions = self::update_users_subscriptions( $user_id, array( $subscription_key => array( 'status' => 'trash' ) ) );

			do_action( 'subscription_trashed', $user_id, $subscription_key );
		}
	}

	/**
	 * Permanently deletes a single subscription on a users account.
	 *
	 * @param $user_id int The ID of the user who the subscription belongs to
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.2
	 */
	public static function delete_subscription( $user_id, $subscription_key ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		if ( empty( $subscription ) )
			return false;

		if ( ! self::can_subscription_be_changed_to( 'deleted', $subscription_key, $user_id ) ) {

			do_action( 'unable_to_delete_subscription', $user_id, $subscription_key );

		} else {

			// Run all cancellation related functions on the subscription
			if ( ! in_array( $subscription['status'], array( 'cancelled', 'expired', 'trash' ) ) )
				self::cancel_subscription( $user_id, $subscription_key );

			$order = new WC_Order( $subscription['order_id'] );

			$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $subscription['product_id'] );

			// Log deletion on order
			$order->add_order_note( sprintf( __( 'Deleted Subscription "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			$users_subscriptions = self::update_users_subscriptions( $user_id, array( $subscription_key => array( 'status' => 'deleted' ) ) );

			do_action( 'subscription_deleted', $user_id, $subscription_key );
		}
	}

	/**
	 * Expires a single subscription on a users account.
	 *
	 * @param $user_id int The id of the user who owns the expiring subscription. 
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function expire_subscription( $user_id, $subscription_key ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		// Don't expire an already expired, cancelled or trashed subscription
		if ( empty( $subscription ) || in_array( $subscription['status'], array( 'expired', 'cancelled', 'trash' ) ) )
			return;

		$users_subscriptions = self::update_users_subscriptions( $user_id, array( $subscription_key => array( 'status' => 'expired', 'end_date' => gmdate( 'Y-m-d H:i:s' ) ) ) );

		// Unset subscriber as a "Paying Customer"
		self::mark_not_paying_customer( $subscription['order_id'] );

		// Assign default inactive subscriber role to user
		self::update_users_role( $user_id, 'default_cancelled_role' );

		// Clear any lingering expiration and payment hooks
		$hook_args = array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key );
		wp_clear_scheduled_hook( 'scheduled_subscription_expiration', $hook_args );
		wp_clear_scheduled_hook( 'scheduled_subscription_payment', $hook_args );

		// Log expiration on order
		$order = new WC_Order( $subscription['order_id'] );

		$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $subscription['product_id'] );

		$order->add_order_note( sprintf( __( 'Subscription Expired: "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

		do_action( 'subscription_expired', $user_id, $subscription_key );
	}

	/**
	 * Fires when a cancelled subscription reaches the end of its prepaid term.
	 *
	 * @param $user_id int The id of the user who owns the expiring subscription.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.3
	 */
	public static function subscription_end_of_prepaid_term( $user_id, $subscription_key ) {
		do_action( 'subscription_end_of_prepaid_term', $user_id, $subscription_key );
	}

	/**
	 * Fires when the trial period for a subscription has completed.
	 *
	 * @param $user_id int The id of the user who owns the expiring subscription. 
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function subscription_trial_end( $user_id, $subscription_key ) {
		do_action( 'subscription_trial_end', $user_id, $subscription_key );
	}

	/**
	 * Records a payment on a subscription.
	 *
	 * @param $user_id int The id of the user who owns the subscription. 
	 * @param $subscription_key string A subscription key of the form obtained by @see get_subscription_key( $order_id, $product_id )
	 * @since 1.0
	 */
	public static function process_subscription_payment( $user_id, $subscription_key ) {

		// Store a record of the subscription payment date
		$subscription = self::get_users_subscription( $user_id, $subscription_key );
		$subscription['completed_payments'][] = gmdate( 'Y-m-d H:i:s' );

		// Reset failed payment count & suspension count
		$subscription['failed_payments'] = $subscription['suspension_count'] = 0;

		self::update_users_subscriptions( $user_id, array( $subscription_key => $subscription ) );

		// Make sure subscriber is marked as a "Paying Customer"
		self::mark_paying_customer( $subscription['order_id'] );

		// Make sure subscriber has default role
		self::update_users_role( $user_id, 'default_subscriber_role' );

		// Log payment on order
		$order = new WC_Order( $subscription['order_id'] );

		$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $subscription['product_id'] );

		$order->add_order_note( sprintf( __( 'Payment received for subscription "%s"', WC_Subscriptions::$text_domain ), $item['name'] ) );

		do_action( 'processed_subscription_payment', $user_id, $subscription_key );
	}

	/**
	 * Processes a failed payment on a subscription by recording the failed payment and cancelling the subscription if it exceeds the 
	 * maximum number of failed payments allowed on the site. 
	 *
	 * @param $user_id int The id of the user who owns the expiring subscription. 
	 * @param $subscription_key string A subscription key of the form obtained by @see get_subscription_key( $order_id, $product_id )
	 * @since 1.0
	 */
	public static function process_subscription_payment_failure( $user_id, $subscription_key ) {

		// Store a record of the subscription payment date
		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		if ( ! isset( $subscription['failed_payments'] ) )
			$subscription['failed_payments'] = 0;

		$subscription['failed_payments'] = $subscription['failed_payments'] + 1;

		self::update_users_subscriptions( $user_id, array( $subscription_key => $subscription ) );

		$order = new WC_Order( $subscription['order_id'] );

		$item = WC_Subscriptions_Order::get_item_by_product_id( $order, $subscription['product_id'] );

		$max_failed_payments = get_option( WC_Subscriptions_Admin::$option_prefix . '_max_failed_payments', 3 );

		// Allow a short circuit for plugins & payment gateways to force max failed payments exceeded
		$failed_payments_exceeded = apply_filters( 'woocommerce_subscriptions_max_failed_payments_exceeded', false, $user_id, $subscription_key );

		// Have we reached the maximum failed payments allowed on the subscription
		if ( $failed_payments_exceeded || ( $subscription['failed_payments'] >= $max_failed_payments && intval( $max_failed_payments ) !== 0 ) ) {

			self::cancel_subscription( $user_id, $subscription_key );

			$order->add_order_note( sprintf( __( 'Cancelled Subscription "%s". Maximum number of failed subscription payments reached.', WC_Subscriptions::$text_domain ), $item['name'] ) );

			if ( 'yes' == get_option( WC_Subscriptions_Admin::$option_prefix . '_generate_renewal_order' ) )
				$renewal_order_id = WC_Subscriptions_Renewal_Order::generate_renewal_order( $order, $subscription['product_id'], array( 'new_order_role' => 'parent' ) );

		} else {

			// Log payment failure on order
			$order->add_order_note( sprintf( __( 'Payment failed for subscription "%s".', WC_Subscriptions::$text_domain ), $item['name'] ) );

			// Place the subscription on-hold
			self::put_subscription_on_hold( $user_id, $subscription_key );

		}

		do_action( 'processed_subscription_payment_failure', $user_id, $subscription_key );
	}

	/**
	 * This function should be called whenever a subscription payment is made on an order. This includes 
	 * when the subscriber signs up and for a recurring payment. 
	 *
	 * The function is a convenience wrapper for @see self::process_subscription_payment(), so if calling that 
	 * function directly, do not call this function also.
	 *
	 * @param $order WC_Order|int The order or ID of the order for which subscription payments should be marked against.
	 * @since 1.0
	 */
	public static function process_subscription_payments_on_order( $order, $product_id = '' ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		if ( empty( $product_id ) ) {
			$order_items      = WC_Subscriptions_Order::get_recurring_items( $order );
			$first_order_item = reset( $order_items );
			$product_id       = WC_Subscriptions_Order::get_items_product_id( $first_order_item );
		}

		if ( WC_Subscriptions_Order::order_contains_subscription( $order ) && WC_Subscriptions_Order::is_item_subscription( $order, $product_id ) ) {

			self::process_subscription_payment( $order->customer_user, self::get_subscription_key( $order->id, $product_id ) );

			do_action( 'processed_subscription_payments_for_order', $order );
		}
	}

	/**
	 * This function should be called whenever a subscription payment has failed.
	 *
	 * The function is a convenience wrapper for @see self::process_subscription_payment_failure(), so if calling that 
	 * function directly, do not call this function also.
	 *
	 * @param $order WC_Order|int The order or ID of the order for which subscription payments should be marked against.
	 * @since 1.0
	 */
	public static function process_subscription_payment_failure_on_order( $order, $product_id = '' ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		if ( empty( $product_id ) ) {
			$order_items      = WC_Subscriptions_Order::get_recurring_items( $order );
			$first_order_item = reset( $order_items );
			$product_id       = WC_Subscriptions_Order::get_items_product_id( $first_order_item );
		}

		if ( WC_Subscriptions_Order::order_contains_subscription( $order ) && WC_Subscriptions_Order::is_item_subscription( $order, $product_id ) ) {

			self::process_subscription_payment_failure( $order->customer_user, self::get_subscription_key( $order->id, $product_id ) );

			do_action( 'processed_subscription_payment_failure_for_order', $order );
		}
	}

	/**
	 * Activates all the subscription products in an order.
	 *
	 * @param $order WC_Order|int The order or ID of the order for which subscriptions should be marked as activated.
	 * @since 1.0
	 */
	public static function activate_subscriptions_for_order( $order ) {

		if ( ! WC_Subscriptions_Order::order_contains_subscription( $order ) )
			return;

		// Update subscription in User's account, calls self::activate_subscription
		self::update_users_subscriptions_for_order( $order, 'active' );

		do_action( 'subscriptions_activated_for_order', $order );
	}

	/**
	 * Suspends all the subscriptions on an order by changing their status to "on-hold".
	 *
	 * @param $order WC_Order|int The order or ID of the order for which subscriptions should be marked as activated.
	 * @since 1.0
	 */
	public static function put_subscription_on_hold_for_order( $order ) {

		if ( ! WC_Subscriptions_Order::order_contains_subscription( $order ) )
			return;

		// Update subscription in User's account, calls self::activate_subscription
		self::update_users_subscriptions_for_order( $order, 'on-hold' );

		do_action( 'subscriptions_put_on_hold_for_order', $order );
		// Backward compatibility
		do_action( 'subscriptions_suspended_for_order', $order );
	}

	/**
	 * Mark all subscriptions in an order as cancelled on the user's account.
	 *
	 * @param $order WC_Order|int The order or ID of the order for which subscriptions should be marked as cancelled.
	 * @since 1.0
	 */
	public static function cancel_subscriptions_for_order( $order ) {

		if ( ! WC_Subscriptions_Order::order_contains_subscription( $order ) )
			return;

		// Update subscription in User's account, calls self::cancel_subscription for each subscription
		self::update_users_subscriptions_for_order( $order, 'cancelled' );

		do_action( 'subscriptions_cancelled_for_order', $order );
	}

	/**
	 * Marks all the subscriptions in an order as expired 
	 *
	 * @param $order WC_Order|int The order or ID of the order for which subscriptions should be marked as expired.
	 * @since 1.0
	 */
	public static function expire_subscriptions_for_order( $order ) {

		// Update subscription in User's account, calls self::expire_subscription
		self::update_users_subscriptions_for_order( $order, 'expired' );

		do_action( 'subscriptions_expired_for_order', $order );
	}

	/**
	 * Called when a sign up fails during the payment processing step.
	 *
	 * @param $order WC_Order | int The order or ID of the order for which subscriptions should be marked as failed.
	 * @since 1.0
	 */
	public static function failed_subscription_sign_ups_for_order( $order ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		if ( ! WC_Subscriptions_Order::order_contains_subscription( $order ) )
			return;

		// Set subscription status to failed and log failure
		if ( $order->status != 'failed' )
			$order->update_status( 'failed', __( 'Subscription sign up failed.', WC_Subscriptions::$text_domain ) );

		self::mark_not_paying_customer( $order );

		// Update subscription in User's account
		self::update_users_subscriptions_for_order( $order, 'failed' );

		do_action( 'failed_subscription_sign_ups_for_order', $order );
	}

	/**
	 * Uses the details of an order to create a pending subscription on the customers account
	 * for a subscription product, as specified with $product_id.
	 *
	 * @param $order mixed int | WC_Order The order ID or WC_Order object to create the subscription from.
	 * @param $product_id int The ID of the subscription product on the order.
	 * @param $args array An array of name => value pairs to customise the details of the subscription, including start_date & expiry_date
	 * @since 1.1
	 */
	public static function create_pending_subscription_for_order( $order, $product_id, $args = array() ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		if ( ! WC_Subscriptions_Product::is_subscription( $product_id ) )
			return;

		$args = wp_parse_args( $args, array(
			'start_date'  => '',
			'expiry_date' => ''
		));

		$subscription_key = self::get_subscription_key( $order->id, $product_id );

		// In case the subscription exists already
		$subscription = self::get_users_subscription( $order->customer_user, $subscription_key );

		// Adding a new subscription so set the start date/time to now
		if ( ! empty( $args['start_date'] ) ){
			if ( is_numeric( $args['start_date'] ) )
				$args['start_date'] = date( 'Y-m-d H:i:s', $args['start_date'] );

			$start_date = $args['start_date'];
		} else {
			$start_date = ( isset( $subscription['start_date'] ) ) ? $subscription['start_date'] : gmdate( 'Y-m-d H:i:s' );
		}

		// Adding a new subscription so set the expiry date/time from the order date
		if ( ! empty( $args['expiry_date'] ) ){
			if ( is_numeric( $args['expiry_date'] ) )
				$args['expiry_date'] = date( 'Y-m-d H:i:s', $args['expiry_date'] );

			$expiration = $args['expiry_date'];
		} else {
			$expiration = ( isset( $subscription['expiry_date'] ) ) ? $subscription['expiry_date'] : WC_Subscriptions_Product::get_expiration_date( $product_id, $start_date );
		}

		// Adding a new subscription so set the expiry date/time from the order date
		$trial_expiration = ( isset( $subscription['trial_expiry_date'] ) ) ? $subscription['trial_expiry_date'] : WC_Subscriptions_Product::get_trial_expiration_date( $product_id, $start_date );

		$failed_payments = ( isset( $subscription['failed_payments'] ) ) ? $subscription['failed_payments'] : 0;
		$completed_payments = ( isset( $subscription['completed_payments'] ) ) ? $subscription['completed_payments'] : array();

		$subscriptions[$subscription_key] = array(
			'product_id'         => $product_id,
			'order_key'          => $order->order_key,
			'order_id'           => $order->id,
			'start_date'         => $start_date,
			'expiry_date'        => $expiration,
			'end_date'           => 0,
			'status'             => 'pending',
			'trial_expiry_date'  => $trial_expiration,
			'failed_payments'    => $failed_payments,
			'completed_payments' => $completed_payments
		);

		self::update_users_subscriptions( $order->customer_user, $subscriptions );

		$product = WC_Subscriptions::get_product( $product_id );

		// Set subscription status to active and log activation
		$order->add_order_note( sprintf( __( 'Pending subscription created for "%s".', WC_Subscriptions::$text_domain ), $product->get_title() ) );

		do_action( 'pending_subscription_created_for_order', $order, $product_id );

	}

	/**
	 * Creates subscriptions against a users account with a status of pending when a user creates
	 * an order containing subscriptions.
	 *
	 * @param $order mixed int | WC_Order The order ID or WC_Order object to create the subscription from.
	 * @since 1.0
	 */
	public static function process_subscriptions_on_checkout( $order ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		// Clear any subscriptions on this order to prevent duplicate "Pending" subscriptions on an order
		self::clear_users_subscriptions_from_order( $order );

		if ( WC_Subscriptions_Order::order_contains_subscription( $order ) ) {
			// Update subscription in User's account
			self::update_users_subscriptions_for_order( $order, 'pending' );

			do_action( 'subscriptions_created_for_order', $order );
		}
	}

	/**
	 * Updates a user's subscriptions for each subscription product in the order.
	 *
	 * @param $order WC_Order The order to get subscriptions and user details from.
	 * @param $status String (optional) A status to change the subscriptions in an order to. Default is 'active'.
	 * @since 1.0
	 */
	public static function update_users_subscriptions_for_order( $order, $status = 'pending' ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		foreach( $order->get_items() as $order_item ) {

			if ( ! WC_Subscriptions_Order::is_item_subscription( $order, $order_item ) )
				continue;

			$subscription_key = self::get_subscription_key( $order->id, WC_Subscriptions_Order::get_items_product_id( $order_item ) );

			$subscription = self::get_users_subscription( $order->customer_user, $subscription_key );

			switch ( $status ) {
				case 'active' :
					self::activate_subscription( $order->user_id, $subscription_key );
					break;
				case 'cancelled' :
					self::cancel_subscription( $order->user_id, $subscription_key );
					break;
				case 'expired' :
					self::expire_subscription( $order->user_id, $subscription_key );
					break;
				case 'on-hold' :
				case 'suspend' : // Backward compatibility
					self::put_subscription_on_hold( $order->user_id, $subscription_key );
					break;
				case 'failed' :
					self::failed_subscription_signup( $order->user_id, $subscription_key );
					break;
				case 'pending' :
				default :
					self::create_pending_subscription_for_order( $order, WC_Subscriptions_Order::get_items_product_id( $order_item ) );
					break;
			}
		}

		do_action( 'updated_users_subscriptions_for_order', $order, $status );
	}

	/**
	 * Takes a user ID and array of subscription details and updates the users subscription details accordingly. 
	 * 
	 * @uses wp_parse_args To allow only part of a subscription's details to be updated, like status.
	 * @param $user_id int The ID of the user for whom subscription details should be updated
	 * @param $new_subscription_details array An array of arrays with a subscription key and corresponding 'detail' => 'value' pair. Should take the form:
	 *        'product_id'          The Product/Post ID of the subscription
	 *        'order_key'           The hash key of the order in which the subscription was purchased
	 *        'order_id'            The id of the order in which the subscription was purchased
	 *        'start_date'          The date the subscription was activated
	 *        'expiry_date'         The date the subscription expires or expired, false if the subscription will never expire
	 *        'end_date'            The date the subscription ended, false if the subscription has not yet ended
	 *        'status'              Subscription status can be: cancelled, active, expired or failed
	 *        'completed_payments'  An array of MySQL formatted dates for all payments that have been made on the subscription
	 * @since 1.0
	 */
	public static function update_users_subscriptions( $user_id, $new_subscription_details ) {

		$subscriptions = self::get_users_subscriptions( $user_id );

		$subscriptions = self::array_merge_recursive_for_real( $subscriptions, $new_subscription_details );

		foreach ( $subscriptions as $subscription_key => $subscription_details )
			if ( 'deleted' == $subscription_details['status'] )
				unset( $subscriptions[$subscription_key] );

		update_user_option( $user_id, self::$users_meta_key, $subscriptions );

		do_action( 'updated_users_subscriptions', $user_id, $new_subscription_details );

		return $subscriptions;
	}

	/**
	 * Clear all subscriptions from a user's account for a given order.
	 *
	 * @param $order WC_Order The order for which subscriptions should be cleared.
	 * @since 1.0
	 */
	public static function clear_users_subscriptions_from_order( $order ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		$subscriptions = self::get_users_subscriptions( $order->user_id );

		foreach ( $subscriptions as $subscription_key => $subscription_details ) {
			if ( $subscription_details['order_id'] == $order->id )
				unset( $subscriptions[$subscription_key] );
		}

		update_user_option( $order->user_id, self::$users_meta_key, $subscriptions );

		do_action( 'cleared_users_subscriptions_from_order', $order );
	}

	/**
	 * Clear all subscriptions from a user's account for a given order.
	 *
	 * @param $order WC_Order The order for which subscriptions should be cleared.
	 * @since 1.0
	 */
	public static function maybe_trash_subscription( $order ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		if ( WC_Subscriptions_Order::order_contains_subscription( $order ) ) {
			foreach( $order->get_items() as $order_item ) {
				if ( WC_Subscriptions_Order::is_item_subscription( $order, $order_item ) ) {
					self::trash_subscription( $order->customer_user, self::get_subscription_key( $order->id, WC_Subscriptions_Order::get_items_product_id( $order_item ) ) );
				}
			}
		}

	}

	/**
	 * Check if a given subscription can be changed to a given a status. 
	 * 
	 * The function checks the subscription's current status and if the payment gateway used to purchase the
	 * subscription allows for the given status to be set via its API. 
	 * 
	 * @param $changed_to_status string The status you want to change th subscription to.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @since 1.0
	 */
	public static function can_subscription_be_changed_to( $new_status, $subscription_key, $user_id = '' ) {
		global $woocommerce;

		$subscription = self::get_subscription( $subscription_key, $user_id );

		if ( empty( $subscription ) ) {
			$subscription_can_be_changed = false;
		} else {

			$order = new WC_Order( $subscription['order_id'] );

			$payment_gateways = $woocommerce->payment_gateways->payment_gateways();

			$payment_gateway  = isset( $payment_gateways[$order->payment_method] ) ? $payment_gateways[$order->payment_method] : '';

			$order_uses_manual_payments = ( empty( $payment_gateway ) || WC_Subscriptions_Order::requires_manual_renewal( $order ) ) ? true : false;

			switch( $new_status ) {
				case 'active' :
					if ( ( $order_uses_manual_payments || $payment_gateway->supports( 'subscription_reactivation' ) ) && $subscription['status'] == 'on-hold' )
						$subscription_can_be_changed = true;
					elseif ( $subscription['status'] == 'pending' )
						$subscription_can_be_changed = true;
					else
						$subscription_can_be_changed = false;
					break;
				case 'on-hold' :
				case 'suspended' : // Backward compatibility
					if ( ( $order_uses_manual_payments || $payment_gateway->supports( 'subscription_suspension' ) ) && in_array( $subscription['status'], array( 'active', 'pending' ) ) )
						$subscription_can_be_changed = true;
					else
						$subscription_can_be_changed = false;
					break;
				case 'cancelled' :
					if ( ( $order_uses_manual_payments || $payment_gateway->supports( 'subscription_cancellation' ) ) && ! in_array( $subscription['status'], array( 'cancelled', 'expired', 'trash' ) ) )
						$subscription_can_be_changed = true;
					else
						$subscription_can_be_changed = false;
					break;
				case 'expired' :
					if ( ! in_array( $subscription['status'], array( 'cancelled', 'trash' ) ) )
						$subscription_can_be_changed = true;
					else
						$subscription_can_be_changed = false;
					break;
				case 'trash' :
					if ( in_array( $subscription['status'], array( 'cancelled', 'expired' ) ) || self::can_subscription_be_changed_to( 'cancelled', $subscription_key, $user_id ) )
						$subscription_can_be_changed = true;
					else
						$subscription_can_be_changed = false;
					break;
				case 'deleted' :
					if ( 'trash' == $subscription['status'] )
						$subscription_can_be_changed = true;
					else
						$subscription_can_be_changed = false;
					break;
				case 'failed' :
					$subscription_can_be_changed = false;
					break;
				case 'new-payment-date' :
					$next_payment_timestamp = self::get_next_payment_date( $subscription_key, $user_id, 'timestamp' );
					if ( 0 != $next_payment_timestamp && ( $order_uses_manual_payments || $payment_gateway->supports( 'subscription_date_changes' ) ) && ! in_array( $subscription['status'], array( 'cancelled', 'trash', 'expired' ) ) )
						$subscription_can_be_changed = true;
					else
						$subscription_can_be_changed = false;
					break;
				default :
					$subscription_can_be_changed = false;
					break;
			}
		}

		return apply_filters( 'woocommerce_subscription_can_be_changed_to_' . $new_status, $subscription_can_be_changed, $subscription, $order );
	}

	/*
	 * Subscription Getters & Property functions
	 */

	/**
	 * Return an associative array of a given subscriptions details (if it exists).
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @return array Subscription details
	 * @since 1.1
	 */
	public static function get_subscription( $subscription_key, $user_id = '' ) {

		$subscription = array();

		if ( ! empty( $user_id ) ) {
			$subscription = self::get_users_subscription( $user_id, $subscription_key );
		} else {
			$users_subscriptions = self::get_all_users_subscriptions();

			foreach( $users_subscriptions as $user_id => $users_subscription ) {
				if ( isset( $users_subscription[$subscription_key] ) ) {
					$subscription = $users_subscription[$subscription_key];
					break;
				}
			}
		}

		return apply_filters( 'woocommerce_get_subscription', $subscription, $subscription_key, $user_id );
	}


	/**
	 * Return a multi-dimensional associative array of subscriptions with a certain value, grouped by user ID.
	 *
	 * A horrendously slow PHP based search routine which can't use the speed of MySQL because subscription
	 * details, like Product ID & User ID are stored in a serialised array instead of individual columns.
	 *
	 * @param $search_query String The query to search the database for. 
	 * @return array Subscription details
	 * @since 1.1
	 */
	public static function search_subscriptions( $search_query ) {
		global $wpdb;

		$subscriptions_to_search = self::get_all_users_subscriptions();

		$subscriptions_found = array();

		foreach ( $subscriptions_to_search as $user_id => $subscriptions ) {

			$user = get_user_by( 'id', $user_id )->data;

			// If the search query is found in the user's details, add all of their subscriptions, otherwise add only subscriptions with a matching item
			if ( false != preg_match( "/$search_query/i", $user->user_nicename ) || false != preg_match( "/$search_query/i", $user->display_name ) ) {
				$subscriptions_found[$user_id] = $subscriptions;
			} elseif ( false != preg_match( "/$search_query/i", $user->user_login ) || false != preg_match( "/$search_query/i", $user->user_email ) ) {
				$subscriptions_found[$user_id] = $subscriptions;
			} else {
				foreach ( $subscriptions as $subscription_key => $subscription ) {

					$product_title = get_the_title( $subscription['product_id'] );

					if ( in_array( $search_query, $subscription, true ) || false != preg_match( "/$search_query/i", $product_title ) )
						$subscriptions_found[$user_id][$subscription_key] = $subscription;

				}
			}
		}

		return apply_filters( 'woocommerce_search_subscriptions', $subscriptions_found, $search_query );
	}

	/**
	 * Return an i18n'ified string for a given subscription status.
	 *
	 * @param $status String An subscription status of it's internal form.
	 * @return string A translated subscription status string for display.
	 * @since 1.2.3
	 */
	public static function get_status_to_display( $status, $subscription_key = '', $user_id = 0 ) {

		switch ( $status ) {
			case 'active' : 
				$status_string = __( 'Active', WC_Subscriptions::$text_domain );
				break;
			case 'cancelled' :
				$status_string = __( 'Cancelled', WC_Subscriptions::$text_domain );
				break;
			case 'expired' :
				$status_string = __( 'Expired', WC_Subscriptions::$text_domain );
				break;
			case 'pending' :
				$status_string = __( 'Pending', WC_Subscriptions::$text_domain );
				break;
			case 'failed' :
				$status_string = __( 'Failed', WC_Subscriptions::$text_domain );
				break;
			case 'on-hold' :
			case 'suspend' : // Backward compatibility
				$status_string = __( 'On-hold', WC_Subscriptions::$text_domain );
				break;
			default :
				$status_string = apply_filters( 'woocommerce_subscriptions_custom_status_string', ucfirst( $status ), $subscription_key, $user_id );
		}

		return apply_filters( 'woocommerce_subscriptions_status_string', $status_string, $status, $subscription_key, $user_id );
	}

	/**
	 * Return an i18n'ified associative array of all possible subscription periods.
	 *
	 * @since 1.1
	 */
	public static function get_subscription_period_strings( $number = 1, $period = '' ) {

		$translated_periods = apply_filters( 'woocommerce_subscription_periods',
			array(
				'day'   => sprintf( _n( 'day', '%s days', $number, WC_Subscriptions::$text_domain ), $number ),
				'week'  => sprintf( _n( 'week', '%s weeks', $number, WC_Subscriptions::$text_domain ), $number ),
				'month' => sprintf( _n( 'month', '%s months', $number, WC_Subscriptions::$text_domain ), $number ),
				'year'  => sprintf( _n( 'year', '%s years', $number, WC_Subscriptions::$text_domain ), $number )
			)
		);

		return ( ! empty( $period ) ) ? $translated_periods[$period] : $translated_periods;
	}

	/**
	 * Return an i18n'ified associative array of all possible subscription periods.
	 *
	 * @since 1.0
	 */
	public static function get_subscription_period_interval_strings( $interval = '' ) {

		$intervals = array( 1 => __( 'per', WC_Subscriptions::$text_domain ) );

		foreach ( range( 2, 6 ) as $i )
			$intervals[$i] = sprintf( __( 'every %s', WC_Subscriptions::$text_domain ), WC_Subscriptions::append_numeral_suffix( $i )  );

		$intervals = apply_filters( 'woocommerce_subscription_period_interval_strings', $intervals );

		if ( empty( $interval ) )
			return $intervals;
		else
			return $intervals[$interval];
	}

	/**
	 * Returns an array of subscription lengths. 
	 * 
	 * PayPal Standard Allowable Ranges
	 * D – for days; allowable range is 1 to 90
	 * W – for weeks; allowable range is 1 to 52
	 * M – for months; allowable range is 1 to 24
	 * Y – for years; allowable range is 1 to 5
	 * 
	 * @param subscription_period string (optional) One of day, week, month or year. If empty, all subscription ranges are returned.
	 * @since 1.0
	 */
	public static function get_subscription_ranges( $subscription_period = '' ) {

		$subscription_periods = self::get_subscription_period_strings();

		foreach ( array( 'day', 'week', 'month', 'year' ) as $period ) {

			$subscription_lengths = array( 
				__( 'all time', WC_Subscriptions::$text_domain ),
				sprintf( __( '%s %s', WC_Subscriptions::$text_domain ), 1, $subscription_periods[$period] ),
			);

			switch( $period ) {
				case 'day':
					$subscription_range = range( 2, 90 );
					break;
				case 'week':
					$subscription_range = range( 2, 52 );
					break;
				case 'month':
					$subscription_range = range( 2, 24 );
					break;
				case 'year':
					$subscription_range = range( 2, 5 );
					break;
			}

			foreach ( $subscription_range as $number )
				$subscription_range[$number] = self::get_subscription_period_strings( $number, $period );

			// Add the possible range to all time range
			$subscription_lengths += $subscription_range;

			$subscription_ranges[$period] = $subscription_lengths;
		}

		if ( ! empty( $subscription_period ) )
			return $subscription_ranges[$subscription_period];
		else
			return $subscription_ranges;
	}

	/**
	 * Returns an array of allowable trial periods. 
	 * 
	 * @see self::get_subscription_ranges()
	 * @param subscription_period string (optional) One of day, week, month or year. If empty, all subscription ranges are returned.
	 * @since 1.1
	 */
	public static function get_subscription_trial_lengths( $subscription_period = '' ) {

		$all_trial_periods = self::get_subscription_ranges();

		foreach ( $all_trial_periods as $period => $trial_periods )
			$all_trial_periods[$period][0] = __( 'no', WC_Subscriptions::$text_domain ); // "No Trial Period"

		if ( ! empty( $subscription_period ) )
			return $all_trial_periods[$subscription_period];
		else
			return $all_trial_periods;
	}

	/**
	 * Return an i18n'ified associative array of all possible subscription trial periods.
	 *
	 * @since 1.2
	 */
	public static function get_subscription_trial_period_strings( $number = 1, $period = '' ) {

		$translated_periods = apply_filters( 'woocommerce_subscription_trial_periods',
			array(
				'day'   => sprintf( _n( '%s day', 'a %s-day', $number, WC_Subscriptions::$text_domain ), $number ),
				'week'  => sprintf( _n( '%s week', 'a %s-week', $number, WC_Subscriptions::$text_domain ), $number ),
				'month' => sprintf( _n( '%s month', 'a %s-month', $number, WC_Subscriptions::$text_domain ), $number ),
				'year'  => sprintf( _n( '%s year', 'a %s-year', $number, WC_Subscriptions::$text_domain ), $number )
			)
		);

		return ( ! empty( $period ) ) ? $translated_periods[$period] : $translated_periods;
	}

	/**
	 * Return an i18n'ified associative array of all time periods allowed for subscriptions.
	 * 
	 * @param $form string Either 'singular' for singular trial periods or 'plural'. 
	 * @since 1.2
	 */
	public static function get_available_time_periods( $form = 'singular' ) {

		$number = ( 'singular' == $form ) ? 1 : 2;

		$translated_periods = apply_filters( 'woocommerce_subscription_available_time_periods',
			array(
				'day'   => _n( 'day', 'days', $number, WC_Subscriptions::$text_domain ),
				'week'  => _n( 'week', 'weeks', $number, WC_Subscriptions::$text_domain ),
				'month' => _n( 'month', 'months', $number, WC_Subscriptions::$text_domain ),
				'year'  => _n( 'year', 'years', $number, WC_Subscriptions::$text_domain )
			)
		);

		return $translated_periods;
	}

	/**
	 * Returns the string key for a subscription purchased in an order specified by $order_id
	 * 
	 * @param order_id int The ID of the order in which the subscription was purchased. 
	 * @param product_id int The ID of the subscription product.
	 * @return string The key representing the given subscription.
	 * @since 1.0
	 */
	public static function get_subscription_key( $order_id, $product_id = '' ) {

		// If we have a child renewal order, we need the parent order's ID
		if ( WC_Subscriptions_Renewal_Order::is_renewal( $order_id, array( 'order_role' => 'child' ) ) )
			$order_id = WC_Subscriptions_Renewal_Order::get_parent_order_id( $order_id );

		if ( empty( $product_id ) ) {
			$order            = new WC_Order( $order_id );
			$order_items      = WC_Subscriptions_Order::get_recurring_items( $order );
			$first_order_item = reset( $order_items );
			$product_id       = WC_Subscriptions_Order::get_items_product_id( $first_order_item );
		}

		$subscription_key = $order_id . '_' . $product_id;

		return apply_filters( 'woocommerce_subscription_key', $subscription_key, $order_id, $product_id );
	}

	/**
	 * Returns the number of failed payments for a given subscription.
	 * 
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @return int The number of outstanding failed payments on the subscription, if any.
	 * @since 1.0
	 */
	public static function get_subscriptions_failed_payment_count( $subscription_key, $user_id = '' ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		if ( ! isset( $subscription['failed_payments'] ) )
			$subscription['failed_payments'] = 0;

		return apply_filters( 'woocommerce_subscription_failed_payment_count', $subscription['failed_payments'], $user_id, $subscription_key );
	}

	/**
	 * Takes a subscription key and returns the date on which the subscription is scheduled to expire 
	 * or 0 if it is cancelled, expired, or never going to expire.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @param $type string (optional) The format for the Either 'mysql' or 'timestamp'.
	 * @since 1.1
	 */
	public static function get_subscription_expiration_date( $subscription_key, $user_id = '', $type = 'mysql' ) {

		if ( empty( $user_id ) )
			$user_id = self::get_user_id_from_subscription_key( $subscription_key );

		$expiration_date = wp_next_scheduled( 'scheduled_subscription_expiration', array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key ) ); 

		// No date scheduled, try calculating it
		if ( false === $expiration_date )
			$expiration_date = self::calculate_subscription_expiration_date( $subscription_key, $user_id, 'timestamp' );

		$expiration_date = ( 'mysql' == $type && 0 != $expiration_date ) ? date( 'Y-m-d H:i:s', $expiration_date ) : $expiration_date;

		return apply_filters( 'woocommerce_subscription_expiration_date' , $expiration_date, $subscription_key, $user_id );
	}

	/**
	 * Updates a subscription's expiration date as scheduled in WP-Cron and in the subscription details array.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int (optional) The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @param $next_payment (optional) string | int The date and time the next payment is due, either as MySQL formatted datetime string or a Unix timestamp. If empty, @see self::calculate_next_payment_date() will be called.
	 * @return mixed If the expiration does not get set, returns false, otherwise it will return a MySQL datetime formatted string for the new date when the subscription will expire
	 * @since 1.2.4
	 */
	public static function set_expiration_date( $subscription_key, $user_id = '', $expiration_date = '' ) {

		$is_set = false;

		if ( empty( $user_id ) )
			$user_id = self::get_user_id_from_subscription_key( $subscription_key );

		if ( empty( $expiration_date ) )
			$expiration_date = self::calculate_subscription_expiration_date( $subscription_key, $user_id, 'timestamp' );
		elseif ( is_string( $expiration_date ) )
			$expiration_date = strtotime( $expiration_date );

		// Update the date stored on the subscription
		$date_string = ( $expiration_date != 0 ) ? date( 'Y-m-d H:i:s', $expiration_date ) : 0;
		self::update_users_subscriptions( $user_id, array( $subscription_key => array( 'expiry_date' => $date_string ) ) );

		$hook_args = array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key );

		// Clear the existing schedule for this hook
		wp_clear_scheduled_hook( 'scheduled_subscription_expiration', $hook_args );

		if ( $expiration_date != 0 && $expiration_date > gmdate( 'U' ) ) {
			wp_schedule_single_event( $expiration_date, 'scheduled_subscription_expiration', array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key ) );
			$is_set = true;
		}

		return apply_filters( 'woocommerce_subscriptions_set_expiration_date', $is_set, $expiration_date, $subscription_key, $user_id );
	}

	/**
	 * Takes a subscription key and calculates the date on which the subscription is scheduled to expire 
	 * or 0 if it will never expire.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @param $type string (optional) The format for the Either 'mysql' or 'timestamp'.
	 * @since 1.1
	 */
	public static function calculate_subscription_expiration_date( $subscription_key, $user_id = '', $type = 'mysql' ) {

		$subscription = self::get_subscription( $subscription_key, $user_id );

		if ( empty( $subscription ) ) {

			$expiration_date = 0;

		} else {

			$order = new WC_Order( $subscription['order_id'] );

			$subscription_period       = WC_Subscriptions_Order::get_subscription_period( $order, $subscription['product_id'] );
			$subscription_length       = WC_Subscriptions_Order::get_subscription_length( $order, $subscription['product_id'] );
			$subscription_interval     = WC_Subscriptions_Order::get_subscription_interval( $order, $subscription['product_id'] );
			$subscription_trial_length = WC_Subscriptions_Order::get_subscription_trial_length( $order, $subscription['product_id'] );
			$subscription_trial_period = WC_Subscriptions_Order::get_subscription_trial_period( $order, $subscription['product_id'] );

			if ( $subscription_length > 0 ){

				if ( isset( $subscription['start_date'] ) && ! empty( $subscription['start_date'] ) )
					$start_date = $subscription['start_date'];
				elseif ( ! empty( $order->order_date ) )
					$start_date = $order->order_date - ( get_option( 'gmt_offset' ) * 3600 );
				else
					$start_date = gmdate( 'Y-m-d H:i:s' );

				$expiration_date = strtotime( "+ $subscription_trial_length {$subscription_trial_period}s", strtotime( $start_date ) );
				$expiration_date = strtotime( "+ $subscription_length {$subscription_period}s", $expiration_date );

			} else {

				$expiration_date = 0;

			}
		}

		$expiration_date = ( 'mysql' == $type && 0 != $expiration_date ) ? date( 'Y-m-d H:i:s', $expiration_date ) : $expiration_date;

		return apply_filters( 'woocommerce_subscription_calculated_expiration_date', $expiration_date, $subscription_key, $user_id );
	}

	/**
	 * Takes a subscription key and returns the date on which the next recurring payment is to be billed, if any.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @param $type string (optional) The format for the Either 'mysql' or 'timestamp'.
	 * @return mixed If there is no future payment set, returns 0, otherwise it will return a date of the next payment in the form specified by $type
	 * @since 1.2
	 */
	public static function get_next_payment_date( $subscription_key, $user_id = '', $type = 'mysql' ) {

		if ( empty( $user_id ) )
			$user_id = self::get_user_id_from_subscription_key( $subscription_key );

		$subscription = self::get_subscription( $subscription_key, $user_id );

		$next_payment_date = wp_next_scheduled( 'scheduled_subscription_payment', array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key ) ); 

		// No date scheduled, try calculating it
		if ( false === $next_payment_date && 'active' == $subscription['status'] )
			$next_payment_date = self::calculate_next_payment_date( $subscription_key, $user_id, 'timestamp' );

		$next_payment_date = ( 'mysql' == $type && 0 != $next_payment_date ) ? date( 'Y-m-d H:i:s', $next_payment_date ) : $next_payment_date;

		return apply_filters( 'woocommerce_subscription_next_payment_date', $next_payment_date, $subscription_key, $user_id, $type );
	}

	/**
	 * Clears the payment schedule for a subscription and schedules a new date for the next payment.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int (optional) The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @param $next_payment (optional) string | int The date and time the next payment is due, either as MySQL formatted datetime string or a Unix timestamp. If empty, @see self::calculate_next_payment_date() will be called.
	 * @return mixed If there is no future payment set, returns 0, otherwise it will return a MySQL datetime formatted string for the date of the next payment
	 * @since 1.2
	 */
	public static function set_next_payment_date( $subscription_key, $user_id = '', $next_payment = '' ) {

		$is_set = false;

		if ( empty( $user_id ) )
			$user_id = self::get_user_id_from_subscription_key( $subscription_key );

		if ( empty( $next_payment ) )
			$next_payment = self::calculate_next_payment_date( $subscription_key, $user_id, 'timestamp' );
		elseif ( is_string( $next_payment ) )
			$next_payment = strtotime( $next_payment );

		$hook_args = array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key );

		// Clear the existing schedule for this hook
		wp_clear_scheduled_hook( 'scheduled_subscription_payment', $hook_args );

		if ( $next_payment != 0 && $next_payment > gmdate( 'U' ) ) {
			wp_schedule_single_event( $next_payment, 'scheduled_subscription_payment', array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key ) );
			$is_set = true;
		}

		return apply_filters( 'woocommerce_subscription_set_next_payment_date', $is_set, $next_payment, $subscription_key, $user_id );
	}

	/**
	 * Takes a subscription key and returns the date on which the next recurring payment is to be billed, if any.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @param $type string (optional) The format for the Either 'mysql' or 'timestamp'.
	 * @return mixed If there is no future payment set, returns 0, otherwise it will return a date of the next payment in the form specified by $type
	 * @since 1.2
	 */
	public static function get_last_payment_date( $subscription_key, $user_id = '', $type = 'mysql' ) {

		$subscription = self::get_subscription( $subscription_key, $user_id );

		$last_payment_date = array_pop( $subscription['completed_payments'] );

		// No payments recorded
		if ( null === $last_payment_date )
			$last_payment_date = '';

		$last_payment_date = ( 'mysql' != $type ) ? strtotime( $last_payment_date ) : $last_payment_date;

		return apply_filters( 'woocommerce_subscription_next_payment_date', $last_payment_date, $subscription_key, $user_id, $type );
	}

	/**
	 * Changes the transient used to safeguard against firing scheduled_subscription_payments during a payment period.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $lock_time int The amount of time to lock for in seconds from now, the lock will be set 1 hour before this time
	 * @param $user_id int (optional) The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @since 1.2
	 */
	public static function update_wp_cron_lock( $subscription_key, $lock_time, $user_id = '' ) {
		global $wpdb;

		if ( empty( $user_id ) )
			$user_id = self::get_user_id_from_subscription_key( $subscription_key );

		$lock_key = 'wcs_blocker_' . $user_id . '_' . $subscription_key;

		// If there are future payments, block until then, otherwise block for 23 hours
		$lock_time  = ( $lock_time > 0 ) ? $lock_time - 60 * 60 : 60 * 60 * 23;
		$lock_time += gmdate( 'U' );

		// Bypass options API
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name = %s", $lock_key ) );
		$wpdb->insert( $wpdb->options, array( 'option_name' => $lock_key, 'option_value' => $lock_time ), array( '%s', '%s' ) );
	}

	/**
	 * Clears the payment schedule for a subscription and sets a net date 
	 * 
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int (optional) The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @param $type string (optional) The format for the Either 'mysql' or 'timestamp'.
	 * @return mixed If there is no future payment set, returns 0, otherwise it will return a date of the next payment of the type specified with $type
	 * @since 1.2
	 */
	public static function calculate_next_payment_date( $subscription_key, $user_id = '', $type = 'mysql', $from_date = '' ) {

		$subscription = self::get_subscription( $subscription_key, $user_id );

		$next_payment = WC_Subscriptions_Order::calculate_next_payment_date( $subscription['order_id'], $subscription['product_id'], $type, $from_date );

		return $next_payment;
	}

	/**
	 * Takes a subscription key and returns the date on which the trial for the subscription ended or is going to end, if any.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @return mixed If the subscription has no trial period, returns 0, otherwise it will return the date the trial period ends or ended in the form specified by $type
	 * @since 1.2
	 */
	public static function get_trial_expiration_date( $subscription_key, $user_id = '', $type = 'mysql' ) {

		if ( empty( $user_id ) )
			$user_id = self::get_user_id_from_subscription_key( $subscription_key );

		$trial_expiry = wp_next_scheduled( 'scheduled_subscription_trial_end', array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key ) ); 

		if ( false === $trial_expiry ) {
			$subscription = self::get_subscription( $subscription_key, $user_id );
			$trial_expiry = ( ! empty( $subscription['trial_expiry_date'] ) ) ? strtotime( $subscription['trial_expiry_date'] ) : 0;
		}

		$trial_expiry = ( 'mysql' == $type && 0 != $trial_expiry ) ? date( 'Y-m-d H:i:s', $trial_expiry ) : $trial_expiry;

		return apply_filters( 'woocommerce_subscription_trial_expiration_date' , $trial_expiry, $subscription_key, $user_id );
	}

	/**
	 * Updates the trial expiration date as scheduled in WP-Cron and in the subscription details array.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int (optional) The ID of the user who owns the subscription. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @param $next_payment (optional) string | int The date and time the next payment is due, either as MySQL formatted datetime string or a Unix timestamp. If empty, @see self::calculate_next_payment_date() will be called.
	 * @return mixed If the trial expiration does not get set, returns false, otherwise it will return a MySQL datetime formatted string for the new date when the trial will expire
	 * @since 1.2.4
	 */
	public static function set_trial_expiration_date( $subscription_key, $user_id = '', $trial_expiration_date = '' ) {

		$is_set = false;

		if ( empty( $user_id ) )
			$user_id = self::get_user_id_from_subscription_key( $subscription_key );

		if ( empty( $trial_expiration_date ) )
			$trial_expiration_date = self::calculate_trial_expiration_date( $subscription_key, $user_id, 'timestamp' );
		elseif ( is_string( $trial_expiration_date ) )
			$trial_expiration_date = strtotime( $trial_expiration_date );

		// Update the date stored on the subscription
		$date_string = ( $trial_expiration_date != 0 ) ? date( 'Y-m-d H:i:s', $trial_expiration_date ) : 0;
		self::update_users_subscriptions( $user_id, array( $subscription_key => array( 'trial_expiry_date' => $date_string ) ) );

		$hook_args = array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key );

		// Clear the existing schedule for this hook
		wp_clear_scheduled_hook( 'scheduled_subscription_trial_end', $hook_args );

		if ( $trial_expiration_date != 0 && $trial_expiration_date > gmdate( 'U' ) ) {
			wp_schedule_single_event( $trial_expiration_date, 'scheduled_subscription_trial_end', array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key ) );
			$is_set = true;
		}

		return apply_filters( 'woocommerce_subscriptions_set_trial_expiration_date', $is_set, $trial_expiration_date, $subscription_key, $user_id );
	}

	/**
	 * Takes a subscription key and calculates the date on which the subscription is scheduled to expire
	 * or 0 if it will never expire.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @param $type string (optional) The format for the Either 'mysql' or 'timestamp'.
	 * @since 1.1
	 */
	public static function calculate_trial_expiration_date( $subscription_key, $user_id = '', $type = 'mysql' ) {

		$subscription = self::get_subscription( $subscription_key, $user_id );

		if ( empty( $subscription ) ) {

			$expiration_date = 0;

		} else {

			$order = new WC_Order( $subscription['order_id'] );

			$subscription_trial_length = WC_Subscriptions_Order::get_subscription_trial_length( $order, $subscription['product_id'] );
			$subscription_trial_period = WC_Subscriptions_Order::get_subscription_trial_period( $order, $subscription['product_id'] );

			if ( $subscription_trial_length > 0 ){

				if ( isset( $subscription['start_date'] ) && ! empty( $subscription['start_date'] ) )
					$start_date = $subscription['start_date'];
				elseif ( ! empty( $order->order_date ) )
					$start_date = $order->order_date - ( get_option( 'gmt_offset' ) * 3600 );
				else
					$start_date = gmdate( 'Y-m-d H:i:s' );

				$expiration_date = strtotime( "+ $subscription_trial_length {$subscription_trial_period}s", strtotime( $start_date ) );

			} else {

				$expiration_date = 0;

			}
		}

		$expiration_date = ( 'mysql' == $type && 0 != $expiration_date ) ? date( 'Y-m-d H:i:s', $expiration_date ) : $expiration_date;

		return apply_filters( 'woocommerce_subscription_calculated_trial_expiration_date' , $expiration_date, $subscription_key, $user_id );
	}

	/**
	 * Takes a subscription key and returns the date on which the next recurring payment is to be billed, if any.
	 * 
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @return int The ID of the user who owns the subscriptions, or 0 if no user can be found with the subscription
	 * @since 1.2
	 */
	public static function get_user_id_from_subscription_key( $subscription_key ) {

		$all_subscriptions = self::get_all_users_subscriptions();
		$user_id = 0;

		foreach( $all_subscriptions as $subscribers_id => $users_subscription ) {
			if ( isset( $users_subscription[$subscription_key] ) ) {
				$user_id = $subscribers_id;
				break;
			}
		}

		return $user_id;
	}

	/**
	 * Checks if a subscription requires manual payment because the payment gateway used to purchase the subscription
	 * did not support automatic payments at the time of the subscription sign up.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @return bool | null True if the subscription exists and requires manual payments, false if the subscription uses automatic payments, null if the subscription doesn't exist.
	 * @since 1.2
	 */
	public static function requires_manual_renewal( $subscription_key, $user_id = '' ) {

		$subscription = self::get_subscription( $subscription_key, $user_id );

		if ( isset( $subscription['order_id'] ) )
			$requires_manual_renewals = WC_Subscriptions_Order::requires_manual_renewal( $subscription['order_id'] );
		else
			$requires_manual_renewals = null;

		return $requires_manual_renewals;
	}

	/**
	 * Checks if a subscription has an unpaid renewal order.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The ID of the user who owns the subscriptions. Although this parameter is optional, if you have the User ID you should pass it to improve performance.
	 * @return bool True if the subscription has an unpaid renewal order, false if the subscription has no unpaid renewal orders.
	 * @since 1.2
	 */
	public static function subscription_requires_payment( $subscription_key, $user_id ) {
		global $wpdb;

		$subscription = WC_Subscriptions_Manager::get_subscription( $subscription_key, $user_id );

		if ( empty( $subscription ) ) {

			$subscription_requires_payment = true;

		} else {

			$subscription_requires_payment = false;

			$statuses_requiring_payment = array( 'pending', 'on-hold', 'failed' );

			$order = new WC_Order( $subscription['order_id'] );

			if ( in_array( $order->status, $statuses_requiring_payment ) ) {
				$subscription_requires_payment = true;
			} else {
				$renewal_order_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent = %s", $subscription['order_id'] ) );

				foreach ( $renewal_order_ids as $renewal_order_id ) {
					$renewal_order = new WC_Order( $renewal_order_id );
					if ( in_array( $renewal_order->status, $statuses_requiring_payment ) ) {
						$subscription_requires_payment = true;
						break;
					}
				}
			}
		}

		return apply_filters( 'woocommerce_subscription_requires_payment', $subscription_requires_payment, $subscription, $subscription_key, $user_id );
	}

	/*
	 * User API Functions
	 */

	/**
	 * Check if a user owns a subscription, as specified with $subscription_key.
	 *
	 * If no user is specified, the currently logged in user will be used.
	 *
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int (optional) int The ID of the user to check against. Defaults to the currently logged in user.
	 * @return bool True if the user has the subscription (or any subscription if no subscription specified), otherwise false.
	 * @since 1.3
	 */
	public static function user_owns_subscription( $subscription_key, $user_id = 0 ) {

		$subscriptions = self::get_users_subscriptions( $user_id );

		$owns_subscription = false;

		foreach ( $subscriptions as $key => $subscription ) {
			if ( $subscription_key == $key ) {
				$owns_subscription = true;
				break;
			}
		}

		return apply_filters( 'woocommerce_user_owns_subscription', $owns_subscription, $subscription_key, $user_id );
	}

	/**
	 * Check if a user has a subscription, optionally specified with $product_id.
	 * 
	 * @param $user_id int (optional) The id of the user whose subscriptions you want. Defaults to the currently logged in user.
	 * @param product_id int (optional) The ID of a subscription product.
	 * @param status string (optional) A subscription status to check against. For example, for a $status of 'active', a subscriber must have an active subscription for a return value of true.
	 * @return bool True if the user has the subscription (or any subscription if no subscription specified), otherwise false.
	 * @version 1.3.5
	 */
	public static function user_has_subscription( $user_id = 0, $product_id = '', $status = '' ) {

		$subscriptions = self::get_users_subscriptions( $user_id );

		$has_subscription = false;

		if ( empty( $product_id ) ) { // Any subscription

			if ( ! empty( $status ) ) // We need to check for a specific status
				foreach ( $subscriptions as $subscription )
					if ( $subscription['status'] == $status )
						$has_subscription = true;
			elseif ( ! empty( $subscriptions ) )
				$has_subscription = true;

		} else {

			foreach ( $subscriptions as $subscription ) {
				if ( $subscription['product_id'] == $product_id && ( empty( $status ) || $subscription['status'] == $status ) ) {
					$has_subscription = true;
					break;
				}
			}

		}

		return apply_filters( 'woocommerce_user_has_subscription', $has_subscription, $user_id, $product_id );
	}

	/**
	 * Gets all the active and inactive subscriptions for all users.
	 * 
	 * @return array An associative array containing all users with subscriptions and the details of their subscriptions: 'user_id' => $subscriptions
	 * @since 1.0
	 */
	public static function get_all_users_subscriptions() {
		global $wpdb;

		$users_and_subscriptions = array();

		$users_with_subscriptions = get_users( array( 'fields' => 'id', 'meta_key' => $wpdb->get_blog_prefix() . self::$users_meta_key ) );

		foreach( $users_with_subscriptions as $user_id )
			$users_and_subscriptions[$user_id] = self::get_users_subscriptions( $user_id );

		return apply_filters( 'woocommerce_all_users_subscriptions', $users_and_subscriptions );
	}

	/**
	 * Gets all the active and inactive subscriptions for a user, as specified by $user_id
	 *
	 * @param $user_id int (optional) The id of the user whose subscriptions you want. Defaults to the currently logged in user.
	 * @since 1.0
	 */
	public static function get_users_subscriptions( $user_id = 0 ) {
		global $wpdb;

		if ( 0 === $user_id )
			$user_id = get_current_user_id();

		$subscriptions = get_user_option( $wpdb->get_blog_prefix() . self::$users_meta_key, $user_id ); // Prepending $wpdb->get_blog_prefix() to self::$users_meta_key circumvents the site agnostic fallback in WP

		if( empty( $subscriptions ) )
			$subscriptions = array();

		return apply_filters( 'woocommerce_users_subscriptions', $subscriptions, $user_id );
	}

	/**
	 * Gets a specific subscription for a user, as specified by $subscription_key
	 *
	 * @param $user_id int (optional) The id of the user whose subscriptions you want. Defaults to the currently logged in user.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function get_users_subscription( $user_id = 0, $subscription_key ) {

		$subscriptions = self::get_users_subscriptions( $user_id );

		if( isset( $subscriptions[$subscription_key] ) )
			$subscription = $subscriptions[$subscription_key];
		else
			$subscription = array();

		return apply_filters( 'woocommerce_users_subscription', $subscription, $user_id, $subscription_key );
	}

	/**
	 * Gets all the subscriptions for a user that have been trashed, as specified by $user_id
	 *
	 * @param $user_id int (optional) The id of the user whose subscriptions you want. Defaults to the currently logged in user.
	 * @since 1.0
	 */
	public static function get_users_trashed_subscriptions( $user_id = '' ) {

		$subscriptions = self::get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $key => $subscription )
			if ( $subscription['status'] != 'trash' )
				unset( $subscriptions[$key] );

		return apply_filters( 'woocommerce_users_trashed_subscriptions', $subscriptions, $user_id );
	}

	/**
	 * Removes a specific subscription for a user, as specified by $subscription_key
	 *
	 * @param $user_id int (optional) The id of the user whose subscriptions you want. Defaults to the currently logged in user.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function remove_users_subscription( $user_id, $subscription_key ) {

		$subscriptions = self::get_users_subscriptions( $user_id );

		$removed_subscription = array();

		if( isset( $subscriptions[$subscription_key] ) ) {
			$removed_subscription = $subscriptions[$subscription_key];
			unset( $subscriptions[$subscription_key] );
			update_user_option( $user_id, self::$users_meta_key, $subscriptions );
		}

		return apply_filters( 'woocommerce_removed_users_subscription', $removed_subscription, $user_id, $subscription_key );
	}

	/**
	 * A convenience wrapper to assign the inactive subscriber role to a user. 
	 *
	 * @param $user_id int The id of the user whose role should be changed
	 * @since 1.2
	 */
	public static function make_user_inactive( $user_id ) {
		self::update_users_role( $user_id, 'default_inactive_role' );
	}

	/**
	 * A convenience wrapper to assign the cancelled subscriber role to a user.
	 *
	 * Hooked to 'subscription_end_of_prepaid_term' hook.
	 *
	 * @param $user_id int The id of the user whose role should be changed
	 * @since 1.3.2
	 */
	public static function make_user_cancelled( $user_id ) {
		self::update_users_role( $user_id, 'default_cancelled_role' );
	}

	/**
	 * A convenience wrapper for changing a users role. 
	 * 
	 * @param $user_id int The id of the user whose role should be changed
	 * @param $role_name string Either a WordPress role or one of the WCS keys: 'default_subscriber_role' or 'default_cancelled_role'
	 * @since 1.0
	 */
	public static function update_users_role( $user_id, $role_name ) {
		$user = new WP_User( $user_id );

		// Never change an admin's role to avoid locking out admins testing the plugin
		if ( ! empty( $user->roles ) && in_array( 'administrator', $user->roles ) )
			return;

		if ( $role_name == 'default_subscriber_role' )
			$role_name = get_option( WC_Subscriptions_Admin::$option_prefix . '_subscriber_role' );
		else if ( in_array( $role_name, array( 'default_inactive_role', 'default_cancelled_role' ) ) )
			$role_name = get_option( WC_Subscriptions_Admin::$option_prefix . '_cancelled_role' );

		$user->set_role( $role_name );

		do_action( 'woocommerce_subscriptions_updated_users_role', $role_name, $user );
	}

	/**
	 * Marks a customer as a paying customer when their subscription is activated.
	 * 
	 * A wrapper for the @see woocommerce_paying_customer() function.
	 * 
	 * @param $order_id int The id of the order for which customers should be pulled from and marked as paying. 
	 * @since 1.0
	 */
	public static function mark_paying_customer( $order_id ) {

		if ( is_object( $order_id ) )
			$order_id = $order_id->id;

		woocommerce_paying_customer( $order_id );
	}

	/**
	 * Unlike someone making a once-off payment, a subscriber can cease to be a paying customer. This function 
	 * changes a user's status to non-paying. 
	 * 
	 * @param $order object The order for which a customer ID should be pulled from and marked as paying.
	 * @since 1.0
	 */
	public static function mark_not_paying_customer( $order ) {

		if ( ! is_object( $order ) )
			$order = new WC_Order( $order );

		if ( $order->user_id > 0 )
			update_user_meta( $order->user_id, 'paying_customer', 0 );
	}

	/**
	 * Return a link for subscribers to change the status of their subscription, as specified with $status parameter
	 * 
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function get_users_change_status_link( $subscription_key, $status ) {

		if ( 'suspended' == $status )
			$status = 'on-hold';

		$action_link = add_query_arg( array( 'subscription_key' => $subscription_key, 'change_subscription_to' => $status ) );
		$action_link = wp_nonce_url( $action_link, $subscription_key );

		return apply_filters( 'woocommerce_subscriptions_users_action_link', $action_link, $subscription_key, $status );
	}

	/**
	 * Return a link for subscribers to change the status of their subscription, as specified with $status parameter
	 * 
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function current_user_can_suspend_subscription( $subscription_key ) {

		$user_can_suspend = false;

		if ( current_user_can( 'manage_woocommerce' ) ) { // Admin, so can always suspend a subscription

			$user_can_suspend = true;

		} else {  // Need to make sure user owns subscription & the suspension limit hasn't been reached

			// Make sure current user owns subscription
			if ( true === self::user_owns_subscription( $subscription_key ) ) {

				$subscription = self::get_users_subscription( get_current_user_id(), $subscription_key );

				// Make sure subscription suspension count hasn't been reached
				$suspension_count    = isset( $subscription['suspension_count'] ) ? $subscription['suspension_count'] : 0;
				$allowed_suspensions = get_option( WC_Subscriptions_Admin::$option_prefix . '_max_customer_suspensions', 0 );

				if ( 'unlimited' === $allowed_suspensions || $allowed_suspensions > $suspension_count ) // 0 not > anything so prevents a customer ever being able to suspend
					$user_can_suspend = true;

			}
		}

		return apply_filters( 'woocommerce_subscriptions_can_current_user_suspend', $user_can_suspend, $subscription_key );
	}

	/**
	 * Checks if the current request is by a user to change the status of their subscription, and if it is
	 * validate the subscription cancellation request and maybe processes the cancellation. 
	 * 
	 * @since 1.0
	 */
	public static function maybe_change_users_subscription() {
		global $woocommerce;

		if ( isset( $_GET['change_subscription_to'] ) && isset( $_GET['subscription_key'] ) && isset( $_GET['_wpnonce'] )  ) {

			$user_id = get_current_user_id();
			$subscription = self::get_users_subscription( $user_id, $_GET['subscription_key'] );

			if ( wp_verify_nonce( $_GET['_wpnonce'], $_GET['subscription_key'] ) === false ) {

				$woocommerce->add_error( sprintf( __( 'That subscription can not be changed to %s. Please contact us if you need assistance.', WC_Subscriptions::$text_domain ), $_GET['change_subscription_to'] ) );

			} elseif ( empty( $subscription ) ) {

				$woocommerce->add_error( __( 'That doesn\'t appear to be one of your subscriptions.', WC_Subscriptions::$text_domain ) );

			} elseif ( ! WC_Subscriptions_Manager::can_subscription_be_changed_to( $_GET['change_subscription_to'], $_GET['subscription_key'], $user_id ) ) {

				$woocommerce->add_error( sprintf( __( 'That subscription can not be changed to %s. Please contact us if you need assistance.', WC_Subscriptions::$text_domain ), $_GET['change_subscription_to'] ) );

			} elseif ( ! in_array( $_GET['change_subscription_to'], array( 'active', 'on-hold', 'cancelled' ) ) ) {

				$woocommerce->add_error( sprintf( __( 'Unknown subscription status: "%s". Please contact us if you need assistance.', WC_Subscriptions::$text_domain ), $_GET['change_subscription_to'] ) );

			} else {

				switch ( $_GET['change_subscription_to'] ) {
					case 'active' :
						if ( WC_Subscriptions_Manager::subscription_requires_payment( $_GET['subscription_key'], $user_id ) )
							$woocommerce->add_error( sprintf( __( 'You can not reactive that subscription until paying to renew it. Please contact us if you need assistance.', WC_Subscriptions::$text_domain ), $_GET['change_subscription_to'] ) );
						else {
							self::reactivate_subscription( $user_id, $_GET['subscription_key'] );
							$status_message = __( 'reactivated', WC_Subscriptions::$text_domain );
						}
						break;
					case 'on-hold' :
						if ( self::current_user_can_suspend_subscription( $_GET['subscription_key'] ) ) {
							self::put_subscription_on_hold( $user_id, $_GET['subscription_key'] );
							$status_message = __( 'suspended', WC_Subscriptions::$text_domain );
						} else {
							$woocommerce->add_error( sprintf( __( 'You can not suspend that subscription - the suspension limit has been reached. Please contact us if you need assistance.', WC_Subscriptions::$text_domain ), $_GET['change_subscription_to'] ) );
						}
						break;
					case 'cancelled' :
						self::cancel_subscription( $user_id, $_GET['subscription_key'] );
						$status_message = __( 'cancelled', WC_Subscriptions::$text_domain );
						break;
				}

				if ( isset( $status_message ) ) {

					$order = new WC_Order( $subscription['order_id'] );

					$order->add_order_note( sprintf( __( 'The status of subscription %s was changed to %s by the subscriber from their account page.', WC_Subscriptions::$text_domain ), $_GET['subscription_key'], $_GET['change_subscription_to'] ) );

					$woocommerce->add_message( sprintf( __( 'Your subscription has been %s.', WC_Subscriptions::$text_domain ), $status_message ) );
				}
			}

			wp_safe_redirect( get_permalink( woocommerce_get_page_id( 'myaccount' ) ) );
			exit;
		}
	}

	/**
	 * Processes an ajax request to change a subscription's next payment date.
	 *
	 * @since 1.2
	 */
	public static function ajax_update_next_payment_date() {

		$response = array( 'status' => 'error' );

		if ( ! wp_verify_nonce( $_POST['wcs_nonce'], WC_Subscriptions::$text_domain ) ) {

			$response['message'] = sprintf( '<div class="error">%s</div>', __( 'Invalid security token, please reload the page and try again.', WC_Subscriptions::$text_domain ) );

		} elseif ( ! current_user_can( 'manage_woocommerce' ) ) {

			$response['message'] = sprintf( '<div class="error">%s</div>', __( 'Only store managers can edit payment dates.', WC_Subscriptions::$text_domain ) );

		} elseif ( empty( $_POST['wcs_day'] ) || empty( $_POST['wcs_month'] ) || empty( $_POST['wcs_year'] ) ) {

			$response['message'] = sprintf( '<div class="error">%s</div>', __( 'Please enter all date fields.', WC_Subscriptions::$text_domain ) );

		} else {

			$new_payment_date      = sprintf( '%s-%s-%s %s', (int)$_POST['wcs_year'], zeroise( (int)$_POST['wcs_month'], 2 ), zeroise( (int)$_POST['wcs_day'], 2 ), date( 'H:i:s', current_time( 'timestamp' ) ) );
			$new_payment_timestamp = self::update_next_payment_date( $new_payment_date, $_POST['wcs_subscription_key'], $_POST['wcs_user_id'], 'user' );

			if ( is_wp_error( $new_payment_timestamp ) ) {

				$response['message'] = sprintf( '<div class="error">%s</div>', $new_payment_timestamp->get_error_message() );

			} else {

				$new_payment_timestamp_user_time = $new_payment_timestamp + ( get_option( 'gmt_offset' ) * 3600 ); // The timestamp is returned in server time

				$time_diff = $new_payment_timestamp - gmdate( 'U' );

				if ( $time_diff > 0 && $time_diff < 7 * 24 * 60 * 60 )
					$date_to_display = sprintf( __( 'In %s', WC_Subscriptions::$text_domain ), human_time_diff( gmdate( 'U' ), $new_payment_timestamp ) );
				else
					$date_to_display = date_i18n( get_option( 'date_format' ), $new_payment_timestamp_user_time );

				$response['status']        = 'success';
				$response['message']       = sprintf( '<div class="updated">%s</div>', __( 'Date Changed', WC_Subscriptions::$text_domain ) );
				$response['dateToDisplay'] = $date_to_display;
				$response['timestamp']     = $new_payment_timestamp_user_time;

			}
		}

		echo json_encode( $response );

		exit();
	}

	/**
	 * Change a subscription's next payment date.
	 *
	 * @param $new_payment_date mixed Either a MySQL formatted Date/time string or a Unix timestamp.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @param $user_id int The id of the user who purchased the subscription
	 * @param $timezone string Either 'server' or 'user' to describe the timezone of the $new_payment_date.
	 * @since 1.2
	 */
	public static function update_next_payment_date( $new_payment_date, $subscription_key, $user_id = '', $timezone = 'server' ) {

		$next_payment_timestamp = self::get_next_payment_date( $subscription_key, $user_id, 'timestamp' );
		$new_payment_timestamp  = ( is_numeric( $new_payment_date ) ) ? $new_payment_date : strtotime( $new_payment_date );

		// Use server time not user time
		if ( 'server' != $timezone )
			$new_payment_timestamp = $new_payment_timestamp - ( get_option( 'gmt_offset' ) * 3600 );

		$expiration_timestamp       = self::get_subscription_expiration_date( $subscription_key, $user_id, 'timestamp' );
		$trial_expiration_timestamp = self::get_trial_expiration_date( $subscription_key, $user_id, 'timestamp' );

		if ( 0 == $next_payment_timestamp ) {

			$response = new WP_Error( 'invalid-date', __( 'This subscription does not have any future payments.', WC_Subscriptions::$text_domain ) );

		} elseif ( $new_payment_timestamp <= gmdate( 'U' ) ) {

			$response = new WP_Error( 'invalid-date', __( 'Please enter a date in the future.', WC_Subscriptions::$text_domain ) );

		} elseif ( 0 != $trial_expiration_timestamp && $new_payment_timestamp <= $trial_expiration_timestamp ) {

			$response = new WP_Error( 'invalid-date', sprintf( __( 'Please enter a date after the trial period ends (%s).', WC_Subscriptions::$text_domain ), date_i18n( get_option( 'date_format' ), $trial_expiration_timestamp + ( get_option( 'gmt_offset' ) * 3600 ) ) ) );

		} elseif ( 0 != $expiration_timestamp && $new_payment_timestamp >= $expiration_timestamp ) {

			$response = new WP_Error( 'invalid-date', __( 'Please enter a date before the expiration date.', WC_Subscriptions::$text_domain ) );

		} elseif ( self::set_next_payment_date( $subscription_key, $user_id, $new_payment_timestamp ) ) {

			self::update_wp_cron_lock( $subscription_key, $new_payment_timestamp - gmdate( 'U' ), $user_id );

			$response = $new_payment_timestamp;

		} else {

			$response = new WP_Error( 'misc-error', __( 'The payment date could not be changed.', WC_Subscriptions::$text_domain ) );

		}

		return $response;
	}

	/*
	 * Helper Functions
	 */

	/**
	 * WP-Cron occasionally gets itself into an infinite loop on scheduled events, this function is 
	 * designed to create a non-cron related safeguard against payments getting caught up in such a loop.
	 *
	 * When the scheduled subscription payment hook is fired by WP-Cron, this function is attached before 
	 * any other to make sure the hook hasn't already fired for this period.
	 *
	 * A transient is used to keep a record of any payment for each period. The transient expiration is
	 * set to one billing period in the future, minus 1 hour, if there is a future payment due, otherwise,
	 * it is set to 23 hours in the future. This later option provides a safeguard in case a subscription's
	 * data is corrupted and the @see self::calculate_next_payment_date() is returning an
	 * invalid value. As no subscription can charge a payment more than once per day, the 23 hours is a safe
	 * throttle period for billing that still removes the possibility of a catastrophic failure (payments
	 * firing every few seconds until a credit card is maxed out).
	 *
	 * The transient keys use both the user ID and subscription key to ensure it is unique per subscription
	 * (even on multisite)
	 *
	 * @param $user_id int The id of the user who purchased the subscription
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.1.2
	 */
	public static function safeguard_scheduled_payments( $user_id, $subscription_key ) {
		global $wp_filter, $wpdb;

		$subscription = self::get_subscription( $subscription_key, $user_id );

		$lock_key = 'wcs_blocker_' . $user_id . '_' . $subscription_key;

		// Bypass options API
		$payments_blocked_until = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $lock_key ) );

		if ( $payments_blocked_until > gmdate( 'U' ) || in_array( $subscription['status'], array( 'suspended', 'on-hold', 'cancelled', 'expired', 'failed' ) ) ) {

			// Short-circuit for debugging
			if ( defined( 'WCS_DEBUG' ) && true === WCS_DEBUG )
				return;

			// Clear the schedule for this hook
			wp_clear_scheduled_hook( 'scheduled_subscription_payment', array( 'user_id' => (int)$user_id, 'subscription_key' => $subscription_key ) );

			// But now that we've cleared the schedule, make sure the next subscription payment is correctly scheduled
			self::maybe_reschedule_subscription_payment( $user_id, $subscription_key );

			// Make sure nothing else fires for this duplicated hook, except for this function: we can't use remove_all_actions here because we need to keep a record of the actions which were removed in case we need to add them for another hook in the same request
			foreach ( $wp_filter['scheduled_subscription_payment'] as $priority => $filters ) {
				foreach( $filters as $filter_id => $filter_details ) {
					if ( __CLASS__ . '::' . __FUNCTION__ == $filter_details['function'] )
						continue;

					self::$removed_filter_cache[] = array(
						'filter_id'     => $filter_id,
						'function'      => $filter_details['function'],
						'priority'      => $priority,
						'accepted_args' => $filter_details['accepted_args']
					);

					remove_action( 'scheduled_subscription_payment', $filter_details['function'], $priority, $filter_details['accepted_args'] );
				}
			}

		} else {

			$next_billing_timestamp = self::calculate_next_payment_date( $subscription_key, $user_id, 'timestamp', gmdate( 'Y-m-d H:i:s' ) );

			// If there are future payments, block until then, otherwise block for 23 hours
			$transient_timeout = ( $next_billing_timestamp > 0 ) ? $next_billing_timestamp - 60 * 60 - gmdate( 'U' ) : 60 * 60 * 23;

			self::update_wp_cron_lock( $subscription_key, $transient_timeout, $user_id );

			// If the payment hook is fired for more than one subscription in the same request, and the actions associated with the hook were removed because a prevous instance was a duplicate, re-add the actions for this instance of the hook
			if ( ! empty( self::$removed_filter_cache ) ) {
				foreach ( self::$removed_filter_cache as $key => $filter ) {
					add_action( 'scheduled_subscription_payment', $filter['function'], $filter['priority'], $filter['accepted_args'] );
					unset( self::$removed_filter_cache[$key] );
				}
			}
		}
	}

	/**
	 * When a scheduled subscription payment hook is fired, automatically process the subscription payment
	 * if the amount is for $0 (and therefore, there is no payment to be processed by a gateway, and likely
	 * no gateway used on the initial order).
	 *
	 * If a subscription has a $0 recurring total and is not already active (after being actived by something else
	 * handling the 'scheduled_subscription_payment' with the default priority of 10), then this function will call
	 * @see self::process_subscription_payment() to reactive the subscription, generate a renewal order etc.
	 *
	 * @param $user_id int The id of the user who the subscription belongs to
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.3.2
	 */
	public static function maybe_process_subscription_payment( $user_id, $subscription_key ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		$amount_to_charge = WC_Subscriptions_Order::get_recurring_total( $subscription['order_id'] );

		// Don't reschedule for cancelled, suspended or expired subscriptions
		if ( $amount_to_charge == 0 && ! in_array( $subscription['status'], array( 'expired', 'cancelled', 'active' ) ) ) {
			// Process payment, which will generate renewal orders, reactive the subscription etc.
			self::process_subscription_payment( $user_id, $subscription_key );
		}
	}

	/**
	 * When a subscription payment hook is fired, reschedule the hook to run again on the
	 * time/date of the next payment (if any).
	 * 
	 * WP-Cron's built in wp_schedule_event() function can not be used because the recurrence
	 * must be a timestamp, which creates inaccurate schedules for month and year billing periods.
	 * 
	 * @param $user_id int The id of the user who the subscription belongs to
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.1.5
	 */
	public static function maybe_reschedule_subscription_payment( $user_id, $subscription_key ) {

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		// Don't reschedule for cancelled, suspended or expired subscriptions
		if ( ! in_array( $subscription['status'], array( 'expired', 'cancelled', 'on-hold' ) ) ) {

			// Reschedule the 'scheduled_subscription_payment' hook
			if ( self::set_next_payment_date( $subscription_key, $user_id ) )
				do_action( 'rescheduled_subscription_payment', $user_id, $subscription_key );
		}
	}

	/**
	 * Because neither PHP nor WP include a real array merge function that works recursively.
	 *
	 * @since 1.0
	 */
	public static function array_merge_recursive_for_real( $first_array, $second_array ) {

		$merged = $first_array;

		if ( is_array( $second_array ) ) {
			foreach ( $second_array as $key => $val ) {
				if ( is_array( $second_array[$key] ) ) {
					$merged[$key] = ( isset( $merged[$key] ) && is_array( $merged[$key] ) ) ? self::array_merge_recursive_for_real( $merged[$key], $second_array[$key] ) : $second_array[$key];
				} else {
					$merged[$key] = $val;
				}
			}
		}

		return $merged;
	}

	/**
	 * Takes a total and calculates the recurring proportion of that based on $proportion and then fixes any rounding bugs to 
	 * make sure the totals add up.
	 *
	 * Used mainly to calculate the recurring amount from a total which may also include a sign up fee.
	 *
	 * @param float $total The total amount
	 * @since 1.2
	 * @return float $proportion A proportion of the total (e.g. 0.5 is half of the total)
	 */
	public static function get_amount_from_proportion( $total, $proportion ) {

		$sign_up_fee_proprotion = 1 - $proportion;

		$sign_up_total    = round( $total * $sign_up_fee_proprotion, 2 );
		$recurring_amount = round( $total * $proportion, 2 );

		// Handle any rounding bugs
		if ( $sign_up_total + $recurring_amount != $total )
			$recurring_amount = $recurring_amount - ( $sign_up_total + $recurring_amount - $total );

		return $recurring_amount;
	}

	/**
	 * Creates a subscription price string from an array of subscription details. For example, ""$5 / month for 12 months".
	 *
	 * @param array $subscription_details A set of name => value pairs for the subscription details to include in the string. Available keys:
	 *		'initial_amount': The upfront payment for the subscription, including sign up fees, as a string from the @see woocommerce_price(). Default empty string (no initial payment)
	 *		'initial_description': The word after the initial payment amount to describe the amount. Examples include "now" or "initial payment". Defaults to "up front".
	 *		'recurring_amount': The amount charged per period. Default 0 (no recurring payment).
	 *		'subscription_interval': How regularly the subscription payments are charged. Default 1, meaning each period e.g. per month.
	 *		'subscription_period': The temporal period of the subscription. Should be one of {day|week|month|year} as used by @see self::get_subscription_period_strings()
	 *		'subscription_length': The total number of periods the subscription should continue for. Default 0, meaning continue indefinitely.
	 *		'trial_length': The total number of periods the subscription trial period should continue for.  Default 0, meaning no trial period.
	 *		'trial_period': The temporal period for the subscription's trial period. Should be one of {day|week|month|year} as used by @see self::get_subscription_period_strings()
	 * @since 1.2
	 * @return float $proportion A proportion of the total (e.g. 0.5 is half of the total)
	 */
	public static function get_subscription_price_string( $subscription_details ) {

		$subscription_details = wp_parse_args( $subscription_details, array(
				'initial_amount'        => '',
				'initial_description'   => __( 'up front', WC_Subscriptions::$text_domain ),
				'recurring_amount'      => '',
				'subscription_interval' => 1,
				'subscription_period'   => '',
				'subscription_length'   => 0,
				'trial_length'          => 0,
				'trial_period'          => ''
			)
		);

		$subscription_details['subscription_period'] = strtolower( $subscription_details['subscription_period'] );

		// Make sure prices have been through woocommerce_price()
		$initial_amount_string   = ( is_numeric( $subscription_details['initial_amount'] ) ) ? woocommerce_price( $subscription_details['initial_amount'] ) : $subscription_details['initial_amount']; 
		$recurring_amount_string = ( is_numeric( $subscription_details['recurring_amount'] ) ) ? woocommerce_price( $subscription_details['recurring_amount'] ) : $subscription_details['recurring_amount']; 

		$subscription_period_string = self::get_subscription_period_strings( $subscription_details['subscription_interval'], $subscription_details['subscription_period'] );
		$subscription_ranges = self::get_subscription_ranges();

		if ( $subscription_details['subscription_length'] > 0 && $subscription_details['subscription_length'] == $subscription_details['subscription_interval'] )
			if ( ! empty( $subscription_details['initial_amount'] ) )
				$subscription_string = sprintf( __( '%s %s then %s', WC_Subscriptions::$text_domain ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string );
			else 
				$subscription_string = $recurring_amount_string;
		elseif ( ! empty( $subscription_details['initial_amount'] ) )
			$subscription_string = sprintf( _n( '%s %s then %s / %s', '%s %s then %s every %s', $subscription_details['subscription_interval'], WC_Subscriptions::$text_domain ), $initial_amount_string, $subscription_details['initial_description'], $recurring_amount_string, $subscription_period_string );
		elseif ( ! empty( $subscription_details['recurring_amount'] ) || intval( $subscription_details['recurring_amount'] ) === 0 )
			$subscription_string = sprintf( _n( '%s / %s', ' %s every %s', $subscription_details['subscription_interval'], WC_Subscriptions::$text_domain ), $recurring_amount_string, $subscription_period_string );
		else
			$subscription_string = '';

		if ( $subscription_details['subscription_length'] > 0 )
			$subscription_string = sprintf( __( '%s for %s', WC_Subscriptions::$text_domain ), $subscription_string, $subscription_ranges[$subscription_details['subscription_period']][$subscription_details['subscription_length']] );

		if ( $subscription_details['trial_length'] > 0 ) {
			$trial_length = self::get_subscription_trial_period_strings( $subscription_details['trial_length'], $subscription_details['trial_period'] );
			if ( ! empty( $subscription_details['initial_amount'] ) )
				$subscription_string = sprintf( __( '%s after %s free trial', WC_Subscriptions::$text_domain ), $subscription_string, $trial_length );
			else
				$subscription_string = sprintf( __( '%s free trial then %s', WC_Subscriptions::$text_domain ), ucfirst( $trial_length ), $subscription_string );
		}

		return apply_filters( 'woocommerce_subscription_price_string', $subscription_string, $subscription_details );
	}


	/**
	 * Copy of the WordPress "touch_time" template function for use with a variety of different times
	 *
	 * @param array $args A set of name => value pairs to customise how the function operates. Available keys:
	 *		'initial_amount': The upfront payment for the subscription, including sign up fees, as a string from the @see woocommerce_price(). Default empty string (no initial payment)
	 *		'initial_description': The word after the initial payment amount to description the amount. Examples include "now" or "initial payment". Defaults to "up front".
	 *		'recurring_amount': The amount charged per period. Default 0 (no recurring payment).
	 *		'subscription_interval': How regularly the subscription payments are charged. Default 1, meaning each period e.g. per month.
	 *		'subscription_period': The temporal period of the subscription. Should be one of {day|week|month|year} as used by @see self::get_subscription_period_strings()
	 *		'subscription_length': The total number of periods the subscription should continue for. Default 0, meaning continue indefinitely.
	 *		'trial_length': The total number of periods the subscription trial period should continue for.  Default 0, meaning no trial period.
	 *		'trial_period': The temporal period for the subscription's trial period. Should be one of {day|week|month|year} as used by @see self::get_subscription_period_strings()
	 * @since 1.2
	 */
	public static function touch_time( $args = array() ) {
		global $wp_locale;

		$args = wp_parse_args( $args, array(
				'date'            => true, 
				'is_edit'         => true, 
				'is_for_post'     => true, 
				'tab_index'       => 0, 
				'multiple'        => false, 
				'echo'            => true,
				'include_time'    => true,
				'include_buttons' => true
			) 
		);

		if ( empty( $args['date'] ) )
			return;

		$tab_index_attribute = ( (int) $args['tab_index'] > 0 ) ? ' tabindex="' . $args['tab_index'] . '"' : '';

		$time_offset = gmdate( 'U' ) + ( get_option( 'gmt_offset' ) * 3600 );

		$month = mysql2date( 'm', $args['date'], false );

		$month_input = '<select ' . ( $args['multiple'] ? '' : 'id="edit-month" ' ) . 'name="edit-month"' . $tab_index_attribute . '>';
		for ( $i = 1; $i < 13; $i = $i +1 ) {
			$month_numeral = zeroise( $i, 2 );
			$month_input .= '<option value="' . $month_numeral . '"';
			$month_input .= ( $i == $month ) ? ' selected="selected"' : '';
			/* translators: 1: month number (01, 02, etc.), 2: month abbreviation */
			$month_input .= '>' . sprintf( __( '%1$s-%2$s' ), $month_numeral, $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) ) . "</option>\n";
		}
		$month_input .= '</select>';

		$day_input  = '<input type="text" ' . ( $args['multiple'] ? '' : 'id="edit-day" ' ) . 'name="edit-day" value="' .  mysql2date( 'd', $args['date'], false ) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
		$year_input = '<input type="text" ' . ( $args['multiple'] ? '' : 'id="edit-year" ' ) . 'name="edit-year" value="' . mysql2date( 'Y', $args['date'], false ) . '" size="4" maxlength="4"' . $tab_index_attribute . ' autocomplete="off" />';

		if ( $args['include_time'] ) {

			$hour_input   = '<input type="text" ' . ( $args['multiple'] ? '' : 'id="edit-hour" ' ) . 'name="edit-hour" value="' . mysql2date( 'H', $args['date'], false ) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
			$minute_input = '<input type="text" ' . ( $args['multiple'] ? '' : 'id="edit-minute" ' ) . 'name="edit-minute" value="' . mysql2date( 'i', $args['date'], false ) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';

			/* translators: 1: month input, 2: day input, 3: year input, 4: hour input, 5: minute input */
			$touch_time = sprintf( __( '%1$s%2$s, %3$s @ %4$s : %5$s' ), $month_input, $day_input, $year_input, $hour_input, $minute_input );

		} else {
			/* translators: 1: month input, 2: day input, 3: year input */
			$touch_time = sprintf( __( '%1$s%2$s, %3$s' ), $month_input, $day_input, $year_input );
		}

		if ( $args['include_buttons'] ) {
			$touch_time .= '<p>';
			$touch_time .= '<a href="#edit_timestamp" class="save-timestamp hide-if-no-js button">' . __( 'Change', WC_Subscriptions::$text_domain ) . '</a>';
			$touch_time .= '<a href="#edit_timestamp" class="cancel-timestamp hide-if-no-js">' . __( 'Cancel', WC_Subscriptions::$text_domain ) . '</a>';
			$touch_time .= '</p>';
		}

		if ( $args['echo'] )
			echo $touch_time;

		return $touch_time;
	}

	/**
	 * If a gateway doesn't manage payment schedules, then we should suspend the subscription until it is paid (i.e. for manual payments
	 * or token gateways like Stripe). If the gateway does manage the scheduling, then we shouldn't suspend the subscription because a 
	 * gateway may use batch processing on the time payments are charged and a subscription could end up being incorrectly suspended.
	 *
	 * @param $user_id int The id of the user whose subscription should be put on-hold.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.2.5
	 */
	public static function maybe_put_subscription_on_hold( $user_id, $subscription_key ) {
		global $woocommerce;

		$subscription = self::get_users_subscription( $user_id, $subscription_key );

		if ( empty( $subscription ) || $subscription['status'] == 'on-hold' )
			return false;

		$order = new WC_Order( $subscription['order_id'] );

		$payment_gateways = $woocommerce->payment_gateways->payment_gateways();

		// If the gateway isn't active or it manages scheduled payments
		if ( ! isset( $payment_gateways[$order->payment_method] ) || ! $payment_gateways[$order->payment_method]->supports( 'gateway_scheduled_payments' ) )
			self::put_subscription_on_hold( $user_id, $subscription_key );
	}


	/* Deprecated Functions */

	/**
	 * @deprecated 1.1
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function can_subscription_be_cancelled( $subscription_key, $user_id = '' ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.1', __CLASS__ . '::can_subscription_be_changed_to( "cancelled", $subscription_key, $user_id )' );
		$subscription_can_be_cancelled = self::can_subscription_be_changed_to( 'cancelled', $subscription_key, $user_id );

		return apply_filters( 'woocommerce_subscription_can_be_cancelled', $subscription_can_be_cancelled, $subscription, $order );
	}

	/**
	 * @deprecated 1.1
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function get_users_cancellation_link( $subscription_key ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.1', __CLASS__ . '::get_users_cancellation_link( $subscription_key, "cancel" )' );
		return apply_filters( 'woocommerce_subscriptions_users_cancellation_link', self::get_users_change_status_link( $subscription_key, 'cancel' ), $subscription_key );
	}

	/**
	 * @deprecated 1.1
	 * @since 1.0
	 */
	public static function maybe_cancel_users_subscription() {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.1', __CLASS__ . '::maybe_change_users_subscription()' );
		self::maybe_change_users_subscription();
	}

	/**
	 * @deprecated 1.1
	 * @param $user_id int The ID of the user who owns the subscriptions.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @since 1.0
	 */
	public static function get_failed_payment_count( $user_id, $subscription_key ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.1', __CLASS__ . '::get_subscriptions_failed_payment_count( $subscription_key, $user_id )' );
		return self::get_subscriptions_failed_payment_count( $subscription_key, $user_id );
	}

	/**
	 * Deprecated in favour of a more correctly named @see maybe_reschedule_subscription_payment()
	 * 
	 * @deprecated 1.1.5
	 * @since 1.0
	 */
	public static function reschedule_subscription_payment( $user_id, $subscription_key ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.1.5', __CLASS__ . '::maybe_reschedule_subscription_payment( $user_id, $subscription_key )' );
		self::maybe_reschedule_subscription_payment( $user_id, $subscription_key );
	}


	/**
	 * Suspended a single subscription on a users account by placing it in the "suspended" status.
	 *
	 * Subscriptions version 1.2 replaced the "suspended" status with the "on-hold" status to match WooCommerce core.
	 *
	 * @param $user_id int The id of the user whose subscription should be suspended.
	 * @param $subscription_key string A subscription key of the form created by @see self::get_subscription_key()
	 * @deprecated 1.2
	 * @since 1.0
	 */
	public static function suspend_subscription( $user_id, $subscription_key ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.2', __CLASS__ . '::put_subscription_on_hold( $user_id, $subscription_key )' );
		self::put_subscription_on_hold( $user_id, $subscription_key );
	}


	/**
	 * Suspended all the subscription products in an order.
	 *
	 * Subscriptions version 1.2 replaced the "suspended" status with the "on-hold" status to match WooCommerce core.
	 *
	 * @param $order WC_Order|int The order or ID of the order for which subscriptions should be marked as activated.
	 * @deprecated 1.2
	 * @since 1.0
	 */
	public static function suspend_subscriptions_for_order( $order ) {
		_deprecated_function( __CLASS__ . '::' . __FUNCTION__, '1.2', __CLASS__ . '::put_subscription_on_hold_for_order( $order )' );
		self::put_subscription_on_hold_for_order( $order );
	}
}

WC_Subscriptions_Manager::init();
