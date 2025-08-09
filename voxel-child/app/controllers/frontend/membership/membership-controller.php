<?php

namespace Voxel\Controllers\Frontend\Membership;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Membership_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_plans.checkout.successful', '@checkout_successful' );
		$this->on( 'voxel/membership/pricing-plan-updated', '@unpublish_posts_over_the_limit', 10, 3 );
		$this->on( 'voxel/membership/pricing-plan-updated', '@trigger_app_event', 100, 3 );

		$this->on( 'voxel_ajax_stripe.customer.portal', '@access_customer_portal' );
	}

	protected function checkout_successful() {
		email_debug_data('checkout_successful');
		$session_id = $_GET['session_id'] ?? null;
		if ( ! ( $session_id && is_user_logged_in() ) ) {
			die;
		}

		$user = \Voxel\current_user();
		$last_session_id = get_user_meta( $user->get_id(), 'voxel:tmp_last_session_id', true );

		// update plan information in case webhook hasn't been triggered yet
		if ( wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'vx_pricing_checkout' ) && $last_session_id === $session_id ) {
			try {
				$stripe = \Voxel\Stripe::getClient();
				$membership = \Voxel\current_user()->get_membership();
				$session = \Voxel\Stripe::getClient()->checkout->sessions->retrieve( $session_id );

				if ( ( $session->mode ?? null ) === 'subscription' ) {
					$subscription = $stripe->subscriptions->retrieve( $session->subscription );
					if ( $subscription ) {
						do_action( 'voxel/membership/subscription-updated', $subscription );
					}
				}

				if ( ( $session->mode ?? null ) === 'payment' ) {
					$payment_intent = $stripe->paymentIntents->retrieve( $session->payment_intent );
					if ( $payment_intent ) {
						$payment_for = $payment_intent->metadata['voxel:payment_for'];
						if ( $payment_for === 'additional_submissions' ) {
							do_action( 'voxel/additional_submissions/payment_intent.succeeded', $payment_intent );
						} else {
							do_action( 'voxel/membership/payment_intent.succeeded', $payment_intent );
						}
					}
				}
				$plan = get_user_plan();
				$screen = 'frontend-checkout';
				//custom_initialize_or_update_user_meta($user->get_id(),$plan,$screen);
				email_debug_data('checkout_ try');
				delete_user_meta( $user->get_id(), 'voxel:tmp_last_session_id' );
			} catch ( \Exception $e ) {
				//
			}
		}

		$redirect_to = base64_decode( $_REQUEST['redirect_to'] ?? '' );

		wp_safe_redirect( $redirect_to ?: home_url( '/' ) );
		die;
	}

	protected function trigger_app_event( $user, $old_plan, $new_plan ) {
		// handle subscription events
		if ( $new_plan->get_type() === 'subscription' ) {
			// handle updates within the same subscription
			if ( $old_plan->get_type() === 'subscription' && $old_plan->get_subscription_id() === $new_plan->get_subscription_id() ) {

				// subscription was canceled
				if ( $old_plan->get_status() !== 'canceled' && $new_plan->get_status() === 'canceled' ) {
					( new \Voxel\Events\Membership\Plan_Canceled_Event )->dispatch( $user->get_id(), $old_plan );
				}

				// subscription was customized
				if ( $new_plan->is_active() && $new_plan->get_active_price_id() !== $old_plan->get_active_price_id() ) {
					( new \Voxel\Events\Membership\Plan_Switched_Event )->dispatch( $user->get_id(), $new_plan );
				}

				// subscription transitioned from incomplete to active
				if ( $old_plan->get_status() === 'incomplete' && $new_plan->is_active() ) {
					( new \Voxel\Events\Membership\Plan_Activated_Event )->dispatch( $user->get_id(), $new_plan );
				}

			// handle updates from default, payment, or different subscription
			} elseif ( $new_plan->is_active() ) {
				// switched from the default plan
				if ( $old_plan->get_type() === 'default' ) {
					( new \Voxel\Events\Membership\Plan_Activated_Event )->dispatch( $user->get_id(), $new_plan );
				}

				// switched from another premium plan
				if ( in_array( $old_plan->get_type(), [ 'payment', 'subscription' ], true ) ) {
					( new \Voxel\Events\Membership\Plan_Switched_Event )->dispatch( $user->get_id(), $new_plan );
				}
			}

		// handle one time payment events
		} elseif ( $new_plan->get_type() === 'payment' ) {
			if ( $old_plan->get_type() === 'payment' && $old_plan->get_payment_intent() === $new_plan->get_payment_intent() ) {
				// one time payment plan was customized
				// @todo

			} else {

				// switched from the default plan
				if ( $old_plan->get_type() === 'default' ) {
					( new \Voxel\Events\Membership\Plan_Activated_Event )->dispatch( $user->get_id(), $new_plan );
				}

				// switched from another premium plan
				if ( in_array( $old_plan->get_type(), [ 'payment', 'subscription' ], true ) ) {
					( new \Voxel\Events\Membership\Plan_Switched_Event )->dispatch( $user->get_id(), $new_plan );
				}
			}

		// handle switch to default plan
		} elseif ( $new_plan->get_type() === 'default' ) {
			// switched from a premium plan
			if ( in_array( $old_plan->get_type(), [ 'payment', 'subscription' ], true ) && $old_plan->is_active() ) {
				( new \Voxel\Events\Membership\Plan_Canceled_Event )->dispatch( $user->get_id(), $old_plan );
			}
		}
	}

	protected function unpublish_posts_over_the_limit( $user, $old_plan, $new_plan ) {
		// incomplete gives the customer 23 hours to complete payment, don't unpublish posts right away
		// @link https://stripe.com/docs/billing/subscriptions/overview#payment-window
		if ( $new_plan->get_type() === 'subscription' && $new_plan->get_status() === 'incomplete' ) {
			return;
		}

		\Voxel\unpublish_posts_over_the_limit_for_user( $user );
	}

	protected function access_customer_portal() {
		try {
			$stripe = \Voxel\Stripe::getClient();
			$session = $stripe->billingPortal->sessions->create( [
				'customer' => \Voxel\current_user()->get_stripe_customer_id(),
				'configuration' => \Voxel\Stripe::get_portal_configuration_id(),
				'return_url' => get_permalink( \Voxel\get( 'templates.current_plan' ) ) ?: home_url('/'),
			] );

			wp_redirect( $session->url );
			die;
		} catch ( \Exception $e ) {
			wp_die( $e->getMessage() );
		}
	}
}
