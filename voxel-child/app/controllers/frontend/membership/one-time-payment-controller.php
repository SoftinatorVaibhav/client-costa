<?php

namespace Voxel\Controllers\Frontend\Membership;

if ( ! defined('ABSPATH') ) {
	exit;
}

class One_Time_Payment_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel/membership/payment_intent.succeeded', '@one_time_payment_succeeded' );
		$this->on( 'voxel/additional_submissions/payment_intent.succeeded', '@additional_submissions_payment_succeeded' );
	}

	/**
	 * User has purchased a one-time payment plan. When this point is reached,
	 * payment has been confirmed so we can update the plan details in the database.
	 *
	 * If user switched to this plan from an active subscription plan they previously
	 * bought, that subscription can be safely canceled at this point.
	 *
	 * @since 1.0
	 */
	protected function one_time_payment_succeeded( $payment_intent ) {
		$plan_key = $payment_intent->metadata['voxel:plan'];
		$plan = \Voxel\Plan::get( $plan_key );
		if ( ! $plan ) {
			throw new \Exception( sprintf( 'Plan "%s" not found for payment_intent "%s"', $plan_key, $payment_intent->id ) );
		}

		$user = \Voxel\User::get_by_customer_id( $payment_intent->customer );
		if ( ! $user ) {
			throw new \Exception( sprintf( 'Customer ID "%s" does not belong to any registered user (payment_intent "%s")', $payment_intent->customer, $payment_intent->id ) );
		}

		// cancel existing subscription (if any)
		$membership = $user->get_membership();
		if ( $membership->get_type() === 'subscription' && $membership->is_active() ) {
			\Voxel\Stripe::getClient()->subscriptions->cancel( $membership->get_subscription_id() );
		}

		$meta_key = \Voxel\Stripe::is_test_mode() ? 'voxel:test_plan' : 'voxel:plan';
		update_user_meta( $user->get_id(), $meta_key, wp_slash( wp_json_encode( [
			'plan' => $plan->get_key(),
			'type' => 'payment',
			'payment_intent' => $payment_intent->id,
			'amount' => $payment_intent->amount,
			'currency' => $payment_intent->currency,
			'status' => $payment_intent->status,
			'price_id' => $payment_intent->metadata['voxel:price_id'] ?? null,
			'created' => date( 'Y-m-d H:i:s', $payment_intent->created ),
			'metadata' => $payment_intent->metadata,
		] ) ) );

		do_action(
			'voxel/membership/pricing-plan-updated',
			$user,
			$user->get_membership(),
			$user->get_membership( $refresh_cache = true )
		);

		// the payment is complete, we can safely process "voxel:switch_role" if requested
		if ( ! empty( $payment_intent->metadata['voxel:switch_role'] ) ) {
			$this->_maybe_switch_role( $user, $payment_intent->metadata['voxel:switch_role'] );
		}
	}

	/**
	 * User has purchased additional post submission limits for their one-time payment plan.
	 *
	 * When this point is reached, the payment has been confirmed, in which case we store
	 * their new limits in the user plan details, under "additional_submissions".
	 *
	 * Each set of additional purchased submissions is keyed using the payment intent id
	 * of that purchase.
	 *
	 * @since 1.2
	 */
	protected function additional_submissions_payment_succeeded( $payment_intent ) {
		$user = \Voxel\User::get_by_customer_id( $payment_intent->customer );
		if ( ! $user ) {
			throw new \Exception( sprintf( 'Customer ID "%s" does not belong to any registered user (payment_intent "%s")', $payment_intent->customer, $payment_intent->id ) );
		}

		$limits = json_decode( $payment_intent->metadata['voxel:limits'] ?? '', true );
		if ( ! is_array( $limits ) || empty( $limits ) ) {
			return;
		}

		$meta_key = \Voxel\Stripe::is_test_mode() ? 'voxel:test_plan' : 'voxel:plan';
		$details = (array) json_decode( get_user_meta( $user->get_id(), $meta_key, true ), ARRAY_A );
		if ( ! isset( $details['additional_submissions'] ) ) {
			$details['additional_submissions'] = [];
		}

		$details['additional_submissions'][ $payment_intent->id ] = $limits;
		update_user_meta( $user->get_id(), $meta_key, wp_slash( wp_json_encode( $details ) ) );

		do_action(
			'voxel/membership/pricing-plan-updated',
			$user,
			$user->get_membership(),
			$user->get_membership( $refresh_cache = true )
		);

		// the payment is complete, we can safely process "voxel:switch_role" if requested
		if ( ! empty( $payment_intent->metadata['voxel:switch_role'] ) ) {
			$this->_maybe_switch_role( $user, $payment_intent->metadata['voxel:switch_role'] );
		}
	}

	/**
	 * Handle requests to switch user role upon successful payment.
	 *
	 * @since 1.2
	 */
	protected function _maybe_switch_role( $user, $new_role_key ) {
		$switch_role = \Voxel\Role::get( $new_role_key );
		if ( ! $switch_role ) {
			return;
		}

		if ( ! $switch_role->is_switching_enabled() ) {
			return;
		}

		$switchable_roles = $user->get_switchable_roles();
		if ( ! isset( $switchable_roles[ $switch_role->get_key() ] ) ) {
			return;
		}

		$membership = $user->get_membership();
		if ( ! $membership->plan->supports_role( $switch_role->get_key() ) ) {
			return;
		}

		if ( $user->has_role( 'administrator' ) || $user->has_role( 'editor' ) ) {
			return;
		}

		// if user already has this role, process checkout without the role-switch request
		if ( $user->has_role( $switch_role->get_key() ) ) {
			return;
		}

		$user->set_role( $switch_role->get_key() );
	}
}
