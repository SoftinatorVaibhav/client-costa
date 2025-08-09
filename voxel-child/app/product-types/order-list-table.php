<?php

namespace Voxel\Product_Types;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Order_List_Table extends \WP_List_Table {

	public function get_columns() {
		$columns = [
			// 'cb' => '<input type="checkbox">',
			'id' => _x( 'ID', 'orders table', 'voxel-backend' ),
			'customer' => _x( 'Customer', 'orders table', 'voxel-backend' ),
			'amount' => _x( 'Amount', 'orders table', 'voxel-backend' ),
			'status' => _x( 'Status', 'orders table', 'voxel-backend' ),
			'vendor' => _x( 'Vendor', 'orders table', 'voxel-backend' ),
			'deposit_amount' => _x( 'Deposit Amount', 'orders table', 'voxel-backend' ),
			'due_arrival' => _x( 'Due On Arrival', 'orders table', 'voxel-backend' ),
			'created_at' => _x( 'Date', 'orders table', 'voxel-backend' ),
			//'payment_method' => _x( 'Type', 'orders table', 'voxel-backend' ),
			//'details' => '',
		];

		return $columns;
	}

	protected function get_sortable_columns() {
		$sortable_columns = [
			'id' => [ 'created_at', 'asc' ],
			'created_at' => [ 'created_at', 'desc' ],
		];

		return $sortable_columns;
	}

	protected function get_primary_column_name() {
		return 'customer';
	}

	protected function column_default( $order, $column_name ) {
		$customer = $order->get_customer();
		$vendor = $order->get_vendor();
		$custom_booking_deposit_amount = get_post_meta( $order->get_id(), "custom_booking_amount", true );
		$custom_due_arrival_remaining_amount = get_post_meta( $order->get_id(), "custom_remaining_amount", true );



		if ( $column_name === 'id' ) {
			if ( $parent_order = $order->get_parent_order() ) {
				return sprintf( '<a href="%s">&mdash; Suborder #%d</a>', esc_url( $order->get_backend_link() ), $order->get_id() );
			} else {
				return sprintf( '<a href="%s"><strong>Order #%d</strong></a>', esc_url( $order->get_backend_link() ), $order->get_id() );
			}
		} elseif ( $column_name === 'amount' ) {
			if ( $order->get_total() !== null ) {
				return sprintf(
					'<span class="price-amount">%s</span>',
					\Voxel\currency_format( $order->get_total(), $order->get_currency(), false )
				);
			}

			if ( $order->get_subtotal() !== null ) {
				return sprintf(
					'<span class="price-amount price-subtotal">%s</span>',
					\Voxel\currency_format( $order->get_subtotal(), $order->get_currency(), false )
				);
			}

			return '&mdash;';
		} elseif ( $column_name === 'customer' ) {
			if ( $customer ) {
				ob_start(); ?>
				<div class="item-details">
					<div class="item-image"><?= $customer->get_avatar_markup(24) ?></div>
					<div class="item-title">
						<span>
							<a href="<?= esc_url( $customer->get_edit_link() ) ?>">
								<?= esc_html( $customer->get_display_name() ) ?>
							</a>
						</span>
					</div>
				</div>
				<?php return ob_get_clean();
			} else {
				return '(deleted)';
			}
		} elseif ( $column_name === 'vendor' ) {
			if ( $order->has_vendor() && $vendor ) {
				ob_start(); ?>
				<div class="item-details">
					<div class="item-image"><?= $vendor->get_avatar_markup(24) ?></div>
					<div class="item-title">
						<span>
							<a href="<?= esc_url( $vendor->get_edit_link() ) ?>">
								<?= esc_html( $vendor->get_display_name() ) ?>
							</a>
						</span>
					</div>
				</div>
				<?php return ob_get_clean();
			} else {
				ob_start(); ?>
				<div class="item-details">
					<div class="item-title">
						<span><?= _x( 'Platform', 'orders table', 'voxel-backend' ) ?></span>
					</div>
				</div>
				<?php return ob_get_clean();
			}
		} elseif ( $column_name === 'status' ) {
			$config = \Voxel\Product_Types\Orders\Order::get_status_config();
			return sprintf(
				'<div class="order-status order-status-%s %s">%s</div>',
				esc_attr( $order->get_status() ),
				esc_attr( $config[ $order->get_status() ]['class'] ?? '' ),
				$order->get_status_label()
			);
		} elseif ( $column_name === 'created_at' ) {
			if ( $created_at = $order->get_created_at() ) {
				return \Voxel\datetime_format( $created_at->getTimestamp() + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
			}

			return '&mdash;';
		} elseif ( $column_name === 'details' ) {
			return sprintf(
				'<a href="%s" class="button right">%s</a>',
				esc_url( admin_url( 'admin.php?page=voxel-orders&order_id='.$order->get_id() ) ),
				_x( 'Details', 'orders table', 'voxel-backend' )
			);
		} elseif ( $column_name === 'payment_method' ) {
			if ( $payment_method = $order->get_payment_method() ) {
				return $payment_method->get_label();
			}

			return '&mdash;';
		}

		elseif ( $column_name === 'deposit_amount' ) {
			if ( $custom_booking_deposit_amount ) {
				$custom_booking_deposit_amount_with_currency = "<strong>$". $custom_booking_deposit_amount . "</strong>";
				return $custom_booking_deposit_amount_with_currency;
			}
			else{
			return '&mdash;';	
			}

			
		}

				elseif ( $column_name === 'due_arrival' ) {
			if ( $custom_due_arrival_remaining_amount ) {
				$custom_due_arrival_remaining_amount_with_currency = "<strong>$". $custom_due_arrival_remaining_amount . "</strong>";
				return $custom_due_arrival_remaining_amount_with_currency;
			}
			else{
			return '&mdash;';	
			}

			
		}

		return null;
	}

	protected function get_views() {
		global $wpdb;

		$testmode = \Voxel\Stripe::is_test_mode() ? 'true' : 'false';
		$total_counts = $wpdb->get_results( <<<SQL
			SELECT status, COUNT(*) AS total
			FROM {$wpdb->prefix}vx_orders
			WHERE testmode IS {$testmode}
			GROUP BY status
		SQL );

		$counts = [];
		$total_count = 0;

		foreach ( $total_counts as $count ) {
			$counts[ $count->status ] = absint( $count->total );
			$total_count += absint( $count->total );
		}

		$active = $_GET['status'] ?? null;

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s%s</a>',
			admin_url('admin.php?page=voxel-orders'),
			empty( $active ) ? 'current' : '',
			_x( 'All', 'orders table', 'voxel-backend' ),
			$total_count > 0 ? sprintf( ' <span class="count">(%s)</span>', number_format_i18n( $total_count ) ) : '',
		);

		foreach ( \Voxel\Product_Types\Orders\Order::get_status_config() as $status_key => $status ) {
			if ( isset( $counts[ $status_key ] ) ) {
				$views[ $status_key ] = sprintf(
					'<a href="%s" class="%s">%s%s</a>',
					admin_url( 'admin.php?page=voxel-orders&status='.$status_key ),
					$active === $status_key ? 'current' : '',
					$status['label'],
					sprintf( ' <span class="count">(%s)</span>', number_format_i18n( $counts[ $status_key ] ) ),
				);
			}
		}

		return $views;
	}

	protected function extra_tablenav( $which ) {
		if ( $which !== 'top' ) {
			return;
		} ?>
		<input type="text" name="search_customer" placeholder="<?= esc_attr( _x( 'Search customer', 'orders table', 'voxel-backend' ) ) ?>" value="<?= esc_attr( wp_unslash( $_GET['search_customer'] ?? '' ) ) ?>">
		<input type="text" name="search_vendor" placeholder="<?= esc_attr( _x( 'Search vendor', 'orders table', 'voxel-backend' ) ) ?>" value="<?= esc_attr( wp_unslash( $_GET['search_vendor'] ?? '' ) ) ?>">
		<input type="submit" class="button" value="Filter">
		<?php
	}

	public function prepare_items() {
		global $wpdb;

		$page = $this->get_pagenum();
		$limit = 20;
		$offset = $limit * ( $page - 1 );
		$columns = $this->get_columns();
		$hidden = [];
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];

		$args = [
			'limit' => $limit,
			'offset' => $offset,
			'order' => ( $_GET['order'] ?? null ) === 'asc' ? 'asc' : 'desc',
			'status' => ! empty( $_GET['status'] ?? null ) ? $_GET['status'] : null,
			'with_items' => false,
			'parent_id' => 0,
			'with_child_orders' => true,

			// @todo
			'search_customer' => ! empty( $_GET['search_customer'] ?? null ) ? wp_unslash( $_GET['search_customer'] ) : null,
			'search_vendor' => ! empty( $_GET['search_vendor'] ?? null ) ? wp_unslash( $_GET['search_vendor'] ) : null,
		];

		$results = \Voxel\Product_Types\Orders\Order::query( $args );
		$count = \Voxel\Product_Types\Orders\Order::count( $args );

		$items = [];
		foreach ( $results as $order ) {
			$items[] = $order;
			foreach ( $order->get_child_orders() as $child_order ) {
				$items[] = $child_order;
			}
		}

		$this->items = $items;
		$this->set_pagination_args( [
			'total_items' => $count,
			'per_page' => $limit,
			'total_pages' => ceil( $count / $limit ),
		] );
	}
}
