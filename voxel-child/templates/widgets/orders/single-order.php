<?php
if ( ! defined('ABSPATH') ) {
	exit;
}

require_once locate_template( 'templates/widgets/orders/item-booking-details.php' );
require_once locate_template( 'templates/widgets/orders/item-deliverables.php' );

?>
<script type="text/html" id="orders-single">
	<div class="vx-order-ease test-sitesss">
		<div v-if="order" class="single-order" :class="{'vx-pending': running_action || orders.order.loading}">
			<div class="vx-order-head">
				<a href="#" @click.prevent="goBack" class="ts-btn ts-btn-1 ts-go-back">
					<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_back') ) ?: \Voxel\get_svg( 'chevron-left.svg' ) ?>
					<?= _x( 'Go back', 'single order', 'voxel' ) ?>
				</a>

				<template v-if="order.customer.id && order.vendor.id">
					<a @click.prevent="openConversation" href="#" class="ts-btn ts-btn-1 has-tooltip"
						:data-tooltip="isVendor() ? <?= esc_attr( wp_json_encode( _x( 'Message customer', 'single order', 'voxel' ) ) ) ?> : <?= esc_attr( wp_json_encode( _x( 'Message seller', 'single order', 'voxel' ) ) ) ?>">
						<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_inbox') ) ?: \Voxel\get_svg( 'inbox.svg' ) ?>
					</a>
				</template>

				<template v-if="order.actions.primary.length">
					<template v-for="action in order.actions.primary">
						<a href="#" @click.prevent="runAction(action)" class="ts-btn ts-btn-2">
							<template v-if="action.action.endsWith('vendor.approve')">
								<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_checkmark') ) ?: \Voxel\get_svg( 'checkmark-circle.svg' ) ?>
							</template>
							{{ action.label }}
						</a>
					</template>
				</template>

				<template v-if="order.actions.secondary.length">
					<form-group
						popup-key="actions"
						ref="actions"
						:show-clear="false"
						:show-save="false"
						:default-class="false"
						class="ts-btn ts-btn-1 has-tooltip ts-popup-target"
						tag="a"
						href="#"
						@click.prevent
						@mousedown="$root.activePopup = 'actions'"
						data-tooltip="<?= esc_attr( _x( 'More actions', 'single order', 'voxel' ) ) ?>"
					>
						<template #trigger>
							<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_menu') ) ?: \Voxel\get_svg( 'menu-two.svg' ) ?>
						</template>
						<template #popup>
							<div class="ts-term-dropdown ts-md-group">
								<ul class="simplify-ul ts-term-dropdown-list min-scroll">
									<template v-for="action in order.actions.secondary">
										<li>
											<a href="#" class="flexify" @click.prevent="runAction( action )">
												<span>{{ action.label }}</span>
											</a>
										</li>
									</template>
								</ul>
							</div>
						</template>
					</form-group>
				</template>
			</div>

			<div class="order-timeline">
				<div class="order-event">
					<div v-if="order.customer.avatar" class="vx-avatar big-avatar" v-html="order.customer.avatar"></div>
					<div class="order-status" :class="orders.config.statuses_ui[ order.status.key ]?.class || 'vx-neutral'">
						{{ orders.config.statuses[ order.status.key ]?.label || order.status.key }}
					</div>
					<h3>{{ order.customer.name }} submitted order #{{ order.id }}</h3>
					<span>{{ order.created_at }}</span>
				</div>

				<div v-if="order.items.length" class="order-event">
					<div class="order-event-box">
						<ul class="ts-cart-list simplify-ul">
							<template v-for="item in order.items">
								<li>
									<div v-if="item.product.thumbnail_url" class="cart-image">
						      			<img width="150" height="150" :src="item.product.thumbnail_url" class="ts-status-avatar" decoding="async">
									</div>
									<div class="cart-item-details">
										<a :href="item.product.link">{{ item.product.label }}</a>
										<span>{{ item.product.description }}</span>
										<span>{{ orders.currencyFormat( item.subtotal, item.currency ) }}</span>
									</div>
								</li>
							</template>
						</ul>
						<ul class="ts-cost-calculator simplify-ul flexify">
							<li v-if="order.pricing.subtotal !== null" class="ts-cost--subtotal">
								<div class="ts-item-name"><p><?= _x( 'Subtotal', 'single order', 'voxel' ) ?></p></div>
								<div class="ts-item-price"><p>{{ orders.currencyFormat( order.pricing.subtotal, order.pricing.currency ) }}</p></div>
							</li>
							<?php 	
							$user_id = null;
							$membership = null;
							$member = get_user_plan();						
							$user = \Voxel\current_user(); // Get current user
							$membership = $user->get_membership();
							$dis_text = 'Traveller Non Membership Rates';							
							$x;
							$num;
								
							if ($member != 'default' && $membership) {
								$user_id = get_current_user_id();
								$num = traveller_num();									
								$dis_text = ' x Traveller Membership Rates';
								preg_match('/\d+/', $value, $matches);
								$x = $matches[0];

							}
							
							?>

							<li class="ts-custom-discount">
								<div class="ts-item-name"><p><?= _x( $num.$dis_text, 'single order', 'voxel' ) ?></p></div>								
							</li>

							<li v-if="order.pricing.tax_amount !== null" class="ts-cost--tax-amount">
								<div class="ts-item-name"><p><?= _x( 'Tax', 'single order', 'voxel' ) ?></p></div>
								<div class="ts-item-price"><p>{{ orders.currencyFormat( order.pricing.tax_amount, order.pricing.currency ) }}</p></div>
							</li>
							<li v-if="order.pricing.total !== null" class="ts-total">
								<div class="ts-item-name"><p><?= _x( 'Total', 'single order', 'voxel' ) ?></p></div>
								<div class="ts-item-price"><p>{{ orders.currencyFormat( order.pricing.total, order.pricing.currency ) }}</p></div>
							</li>

							<?php 
							if(get_post_meta( $_GET['order_id'], 'custom_booking_amount', true )!= null && !empty(get_post_meta( $_GET['order_id'], 'custom_booking_amount', true ))):?>
								<li class="ts-custom-remaining-amount">
									<div class="ts-item-name"><p><?= _x( 'Deposit Today', 'single order', 'voxel' ) ?></p></div>
									<div class="ts-item-price"><p><?php echo '$'.get_post_meta( $_GET['order_id'], 'custom_booking_amount', true );?></p></div>
								</li>
							<?php endif;?>

							<?php 
							if(get_post_meta( $_GET['order_id'], 'custom_remaining_amount', true )!= null && !empty(get_post_meta( $_GET['order_id'], 'custom_remaining_amount', true ))):?>
								<li class="ts-custom-remaining-amount">
									<div class="ts-item-name"><p><?= _x( 'Remaing Amount (To be paid at location)', 'single order', 'voxel' ) ?></p></div>
									<div class="ts-item-price"><p><?php echo '$'.get_post_meta( $_GET['order_id'], 'custom_remaining_amount', true );?></p></div>
								</li>
							<?php endif;?>

							<li v-if="order.pricing.total !== null && order.pricing.subscription_interval !== null">
								<div class="ts-item-name"></div>
								<div class="ts-item-price"><p><?= _x( 'Renews', 'single order', 'voxel' ) ?> {{ order.pricing.subscription_interval }}</p></div>
							</li>
						</ul>
						<details class="order-accordion" v-if="order.vendor.fees">
							<summary><?= _x( 'Vendor fees', 'single order', 'voxel' ) ?><?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_down') ) ?: \Voxel\get_svg( 'chevron-down.svg' ) ?></summary>
							<div class="details-body">
								 <ul  class="ts-cost-calculator simplify-ul flexify ts-customer-details">
									<li v-for="fee in order.vendor.fees.breakdown" >
										<div class="ts-item-name"><p>{{ fee.label }}</p></div>
										<div class="ts-item-price"><p>{{ fee.content }}</p></div>
									</li>
									<li class="ts-total">
										<div class="ts-item-name"><p><?= _x( 'Total', 'single order', 'voxel' ) ?></p></div>
										<div class="ts-item-price"><p>{{ orders.currencyFormat( order.vendor.fees.total, order.pricing.currency ) }}</p></div>
									</li>
								</ul>
							</div>
						</details>
						<details class="order-accordion" v-if="order.customer.customer_details?.length">
							<summary><?= _x( 'Customer details', 'single order', 'voxel' ) ?><?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_down') ) ?: \Voxel\get_svg( 'chevron-down.svg' ) ?></summary>
							<div class="details-body">
								 <ul  class="ts-cost-calculator simplify-ul flexify ts-customer-details">
									<li v-for="detail in order.customer.customer_details" >
										<div class="ts-item-name"><p>{{ detail.label }}</p></div>
										<div class="ts-item-price"><p>{{ detail.content }}</p></div>
									</li>
								</ul>
							</div>
						</details>
						<details class="order-accordion" v-if="order.customer.shipping_details?.length">
							<summary><?= _x( 'Shipping details', 'single order', 'voxel' ) ?><?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_down') ) ?: \Voxel\get_svg( 'chevron-down.svg' ) ?></summary>
							<div class="details-body">
								 <ul  class="ts-cost-calculator simplify-ul flexify ts-customer-details">
									<li v-for="detail in order.customer.shipping_details" >
										<div class="ts-item-name"><p>{{ detail.label }}</p></div>
										<div class="ts-item-price"><p>{{ detail.content }}</p></div>
									</li>
								</ul>
							</div>
						</details>
						<details class="order-accordion" v-if="order.customer.order_notes?.length">
							<summary><?= _x( 'Order notes', 'single order', 'voxel' ) ?><?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_down') ) ?: \Voxel\get_svg( 'chevron-down.svg' ) ?></summary>
							<div class="details-body">
								<p v-html="order.customer.order_notes" style="white-space: pre-wrap; word-break: break-word;"></p>
							</div>
						</details>
						<details class="order-accordion" v-if="order.vendor.notes_to_customer?.length">
							<summary><?= _x( 'Notes to customer', 'single order', 'voxel' ) ?><?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_down') ) ?: \Voxel\get_svg( 'chevron-down.svg' ) ?></summary>
							<div class="details-body">
								<p v-html="order.vendor.notes_to_customer" style="white-space: pre-wrap; word-break: break-word;"></p>
							</div>
						</details>

					</div>
				</div>

				<template v-for="item in order.items">
					<template v-if="item.type === 'regular' && item.details.claim && item.details.claim.proof_of_ownership.length">
						<div class="order-event">
							<div class="order-event-icon vx-blue">
								<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_files') ) ?: \Voxel\get_svg( 'files.svg' ) ?>
							</div>
							<span><?= _x( 'Proof of ownership', 'single order', 'voxel' ) ?></span>
							<ul class="flexify simplify-ul vx-order-files">
								<li v-for="file in item.details.claim.proof_of_ownership">
									<a :href="file.url" target="_blank" class="ts-order-file">{{ file.name }}</a>
								</li>
							</ul>
						</div>
					</template>
				</template>

				<div v-if="order.pricing.payment_method === 'stripe_subscription' && order.status.key !== 'pending_payment'" class="order-event">
					<div v-if="orders.config.statuses_ui[ order.status.key ]?.icon"
						class="order-event-icon"
						:class="orders.config.statuses_ui[ order.status.key ]?.class || 'vx-neutral'"
						v-html="orders.config.statuses_ui[ order.status.key ].icon"
					></div>
					<div v-else class="order-event-icon" :class="orders.config.statuses_ui[ order.status.key ]?.class || 'vx-neutral'">
						<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_info') ) ?: \Voxel\get_svg( 'info.svg' ) ?>
					</div>

					<!-- <b v-if="order.status.long_label">{{ order.status.long_label }}</b>
					<b v-else>{{ orders.config.statuses[ order.status.key ]?.long_label || orders.config.statuses[ order.status.key ]?.label || order.status.key }}</b> -->

					<template v-if="order.pricing.details.cancel_at_period_end">
						<b><?= _x( 'Subscription is active', 'single order subscriptions', 'voxel' ) ?></b>
						<span><?= \Voxel\replace_vars( _x( 'Automatic renewal is disabled. Subscription will be cancelled on @period_end.', 'single order subscriptions', 'voxel' ), [
							'@period_end' => '{{ order.pricing.details.current_period_end_display }}',
						] ) ?></span>

						<div v-if="getAction('payments/stripe_subscription/customers/customer.subscriptions.enable_renewal')" class="further-actions">
							<a href="#" @click.prevent="runAction( getAction('payments/stripe_subscription/customers/customer.subscriptions.enable_renewal') )" class="ts-btn ts-btn-1"><?= _x( 'Enable renewals', 'single order subscriptions', 'voxel' ) ?></a>
						</div>
					</template>
					<template v-else-if="order.pricing.details.status === 'trialing'">
						<b><?= _x( 'Subscription is active', 'single order subscriptions', 'voxel' ) ?></b>
						<span><?= \Voxel\replace_vars( _x( 'Your trial ends on @trial_end', 'single order subscriptions', 'voxel' ), [
							'@trial_end' => '{{ order.pricing.details.trial_end_display }}',
						] ) ?></span>
					</template>
					<template v-else-if="order.pricing.details.status === 'active'">
						<b><?= _x( 'Subscription is active', 'single order subscriptions', 'voxel' ) ?></b>
						<span><?= \Voxel\replace_vars( _x( 'Next renewal date is @period_end', 'single order subscriptions', 'voxel' ), [
							'@period_end' => '{{ order.pricing.details.current_period_end_display }}',
						] ) ?></span>
					</template>
					<template v-else-if="order.pricing.details.status === 'incomplete'">
						<b><?= _x( 'Subscription payment has not been completed', 'single order subscriptions', 'voxel' ) ?></b>
						<div class="further-actions">
							<a v-if="getAction('payments/stripe_subscription/customers/customer.subscriptions.finalize_payment')" href="#" @click.prevent="runAction( getAction('payments/stripe_subscription/customers/customer.subscriptions.finalize_payment') )" class="ts-btn ts-btn-1"><?= _x( 'Finalize payment', 'single order subscriptions', 'voxel' ) ?></a>
						</div>
					</template>
					<template v-else-if="order.pricing.details.status === 'incomplete_expired'">
						<b><?= _x( 'Subscription expired', 'single order subscriptions', 'voxel' ) ?></b>
						<span>{{ order.status.updated_at }}</span>
					</template>
					<template v-else-if="order.pricing.details.status === 'past_due'">
						<b><?= _x( 'Subscription is past due', 'single order subscriptions', 'voxel' ) ?></b>
						<span><?= _x( 'Subscription renewal failed', 'single order subscriptions', 'voxel' ) ?></span>
						<div class="further-actions">
							<a v-if="getAction('payments/stripe_subscription/customers/customer.subscriptions.finalize_payment')" href="#" @click.prevent="runAction( getAction('payments/stripe_subscription/customers/customer.subscriptions.finalize_payment') )" class="ts-btn ts-btn-1"><?= _x( 'Finalize payment', 'single order subscriptions', 'voxel' ) ?></a>
							<a v-if="getAction('payments/stripe_subscription/customers/customer.access_portal')" href="#" @click.prevent="runAction( getAction('payments/stripe_subscription/customers/customer.access_portal') )" class="ts-btn ts-btn-1"><?= _x( 'Update payment method', 'single order subscriptions', 'voxel' ) ?></a>
						</div>
					</template>
					<template v-else-if="order.pricing.details.status === 'canceled'">
						<b><?= _x( 'Subscription canceled', 'single order subscriptions', 'voxel' ) ?></b>
						<span>{{ order.status.updated_at }}</span>
					</template>
					<template v-else-if="order.pricing.details.status === 'unpaid'">
						<b><?= _x( 'Subscription is unpaid', 'single order subscriptions', 'voxel' ) ?></b>
						<span><?= _x( 'Subscription has been deactivated due to failed renewal attempts.', 'single order subscriptions', 'voxel' ) ?></span>
						<div class="further-actions">
							<a v-if="getAction('payments/stripe_subscription/customers/customer.subscriptions.finalize_payment')" href="#" @click.prevent="runAction( getAction('payments/stripe_subscription/customers/customer.subscriptions.finalize_payment') )" class="ts-btn ts-btn-1"><?= _x( 'Finalize payment', 'single order subscriptions', 'voxel' ) ?></a>
							<a v-if="getAction('payments/stripe_subscription/customers/customer.access_portal')" href="#" @click.prevent="runAction( getAction('payments/stripe_subscription/customers/customer.access_portal') )" class="ts-btn ts-btn-1"><?= _x( 'Update payment method', 'single order subscriptions', 'voxel' ) ?></a>
						</div>
					</template>
				</div>
				<div v-else class="order-event vx-green">
					<div v-if="orders.config.statuses_ui[ order.status.key ]?.icon"
						class="order-event-icon"
						:class="orders.config.statuses_ui[ order.status.key ]?.class || 'vx-neutral'"
						v-html="orders.config.statuses_ui[ order.status.key ].icon"
					></div>
					<div v-else class="order-event-icon" :class="orders.config.statuses_ui[ order.status.key ]?.class || 'vx-neutral'">
						<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_info') ) ?: \Voxel\get_svg( 'info.svg' ) ?>
					</div>
					<b v-if="order.status.long_label">{{ order.status.long_label }}</b>
					<b v-else>{{ orders.config.statuses[ order.status.key ]?.long_label || orders.config.statuses[ order.status.key ]?.label || order.status.key }}</b>
					<span>{{ order.status.updated_at }}</span>
				</div>

				<template v-for="item in order.items">
					<template v-if="item.type === 'booking' && item.details.booking">
						<item-booking-details :booking="item.details.booking" :item="item" :order="order" :parent="this"></item-booking-details>
					</template>
					<template v-if="item.type === 'regular' && item.details.deliverables">
						<item-deliverables :deliverables="item.details.deliverables" :item="item" :order="order" :parent="this"></item-deliverables>
					</template>
					<template v-if="item.type === 'regular' && item.details.claim && item.details.claim.approved">
						<div class="order-event">
							<div class="order-event-icon vx-blue">
								<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_info') ) ?: \Voxel\get_svg( 'info.svg' ) ?>
							</div>
							<b><?= _x( 'The listing has been claimed succesfully', 'single order', 'voxel' ) ?></b>
							<div v-if="item.product.link" class="further-actions">
								<a :href="item.product.link" target="_blank" class="ts-btn ts-btn-1"><?= _x( 'View listing', 'single order', 'voxel' ) ?></a>
							</div>
						</div>
					</template>
					<template v-if="item.type === 'regular' && item.details.promotion_package && ( item.details.promotion_package.status === 'active' || item.details.promotion_package.status === 'ended' )">
						<div class="order-event">
							<div class="order-event-icon vx-blue">
								<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_info') ) ?: \Voxel\get_svg( 'info.svg' ) ?>
							</div>
							<b v-if="item.details.promotion_package.status === 'active'"><?= _x( 'Promotion is active', 'single order', 'voxel' ) ?></b>
							<b v-else><?= _x( 'Promotion has ended', 'single order', 'voxel' ) ?></b>
							<span v-if="item.details.promotion_package.start_date && item.details.promotion_package.end_date">
								{{ item.details.promotion_package.start_date }}
								-
								{{ item.details.promotion_package.end_date }}
							</span>
							<div class="further-actions">
								<a v-if="item.details.promotion_package.post_link" :href="item.details.promotion_package.post_link" target="_blank" class="ts-btn ts-btn-1">
									<?= _x( 'View listing', 'single order', 'voxel' ) ?>
								</a>
								<a v-if="item.details.promotion_package.stats_link" :href="item.details.promotion_package.stats_link" target="_blank" class="ts-btn ts-btn-1">
									<?= _x( 'View stats', 'single order', 'voxel' ) ?>
								</a>
							</div>
						</div>
					</template>
				</template>
			</div>
		</div>
	</div>
</script>
