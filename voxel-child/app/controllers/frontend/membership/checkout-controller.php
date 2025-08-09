<?php

namespace Voxel\Controllers\Frontend\Membership;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Checkout_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_plans.choose_plan', '@choose_plan' );
	}

	protected function choose_plan() {
		try {
			\Voxel\verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'vx_choose_plan' );

			$stripe = \Voxel\Stripe::getClient();
			$user = \Voxel\current_user();
			$role = \Voxel\Role::get( $user->get_role_keys()[0] ?? null );
			$membership = $user->get_membership();
			$customer = $user->get_or_create_stripe_customer();

			if ( ( $_GET['plan'] ?? '' ) === 'default' ) {
				$plan = \Voxel\Plan::get_or_create_default_plan();
				$price = null;
			} else {
				$price = \Voxel\Plan_Price::from_key( sanitize_text_field( $_GET['plan'] ?? '' ) );
				$plan = $price->plan;
			}

			if ( $plan->is_archived() ) {
				throw new \Exception( _x( 'This plan is no longer available.', 'pricing plans', 'voxel' ) );
			}

			/**
			 * Handle requests to switch user role upon activating a plan.
			 */
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

			// if role-switch is not requested, check if user has at least one role that supports chosen plan
			if ( $switch_role === null ) {
				if ( ! $plan->supports_user( $user ) ) {
					throw new \Exception( _x( 'This plan is not supported by your current role.', 'roles', 'voxel' ), 110 );
				}
			}

			// determine redirect url
			$requested_redirect = wp_validate_redirect( $_REQUEST['redirect_to'] ?? '' );
			if ( ! empty( $_REQUEST['redirect_to'] ) && $requested_redirect ) {
				if ( $role && ( $_REQUEST['context'] ?? null ) === 'signup' ) {
					$after_registration = $role->config( 'registration.after_registration', 'welcome_step' );
					if ( $after_registration === 'welcome_step' ) {
						$redirect_to = add_query_arg( [
							'welcome' => '',
							'redirect_to' => $requested_redirect,
						], get_permalink( \Voxel\get( 'templates.auth' ) ) ?: home_url('/') );
					} elseif ( $after_registration === 'custom_redirect' ) {
						$redirect_to = \Voxel\render( $role->config( 'registration.custom_redirect', '' ), [
							'site' => [ 'type' => \Voxel\Dynamic_Tags\Site_Group::class ],
							'user' => [ 'type' => \Voxel\Dynamic_Tags\User_Group::class ],
						] );
					} else {
						$redirect_to = $requested_redirect ?: home_url('/');
					}
				} else {
					$redirect_to = $requested_redirect ?: home_url('/');
				}
			} else {
				$redirect_to = get_permalink( \Voxel\get( 'templates.current_plan' ) ) ?: home_url('/');
			}

			/**
			 * Handle switching to the default plan. Cancel any active subscriptions.
			 */
			if ( $plan->get_key() === 'default' ) {

				// show configuration screen if this upgrade causes any unpublished posts
				if ( $plan->will_cause_unpublished_posts_on_upgrade( $user ) ) {
					return wp_send_json( [
						'success' => true,
						'redirect_to' => add_query_arg( [
							'price' => 'default',
							'switch_to_role' => $switch_role ? $switch_role->get_key() : null,
							'redirect_to' => $redirect_to,
						], get_permalink( \Voxel\get( 'templates.configure_plan' ) ) ?: home_url('/') ),
					] );
				}

				$membership = $user->get_membership();
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
				/**
				 * Handle switching to a paid membership plan.
				 */

				if ( ! $price->is_enabled() ) {
					throw new \Exception( _x( 'Price is not available.', 'pricing plans', 'voxel' ) );
				}

				// show configuration screen if this upgrade causes any unpublished posts
				if ( $plan->will_cause_unpublished_posts_on_upgrade( $user ) ) {
					return wp_send_json( [
						'success' => true,
						'redirect_to' => add_query_arg( [
							'price' => $price->to_key(),
							'switch_to_role' => $switch_role ? $switch_role->get_key() : null,
							'redirect_to' => $redirect_to,
						], get_permalink( \Voxel\get( 'templates.configure_plan' ) ) ?: home_url('/') ),
					] );
				}

				$payment_mode = $price->get_type() === 'recurring' ? 'subscription' : 'payment';

				/**
				 * User is switching from one subscription to another. Automatically upgrade and
				 * prorate, skipping checkout altogether.
				 */
				if ( $membership->get_type() === 'subscription' && $payment_mode === 'subscription' && $membership->is_switchable() ) {
					if ( $membership->get_price_id() === $price->get_id() ) {
						throw new \Exception( _x( 'You are already on this plan.', 'pricing plans', 'voxel' ) );
					}

					$subscription = \Voxel\Vendor\Stripe\Subscription::retrieve( $membership->get_subscription_id() );

					$args = [
						'items' => [ [
							'id' => $subscription->items->data[0]->id,
							'price' => $price->get_id(),
							'quantity' => 1,
						] ],
						'metadata' => [
							'voxel:payment_for' => 'membership',
							'voxel:plan' => $plan->get_key(),
							'voxel:original_price_id' => '',
							'voxel:limits' => '',
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
					/**
					 * User is activating a subscription for the first time, proceed to checkout.
					 */
					if ( $payment_mode === 'subscription' ) {
						$trial_enabled = \Voxel\get( 'settings.membership.trial.enabled', false );
						$trial_days = absint( \Voxel\get( 'settings.membership.trial.period_days', 0 ) );

						// only allow free trial on first plan sign-up
						$trial_allowed = ! metadata_exists( 'user', $user->get_id(), \Voxel\Stripe::is_test_mode() ? 'voxel:test_plan' : 'voxel:plan' );

						$args = [
							'customer' => $customer->id,
							'mode' => $payment_mode,
							'line_items' => [ [
								'price' => $price->get_id(),
								'quantity' => 1,
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
								'trial_period_days' => ( $trial_allowed && $trial_enabled && $trial_days ) ? $trial_days : null,
								'metadata' => [
									'voxel:payment_for' => 'membership',
									'voxel:plan' => $plan->get_key(),
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
					} else {
						/**
						 * One time payment plan with amount set to 0 (free).
						 * Cancel any existing subscriptions, skip checkout, and apply plan right away.
						 */
						if ( floatval( $price->get_amount() ) === floatval(0) ) {
							$membership = $user->get_membership();
							if ( $membership->get_type() === 'subscription' && $membership->is_active() ) {
								\Voxel\Stripe::getClient()->subscriptions->cancel( $membership->get_subscription_id() );
							}

							$meta_key = \Voxel\Stripe::is_test_mode() ? 'voxel:test_plan' : 'voxel:plan';
							update_user_meta( $user->get_id(), $meta_key, wp_slash( wp_json_encode( [
								'plan' => $plan->get_key(),
								'type' => 'payment',
								'amount' => $price->get_amount(),
								'currency' => $price->get_currency(),
								'status' => 'succeeded',
								'price_id' => $price->get_id(),
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
							/**
							 * One time payment plan with price greater than zero.
							 * Proceed to checkout.
							 */
							$args = [
								'customer' => $customer->id,
								'mode' => $payment_mode,
								'line_items' => [ [
									'price' => $price->get_id(),
									'quantity' => 1,
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
										'voxel:price_id' => $price->get_id(),
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
			}
		} catch ( \Voxel\Vendor\Stripe\Exception\ApiErrorException | \Voxel\Vendor\Stripe\Exception\InvalidArgumentException $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => __( 'An error occurred.', 'voxel' ),
				'stripe_error' => $e->getMessage(),
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
			] );
		}
	}
}
