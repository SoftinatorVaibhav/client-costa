<?php
if ( ! defined('ABSPATH') ) {
	exit;
} ?>
<div id="vx-edit-plan" v-cloak data-config="<?= esc_attr( wp_json_encode( $config ) ) ?>">
	<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ) ?>" @submit.prevent>
		<div class="sticky-top">
			<div class="vx-head x-container">
				<h2><?= $plan->get_label() ?>

				</h2>
				<div class="">
					<a href="#" @click.prevent="save" class="ts-button ts-save-settings btn-shadow">
						<i class="las la-save icon-sm"></i>
						Save changes
					</a>
				</div>
			</div>
		</div>
		<div class="x-container">
			<div class="ts-theme-options-nav">
				<div class="ts-nav">
					<div class="ts-nav-item" :class="{'current-item': tab === 'general'}">
						<a href="#" @click.prevent="setTab('general', 'general')">
							<span class="item-icon all-center">
								<i class="las la-home"></i>
							</span>
							<span class="item-name">
								General
							</span>
						</a>
					</div>
					<div class="ts-nav-item" :class="{'current-item': tab === 'submissions'}">
						<a href="#" @click.prevent="setTab('submissions')">
							<span class="item-icon all-center">
								<i class="las la-grip-lines"></i>
							</span>
							<span class="item-name">
								Post submissions
							</span>
						</a>
					</div>
					<div class="ts-nav-item" :class="{'current-item': tab === 'pricing' && mode === 'live', 'vx-disabled': plan.key === 'default'}">
						<a href="#" @click.prevent="setTab('pricing', 'live')">
							<span class="item-icon all-center">
								<i class="las la-dollar-sign"></i>
							</span>
							<span class="item-name">
								Pricing
							</span>
						</a>
					</div>
					<div class="ts-nav-item" :class="{'current-item': tab === 'pricing' && mode === 'test', 'vx-disabled': plan.key === 'default'}">
						<a href="#" @click.prevent="setTab('pricing', 'test')">
							<span class="item-icon all-center">
								<i class="las la-dollar-sign"></i>
							</span>
							<span class="item-name">
								Pricing <span style="margin-left: 3px;font-size: 11px;background: #ffffff17;border-radius: 50px; padding: 5px;">Test mode</span>
							</span>
						</a>
					</div>
				</div>
			</div>
			<div class="ts-spacer"></div>
			<div v-if="tab === 'general'" class="ts-tab-content ts-container">
				<?php require_once locate_template( 'templates/backend/membership/components/plan-settings.php' ) ?>
			</div>
			<div v-else-if="tab === 'submissions'" class="ts-tab-content ts-container">
				<?php require_once locate_template( 'templates/backend/membership/components/plan-submissions.php' ) ?>
			</div>
			<div v-else-if="tab === 'pricing'" class="ts-tab-content ts-container">
				<?php require_once locate_template( 'templates/backend/membership/components/plan-pricing.php' ) ?>
			</div>
		</div>
	</form>
</div>

<script type="text/html" id="membership-edit-plan">
	<div>
		<div class="inner-tab x-row">
			<div class="x-col-12">
				<div v-if="tab === 'more'" :class="{'vx-disabled': loading}">
					<div class="x-row">
						<div v-if="plan.archived" class="ts-form-group x-col-12">
							<a href="#" class="ts-button ts-outline full-width" @click.prevent="archivePlan">Unarchive plan</a>
						</div>
						<div v-else class="ts-form-group x-col-12">
							<a href="#" class="ts-button ts-outline full-width" @click.prevent="archivePlan">Archive this plan</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</script>
