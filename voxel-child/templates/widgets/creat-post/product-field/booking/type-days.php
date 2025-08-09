<?php
if ( ! defined('ABSPATH') ) {
	exit;
} ?>
<div class="ts-form-group" :class="{'vx-1-2': !field.props.quantity_per_slot.enabled}">
	<label><?= _x( 'Availability', 'product field booking', 'voxel' ) ?></label>
	<div class="input-container">
		<input type="number" v-model="value.availability.max_days" min="0" class="ts-filter">
		<span class="input-suffix"><?= _x( 'Days', 'product field booking', 'voxel' ) ?></span>
	</div>
</div>



<template v-if="field.props.quantity_per_slot.enabled">
	<div class="ts-form-group vx-1-2">
		<label><?= _x( 'Quantity per day', 'product field booking', 'voxel' ) ?></label>
		<input type="number" v-model="value.quantity_per_slot" class="ts-filter">
	</div>
</template>

<div class="ts-form-group switcher-label">
	<label @click.prevent="value.booking_mode = value.booking_mode === 'date_range' ? 'single_day' : 'date_range'">
		<div class="switch-slider">
			<div class="onoffswitch">
				<input type="checkbox" class="onoffswitch-checkbox" :checked="value.booking_mode === 'single_day'">
				<label class="onoffswitch-label"></label>
			</div>
		</div>
		<?= _x( 'Dates are booked individually', 'product field booking', 'voxel' ) ?>
	</label>
</div>

<template v-if="value.booking_mode === 'date_range'">
	<div class="ts-form-group switcher-label">
		<label>
			<div class="switch-slider">
				<div class="onoffswitch">
					<input type="checkbox" class="onoffswitch-checkbox" v-model="value.date_range.set_custom_limits">
					<label
						class="onoffswitch-label"
						@click.prevent="value.date_range.set_custom_limits = ! value.date_range.set_custom_limits"
					></label>
				</div>
			</div>
			<?= _x( 'Set minimum and maximum range length', 'product field booking', 'voxel' ) ?>
		</label>
	</div>

	<template v-if="value.date_range.set_custom_limits">
		<div class="ts-form-group vx-1-2">
			<label><?= _x( 'Minimum range length', 'product field booking', 'voxel' ) ?></label>
			<div class="input-container">
				<input type="number" v-model="value.date_range.min_length" min="0" class="ts-filter">
				<span class="input-suffix"><?= _x( 'Days', 'product field booking', 'voxel' ) ?></span>
			</div>
		</div>
		<div class="ts-form-group vx-1-2">
			<label><?= _x( 'Maximum range length', 'product field booking', 'voxel' ) ?></label>
			<div class="input-container">
				<input type="number" v-model="value.date_range.max_length" min="0" class="ts-filter">
				<span class="input-suffix"><?= _x( 'Days', 'product field booking', 'voxel' ) ?></span>
			</div>
		</div>
	</template>
</template>

<template v-if="field.props.booking_type === 'days'">
	<weekday-exclusions :booking="this"></weekday-exclusions>
</template>

<div class="ts-form-group switcher-label">
	<label>
		<div class="switch-slider">
			<div class="onoffswitch">
				<input type="checkbox" class="onoffswitch-checkbox" v-model="value.excluded_days_enabled">
				<label
					class="onoffswitch-label"
					@click.prevent="value.excluded_days_enabled = ! value.excluded_days_enabled"
				></label>
			</div>
		</div>
		<?= _x( 'Exclude specific dates', 'product field booking', 'voxel' ) ?>
	</label>
</div>

<div v-if="value.excluded_days_enabled" class="ts-form-group">
	<label>
		<?= _x( 'Calendar', 'product field', 'voxel' ) ?>
		<div class="vx-dialog">
			<?= \Voxel\get_icon_markup( $this->get_settings_for_display('info_icon') ) ?: \Voxel\svg( 'info.svg' ) ?>
			<div class="vx-dialog-content min-scroll">
				<p><?= _x( 'Availability visualization based on your settings. Click to exclude specific days', 'product field', 'voxel' ) ?></p>
			</div>
		</div>
	</label>
	<booking-calendar ref="calendar" :booking="this"></booking-calendar>
</div>
