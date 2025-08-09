<?php

namespace Voxel\Controllers;

if ( ! defined('ABSPATH') ) {
	exit;
}

class User_Controller extends Base_Controller {

	protected function hooks() {
		$this->on( 'init', '@register_post_type', 0 );
		$this->on( 'wp_insert_post', '@cache_user_post_stats', 10 );
		$this->on( 'after_delete_post', '@cache_user_post_stats', 10 );
		$this->on( 'pre_get_avatar_data', '@show_custom_avatar', 35, 2 );
		$this->filter( 'show_admin_bar', '@should_show_admin_bar' );

		// wp admin profile screen
		$this->on( 'admin_head', '@show_profile_details' );
		$this->filter( 'manage_edit-profile_columns', '@profile_columns' );
		$this->filter( 'user_row_actions', '@user_row_actions', 10, 2 );

		if ( is_admin() ) {
			$this->filter( 'request', '@profile_sort' );
		}

		// custom profile fields
		if ( current_user_can( 'manage_options' ) ) {
			$this->on( 'edit_user_profile', '@show_custom_fields' );
			$this->on( 'show_user_profile', '@show_custom_fields' );
			$this->on( 'personal_options_update', '@save_custom_fields' );
			$this->on( 'edit_user_profile_update', '@save_custom_fields' );
		}
	}

	protected function register_post_type() {
		register_post_type( 'profile', [
			'labels' => [
				'name' => 'Profiles',
				'singular_name' => 'Profile',
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
			'hierarchical'        => false,
			'query_var'           => true,
			'supports'            => [],
			'menu_position'       => 70,
			'delete_with_user'    => true,
			'_is_created_by_voxel' => false,
			'has_archive' => 'profiles',
			'rewrite' => [
				'slug' => 'profile_preview',
			],
		] );

		remove_post_type_support( 'profile', 'author' );
		remove_post_type_support( 'profile', 'comments' );
		remove_post_type_support( 'profile', 'title' );
		remove_post_type_support( 'profile', 'editor' );
	}

	protected function cache_user_post_stats( $post_id ) {
		$post = \Voxel\Post::get( $post_id );
		if ( $post && $post->post_type && $post->post_type->is_managed_by_voxel() ) {
			\Voxel\queue_user_post_stats_for_caching( $post->get_author_id() );
		}
	}

	protected function show_custom_avatar( $args, $id_or_email ) {
		if ( (bool) $args['force_default'] === true ) {
			return $args;
		}

		if ( ! ( $user = \Voxel\get_user_by_id_or_email( $id_or_email ) ) ) {
			return $args;
		}

		$avatar_id = $user->get_avatar_id();
		$avatar_url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
		if ( $avatar_id && $avatar_url ) {
			$args['url'] = $avatar_url;
		}

		return $args;
	}

	protected function should_show_admin_bar( $should_show ) {
		$user = \Voxel\current_user();
		if ( ! ( $user && ( $user->has_role( 'administrator' ) || $user->has_role( 'editor' ) ) ) ) {
			return false;
		}

		return $should_show;
	}

	protected function show_profile_details() {
		$screen = get_current_screen();
		if ( ! ( ( $screen->post_type ?? null ) === 'profile' && ( $screen->id ?? null ) === 'edit-profile' ) ) {
			return;
		}

		add_filter( 'the_title', function( $title, $post_id ) {
			$post = \Voxel\Post::get( $post_id );
			if ( $post->post_type->get_key() !== 'profile' ) {
				return __( '(unknown)', 'voxel-backend' );
			}

			$author = $post->get_author();
			if ( ! ( $author && (int) $author->get_profile_id() === (int) $post->get_id() ) ) {
				return __( '(unknown)', 'voxel-backend' );
			}

			return sprintf( '#%d &mdash; %s', $author->get_id(), $author->get_display_name() );
		}, 10, 2 );

		add_filter( 'post_row_actions', function( $actions, $post ) {
			$post = \Voxel\Post::get( $post );
			if ( $post->post_type->get_key() !== 'profile' ) {
				return $actions;
			}

			$author = $post->get_author();
			if ( ! ( $author && (int) $author->get_profile_id() === (int) $post->get_id() ) ) {
				return $actions;
			}

			$actions['view'] = sprintf( '<a href="%s">%s</a>', esc_url( $author->get_link() ), __( 'View Profile', 'voxel-backend' ) );
			$actions['view_user'] = sprintf( '<a href="%s">%s</a>', esc_url( $author->get_edit_link() ), __( 'Edit User', 'voxel-backend' ) );
			return $actions;
		}, 10, 2 );
	}

	protected function profile_columns( $columns ) {
		$columns['title'] = __( 'Profile', 'voxel-backend' );
		// unset( $columns['author'] );
		unset( $columns['comments'] );
		unset( $columns['date'] );
		return $columns;
	}

	protected function user_row_actions( $actions, $user ) {
		$user = \Voxel\User::get( $user );
		$profile = $user->get_or_create_profile();
		$actions['edit_profile'] = sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $profile->get_id() ) ), __( 'Edit profile', 'voxel-backend' ) );
		return $actions;
	}

	protected function profile_sort( $vars ) {
		$screen = get_current_screen();
		if ( ! ( $screen && $screen->id === 'edit-profile' ) ) {
			return $vars;
		}

		if ( empty( $vars['orderby'] ) ) {
			$vars['orderby'] = 'post_author';
			$vars['order'] = 'desc';
		}

		if ( $vars['orderby'] === 'title' ) {
			$vars['orderby'] = 'post_author';
		}

		return $vars;
	}

	protected function show_custom_fields( $user ) {
		$user = \Voxel\User::get( $user );
		$membership = $user->get_membership();

		require locate_template( 'templates/backend/user-custom-fields.php' );
	}

	protected function save_custom_fields( $user_id ) {
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$data = $_POST['vx_details'] ?? [];
		if ( ! is_array( $data ) || empty( $data ) ) {
			return;
		}

		$user = \Voxel\User::get( $user_id );

		if ( isset( $data['plan'] ) ) {
			if ( $plan = \Voxel\Plan::get( $data['plan'] ) ) {
				$meta_key = \Voxel\Stripe::is_test_mode() ? 'voxel:test_plan' : 'voxel:plan';
				$details = (array) json_decode( get_user_meta( $user->get_id(), $meta_key, true ), ARRAY_A );
				$details['plan'] = $plan->get_key();
				$screen = 'user-dasboard';

				// custom_initialize_or_update_user_meta($user->get_id(),$details['plan'],$screen);

				// email_debug_data(array(
				// 	'user-controller-id' => $user->get_id(),
			    //     'meta_key' => $meta_key,
			    //     'details-plan' => $details['plan']
			    // ));

				// update_user_meta( $user->get_id(), $meta_key, wp_slash( wp_json_encode( $details ) ) );
			}
		}
	}
}
