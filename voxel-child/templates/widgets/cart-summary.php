<?php
if ( ! defined('ABSPATH') ) {
	exit;
}
?>

<script type="text/json" class="vxconfig"><?= wp_specialchars_decode( wp_json_encode( $config ) ) ?></script>
<div class="vx-loading-screen ts-checkout-loading">
	<div class="ts-no-posts">
		<span class="ts-loader"></span>
	</div>
</div>
<div class="ts-form ts-checkout ts-checkout-regular">
	<template v-if="loading"></template>
	<template v-else-if="!hasItems()">
		<div class="vx-loading-screen">
			<div class="ts-form-group ts-no-posts">
				<?= \Voxel\get_icon_markup( $this->get_settings_for_display('nostock_ico') ) ?: \Voxel\svg( 'box-remove.svg' ) ?>
				<p><?= _x( 'No products selected for checkout', 'cart summary', 'voxel' ) ?></p>
			</div>
		</div>
	</template>
	<template v-else>
		<?php if ( ! is_user_logged_in() && $config['guest_customers']['behavior'] === 'proceed_with_email' ): ?>
			<a href="<?= esc_url( $auth_link ) ?>" class="ts-btn ts-btn-1 form-btn">
				<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_enter') ) ?: \Voxel\svg( 'user.svg' ) ?>
				<?= _x( 'Returning customer? Sign in', 'cart summary', 'voxel' ) ?>
			</a>
		<?php endif ?>
		<div class="ts-cart-head">
			<h1 v-if="cart_context === 'booking'">
				<?= _x( 'Booking confirmation', 'cart summary', 'voxel' ) ?>
			</h1>
			<h1 v-else-if="cart_context === 'claim'">
				<?= _x( 'Claim listing', 'cart summary', 'voxel' ) ?>
			</h1>
			<h1 v-else-if="cart_context === 'promote'">
				<?= _x( 'Promote post', 'cart summary', 'voxel' ) ?>
			</h1>
			<h1 v-else-if="cart_context === 'direct_order'">
				<?= _x( 'Order details', 'cart summary', 'voxel' ) ?>
			</h1>
			<h1 v-else>
				<?= _x( 'Cart', 'cart summary', 'voxel' ) ?>
			</h1>
		</div>
		<div class="checkout-section form-field-grid">
			<div class="ts-form-group">
				<div class="or-group">
					<span class="or-text"><?= _x( 'Items', 'cart summary', 'voxel' ) ?></span>
					<div class="or-line"></div>
				</div>
			</div>
			<div class="ts-form-group">
				<ul class="ts-cart-list simplify-ul test">
					<template v-for="item in items">
						<!-- Debugging item data -->
						<div>
							<pre>{{ console.log(item) }}</pre>
						</div>
						<li :class="{'vx-disabled': item._disabled}">
							<div class="cart-image" v-html="item.logo"></div>
							<div class="cart-item-details">
								<a :href="item.link">{{ item.title }}</a>
								<span v-if="item.subtitle">{{ item.subtitle }}</span>

								<!-- New div to display the amount values with quantity -->
								<div class="cart-item-pricing" :product-id="item.value.product.post_id">
									<div v-if="item.pricing.summary.length && item.pricing.summary[0].summary" class="pricing-details">
										<div class="pricing-row" v-if="item.pricing.summary[0].summary[0]">
											<span class="label">
												Adults × {{ item.pricing.summary[0].summary[0]?.quantity || 0 }}
											</span>
											<span class="price">
												${{ item.pricing.summary[0].summary[0]?.amount || 0 }}
											</span>
										</div>
										<div class="pricing-row" v-if="item.pricing.summary[0].summary[1]">
											<span class="label">
												Children × {{ item.pricing.summary[0].summary[1]?.quantity || 0 }}
											</span>
											<span class="price">
												${{ item.pricing.summary[0].summary[1]?.amount || 0 }}
											</span>
										</div>
									</div>
								</div>

								<div class="cart-item-subtotal">
									<span class="label">Subtotal</span>
									<span class="be-3">{{ currencyFormat( item.pricing.total_amount ) }}</span>
								</div>
							</div>

							<div v-if="item.quantity.enabled" class="cart-stepper">
								<a @click.prevent="minusOne(item)" href="#" class="ts-icon-btn ts-smaller">
									<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_minus_icon') ) ?: \Voxel\svg( 'minus.svg' ) ?>
								</a>
								<span>{{ getItemQuantity(item) }}</span>
								<a @click.prevent="plusOne(item)" href="#" class="ts-icon-btn ts-smaller" :class="{'vx-disabled': !hasStockLeft(item)}">
									<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_plus_icon') ) ?: \Voxel\svg( 'plus.svg' ) ?>
								</a>
							</div>
							<div v-else class="cart-stepper">x
								<a href="#" class="ts-icon-btn ts-smaller" @click.prevent="removeItem(item)">
									<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_delete_icon') ) ?: \Voxel\svg( 'trash-can.svg' ) ?>
								</a>
							</div>
						</li>
					</template>
				</ul>
			</div>
		</div>

		<?php if ( is_user_logged_in() || $config['guest_customers']['behavior'] === 'proceed_with_email' ): ?>
			<div class="checkout-section form-field-grid">
				<div class="ts-form-group">
					<div class="or-group">
						<span class="or-text"><?= _x( 'Order details', 'cart summary', 'voxel' ) ?></span>
						<div class="or-line"></div>
					</div>
				</div>
				<?php if ( ! is_user_logged_in() && $config['guest_customers']['behavior'] === 'proceed_with_email' ): ?>
					<div class="ts-form-group vx-1-1">
						<label><?= esc_attr( _x( 'Email address', 'cart summary', 'voxel' ) ) ?></label>
						<div class="ts-input-icon flexify">
							<?= \Voxel\get_icon_markup( $this->get_settings_for_display('auth_email_ico') ) ?: \Voxel\svg( 'envelope.svg' ) ?>
							<input
								v-model="quick_register.email"
								type="email"
								placeholder="<?= esc_attr( _x( 'Your email address', 'cart summary', 'voxel' ) ) ?>"
								@input="quick_register.sent_code ? quick_register.sent_code = false : ''"
								:readonly="quick_register.sending_code || quick_register.registered"
								@keydown.enter="$refs.sendCode?.click()"
								class="ts-filter"
							>
						</div>
					</div>
					<?php if ( $config['guest_customers']['proceed_with_email']['require_verification'] ): ?>
						<div v-if="!quick_register.sent_code && /^\S+@\S+\.\S+$/.test(quick_register.email)" class="ts-form-group vx-1-1">
							<div :class="{'vx-disabled': quick_register.sending_code}">
								<a href="#" class="ts-btn ts-btn-1 form-btn" ref="sendCode" @click.prevent="sendEmailVerificationCode">
									<?= \Voxel\get_icon_markup( $this->get_settings_for_display('auth_email_ico') ) ?: \Voxel\svg( 'envelope.svg' ) ?>
									<?= _x( 'Send confirmation code', 'cart summary', 'voxel' ) ?>
								</a>
							</div>
						</div>
						<div v-if="quick_register.sent_code" class="ts-form-group vx-1-1">
							<label><?= esc_attr( _x( 'Confirmation code', 'cart summary', 'voxel' ) ) ?></label>
							<input
								ref="emailConfirmCode"
								type="text"
								maxlength="6"
								placeholder="<?= esc_attr( _x( 'Type your 6 digit code', 'cart summary', 'voxel' ) ) ?>"
								v-model="quick_register.code"
								:readonly="quick_register.registered"
								class="ts-filter"
							>
						</div>
					<?php endif ?>
				<?php endif ?>
				<div v-if="proof_of_ownership.status === 'optional'" class="tos-checkbox ts-form-group vx-1-1 switcher-label">
					<label @click.prevent="proof_of_ownership.enabled = !proof_of_ownership.enabled">
						<div class="ts-checkbox-container">
							<label class="container-checkbox">
								<input :checked="proof_of_ownership.enabled" type="checkbox" tabindex="0" class="hidden">
								<span class="checkmark"></span>
							</label>
						</div>
						<?= _x( 'Add proof of ownership?', 'cart summary', 'voxel' ) ?>
					</label>
				</div>
				<div v-if="proof_of_ownership.status === 'required' || proof_of_ownership.enabled" class="ts-form-group vx-1-1">
					<label>
						<?= _x( 'Proof of ownership', 'cart summary', 'voxel' ) ?>
						<div class="vx-dialog">
							<?= \Voxel\get_icon_markup( $this->get_settings_for_display('info_icon') ) ?: \Voxel\svg( 'info.svg' ) ?>
							<div class="vx-dialog-content min-scroll">
								<p><?= _x( 'Upload a business document to verify your ownership', 'cart summary', 'voxel' ) ?></p>
							</div>
						</div>
					</label>
					<file-upload
						v-model="proof_of_ownership.files"
						:sortable="false"
						:allowed-file-types="config.files.allowed_file_types.join(',')"
						:max-file-count="config.files.max_count"
					></file-upload>
				</div>
				<div class="tos-checkbox ts-form-group vx-1-1 switcher-label">
					<label @click.prevent="toggleComposer">
						<div class="ts-checkbox-container">
							<label class="container-checkbox">
								<input :checked="order_notes.enabled" type="checkbox" tabindex="0" class="hidden">
								<span class="checkmark"></span>
							</label>
						</div>
						<?= _x( 'Add order notes?', 'cart summary', 'voxel' ) ?>
					</label>
				</div>
				<div v-if="order_notes.enabled" class="ts-form-group vx-1-1">
					<textarea
						ref="orderNotes"
						:value="order_notes.content"
						@input="order_notes.content = $event.target.value; resizeComposer();"
						placeholder="<?= esc_attr( _x( 'Add notes about your order', 'cart summary', 'voxel' ) ) ?>"
						class="autofocus ts-filter"
					></textarea>
					<textarea ref="_orderNotes" disabled style="height:5px;position:fixed;top:-9999px;left:-9999px;visibility:hidden;"></textarea>
				</div>
			</div>
			<?php
				if(is_user_logged_in()) {
					$user = \Voxel\current_user();
					$membership = $user->get_membership();
					$member = get_user_plan();
					$user_id = get_current_user_id();
					$traveller_num = traveller_num(); 
				}
				
			?>
			<?php if(is_user_logged_in() && traveller_num() > 0): ?>
				<li class="sin-discount" style="list-style: none">
					<div class="item-name">
						<div style="display: flex;align-items: center;">
							<div class="bui-group__item" style="margin-right: 5px;">
								<svg class="bk-icon -streamline-accounting_bills" fill="#008009" height="20" width="20" viewBox="0 0 24 24" role="presentation" aria-hidden="true" focusable="false"><path d="M4.125 8.25a.375.375 0 1 1 0-.75.375.375 0 0 1 0 .75.75.75 0 0 0 0-1.5 1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25.75.75 0 0 0 0 1.5zm12.75 5.25a.375.375 0 1 1 0-.75.375.375 0 0 1 0 .75.75.75 0 0 0 0-1.5 1.125 1.125 0 1 0 0 2.25 1.125 1.125 0 0 0 0-2.25.75.75 0 0 0 0 1.5zm2.625-3V15a.75.75 0 0 1-.75.75H2.25A.75.75 0 0 1 1.5 15V6a.75.75 0 0 1 .75-.75h16.5a.75.75 0 0 1 .75.75v4.5zm1.5 0V6a2.25 2.25 0 0 0-2.25-2.25H2.25A2.25 2.25 0 0 0 0 6v9a2.25 2.25 0 0 0 2.25 2.25h16.5A2.25 2.25 0 0 0 21 15v-4.5zm-8.25 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0zm1.5 0a3.75 3.75 0 1 0-7.5 0 3.75 3.75 0 0 0 7.5 0zM22.5 9v9a.75.75 0 0 1-.75.75H5.25a.75.75 0 0 0 0 1.5h16.5A2.25 2.25 0 0 0 24 18V9a.75.75 0 0 0-1.5 0z"></path></svg>
							</div>
							<?php if ($member !='default' && $membership): ?>
								<p style="color: #00826F;"><strong><?= _x(traveller_num() . ' Traveller Membership Rate', 'product form', 'voxel') ?></strong></p>
							<?php elseif ($member =='default' && $membership): ?>
								<p style="color: #00826F;"><strong><?= _x(' Non Membership Rate', 'product form', 'voxel') ?></strong></p>
							<?php endif; ?>
						</div>
					</div>
					<div class="ts-item-price sin-discount-no" style="display: flex;align-items: center; flex-direction: row-reverse;">
						<p style="color: #008009;"></p>
					</div>
				</li>
			<?php endif; ?>
			<div class="checkout-section">
				<?php if(is_user_logged_in() && traveller_num() > 0): ?>
					<ul class="ts-cost-calculator simplify-ul flexify">
						<li class="ts-total">
							<div class="ts-item-name due-arival">
								<p><?= _x( 'Total due on arrival', 'cart summary', 'voxel' ) ?></p>
							</div>
							<div class="ts-item-price">
								<p>{{ currencyFormat( getSubtotal() ) }}</p>
							</div>
						</li>
						<li class="due-today">
							<div class="ts-item-name">
								<p><?= _x( 'Deposit due today', 'cart summary', 'voxel' ) ?></p>
							</div>
							<div class="ts-item-price">
								<p></p>
							</div>
						</li>
					</ul>
				<?php else: ?>
					<ul class="ts-cost-calculator simplify-ul flexify">
						<li class="ts-total">
							<div class="ts-item-name">
								<p><?= _x( 'Subtotal', 'cart summary', 'voxel' ) ?></p>
							</div>
							<div class="ts-item-price">
								<p>{{ currencyFormat( getSubtotal() ) }}</p>
							</div>
						</li>
					</ul>
				<?php endif; ?>	
				<a href="#" class="ts-btn ts-btn-2 form-btn" @click.prevent="!processing ? submit() : null" :class="{'ts-loading-btn': processing, 'vx-disabled': !canProceedWithPayment()}">
					<div v-if="processing" class="ts-loader-wrapper">
						<span class="ts-loader"></span>
					</div>
					<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_checkout_icon') ) ?: \Voxel\svg( 'bag-2.svg' ) ?>
					<?= _x( 'Pay now', 'cart summary', 'voxel' ) ?>
				</a>
			</div>
		<?php else: ?>
			<div class="checkout-section">
				<ul class="ts-cost-calculator simplify-ul flexify">
				  <li class="ts-total">
				    <div class="ts-item-name">
				      <p><?= _x( 'Subtotal', 'cart summary', 'voxel' ) ?></p>
				    </div>
				    <div class="ts-item-price">
				      <p>{{ currencyFormat( getSubtotal() ) }}</p>
				    </div>
				  </li>
				</ul>
				<a href="<?= esc_url( $auth_link ) ?>" class="ts-btn ts-btn-2 form-btn">
					<div v-if="processing" class="ts-loader-wrapper">
						<span class="ts-loader"></span>
					</div>
					<?= \Voxel\get_icon_markup( $this->get_settings_for_display('auth_user_ico') ) ?: \Voxel\svg( 'user.svg' ) ?>
					<?= _x( 'Login to continue', 'cart summary', 'voxel' ) ?>
				</a>
			</div>
		<?php endif ?>
	</template>
	<!-- <pre debug>{{ proof_of_ownership }}</pre> -->
</div>

<script type="text/html" id="vx-file-upload">
	<div class="ts-form-group ts-file-upload inline-file-field vx-1-1" @dragenter="dragActive = true">
		<div class="drop-mask" v-show="dragActive && !reordering" @dragleave.prevent="dragActive = false" @drop.prevent="onDrop" @dragenter.prevent @dragover.prevent></div>
		<div class="ts-file-list">
			<div class="pick-file-input">
				<a href="#" @click.prevent="$refs.input.click()">
					<?= \Voxel\get_icon_markup( $this->get_settings_for_display('ts_upload_ico') ) ?: \Voxel\svg( 'upload.svg' ) ?>
					<?= _x( 'Upload', 'file field', 'voxel' ) ?>
				</a>
			</div>

			<template v-for="file, index in value">
				<div class="ts-file" :style="getStyle(file)" :class="{'ts-file-img': file.type.startsWith('image/')}">
					<div class="ts-file-info">
						<?= \Voxel\get_svg( 'cloud-upload' ) ?>
						<code>{{ file.name }}</code>
					</div>
					<a href="#" @click.prevent="value.splice(index,1)" class="ts-remove-file flexify">
						<?= \Voxel\get_icon_markup( $this->get_settings_for_display('trash_icon') ) ?: \Voxel\svg( 'trash-can.svg' ) ?>
					</a>
				</div>
			</template>
		</div>

		<input ref="input" type="file" class="hidden" :multiple="maxFileCount > 1" :accept="allowedFileTypes">
	</div>
</script>
