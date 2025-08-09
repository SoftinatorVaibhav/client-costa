<?php

namespace Voxel\Controllers\Frontend\Payments\Stripe;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Stripe_Payments_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_stripe_payments.checkout.success', '@checkout_success_endpoint' );
		$this->on( 'voxel_ajax_stripe_payments.checkout.cancel', '@checkout_cancel_endpoint' );

		// webhook handlers
		$this->on( 'voxel/stripe_payments/event:charge.captured', '@charge_updated', 10, 4 );
		$this->on( 'voxel/stripe_payments/event:charge.refunded', '@charge_updated', 10, 4 );
		$this->on( 'voxel/stripe_payments/event:charge.refund.updated', '@charge_refund_updated', 10, 4 );

		// checkout session events
		foreach ( [
			'checkout.session.completed',
			'checkout.session.async_payment_succeeded',
			'checkout.session.async_payment_failed',
		] as $event_type ) {
			$this->on( 'voxel/stripe_payments/event:'.$event_type, '@checkout_session_updated', 10, 4 );
			$this->on( 'voxel/stripe_payments/zero_amount/event:'.$event_type, '@zero_amount_checkout_session_updated', 10, 3 );
		}
	}

	protected function checkout_session_updated( $event, $session, $payment_intent, $order ) {
		$payment_method = $order->get_payment_method();
		$payment_method->payment_intent_updated( $payment_intent, $session );
	}

	protected function zero_amount_checkout_session_updated( $event, $session, $order ) {
		$payment_method = $order->get_payment_method();
		$payment_method->zero_amount_checkout_session_updated( $session );
	}

	protected function charge_updated( $event, $charge, $payment_intent, $order ) {
		$payment_method = $order->get_payment_method();
		$payment_method->payment_intent_updated( $payment_intent );
	}

	protected function charge_refund_updated( $event, $refund, $payment_intent, $order ) {
		$payment_method = $order->get_payment_method();
		$payment_method->payment_intent_updated( $payment_intent );
	}

	protected function checkout_success_endpoint() {
		$order_id = $_REQUEST['order_id'] ?? null;
		$session_id = $_REQUEST['session_id'] ?? null;
		if ( ! is_numeric( $order_id ) || ! is_string( $session_id ) || empty( $session_id ) ) {
			exit;
		}
		order_custom_status($order_id);
		$order = \Voxel\Product_Types\Orders\Order::find( [
			'id' => $order_id,
			'customer_id' => get_current_user_id(),
		] );

		if ( $order ) {
			
			$customer_id = get_current_user_id();
			//update_custom_user_meta_offer($customer_id);
			
			// clear customer cart on successful checkout
			if ( $order->get_details( 'cart.type' ) === 'customer_cart' ) {
				$cart = \Voxel\current_user()->get_cart();
				$cart->empty();
				$cart->update();
			}

			wp_safe_redirect( $order->get_link() );
			exit;
		}

		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	protected function checkout_cancel_endpoint() {
		$order_id = $_REQUEST['order_id'] ?? null;
		$session_id = $_REQUEST['session_id'] ?? null;
		if ( ! is_numeric( $order_id ) || ! is_string( $session_id ) || empty( $session_id ) ) {
			exit;
		}

		$order = \Voxel\Product_Types\Orders\Order::find( [
			'id' => $order_id,
			'customer_id' => get_current_user_id(),
		] );

		if ( $order && $order->get_details( 'checkout.session_id' ) === $session_id ) {
			$order->delete();
		}

		wp_safe_redirect( \Voxel\get_redirect_url() );
		exit;
	}
}
