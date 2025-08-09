<?php

namespace Voxel\Controllers;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Assets_Controller extends Base_Controller {

	/**
	 * List of script handles to defer.
	 *
	 * @since 1.0
	 */
	protected $deferred_scripts = [
		'vue' => true,
		'sortable' => true,
		'vue-draggable' => true,
		'google-recaptcha' => true,
		'google-maps' => true,
		'mapbox-gl' => true,
		'pikaday' => true,
		'nouislider' => true,
		'vx:commons.js' => true,
		'vx:search-form.js' => true,
		'vx:post-feed.js' => true,
		'vx:create-post.js' => true,
		'vx:timeline-main.js' => true,
		'vx:google-maps.js' => true,
		'vx:mapbox.js' => true,
		'vx:auth.js' => true,
		'vx:orders.js' => true,
		'vx:product-form.js' => true,
		'vx:product-summary.js' => true,
		'vx:user-bar.js' => true,
		'vx:messages.js' => true,
		'vx:reservations.js' => true,
		'vx:vendor-stats.js' => true,
		'vx:quick-search.js' => true,
		'vx:collections.js' => true,
		'vx:countdown.js' => true,
		'vx:share.js' => true,
		'vx:configure-plan.js' => true,
		'vx:visit-tracker.js' => true,
		'vx:visits-chart.js' => true,
		'vx:stripe-connect-dashboard.js' => true,
	];

	protected $soft_loaded_scripts = [
		'google-maps' => true,
		'vx:google-maps.js' => true,
		'mapbox-gl' => true,
		'vx:mapbox.js' => true,
	];

	/**
	 * Defer non-critical CSS.
	 *
	 * @link https://web.dev/defer-non-critical-css/
	 * @since 1.0
	 */
	protected $deferred_styles = [
		'wp-block-library' => true,
		'vx:popup-kit.css' => true,
		'mapbox-gl' => true,
		'swiper' => true,
		'vx:product-form.css' => true,
		'vx:social-feed.css' => true,
		'fonts:jetbrains-mono' => true,
	];

	protected function hooks() {
		$this->on( 'wp_enqueue_scripts', '@register_scripts' );
		$this->on( 'admin_enqueue_scripts', '@register_scripts' );
		$this->on( 'elementor/editor/before_enqueue_scripts', '@register_scripts' );
		$this->on( 'voxel/before_render_search_results', '@register_scripts' );
		$this->on( 'voxel/before_render_tab_template', '@register_scripts' );
		$this->on( 'admin_footer', '@enable_dtags_in_backend' );

		$this->on( 'admin_enqueue_scripts', '@enqueue_backend_scripts' );
		$this->on( 'wp_enqueue_scripts', '@enqueue_common_scripts_in_preview' );
		$this->on( 'wp_enqueue_scripts', '@enqueue_frontend_scripts', 5 );
		$this->on( 'wp_footer', '@enqueue_frontend_low_priority_scripts' );

		$this->on( 'wp_default_scripts', '@remove_jquery_migrate' );

		$this->filter( 'script_loader_tag', '@defer_scripts', 10, 2 );
		$this->filter( 'style_loader_tag', '@defer_styles', 10, 4 );

		$this->on( 'wp_head', '@print_head_content' );
		$this->on( 'admin_head', '@print_head_content' );
		$this->on( 'customize_controls_enqueue_scripts', '@print_head_content' );
		$this->on( 'elementor/editor/before_enqueue_scripts', '@print_head_content' );

		$this->on( 'wp_footer', '@print_footer_helpers' );
		$this->on( 'admin_footer', '@print_alert_template' );

		if ( apply_filters( 'voxel/disable-wp-emoji', true ) !== false ) {
			$this->on( 'init', '@disable_wp_emoji' );
		}

		add_action( 'get_header', function() {
			remove_action( 'wp_head', '_admin_bar_bump_cb' );
		} );
	}

	protected function register_scripts() {
		$assets = trailingslashit( get_template_directory_uri() ) . 'assets/';
		$dist = trailingslashit( $assets ) . 'dist/';
		$vendor = trailingslashit( $assets ) . 'vendor/';
		$version = \Voxel\get_assets_version();
	
		// styles
		foreach ( \Voxel\config('assets.styles') as $style ) {
			wp_register_style( sprintf( 'vx:%s', $style ), $dist . ( is_rtl() ? str_replace( '.css', '-rtl.css', $style ) : $style ), [], $version );
		}
	
		// scripts
		foreach ( \Voxel\config('assets.scripts') as $script ) {
			if ( is_array( $script ) ) {
				$script_path = ($script['src'] === 'plan-editor.js') 
					? get_stylesheet_directory_uri() . '/assets/dist/' . $script['src'] // Load from child theme
					: $dist . $script['src']; // Load from parent theme
	
				wp_register_script( sprintf( 'vx:%s', $script['src'] ), $script_path, $script['deps'] ?? [], $version, true );
			} else {
				$script_path = ($script === 'plan-editor.js') 
					? get_stylesheet_directory_uri() . '/assets/dist/' . $script // Load from child theme
					: $dist . $script; // Load from parent theme
	
				wp_register_script( sprintf( 'vx:%s', $script ), $script_path, [], $version, true );
			}
		}
	
		// vendor styles
		$suffix = \Voxel\is_dev_mode() ? '' : '.prod';
		wp_register_style( 'nouislider', $vendor . 'nouislider/nouislider' . $suffix . '.css', [], '14.6.3' );
		wp_register_style( 'pikaday', $vendor . 'pikaday/pikaday' . $suffix . '.css', [], '1.8.15' );
	
		// vendor scripts
		wp_register_script( 'vue', $vendor . 'vue/vue' . $suffix . '.js', [], '3.5.13', true );
		wp_register_script( 'sortable', $vendor . 'sortable/sortable' . $suffix . '.js', [], '1.10.2', true );
		wp_register_script( 'vue-draggable', $vendor . 'vue-draggable/vue-draggable' . $suffix . '.js', [ 'vue', 'sortable' ], '4.0.1', true );
		wp_register_script( 'nouislider', $vendor . 'nouislider/nouislider' . $suffix . '.js', ['jquery'], '14.6.3', true );
		wp_register_script( 'pikaday', $vendor . 'pikaday/pikaday' . $suffix . '.js', ['jquery'], '1.8.15', true );
		wp_register_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . \Voxel\get('settings.recaptcha.key'), [], false, true );
		wp_register_script( 'google-maps', sprintf( 'https://maps.googleapis.com/maps/api/js?%s', http_build_query( [
			'key' => \Voxel\get( 'settings.maps.google_maps.api_key' ),
			'libraries' => 'places',
			'v' => '3.57',
			'callback' => 'Voxel.Maps.GoogleMaps',
			'language' => \Voxel\get( 'settings.maps.google_maps.language' ) ?: null,
			'region' => \Voxel\get( 'settings.maps.google_maps.region' ) ?: null,
			'loading' => 'async',
		] ) ), ['vx:commons.js'], false, true );
		wp_register_script( 'mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v3.5.1/mapbox-gl.js', ['vx:commons.js'], '3.5.1', true );
		wp_register_style( 'mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v3.5.1/mapbox-gl.css', [], '3.5.1' );
		wp_register_style( 'fonts:jetbrains-mono', 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital@0;1&display=swap', false, null );
	
		if ( $timeline_kit = \Voxel\get( 'templates.kit_timeline', null ) ) {
			$this->deferred_styles['elementor-post-'.$timeline_kit] = true;
		}
	}
	

	protected function enable_dtags_in_backend() {
		wp_enqueue_style( 'vx:elementor.css' );
		require locate_template( 'templates/backend/dynamic-data/dynamic-data.php' );
	}

	protected function enqueue_common_scripts_in_preview() {
		if ( ! \Voxel\is_preview_mode() ) {
			return;
		}

		wp_enqueue_script( 'vue' );
		wp_enqueue_script( 'sortable' );
		wp_enqueue_script( 'vue-draggable' );
		wp_enqueue_script( 'vx:commons.js' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'pikaday' );
		wp_enqueue_script( 'nouislider' );
		wp_enqueue_style( 'pikaday' );
		wp_enqueue_style( 'nouislider' );
		wp_enqueue_style( 'vx:commons.css' );
		wp_enqueue_style( 'vx:preview.css' );

		if ( ! class_exists( '_WP_Editors', false ) ) {
			require( ABSPATH . WPINC . '/class-wp-editor.php' );
		}
		wp_deregister_style( 'editor-buttons' );
		\_WP_Editors::enqueue_default_editor();
	}

	protected function enqueue_backend_scripts() {
		wp_enqueue_script( 'vue' );
		wp_enqueue_script( 'vx:backend.js' );
		wp_enqueue_style( 'vx:backend.css' );
		wp_enqueue_style( 'fonts:jetbrains-mono' );
		wp_enqueue_media();
	}

	protected function enqueue_frontend_scripts() {
		wp_enqueue_script( 'jquery-core', '', [], false, true );
		wp_enqueue_script( 'vue' );
		wp_enqueue_script( 'vx:commons.js' );
		wp_enqueue_style( 'vx:commons.css' );

		wp_deregister_style( 'classic-theme-styles' );
		wp_dequeue_style( 'classic-theme-styles' );

		$current_post = \Voxel\get_current_post();
		if (
			! ( get_post_type() && use_block_editor_for_post_type( get_post_type() ) )
			|| ( $current_post && $current_post->is_built_with_elementor() )
		) {
			wp_dequeue_style( 'wp-block-library' );
			wp_dequeue_style( 'global-styles' );
		}
	}

	protected function enqueue_frontend_low_priority_scripts() {
		wp_enqueue_style( 'vx:popup-kit.css' );

		if ( apply_filters( '_voxel/enqueue-custom-popup-kit', true ) === true ) {
			if ( $popup_kit = \Voxel\get( 'templates.kit_popups', null ) ) {
				$this->deferred_styles[ 'elementor-post-'.$popup_kit ] = true;
				\Voxel\enqueue_template_css( $popup_kit );
			}
		}
	}

	protected function print_head_content() {
		$config = [
			'ajax_url' => add_query_arg( 'vx', 1, home_url( '/' ) ),
			'is_logged_in' => (bool) is_user_logged_in(),
			'current_user_id' => (int) get_current_user_id(),
			'login_url' => \Voxel\get_auth_url(),
			'register_url' => add_query_arg( 'register', '', \Voxel\get_auth_url() ),
			'is_rtl' => is_rtl(),
			'l10n' => [
				'ajaxError' => __( 'There was a problem. Please try again.', 'voxel' ),
				'confirmAction' => __( 'Are you sure you want to proceed with this action?', 'voxel' ),
				'accountRequired' => __( 'An account is required to perform this action', 'voxel' ),
				'login' => __( 'Log in', 'voxel' ),
				'register' => __( 'Register', 'voxel' ),
				'yes' => __( 'Yes', 'voxel' ),
				'no' => __( 'No', 'voxel' ),
				'copied' => __( 'Copied to clipboard', 'voxel' ),
				'positionFail' => __( 'Could not determine your location.', 'voxel' ),
				'addressFail' => __( 'Could not determine your address.', 'voxel' ),
				'view_cart' => _x( 'View cart', 'product form', 'voxel' ),
				'added_to_cart' => _x( 'Your product has been added to cart.', 'product form', 'voxel' ),
				'months' => [
					_x( 'January', 'months', 'voxel' ),
					_x( 'February', 'months', 'voxel' ),
					_x( 'March', 'months', 'voxel' ),
					_x( 'April', 'months', 'voxel' ),
					_x( 'May', 'months', 'voxel' ),
					_x( 'June', 'months', 'voxel' ),
					_x( 'July', 'months', 'voxel' ),
					_x( 'August', 'months', 'voxel' ),
					_x( 'September', 'months', 'voxel' ),
					_x( 'October', 'months', 'voxel' ),
					_x( 'November', 'months', 'voxel' ),
					_x( 'December', 'months', 'voxel' ),
				],
				'weekdays' => [
					_x( 'Sunday', 'weekdays', 'voxel' ),
					_x( 'Monday', 'weekdays', 'voxel' ),
					_x( 'Tuesday', 'weekdays', 'voxel' ),
					_x( 'Wednesday', 'weekdays', 'voxel' ),
					_x( 'Thursday', 'weekdays', 'voxel' ),
					_x( 'Friday', 'weekdays', 'voxel' ),
					_x( 'Saturday', 'weekdays', 'voxel' ),
				],
				'weekdaysShort' => [
					_x( 'Sun', 'weekdays short', 'voxel' ),
					_x( 'Mon', 'weekdays short', 'voxel' ),
					_x( 'Tue', 'weekdays short', 'voxel' ),
					_x( 'Wed', 'weekdays short', 'voxel' ),
					_x( 'Thu', 'weekdays short', 'voxel' ),
					_x( 'Fri', 'weekdays short', 'voxel' ),
					_x( 'Sat', 'weekdays short', 'voxel' ),
				],
			],
			'locale' => get_locale(),
			'currency' => \Voxel\get('settings.stripe.currency', 'usd'),
			'maps' => [
				'provider' => \Voxel\get( 'settings.maps.provider' ),
				'default_lat' => \Voxel\get( 'settings.maps.default_location.lat' ) ?: 42.5,
				'default_lng' => \Voxel\get( 'settings.maps.default_location.lng' ) ?: 21,
			],
		];

		if ( \Voxel\get( 'settings.maps.provider' ) === 'mapbox' && ! \Voxel\is_preview_mode() ) {
			$config['mapbox'] = [
				'api_key' => \Voxel\get( 'settings.maps.mapbox.api_key' ),
				'skin' => \Voxel\get( 'settings.maps.mapbox.skin' ),
				'language' => \Voxel\get( 'settings.maps.mapbox.language' ),
				'handle' => 'vx:mapbox.js-js',
			];
		} elseif ( \Voxel\get( 'settings.maps.provider' ) === 'google_maps' ) {
			$map_skin_json = \Voxel\get( 'settings.maps.google_maps.skin' );
			$map_skin = json_decode( $map_skin_json ?? '' );
			$config['google_maps'] = [
				'skin' => ( ! empty( $map_skin_json ) && json_last_error() === JSON_ERROR_NONE ) ? $map_skin : null,
				'mapTypeId' => \Voxel\get( 'settings.maps.google_maps.map_type_id', 'roadmap' ),
				'mapTypeControl' => !! \Voxel\get( 'settings.maps.google_maps.map_type_control', false ),
				'streetViewControl' => !! \Voxel\get( 'settings.maps.google_maps.street_view_control', false ),
				'handle' => 'vx:google-maps.js-js',
			];
		}

		printf( '<script type="text/javascript">var Voxel_Config = %s;</script>', wp_json_encode( (object) $config ) );
	}

	protected function disable_wp_emoji() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', function( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
		} );
		add_filter( 'wp_resource_hints', function( $urls, $relation_type ) {
			if ( $relation_type === 'dns-prefetch' ) {
				$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/14.0.0/svg/' );
				$urls = array_diff( $urls, [ $emoji_svg_url ] );
			}
			return $urls;
		}, 10, 2 );
	}

	protected function remove_jquery_migrate( $scripts ) {
		$scripts->registered['jquery']->deps = array_diff(
			$scripts->registered['jquery']->deps,
			[ 'jquery-migrate' ]
		);
	}

	protected function print_alert_template() {
		echo <<<HTML
			<div id="vx-alert"></div>
			<script type="text/html" id="vx-alert-tpl">
				<div class="ts-notice ts-notice-{type} flexify">
					<i class="las la-info-circle"></i>
					<p>{message}</p>
					<a href="#">Close</a>
				</div>
			</script>
		HTML;
	}

	protected function print_footer_helpers() {
		require locate_template( 'templates/components/alert.php' );
		echo <<<HTML
			<div id="vx-alert"></div>
			<div id="vx-markup-cache" class="hidden"></div>
			<div id="vx-assets-cache" class="hidden"></div>
		HTML;
	}

	protected function defer_scripts( $tag, $handle ) {
		if ( isset( $this->deferred_scripts[ $handle ] ) ) {
			$tag = str_replace( '<script ', '<script defer ', $tag );
		}

		if ( isset( $this->soft_loaded_scripts[ $handle ] ) ) {
			$tag = str_replace( ' src=', ' data-src=', $tag );
		}

		return $tag;
	}

	protected function defer_styles( $tag, $handle, $href, $media ) {
		if ( isset( $this->deferred_styles[ $handle ] ) ) {
			return str_replace( "rel='stylesheet'", "rel='preload stylesheet' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag );
		}

		return $tag;
	}
}
