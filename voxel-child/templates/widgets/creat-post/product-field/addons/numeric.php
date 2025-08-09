<?php
if ( ! defined('ABSPATH') ) {
	exit;
} ?>
<script type="text/html" id="product-addon-numeric">
	<div class="ts-form-group switcher-label">
		<template v-if="!addon.required">
			<label>
				<div class="switch-slider">
					<div class="onoffswitch">
						<input type="checkbox" class="onoffswitch-checkbox" v-model="value.enabled">
						<label class="onoffswitch-label" @click.prevent="value.enabled = !value.enabled"></label>
					</div>
				</div>
				{{ addon.label }}
				<div class="vx-dialog" v-if="addon.description">
					<?= \Voxel\get_icon_markup( $this->get_settings_for_display('info_icon') ) ?: \Voxel\svg( 'info.svg' ) ?>
					<div class="vx-dialog-content min-scroll">
						<p>{{ addon.description }}</p>
					</div>
				</div>
			</label>
		</template>
		<template v-else>
			<label>
				{{ addon.label }}
				<div class="vx-dialog" v-if="addon.description">
					<?= \Voxel\get_icon_markup( $this->get_settings_for_display('info_icon') ) ?: \Voxel\svg( 'info.svg' ) ?>
					<div class="vx-dialog-content min-scroll">
						<p>{{ addon.description }}</p>
					</div>
				</div>
			</label>
		</template>
		<div v-if="addon.required || value.enabled" class="ts-field-repeater">
			<div class="medium form-field-grid">
				<div class="ts-form-group">
					<label>
						<?= _x( 'Price', 'product field', 'voxel' ) ?>
						<div class="vx-dialog" >
							<?= \Voxel\get_icon_markup( $this->get_settings_for_display('info_icon') ) ?: \Voxel\svg( 'info.svg' ) ?>
						</div>
					</label>
					<div class="input-container">
						<input type="number" v-model="value.price" class="ts-filter" placeholder="<?= _x( 'Rack price', 'product field', 'voxel' ) ?>" min="0">
						<span class="input-suffix"><?= \Voxel\get('settings.stripe.currency') ?></span>
					</div>
					<br>
					<div class="input-container">
						<input type="number" v-model="value.net" class="ts-filter" placeholder="<?= _x( 'Net price', 'product field', 'voxel' ) ?>" min="0">
						<span class="input-suffix"><?= \Voxel\get('settings.stripe.currency') ?></span>
					</div>

				</div>

<template>
  <div>
    <!-- Hidden Min Number Field -->
    <div class="ts-form-group vx-1-2" style="display: none;">
      <label>
        <?= _x( 'Min number', 'product field', 'voxel' ) ?>
      </label>
      <div class="input-container">
        <input
          type="hidden"
          v-model="value.min"
          class="ts-filter"
          placeholder="<?= esc_attr( _x( 'Min', 'product field', 'voxel' ) ) ?>"
          min="0"
        >
      </div>
    </div>

    <!-- Hidden Max Number Field -->
    <div class="ts-form-group vx-1-2" style="display: none;">
      <label>
        <?= _x( 'Max number', 'product field', 'voxel' ) ?>
      </label>
      <div class="input-container">
        <input
          type="hidden"
          v-model="value.max"
          class="ts-filter"
          placeholder="<?= esc_attr( _x( 'Max', 'product field', 'voxel' ) ) ?>"
          min="0"
        >
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'MyComponent',
  data() {
    return {
      value: {
        min: 1,          // Default minimum value
        max: Infinity    // Default maximum value (represents no upper limit)
      }
    }
  }
}
</script>

<style scoped>
/* Optional: add your component-specific styles here */
</style>

			</div>
		</div>
	</div>
</script>
