<?php

namespace Voxel\Controllers\Membership;

use Voxel\Utils\Config_Schema\Schema as Schema;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Membership_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'admin_menu', '@add_menu_page', 10 );
		$this->on( 'voxel_ajax_membership.update_customer_plan', '@update_customer_plan' );
	}

	protected function add_menu_page() {
		add_menu_page(
			__( 'Membership', 'voxel-backend' ),
			__( 'Membership', 'voxel-backend' ),
			'manage_options',
			'voxel-membership',
			function() {
				$action = sanitize_text_field( $_GET['action'] ?? 'manage-types' );

				if ( $action === 'create-plan' ) {
					require locate_template( 'templates/backend/membership/create-plan.php' );
				} elseif ( $action === 'edit-plan' ) {
					$plan = \Voxel\Plan::get( $_GET['plan'] ?? '' );
					if ( ! $plan ) {
						return;
					}

					$post_types = [];
					foreach ( \Voxel\Post_Type::get_voxel_types() as $post_type ) {
						$post_types[ $post_type->get_key() ] = [
							'key' => $post_type->get_key(),
							'label' => $post_type->get_label(),
							'submittable' => ! in_array( $post_type->get_key(), [ 'profile' ], true ),
						];
					}

					$config = [
						'plan' => $plan->get_editor_config(),
						'postTypes' => $post_types,
					];

					wp_enqueue_script( 'vx:plan-editor.js' );
					require locate_template( 'templates/backend/membership/edit-plan.php' );
				} else {
					$default_plan = \Voxel\Plan::get_or_create_default_plan();
					$active_plans = \Voxel\Plan::active();
					$archived_plans = \Voxel\Plan::archived();
					$add_plan_url = admin_url('admin.php?page=voxel-membership&action=create-plan');

					require locate_template( 'templates/backend/membership/view-plans.php' );
				}
			},
			sprintf( 'data:image/svg+xml;base64,%s', base64_encode( \Voxel\paint_svg(
				file_get_contents( locate_template( 'assets/images/svgs/user.svg' ) ),
				'#a7aaad'
			) ) ),
			'0.394'
		);

		add_submenu_page(
			'voxel-membership',
			__( 'Customers', 'voxel-backend' ),
			__( 'Customers', 'voxel-backend' ),
			'manage_options',
			'voxel-customers',
			function() {
				if ( ! empty( $_GET['customer'] ) ) {
					$customer = \Voxel\User::get( $_GET['customer'] );
					if ( ! $customer ) {
						echo '<div class="wrap">'.__( 'Customer not found.', 'voxel-backend' ).'</div>';
						return;
					}

					$membership = $customer->get_membership();
					$stripe_base_url = \Voxel\Stripe::is_test_mode() ? 'https://dashboard.stripe.com/test/' : 'https://dashboard.stripe.com/';
					$plan = $membership->get_selected_plan();

					$config = [
						'customer' => [
							'id' => $customer->get_id(),
							'email' => $customer->get_email(),
							'display_name' => $customer->get_display_name(),
							'edit_link' => $customer->get_edit_link(),
							'avatar_markup' => $customer->get_avatar_markup(),
						],
						'plan' => [
							'key' => $plan->get_key(),
							'label' => $plan->get_label(),
							'edit_link' => $plan->get_edit_link(),
						],
						'membership' => [
							'type' => $membership->get_type(),
							'is_active' => $membership->is_active(),
						],
						'edit_membership' => [
							'customer_id' => $customer->get_id(),
							'plan' => $plan->get_key(),
							'type' => $membership->get_type(),
							'subscription_id' => null,
							'payment_intent_id' => null,
							'trial_allowed' => false,
						],
						'_wpnonce' => wp_create_nonce( 'vx_admin_edit_customer' ),
					];

					if ( $membership->get_type() === 'subscription' ) {
						$config['edit_membership']['subscription_id'] = $membership->get_subscription_id();
					} elseif ( $membership->get_type() === 'payment' ) {
						$config['edit_membership']['payment_intent_id'] = $membership->get_payment_intent_id();
					} elseif ( $membership->get_type() === 'default' && $plan->get_key() === 'default' ) {
						$config['edit_membership']['trial_allowed'] = $customer->is_eligible_for_free_trial();
					}

					wp_enqueue_script( 'vx:customer-editor.js' );
					require locate_template( 'templates/backend/membership/customer-details.php' );
				} else {
					$table = new \Voxel\Membership\Customer_List_Table;
					$table->prepare_items();
					require locate_template( 'templates/backend/membership/customers.php' );
				}
			},
			'2.0'
		);
	}

	protected function update_customer_plan() {
		try {
			if ( ( $_SERVER['REQUEST_METHOD'] ?? null ) !== 'POST' ) {
				throw new \Exception( __( 'Invalid request.', 'voxel-backend' ), 100 );
			}

			if ( ! current_user_can('manage_options') ) {
				throw new \Exception( __( 'Invalid request.', 'voxel-backend' ), 101 );
			}

			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'vx_admin_edit_customer' )  ) {
				throw new \Exception( __( 'Invalid request.', 'voxel-backend' ), 102 );
			}

			$payload = (array) json_decode( wp_unslash( $_REQUEST['payload'] ?? '' ), true );

			$customer = \Voxel\User::get( $payload['customer_id'] ?? null );
			if ( ! $customer ) {
				throw new \Exception( __( 'Invalid request.', 'voxel-backend' ), 103 );
			}

			$current_membership = $customer->get_membership();

			$new_plan = \Voxel\Plan::get( $payload['plan'] ?? null );
			if ( ! $new_plan ) {
				throw new \Exception( __( 'Invalid request.', 'voxel-backend' ), 104 );
			}

			$unlink_current_plan = function() use ( $customer, $current_membership ) {
				// remove membership metadata from the old subscription (if one exists)
				if ( $current_membership->get_type() === 'subscription' ) {
					try {
						$stripe = \Voxel\Stripe::getClient();
						$current_subscription = $stripe->subscriptions->retrieve( $current_membership->get_subscription_id() );
						$stripe->subscriptions->update( $current_subscription->id, [
							'metadata' => [
								'voxel:payment_for' => null,
								'voxel:plan' => null,
							],
						] );
					} catch ( \Exception $e ) {
						// dd($e->getMessage());
					}
				}
			};

			if ( $new_plan->get_key() === 'default' ) {
				$unlink_current_plan();

				$details = [
					'plan' => $new_plan->get_key(),
					'type' => 'default',
				];

				if ( $payload['trial_allowed'] ?? null ) {
					$details['trial_allowed'] = true;
				}

				$meta_key = \Voxel\Stripe::is_test_mode() ? 'voxel:test_plan' : 'voxel:plan';
				update_user_meta( $customer->get_id(), $meta_key, wp_slash( wp_json_encode( $details ) ) );

				return wp_send_json( [
					'success' => true,
				] );
			} else {
				$payment_method = $payload['type'] ?? null;
				if ( $payment_method === 'subscription' ) {
					if ( empty( $payload['subscription_id'] ) ) {
						throw new \Exception( __( 'Subscription ID is required', 'voxel-backend' ), 112 );
					}

					$stripe = \Voxel\Stripe::getClient();
					$subscription = $stripe->subscriptions->retrieve( $payload['subscription_id'] ?? null );
					if ( $subscription->customer !== $customer->get_stripe_customer_id() ) {
						throw new \Exception( __( 'Provided subscription does not belongs to this customer', 'voxel-backend' ), 106 );
					}

					if ( $subscription->status === 'canceled' ) {
						throw new \Exception( __( 'This subscription has been canceled', 'voxel-backend' ), 108 );
					}

					if ( $current_membership->get_type() === 'subscription' && $current_membership->get_subscription_id() === $subscription->id ) {
						if ( $current_membership->get_selected_plan()->get_key() !== $new_plan->get_key() ) {
							$subscription = $stripe->subscriptions->update( $subscription->id, [
								'metadata' => [
									'voxel:payment_for' => 'membership',
									'voxel:plan' => $new_plan->get_key(),
								],
							] );

							do_action( 'voxel/membership/subscription-updated', $subscription );
						}

						return wp_send_json( [
							'success' => true,
						] );
					}

					// update new subscription with membership metadata
					$update_metadata = [];
					if ( ( $subscription->metadata['voxel:payment_for'] ?? null ) !== 'membership' ) {
						$update_metadata['voxel:payment_for'] = 'membership';
					}

					if ( ( $subscription->metadata['voxel:plan'] ?? null ) !== $new_plan->get_key() ) {
						$update_metadata['voxel:plan'] = $new_plan->get_key();
					}

					if ( ! empty( $update_metadata ) ) {
						$subscription = $stripe->subscriptions->update( $subscription->id, [
							'metadata' => $update_metadata,
						] );
					}

					$unlink_current_plan();

					do_action( 'voxel/membership/subscription-updated', $subscription );

					return wp_send_json( [
						'success' => true,
					] );
				} elseif ( $payment_method === 'payment' ) {
					if ( empty( $payload['payment_intent_id'] ) ) {
						throw new \Exception( __( 'Payment Intent ID is required', 'voxel-backend' ), 112 );
					}

					$stripe = \Voxel\Stripe::getClient();
					$payment_intent = $stripe->paymentIntents->retrieve( $payload['payment_intent_id'] ?? null );
					if ( $payment_intent->customer !== $customer->get_stripe_customer_id() ) {
						throw new \Exception( __( 'Provided payment intent does not belongs to this customer', 'voxel-backend' ), 109 );
					}

					if ( $payment_intent->status === 'canceled' ) {
						throw new \Exception( __( 'This payment intent has been canceled', 'voxel-backend' ), 111 );
					}

					if ( $current_membership->get_type() === 'payment' && $current_membership->get_payment_intent_id() === $payment_intent->id ) {
						if ( $current_membership->get_selected_plan()->get_key() !== $new_plan->get_key() ) {
							$payment_intent = $stripe->paymentIntents->update( $payment_intent->id, [
								'metadata' => [
									'voxel:payment_for' => 'membership',
									'voxel:plan' => $new_plan->get_key(),
								],
							] );

							do_action( 'voxel/membership/payment_intent.succeeded', $payment_intent, [
								'preserve_additional_submissions' => true,
							] );
						}

						return wp_send_json( [
							'success' => true,
						] );
					}

					// update payment_intent with membership metadata
					$update_metadata = [];
					if ( ( $payment_intent->metadata['voxel:payment_for'] ?? null ) !== 'membership' ) {
						$update_metadata['voxel:payment_for'] = 'membership';
					}

					if ( ( $payment_intent->metadata['voxel:plan'] ?? null ) !== $new_plan->get_key() ) {
						$update_metadata['voxel:plan'] = $new_plan->get_key();
					}

					if ( ! empty( $update_metadata ) ) {
						$payment_intent = $stripe->paymentIntents->update( $payment_intent->id, [
							'metadata' => $update_metadata,
						] );
					}

					$unlink_current_plan();

					do_action( 'voxel/membership/payment_intent.succeeded', $payment_intent );

					return wp_send_json( [
						'success' => true,
					] );
				} elseif ( $payment_method === 'default' ) {
					$unlink_current_plan();

					$details = [
						'plan' => $new_plan->get_key(),
						'type' => 'default',
					];

					$meta_key = \Voxel\Stripe::is_test_mode() ? 'voxel:test_plan' : 'voxel:plan';
					update_user_meta( $customer->get_id(), $meta_key, wp_slash( wp_json_encode( $details ) ) );

					do_action(
						'voxel/membership/pricing-plan-updated',
						$customer,
						$customer->get_membership(),
						$customer->get_membership( $refresh_cache = true )
					);

					return wp_send_json( [
						'success' => true,
					] );
				} else {
					throw new \Exception( __( 'Invalid request.', 'voxel-backend' ), 105 );
				}
			}
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}
}
