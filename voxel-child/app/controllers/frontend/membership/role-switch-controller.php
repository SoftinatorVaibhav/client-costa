<?php

namespace Voxel\Controllers\Frontend\Membership;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Role_Switch_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_roles.switch_role', '@switch_role' );
	}

	protected function switch_role() {
		try {
			\Voxel\verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'vx_switch_role' );

			$role_key = sanitize_text_field( $_REQUEST['role_key'] ?? '' );
			$role = \Voxel\Role::get( $role_key );

			if ( ! $role ) {
				throw new \Exception( _x( 'Invalid request.', 'auth', 'voxel' ), 100 );
			}

			if ( ! $role->is_switching_enabled() ) {
				throw new \Exception( _x( 'Invalid request.', 'auth', 'voxel' ), 101 );
			}

			$user = \Voxel\current_user();
			if ( $user->has_role( 'administrator' ) || $user->has_role( 'editor' ) ) {
				throw new \Exception( _x( 'Switching roles is not allowed for Administrator and Editor accounts.', 'roles', 'voxel' ), 102 );
			}

			$switchable_roles = $user->get_switchable_roles();
			if ( ! isset( $switchable_roles[ $role->get_key() ] ) ) {
				throw new \Exception( _x( 'You cannot switch to this role.', 'roles', 'voxel' ), 103 );
			}

			$membership = $user->get_membership();
			$plan = $membership->plan;

			/**
			 * Handle switching when user is on the fallback plan
			 */
			if ( $plan->get_key() === 'default' ) {
				if ( $role->has_plans_enabled() && $role->config( 'settings.role_switch.show_plans_on_switch', true ) ) {
					/**
					 * If the new role has pricing plans and it is configured to show plans during
					 * switch, redirect the user to the role's pricing plans page.
					 */
					$redirect_to = get_permalink( $role->get_pricing_page_id() ) ?: home_url('/');

					return wp_send_json( [
						'success' => true,
						'redirect_to' => add_query_arg( 'switch_to_role', $role->get_key(), $redirect_to ),
					] );
				} else {
					/**
					 * Otherwise, switch to the new role right away.
					 */
					$user->set_role( $role->get_key() );

					return wp_send_json( [
						'success' => true,
						'redirect_to' => '(reload)',
					] );
				}
			} else {
				/**
				 * Handle switching when user is on a paid plan.
				 */
				if ( $plan->supports_role( $role->get_key() ) ) {
					/**
					 * User has paid plan which is also supported by the new role, which means we can
					 * switch the role right away.
					 */
					$user->set_role( $role->get_key() );

					return wp_send_json( [
						'success' => true,
						'redirect_to' => '(reload)',
					] );
				} else {
					/**
					 * Handle switching when user has paid plan which is not supported by the new role.
					 */
					if ( $role->has_plans_enabled() ) {
						/**
						 * The new role doesn't support current plan, but it does support different plans,
						 * so we can redirect the user to the pricing plans page.
						 */
						$redirect_to = get_permalink( $role->get_pricing_page_id() ) ?: home_url('/');

						return wp_send_json( [
							'success' => true,
							'redirect_to' => add_query_arg( 'switch_to_role', $role->get_key(), $redirect_to ),
						] );
					} else {
						/**
						 * The new role doesn't support current plan or any plans at all.
						 */
						throw new \Exception( _x( 'You cannot switch to this role.', 'roles', 'voxel' ), 109 );
					}
				}
			}
		} catch ( \Exception $e ) {
			return wp_send_json( [
				'success' => false,
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
			] );
		}
	}
}
