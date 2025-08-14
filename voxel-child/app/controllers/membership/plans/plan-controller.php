<?php

namespace Voxel\Controllers\Membership\Plans;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Plan_Controller extends \Voxel\Controllers\Base_Controller {

	protected function authorize() {
		return current_user_can( 'manage_options' );
	}

	protected function hooks() {
		$this->on( 'admin_post_voxel_create_membership_plan', '@create_plan' );

		$this->on( 'voxel_ajax_membership.update_plan', '@update_plan' );
		$this->on( 'voxel_ajax_membership.archive_plan', '@archive_plan' );
		$this->on( 'voxel_ajax_membership.delete_plan', '@delete_plan' );
		$this->on( 'voxel_ajax_membership.create_price', '@create_price' );
		$this->on( 'voxel_ajax_membership.sync_prices', '@sync_prices' );
		$this->on( 'voxel_ajax_membership.toggle_price', '@toggle_price' );
		$this->on( 'voxel_ajax_membership.setup_pricing', '@setup_pricing' );

		// role pricing templates
		$this->on( 'voxel_ajax_membership.create_pricing_template', '@create_pricing_template' );
		$this->on( 'voxel_ajax_membership.delete_pricing_template', '@delete_pricing_template' );
		$this->on( 'voxel_ajax_membership.update_pricing_template', '@update_pricing_template' );
	}

	protected function create_plan() {
		check_admin_referer('voxel_manage_membership_plans');
		if (!current_user_can('manage_options')) {
			die;
		}
	
		if (empty($_POST['membership_plan']) || !is_array($_POST['membership_plan'])) {
			die;
		}
	
		$key = sanitize_key($_POST['membership_plan']['key'] ?? '');
		$label = sanitize_text_field($_POST['membership_plan']['label'] ?? '');
		$description = sanitize_textarea_field($_POST['membership_plan']['description'] ?? '');

		// here is custom code
		$commission_discount = isset($_POST['membership_plan']['commission_discount']) ? intval($_POST['membership_plan']['commission_discount']) : 0;
		$adults = isset($_POST['membership_plan']['adults']) ? intval($_POST['membership_plan']['adults']) : 0;
		$children = isset($_POST['membership_plan']['children']) ? intval($_POST['membership_plan']['children']) : 0;
		$students = isset($_POST['membership_plan']['students']) ? intval($_POST['membership_plan']['students']) : 0;
		$seniors = isset($_POST['membership_plan']['seniors']) ? intval($_POST['membership_plan']['seniors']) : 0;
	
		try {
			$plan = \Voxel\Plan::create([
				'key'         			=> $key,
				'label'       			=> $label,
				'description' 			=> $description,
				'commission_discount' 	=> $commission_discount,  // Store commission_discount
				'adults'      			=> $adults,  // Store adults
				'children'    			=> $children, // Store children
				'students'    			=> $students, // Store students
				'seniors'    			=> $seniors // Store seniors
			]);
	
			email_debug_data(['create_plan---' => $_POST]); // Debugging log
	
		} catch (\Exception $e) {
			wp_die($e->getMessage());
		}
	
		wp_safe_redirect(admin_url('admin.php?page=voxel-membership'));
		exit;
	}
	

	protected function update_plan() {
		//  Log received data to debug
		email_debug_data(['update_plan---' => $_POST]);
	
		try {
			$data = json_decode(stripslashes($_POST['plan']), true);
	
			//  Debug: Check if JSON decode was successful
			if (!$data) {
				throw new \Exception("Invalid JSON format.");
			}
	
			$key = sanitize_text_field(trim($data['key'] ?? ''));
			$plan = \Voxel\Plan::get($key);
			if (!$plan) {
				throw new \Exception(__('Plan not found.', 'voxel-backend'));
			}
	
			$submissions = [];
			foreach ((array)($data['submissions'] ?? []) as $post_type_key => $limit) {
				if (post_type_exists($post_type_key)) {
					$submissions[$post_type_key] = (array)$limit;
				}
			}
	
			// Log adults and children values before saving
			email_debug_data(['After Update---' => $data]);
	
			// Save Adults & Children Data
			$plan->update([
				'label'       			=> sanitize_text_field(trim($data['label'])),
				'description' 			=> wp_kses_post(trim($data['description'])),
				'submissions' 			=> $submissions,
				'settings'    			=> $data['settings'] ?? [],
				'commission_discount'  	=> isset($data['commission_discount']) ? intval($data['commission_discount']) : 0,
				'adults'      			=> isset($data['adults']) ? intval($data['adults']) : 0,
				'children'    			=> isset($data['children']) ? intval($data['children']) : 0,
				'students'    			=> isset($data['students']) ? intval($data['students']) : 0,
				'seniors'    			=> isset($data['seniors']) ? intval($data['seniors']) : 0,
			]);
	
			return wp_send_json([
				'success' => true,
			]);
		} catch (\Exception $e) {
			return wp_send_json([
				'success' => false,
				'errors'  => [$e->getMessage()],
			]);
		}
	}
	

	protected function archive_plan() {
		try {
			$data = $_POST['plan'] ?? [];
			$key = sanitize_text_field( trim( $data['key'] ?? '' ) );
			$plan = \Voxel\Plan::get( $key );
			if ( ! $plan ) {
				throw new \Exception( __( 'Plan not found.', 'voxel-backend' ) );
			}

			$plan->update( 'archived', ! $plan->is_archived() );

			return wp_send_json( [
				'success' => true,
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'errors' => [ $e->getMessage() ],
			] );
		}
	}

	protected function delete_plan() {
		try {
			$data = $_POST['plan'] ?? [];
			$key = sanitize_text_field( trim( $data['key'] ?? '' ) );
			$plan = \Voxel\Plan::get( $key );
			if ( ! $plan ) {
				throw new \Exception( __( 'Plan not found.', 'voxel-backend' ) );
			}

			$plans = \Voxel\get( 'plans' );
			unset( $plans[ $plan->get_key() ] );
			\Voxel\set( 'plans', $plans );

			return wp_send_json( [
				'redirect_to' => admin_url( 'admin.php?page=voxel-membership' ),
				'success' => true,
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'errors' => [ $e->getMessage() ],
			] );
		}
	}

	protected function create_price() {
		try {
			$plan = \Voxel\Plan::get( $_POST['plan'] );
			if ( ! $plan ) {
				throw new \Exception( __( 'Plan not found.', 'voxel-backend' ) );
			}

			$pricing = $plan->get_pricing();
			$mode = ( $_POST['mode'] ?? 'test' ) === 'test' ? 'test' : 'live';
			$client = ( $mode === 'live' )
				? \Voxel\Stripe::getLiveClient()
				: \Voxel\Stripe::getTestClient();

			// create stripe product if it doesn't exist
			if ( empty( $pricing[ $mode ] ) ) {
				$args = [
					'name' => $plan->get_label(),
					'metadata' => [
						'product_type' => 'membership\plan',
					],
				];

				if ( ! empty( $plan->get_description() ) ) {
					$args['description'] = $plan->get_description();
				}

				$product = $client->products->create( $args );

				$pricing[ $mode ] = [
					'product_id' => $product->id,
					'prices' => [],
				];

				$plan->update( 'pricing', $pricing );
			}

			$product_id = $pricing[ $mode ]['product_id'];
			$data = $_POST['price'] ?? [];
			$amount = isset( $data['amount'] ) ? abs( $data['amount'] ) : null;
			$currency = isset( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : null;
			$type = isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : null;
			$interval = isset( $data['interval'] ) ? sanitize_text_field( $data['interval'] ) : null;
			$intervalCount = isset( $data['intervalCount'] ) ? absint( $data['intervalCount'] ) : null;
			$tax_behavior = ( ! empty( $data['includeTax'] ?? null ) && $data['includeTax'] !== 'false' ) ? 'inclusive' : 'exclusive';

			if ( $currency === null || $amount === null ) {
				throw new \Exception( __( 'Please provide an amount and a currency.', 'voxel-backend' ) );
			}

			if ( ! \Voxel\Stripe\Currencies::is_zero_decimal( $currency ) ) {
				$amount *= 100;
			}

			$args = [
				'currency' => $currency,
				'product' => $product_id,
				'active' => true,
				'unit_amount' => $amount,
				'tax_behavior' => $tax_behavior,
				'metadata' => [
					'pricing_type' => 'membership_pricing',
				],
			];

			if ( $type === 'recurring' ) {
				$args['recurring'] = [
					'interval' => $interval,
					'interval_count' => $intervalCount,
				];
			}

			$price = $client->prices->create( $args );

			$pricing[ $mode ]['prices'][ $price->id ] = [
				'currency' => $price->currency,
				'type' => $price->type,
				'amount' => $price->unit_amount,
				'active' => $price->active,
				'tax_behavior' => $price->tax_behavior,
			];

			if ( $price->type === 'recurring' ) {
				$pricing[ $mode ]['prices'][ $price->id ]['recurring'] = [
					'interval' => $price->recurring->interval,
					'interval_count' => $price->recurring->interval_count,
				];
			}

			$plan->update( 'pricing', $pricing );

			return wp_send_json( [
				'success' => true,
				'pricing' => $plan->get_editor_config()['pricing'],
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'errors' => [ $e->getMessage() ],
			] );
		}
	}

	protected function sync_prices() {
		try {
			$plan = \Voxel\Plan::get( $_GET['plan'] );
			if ( ! $plan ) {
				throw new \Exception( __( 'Plan not found.', 'voxel-backend' ) );
			}

			$pricing = $plan->get_pricing();
			$mode = ( $_GET['mode'] ?? 'test' ) === 'test' ? 'test' : 'live';
			$client = ( $mode === 'live' )
				? \Voxel\Stripe::getLiveClient()
				: \Voxel\Stripe::getTestClient();

			$product_id = $pricing[ $mode ]['product_id'];
			$prices = $client->prices->all( [
				'product' => $product_id,
				'limit' => 100,
			] );

			$pricing[ $mode ]['prices'] = [];
			foreach ( $prices->data as $price) {
				$pricing[ $mode ]['prices'][ $price->id ] = [
					'currency' => $price->currency,
					'type' => $price->type,
					'amount' => $price->unit_amount,
					'active' => $price->active,
					'tax_behavior' => $price->tax_behavior,
				];

				if ( $price->type === 'recurring' ) {
					$pricing[ $mode ]['prices'][ $price->id ]['recurring'] = [
						'interval' => $price->recurring->interval,
						'interval_count' => $price->recurring->interval_count,
					];
				}
			}

			$plan->update( 'pricing', $pricing );

			return wp_send_json( [
				'success' => true,
				'pricing' => $plan->get_editor_config()['pricing'],
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'errors' => [ $e->getMessage() ],
			] );
		}
	}

	protected function toggle_price() {
		try {
			$plan = \Voxel\Plan::get( $_GET['plan'] );
			if ( ! $plan ) {
				throw new \Exception( __( 'Plan not found.', 'voxel-backend' ) );
			}

			$pricing = $plan->get_pricing();
			$mode = ( $_GET['mode'] ?? 'test' ) === 'test' ? 'test' : 'live';
			$priceId = sanitize_text_field( $_GET['price'] ?? null );

			if ( empty( $pricing[ $mode ]['prices'][ $priceId ] ) ) {
				throw new \Exception( __( 'Price not found.', 'voxel-backend' ) );
			}

			$client = ( $mode === 'live' )
				? \Voxel\Stripe::getLiveClient()
				: \Voxel\Stripe::getTestClient();

			$isActive = (bool) $pricing[ $mode ]['prices'][ $priceId ]['active'];
			$prices = $client->prices->update( $priceId, [
				'active' => ! $isActive,
			] );

			$pricing[ $mode ]['prices'][ $priceId ]['active'] = ! $isActive;
			$plan->update( 'pricing', $pricing );

			return wp_send_json( [
				'success' => true,
				'pricing' => $plan->get_editor_config()['pricing'],
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'errors' => [ $e->getMessage() ],
			] );
		}
	}

	protected function setup_pricing() {
		try {
			$plan = \Voxel\Plan::get( $_GET['plan'] );
			if ( ! $plan ) {
				throw new \Exception( __( 'Plan not found.', 'voxel-backend' ) );
			}

			$pricing = $plan->get_pricing();
			$mode = ( $_GET['mode'] ?? 'test' ) === 'test' ? 'test' : 'live';
			$client = ( $mode === 'live' )
				? \Voxel\Stripe::getLiveClient()
				: \Voxel\Stripe::getTestClient();

			$createProduct = function() use ( $plan, $pricing, $client, $mode ) {
				$args = [
					'name' => $plan->get_label(),
					'metadata' => [
						'product_type' => 'membership\plan',
					],
				];

				if ( ! empty( $plan->get_description() ) ) {
					$args['description'] = $plan->get_description();
				}

				$product = $client->products->create( $args );

				$pricing[ $mode ] = [
					'product_id' => $product->id,
					'prices' => [],
				];

				$plan->update( 'pricing', $pricing );

				return $product;
			};

			$product_id = $pricing[ $mode ]['product_id'] ?? null;

			if ( ! empty( $product_id ) ) {
				try {
					$product = $client->products->retrieve( $product_id );
					// \Voxel\log('product id exists and retrieved');
				} catch ( \Voxel\Vendor\Stripe\Exception\ApiErrorException $e ) {
					if ( $e->getStripeCode() === 'resource_missing' ) {
						$product = $createProduct();
						// \Voxel\log('product id exists but retrieval failed');
					}
				}
			} else {
				// \Voxel\log('product id does not exist');
				$product = $createProduct();
			}

			return wp_send_json( [
				'success' => true,
				'product_id' => $product->id,
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	protected function create_pricing_template() {
		try {
			$role = \Voxel\Role::get( sanitize_text_field( $_REQUEST['role_key'] ) );
			if ( ! ( $role && $role->is_managed_by_voxel() && $role->_is_safe_for_registration() ) ) {
				throw new \Exception( __( 'Invalid request.', 'voxel-backend' ) );
			}

			if ( $role->get_pricing_page_id() ) {
				throw new \Exception( __( 'Invalid request.', 'voxel-backend' ) );
			}

			$template_id = \Voxel\create_page(
				sprintf( _x( '%s pricing', 'pricing page title', 'voxel-backend' ), $role->get_label() ),
				sprintf( '%s-pricing', $role->get_key() )
			);

			if ( is_wp_error( $template_id ) ) {
				throw new \Exception( __( 'Could not create page.', 'voxel-backend' ) );
			}

			$settings = $role->get_editor_config()['settings'];
			$settings['templates']['pricing'] = $template_id;

			$role->set_config( [
				'settings' => $settings,
			] );

			return wp_send_json( [
				'success' => true,
				'template_id'=> $template_id,
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	protected function delete_pricing_template() {
		try {
			$role = \Voxel\Role::get( sanitize_text_field( $_REQUEST['role_key'] ) );
			if ( ! ( $role && $role->is_managed_by_voxel() && $role->_is_safe_for_registration() ) ) {
				throw new \Exception( __( 'Invalid request.', 'voxel-backend' ) );
			}

			wp_delete_post( $role->get_pricing_page_id() );

			$settings = $role->get_editor_config()['settings'];
			$settings['templates']['pricing'] = null;

			$role->set_config( [
				'settings' => $settings,
			] );

			return wp_send_json( [
				'success' => true,
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	protected function update_pricing_template() {
		try {
			$role = \Voxel\Role::get( sanitize_text_field( $_REQUEST['role_key'] ) );
			if ( ! ( $role && $role->is_managed_by_voxel() && $role->_is_safe_for_registration() ) ) {
				throw new \Exception( __( 'Invalid request.', 'voxel-backend' ) );
			}

			$new_template_id = absint( $_REQUEST['template_id'] ?? null );
			if ( ! \Voxel\page_exists( $new_template_id ) ) {
				throw new \Exception( __( 'Provided page template does not exist.', 'voxel-backend' ) );
			}

			$settings = $role->get_editor_config()['settings'];
			$settings['templates']['pricing'] = $new_template_id;

			$role->set_config( [
				'settings' => $settings,
			] );

			return wp_send_json( [
				'success' => true,
				'template_id' => $new_template_id,
			] );
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}
}
