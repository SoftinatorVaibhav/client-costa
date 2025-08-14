<?php
/**
 * Admin membership settings.
 *
 * @since 1.0
 */

if ( ! defined('ABSPATH') ) {
	exit;
} ?>
<div class="sticky-top">
	<div class="vx-head x-container">
		<h2>Create a membership plan</h2>
	</div>
</div>
<div class="ts-spacer"></div>
<div class="x-container">

	<div class="x-row">
		<div class="x-col-4">
			<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ) ?>">
				<div class="x-row">
					<div class="ts-form-group x-col-12">
						<label>Label</label>
						<input name="membership_plan[label]" type="text" autocomplete="off" required>
					</div>
					<div class="ts-form-group x-col-12">
						<label>Key</label>
						<input name="membership_plan[key]" type="text" autocomplete="off" required><br>
					</div>
					<div class="ts-form-group x-col-12">
						<label>Description</label>
						<textarea name="membership_plan[description]"></textarea>
					</div>

					<div class="ts-form-group x-col-12">
						<label>Number of Adults</label>
						<input name="membership_plan[adults]" type="number" min="0" value="0" required>
					</div>

					<div class="ts-form-group x-col-12">
						<label>Number of Children</label>
						<input name="membership_plan[children]" type="number" min="0" value="0" required>
					</div>

					<div class="ts-form-group x-col-12">
						<label>Number of Students</label>
						<input name="membership_plan[students]" type="number" min="0" value="0" required>
					</div>

					<div class="ts-form-group x-col-12">
						<label>Number of Seniors</label>
						<input name="membership_plan[seniors]" type="number" min="0" value="0" required>
					</div>


					<div class="ts-form-group x-col-12">
						<input type="hidden" name="action" value="voxel_create_membership_plan">
						<?php wp_nonce_field( 'voxel_manage_membership_plans' ) ?>
						<button type="submit" class="ts-button ts-create-settings full-width">Create plan</button>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>