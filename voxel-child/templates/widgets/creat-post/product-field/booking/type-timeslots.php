<?php
if ( ! defined('ABSPATH') ) {
	exit;
} ?>






<div class="ts-form-group">
	<label><?= _x( 'Timeslots', 'product field', 'voxel' ) ?></label>
	<time-slots ref="timeslots" :booking="this"></time-slots>
</div>

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
		<?= _x( 'Exclude specific dates', 'product field timeslots', 'voxel' ) ?>
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
