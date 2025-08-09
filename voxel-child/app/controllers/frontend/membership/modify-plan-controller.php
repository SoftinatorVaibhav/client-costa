<?php

namespace Voxel\Controllers\Frontend\Membership;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Modify_Plan_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_plans.modify_plan', '@modify_plan' );
	}

	protected function modify_plan() {
		try {
			\Voxel\verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'vx_modify_plan' );

			if ( isset( $_REQUEST['price_key'] ) && $_REQUEST['price_key'] !== 'current' ) {
				$this->modify_new_price();
			} else {
				$this->modify_current_price();
			}
		} catch ( \Voxel\Vendor\Stripe\Exception\ApiErrorException | \Voxel\Vendor\Stripe\Exception\InvalidArgumentException $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => _x( 'Something went wrong', 'checkout', 'voxel' ),
				'debug' => [
					'type' => 'stripe_error',
					'code' => method_exists( $e, 'getStripeCode' ) ? $e->getStripeCode() : $e->getCode(),
					'message' => $e->getMessage(),
				],
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
			] );
		}
	}

	private function modify_current_price() {
		$user = \Voxel\current_user();
		$membership = $user->get_membership();
		$is_test_mode = \Voxel\Stripe::is_test_mode();
		$customer = $user->get_or_create_stripe_customer();
		$redirect_to = get_permalink( \Voxel\get( 'templates.current_plan' ) ) ?: home_url('/');
		if ( ! empty( $_REQUEST['redirect_to'] ) ) {
			$redirect_to = wp_validate_redirect( $_REQUEST['redirect_to'], $redirect_to );
		}

		if ( ! $membership->is_active() ) {
			throw new \Exception( _x( 'Cannot modify current plan.', 'pricing plans', 'voxel' ), 148 );
		}

		if ( ! in_array( $membership->get_type(), [ 'payment', 'subscription' ], true ) ) {
			throw new \Exception( _x( 'Cannot modify current plan.', 'pricing plans', 'voxel' ), 149 );
		}

		$plan = $membership->plan;
		$price = new \Voxel\Plan_Price( [
			'id' => $membership->get_price_id(),
			'mode' => \Voxel\Stripe::is_test_mode() ? 'test' : 'live',
			'plan' => $plan->get_key(),
		] );

		$switch_role = $this->_get_switch_role( $plan );

		$checkout_details = $this->_get_checkout_details( $price );
		$additions_price = $checkout_details['price'];
		$added_limits = $checkout_details['limits'];

		if ( empty( $added_limits ) ) {
			throw new \Exception( _x( 'Cannot modify current plan.', 'pricing plans', 'voxel' ), 150 );
		}

		if ( $additions_price <= 0 ) {
			throw new \Exception( _x( 'Cannot modify current plan.', 'pricing plans', 'voxel' ), 151 );
		}

		if ( $membership->get_type() === 'subscription' ) {
			$subscription = \Voxel\Vendor\Stripe\Subscription::retrieve( $membership->get_subscription_id() );
			$stored_limits = json_decode( $subscription->metadata['voxel:limits'] ?? '', true );
			if ( ! is_array( $stored_limits ) ) {
				$stored_limits = [];
			}

			foreach ( $stored_limits as $post_type_key => $post_type_limit ) {
				if ( is_numeric( $post_type_limit ) && $post_type_limit > 0 ) {
					$stored_limits[ $post_type_key ] = absint( $post_type_limit );
				} else {
					unset( $stored_limits[ $post_type_key ] );
				}
			}

			foreach ( $added_limits as $post_type_key => $post_type_limit ) {
				if ( ! isset( $stored_limits[ $post_type_key ] ) ) {
					$stored_limits[ $post_type_key ] = 0;
				}

				$stored_limits[ $post_type_key ] += $post_type_limit;
			}

			$args = [
				'items' => [ [
					'id' => $subscription->items->data[0]->id,
					'quantity' => 1,
					'price_data' => [
						'currency' => $membership->get_currency(),
						'unit_amount' => $membership->get_amount() + $additions_price,
						'recurring' => [
							'interval' => $membership->get_interval(),
							'interval_count' => $membership->get_interval_count(),
						],
						'product' => $plan->get_pricing()[ $is_test_mode ? 'test' : 'live' ]['product_id'] ?? null,
					],
				] ],
				'metadata' => [
					'voxel:payment_for' => 'membership',
					'voxel:plan' => $plan->get_key(),
					'voxel:limits' => wp_json_encode( $stored_limits ),
					'voxel:original_price_id' => $price->get_id(),
				],
				'payment_behavior' => apply_filters( 'voxel/update-subscription/payment-behavior', 'allow_incomplete' ),
				'proration_behavior' => \Voxel\get( 'settings.membership.update.proration_behavior', 'always_invoice' ),
			];

			$args = \Voxel\Membership\Tax_Details::apply_to_subscription_upgrade( $args );
			$updatedSubscription = \Voxel\Vendor\Stripe\Subscription::update( $subscription->id, $args );

			do_action( 'voxel/membership/subscription-updated', $updatedSubscription );

			// checkout is complete, update role (if requested)
			if ( $switch_role !== null ) {
				$user->set_role( $switch_role->get_key() );
			}

			return wp_send_json( [
				'success' => true,
				'redirect_to' => $redirect_to,
			] );
		} else {
			$args = [
				'customer' => $customer->id,
				'mode' => 'payment',
				'line_items' => [ [
					'quantity' => 1,
					'price_data' => [
						'currency' => $membership->get_currency(),
						'unit_amount' => $additions_price,
						'product' => $plan->get_pricing()[ $is_test_mode ? 'test' : 'live' ]['product_id'] ?? null,
					],
				] ],
				'success_url' => add_query_arg( [
					'action' => 'plans.checkout.successful',
					'session_id' => '{CHECKOUT_SESSION_ID}',
					'_wpnonce' => wp_create_nonce('vx_pricing_checkout'),
					'redirect_to' => base64_encode( $redirect_to ),
				], home_url('/?vx=1') ),
				'cancel_url' => add_query_arg( 'canceled', 1, get_permalink( \Voxel\get( 'templates.current_plan' ) ) ?: home_url('/') ),
				'payment_intent_data' => [
					'metadata' => [
						'voxel:payment_for' => 'additional_submissions',
						'voxel:limits' => wp_json_encode( $added_limits ),
					],
				],
				'allow_promotion_codes' => !! \Voxel\get( 'settings.membership.checkout.promotion_codes.enabled' ),
				'customer_update' => [
					'address' => 'auto',
					'name' => 'auto',
				],
			];

			// if role switch is requested, include it in payment metadata so it can be
			// processed when checkout is completed
			if ( $switch_role !== null ) {
				$args['payment_intent_data']['metadata']['voxel:switch_role'] = $switch_role->get_key();
			}

			$args = \Voxel\Membership\Tax_Details::apply_to_checkout_session( $args );
			$session = \Voxel\Vendor\Stripe\Checkout\Session::create( $args );
			update_user_meta( $user->get_id(), 'voxel:tmp_last_session_id', $session->id );

			return wp_send_json( [
				'success' => true,
				'redirect_to' => $session->url,
			] );
		}
	}

	private function modify_new_price() {
		$user = \Voxel\current_user();
		$membership = $user->get_membership();
		$is_test_mode = \Voxel\Stripe::is_test_mode();
		$customer = $user->get_or_create_stripe_customer();
		$redirect_to = get_permalink( \Voxel\get( 'templates.current_plan' ) ) ?: home_url('/');
		if ( ! empty( $_REQUEST['redirect_to'] ) ) {
			$redirect_to = wp_validate_redirect( $_REQUEST['redirect_to'], $redirect_to );
		}

		// handle switch to default price
		if ( $_REQUEST['price_key'] === 'default' ) {
			$plan = \Voxel\Plan::get_or_create_default_plan();
			$switch_role = $this->_get_switch_role( $plan );

			// if role-switch is not requested, check if user has at least one role that supports chosen plan
			if ( $switch_role === null ) {
				if ( ! $plan->supports_user( $user ) ) {
					throw new \Exception( _x( 'This plan is not supported by your current role.', 'roles', 'voxel' ), 110 );
				}
			}

			if ( $membership->get_type() === 'subscription' && $membership->is_active() ) {
				\Voxel\Stripe::getClient()->subscriptions->cancel( $membership->get_subscription_id() );
			}

			$meta_key = \Voxel\Stripe::is_test_mode() ? 'voxel:test_plan' : 'voxel:plan';
			update_user_meta( $user->get_id(), $meta_key, wp_slash( wp_json_encode( [
				'plan' => 'default',
				'created' => \Voxel\utc()->format( 'Y-m-d H:i:s' ),
			] ) ) );

			do_action( 'voxel/membership/pricing-plan-updated', $user, $user->get_membership(), $user->get_membership( $refresh_cache = true ) );

			// checkout is complete, update role (if requested)
			if ( $switch_role !== null ) {
				$user->set_role( $switch_role->get_key() );
			}

			return wp_send_json( [
				'success' => true,
				'redirect_to' => $redirect_to,
			] );
		} else {
			$price = \Voxel\Plan_Price::from_key( sanitize_text_field( $_REQUEST['price_key'] ) );
			if ( ! $price->is_enabled() ) {
				throw new \Exception( 'This plan is not active.' );
			}

			if ( ! in_array( $price->get_type(), [ 'one_time', 'recurring' ], true ) ) {
				throw new \Exception( 'Cannot modify this plan.' );
			}

			$plan = $price->plan;

			$switch_role = $this->_get_switch_role( $plan );

			// if role-switch is not requested, check if user has at least one role that supports chosen plan
			if ( $switch_role === null ) {
				if ( ! $plan->supports_user( $user ) ) {
					throw new \Exception( _x( 'This plan is not supported by your current role.', 'roles', 'voxel' ), 110 );
				}
			}

			$checkout_details = $this->_get_checkout_details( $price );
			$additions_price = $checkout_details['price'];
			$added_limits = $checkout_details['limits'];

			if ( $price->get_type() === 'recurring' ) {
				$details = $price->get_details();

				if ( $membership->get_type() === 'subscription' && $membership->is_active() ) {
					$subscription = \Voxel\Vendor\Stripe\Subscription::retrieve( $membership->get_subscription_id() );

					$args = [
						'items' => [ [
							'id' => $subscription->items->data[0]->id,
							'quantity' => 1,
							'price_data' => [
								'currency' => $price->get_currency(),
								'unit_amount' => $price->get_amount() + $additions_price,
								'recurring' => [
									'interval' => $details['recurring']['interval'] ?? '',
									'interval_count' => $details['recurring']['interval_count'] ?? '',
								],
								'product' => $plan->get_pricing()[ $is_test_mode ? 'test' : 'live' ]['product_id'] ?? null,
							],
						] ],
						'metadata' => [
							'voxel:payment_for' => 'membership',
							'voxel:plan' => $plan->get_key(),
							'voxel:limits' => wp_json_encode( $added_limits ),
							'voxel:original_price_id' => $price->get_id(),
						],
						'payment_behavior' => apply_filters( 'voxel/update-subscription/payment-behavior', 'allow_incomplete' ),
						'proration_behavior' => \Voxel\get( 'settings.membership.update.proration_behavior', 'always_invoice' ),
					];

					$args = \Voxel\Membership\Tax_Details::apply_to_subscription_upgrade( $args );
					$updatedSubscription = \Voxel\Vendor\Stripe\Subscription::update( $subscription->id, $args );

					do_action( 'voxel/membership/subscription-updated', $updatedSubscription );

					// checkout is complete, update role (if requested)
					if ( $switch_role !== null ) {
						$user->set_role( $switch_role->get_key() );
					}

					return wp_send_json( [
						'success' => true,
						'redirect_to' => $redirect_to,
					] );
				} else {
					$args = [
						'customer' => $customer->id,
						'mode' => 'subscription',
						'line_items' => [ [
							'quantity' => 1,
							'price_data' => [
								'currency' => $price->get_currency(),
								'unit_amount' => $price->get_amount() + $additions_price,
								'recurring' => [
									'interval' => $details['recurring']['interval'] ?? '',
									'interval_count' => $details['recurring']['interval_count'] ?? '',
								],
								'product' => $plan->get_pricing()[ $is_test_mode ? 'test' : 'live' ]['product_id'] ?? null,
							],
						] ],
						'success_url' => add_query_arg( [
							'action' => 'plans.checkout.successful',
							'session_id' => '{CHECKOUT_SESSION_ID}',
							'_wpnonce' => wp_create_nonce('vx_pricing_checkout'),
							'redirect_to' => base64_encode( $redirect_to ),
						], home_url('/?vx=1') ),
						'cancel_url' => add_query_arg( 'canceled', 1, get_permalink( \Voxel\get( 'templates.current_plan' ) ) ?: home_url('/') ),
						'subscription_data' => [
							'payment_behavior' => apply_filters( 'voxel/create-subscription/payment-behavior', 'allow_incomplete' ),
							'metadata' => [
								'voxel:payment_for' => 'membership',
								'voxel:plan' => $plan->get_key(),
								'voxel:limits' => wp_json_encode( $added_limits ),
								'voxel:original_price_id' => $price->get_id(),
							],
						],
						'allow_promotion_codes' => !! \Voxel\get( 'settings.membership.checkout.promotion_codes.enabled' ),
						'customer_update' => [
							'address' => 'auto',
							'name' => 'auto',
						],
					];

					// if role switch is requested, include it in subscription metadata so it can be
					// processed when checkout is completed and subscription gets activated
					if ( $switch_role !== null ) {
						$args['subscription_data']['metadata']['voxel:switch_role'] = $switch_role->get_key();
					}

					$args = \Voxel\Membership\Tax_Details::apply_to_checkout_session( $args );
					$session = \Voxel\Vendor\Stripe\Checkout\Session::create( $args );
					update_user_meta( $user->get_id(), 'voxel:tmp_last_session_id', $session->id );

					return wp_send_json( [
						'success' => true,
						'redirect_to' => $session->url,
					] );
				}
			} elseif ( $price->get_type() === 'one_time' ) {
				$args = [
					'customer' => $customer->id,
					'mode' => 'payment',
					'line_items' => [ [
						'quantity' => 1,
						'price_data' => [
							'currency' => $price->get_currency(),
							'unit_amount' => $price->get_amount() + $additions_price,
							'product' => $plan->get_pricing()[ $is_test_mode ? 'test' : 'live' ]['product_id'] ?? null,
						],
					] ],
					'success_url' => add_query_arg( [
						'action' => 'plans.checkout.successful',
						'session_id' => '{CHECKOUT_SESSION_ID}',
						'_wpnonce' => wp_create_nonce('vx_pricing_checkout'),
						'redirect_to' => base64_encode( $redirect_to ),
					], home_url('/?vx=1') ),
					'cancel_url' => add_query_arg( 'canceled', 1, get_permalink( \Voxel\get( 'templates.current_plan' ) ) ?: home_url('/') ),
					'payment_intent_data' => [
						'metadata' => [
							'voxel:payment_for' => 'membership',
							'voxel:plan' => $plan->get_key(),
							'voxel:limits' => wp_json_encode( $added_limits ),
							'voxel:original_price_id' => $price->get_id(),
						],
					],
					'allow_promotion_codes' => !! \Voxel\get( 'settings.membership.checkout.promotion_codes.enabled' ),
					'customer_update' => [
						'address' => 'auto',
						'name' => 'auto',
					],
				];

				// if role switch is requested, include it in payment metadata so it can be
				// processed when checkout is completed
				if ( $switch_role !== null ) {
					$args['payment_intent_data']['metadata']['voxel:switch_role'] = $switch_role->get_key();
				}

				$args = \Voxel\Membership\Tax_Details::apply_to_checkout_session( $args );
				$session = \Voxel\Vendor\Stripe\Checkout\Session::create( $args );
				update_user_meta( $user->get_id(), 'voxel:tmp_last_session_id', $session->id );

				return wp_send_json( [
					'success' => true,
					'redirect_to' => $session->url,
				] );
			}
		}
	}

	private function _get_checkout_details( $price ) {
		$additions_price = 0;
		$added_limits = [];
		$additions = json_decode( stripslashes( $_REQUEST['additions'] ?? '' ), true );
		foreach ( (array) $additions as $post_type_key => $additional_limit ) {
			$additional_limit = absint( $additional_limit );
			if ( $additional_limit < 1 ) {
				continue;
			}

			if ( $price->supports_addition( $post_type_key ) ) {
				$additions_price += ( $additional_limit * $price->get_price_per_addition( $post_type_key ) );
				$added_limits[ $post_type_key ] = $additional_limit;
			}
		}

		return [
			'price' => $additions_price,
			'limits' => $added_limits,
		];
	}

	/**
	 * Handle requests to switch user role upon activating a plan.
	 */
	private function _get_switch_role( $plan ) {
		$user = \Voxel\current_user();
		$switch_role = null;
		if ( ! empty( $_REQUEST['switch_to_role'] ) ) {
			$switch_role = \Voxel\Role::get( $_REQUEST['switch_to_role'] );

			if ( ! $switch_role ) {
				throw new \Exception( __( 'Invalid request.', 'voxel' ), 100 );
			}

			if ( ! $switch_role->is_switching_enabled() ) {
				throw new \Exception( __( 'Invalid request.', 'voxel' ), 101 );
			}

			$switchable_roles = $user->get_switchable_roles();
			if ( ! isset( $switchable_roles[ $switch_role->get_key() ] ) ) {
				throw new \Exception( __( 'Invalid request.', 'voxel' ), 102 );
			}

			if ( ! $plan->supports_role( $switch_role->get_key() ) ) {
				throw new \Exception( __( 'Invalid request.', 'voxel' ), 103 );
			}

			if ( $user->has_role( 'administrator' ) || $user->has_role( 'editor' ) ) {
				throw new \Exception( _x( 'Switching roles is not allowed for Administrator and Editor accounts.', 'roles', 'voxel' ), 102 );
			}

			// if user already has this role, process checkout without the role-switch request
			if ( $user->has_role( $switch_role->get_key() ) ) {
				$switch_role = null;
			}
		}

		return $switch_role;
	}
}
